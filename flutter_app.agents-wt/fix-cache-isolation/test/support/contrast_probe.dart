import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/shared/theme/app_contrast.dart';

/// Nearest opaque background painted behind [element] in a pumped tree:
/// a coloured or gradient [DecoratedBox], a non-transparent [Material], or a
/// button's resolved fill. A gradient yields every stop, so text must pass
/// against the worst one.
List<Color> paintedBackgroundsOf(Element element) {
  List<Color>? found;
  element.visitAncestorElements((ancestor) {
    final widget = ancestor.widget;
    Color? candidate;
    if (widget is DecoratedBox) {
      final d = widget.decoration;
      if (d is BoxDecoration) {
        final gradient = d.gradient;
        if (gradient != null) {
          found = gradient.colors;
          return false;
        }
        candidate = d.color;
      }
    } else if (widget is Material && widget.type != MaterialType.transparency) {
      candidate = widget.color;
    } else if (widget is ButtonStyleButton) {
      final theme = Theme.of(ancestor);
      final themed = switch (widget) {
        ElevatedButton() => theme.elevatedButtonTheme.style,
        FilledButton() => theme.filledButtonTheme.style,
        OutlinedButton() => theme.outlinedButtonTheme.style,
        TextButton() => theme.textButtonTheme.style,
        _ => null,
      };
      candidate = widget.style?.backgroundColor?.resolve({}) ??
          themed?.backgroundColor?.resolve({});
    }
    if (candidate != null && candidate.a > 0) found = [candidate];
    return found == null;
  });
  return found ?? fail('no painted background above ${element.widget}');
}

/// Asserts every [Text] currently on screen meets WCAG AA (4.5:1) against
/// what it is actually painted on.
void expectAllTextAa(String label) {
  final texts = find.byType(Text).evaluate().toList();
  expect(texts, isNotEmpty, reason: label);
  for (final element in texts) {
    final text = element.widget as Text;
    final color =
        text.style?.color ?? DefaultTextStyle.of(element).style.color!;
    for (final background in paintedBackgroundsOf(element)) {
      expect(
        AppContrast.ratio(Color.alphaBlend(color, background), background),
        greaterThanOrEqualTo(4.5),
        reason: '"${text.data}" on $background ($label)',
      );
    }
  }
}
