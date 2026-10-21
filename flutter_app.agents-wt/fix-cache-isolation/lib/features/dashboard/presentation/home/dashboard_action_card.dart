import 'package:flutter/material.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/directional_icon.dart';
import 'package:sirati/shared/widgets/motion.dart';

/// Visual treatment of a [DashboardActionCard]. Each tone uses token pairs
/// covered by `AppContrast`, in light and dark mode.
enum DashboardActionTone {
  /// Dark brand fill with light text — the primary call to action.
  brand,

  /// Surface card with a warm accent — secondary actions.
  neutral,
}

class _TonePalette {
  const _TonePalette({
    required this.background,
    required this.border,
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.button,
    required this.buttonText,
  });

  factory _TonePalette.of(DashboardActionTone tone, SiratiColors c) =>
      switch (tone) {
        DashboardActionTone.brand => _TonePalette(
            background: c.brandSurface,
            border: null,
            title: c.onBrandSurface,
            subtitle: c.onBrandSurface.withValues(alpha: .85),
            icon: c.onBrandSurface.withValues(alpha: .94),
            // Inverted pill: same gated pair as the card, swapped.
            button: c.onBrandSurface,
            buttonText: c.brandSurface,
          ),
        DashboardActionTone.neutral => _TonePalette(
            background: c.surface,
            border: c.border,
            title: c.textPrimary,
            subtitle: c.textSecondary,
            icon: c.amber,
            button: c.warningLight,
            buttonText: c.onWarningContainer,
          ),
      };

  final Color background;
  final Color? border;
  final Color title;
  final Color subtitle;
  final Color icon;
  final Color button;
  final Color buttonText;
}

/// Large tappable dashboard card: icon, title, subtitle, and a pill button,
/// with a faint watermark icon in the corner.
class DashboardActionCard extends StatelessWidget {
  const DashboardActionCard({
    super.key,
    required this.title,
    required this.subtitle,
    required this.buttonLabel,
    required this.tone,
    required this.icon,
    required this.watermark,
    required this.onTap,
  });

  final String title;
  final String subtitle;
  final String buttonLabel;
  final DashboardActionTone tone;
  final IconData icon;
  final IconData watermark;
  final VoidCallback onTap;

  static const _radius = BorderRadius.all(Radius.circular(20));

  @override
  Widget build(BuildContext context) {
    final colors = _TonePalette.of(tone, context.sirati);
    final border = colors.border;

    return PressScale(
      child: Container(
        decoration: BoxDecoration(
          color: colors.background,
          borderRadius: _radius,
          border: border == null ? null : Border.all(color: border),
          boxShadow: context.sirati.softShadow,
        ),
        child: Material(
          type: MaterialType.transparency,
          borderRadius: _radius,
          child: InkWell(
            onTap: onTap,
            borderRadius: _radius,
            // minHeight preserves card density; content grows with scale.
            child: ConstrainedBox(
              constraints: const BoxConstraints(minHeight: 174),
              child: ClipRRect(
                borderRadius: _radius,
                child: Stack(
                  children: [
                    PositionedDirectional(
                      end: -36,
                      bottom: -40,
                      child: Icon(
                        watermark,
                        size: 114,
                        color: colors.title.withValues(alpha: .10),
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.all(22),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(icon, color: colors.icon, size: 22),
                          const SizedBox(height: 12),
                          Text(
                            title,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            textAlign: TextAlign.start,
                            style: TextStyle(
                              fontSize: 17,
                              height: 1.35,
                              fontWeight: FontWeight.w800,
                              color: colors.title,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            subtitle,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            textAlign: TextAlign.start,
                            style: TextStyle(
                              fontSize: 13,
                              height: 1.55,
                              fontWeight: FontWeight.w500,
                              color: colors.subtitle,
                            ),
                          ),
                          const SizedBox(height: 16),
                          Align(
                            alignment: AlignmentDirectional.centerStart,
                            child: _PillButton(
                              label: buttonLabel,
                              background: colors.button,
                              foreground: colors.buttonText,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _PillButton extends StatelessWidget {
  const _PillButton({
    required this.label,
    required this.background,
    required this.foreground,
  });

  final String label;
  final Color background;
  final Color foreground;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 9),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: foreground,
              ),
            ),
          ),
          const SizedBox(width: 8),
          DirectionalIcon(
            Icons.arrow_forward_rounded,
            size: 14,
            color: foreground,
          ),
        ],
      ),
    );
  }
}
