import 'package:flutter/material.dart';

import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/directional_icon.dart';
import 'package:sirati/shared/widgets/motion.dart';

/// Primary/outlined action with fixed height so spinner swap never collapses layout.
class SubmitButton extends StatelessWidget {
  final String label;
  final String? loadingLabel;
  final VoidCallback? onPressed;
  final bool isLoading;
  final IconData? icon;
  final bool outlined;
  final double height;

  const SubmitButton({
    super.key,
    required this.label,
    this.loadingLabel,
    required this.onPressed,
    this.isLoading = false,
    this.icon,
    this.outlined = false,
    this.height = 54,
  });

  @override
  Widget build(BuildContext context) {
    final enabled = onPressed != null && !isLoading;
    final text = isLoading ? (loadingLabel ?? label) : label;
    final spinnerColor =
        outlined ? context.sirati.primary : context.sirati.onPrimary;

    final content = Row(
      mainAxisAlignment: MainAxisAlignment.center,
      mainAxisSize: MainAxisSize.min,
      children: [
        if (isLoading)
          SizedBox(
            width: 20,
            height: 20,
            child: CircularProgressIndicator(
              strokeWidth: 2.4,
              color: spinnerColor,
            ),
          )
        else if (icon != null)
          DirectionalIcon.isDirectional(icon!)
              ? DirectionalIcon(icon!, size: 18)
              : Icon(icon, size: 18),
        if (isLoading || icon != null) const SizedBox(width: AppSpacing.xs),
        Flexible(
          child: Text(
            text,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
          ),
        ),
      ],
    );

    // minimumSize only — fixedSize clips Arabic/large text scales.
    final button = outlined
        ? OutlinedButton(
            onPressed: enabled ? onPressed : null,
            style: OutlinedButton.styleFrom(
              minimumSize: Size(double.infinity, height),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            ),
            child: content,
          )
        : ElevatedButton(
            onPressed: enabled ? onPressed : null,
            style: ElevatedButton.styleFrom(
              minimumSize: Size(double.infinity, height),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              backgroundColor: context.sirati.primary,
              foregroundColor: context.sirati.onPrimary,
              disabledBackgroundColor:
                  context.sirati.primary.withValues(alpha: .55),
              disabledForegroundColor: context.sirati.onPrimary,
            ),
            child: content,
          );

    return PressScale(
      enabled: enabled,
      child: SizedBox(width: double.infinity, child: button),
    );
  }
}
