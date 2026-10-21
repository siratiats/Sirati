import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';

class MotionDurations {
  static const fast = Duration(milliseconds: 140);
  static const medium = Duration(milliseconds: 200);
  static const slow = Duration(milliseconds: 280);

  /// Soft pulse for skeleton placeholders (single shared loop feel).
  static const skeleton = Duration(milliseconds: 1100);

  /// Max staggered reveal delay across a screen (order * step, capped).
  static const maxRevealDelay = Duration(milliseconds: 120);
  static const revealStep = Duration(milliseconds: 24);
}

class MotionCurves {
  static const enter = Cubic(0.22, 1, 0.36, 1);
  static const state = Cubic(0.25, 1, 0.5, 1);
  static const exit = Curves.easeInCubic;
  static const skeleton = Curves.easeInOut;
}

class MotionSettings {
  /// Accessibility only: OS "remove animations" / disableAnimations.
  ///
  /// Use for any motion that should fully stop (transitions, shimmer, press
  /// scale, etc.). Screen size is intentionally **not** part of this gate.
  static bool reduce(BuildContext context) {
    final mediaQuery = MediaQuery.maybeOf(context);
    if (mediaQuery == null) return false;
    return mediaQuery.disableAnimations;
  }

  /// Perf tuning for stacked list reveals: a11y reduce **or** large screens
  /// (shortestSide ≥ 700). Use only to skip/shorten [MotionReveal] stagger
  /// delays — not to disable global motion.
  static bool limitStagger(BuildContext context) {
    final mediaQuery = MediaQuery.maybeOf(context);
    if (mediaQuery == null) return false;
    if (mediaQuery.disableAnimations) return true;
    return mediaQuery.size.shortestSide >= 700;
  }
}

/// Direction-aware horizontal offsets for enter/exit motion.
///
/// [direction] is +1 for forward (push / next step) and -1 for back.
/// Forward motion enters from the **end** edge (right in LTR, left in RTL).
class MotionAxis {
  MotionAxis._();

  static double endSign(BuildContext context) {
    return Directionality.of(context) == TextDirection.rtl ? -1.0 : 1.0;
  }

  static Offset slideIn({
    required BuildContext context,
    double distance = 0.04,
    int direction = 1,
  }) {
    return Offset(endSign(context) * distance * direction, 0);
  }
}

class SiratiPageTransitionsBuilder extends PageTransitionsBuilder {
  const SiratiPageTransitionsBuilder();

  @override
  Widget buildTransitions<T>(
    PageRoute<T> route,
    BuildContext context,
    Animation<double> animation,
    Animation<double> secondaryAnimation,
    Widget child,
  ) {
    if (MotionSettings.reduce(context) || route.isFirst) return child;

    final curved = CurvedAnimation(
      parent: animation,
      curve: MotionCurves.enter,
      reverseCurve: MotionCurves.exit,
    );

    // Forward push enters from the end edge (mirrors in RTL).
    final begin = MotionAxis.slideIn(context: context, distance: 0.04);

    return FadeTransition(
      opacity: curved,
      child: SlideTransition(
        position: Tween<Offset>(
          begin: begin,
          end: Offset.zero,
        ).animate(curved),
        child: child,
      ),
    );
  }
}

class MotionTabStack extends StatefulWidget {
  final int currentIndex;
  final List<Widget> children;

  const MotionTabStack({
    super.key,
    required this.currentIndex,
    required this.children,
  });

  @override
  State<MotionTabStack> createState() => _MotionTabStackState();
}

class _MotionTabStackState extends State<MotionTabStack> {
  late int _renderedIndex = widget.currentIndex;
  bool _animateTabChanges = false;

