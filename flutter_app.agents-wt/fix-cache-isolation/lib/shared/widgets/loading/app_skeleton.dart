import 'package:flutter/material.dart';

import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/motion.dart';

/// Gradient-shimmer bone for loading placeholders.
///
/// Uses one shared [AnimationController] from an ancestor [AppSkeletonScope]
/// when nested; otherwise a local controller. Sweep direction follows
/// [Directionality] (start → end). Reduced motion → static bone.
class AppSkeleton extends StatefulWidget {
  final double? width;
  final double height;
  final double radius;

  const AppSkeleton({
    super.key,
    this.width,
    this.height = 14,
    this.radius = 10,
  });

  @override
  State<AppSkeleton> createState() => _AppSkeletonState();
}

class _AppSkeletonState extends State<AppSkeleton>
    with SingleTickerProviderStateMixin {
  AnimationController? _local;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final scope = AppSkeletonScope.maybeOf(context);
    if (scope != null) return;

    _local ??= AnimationController(
      vsync: this,
      duration: MotionDurations.skeleton,
    )..repeat();
  }

  @override
  void dispose() {
    _local?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (MotionSettings.reduce(context)) {
      return _bone(staticOpacity: 0.7);
    }

    final scope = AppSkeletonScope.maybeOf(context);
    final controller = scope?.controller ?? _local;
    if (controller == null) return _bone(staticOpacity: 0.7);

    final rtl = Directionality.of(context) == TextDirection.rtl;

    return AnimatedBuilder(
      animation: controller,
      builder: (context, _) {
        final t = MotionCurves.skeleton.transform(controller.value);
        // Highlight travels start → end (mirrors in RTL via AlignmentDirectional).
        final start = rtl ? 1.0 - (t * 2 - 0.5) : (t * 2 - 0.5);
        return _bone(shimmerCenter: start);
      },
    );
  }

  Widget _bone({double? staticOpacity, double? shimmerCenter}) {
    final child = Container(
      width: widget.width,
      height: widget.height,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(widget.radius),
        color: staticOpacity != null ? context.sirati.surfaceHigh : null,
        gradient: shimmerCenter == null
            ? null
            : LinearGradient(
                begin: Alignment(-1.0 + shimmerCenter * 2, 0),
                end: Alignment(1.0 + shimmerCenter * 2, 0),
                colors: [
                  context.sirati.surfaceHigh,
                  context.sirati.surfaceLow,
                  context.sirati.surface,
                  context.sirati.surfaceLow,
                  context.sirati.surfaceHigh,
                ],
                stops: const [0.0, 0.35, 0.5, 0.65, 1.0],
              ),
      ),
    );

    if (staticOpacity != null) {
      return Opacity(opacity: staticOpacity, child: child);
    }
    return child;
  }
}

/// Shares one shimmer animation across many [AppSkeleton] children.
class AppSkeletonScope extends StatefulWidget {
  final Widget child;

  const AppSkeletonScope({super.key, required this.child});

  static AppSkeletonScopeState? maybeOf(BuildContext context) {
    return context.findAncestorStateOfType<AppSkeletonScopeState>();
  }

  @override
  State<AppSkeletonScope> createState() => AppSkeletonScopeState();
}

class AppSkeletonScopeState extends State<AppSkeletonScope>
    with SingleTickerProviderStateMixin {
  late final AnimationController controller;

  /// Kept for any callers still reading the opacity animation.
  late final Animation<double> animation;

  @override
  void initState() {
    super.initState();
    controller = AnimationController(
      vsync: this,
      duration: MotionDurations.skeleton,
    );
    animation = Tween<double>(begin: 0, end: 1).animate(
      CurvedAnimation(parent: controller, curve: MotionCurves.skeleton),
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MotionSettings.reduce(context)) {
      controller.stop();
      controller.value = 0;
    } else if (!controller.isAnimating) {
      controller.repeat();
    }
  }

  @override
  void dispose() {
    controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.child;
}

// ── Screen-shaped skeletons ─────────────────────────────────────────────────

class DashboardSkeleton extends StatelessWidget {
  final double horizontalPadding;

  const DashboardSkeleton({super.key, this.horizontalPadding = 20});

  @override
  Widget build(BuildContext context) {
    return AppSkeletonScope(
      child: ListView(
        padding:
            EdgeInsets.fromLTRB(horizontalPadding, 18, horizontalPadding, 112),
        children: const [
          Row(
            children: [
              AppSkeleton(width: 46, height: 46, radius: 23),
              SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    AppSkeleton(width: 140, height: 16),
                    SizedBox(height: 8),
                    AppSkeleton(width: 88, height: 12),
                  ],
                ),
              ),
              AppSkeleton(width: 42, height: 42, radius: 21),
            ],
          ),
          SizedBox(height: 20),
          AppSkeleton(height: 160, radius: 20),
          SizedBox(height: 14),
          AppSkeleton(height: 160, radius: 20),
          SizedBox(height: 18),
          AppSkeleton(width: 120, height: 40, radius: 12),
          SizedBox(height: 14),
          Row(
            children: [
              Expanded(child: AppSkeleton(width: 100, height: 16)),
              AppSkeleton(width: 64, height: 14),
            ],
          ),
          SizedBox(height: 12),
          AppSkeleton(height: 72, radius: 16),
          SizedBox(height: 10),
          AppSkeleton(height: 72, radius: 16),
          SizedBox(height: 10),
          AppSkeleton(height: 72, radius: 16),
        ],
      ),
    );
  }
}

