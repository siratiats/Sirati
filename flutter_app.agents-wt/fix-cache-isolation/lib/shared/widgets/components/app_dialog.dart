import 'package:flutter/material.dart';

import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/components/app_button.dart';

Future<T?> showAppDialog<T>({
  required BuildContext context,
  required String title,
  required String body,
  String? confirmLabel,
  String? cancelLabel,
  VoidCallback? onConfirm,
  bool destructive = false,
}) {
  final c = context.sirati;
  return showDialog<T>(
    context: context,
    builder: (context) {
      return AlertDialog(
        backgroundColor: c.surface,
        shape: RoundedRectangleBorder(borderRadius: AppRadius.xlAll),
        title: Text(title,
            style: AppTypography.of(context, AppTypography.titleLg)),
        content: Text(
          body,
          style: AppTypography.of(context, AppTypography.bodyMd,
              color: c.textSecondary),
        ),
        actions: [
          if (cancelLabel != null)
            AppButton.text(
              label: cancelLabel,
              onPressed: () => Navigator.of(context).pop(),
            ),
          AppButton(
            label: confirmLabel ??
                (Directionality.of(context) == TextDirection.rtl
                    ? 'حسناً'
                    : 'OK'),
            variant: destructive
                ? AppButtonVariant.destructive
                : AppButtonVariant.primary,
            expand: false,
            onPressed: () {
              Navigator.of(context).pop();
              onConfirm?.call();
            },
          ),
        ],
      );
    },
  );
}
