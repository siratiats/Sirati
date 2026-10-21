import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/motion.dart';

/// Brief checkmark celebration (haptic and optional visual).
///
/// Reduced motion: haptic only.
/// Full motion + [fullOverlay]: non-dismissible dialog (~700ms).
class SuccessBeat {
  SuccessBeat._();

  static const _holdTotal = Duration(milliseconds: 700);
  static const _animDuration = Duration(milliseconds: 450);

  /// Haptic always; visual full-screen overlay when [fullOverlay] and motion OK.
  ///
  /// Returns as soon as the haptic fires when [fullOverlay] is false (or reduce
  /// motion), so score-bar completion stays snappy. Awaits overlay dismissal
  /// when [fullOverlay] is true.
  static Future<void> celebrate(
    BuildContext context, {
    bool fullOverlay = false,
  }) async {
    await HapticFeedback.mediumImpact();
    if (!context.mounted) return;

    if (MotionSettings.reduce(context) || !fullOverlay) return;

    await showGeneralDialog<void>(
      context: context,
      barrierDismissible: false,
      barrierLabel: 'Success',
      barrierColor: Theme.of(context).colorScheme.scrim.withValues(alpha: 0.18),
      transitionDuration: Duration.zero,
      pageBuilder: (context, animation, secondaryAnimation) {
        return const _SuccessBeatOverlay();
      },
    );
  }

  /// Full celebration used after CV generation (haptic + overlay).
  static Future<void> play(BuildContext context) =>
      celebrate(context, fullOverlay: true);
}

class _SuccessBeatOverlay extends StatefulWidget {
  const _SuccessBeatOverlay();

  @override
  State<_SuccessBeatOverlay> createState() => _SuccessBeatOverlayState();
}

class _SuccessBeatOverlayState extends State<_SuccessBeatOverlay>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _scale;
  late final Animation<double> _opacity;
  Timer? _dismissTimer;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: SuccessBeat._animDuration,
    );
    final curved =
        CurvedAnimation(parent: _controller, curve: MotionCurves.enter);
    _scale = Tween<double>(begin: 0.6, end: 1.0).animate(curved);
    _opacity = Tween<double>(begin: 0, end: 1).animate(curved);

    _controller.forward();
    _dismissTimer = Timer(SuccessBeat._holdTotal, () {
      if (mounted) Navigator.of(context).pop();
    });
  }

  @override
  void dispose() {
    _dismissTimer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Material(
      type: MaterialType.transparency,
      child: Center(
        child: AnimatedBuilder(
          animation: _controller,
          builder: (context, child) {
            return Opacity(
              opacity: _opacity.value,
              child: Transform.scale(
                scale: _scale.value,
                child: child,
              ),
            );
          },
          child: Container(
            width: 88,
            height: 88,
            decoration: BoxDecoration(
              color: context.sirati.primaryLight,
              shape: BoxShape.circle,
            ),
            child: Icon(
              Icons.check_rounded,
              size: 48,
              color: context.sirati.primary,
            ),
          ),
        ),
      ),
    );
  }
}
