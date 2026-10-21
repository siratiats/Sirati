import 'package:flutter/material.dart';

/// Brand / semantic color roles for Sirati light and dark themes.
///
/// Registered on [ThemeData] via [ThemeExtension]. Access with [context.sirati].
@immutable
class SiratiColors extends ThemeExtension<SiratiColors> {
  const SiratiColors({
    required this.primary,
    required this.primaryDark,
    required this.primaryLight,
    required this.primaryMid,
    required this.primaryContainer,
    required this.onPrimary,
    required this.primaryInk,
    required this.teal,
    required this.tealLight,
    required this.tealDark,
    required this.amber,
    required this.amberLight,
    required this.amberAccent,
    required this.red,
    required this.redLight,
    required this.tertiary,
    required this.tertiaryLight,
    required this.error,
    required this.errorLight,
    required this.success,
    required this.successLight,
    required this.warning,
    required this.warningLight,
    required this.info,
    required this.infoLight,
    required this.onSuccessContainer,
    required this.onWarningContainer,
    required this.onErrorContainer,
    required this.onInfoContainer,
    required this.surface,
    required this.background,
    required this.surfaceLow,
    required this.surfaceContainer,
    required this.surfaceHigh,
    required this.border,
    required this.borderStrong,
    required this.textPrimary,
    required this.textSecondary,
    required this.textHint,
    required this.brandSurface,
    required this.brandSurfaceDeep,
    required this.onBrandSurface,
    required this.softShadow,
  });

  final Color primary;
  final Color primaryDark;
  final Color primaryLight;
  final Color primaryMid;
  final Color primaryContainer;
  final Color onPrimary;

  /// Brand ink for FOREGROUND use (labels, icons, indicators) on neutral
  /// surfaces. [primary] is a FILL color: at 2.79:1 on the light background
  /// it cannot legally carry text (SIRATI-90).
  final Color primaryInk;
  final Color teal;
  final Color tealLight;
  final Color tealDark;
  final Color amber;
  final Color amberLight;
  final Color amberAccent;
  final Color red;
  final Color redLight;
  final Color tertiary;
  final Color tertiaryLight;
  final Color error;
  final Color errorLight;
  final Color success;
  final Color successLight;
  final Color warning;
  final Color warningLight;
  final Color info;
  final Color infoLight;

  /// Ink for text/icons sitting ON the matching status tint.
  final Color onSuccessContainer;
  final Color onWarningContainer;
  final Color onErrorContainer;
  final Color onInfoContainer;
  final Color surface;
  final Color background;
  final Color surfaceLow;
  final Color surfaceContainer;
  final Color surfaceHigh;
  final Color border;
  final Color borderStrong;
  final Color textPrimary;
  final Color textSecondary;
  final Color textHint;

  /// Dark brand fill for hero cards that carry white text. Unlike [primary]
  /// (a mid-tone fill), it holds AA for [onBrandSurface] in both themes,
  /// across the whole [brandSurface] -> [brandSurfaceDeep] gradient.
  final Color brandSurface;
  final Color brandSurfaceDeep;
  final Color onBrandSurface;
  final List<BoxShadow> softShadow;

  /// Semantic aliases (SIRATI-17). Prefer these names in new widgets.
  Color get onSurface => textPrimary;
  Color get onSurfaceVariant => textSecondary;
  Color get onSurfaceMuted => textHint;
  Color get outline => border;
  Color get outlineVariant => borderStrong;

  /// Text/icons sitting on [error] fills. Pick the ink with higher contrast.
  Color get onError {
    const darkInk = Color(0xFF1A0504);
    return error.computeLuminance() > 0.18 ? darkInk : Colors.white;
  }

