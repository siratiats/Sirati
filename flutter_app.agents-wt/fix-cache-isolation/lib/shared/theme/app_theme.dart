import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/shared/theme/app_typography.dart';
import 'package:sirati/shared/theme/sirati_colors.dart';
import 'package:sirati/shared/widgets/form_fields.dart';
import 'package:sirati/shared/widgets/motion.dart';

export 'package:sirati/shared/theme/app_contrast.dart';
export 'package:sirati/shared/theme/app_typography.dart';
export 'package:sirati/shared/theme/sirati_colors.dart';

/// Light-mode static palette — source of truth for [SiratiColors.light].
/// Prefer [context.sirati] in widgets so dark mode resolves correctly.
class AppColors {
  // ── Brand teal ──
  static const primary = Color(0xFF007F73);
  static const primaryDark = Color(0xFF006A60);
  static const primaryLight = Color(0xFFDDF6F3);
  static const primaryMid = Color(0xFF59DAC9);
  static const primaryContainer = Color(0xFF007F73);
  static const onPrimary = Color(0xFFFFFFFF);

  /// Foreground-safe brand ink (see SiratiColors.primaryInk).
  static const primaryInk = Color(0xFF006A60);

  static const teal = Color(0xFF007F73);
  static const tealLight = Color(0xFFDDF6F3);
  static const tealDark = Color(0xFF006A60);

  static const amber = Color(0xFFC97F1B);
  static const amberLight = Color(0xFFFDE9C7);
  static const amberAccent = Color(0xFFF2A93D);

  static const red = Color(0xFFC73B36);
  static const redLight = Color(0xFFFFE1DE);

  static const tertiary = Color(0xFF9A4528);
  static const tertiaryLight = Color(0xFFFFDBD0);

  static const error = Color(0xFFC73B36);
  static const errorLight = redLight;
  static const success = Color(0xFF2E7D5B);
  static const successLight = Color(0xFFD7F0E5);
  static const warning = Color(0xFFC97F1B);
  static const warningLight = Color(0xFFFDE9C7);
  static const info = Color(0xFF006A60);
  static const infoLight = Color(0xFFDDF6F3);

  static const surface = Color(0xFFFFFFFF);
  static const background = Color(0xFFFAF7F2);
  static const surfaceLow = Color(0xFFF2F1EC);
  static const surfaceContainer = Color(0xFFEDECE6);
  static const surfaceHigh = Color(0xFFE7E5DE);
  static const border = Color(0xFFD3E3DF);
  static const borderStrong = Color(0xFFBBC9C6);

  static const textPrimary = Color(0xFF171D1B);
  static const textSecondary = Color(0xFF3C4947);
  static const textHint = Color(0xFF54615E);

  static const softShadow = [
    BoxShadow(
      color: Color(0x0D000000),
      blurRadius: 12,
      offset: Offset(0, 4),
    ),
  ];
}

/// Spacing scale — use only these values for layout padding/gaps.
class AppSpacing {
  static const xxs = 4.0;
  static const xs = 8.0;
  static const sm = 12.0;
  static const md = 16.0;
  static const lg = 20.0;
  static const xl = 24.0;
  static const xxl = 32.0;

  static double pageGutter(double width) => width >= 780 ? lg + 4 : lg;

  /// Extra scroll padding for screens that float their own action bar or FAB
  /// OVER the list. The dashboard Scaffold already lays its body out above the
  /// bottom navigation bar (`extendBody` is false), so these values must not be
  /// used just to "clear the nav bar" -- doing so leaves a visible empty band
  /// under the last card, which is very obvious on short screens and on iOS
  /// where bouncing physics let you drag it further into view.
  static const scrollBottomNav = 104.0;
  static const scrollBottomNavFab = 156.0;

  /// Trailing breathing room for a plain tab list inside the dashboard shell.
  static const scrollBottomTab = 32.0;

  static const scale = <double>[xxs, xs, sm, md, lg, xl, xxl];
}

/// Elevation steps per surface type (SIRATI-19).
class AppElevation {
  static const none = 0.0;
  static const card = 1.0;
  static const raised = 3.0;
  static const sheet = 6.0;
  static const dialog = 12.0;
}

