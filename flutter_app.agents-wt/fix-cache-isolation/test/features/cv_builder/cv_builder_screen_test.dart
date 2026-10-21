import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sirati/features/cv_builder/data/cv_repository.dart';
import 'package:sirati/features/cv_builder/data/cv_repository_provider.dart';
import 'package:sirati/features/cv_builder/presentation/cv_builder/save_status_badge.dart';
import 'package:sirati/features/cv_builder/presentation/cv_builder_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/cv_document.dart';
import 'package:sirati/shared/theme/app_theme.dart';

import '../../support/contrast_probe.dart';

class _FailingRepo extends LocalCvRepository {
  _FailingRepo() : super(engine: MemoryCvStorageEngine());
  bool fail = false;

  @override
  Future<void> saveCv(CvDocument document, {bool touchUpdatedAt = true}) {
    if (fail) return Future.error(StateError('disk full'));
    return super.saveCv(document, touchUpdatedAt: touchUpdatedAt);
  }
}

Widget _app(CvRepository repo, {ThemeData? theme}) => ProviderScope(
      overrides: [cvRepositoryProvider.overrideWithValue(repo)],
      child: MaterialApp(
        theme: theme ?? AppTheme.lightFor(arabic: false),
        locale: const Locale('en'),
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        home: Builder(
          builder: (context) => Scaffold(
            body: Center(
              child: TextButton(
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => const CvBuilderScreen(cvId: 'cv1'),
                  ),
                ),
                child: const Text('open'),
              ),
            ),
          ),
        ),
      ),
    );

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));

  Future<_FailingRepo> openBuilder(WidgetTester tester) async {
    final repo = _FailingRepo();
    await repo.saveCv(CvDocument.createEmpty(id: 'cv1', title: 'My CV'));
    await tester.pumpWidget(_app(repo));
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    expect(find.byType(CvBuilderScreen), findsOneWidget);
    return repo;
  }

  testWidgets('pressing Back twice leaves the builder only once',
      (tester) async {
    await openBuilder(tester);
    final navigator = tester.state<NavigatorState>(find.byType(Navigator));
    unawaited(navigator.maybePop());
    unawaited(navigator.maybePop());
    await tester.pumpAndSettle();

    expect(find.byType(CvBuilderScreen), findsNothing);
    expect(find.text('open'), findsOneWidget,
        reason: 'the route underneath must survive the second Back');
  });

  testWidgets('Save now shows the outcome of the write', (tester) async {
    final repo = await openBuilder(tester);
    final l10n = await AppLocalizations.delegate.load(const Locale('en'));

    // Edit the name field to dirty the document.
    await tester.enterText(find.byType(TextField).first, 'Sara');
    await tester.pump();

    repo.fail = true;
    await tester.tap(find.byTooltip(l10n.saveNow));
    await tester.pumpAndSettle();
    expect(find.text(l10n.cvSaveFailed), findsOneWidget);
    expect(find.text(l10n.cvSaved), findsNothing);

    ScaffoldMessenger.of(tester.element(find.byType(CvBuilderScreen)))
        .removeCurrentSnackBar();
    repo.fail = false;
    await tester.tap(find.byTooltip(l10n.saveNow));
    await tester.pumpAndSettle();
    expect(find.text(l10n.cvSaved), findsOneWidget);
  });

  for (final (mode, theme) in [
    ('light', AppTheme.lightFor(arabic: false)),
    ('dark', AppTheme.darkFor(arabic: false)),
  ]) {
    for (final (saving, dirty) in [
      (false, false),
      (false, true),
      (true, false)
    ]) {
      testWidgets('save badge is AA in $mode (saving=$saving dirty=$dirty)',
          (tester) async {
        await tester.pumpWidget(MaterialApp(
          theme: theme,
          locale: const Locale('en'),
          supportedLocales: AppLocalizations.supportedLocales,
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          home: Scaffold(
            appBar: AppBar(
              title: SaveStatusBadge(isSaving: saving, isDirty: dirty),
            ),
          ),
        ));
        await tester.pump();
        expectAllTextAa('badge $mode saving=$saving dirty=$dirty');
      });
    }
  }
}
