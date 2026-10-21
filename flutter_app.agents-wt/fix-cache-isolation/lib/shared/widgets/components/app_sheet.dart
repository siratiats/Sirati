import 'package:flutter/material.dart';

import 'package:sirati/shared/theme/app_theme.dart';

Future<T?> showAppBottomSheet<T>({
  required BuildContext context,
  required Widget child,
  String? semanticLabel,
}) {
  final c = context.sirati;
  return showModalBottomSheet<T>(
    context: context,
    backgroundColor: c.surface,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(AppRadius.xl)),
    ),
    builder: (context) {
      return Semantics(
        namesRoute: true,
        label: semanticLabel,
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.lg,
              AppSpacing.md,
              AppSpacing.lg,
              AppSpacing.lg,
            ),
            child: child,
          ),
        ),
      );
    },
  );
}
