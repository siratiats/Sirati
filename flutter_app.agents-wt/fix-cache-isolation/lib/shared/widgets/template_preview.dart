import 'package:flutter/material.dart';

import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/loading/app_skeleton.dart';
import 'package:sirati/shared/widgets/motion.dart';

/// Compact CV template preview for 44×56 list/picker slots.
///
/// - Decodes at slot × DPR via [Image.network.cacheWidth]
/// - Shows [AppSkeleton] while bytes load
/// - Fades in over 200ms with [MotionCurves.enter] (skipped when reduce motion)
class TemplatePreview extends StatelessWidget {
  static const double width = 44;
  static const double height = 56;
  static const double radius = 8;

  final String? imageUrl;

  /// Shown when [imageUrl] is null/empty or the network image fails.
  final Widget errorFallback;

  const TemplatePreview({
    super.key,
    required this.imageUrl,
    required this.errorFallback,
  });

  @override
  Widget build(BuildContext context) {
    final url = imageUrl;
    if (url == null || url.isEmpty) {
      return _fallbackShell(context, errorFallback);
    }

    final cacheWidth =
        (width * MediaQuery.of(context).devicePixelRatio).round();

    return ClipRRect(
      borderRadius: BorderRadius.circular(radius),
      child: Image.network(
        url,
        width: width,
        height: height,
        fit: BoxFit.cover,
        cacheWidth: cacheWidth,
        gaplessPlayback: true,
        frameBuilder: (context, child, frame, wasSynchronouslyLoaded) {
          if (MotionSettings.reduce(context) || wasSynchronouslyLoaded) {
            return child;
          }
          return AnimatedOpacity(
            opacity: frame == null ? 0 : 1,
            duration: const Duration(milliseconds: 200),
            curve: MotionCurves.enter,
            child: child,
          );
        },
        loadingBuilder: (context, child, loadingProgress) {
          if (loadingProgress == null) return child;
          return const AppSkeleton(
            width: width,
            height: height,
            radius: radius,
          );
        },
        errorBuilder: (_, __, ___) => _fallbackShell(context, errorFallback),
      ),
    );
  }

  static Widget _fallbackShell(BuildContext context, Widget child) {
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        color: context.sirati.tealLight,
        borderRadius: BorderRadius.circular(radius),
      ),
      child: Center(child: child),
    );
  }
}