class CvListSkeleton extends StatelessWidget {
  final double horizontalPadding;

  const CvListSkeleton({super.key, this.horizontalPadding = 20});

  @override
  Widget build(BuildContext context) {
    return AppSkeletonScope(
      child: ListView(
        padding:
            EdgeInsets.fromLTRB(horizontalPadding, 18, horizontalPadding, 112),
        children: const [
          Row(
            children: [
              AppSkeleton(width: 46, height: 46, radius: 23),
              SizedBox(width: 12),
              Expanded(child: AppSkeleton(height: 18)),
              SizedBox(width: 12),
              AppSkeleton(width: 42, height: 42, radius: 21),
            ],
          ),
          SizedBox(height: 12),
          AppSkeleton(width: 180, height: 14),
          SizedBox(height: 18),
          AppSkeleton(height: 56, radius: 16),
          SizedBox(height: 12),
          AppSkeleton(height: 96, radius: 16),
          SizedBox(height: 10),
          AppSkeleton(height: 96, radius: 16),
          SizedBox(height: 10),
          AppSkeleton(height: 96, radius: 16),
        ],
      ),
    );
  }
}

class JobNewsSkeleton extends StatelessWidget {
  final double horizontalPadding;

  const JobNewsSkeleton({super.key, this.horizontalPadding = 20});

  @override
  Widget build(BuildContext context) {
    return AppSkeletonScope(
      child: ListView(
        padding:
            EdgeInsets.fromLTRB(horizontalPadding, 18, horizontalPadding, 112),
        children: const [
          Row(
            children: [
              AppSkeleton(width: 46, height: 46, radius: 23),
              SizedBox(width: 12),
              Expanded(child: AppSkeleton(height: 18)),
              SizedBox(width: 12),
              AppSkeleton(width: 42, height: 42, radius: 21),
            ],
          ),
          SizedBox(height: 16),
          AppSkeleton(height: 48, radius: 14),
          SizedBox(height: 14),
          AppSkeleton(height: 160, radius: 18),
          SizedBox(height: 16),
          AppSkeleton(width: 100, height: 16),
          SizedBox(height: 10),
          AppSkeleton(height: 88, radius: 16),
          SizedBox(height: 10),
          AppSkeleton(height: 88, radius: 16),
          SizedBox(height: 10),
          AppSkeleton(height: 88, radius: 16),
        ],
      ),
    );
  }
}

class ListScreenSkeleton extends StatelessWidget {
  final int itemCount;
  final EdgeInsetsGeometry padding;

  const ListScreenSkeleton({
    super.key,
    this.itemCount = 5,
    this.padding = const EdgeInsets.all(AppSpacing.md),
  });

  @override
  Widget build(BuildContext context) {
    return AppSkeletonScope(
      child: ListView.separated(
        padding: padding,
        itemCount: itemCount,
        separatorBuilder: (_, __) => const SizedBox(height: 10),
        itemBuilder: (_, __) => const AppSkeleton(height: 88, radius: 16),
      ),
    );
  }
}

class EducationSkeleton extends StatelessWidget {
  final double horizontalPadding;

  const EducationSkeleton({super.key, this.horizontalPadding = 20});

  @override
  Widget build(BuildContext context) {
    return AppSkeletonScope(
      child: ListView(
        padding:
            EdgeInsets.fromLTRB(horizontalPadding, 18, horizontalPadding, 112),
        children: const [
          Row(
            children: [
              AppSkeleton(width: 46, height: 46, radius: 23),
              SizedBox(width: 12),
              Expanded(child: AppSkeleton(height: 18)),
              SizedBox(width: 12),
              AppSkeleton(width: 42, height: 42, radius: 21),
            ],
          ),
          SizedBox(height: 16),
          Row(
            children: [
              AppSkeleton(width: 72, height: 32, radius: 999),
              SizedBox(width: 8),
              AppSkeleton(width: 96, height: 32, radius: 999),
              SizedBox(width: 8),
              AppSkeleton(width: 80, height: 32, radius: 999),
            ],
          ),
          SizedBox(height: 18),
          AppSkeleton(height: 120, radius: 16),
          SizedBox(height: 10),
          AppSkeleton(height: 120, radius: 16),
          SizedBox(height: 10),
          AppSkeleton(height: 120, radius: 16),
        ],
      ),
    );
  }
}
