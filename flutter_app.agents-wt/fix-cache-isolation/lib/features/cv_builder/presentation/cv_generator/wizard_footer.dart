import 'package:flutter/material.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/directional_icon.dart';
import 'package:sirati/shared/widgets/motion.dart';
import 'package:sirati/shared/widgets/submit_button.dart';

/// Fixed Back / Next-or-Generate bar, outside the scroll view so it stays put
/// across step transitions and above the keyboard.
class WizardFooter extends StatelessWidget {
  const WizardFooter({
    super.key,
    required this.english,
    required this.step,
    required this.stepCount,
    required this.isEditMode,
    required this.isLoading,
    required this.onBack,
    required this.onPrimary,
  });

  final bool english;
  final int step;
  final int stepCount;
  final bool isEditMode;
  final bool isLoading;
  final VoidCallback onBack;
  final VoidCallback onPrimary;

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    final isLast = step == stepCount - 1;

    return SafeArea(
      top: false,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
        decoration: BoxDecoration(
          color: c.surface,
          border: Border(top: BorderSide(color: c.border)),
          boxShadow: AppShadows.above(c, elevation: 5),
        ),
        child: Row(
          children: [
            if (step > 0) ...[
              Expanded(
                child: PressScale(
                  child: OutlinedButton.icon(
                    onPressed: onBack,
                    icon: const DirectionalIcon(
                      Icons.arrow_back_rounded,
                      size: 18,
                    ),
                    label: Text(english ? 'Back' : 'السابق'),
                  ),
                ),
              ),
              const SizedBox(width: 12),
            ],
            Expanded(
              flex: 2,
              child: SubmitButton(
                label: isLast
                    ? (isEditMode
                        ? (english ? 'Update CV' : 'تحديث السيرة')
                        : (english ? 'Generate CV' : 'توليد السيرة'))
                    : (english ? 'Next' : 'التالي'),
                loadingLabel: english ? 'Generating...' : 'جارٍ التوليد...',
                isLoading: isLoading,
                icon: isLast ? Icons.auto_awesome : Icons.arrow_forward_rounded,
                onPressed: onPrimary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