  /// Light palette — mirrors historical [AppColors] constants.
  ///
  /// Consistency invariants (SIRATI-101) — do not diverge these values by
  /// hand. If two roles need different reds/greens, add a new named token.
  ///   red        == error          (was #D8403A vs #C73B36 — visible drift)
  ///   tealLight  == primaryLight   (was #E1F5F2 vs #DDF6F3 — near-dup)
  ///   teal       == primary        (already same, kept as legacy alias)
  ///   tealDark   == primaryDark    (already same, kept as legacy alias)
  ///   warning    == amber          (already same, kept as legacy alias)
  ///   info       == primaryDark    (already same, kept as legacy alias)
  /// Prefer semantic names (error, warning, info) over raw (red, amber, teal)
  /// in new code so a future re-tune can move a single knob.
  static const light = SiratiColors(
    primary: Color(0xFF007F73),
    primaryDark: Color(0xFF006A60),
    primaryLight: Color(0xFFDDF6F3),
    primaryMid: Color(0xFF59DAC9),
    primaryContainer: Color(0xFF007F73),
    onPrimary: Color(0xFFFFFFFF),
    primaryInk: Color(0xFF006A60),
    teal: Color(0xFF007F73),
    tealLight: Color(0xFFDDF6F3), // unified with primaryLight (SIRATI-101)
    tealDark: Color(0xFF006A60),
    amber: Color(0xFFC97F1B),
    amberLight: Color(0xFFFDE9C7),
    amberAccent: Color(0xFFF2A93D),
    red: Color(0xFFC73B36), // unified with error (SIRATI-101)
    redLight: Color(0xFFFFE1DE),
    tertiary: Color(0xFF9A4528),
    tertiaryLight: Color(0xFFFFDBD0),
    error: Color(0xFFC73B36),
    errorLight: Color(0xFFFFE1DE),
    success: Color(0xFF2E7D5B),
    successLight: Color(0xFFD7F0E5),
    warning: Color(0xFFC97F1B),
    warningLight: Color(0xFFFDE9C7),
    info: Color(0xFF006A60),
    infoLight: Color(0xFFDDF6F3),
    onSuccessContainer: Color(0xFF256A4D),
    onWarningContainer: Color(0xFF8A5612),
    onErrorContainer: Color(0xFFA32F2B),
    onInfoContainer: Color(0xFF006A60),
    surface: Color(0xFFFFFFFF),
    background: Color(0xFFFAF7F2),
    surfaceLow: Color(0xFFF2F1EC),
    surfaceContainer: Color(0xFFEDECE6),
    surfaceHigh: Color(0xFFE7E5DE),
    border: Color(0xFFD3E3DF),
    borderStrong: Color(0xFFBBC9C6),
    textPrimary: Color(0xFF171D1B),
    textSecondary: Color(0xFF3C4947),
    // Was #64726F (~4.7:1 on background, borderline AA). Darkened to ~6:1
    // for comfortable margin on small hint text.
    textHint: Color(0xFF54615E),
    brandSurface: Color(0xFF006A60),
    brandSurfaceDeep: Color(0xFF004A43),
    onBrandSurface: Color(0xFFFFFFFF),
    softShadow: [
      BoxShadow(
        color: Color(0x0D000000),
        blurRadius: 12,
        offset: Offset(0, 4),
      ),
    ],
  );

