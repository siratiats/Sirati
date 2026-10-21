import 'package:flutter/material.dart';

import 'package:sirati/shared/theme/sirati_colors.dart';

/// WCAG contrast for semantic token pairs (SIRATI-17).
///
/// Ratios are measured from [Color.computeLuminance] (sRGB relative luminance).
/// AA: 4.5:1 body text, 3.0:1 large text / UI chrome.
class ContrastPair {
  const ContrastPair({
    required this.name,
    required this.foreground,
    required this.background,
    required this.minRatio,
  });

  final String name;
  final Color foreground;
  final Color background;
  final double minRatio;

  double get ratio => AppContrast.ratio(foreground, background);

  bool get passes => ratio >= minRatio;
}

class AppContrast {
  AppContrast._();

  static double ratio(Color a, Color b) {
    final l1 = a.computeLuminance();
    final l2 = b.computeLuminance();
    final light = l1 > l2 ? l1 : l2;
    final dark = l1 > l2 ? l2 : l1;
    return (light + 0.05) / (dark + 0.05);
  }

  /// Foreground/background pairs that must stay WCAG AA.
  static List<ContrastPair> pairs(SiratiColors c) {
    return [
      ContrastPair(
        name: 'onSurface / background',
        foreground: c.onSurface,
        background: c.background,
        minRatio: 4.5,
      ),
      ContrastPair(
        name: 'onSurface / surface',
        foreground: c.onSurface,
        background: c.surface,
        minRatio: 4.5,
      ),
      ContrastPair(
        name: 'onSurfaceVariant / background',
        foreground: c.onSurfaceVariant,
        background: c.background,
        minRatio: 4.5,
      ),
      ContrastPair(
        name: 'onSurfaceVariant / surface',
        foreground: c.onSurfaceVariant,
        background: c.surface,
        minRatio: 4.5,
      ),
      ContrastPair(
        name: 'onSurfaceMuted / background',
        foreground: c.onSurfaceMuted,
        background: c.background,
        minRatio: 4.5,
      ),
      ContrastPair(
        name: 'onSurfaceMuted / surface',
        foreground: c.onSurfaceMuted,
        background: c.surface,
        minRatio: 4.5,
      ),
      ContrastPair(
        name: 'onPrimary / primary',
        foreground: c.onPrimary,
        background: c.primary,
        minRatio: 4.5,
      ),
      ContrastPair(
        name: 'onError / error',
        foreground: c.onError,
        background: c.error,
        minRatio: 4.5,
      ),
      ContrastPair(
        name: 'error / surface',
        foreground: c.error,
        background: c.surface,
        minRatio: 3.0,
      ),
      ContrastPair(
        name: 'primaryDark / surface',
        foreground: c.primaryDark,
        background: c.surface,
        minRatio: 4.5,
      ),
      ContrastPair(
        name: 'primaryDark / background',
        foreground: c.primaryDark,
        background: c.background,
        minRatio: 4.5,
      ),
      // Brand ink used as a FOREGROUND (buttons, tabs, bottom nav, progress).
      // These are the pairs SIRATI-90 missed: `primary` itself is only
      // 2.79:1 on background and must never carry a label.
      ContrastPair(
        name: 'primaryInk / surface',
        foreground: c.primaryInk,
        background: c.surface,
        minRatio: 4.5,
      ),
      ContrastPair(
        name: 'primaryInk / background',
        foreground: c.primaryInk,
        background: c.background,
        minRatio: 4.5,
      ),
      ContrastPair(
        name: 'primaryInk / primaryLight (chip label on chip fill)',
        foreground: c.primaryInk,
        background: c.primaryLight,
        minRatio: 4.5,
      ),
      // Status inks on their own tint containers.
      ContrastPair(
        name: 'onSuccessContainer / successLight',
        foreground: c.onSuccessContainer,
        background: c.successLight,
        minRatio: 4.5,
      ),
      ContrastPair(
        name: 'onWarningContainer / warningLight',
        foreground: c.onWarningContainer,
        background: c.warningLight,
        minRatio: 4.5,
      ),
      ContrastPair(
        name: 'onErrorContainer / errorLight',
        foreground: c.onErrorContainer,
        background: c.errorLight,
        minRatio: 4.5,
      ),
      ContrastPair(
        name: 'onInfoContainer / infoLight',
        foreground: c.onInfoContainer,
        background: c.infoLight,
        minRatio: 4.5,
      ),
      ContrastPair(
        name: 'onBrandSurface / brandSurface',
        foreground: c.onBrandSurface,
        background: c.brandSurface,
        minRatio: 4.5,
      ),
      ContrastPair(
        name: 'onBrandSurface / brandSurfaceDeep',
        foreground: c.onBrandSurface,
        background: c.brandSurfaceDeep,
        minRatio: 4.5,
      ),
    ];
  }
}
