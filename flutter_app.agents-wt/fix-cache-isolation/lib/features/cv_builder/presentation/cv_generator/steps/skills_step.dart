import 'package:flutter/material.dart';
import 'package:sirati/features/cv_builder/presentation/cv_generator/wizard_widgets.dart';
import 'package:sirati/features/cv_builder/presentation/widgets/skill_chips_selector.dart';
import 'package:sirati/shared/models/job_title.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/ai_cv_field.dart';
import 'package:sirati/shared/widgets/form_fields.dart';

/// Step 2: core skills (chip picker) and professional summary.
class SkillsStep extends StatelessWidget {
  const SkillsStep({super.key, required this.props, required this.jobTitles});

  final WizardStepProps props;
  final List<JobTitle> jobTitles;

  @override
  Widget build(BuildContext context) {
    final english = props.english;
    final f = props.fields;

    return WizardStepForm(
      props: props,
      title: english ? 'Skills & Summary' : 'المهارات والملخص',
      icon: Icons.psychology_rounded,
      children: [
        FieldGroup(
          label: english ? 'Core Skills *' : 'المهارات الأساسية *',
          // One editor for skills: the chips own the text the AI enhances,
          // so a second raw field would only show the same list twice.
          child: AiCvField(
            field: 'skills',
            controller: f.skills,
            english: english,
            isLoading: props.isEnhancing('skills'),
            helperText: english
                ? 'Tap a suggestion or type a skill, then press Add.'
                : 'اختر من المقترحات أو اكتب مهارة واضغط إضافة.',
            result: props.fieldResults['skills'],
            onEnhance: () => props.onEnhance('skills'),
            onDismissResult: () => props.onDismissResult('skills'),
            child: FormField<String>(
              validator: (_) => f.skills.text.trim().isEmpty
                  ? (english
                      ? 'Core skills are required.'
                      : 'المهارات الأساسية مطلوبة.')
                  : null,
              builder: (state) => Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SkillChipsSelector(
                    controller: f.skills,
                    targetJobTitle: f.jobTitle.text,
                    english: english,
                    allJobTitles: jobTitles,
                    enabled: !props.isEnhancing('skills'),
                    onChanged: () => state.didChange(f.skills.text),
                  ),
                  if (state.hasError) ...[
                    const SizedBox(height: 6),
                    Text(
                      state.errorText!,
                      style: AppFormStyles.errorTextStyle(context.sirati),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ),
        const SizedBox(height: 18),
        FieldGroup(
          label: english
              ? 'Professional Summary (optional)'
              : 'الملخص المهني (اختياري)',
          child: AiCvField(
            field: 'summary',
            controller: f.summary,
            english: english,
            isLoading: props.isEnhancing('summary'),
            helperText: english
                ? 'Example: Backend developer focused on reliable APIs and measurable product outcomes.'
                : 'مثال: مطور Backend متخصص في بناء APIs موثوقة وتحقيق نتائج قابلة للقياس.',
            result: props.fieldResults['summary'],
            onEnhance: () => props.onEnhance('summary'),
            onDismissResult: () => props.onDismissResult('summary'),
            child: AppTextFormField(
              controller: f.summary,
              textAlign: TextAlign.start,
              maxLines: 4,
              textInputAction: TextInputAction.done,
              onFieldSubmitted: (_) => props.onPrimaryAction(),
              enabled: !props.isEnhancing('summary'),
              hintText: english
                  ? 'Briefly describe your experience and achievements...'
                  : 'نبذة مختصرة عن خبرتك وإنجازاتك...',
            ),
          ),
        ),
      ],
    );
  }
}
