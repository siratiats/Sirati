import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/features/dashboard/presentation/home/dashboard_action_card.dart';
import 'package:sirati/shared/theme/app_theme.dart';

/// Nearest painted background behind [text] in the pumped tree.
Color _backgroundOf(WidgetTester tester, Finder text) {
  final boxes = tester.widgetList<DecoratedBox>(
    find.ancestor(of: text, matching: find.byType(DecoratedBox)),
  );
  for (final box in boxes) {
    final decoration = box.decoration;
    if (decoration is BoxDecoration && decoration.color != null) {
      return decoration.color!;
    }
  }
  fail('no painted background above $text');
}

void main() {
  for (final (name, theme) in [
    ('light', AppTheme.lightFor(arabic: false)),
    ('dark', AppTheme.darkFor(arabic: false)),
  ]) {
    for (final tone in DashboardActionTone.values) {
      testWidgets('$tone in $name mode: every text is AA on what it sits on',
          (tester) async {
        await tester.pumpWidget(MaterialApp(
          theme: theme,
          home: Scaffold(
            body: DashboardActionCard(
              title: 'Title',
              subtitle: 'Subtitle',
              buttonLabel: 'Button',
              tone: tone,
              icon: Icons.star,
              watermark: Icons.star,
              onTap: () {},
            ),
          ),
        ));

        final texts = find.descendant(
          of: find.byType(DashboardActionCard),
          matching: find.byType(Text),
        );
        expect(texts, findsNWidgets(3));
        for (final element in texts.evaluate()) {
          final text = element.widget as Text;
          final finder = find.byWidget(text);
          final background = _backgroundOf(tester, finder);
          final foreground = Color.alphaBlend(text.style!.color!, background);
          expect(
            AppContrast.ratio(foreground, background),
            greaterThanOrEqualTo(4.5),
            reason: '"${text.data}" ($tone, $name)',
          );
        }
      });
    }
  }
}
