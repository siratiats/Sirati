import 'package:flutter/material.dart';
import 'package:sirati/features/cv_builder/presentation/cv_generator/wizard_widgets.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/ai_cv_field.dart';
import 'package:sirati/shared/widgets/form_fields.dart';
import 'package:sirati/shared/widgets/loading/ai_field_loading_overlay.dart';
import 'package:sirati/shared/widgets/submit_button.dart';

/// Step 1: contact details, target role, optional job description, and the
/// CV output language.
class PersonalStep extends StatelessWidget {
  const PersonalStep({
    super.key,
    required this.props,
    required this.language,
    required this.onLanguageChanged,
    required this.isEnhancingJobDescription,
    required this.onEnhanceJobDescription,
  });

  final WizardStepProps props;
  final String language;
  final ValueChanged<String> onLanguageChanged;
  final bool isEnhancingJobDescription;
  final VoidCallback onEnhanceJobDescription;

  @override
  Widget build(BuildContext context) {
    final english = props.english;
    final f = props.fields;
    void next(String _) => FocusScope.of(context).nextFocus();

    return WizardStepForm(
      props: props,
      title: english ? 'Personal Information' : 'المعلومات الشخصية',
      icon: Icons.person_rounded,
      children: [
        FieldGroup(
          label: english ? 'Full Name *' : 'الاسم الكامل *',
          child: AppTextFormField(
            controller: f.name,
            textAlign: TextAlign.start,
            textCapitalization: TextCapitalization.words,
            textInputAction: TextInputAction.next,
            onFieldSubmitted: next,
            hintText: english ? 'Salem Sayer' : 'سالم سيار',
            prefixIcon: const Icon(Icons.person_outline),
            validator: (value) => (value?.trim().isEmpty ?? true)
                ? (english ? 'Full name is required.' : 'الاسم الكامل مطلوب.')
                : null,
          ),
        ),
        const SizedBox(height: 14),
        FieldGroup(
          label: english ? 'Email' : 'البريد الإلكتروني',
          child: AppTextFormField(
            controller: f.email,
            keyboardType: TextInputType.emailAddress,
            textInputAction: TextInputAction.next,
            onFieldSubmitted: next,
            textDirection: TextDirection.ltr,
            textAlign: TextAlign.start,
            hintText: 'salem@example.com',
            prefixIcon: const Icon(Icons.email_outlined),
            validator: (value) {
              final email = value?.trim() ?? '';
              if (email.isEmpty) {
                return english
                    ? 'Email is required so an employer can reply.'
                    : 'البريد الإلكتروني مطلوب حتى يتمكن صاحب العمل من الرد.';
              }
              if (!email.contains('@')) {
                return english
                    ? 'Enter a valid email address.'
                    : 'أدخل بريداً إلكترونياً صحيحاً.';
              }
              return null;
            },
          ),
        ),
        const SizedBox(height: 14),
        FieldGroup(
          label: english ? 'Phone' : 'رقم الهاتف',
          child: AppTextFormField(
            controller: f.phone,
            keyboardType: TextInputType.phone,
            textInputAction: TextInputAction.next,
            onFieldSubmitted: next,
            textDirection: TextDirection.ltr,
            textAlign: TextAlign.start,
            hintText: '+966 5X XXX XXXX',
            prefixIcon: const Icon(Icons.phone_outlined),
            validator: (value) => (value?.trim().isEmpty ?? true)
                ? (english
                    ? 'Phone is required so an employer can reply.'
                    : 'رقم الهاتف مطلوب حتى يتمكن صاحب العمل من الرد.')
                : null,
          ),
        ),
        const SizedBox(height: 14),
        FieldGroup(
          label: english ? 'LinkedIn URL' : 'رابط LinkedIn',
          child: AppTextFormField(
            controller: f.linkedin,
            keyboardType: TextInputType.url,
            textInputAction: TextInputAction.next,
            onFieldSubmitted: next,
            textDirection: TextDirection.ltr,
            textAlign: TextAlign.start,
            hintText: 'linkedin.com/in/username',
            prefixIcon: const Icon(Icons.link_rounded),
          ),
        ),
        const SizedBox(height: 14),
        FieldGroup(
          label: english ? 'Target Job Title *' : 'المسمى الوظيفي المستهدف *',
          child: AppTextFormField(
            controller: f.jobTitle,
            textAlign: TextAlign.start,
            textInputAction: TextInputAction.next,
            onFieldSubmitted: next,
            hintText:
                english ? 'Laravel Backend Developer' : 'مطوّر Laravel Backend',
            prefixIcon: const Icon(Icons.work_outline_rounded),
            validator: (value) => (value?.trim().isEmpty ?? true)
                ? (english
                    ? 'Target job title is required.'
                    : 'المسمى الوظيفي المستهدف مطلوب.')
                : null,
          ),
        ),
        const SizedBox(height: 14),
        FieldGroup(
          label: english
              ? 'Job Description (optional)'
              : 'الوصف الوظيفي (اختياري)',
          child: _JobDescriptionField(
            controller: f.jobDescription,
            english: english,
            isEnhancing: isEnhancingJobDescription,
            onEnhance: onEnhanceJobDescription,
            onDone: props.onPrimaryAction,
          ),
        ),
        const SizedBox(height: 18),
        FieldLabel(english ? 'CV Language' : 'لغة السيرة الذاتية'),
        const SizedBox(height: 8),
        _LanguagePicker(value: language, onChanged: onLanguageChanged),
      ],
    );
  }
}

