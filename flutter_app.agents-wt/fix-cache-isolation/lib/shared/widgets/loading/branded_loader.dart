import 'package:flutter/material.dart';

import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/motion.dart';

/// Branded loader for the splash / bootstrap screen.
///
/// Uses the [SiratiMark] with a subtle pulse + halo animation so cold-start
/// feels intentional and branded, not like a generic Material spinner.
/// Falls back to a static logo when reduced motion is requested.
class BrandedLoader extends StatefulWidget {
  final double size;
  final bool halo;

  const BrandedLoader({super.key, this.size = 72, this.halo = true});

  @override
  State<BrandedLoader> createState() => _BrandedLoaderState();
}

class _BrandedLoaderState extends State<BrandedLoader>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    // Slightly longer than skeleton so the splash pulse feels calmer.
    _controller = AnimationController(
      vsync: this,
      duration: MotionDurations.skeleton + const Duration(milliseconds: 300),
    )..repeat(reverse: true);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (MotionSettings.reduce(context)) {
      return SiratiMark(size: widget.size, elevated: true);
    }

    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        final t = Curves.easeInOut.transform(_controller.value);
        final scale = 1 + t * 0.06;
        final haloScale = 1.15 + t * 0.35;
        final haloOpacity = (0.35 - t * 0.35).clamp(0.0, 1.0);

        return SizedBox(
          width: widget.size * 1.8,
          height: widget.size * 1.8,
          child: Stack(
            alignment: Alignment.center,
            children: [
              if (widget.halo)
                Opacity(
                  opacity: haloOpacity,
                  child: Container(
                    width: widget.size * haloScale,
                    height: widget.size * haloScale,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: context.sirati.primary.withValues(alpha: .25),
                    ),
                  ),
                ),
              Transform.scale(
                scale: scale,
                child: child,
              ),
            ],
          ),
        );
      },
      child: SiratiMark(size: widget.size, elevated: true),
    );
  }
}