  /// Dark palette — teal brand, WCAG-minded text/surface pairs.
  static const dark = SiratiColors(
    primary: Color(0xFF007F73),
    primaryDark: Color(0xFF1A9E8F),
    primaryLight: Color(0xFF0E3B36),
    primaryMid: Color(0xFF3DD4C2),
    primaryContainer: Color(0xFF007F73),
    onPrimary: Color(0xFFFFFFFF),
    primaryInk: Color(0xFF2FC4B2),
    teal: Color(0xFF007F73),
    tealLight: Color(0xFF0E3B36),
    tealDark: Color(0xFF1A9E8F),
    amber: Color(0xFFE0A03A),
    amberLight: Color(0xFF3D2E14),
    amberAccent: Color(0xFFF2A93D),
    red: Color(0xFFF2726C),
    redLight: Color(0xFF4A211F),
    tertiary: Color(0xFFE8957A),
    tertiaryLight: Color(0xFF3D241C),
    error: Color(0xFFF2726C),
    errorLight: Color(0xFF4A211F),
    success: Color(0xFF5FBF92),
    successLight: Color(0xFF1A3D2E),
    warning: Color(0xFFE0A03A),
    warningLight: Color(0xFF3D2E14),
    info: Color(0xFF2FC4B2),
    infoLight: Color(0xFF0E3B36),
    onSuccessContainer: Color(0xFF5FBF92),
    onWarningContainer: Color(0xFFE0A03A),
    onErrorContainer: Color(0xFFF2726C),
    onInfoContainer: Color(0xFF2FC4B2),
    surface: Color(0xFF1A201D),
    background: Color(0xFF121614),
    surfaceLow: Color(0xFF202623),
    surfaceContainer: Color(0xFF252C28),
    surfaceHigh: Color(0xFF2B332E),
    border: Color(0xFF31423E),
    borderStrong: Color(0xFF445753),
    textPrimary: Color(0xFFE6EAE7),
    textSecondary: Color(0xFFAEB8B4),
    textHint: Color(0xFF7E8985),
    brandSurface: Color(0xFF0F5C54),
    brandSurfaceDeep: Color(0xFF0A3D38),
    onBrandSurface: Color(0xFFFFFFFF),
    softShadow: [
      BoxShadow(
        color: Color(0x14000000),
        blurRadius: 8,
        offset: Offset(0, 2),
      ),
    ],
  );

  @override
  SiratiColors copyWith({
    Color? primary,
    Color? primaryDark,
    Color? primaryLight,
    Color? primaryMid,
    Color? primaryContainer,
    Color? onPrimary,
    Color? primaryInk,
    Color? teal,
    Color? tealLight,
    Color? tealDark,
    Color? amber,
    Color? amberLight,
    Color? amberAccent,
    Color? red,
    Color? redLight,
    Color? tertiary,
    Color? tertiaryLight,
    Color? error,
    Color? errorLight,
    Color? success,
    Color? successLight,
    Color? warning,
    Color? warningLight,
    Color? info,
    Color? infoLight,
    Color? onSuccessContainer,
    Color? onWarningContainer,
    Color? onErrorContainer,
    Color? onInfoContainer,
    Color? surface,
    Color? background,
    Color? surfaceLow,
    Color? surfaceContainer,
    Color? surfaceHigh,
    Color? border,
    Color? borderStrong,
    Color? textPrimary,
    Color? textSecondary,
    Color? textHint,
    Color? brandSurface,
    Color? brandSurfaceDeep,
    Color? onBrandSurface,
    List<BoxShadow>? softShadow,
  }) {
    return SiratiColors(
      primary: primary ?? this.primary,
      primaryDark: primaryDark ?? this.primaryDark,
      primaryLight: primaryLight ?? this.primaryLight,
      primaryMid: primaryMid ?? this.primaryMid,
      primaryContainer: primaryContainer ?? this.primaryContainer,
      onPrimary: onPrimary ?? this.onPrimary,
      primaryInk: primaryInk ?? this.primaryInk,
      teal: teal ?? this.teal,
      tealLight: tealLight ?? this.tealLight,
      tealDark: tealDark ?? this.tealDark,
      amber: amber ?? this.amber,
      amberLight: amberLight ?? this.amberLight,
      amberAccent: amberAccent ?? this.amberAccent,
      red: red ?? this.red,
      redLight: redLight ?? this.redLight,
      tertiary: tertiary ?? this.tertiary,
      tertiaryLight: tertiaryLight ?? this.tertiaryLight,
      error: error ?? this.error,
      errorLight: errorLight ?? this.errorLight,
      success: success ?? this.success,
      successLight: successLight ?? this.successLight,
      warning: warning ?? this.warning,
      warningLight: warningLight ?? this.warningLight,
      info: info ?? this.info,
      infoLight: infoLight ?? this.infoLight,
      onSuccessContainer: onSuccessContainer ?? this.onSuccessContainer,
      onWarningContainer: onWarningContainer ?? this.onWarningContainer,
      onErrorContainer: onErrorContainer ?? this.onErrorContainer,
      onInfoContainer: onInfoContainer ?? this.onInfoContainer,
      surface: surface ?? this.surface,
      background: background ?? this.background,
      surfaceLow: surfaceLow ?? this.surfaceLow,
      surfaceContainer: surfaceContainer ?? this.surfaceContainer,
      surfaceHigh: surfaceHigh ?? this.surfaceHigh,
      border: border ?? this.border,
      borderStrong: borderStrong ?? this.borderStrong,
      textPrimary: textPrimary ?? this.textPrimary,
      textSecondary: textSecondary ?? this.textSecondary,
      textHint: textHint ?? this.textHint,
      brandSurface: brandSurface ?? this.brandSurface,
      brandSurfaceDeep: brandSurfaceDeep ?? this.brandSurfaceDeep,
      onBrandSurface: onBrandSurface ?? this.onBrandSurface,
      softShadow: softShadow ?? this.softShadow,
    );
  }

