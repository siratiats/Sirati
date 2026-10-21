import 'package:flutter/material.dart';

import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/motion.dart';

/// The single "create CV" action button used across the app. One shape, one
/// colour, one size everywhere so it reads as the same control on every screen.
class SiratiAddButton extends StatelessWidget {
  final VoidCallback onTap;
  final double size;

  const SiratiAddButton({super.key, required this.onTap, this.size = 52});

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    final label = english ? 'Create new CV' : 'إنشاء سيرة جديدة';

    return Tooltip(
      message: label,
      child: Semantics(
        button: true,
        label: label,
        child: PressScale(
          pressedScale: .94,
          child: Material(
            color: context.sirati.amberAccent,
            shape: const CircleBorder(),
            elevation: 6,
            shadowColor: context.sirati.amberAccent.withValues(alpha: .28),
            child: InkWell(
              onTap: onTap,
              customBorder: const CircleBorder(),
              child: SizedBox(
                width: size,
                height: size,
                child: Icon(Icons.add_rounded,
                    color: context.sirati.textPrimary, size: 30),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
