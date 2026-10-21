import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/app_snack_bar.dart';
import 'package:sirati/shared/widgets/empty_state.dart';
import 'package:sirati/shared/widgets/form_fields.dart';
import 'package:sirati/shared/widgets/screen_header.dart';

import '../support/contrast_probe.dart';

Widget _app(ThemeData theme, Widget body) => MaterialApp(
      theme: theme,
      locale: const Locale('en'),
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      home: Scaffold(body: body),
    );

void main() {
  setUp(() => AppLocale.languageCode.value = 'en');

  for (final (mode, theme) in [
    ('light', AppTheme.lightFor(arabic: false)),
    ('dark', AppTheme.darkFor(arabic: false)),
  ]) {
    testWidgets('every snackbar variant is AA in $mode, icon >= 3:1',
        (tester) async {
      await tester.pumpWidget(_app(theme, const SizedBox()));
      final context = tester.element(find.byType(Scaffold));
      final show = <void Function()>[
        () => AppSnackBar.error(context, 'Error message'),
        () => AppSnackBar.success(context, 'Saved'),
        () => AppSnackBar.warning(context, 'Careful'),
        () => AppSnackBar.info(context, 'Heads up'),
      ];
      for (final variant in show) {
        ScaffoldMessenger.of(context).removeCurrentSnackBar();
        variant();
        await tester.pumpAndSettle();
        expectAllTextAa('snackbar $mode');
        final icon = tester.widget<Icon>(find.descendant(
          of: find.byType(SnackBar),
          matching: find.byType(Icon),
        ));
        final bg = paintedBackgroundsOf(
          tester.element(find.byWidget(icon)),
        ).single;
        expect(AppContrast.ratio(icon.color!, bg), greaterThanOrEqualTo(3.0),
            reason: 'snackbar icon ($mode)');
      }
    });

    test('outlined buttons show state through their outline ($mode)', () {
      final side = theme.outlinedButtonTheme.style!.side!;
      final surface = theme.colorScheme.surface;
      final active = side.resolve(<WidgetState>{})!.color;
      final disabled = side.resolve({WidgetState.disabled})!.color;
      // Enabled outline is a real control boundary (>= 3:1); the disabled
      // one must read as inactive, i.e. not the same strong outline.
      expect(AppContrast.ratio(active, surface), greaterThanOrEqualTo(3.0));
      expect(AppContrast.ratio(disabled, surface), lessThan(3.0));
    });

    testWidgets('form feedback, banner, badge and empty state are AA ($mode)',
        (tester) async {
      await tester.pumpWidget(_app(
        theme,
        ListView(
          children: [
            Builder(
              builder: (context) => Text(
                'Please enter your email',
                style: AppFormStyles.errorTextStyle(context.sirati),
              ),
            ),
            Builder(
              builder: (context) => Text(
                'Looks good',
                style: AppFormStyles.successTextStyle(context.sirati),
              ),
            ),
            const AppFormErrorBanner(message: 'Fix the highlighted fields'),
            ScreenHeader(
              english: true,
              title: 'Home',
              icon: Icons.home,
              unreadCount: 12,
              onNotifications: () {},
            ),
            AppEmptyState(
              icon: Icons.inbox,
              title: 'Nothing yet',
              subtitle: 'Start here',
              actionLabel: 'Create CV',
              onAction: () {},
              scrollable: false,
            ),
          ],
        ),
      ));
      await tester.pumpAndSettle();
      expectAllTextAa('shared components $mode');
    });
  }
}