  @override
  SiratiColors lerp(ThemeExtension<SiratiColors>? other, double t) {
    if (other is! SiratiColors) return this;
    Color l(Color a, Color b) => Color.lerp(a, b, t)!;
    return SiratiColors(
      primary: l(primary, other.primary),
      primaryDark: l(primaryDark, other.primaryDark),
      primaryLight: l(primaryLight, other.primaryLight),
      primaryMid: l(primaryMid, other.primaryMid),
      primaryContainer: l(primaryContainer, other.primaryContainer),
      onPrimary: l(onPrimary, other.onPrimary),
      primaryInk: l(primaryInk, other.primaryInk),
      teal: l(teal, other.teal),
      tealLight: l(tealLight, other.tealLight),
      tealDark: l(tealDark, other.tealDark),
      amber: l(amber, other.amber),
      amberLight: l(amberLight, other.amberLight),
      amberAccent: l(amberAccent, other.amberAccent),
      red: l(red, other.red),
      redLight: l(redLight, other.redLight),
      tertiary: l(tertiary, other.tertiary),
      tertiaryLight: l(tertiaryLight, other.tertiaryLight),
      error: l(error, other.error),
      errorLight: l(errorLight, other.errorLight),
      success: l(success, other.success),
      successLight: l(successLight, other.successLight),
      warning: l(warning, other.warning),
      warningLight: l(warningLight, other.warningLight),
      info: l(info, other.info),
      infoLight: l(infoLight, other.infoLight),
      onSuccessContainer: l(onSuccessContainer, other.onSuccessContainer),
      onWarningContainer: l(onWarningContainer, other.onWarningContainer),
      onErrorContainer: l(onErrorContainer, other.onErrorContainer),
      onInfoContainer: l(onInfoContainer, other.onInfoContainer),
      surface: l(surface, other.surface),
      background: l(background, other.background),
      surfaceLow: l(surfaceLow, other.surfaceLow),
      surfaceContainer: l(surfaceContainer, other.surfaceContainer),
      surfaceHigh: l(surfaceHigh, other.surfaceHigh),
      border: l(border, other.border),
      borderStrong: l(borderStrong, other.borderStrong),
      textPrimary: l(textPrimary, other.textPrimary),
      textSecondary: l(textSecondary, other.textSecondary),
      textHint: l(textHint, other.textHint),
      brandSurface: l(brandSurface, other.brandSurface),
      brandSurfaceDeep: l(brandSurfaceDeep, other.brandSurfaceDeep),
      onBrandSurface: l(onBrandSurface, other.onBrandSurface),
      softShadow: t < 0.5 ? softShadow : other.softShadow,
    );
  }
}

/// Theme-aware brand colors: `context.sirati.primary`, etc.
extension SiratiColorsX on BuildContext {
  SiratiColors get sirati =>
      Theme.of(this).extension<SiratiColors>() ?? SiratiColors.light;
}
