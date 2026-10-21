import 'package:flutter/material.dart';

import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/directional_icon.dart';
import 'package:sirati/shared/widgets/motion.dart';

/// Direction-aware list row used across Home activity, history, and similar
/// surfaces.
///
/// Layout (logical order, mirrors with ambient [Directionality]):
/// leading → title/subtitle → trailing (default chevron)
class AppListTile extends StatelessWidget {
  final Widget? leading;
  final IconData? leadingIcon;
  final Color? leadingBackground;
  final Color? leadingIconColor;
  final String title;
  final String? subtitle;
  final Widget? trailing;
  final bool showChevron;
  final VoidCallback? onTap;
  final EdgeInsetsGeometry padding;
  final Color? titleColor;

  const AppListTile({
    super.key,
    this.leading,
    this.leadingIcon,
    this.leadingBackground,
    this.leadingIconColor,
    required this.title,
    this.subtitle,
    this.trailing,
    this.showChevron = true,
    this.onTap,
    this.padding = const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
    this.titleColor,
  });

  @override
  Widget build(BuildContext context) {
    final resolvedLeading = leading ??
        (leadingIcon == null
            ? null
            : _LeadingIconBox(
                icon: leadingIcon!,
                background: leadingBackground ?? context.sirati.primaryLight,
                iconColor: leadingIconColor ?? context.sirati.primary,
              ));

    final semanticsLabel = subtitle != null && subtitle!.trim().isNotEmpty
        ? '$title. $subtitle'
        : title;

    final content = Container(
      padding: padding,
      decoration: BoxDecoration(
        color: context.sirati.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: context.sirati.border),
      ),
      child: Row(
        children: [
          if (resolvedLeading != null) ...[
            ExcludeSemantics(child: resolvedLeading),
            const SizedBox(width: 12),
          ],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  textAlign: TextAlign.start,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: AppTextStyles.titleMd(context.sirati).copyWith(
                    color: titleColor ?? context.sirati.textPrimary,
                  ),
                ),
                if (subtitle != null && subtitle!.trim().isNotEmpty) ...[
                  const SizedBox(height: 5),
                  Text(
                    subtitle!,
                    textAlign: TextAlign.start,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextStyles.bodySm(context.sirati),
                  ),
                ],
              ],
            ),
          ),
          if (trailing != null) ...[
            const SizedBox(width: 8),
            trailing!,
          ] else if (showChevron) ...[
            const SizedBox(width: 8),
            // Decorative; whole row is the control when [onTap] is set.
            ExcludeSemantics(
              child: DirectionalIcon(
                Icons.arrow_forward_ios_rounded,
                size: 14,
                color: context.sirati.textHint,
              ),
            ),
          ],
        ],
      ),
    );

    if (onTap == null) {
      return MergeSemantics(child: content);
    }

    // Single semantic node for TalkBack; decorative chrome excluded above.
    return Semantics(
      button: true,
      label: semanticsLabel,
      child: ExcludeSemantics(
        child: PressScale(
          child: Material(
            type: MaterialType.transparency,
            child: InkWell(
              onTap: onTap,
              borderRadius: BorderRadius.circular(16),
              child: content,
            ),
          ),
        ),
      ),
    );
  }
}

class _LeadingIconBox extends StatelessWidget {
  final IconData icon;
  final Color background;
  final Color iconColor;

  const _LeadingIconBox({
    required this.icon,
    required this.background,
    required this.iconColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 40,
      height: 40,
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Icon(icon, color: iconColor, size: 18),
    );
  }
}