/// Shadows derived from [AppElevation] + the active palette.
class AppShadows {
  static List<BoxShadow> of(SiratiColors colors, {required double elevation}) {
    if (elevation <= 0) return const <BoxShadow>[];
    final dark = colors.surface.computeLuminance() < 0.5;
    final opacity = dark ? 0.36 : 0.08;
    return [
      BoxShadow(
        color: Color.fromRGBO(0, 0, 0, opacity),
        blurRadius: elevation * 2.4,
        offset: Offset(0, elevation * 0.6),
      ),
    ];
  }

  /// Same shadow cast upward, for bars pinned to the bottom of the screen.
  static List<BoxShadow> above(
    SiratiColors colors, {
    required double elevation,
  }) =>
      [
        for (final s in of(colors, elevation: elevation))
          s.copyWith(offset: Offset(0, -s.offset.dy)),
      ];
}

/// Shared type ramp. [colors] is required so dark-mode text cannot silently
/// fall back to the light palette (SIRATI-80).
///
/// All styles come from [AppTypography] tokens (SIRATI-18).
class AppTextStyles {
  static bool _arabicDefault() => AppLocale.languageCode.value != 'en';

  static TextStyle titleLg(SiratiColors colors, {bool? arabic}) {
    return AppTypography.titleLg.resolve(
      arabic: arabic ?? _arabicDefault(),
      color: colors.textPrimary,
    );
  }

  static TextStyle titleMd(SiratiColors colors, {bool? arabic}) {
    return AppTypography.titleMd.resolve(
      arabic: arabic ?? _arabicDefault(),
      color: colors.textPrimary,
    );
  }

  static TextStyle titleSm(SiratiColors colors, {bool? arabic}) {
    return AppTypography.titleSm.resolve(
      arabic: arabic ?? _arabicDefault(),
      color: colors.textPrimary,
    );
  }

  static TextStyle bodyMd(SiratiColors colors, {bool? arabic}) {
    return AppTypography.bodyMd.resolve(
      arabic: arabic ?? _arabicDefault(),
      color: colors.textPrimary,
    );
  }

  static TextStyle bodySm(SiratiColors colors, {bool? arabic}) {
    return AppTypography.bodySm.resolve(
      arabic: arabic ?? _arabicDefault(),
      color: colors.textSecondary,
    );
  }

  static TextStyle labelMd(SiratiColors colors, {bool? arabic}) {
    return AppTypography.labelMd.resolve(
      arabic: arabic ?? _arabicDefault(),
      color: colors.textSecondary,
    );
  }

  static TextStyle displayStat(SiratiColors colors, {bool? arabic}) {
    return AppTypography.displayMd.resolve(
      arabic: arabic ?? _arabicDefault(),
      color: colors.textPrimary,
    );
  }
}

class AppTheme {
  static final ThemeData _lightArabic =
      _build(SiratiColors.light, Brightness.light, arabic: true);
  static final ThemeData _lightLatin =
      _build(SiratiColors.light, Brightness.light, arabic: false);
  static final ThemeData _darkArabic =
      _build(SiratiColors.dark, Brightness.dark, arabic: true);
  static final ThemeData _darkLatin =
      _build(SiratiColors.dark, Brightness.dark, arabic: false);

  static ThemeData get light => _lightArabic;

  static ThemeData get dark => _darkArabic;

  static ThemeData lightFor({required bool arabic}) =>
      arabic ? _lightArabic : _lightLatin;

  static ThemeData darkFor({required bool arabic}) =>
      arabic ? _darkArabic : _darkLatin;

