import 'package:flutter/material.dart';

import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/motion.dart';
import 'package:sirati/shared/widgets/success_beat.dart';

/// ATS / match progress bar with a satisfying count-up + overshoot.
///
/// Uses an explicit [AnimationController] and [Curves.elasticOut] so the fill
/// races past the target then settles. When [celebrateOnComplete] is true,
/// [SuccessBeat.celebrate] fires the **instant** the controller completes
/// (haptic always; full visual overlay only if [celebrateFullOverlay] is true).
///
/// Rebuilds: only this widget listens to the controller; parent rebuilds that
/// keep the same [value] do not restart the animation.
class AnimatedAtsScoreBar extends StatefulWidget {
  /// Progress in the range 0.0–1.0.
  final double value;
  final Color color;
  final double height;
  final double borderRadius;
  final Color? trackColor;
  final String? semanticLabel;

  /// Fire [SuccessBeat] when the intro animation finishes.
  final bool celebrateOnComplete;

  /// When celebrating, show the full-screen check overlay (usually only once
  /// per screen for the primary score). Default: haptic only.
  final bool celebrateFullOverlay;

  /// Optional extra hook after the animation (and celebration) complete.
  final VoidCallback? onAnimationComplete;

  /// Animation length for the elastic fill.
  final Duration duration;

  const AnimatedAtsScoreBar({
    super.key,
    required this.value,
    required this.color,
    this.height = 5,
    this.borderRadius = 4,
    this.trackColor,
    this.semanticLabel,
    this.celebrateOnComplete = false,
    this.celebrateFullOverlay = false,
    this.onAnimationComplete,
    this.duration = const Duration(milliseconds: 1100),
  });

  @override
  State<AnimatedAtsScoreBar> createState() => _AnimatedAtsScoreBarState();
}

class _AnimatedAtsScoreBarState extends State<AnimatedAtsScoreBar>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  Animation<double>? _animation;

  /// Target used for the running / completed animation (avoids restarts).
  double _playedTarget = -1;
  bool _hasCompleted = false;
  bool _celebrateArmed = false;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(vsync: this, duration: widget.duration);
    _controller.addStatusListener(_onStatus);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _ensureAnimation();
  }

  @override
  void didUpdateWidget(covariant AnimatedAtsScoreBar oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.duration != widget.duration) {
      _controller.duration = widget.duration;
    }
    final next = widget.value.clamp(0.0, 1.0);
    // Restart only when the score actually changes after first play.
    if (_hasCompleted && (next - _playedTarget).abs() > 0.001) {
      _hasCompleted = false;
      _playedTarget = -1;
      _ensureAnimation(force: true);
    } else {
      _ensureAnimation();
    }
  }

  @override
  void dispose() {
    _controller.removeStatusListener(_onStatus);
    _controller.dispose();
    super.dispose();
  }

  void _onStatus(AnimationStatus status) {
    if (status != AnimationStatus.completed || _hasCompleted) return;
    _hasCompleted = true;

    if (widget.celebrateOnComplete && _celebrateArmed && mounted) {
      _celebrateArmed = false;
      // Fire the exact moment the controller finishes — do not await overlay
      // for non-blocking criteria bars (haptic is sync enough).
      SuccessBeat.celebrate(
        context,
        fullOverlay: widget.celebrateFullOverlay,
      );
    }
    widget.onAnimationComplete?.call();
  }

  void _ensureAnimation({bool force = false}) {
    final target = widget.value.clamp(0.0, 1.0);

    if (MotionSettings.reduce(context)) {
      final already = _hasCompleted && (_playedTarget - target).abs() < 0.001;
      _playedTarget = target;
      _animation = AlwaysStoppedAnimation(target);
      if (!already) {
        _hasCompleted = true;
        // Still fire celebration (haptic only under reduce motion).
        if (widget.celebrateOnComplete && mounted) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (!mounted) return;
            SuccessBeat.celebrate(
              context,
              fullOverlay: false,
            );
            widget.onAnimationComplete?.call();
          });
        } else {
          widget.onAnimationComplete?.call();
        }
      }
      return;
    }

    if (!force &&
        _playedTarget >= 0 &&
        (target - _playedTarget).abs() < 0.001) {
      // Same score: keep existing animation / settled value.
      return;
    }

    _playedTarget = target;
    _hasCompleted = false;
    _celebrateArmed = widget.celebrateOnComplete;

    // elasticOut overshoots past [target] then settles — satisfying snap-back.
    // widthFactor is clamped to 1.0 so a 100% score still fills the track cleanly.
    _animation = Tween<double>(begin: 0, end: target).animate(
      CurvedAnimation(
        parent: _controller,
        curve: Curves.elasticOut,
      ),
    );

    _controller
      ..duration = widget.duration
      ..forward(from: 0);
  }

  @override
  Widget build(BuildContext context) {
    final track = widget.trackColor ?? context.sirati.border;
    final clamped = widget.value.clamp(0.0, 1.0);
    final anim = _animation;

    final bar = anim == null || MotionSettings.reduce(context)
        ? _Bar(
            progress: clamped,
            color: widget.color,
            height: widget.height,
            borderRadius: widget.borderRadius,
            trackColor: track,
          )
        : AnimatedBuilder(
            animation: anim,
            builder: (context, _) {
              // Allow slight overshoot past target but never past full track.
              final raw = anim.value;
              final progress = raw.clamp(0.0, 1.0);
              return _Bar(
                progress: progress,
                color: widget.color,
                height: widget.height,
                borderRadius: widget.borderRadius,
                trackColor: track,
              );
            },
          );

    return Semantics(
      label: widget.semanticLabel,
      value: '${(clamped * 100).round()}%',
      readOnly: true,
      child: ExcludeSemantics(child: bar),
    );
  }
}

class _Bar extends StatelessWidget {
  final double progress;
  final Color color;
  final double height;
  final double borderRadius;
  final Color trackColor;

  const _Bar({
    required this.progress,
    required this.color,
    required this.height,
    required this.borderRadius,
    required this.trackColor,
  });

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(borderRadius),
      child: SizedBox(
        height: height,
        child: Stack(
          fit: StackFit.expand,
          children: [
            ColoredBox(color: trackColor),
            // Start edge: LTR left / RTL right.
            FractionallySizedBox(
              widthFactor: progress.clamp(0.0, 1.0),
              alignment: AlignmentDirectional.centerStart,
              child: ColoredBox(color: color),
            ),
          ],
        ),
      ),
    );
  }
}
