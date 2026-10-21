import 'package:flutter/material.dart';

/// Icon that mirrors with ambient [TextDirection].
///
/// Use for chevrons, back/forward arrows, and any glyph whose meaning is
/// "point toward start/end" rather than a fixed geographic left/right.
class DirectionalIcon extends StatelessWidget {
  const DirectionalIcon(
    this.icon, {
    super.key,
    this.size,
    this.color,
    this.semanticLabel,
  });

  final IconData icon;
  final double? size;
  final Color? color;
  final String? semanticLabel;

  /// Icons whose geometry encodes a start/end direction.
  static const directionalIcons = <IconData>[
    Icons.arrow_forward,
    Icons.arrow_back,
    Icons.arrow_forward_rounded,
    Icons.arrow_back_rounded,
    Icons.arrow_forward_outlined,
    Icons.arrow_back_outlined,
    Icons.arrow_forward_ios,
    Icons.arrow_back_ios,
    Icons.arrow_forward_ios_rounded,
    Icons.arrow_back_ios_rounded,
    Icons.arrow_back_ios_new,
    Icons.arrow_back_ios_new_rounded,
    Icons.chevron_right,
    Icons.chevron_left,
    Icons.chevron_right_rounded,
    Icons.chevron_left_rounded,
    Icons.arrow_right,
    Icons.arrow_left,
    Icons.arrow_right_alt,
    Icons.keyboard_arrow_right,
    Icons.keyboard_arrow_left,
  ];

  static bool isDirectional(IconData icon) => directionalIcons.contains(icon);

  /// Mirrors exactly once in RTL. Most Material arrows already set
  /// `matchTextDirection` and [Icon] flips them itself; flipping those again
  /// would point them backwards.
  @override
  Widget build(BuildContext context) {
    final glyph = Icon(
      icon,
      size: size,
      color: color,
      semanticLabel: semanticLabel,
    );
    final rtl = Directionality.of(context) == TextDirection.rtl;
    if (!rtl || icon.matchTextDirection) return glyph;
    return Transform.scale(scaleX: -1.0, child: glyph);
  }
}
