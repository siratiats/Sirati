import 'package:flutter/material.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/motion.dart';

/// Step titles in wizard order.
List<String> wizardStepTitles({required bool english}) => english
    ? const ['Personal', 'Skills', 'Experience', 'Education']
    : const ['الشخصية', 'المهارات', 'الخبرات', 'التعليم'];

const wizardStepIcons = [
  Icons.person_rounded,
  Icons.psychology_rounded,
  Icons.work_rounded,
  Icons.school_rounded,
];

/// "Step X of N", progress bar, and the tappable step dots. Only completed
/// steps (and the current one) can be jumped to.
class WizardHeader extends StatelessWidget {
  const WizardHeader({
    super.key,
    required this.step,
    required this.english,
    required this.onStepTap,
  });

  final int step;
  final bool english;
  final ValueChanged<int> onStepTap;

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    final steps = wizardStepTitles(english: english);
    final progress = (step + 1) / steps.length;

    return Container(
      color: c.background,
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 14),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                english
                    ? 'Step ${step + 1} of ${steps.length}'
                    : 'خطوة ${step + 1} من ${steps.length}',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  // primaryInk: primary is a fill colour and fails AA as text.
                  color: c.primaryInk,
                ),
              ),
              Text(
                steps[step],
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: c.textSecondary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          WizardProgressBar(progress: progress),
          const SizedBox(height: 14),
          Row(
            children: [
              for (var i = 0; i < steps.length * 2 - 1; i++)
                if (i.isOdd)
                  Expanded(
                    child: Container(
                      height: 2,
                      color: i ~/ 2 < step ? c.primaryContainer : c.surfaceHigh,
                    ),
                  )
                else
                  _StepDot(
                    index: i ~/ 2,
                    step: step,
                    onTap: onStepTap,
                  ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              for (var i = 0; i < steps.length; i++)
                Text(
                  steps[i],
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w600,
                    color: i <= step ? c.primaryInk : c.textHint,
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _StepDot extends StatelessWidget {
  const _StepDot({
    required this.index,
    required this.step,
    required this.onTap,
  });

  final int index;
  final int step;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    final done = index < step;
    final active = index == step;
    final canJump = index <= step;

    return PressScale(
      enabled: canJump,
      child: GestureDetector(
        onTap: canJump && !active ? () => onTap(index) : null,
        child: AnimatedContainer(
          duration: MotionSettings.reduce(context)
              ? Duration.zero
              : MotionDurations.medium,
          curve: MotionCurves.state,
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            color: done
                ? c.primaryContainer
                : active
                    ? c.amberAccent
                    : c.surfaceHigh,
            shape: BoxShape.circle,
          ),
          child: Center(
            child: done
                ? Icon(Icons.check_rounded, size: 16, color: c.onPrimary)
                : Icon(
                    wizardStepIcons[index],
                    size: 16,
                    color: active ? c.primaryDark : c.textHint,
                  ),
          ),
        ),
      ),
    );
  }
}

/// Thin progress fill that grows from the start edge (left in LTR, right in
/// RTL) and animates between steps.
class WizardProgressBar extends StatefulWidget {
  const WizardProgressBar({super.key, required this.progress});

  final double progress;

  @override
  State<WizardProgressBar> createState() => _WizardProgressBarState();
}

class _WizardProgressBarState extends State<WizardProgressBar> {
  /// Stable tween so rebuilds do not restart; begin tracks last settled value.
  late Tween<double> _tween =
      Tween<double>(begin: 0, end: widget.progress.clamp(0.0, 1.0));

  @override
  void didUpdateWidget(covariant WizardProgressBar oldWidget) {
    super.didUpdateWidget(oldWidget);
    final next = widget.progress.clamp(0.0, 1.0);
    final prev = oldWidget.progress.clamp(0.0, 1.0);
    if (next != prev) {
      _tween = Tween<double>(begin: prev, end: next);
    }
  }

  @override
  Widget build(BuildContext context) {
    final target = widget.progress.clamp(0.0, 1.0);
    final reduce = MotionSettings.reduce(context);

    Widget fill(double factor) {
      return Align(
        alignment: AlignmentDirectional.centerStart,
        child: FractionallySizedBox(
          widthFactor: factor,
          heightFactor: 1,
          child: ColoredBox(color: context.sirati.primary),
        ),
      );
    }

    return ClipRRect(
      borderRadius: BorderRadius.circular(999),
      child: SizedBox(
        height: 4,
        width: double.infinity,
        child: ColoredBox(
          color: context.sirati.border,
          child: reduce
              ? fill(target)
              : TweenAnimationBuilder<double>(
                  // Key forces a new controller when the target step changes.
                  key: ValueKey(target),
                  tween: _tween,
                  duration: MotionDurations.slow,
                  curve: MotionCurves.state,
                  builder: (context, value, _) => fill(value),
                ),
        ),
      ),
    );
  }
}
