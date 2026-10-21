import 'dart:io';

import 'package:analyzer/dart/analysis/features.dart';
import 'package:analyzer/dart/analysis/utilities.dart';
import 'package:analyzer/dart/ast/ast.dart';
import 'package:analyzer/dart/ast/visitor.dart';
import 'package:flutter_test/flutter_test.dart';

/// SIRATI-11: package import boundaries, checked via the Dart AST.
///
/// Source regex is not used — [parseFile] walks real import/export directives.
/// One-line barrels are followed to their target before classifying a URI.
void main() {
  final lib = Directory('lib');

  test('lib/core, lib/shared, and lib/features exist', () {
    expect(Directory('lib/core').existsSync(), isTrue);
    expect(Directory('lib/shared').existsSync(), isTrue);
    expect(Directory('lib/features').existsSync(), isTrue);
  });

  test('core has zero dependencies on shared or features', () {
    final violations = <String>[];
    for (final file in _dartFiles(Directory('lib/core'))) {
      for (final uri in _importedUris(file)) {
        final resolved = _canonicalizeUri(uri, fromFile: file);
        if (_isPackage(resolved, 'shared/') ||
            _isPackage(resolved, 'features/')) {
          violations.add('${_rel(file, lib)} → $uri → $resolved');
        }
      }
    }
    expect(violations, isEmpty, reason: violations.join('\n'));
  });

  test('shared never depends on features', () {
    final violations = <String>[];
    for (final file in _dartFiles(Directory('lib/shared'))) {
      for (final uri in _importedUris(file)) {
        final resolved = _canonicalizeUri(uri, fromFile: file);
        if (_isPackage(resolved, 'features/')) {
          violations.add('${_rel(file, lib)} → $uri → $resolved');
        }
      }
    }
    expect(violations, isEmpty, reason: violations.join('\n'));
  });

  test('features do not import other features except composition roots', () {
    const composition = {'app', 'dashboard', 'localization'};
    final violations = <String>[];
    for (final file in _dartFiles(Directory('lib/features'))) {
      final rel = _rel(file, Directory('lib/features')).replaceAll('\\', '/');
      final parts = rel.split('/');
      if (parts.length < 2) continue;
      final feature = parts[0];
      if (composition.contains(feature)) continue;
      for (final uri in _importedUris(file)) {
        final resolved = _canonicalizeUri(uri, fromFile: file);
        if (!_isPackage(resolved, 'features/')) continue;
        final other = resolved
            .substring('package:sirati/features/'.length)
            .split('/')
            .first;
        if (other != feature && other != 'localization') {
          violations.add('$rel → $uri → $resolved');
        }
      }
    }
    expect(violations, isEmpty, reason: violations.join('\n'));
  });

  test('API base URL and Sentry DSN come from dart-define, not literals', () {
    final config = File('lib/core/network/api_config.dart');
    expect(config.existsSync(), isTrue);
    final unit = parseFile(
      path: _parsePath(config),
      featureSet: FeatureSet.latestLanguageVersion(),
    ).unit;

    final visitor = _FromEnvironmentVisitor();
    unit.accept(visitor);
    expect(visitor.names, contains('baseUrl'));
    expect(visitor.environmentKeys, contains('SIRATI_API_BASE_URL'));
    expect(config.readAsStringSync().contains('sk-'), isFalse);
  });

  test(
      'relative imports resolve against the importing file before classification',
      () {
    final fromCore = File('lib/core/utils/app_format.dart');
    expect(fromCore.existsSync(), isTrue);

    final relativeIntoFeatures = _canonicalizeUri(
      '../../features/dashboard/presentation/home_screen.dart',
      fromFile: fromCore,
    );
    expect(
      relativeIntoFeatures,
      'package:sirati/features/dashboard/presentation/home_screen.dart',
    );
    expect(_isPackage(relativeIntoFeatures, 'features/'), isTrue);

    final sibling = _canonicalizeUri('app_locale.dart', fromFile: fromCore);
    expect(sibling, 'package:sirati/core/utils/app_locale.dart');
    expect(_isPackage(sibling, 'features/'), isFalse);
    expect(_isPackage(sibling, 'shared/'), isFalse);

    final fromShared = File('lib/shared/theme/app_theme.dart');
    expect(fromShared.existsSync(), isTrue);
    final sharedIntoFeatures = _canonicalizeUri(
      '../../features/cv_builder/presentation/cv_builder_screen.dart',
      fromFile: fromShared,
    );
    expect(_isPackage(sharedIntoFeatures, 'features/'), isTrue);
  });
}

