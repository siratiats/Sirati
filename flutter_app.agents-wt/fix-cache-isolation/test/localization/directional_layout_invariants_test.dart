import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/shared/widgets/directional_icon.dart';

void main() {
  Widget wrap({
    required TextDirection direction,
    required Widget child,
  }) {
    return MaterialApp(
      home: Directionality(
        textDirection: direction,
        child: Scaffold(body: child),
      ),
    );
  }

  testWidgets(
      'DirectionalIcon and start/end padding mirror between LTR and RTL',
      (tester) async {
    Future<Rect> pump(TextDirection direction) async {
      await tester.pumpWidget(
        wrap(
          direction: direction,
          child: const Align(
            alignment: AlignmentDirectional.centerStart,
            child: Padding(
              padding: EdgeInsetsDirectional.only(start: 24, end: 4),
              child: DirectionalIcon(
                Icons.arrow_forward,
                size: 24,
                key: Key('chevron'),
              ),
            ),
          ),
        ),
      );
      await tester.pump();
      return tester.getRect(find.byKey(const Key('chevron')));
    }

    final ltr = await pump(TextDirection.ltr);
    final rtl = await pump(TextDirection.rtl);
    const surfaceWidth = 800.0;

    expect(ltr.left, greaterThanOrEqualTo(24));
    expect(ltr.left, lessThan(surfaceWidth / 2));
    expect(rtl.right, lessThanOrEqualTo(surfaceWidth - 24 + 0.5));
    expect(rtl.left, greaterThan(surfaceWidth / 2));
    expect((ltr.left - (surfaceWidth - rtl.right)).abs(), lessThan(1.0));
  });

  testWidgets('back chevron points toward start in both directions',
      (tester) async {
    await tester.pumpWidget(
      wrap(
        direction: TextDirection.rtl,
        child: const DirectionalIcon(Icons.arrow_back, key: Key('back')),
      ),
    );
    expect(find.byType(DirectionalIcon), findsOneWidget);
    expect(find.byType(Transform), findsWidgets);
  });
}