  static ThemeData _build(
    SiratiColors c,
    Brightness brightness, {
    required bool arabic,
  }) {
    final isDark = brightness == Brightness.dark;
    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      extensions: [c],
      colorScheme: ColorScheme(
        brightness: brightness,
        primary: c.primary,
        onPrimary: c.onPrimary,
        secondary: c.amber,
        onSecondary: isDark ? c.textPrimary : const Color(0xFF1A1200),
        tertiary: c.tertiary,
        onTertiary: isDark ? c.onPrimary : Colors.white,
        error: c.error,
        onError: c.onError,
        surface: c.surface,
        onSurface: c.textPrimary,
      ),
      scaffoldBackgroundColor: c.background,
      fontFamily: 'IBM Plex Sans Arabic',
      textTheme: AppTypography.textTheme(c, arabic: arabic),
      appBarTheme: AppBarTheme(
        backgroundColor: c.background,
        foregroundColor: c.textPrimary,
        elevation: 0,
        centerTitle: true,
        systemOverlayStyle: systemUiOverlayStyle(c, brightness),
        titleTextStyle: AppTypography.titleLg.resolve(
          arabic: arabic,
          color: c.textPrimary,
        ),
        iconTheme: IconThemeData(color: c.textSecondary, size: 22),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: c.primary,
          foregroundColor: c.onPrimary,
          minimumSize: const Size.fromHeight(54),
          elevation: 0,
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          textStyle: AppTypography.labelLg.resolve(arabic: arabic),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: c.primaryInk,
          minimumSize: const Size.fromHeight(54),
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          textStyle: AppTypography.labelLg.resolve(arabic: arabic),
        ).copyWith(
          // A disabled button must not keep the active outline.
          side: WidgetStateProperty.resolveWith(
            (states) => BorderSide(
              color: states.contains(WidgetState.disabled)
                  ? c.border
                  : c.primaryInk,
              width: 1.5,
            ),
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: c.primaryInk,
          textStyle: AppTypography.labelMd.resolve(arabic: arabic),
        ),
      ),
      inputDecorationTheme: AppFormStyles.inputThemeFor(c),
      cardTheme: CardThemeData(
        color: c.surface,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: AppRadius.xlAll,
          side: BorderSide.none,
        ),
        margin: EdgeInsets.zero,
      ),
      chipTheme: ChipThemeData(
        backgroundColor: c.primaryLight,
        labelStyle: AppTypography.labelMd.resolve(
          arabic: arabic,
          color: c.primaryInk,
        ),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        side: BorderSide.none,
      ),
      dividerTheme: DividerThemeData(
        color: c.border,
        thickness: 1,
        space: 0,
      ),
      bottomNavigationBarTheme: BottomNavigationBarThemeData(
        backgroundColor: c.surface,
        selectedItemColor: c.primaryInk,
        unselectedItemColor: c.textHint,
        selectedLabelStyle: AppTypography.labelSm.resolve(arabic: arabic),
        unselectedLabelStyle: AppTypography.labelSm.resolve(arabic: arabic),
        elevation: 0,
        type: BottomNavigationBarType.fixed,
      ),
      tabBarTheme: TabBarThemeData(
        labelColor: c.primaryInk,
        unselectedLabelColor: c.textHint,
        indicatorColor: c.primaryInk,
        labelStyle: AppTypography.labelLg.resolve(arabic: arabic),
        unselectedLabelStyle: AppTypography.labelLg.resolve(arabic: arabic),
      ),
      progressIndicatorTheme: ProgressIndicatorThemeData(
        color: c.primaryInk,
        linearTrackColor: c.border,
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: c.surface,
        surfaceTintColor: Colors.transparent,
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: isDark ? c.surfaceHigh : c.textPrimary,
        contentTextStyle: AppTypography.bodyMd.resolve(
          arabic: arabic,
          color: isDark ? c.textPrimary : Colors.white,
        ),
      ),
      pageTransitionsTheme: const PageTransitionsTheme(
        builders: {
          TargetPlatform.android: SiratiPageTransitionsBuilder(),
          TargetPlatform.iOS: SiratiPageTransitionsBuilder(),
          TargetPlatform.macOS: SiratiPageTransitionsBuilder(),
          TargetPlatform.windows: SiratiPageTransitionsBuilder(),
          TargetPlatform.linux: SiratiPageTransitionsBuilder(),
        },
      ),
    );
  }

  /// Global status / navigation bar styling for every route (also on
  /// [AppBarTheme.systemOverlayStyle]). Transparent bars support edge-to-edge;
  /// icon brightness follows theme.
  /// Pre-theme overlay used at process start, before [SiratiApp] mounts.
  static SystemUiOverlayStyle bootstrapSystemUiOverlayStyle() =>
      systemUiOverlayStyle(SiratiColors.light, Brightness.light);

  static SystemUiOverlayStyle systemUiOverlayStyle(
    SiratiColors c,
    Brightness brightness,
  ) {
    final lightIcons = brightness == Brightness.dark;
    return SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: lightIcons ? Brightness.light : Brightness.dark,
      statusBarBrightness: lightIcons ? Brightness.dark : Brightness.light,
      // Transparent for SystemUiMode.edgeToEdge; content uses SafeArea.
      systemNavigationBarColor: Colors.transparent,
      systemNavigationBarIconBrightness:
          lightIcons ? Brightness.light : Brightness.dark,
      systemNavigationBarDividerColor: Colors.transparent,
      systemNavigationBarContrastEnforced: false,
      systemStatusBarContrastEnforced: false,
    );
  }
}

