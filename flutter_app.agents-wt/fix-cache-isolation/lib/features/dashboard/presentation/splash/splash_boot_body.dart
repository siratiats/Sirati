import 'package:flutter/material.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/motion.dart';

/// Two-beat splash: wordmark first, then the brand mark on the second beat with fluid motion.
class SplashBootBody extends StatefulWidget {
  const SplashBootBody({super.key});

  @override
  State<SplashBootBody> createState() => _SplashBootBodyState();
}

class _SplashBootBodyState extends State<SplashBootBody>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _wordmarkOpacity;
  late final Animation<Offset> _wordmarkSlide;
  late final Animation<double> _wordmarkScale;
  late final Animation<double> _markOpacity;
  late final Animation<double> _markScale;
  late final Animation<double> _auraScale;
  late final Animation<double> _auraOpacity;
  late final Animation<double> _logoScale;
  late final Animation<Offset> _brandTextSlide;
  late final Animation<double> _brandTextOpacity;
  late final Animation<double> _statusOpacity;
  late final Animation<Offset> _statusSlide;
  late final Animation<double> _dotsProgress;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 860),
    );

    _wordmarkOpacity = TweenSequence<double>([
      TweenSequenceItem(tween: Tween(begin: 0.0, end: 1.0), weight: 24),
      TweenSequenceItem(tween: ConstantTween(1.0), weight: 14),
      TweenSequenceItem(tween: Tween(begin: 1.0, end: 0.0), weight: 14),
      TweenSequenceItem(tween: ConstantTween(0.0), weight: 48),
    ]).animate(CurvedAnimation(parent: _controller, curve: Curves.easeInOut));

    _wordmarkSlide = Tween<Offset>(
      begin: const Offset(0, 0.08),
      end: Offset.zero,
    ).animate(CurvedAnimation(
      parent: _controller,
      curve: const Interval(0, 0.32, curve: Curves.easeOutCubic),
    ));

    _wordmarkScale = Tween<double>(
      begin: 0.92,
      end: 1.0,
    ).animate(CurvedAnimation(
      parent: _controller,
      curve: const Interval(0, 0.32, curve: Curves.easeOutCubic),
    ));

    // Brand mark circle blooms with spring physics overshoot
    _markOpacity = Tween<double>(begin: 0.0, end: 1.0).animate(CurvedAnimation(
      parent: _controller,
      curve: const Interval(0.38, 0.70, curve: Curves.easeOut),
    ));
    _markScale = Tween<double>(begin: 0.76, end: 1.0).animate(CurvedAnimation(
      parent: _controller,
      curve: const Interval(0.38, 0.82, curve: Curves.easeOutBack),
    ));

    // Outer aura ripple ring expanding outward behind the mark circle
    _auraScale = Tween<double>(begin: 0.92, end: 1.34).animate(CurvedAnimation(
      parent: _controller,
      curve: const Interval(0.48, 0.92, curve: Curves.easeOutQuad),
    ));
    _auraOpacity = TweenSequence<double>([
      TweenSequenceItem(tween: ConstantTween(0.0), weight: 48),
      TweenSequenceItem(tween: Tween(begin: 0.0, end: 0.40), weight: 18),
      TweenSequenceItem(tween: Tween(begin: 0.40, end: 0.0), weight: 34),
    ]).animate(_controller);

    // Inner logo micro-spring
    _logoScale = Tween<double>(begin: 0.68, end: 1.0).animate(CurvedAnimation(
      parent: _controller,
      curve: const Interval(0.46, 0.84, curve: Curves.easeOutBack),
    ));

    // Brand name text glides up
    _brandTextSlide = Tween<Offset>(
      begin: const Offset(0, 0.28),
      end: Offset.zero,
    ).animate(CurvedAnimation(
      parent: _controller,
      curve: const Interval(0.54, 0.86, curve: Curves.easeOutCubic),
    ));
    _brandTextOpacity =
        Tween<double>(begin: 0.0, end: 1.0).animate(CurvedAnimation(
      parent: _controller,
      curve: const Interval(0.52, 0.78, curve: Curves.easeOut),
    ));

    // Status message and wave dots
    _statusOpacity =
        Tween<double>(begin: 0.0, end: 1.0).animate(CurvedAnimation(
      parent: _controller,
      curve: const Interval(0.66, 0.92, curve: Curves.easeOut),
    ));
    _statusSlide = Tween<Offset>(
      begin: const Offset(0, 0.16),
      end: Offset.zero,
    ).animate(CurvedAnimation(
      parent: _controller,
      curve: const Interval(0.66, 0.92, curve: Curves.easeOutCubic),
    ));

    _dotsProgress = CurvedAnimation(
      parent: _controller,
      curve: const Interval(0.70, 1.0, curve: Curves.easeInOut),
    );

    _controller.forward();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final en = AppLocale.isEnglish(context);
    final reduceMotion = MotionSettings.reduce(context);
    final wordmark = Text(
      en ? 'Sirati' : 'سيرتي',
      textAlign: TextAlign.center,
      style: TextStyle(
        fontSize: 34,
        fontWeight: FontWeight.w800,
        letterSpacing: -0.4,
        color: context.sirati.primaryInk,
      ),
    );
    final status = Text(
      en ? 'Preparing workspace…' : 'جارٍ تجهيز مساحتك…',
      textAlign: TextAlign.center,
      style: AppTextStyles.bodySm(context.sirati).copyWith(
        color: context.sirati.textSecondary,
        fontWeight: FontWeight.w500,
      ),
    );

    if (reduceMotion) {
      final staticCircle = _SplashMarkCircle(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const SiratiMark(size: 64, elevated: true),
            const SizedBox(height: AppSpacing.sm),
            Text(
              en ? 'Sirati' : 'سيرتي',
              style: TextStyle(
                fontSize: 22,
                fontWeight: FontWeight.w800,
                color: context.sirati.primaryInk,
              ),
            ),
          ],
        ),
      );

      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            staticCircle,
            const SizedBox(height: AppSpacing.md),
            status,
            const SizedBox(height: 8),
            _SplashDotsWave(
              animationValue: 1.0,
              color: context.sirati.primary,
            ),
          ],
        ),
      );
    }

    return AnimatedBuilder(
      animation: _controller,
      builder: (context, _) {
        final animatedCircle = _SplashMarkCircle(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Transform.scale(
                scale: _logoScale.value,
                child: const SiratiMark(size: 64, elevated: true),
              ),
              const SizedBox(height: AppSpacing.sm),
              SlideTransition(
                position: _brandTextSlide,
                child: Opacity(
                  opacity: _brandTextOpacity.value,
                  child: Text(
                    en ? 'Sirati' : 'سيرتي',
                    style: TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w800,
                      letterSpacing: -0.2,
                      color: context.sirati.primaryInk,
                    ),
                  ),
                ),
              ),
            ],
          ),
        );

        return Center(
          child: Stack(
            alignment: Alignment.center,
            children: [
              // Beat 1: Initial wordmark entrance
              FadeTransition(
                opacity: _wordmarkOpacity,
                child: SlideTransition(
                  position: _wordmarkSlide,
                  child: ScaleTransition(
                    scale: _wordmarkScale,
                    child: wordmark,
                  ),
                ),
              ),
              // Beat 2: Aura ripple ring expanding outward behind the mark circle
              if (_auraOpacity.value > 0.001)
                Opacity(
                  opacity: _auraOpacity.value,
                  child: Transform.scale(
                    scale: _auraScale.value,
                    child: Container(
                      width: 220,
                      height: 220,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: context.sirati.primaryLight,
                          width: 2,
                        ),
                        boxShadow: [
                          BoxShadow(
                            color:
                                context.sirati.primary.withValues(alpha: 0.18),
                            blurRadius: 28,
                            spreadRadius: 3,
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              // Beat 2: Brand mark circle and status
              Opacity(
                opacity: _markOpacity.value,
                child: Transform.scale(
                  scale: _markScale.value,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      animatedCircle,
                      const SizedBox(height: AppSpacing.md),
                      Opacity(
                        opacity: _statusOpacity.value,
                        child: SlideTransition(
                          position: _statusSlide,
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              status,
                              const SizedBox(height: 8),
                              _SplashDotsWave(
                                animationValue: _dotsProgress.value,
                                color: context.sirati.primary,
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _SplashDotsWave extends StatelessWidget {
  final double animationValue;
  final Color color;

  const _SplashDotsWave({
    required this.animationValue,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: List.generate(3, (i) {
        final phase = ((animationValue * 2.5) - (i * 0.4)).clamp(0.0, 1.0);
        final scale = 0.75 + (0.35 * phase);
        final alpha = 0.25 + (0.75 * phase);
        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: 3.5),
          child: Transform.scale(
            scale: scale,
            child: Container(
              width: 5,
              height: 5,
              decoration: BoxDecoration(
                color: color.withValues(alpha: alpha),
                shape: BoxShape.circle,
              ),
            ),
          ),
        );
      }),
    );
  }
}

class _SplashMarkCircle extends StatelessWidget {
  final Widget child;

  const _SplashMarkCircle({required this.child});

  @override
  Widget build(BuildContext context) {
    return Container(
      key: const ValueKey('splash_circle'),
      width: 220,
      height: 220,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: context.sirati.surface,
        border: Border.all(
          color: context.sirati.primaryLight,
          width: 2,
        ),
        boxShadow: [
          BoxShadow(
            color: context.sirati.primary.withValues(alpha: 0.15),
            blurRadius: 32,
            spreadRadius: 2,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Center(child: child),
    );
  }
}
