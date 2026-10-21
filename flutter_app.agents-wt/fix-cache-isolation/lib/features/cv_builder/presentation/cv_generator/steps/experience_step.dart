import 'package:flutter/material.dart';
import 'package:sirati/features/cv_builder/domain/cv_form_data.dart';
import 'package:sirati/features/cv_builder/presentation/cv_generator/wizard_widgets.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/ai_cv_field.dart';
import 'package:sirati/shared/widgets/form_fields.dart';

/// Step 3: work experience, with the XYZ achievement builder and an
/// optional "target shape" hint shown the first time the field is focused.
class ExperienceStep extends StatelessWidget {
  const ExperienceStep({
    super.key,
    required this.props,
    required this.focusNode,
    required this.showShapeHint,
    required this.onDismissShapeHint,
    required this.onOpenAchievementBuilder,
  });

  final WizardStepProps props;
  final FocusNode focusNode;
  final bool showShapeHint;
  final VoidCallback onDismissShapeHint;
  final VoidCallback onOpenAchievementBuilder;

  @override
  Widget build(BuildContext context) {
    final english = props.english;
    final c = context.sirati;
    final enhancing = props.isEnhancing('experience');

    return WizardStepForm(
      props: props,
      title: english ? 'Work Experience' : 'الخبرات العملية',
      icon: Icons.work_rounded,
      children: [
        FieldGroup(
          label: english ? 'Experience *' : 'الخبرة العملية *',
          child: AiCvField(
            field: 'experience',
            controller: props.fields.experience,
            english: english,
            isLoading: enhancing,
            minimumCharacters: CvFormData.minExperienceChars,
            leadingHint: showShapeHint
                ? _ShapeHint(english: english, onDismiss: onDismissShapeHint)
                : null,
            helperText: english
                ? 'Example: Backend Developer — Company — 2022–present; improved API response time by 35%.'
                : 'مثال: مطور Backend — الشركة — 2022 حتى الآن؛ حسّنت سرعة API بنسبة 35%.',
            result: props.fieldResults['experience'],
            onEnhance: () => props.onEnhance('experience'),
            onDismissResult: () => props.onDismissResult('experience'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  margin: const EdgeInsets.only(bottom: 10),
                  child: OutlinedButton.icon(
                    key: const Key('open_achievement_builder_btn'),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 14, vertical: 11),
                      foregroundColor: c.primaryDark,
                      side: BorderSide(color: c.primary.withValues(alpha: .35)),
                      backgroundColor: c.primaryLight.withValues(alpha: .5),
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10)),
                    ),
                    onPressed: enhancing ? null : onOpenAchievementBuilder,
                    icon: const Icon(Icons.auto_awesome_rounded, size: 18),
                    label: Text(
                      english
                          ? 'XYZ Achievement Builder'
                          : 'صانع الإنجازات المقاسة (معادلة XYZ)',
                      style: const TextStyle(
                          fontWeight: FontWeight.w700, fontSize: 13),
                    ),
                  ),
                ),
                AppTextFormField(
                  controller: props.fields.experience,
                  focusNode: focusNode,
                  textAlign: TextAlign.start,
                  maxLines: 10,
                  textInputAction: TextInputAction.done,
                  onFieldSubmitted: (_) => props.onPrimaryAction(),
                  enabled: !enhancing,
                  hintText: english
                      ? 'Role, company, period\n- Achievement with a measurable result'
                      : 'المسمى، الشركة، الفترة\n- إنجاز بنتيجة قابلة للقياس',
                  validator: (value) {
                    if ((value?.trim().length ?? 0) <
                        CvFormData.minExperienceChars) {
                      return english
                          ? 'Write at least ${CvFormData.minExperienceChars} characters about your experience.'
                          : 'اكتب الخبرات العملية بتفاصيل لا تقل عن ${CvFormData.minExperienceChars} حرفاً.';
                    }
                    return null;
                  },
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _ShapeHint extends StatelessWidget {
  const _ShapeHint({required this.english, required this.onDismiss});

  final bool english;
  final VoidCallback onDismiss;

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    return Container(
      key: const Key('experience_shape_hint'),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: c.infoLight,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.lightbulb_outline_rounded, size: 19, color: c.info),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              english
                  ? 'Target shape: role — company — period, then one or two achievements with numbers.'
                  : 'الشكل المستهدف: المسمى — الشركة — الفترة، ثم إنجاز أو اثنان بأرقام.',
              style:
                  TextStyle(color: c.textPrimary, fontSize: 12.5, height: 1.45),
            ),
          ),
          IconButton(
            tooltip: english ? 'Dismiss' : 'إخفاء',
            visualDensity: VisualDensity.compact,
            onPressed: onDismiss,
            icon: const Icon(Icons.close_rounded, size: 18),
          ),
        ],
      ),
    );
  }
}
