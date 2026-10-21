import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/dashboard/application/education_interest_controller.dart';
import 'package:sirati/features/dashboard/presentation/education_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/theme/app_theme.dart';

import '../../support/contrast_probe.dart';

/// Device storage that outlives a single ProviderContainer (an app restart).
class _Store extends EducationInterestStore {
  bool saved = false;
  bool failRead = false;
  bool failSave = false;
  int saves = 0;

  @override
  Future<bool> read() async {
    if (failRead) throw StateError('keystore');
    return saved;
  }

  @override
  Future<void> save() async {
    saves++;
    if (failSave) throw StateError('disk');
    saved = true;
  }
}

void main() {
  group('EducationInterestController', () {
    ProviderContainer boot(_Store store) {
      final c = ProviderContainer(
        overrides: [educationInterestStoreProvider.overrideWithValue(store)],
      );
      c.listen(educationInterestProvider, (_, __) {});
      addTearDown(c.dispose);
      return c;
    }

    test('interest survives a restart', () async {
      final store = _Store();
      final first = boot(store);
      expect(await first.read(educationInterestProvider.future), isFalse);
      await first.read(educationInterestProvider.notifier).register();
      expect(first.read(educationInterestProvider).value, isTrue);

      final afterRestart = boot(store);
      expect(await afterRestart.read(educationInterestProvider.future), isTrue);
    });

    test('registering twice writes once', () async {
      final store = _Store();
      final c = boot(store);
      await c.read(educationInterestProvider.future);
      for (var i = 0; i < 3; i++) {
        await c.read(educationInterestProvider.notifier).register();
      }
      expect(store.saves, 1);
    });

    test('storage failures never throw into the UI', () async {
      final store = _Store()
        ..failRead = true
        ..failSave = true;
      final c = boot(store);
      expect(await c.read(educationInterestProvider.future), isFalse);
      await c.read(educationInterestProvider.notifier).register();
      expect(c.read(educationInterestProvider).value, isTrue);
    });
  });

  group('EducationScreen', () {
    setUp(() => AppLocale.languageCode.value = 'en');

    Widget app(_Store store, ThemeData theme) => ProviderScope(
          overrides: [educationInterestStoreProvider.overrideWithValue(store)],
          child: MaterialApp(
            theme: theme,
            locale: const Locale('en'),
            supportedLocales: AppLocalizations.supportedLocales,
            localizationsDelegates: AppLocalizations.localizationsDelegates,
            home: const Scaffold(body: EducationScreen()),
          ),
        );

    testWidgets('Notify me records interest without promising a message',
        (tester) async {
      tester.view.physicalSize = const Size(1080, 3200);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);
      final store = _Store();
      await tester.pumpWidget(app(store, AppTheme.lightFor(arabic: false)));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Notify Me on Launch'));
      await tester.pumpAndSettle();

      expect(store.saved, isTrue);
      expect(find.text('Interest Registered'), findsOneWidget);
      expect(find.textContaining('notified'), findsNothing,
          reason: 'no launch-notification pipeline exists yet');
    });

    for (final (mode, theme) in [
      ('light', AppTheme.lightFor(arabic: false)),
      ('dark', AppTheme.darkFor(arabic: false)),
    ]) {
      for (final registered in [false, true]) {
        testWidgets('all text is AA in $mode (registered: $registered)',
            (tester) async {
          tester.view.physicalSize = const Size(1080, 3200);
          tester.view.devicePixelRatio = 1;
          addTearDown(tester.view.reset);
          await tester.pumpWidget(app(_Store()..saved = registered, theme));
          await tester.pumpAndSettle();
          expectAllTextAa('education $mode registered=$registered');
        });
      }
    }
  });
}