  @override
  void didUpdateWidget(covariant MotionTabStack oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.currentIndex != widget.currentIndex) {
      _renderedIndex = widget.currentIndex;
      _animateTabChanges = true;
    }
  }

  @override
  Widget build(BuildContext context) {
    // IndexedStack is always the base so only the active tab is ever painted.
    // This guarantees inactive tabs can never leak/overlap content (a known
    // iOS compositing issue when hiding tabs with opacity alone), while every
    // tab stays mounted to preserve scroll/form state.
    //
    // Each slot keeps a stable pane widget so form state survives tab changes.
    // The first painted Home frame is fully visible; enter motion runs only
    // when the user actually changes tabs.
    if (MotionSettings.reduce(context)) {
      return IndexedStack(index: _renderedIndex, children: widget.children);
    }

    final directionSign = MotionAxis.endSign(context);

    return IndexedStack(
      index: _renderedIndex,
      children: [
        for (var index = 0; index < widget.children.length; index++)
          _TabEnterTransition(
            key: ValueKey('tab-$index'),
            active: index == _renderedIndex,
            animateOnActivate: _animateTabChanges,
            directionSign: directionSign,
            child: widget.children[index],
          ),
      ],
    );
  }
}

/// Keeps one stable pane per tab. The first frame is fully visible; later
/// activations fade/slide in without rebuilding the tab's child state.
class _TabEnterTransition extends StatefulWidget {
  final bool active;
  final bool animateOnActivate;
  final double directionSign;
  final Widget child;

  const _TabEnterTransition({
    super.key,
    required this.active,
    required this.animateOnActivate,
    required this.directionSign,
    required this.child,
  });

  @override
  State<_TabEnterTransition> createState() => _TabEnterTransitionState();
}

class _TabEnterTransitionState extends State<_TabEnterTransition>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    value: 1,
    duration: MotionDurations.medium,
  );
  late final Animation<double> _curved = CurvedAnimation(
    parent: _controller,
    curve: MotionCurves.enter,
  );

  @override
  void didUpdateWidget(covariant _TabEnterTransition oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active &&
        !oldWidget.active &&
        widget.animateOnActivate &&
        _controller.value == 1) {
      _controller.forward(from: 0);
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _curved,
      builder: (context, child) {
        final value = _curved.value;
        return Opacity(
          opacity: value,
          child: Transform.translate(
            offset: Offset(widget.directionSign * .035 * (1 - value), 0),
            child: child,
          ),
        );
      },
      child: widget.child,
    );
  }
}

/// Smoothly replaces loading, error, empty, and data states without delaying
/// interaction. The caller supplies a stable [stateKey] for each state.
class MotionStateSwitcher extends StatelessWidget {
  final Object stateKey;
  final Widget child;

  const MotionStateSwitcher({
    super.key,
    required this.stateKey,
    required this.child,
  });

  @override
  Widget build(BuildContext context) {
    final keyedChild = KeyedSubtree(key: ValueKey(stateKey), child: child);
    if (MotionSettings.reduce(context)) return keyedChild;

    return AnimatedSwitcher(
      duration: MotionDurations.medium,
      reverseDuration: MotionDurations.fast,
      switchInCurve: MotionCurves.enter,
      switchOutCurve: MotionCurves.exit,
      layoutBuilder: (currentChild, previousChildren) => Stack(
        fit: StackFit.passthrough,
        alignment: Alignment.topCenter,
        children: [...previousChildren, if (currentChild != null) currentChild],
      ),
      transitionBuilder: (child, animation) {
        final slide = Tween<Offset>(
          begin: const Offset(0, .012),
          end: Offset.zero,
        ).animate(animation);
        return FadeTransition(
          opacity: animation,
          child: SlideTransition(position: slide, child: child),
        );
      },
      child: keyedChild,
    );
  }
}

/// Animated selection treatment for bottom-navigation icons.
///
/// The capsule clarifies the active destination while the icon swap and
/// slight scale acknowledge the tab change. Reduced motion keeps the same
/// selected styling without interpolation.
class MotionNavIcon extends StatelessWidget {
  final IconData icon;
  final IconData selectedIcon;
  final bool selected;
  final Color selectedBackgroundColor;