class _JobDescriptionField extends StatelessWidget {
  const _JobDescriptionField({
    required this.controller,
    required this.english,
    required this.isEnhancing,
    required this.onEnhance,
    required this.onDone,
  });

  final TextEditingController controller;
  final bool english;
  final bool isEnhancing;
  final VoidCallback onEnhance;
  final VoidCallback onDone;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        AiFieldLoadingOverlay(
          isLoading: isEnhancing,
          statusMessages:
              AiFieldLoadingOverlay.defaultStatusMessages(english: english),
          semanticsLabel: english
              ? 'Enhancing job description'
              : 'جارٍ تحسين الوصف الوظيفي',
          child: AppTextFormField(
            controller: controller,
            textAlign: TextAlign.start,
            maxLines: 5,
            textInputAction: TextInputAction.done,
            onFieldSubmitted: (_) => onDone(),
            enabled: !isEnhancing,
            hintText: english
                ? 'Paste the job description or let Sirati complete it from the role...'
                : 'الصق الوصف الوظيفي أو دع سيرتي يكمله من المسمى...',
            prefixIcon: const Icon(Icons.assignment_outlined),
          ),
        ),
        const SizedBox(height: 10),
        // Rebuild only the button row as the user types, not the whole step.
        ValueListenableBuilder<TextEditingValue>(
          valueListenable: controller,
          builder: (context, value, _) {
            final enabled = !isEnhancing &&
                AiCvField.isSufficientForEnhancement(value.text);
            final showHint =
                value.text.trim().isNotEmpty && !enabled && !isEnhancing;
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SubmitButton(
                  label: english ? 'Enhance' : 'تحسين',
                  loadingLabel: english ? 'Enhancing...' : 'جارٍ التحسين...',
                  isLoading: isEnhancing,
                  outlined: true,
                  height: 44,
                  icon: Icons.auto_fix_high_rounded,
                  onPressed: enabled ? onEnhance : null,
                ),
                if (showHint) ...[
                  const SizedBox(height: 5),
                  Row(
                    children: [
                      Icon(
                        Icons.info_outline_rounded,
                        size: 13,
                        color: context.sirati.textHint,
                      ),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          english
                              ? 'Write more than a line for accurate AI enhancement'
                              : 'اكتب أكثر من سطر لتوجيه الذكاء الاصطناعي بدقة',
                          style: TextStyle(
                            color: context.sirati.textHint,
                            fontSize: 11,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            );
          },
        ),
      ],
    );
  }
}

class _LanguagePicker extends StatelessWidget {
  const _LanguagePicker({required this.value, required this.onChanged});

  final String value;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: context.sirati.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: context.sirati.border),
      ),
      child: Row(
        children: [
          for (final (code, label) in const [
            ('en', 'English'),
            ('ar', 'العربية')
          ])
            Expanded(
              child: _LanguageOption(
                label: label,
                selected: value == code,
                onTap: () => onChanged(code),
              ),
            ),
        ],
      ),
    );
  }
}

class _LanguageOption extends StatelessWidget {
  const _LanguageOption({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      selected: selected,
      child: GestureDetector(
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: BoxDecoration(
            color: selected ? context.sirati.primary : null,
            borderRadius: BorderRadius.circular(11),
          ),
          alignment: Alignment.center,
          child: Text(
            label,
            style: TextStyle(
              fontSize: 14,
              fontWeight: selected ? FontWeight.w700 : FontWeight.w400,
              color: selected
                  ? context.sirati.onPrimary
                  : context.sirati.textSecondary,
            ),
          ),
        ),
      ),
    );
  }
}