Iterable<File> _dartFiles(Directory dir) {
  if (!dir.existsSync()) return const [];
  return dir
      .listSync(recursive: true)
      .whereType<File>()
      .where((f) => f.path.endsWith('.dart'));
}

Iterable<String> _importedUris(File file) {
  final parsed = parseFile(
    path: _parsePath(file),
    featureSet: FeatureSet.latestLanguageVersion(),
  );
  final uris = <String>[];
  for (final directive in parsed.unit.directives) {
    if (directive is UriBasedDirective) {
      final uri = directive.uri.stringValue;
      if (uri != null) uris.add(uri);
    }
  }
  return uris;
}

bool _isPackage(String uri, String rest) {
  return uri.startsWith('package:sirati/$rest');
}

String _canonicalizeUri(String uri, {required File fromFile}) {
  var packageUri = uri;
  if (!uri.startsWith('package:sirati/')) {
    if (uri.startsWith('package:') || uri.startsWith('dart:')) {
      return uri;
    }
    final fromRel = _libRelative(fromFile);
    if (fromRel == null) return uri;
    final resolved = _resolveExportTarget(fromRel, uri);
    if (resolved == null) return uri;
    packageUri = 'package:sirati/$resolved';
  }
  var rel = packageUri.substring('package:sirati/'.length);
  final seen = <String>{};
  while (seen.add(rel)) {
    final file = File(
      _parsePath(File('lib/${rel.replaceAll('/', Platform.pathSeparator)}')),
    );
    if (!file.existsSync()) break;
    final parsed = parseFile(
      path: _parsePath(file),
      featureSet: FeatureSet.latestLanguageVersion(),
    );
    if (!_isBarrel(parsed.unit)) break;
    final exports =
        parsed.unit.directives.whereType<ExportDirective>().toList();
    if (exports.length != 1) break;
    final exportUri = exports.first.uri.stringValue;
    if (exportUri == null) break;
    final next = _resolveExportTarget(rel, exportUri);
    if (next == null) break;
    rel = next;
  }
  return 'package:sirati/$rel';
}

bool _isBarrel(CompilationUnit unit) {
  if (unit.declarations.isNotEmpty) return false;
  return unit.directives.any((directive) => directive is ExportDirective);
}

String? _resolveExportTarget(String fromRel, String exportUri) {
  if (exportUri.startsWith('package:sirati/')) {
    return exportUri.substring('package:sirati/'.length);
  }
  if (exportUri.startsWith('package:') || exportUri.startsWith('dart:')) {
    return null;
  }
  final parts = fromRel.split('/')..removeLast();
  for (final segment in exportUri.split('/')) {
    if (segment.isEmpty || segment == '.') continue;
    if (segment == '..') {
      if (parts.isNotEmpty) parts.removeLast();
    } else {
      parts.add(segment);
    }
  }
  return parts.join('/');
}

String? _libRelative(File file) {
  final libPath = Directory('lib').absolute.uri.path;
  final filePath = file.absolute.uri.path;
  if (!filePath.startsWith(libPath)) return null;
  return filePath.substring(libPath.length);
}

String _parsePath(File file) {
  return file.absolute.path.replaceAll('/', Platform.pathSeparator);
}

String _rel(File file, Directory root) {
  return file.uri.path.substring(root.uri.path.length);
}

class _FromEnvironmentVisitor extends RecursiveAstVisitor<void> {
  final names = <String>[];
  final environmentKeys = <String>[];

  @override
  void visitVariableDeclaration(VariableDeclaration node) {
    final init = node.initializer;
    if (init is MethodInvocation && init.methodName.name == 'fromEnvironment') {
      names.add(node.name.lexeme);
      final args = init.argumentList.arguments;
      if (args.isNotEmpty) {
        final first = args.first;
        if (first is SimpleStringLiteral) {
          environmentKeys.add(first.value);
        }
      }
    }
    super.visitVariableDeclaration(node);
  }
}