// ── Reusable Widget Helpers ──────────────────────────────────────────────────

class AppCard extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry? padding;
  final VoidCallback? onTap;
  final Color? color;

  const AppCard({
    super.key,
    required this.child,
    this.padding,
    this.onTap,
    this.color,
  });

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    return Material(
      color: color ?? c.surface,
      borderRadius: AppRadius.xlAll,
      child: InkWell(
        onTap: onTap,
        borderRadius: AppRadius.xlAll,
        child: Container(
          padding: padding ?? const EdgeInsets.all(AppSpacing.md),
          decoration: BoxDecoration(
            borderRadius: AppRadius.xlAll,
            border: Border.all(color: c.border),
            boxShadow: c.softShadow,
          ),
          child: child,
        ),
      ),
    );
  }
}

class SiratiMark extends StatelessWidget {
  final double size;
  final bool elevated;

  const SiratiMark({super.key, this.size = 56, this.elevated = false});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF12384F), Color(0xFF0E8F86)],
        ),
        // Circular mark (SIRATI-99). The check-path in _SiratiMarkPainter is
        // authored in a unit square whose reachable extents (including the
        // top-right dot + stroke half-width) sit within the inscribed circle
        // of radius 0.5, so the glyph is not clipped by the switch to
        // BoxShape.circle.
        shape: BoxShape.circle,
        boxShadow: elevated
            ? const [
                BoxShadow(
                  color: Color(0x3312384F),
                  blurRadius: 18,
                  offset: Offset(0, 8),
                )
              ]
            : null,
      ),
      child: CustomPaint(painter: _SiratiMarkPainter()),
    );
  }
}

class _SiratiMarkPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final stroke = Paint()
      ..color = Colors.white
      ..style = PaintingStyle.stroke
      ..strokeWidth = size.width * .11
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;

    final mark = Path()
      ..moveTo(size.width * .22, size.height * .56)
      ..cubicTo(
        size.width * .26,
        size.height * .66,
        size.width * .34,
        size.height * .75,
        size.width * .41,
        size.height * .75,
      )
      ..cubicTo(
        size.width * .47,
        size.height * .75,
        size.width * .51,
        size.height * .67,
        size.width * .56,
        size.height * .59,
      )
      ..cubicTo(
        size.width * .63,
        size.height * .47,
        size.width * .69,
        size.height * .36,
        size.width * .75,
        size.height * .27,
      );

    canvas.drawPath(mark, stroke);
    canvas.drawCircle(
      Offset(size.width * .79, size.height * .23),
      size.width * .07,
      Paint()..color = Colors.white,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class SectionTitle extends StatelessWidget {
  final String text;
  final Widget? trailing;

  const SectionTitle(this.text, {super.key, this.trailing});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            text,
            style: AppTypography.of(context, AppTypography.titleSm),
          ),
        ),
        if (trailing != null) trailing!,
      ],
    );
  }
}

class StatusChip extends StatelessWidget {
  final String label;
  final Color bg;
  final Color fg;

  const StatusChip(
      {super.key, required this.label, required this.bg, required this.fg});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration:
          BoxDecoration(color: bg, borderRadius: BorderRadius.circular(20)),
      child: Text(
        label,
        style: AppTypography.of(context, AppTypography.labelMd, color: fg),
      ),
    );
  }
}
