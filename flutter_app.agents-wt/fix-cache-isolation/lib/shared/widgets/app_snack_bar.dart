import 'package:flutter/material.dart';

import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/shared/theme/app_theme.dart';

/// Semantic status of a snackbar / toast — drives icon, color, and copy.
enum AppSnackBarVariant { error, success, info, warning }

/// Styled snackbar helper for the entire app.
///
/// Replaces the plain `ScaffoldMessenger.showSnackBar(SnackBar(...))` pattern
/// with a consistent, branded, animated toast that includes:
/// * A colored leading icon chip.
/// * A rounded floating surface with the appropriate variant color.
/// * An optional action button (e.g. "Retry" for retryable failures).
///
/// Use the named helpers ([error], [success], [info], [warning]) or the
/// [fromException] shortcut which picks a variant/icon from an [ApiException].

const _clear = Colors.transparent; // ignore: hardcoded_color -- shell

class AppSnackBar {
  AppSnackBar._();

  static ScaffoldFeatureController<SnackBar, SnackBarClosedReason> show(
    BuildContext context, {
    required String message,
    AppSnackBarVariant variant = AppSnackBarVariant.info,
    IconData? icon,
    String? actionLabel,
    VoidCallback? onAction,
    Duration duration = const Duration(seconds: 4),
  }) {
    final scheme = _scheme(context, variant);
    final resolvedIcon = icon ?? _iconFor(variant);

    return ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        // The content paints its own surface.
        backgroundColor: _clear,
        elevation: 0,
        padding: EdgeInsets.zero,
        margin: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        duration: duration,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
        ),
        content: _AppSnackBarContent(
          message: message,
          icon: resolvedIcon,
          scheme: scheme,
          actionLabel: actionLabel,
          onAction: onAction,
        ),
      ),
    );
  }

  static void error(
    BuildContext context,
    String message, {
    String? actionLabel,
    VoidCallback? onAction,
  }) =>
      show(
        context,
        message: message,
        variant: AppSnackBarVariant.error,
        actionLabel: actionLabel,
        onAction: onAction,
      );

  static void success(BuildContext context, String message) => show(
        context,
        message: message,
        variant: AppSnackBarVariant.success,
      );

  static void info(BuildContext context, String message) => show(
        context,
        message: message,
        variant: AppSnackBarVariant.info,
      );

  static void warning(BuildContext context, String message) => show(
        context,
        message: message,
        variant: AppSnackBarVariant.warning,
      );

  /// Show a snackbar for an [ApiException], picking the correct icon and
  /// offering a Retry action when the underlying error is retryable.
  static void fromException(
    BuildContext context,
    ApiException exception, {
    String retryLabel = 'Retry',
    VoidCallback? onRetry,
  }) {
    show(
      context,
      message: exception.displayMessage,
      variant: exception.type == ApiErrorType.rateLimited
          ? AppSnackBarVariant.warning
          : AppSnackBarVariant.error,
      icon: _iconForApiType(exception.type),
      actionLabel: exception.isRetryable && onRetry != null ? retryLabel : null,
      onAction: onRetry,
    );
  }

  static _SnackBarScheme _scheme(
      BuildContext context, AppSnackBarVariant variant) {
    final c = context.sirati;
    switch (variant) {
      case AppSnackBarVariant.error:
        return _SnackBarScheme(
          background: c.errorLight,
          border: c.error,
          // Gated pair, inverted: on-container square, container glyph.
          iconBackground: c.onErrorContainer,
          iconColor: c.errorLight,
          textColor: c.textPrimary,
          accent: c.error,
        );
      case AppSnackBarVariant.success:
        return _SnackBarScheme(
          background: c.successLight,
          border: c.success,
          // Gated pair, inverted: on-container square, container glyph.
          iconBackground: c.onSuccessContainer,
          iconColor: c.successLight,
          textColor: c.textPrimary,
          accent: c.success,
        );
      case AppSnackBarVariant.warning:
        return _SnackBarScheme(
          background: c.warningLight,
          border: c.warning,
          // Gated pair, inverted: on-container square, container glyph.
          iconBackground: c.onWarningContainer,
          iconColor: c.warningLight,
          textColor: c.textPrimary,
          accent: c.warning,
        );
      case AppSnackBarVariant.info:
        return _SnackBarScheme(
          background: c.infoLight,
          border: c.info,
          // Gated pair, inverted: on-container square, container glyph.
          iconBackground: c.onInfoContainer,
          iconColor: c.infoLight,
          textColor: c.textPrimary,
          accent: c.info,
        );
    }
  }

  static IconData _iconFor(AppSnackBarVariant variant) {
    switch (variant) {
      case AppSnackBarVariant.error:
        return Icons.error_outline_rounded;
      case AppSnackBarVariant.success:
        return Icons.check_circle_outline_rounded;
      case AppSnackBarVariant.warning:
        return Icons.warning_amber_rounded;
      case AppSnackBarVariant.info:
        return Icons.info_outline_rounded;
    }
  }

  static IconData _iconForApiType(ApiErrorType type) {
    switch (type) {
      case ApiErrorType.network:
        return Icons.wifi_off_rounded;
      case ApiErrorType.timeout:
        return Icons.timer_off_outlined;
      case ApiErrorType.auth:
        return Icons.lock_outline_rounded;
      case ApiErrorType.notFound:
        return Icons.search_off_rounded;
      case ApiErrorType.server:
        return Icons.cloud_off_rounded;
      case ApiErrorType.rateLimited:
        return Icons.hourglass_top_rounded;
      case ApiErrorType.validation:
      case ApiErrorType.unknown:
        return Icons.error_outline_rounded;
    }
  }
}

class _SnackBarScheme {
  final Color background;
  final Color border;
  final Color iconBackground;
  final Color iconColor;
  final Color textColor;
  final Color accent;

  const _SnackBarScheme({
    required this.background,
    required this.border,
    required this.iconBackground,
    required this.iconColor,
    required this.textColor,
    required this.accent,
  });
}

class _AppSnackBarContent extends StatelessWidget {
  final String message;
  final IconData icon;
  final _SnackBarScheme scheme;
  final String? actionLabel;
  final VoidCallback? onAction;

  const _AppSnackBarContent({
    required this.message,
    required this.icon,
    required this.scheme,
    this.actionLabel,
    this.onAction,
  });

  @override
  Widget build(BuildContext context) {
    final showAction =
        actionLabel != null && actionLabel!.isNotEmpty && onAction != null;

    return Material(
      type: MaterialType.transparency,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: scheme.background,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: scheme.border.withValues(alpha: .35)),
          boxShadow: [
            BoxShadow(
              color: scheme.accent.withValues(alpha: .12),
              blurRadius: 18,
              offset: const Offset(0, 6),
            ),
          ],
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: scheme.iconBackground,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, size: 20, color: scheme.iconColor),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                message,
                textAlign: TextAlign.start,
                style: TextStyle(
                  fontSize: 13.5,
                  height: 1.4,
                  fontWeight: FontWeight.w600,
                  color: scheme.textColor,
                ),
              ),
            ),
            if (showAction) ...[
              const SizedBox(width: 8),
              TextButton(
                onPressed: () {
                  ScaffoldMessenger.of(context).hideCurrentSnackBar();
                  onAction!();
                },
                style: TextButton.styleFrom(
                  foregroundColor: scheme.accent,
                  padding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  minimumSize: const Size(0, 32),
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  textStyle: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                child: Text(actionLabel!),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
