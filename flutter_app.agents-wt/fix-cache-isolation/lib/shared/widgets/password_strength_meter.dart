import 'package:flutter/material.dart';

import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/motion.dart';

enum PasswordStrength { empty, weak, medium, strong }

/// Compact 3-segment password strength meter (no external packages).
///
/// Rules: length ≥ 8, has letter, has digit, has symbol.
/// 0–1 rules → weak, 2–3 → medium, 4 → strong.
class PasswordStrengthMeter extends StatelessWidget {
  final String password;
  final bool english;

  const PasswordStrengthMeter({
    super.key,
    required this.password,
    required this.english,
  });

  static PasswordStrength evaluate(String password) {
    if (password.isEmpty) return PasswordStrength.empty;

    var score = 0;
    if (password.length >= 8) score++;
    if (RegExp(r'[A-Za-z\u0600-\u06FF]').hasMatch(password)) score++;
    if (RegExp(r'[0-9]').hasMatch(password)) score++;
    if (RegExp(r'[^A-Za-z0-9\u0600-\u06FF]').hasMatch(password)) score++;

    if (score <= 1) return PasswordStrength.weak;
    if (score <= 3) return PasswordStrength.medium;
    return PasswordStrength.strong;
  }

  @override
  Widget build(BuildContext context) {
    final level = evaluate(password);
    final reduce = MotionSettings.reduce(context);
    final activeCount = switch (level) {
      PasswordStrength.empty => 0,
      PasswordStrength.weak => 1,
      PasswordStrength.medium => 2,
      PasswordStrength.strong => 3,
    };
    final color = switch (level) {
      PasswordStrength.empty => context.sirati.border,
      PasswordStrength.weak => context.sirati.error,
      PasswordStrength.medium => context.sirati.warning,
      PasswordStrength.strong => context.sirati.success,
    };
    // The empty track color is intentionally subtle, but using it for text
    // fails contrast on the app's light surfaces. Keep the label readable.
    final labelColor =
        level == PasswordStrength.empty ? context.sirati.textSecondary : color;
    final label = switch (level) {
      PasswordStrength.empty =>
        english ? 'Enter a password' : 'أدخل كلمة المرور',
      PasswordStrength.weak => english ? 'Weak' : 'ضعيفة',
      PasswordStrength.medium => english ? 'Medium' : 'متوسطة',
      PasswordStrength.strong => english ? 'Strong' : 'قوية',
    };

    return Semantics(
      liveRegion: true,
      label: english ? 'Password strength: $label' : 'قوة كلمة المرور: $label',
      child: ExcludeSemantics(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: List.generate(3, (i) {
                final filled = i < activeCount;
                return Expanded(
                  child: Padding(
                    padding: EdgeInsetsDirectional.only(
                      end: i < 2 ? AppSpacing.xxs : 0,
                    ),
                    child: AnimatedContainer(
                      duration: reduce ? Duration.zero : MotionDurations.fast,
                      curve: MotionCurves.state,
                      height: 4,
                      decoration: BoxDecoration(
                        color: filled ? color : context.sirati.border,
                        borderRadius: BorderRadius.circular(999),
                      ),
                    ),
                  ),
                );
              }),
            ),
            const SizedBox(height: AppSpacing.xxs),
            Text(
              label,
              textAlign: TextAlign.start,
              style: AppTextStyles.labelMd(context.sirati)
                  .copyWith(color: labelColor),
            ),
          ],
        ),
      ),
    );
  }
}
