import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/shared/theme/app_theme.dart';

void main() {
  test('spacing scale is the only allowed layout steps', () {
    expect(AppSpacing.scale, [4, 8, 12, 16, 20, 24, 32]);
  });

  test('radius and elevation tokens exist per surface type', () {
    expect(AppRadius.sm, 10);
    expect(AppRadius.md, 14);
    expect(AppRadius.lg, 16);
    expect(AppRadius.xl, 18);
    expect(AppElevation.none, 0);
    expect(AppElevation.card, 1);
    expect(AppElevation.raised, 3);
    expect(AppElevation.sheet, 6);
    expect(AppElevation.dialog, 12);
  });

  test('component library does not use magic EdgeInsets numbers', () {
    final dir = Directory('lib/shared/widgets/components');
    expect(dir.existsSync(), isTrue);

    final allowed = {
      ...AppSpacing.scale.map((v) => v.toInt()),
      AppTouchTarget.min.toInt(),
    };
    final magic =
        RegExp(r'EdgeInsets\.(all|symmetric|only|fromLTRB)\(([^\)]*)\)');
    final numbers = RegExp(r'(\d+(?:\.\d+)?)');
    final offenders = <String>[];

    for (final file in dir.listSync().whereType<File>()) {
      if (!file.path.endsWith('.dart')) continue;
      final source = file.readAsStringSync();
      for (final match in magic.allMatches(source)) {
        for (final n in numbers.allMatches(match.group(2)!)) {
          final value = double.parse(n.group(1)!);
          if (!allowed.contains(value.round())) {
            offenders.add('${file.uri.pathSegments.last}: ${match.group(0)}');
          }
        }
      }
    }

    expect(offenders, isEmpty, reason: offenders.join('\n'));
  });

  /// Repo-wide hardcoded-color ratchet (SIRATI-90).
  ///
  /// Every .dart file under lib/ is scanned except lib/shared/theme/, where the
  /// palettes are DEFINED. A line may opt out with
  /// `// ignore: hardcoded_color -- <reason>` (see cv_live_preview_pane.dart,
  /// which simulates A4 paper and is intentionally theme-independent).
  ///
  /// [legacyColorDebt] is the pre-existing debt, frozen at its current count.
  /// The list may only shrink: adding a violation to a clean file fails, adding
  /// one to a debt file fails, and clearing a debt file fails until its entry is
  /// deleted here. Do not add new entries.
  const legacyColorDebt = <String, int>{
  };

  test(
      'no raw Color literals, Colors.*, or AppColors.* outside the theme layer',
      () {
    final raw = RegExp(
        r'(Color\s*\(\s*0x|Colors\.[a-zA-Z]+|Color\.fromRGBO|AppColors\.)');
    final escapeHatch = RegExp(r'//\s*ignore:\s*hardcoded_color\s*--\s*\S+');

    final counts = <String, int>{};
    final offenders = <String>[];

    for (final file in Directory('lib')
        .listSync(recursive: true)
        .whereType<File>()
        .where((f) => f.path.endsWith('.dart'))) {
      final path = file.path.replaceAll('\\', '/');
      if (path.startsWith('lib/shared/theme/')) continue;

      final lines = file.readAsLinesSync();
      for (int i = 0; i < lines.length; i++) {
        final line = lines[i];
        if (raw.hasMatch(line) && !escapeHatch.hasMatch(line)) {
          counts[path] = (counts[path] ?? 0) + 1;
          if (!legacyColorDebt.containsKey(path)) {
            offenders.add('$path:${i + 1}: ${line.trim()}');
          }
        }
      }
    }

    expect(
      offenders,
      isEmpty,
      reason: 'Hardcoded color in a file with no legacy debt. Use semantic '
          'tokens from SiratiColors, or document an intentional escape hatch '
          'with `// ignore: hardcoded_color -- <reason>`:\n'
          '${offenders.join('\n')}',
    );

    // Ratchet: debt may shrink, never grow.
    final grown = <String>[];
    final cleared = <String>[];
    legacyColorDebt.forEach((path, budget) {
      final actual = counts[path] ?? 0;
      if (actual > budget) {
        grown.add('$path: $actual > allowed $budget');
      } else if (actual == 0) {
        cleared.add(path);
      }
    });

    expect(grown, isEmpty,
        reason: 'Hardcoded-color debt increased:\n${grown.join('\n')}');
    expect(cleared, isEmpty,
        reason: 'These files are clean now -- delete their entries from '
            'legacyColorDebt so the ratchet keeps holding:\n'
            '${cleared.join('\n')}');
  });

  test(
      'component library uses outline and AppShadows rather than legacy tokens',
      () {
    final dir = Directory('lib/shared/widgets/components');
    final legacyTokens = RegExp(r'\bc\.(border|softShadow)\b');
    final offenders = <String>[];
    for (final file in dir.listSync().whereType<File>()) {
      if (!file.path.endsWith('.dart')) continue;
      final source = file.readAsStringSync();
      for (final match in legacyTokens.allMatches(source)) {
        offenders.add('${file.uri.pathSegments.last}: ${match.group(0)}');
      }
    }
    expect(offenders, isEmpty, reason: offenders.join('\n'));
  });

  for (final (mode, c) in [
    ('light', SiratiColors.light),
    ('dark', SiratiColors.dark),
  ]) {
    test('filled buttons carry white labels at AA ($mode)', () {
      // Product decision: primary actions read white-on-teal. The fill is
      // tuned so that stays >= 4.5:1, not the other way round.
      expect(c.onPrimary, const Color(0xFFFFFFFF));
      for (final fill in [c.primary, c.primaryContainer]) {
        expect(AppContrast.ratio(c.onPrimary, fill), greaterThanOrEqualTo(4.5),
            reason: 'onPrimary on $fill ($mode)');
      }
    });
  }
}
