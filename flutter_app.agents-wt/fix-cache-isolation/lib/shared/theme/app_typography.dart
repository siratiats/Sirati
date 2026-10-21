import 'package:flutter/material.dart';

import 'package:sirati/shared/theme/sirati_colors.dart';

/// Token type scale (SIRATI-18).
///
/// Arabic needs more vertical room than Latin at the same point size.
/// Always resolve through [AppTypography.of] / [resolve] so line-height
/// follows [Directionality] (RTL ≈ Arabic).
class AppTypeToken {
  const AppTypeToken({
    required this.size,
    required this.weight,
    required this.heightLatin,
    required this.heightArabic,
  });

  final double size;
  final FontWeight weight;
  final double heightLatin;
  final double heightArabic;

  TextStyle resolve({required bool arabic, Color? color}) {
    return TextStyle(
      fontSize: size,
      fontWeight: weight,
      height: arabic ? heightArabic : heightLatin,
      color: color,
      fontFamily: 'IBM Plex Sans Arabic',
    );
  }
}

/// Display / headline / title / body / label, each with weights.
///
/// WEIGHT CEILING: IBM Plex Sans Arabic ships Thin/ExtraLight/Light/Regular/
/// Text/Medium/SemiBold/Bold -- there is no ExtraBold in this family, so w800
/// would silently fall back to the Bold (700) file. Tokens therefore top out
/// at w700 and the display tier earns its hierarchy from size, not weight.
/// Only 400/600/700 are declared in pubspec.yaml; do not request 500 until
/// the Medium file is added there.
class AppTypography {
  AppTypography._();

  static const displayLg = AppTypeToken(
    size: 32,
    weight: FontWeight.w700,
    heightLatin: 1.15,
    heightArabic: 1.38,
  );
  static const displayMd = AppTypeToken(
    size: 28,
    weight: FontWeight.w700,
    heightLatin: 1.18,
    heightArabic: 1.4,
  );

  static const headlineLg = AppTypeToken(
    size: 24,
    weight: FontWeight.w700,
    heightLatin: 1.25,
    heightArabic: 1.48,
  );
  static const headlineMd = AppTypeToken(
    size: 20,
    weight: FontWeight.w700,
    heightLatin: 1.28,
    heightArabic: 1.5,
  );

  static const titleLg = AppTypeToken(
    size: 18,
    weight: FontWeight.w700,
    heightLatin: 1.3,
    heightArabic: 1.52,
  );
  static const titleMd = AppTypeToken(
    size: 16,
    weight: FontWeight.w700,
    heightLatin: 1.35,
    heightArabic: 1.55,
  );
  static const titleSm = AppTypeToken(
    size: 14,
    weight: FontWeight.w700,
    heightLatin: 1.35,
    heightArabic: 1.52,
  );

  static const bodyLg = AppTypeToken(
    size: 16,
    weight: FontWeight.w400,
    heightLatin: 1.4,
    heightArabic: 1.65,
  );
  static const bodyMd = AppTypeToken(
    size: 14,
    weight: FontWeight.w400,
    heightLatin: 1.45,
    heightArabic: 1.65,
  );
  static const bodySm = AppTypeToken(
    // Bumped 12.5 -> 13 (SIRATI-92). 12.5 hints inconsistently across engines
    // and Arabic diacritics lose legibility at that size on high-DPI Android.
    size: 13,
    weight: FontWeight.w400,
    heightLatin: 1.4,
    heightArabic: 1.6,
  );

  static const labelLg = AppTypeToken(
    size: 14,
    weight: FontWeight.w700,
    heightLatin: 1.25,
    heightArabic: 1.42,
  );
  static const labelMd = AppTypeToken(
    size: 12,
    weight: FontWeight.w600,
    heightLatin: 1.3,
    heightArabic: 1.45,
  );
  static const labelSm = AppTypeToken(
    // Bumped 11 -> 12 (SIRATI-92). 11px Arabic is at the readability floor;
    // labelSm is used for microcopy under form fields and inside upload zones
    // where users must actually read the constraint (e.g. "max 5 MB").
    size: 12,
    weight: FontWeight.w600,
    heightLatin: 1.25,
    heightArabic: 1.4,
  );

  static const all = <AppTypeToken>[
    displayLg,
    displayMd,
    headlineLg,
    headlineMd,
    titleLg,
    titleMd,
    titleSm,
    bodyLg,
    bodyMd,
    bodySm,
    labelLg,
    labelMd,
    labelSm,
  ];

  static bool isArabic(BuildContext context) {
    return Directionality.of(context) == TextDirection.rtl;
  }

  static TextStyle of(
    BuildContext context,
    AppTypeToken token, {
    Color? color,
  }) {
    final colors = Theme.of(context).extension<SiratiColors>();
    return token.resolve(
      arabic: isArabic(context),
      color: color ?? colors?.textPrimary,
    );
  }

  static TextTheme textTheme(SiratiColors colors, {required bool arabic}) {
    TextStyle t(AppTypeToken token, {Color? color}) => token.resolve(
          arabic: arabic,
          color: color ?? colors.textPrimary,
        );

    return TextTheme(
      displayLarge: t(displayLg),
      displayMedium: t(displayMd),
      headlineLarge: t(headlineLg),
      headlineMedium: t(headlineMd),
      titleLarge: t(titleLg),
      titleMedium: t(titleMd),
      titleSmall: t(titleSm),
      bodyLarge: t(bodyLg),
      bodyMedium: t(bodyMd, color: colors.textSecondary),
      bodySmall: t(bodySm, color: colors.textSecondary),
      labelLarge: t(labelLg),
      labelMedium: t(labelMd, color: colors.textSecondary),
      labelSmall: t(labelSm, color: colors.textHint),
    );
  }
}

/// Corner radius tokens.
class AppRadius {
  static const sm = 10.0;
  static const md = 14.0;
  static const lg = 16.0;
  static const xl = 18.0;
  static const pill = 24.0;

  static BorderRadius get smAll => BorderRadius.circular(sm);
  static BorderRadius get mdAll => BorderRadius.circular(md);
  static BorderRadius get lgAll => BorderRadius.circular(lg);
  static BorderRadius get xlAll => BorderRadius.circular(xl);
}

/// Minimum accessible tap target (WCAG / Material).
class AppTouchTarget {
  static const min = 48.0;
}
