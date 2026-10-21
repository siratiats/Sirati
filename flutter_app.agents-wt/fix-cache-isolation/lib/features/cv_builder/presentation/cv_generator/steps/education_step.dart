import 'package:flutter/material.dart';
import 'package:sirati/features/cv_builder/presentation/cv_generator/wizard_widgets.dart';
import 'package:sirati/shared/widgets/ai_cv_field.dart';
import 'package:sirati/shared/widgets/form_fields.dart';

/// Step 4: education (required) and certifications (optional).
class EducationStep extends StatelessWidget {
  const EducationStep({super.key, required this.props});

  final WizardStepProps props;

  @override
  Widget build(BuildContext context) {
    final english = props.english;
    final f = props.fields;

    return WizardStepForm(
      props: props,
      title: english ? 'Education & Certifications' : 'التعليم والشهادات',
      icon: Icons.school_rounded,
      children: [
        FieldGroup(
          label: english ? 'Education *' : 'التعليم *',
          child: AiCvField(
            field: 'education',
            controller: f.education,
            english: english,
            isLoading: props.isEnhancing('education'),
            helperText: english
                ? 'Example: BSc Computer Science — King Saud University — 2020.'
                : 'مثال: بكالوريوس علوم الحاسب — جامعة الملك سعود — 2020.',
            result: props.fieldResults['education'],
            onEnhance: () => props.onEnhance('education'),
            onDismissResult: () => props.onDismissResult('education'),
            child: AppTextFormField(
              controller: f.education,
              textAlign: TextAlign.start,
              maxLines: 4,
              textInputAction: TextInputAction.next,
              onFieldSubmitted: (_) => FocusScope.of(context).nextFocus(),
              enabled: !props.isEnhancing('education'),
              hintText: english
                  ? 'BSc Computer Science, King Abdulaziz University, 2020'
                  : 'بكالوريوس علوم الحاسب، جامعة الملك عبدالعزيز، 2020',
              validator: (value) => (value?.trim().isEmpty ?? true)
                  ? (english ? 'Education is required.' : 'التعليم مطلوب.')
                  : null,
            ),
          ),
        ),
        const SizedBox(height: 18),
        FieldGroup(
          label: english
              ? 'Certifications & Courses (optional)'
              : 'الشهادات والدورات (اختياري)',
          child: AiCvField(
            field: 'certifications',
            controller: f.certifications,
            english: english,
            isLoading: props.isEnhancing('certifications'),
            helperText: english
                ? 'Example: AWS Certified Cloud Practitioner — Amazon Web Services — 2023.'
                : 'مثال: شهادة AWS Cloud Practitioner من Amazon عام 2023.',
            result: props.fieldResults['certifications'],
            onEnhance: () => props.onEnhance('certifications'),
            onDismissResult: () => props.onDismissResult('certifications'),
            child: AppTextFormField(
              controller: f.certifications,
              textAlign: TextAlign.start,
              maxLines: 4,
              textInputAction: TextInputAction.done,
              onFieldSubmitted: (_) => props.onPrimaryAction(),
              enabled: !props.isEnhancing('certifications'),
              hintText: english
                  ? 'Certification — issuer — year'
                  : 'اسم الشهادة — الجهة المانحة — السنة',
            ),
          ),
        ),
      ],
    );
  }
}
