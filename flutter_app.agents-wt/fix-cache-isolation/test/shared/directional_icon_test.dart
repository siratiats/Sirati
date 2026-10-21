import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/shared/widgets/directional_icon.dart';

/// Horizontal scale applied to the painted glyph, relative to the icon box.
double _netScaleX(WidgetTester tester) {
  final box = tester.renderObject<RenderBox>(find.byType(DirectionalIcon));
  final glyph = tester.renderObject<RenderBox>(find.descendant(
    of: find.byType(DirectionalIcon),
    matching: find.byType(RichText),
  ));
  return glyph.getTransformTo(box).storage[0];
}

void main() {
  // Every glyph the widget claims to handle, plus ones that do not mirror
  // on their own, so both code paths are covered.
  final icons = <IconData>{
    ...DirectionalIcon.directionalIcons,
    Icons.keyboard_arrow_right_rounded,
    Icons.add_rounded,
  };

  for (final dir in TextDirection.values) {
    testWidgets('each glyph is mirrored exactly once in RTL ($dir)',
        (tester) async {
      for (final icon in icons) {
        await tester.pumpWidget(Directionality(
          textDirection: dir,
          child: Center(child: DirectionalIcon(icon, size: 24)),
        ));
        final scale = _netScaleX(tester);
        expect(
          scale,
          dir == TextDirection.rtl ? lessThan(0) : greaterThan(0),
          reason: '${icon.codePoint.toRadixString(16)} '
              '(matchTextDirection: ${icon.matchTextDirection}) in $dir',
        );
      }
    });
  }
}