  const MotionNavIcon({
    super.key,
    required this.icon,
    required this.selectedIcon,
    required this.selected,
    required this.selectedBackgroundColor,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 44,
      height: 32,
      child: Center(
        child: Icon(
          selected ? selectedIcon : icon,
          key: ValueKey(selected),
        ),
      ),
    );
  }
}

class MotionReveal extends StatefulWidget {
  final Widget child;
  final int order;
  final Offset offset;
  final Duration duration;

  /// Stagger only the first N items in a list for scroll perf.
  static const maxStaggerIndex = 5;

  const MotionReveal({
    super.key,
    required this.child,
    this.order = 0,
    this.offset = const Offset(0, .025),
    this.duration = MotionDurations.medium,
  });

  @override
  State<MotionReveal> createState() => _MotionRevealState();
}

class _MotionRevealState extends State<MotionReveal>
    with SingleTickerProviderStateMixin {
  AnimationController? _controller;
  Animation<double>? _opacity;
  Animation<Offset>? _slide;
  Timer? _timer;
  bool _skipMotion = false;

  @override
  void initState() {
    super.initState();
    // Long lists: skip controllers beyond the first few items.
    if (widget.order > MotionReveal.maxStaggerIndex) {
      _skipMotion = true;
      return;
    }
    _controller = AnimationController(vsync: this, duration: widget.duration);
    final curved =
        CurvedAnimation(parent: _controller!, curve: MotionCurves.enter);
    _opacity = Tween<double>(begin: 0, end: 1).animate(curved);
    _slide =
        Tween<Offset>(begin: widget.offset, end: Offset.zero).animate(curved);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_skipMotion) return;
    final controller = _controller;
    if (controller == null) return;

    // Full a11y reduce: snap visible, no entrance motion.
    if (MotionSettings.reduce(context)) {
      controller.value = 1;
      return;
    }

    if (controller.status == AnimationStatus.dismissed && _timer == null) {
      // Tablets (and reduce) skip stagger delays to limit stacked-list jank;
      // the fade/slide still runs when animations are allowed.
      final delayMs = MotionSettings.limitStagger(context)
          ? 0
          : math.min(
              widget.order * MotionDurations.revealStep.inMilliseconds,
              MotionDurations.maxRevealDelay.inMilliseconds,
            );
      if (delayMs == 0) {
        controller.forward();
      } else {
        _timer = Timer(Duration(milliseconds: delayMs), () {
          if (mounted) controller.forward();
        });
      }
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_skipMotion || MotionSettings.reduce(context)) return widget.child;
    final opacity = _opacity;
    final slide = _slide;
    if (opacity == null || slide == null) return widget.child;

    return FadeTransition(
      opacity: opacity,
      child: SlideTransition(position: slide, child: widget.child),
    );
  }
}

class PressScale extends StatefulWidget {
  final Widget child;
  final bool enabled;
  final double pressedScale;

  const PressScale({
    super.key,
    required this.child,
    this.enabled = true,
    this.pressedScale = .975,
  });

  @override
  State<PressScale> createState() => _PressScaleState();
}

class _PressScaleState extends State<PressScale> {
  bool _pressed = false;

  void _setPressed(bool value) {
    if (_pressed == value || !widget.enabled) return;
    setState(() => _pressed = value);
  }

  @override
  Widget build(BuildContext context) {
    if (MotionSettings.reduce(context) || !widget.enabled) return widget.child;

    return Listener(
      onPointerDown: (_) => _setPressed(true),
      onPointerUp: (_) => _setPressed(false),
      onPointerCancel: (_) => _setPressed(false),
      child: AnimatedScale(
        scale: _pressed ? widget.pressedScale : 1,
        duration: MotionDurations.fast,
        curve: MotionCurves.state,
        child: widget.child,
      ),
    );
  }
}
