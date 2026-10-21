import 'package:flutter/material.dart';
import 'package:sirati/features/cv_builder/presentation/cv_generator/cv_form_controllers.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/form_fields.dart';

/// Everything a wizard step needs from the screen, bundled so each step's
/// constructor stays small and steps never reach into screen state.
class WizardStepProps {
  const WizardStepProps({
    required this.english,
    required this.formKey,
    required this.submitted,
    required this.showErrorBanner,
    required this.onDismissErrorBanner,
    required this.fields,
    required this.enhancingField,
    required this.fieldResults,
    required this.onEnhance,
    required this.onDismissResult,
    required this.onPrimaryAction,
  });

  final bool english;
  final GlobalKey<FormState> formKey;

  /// True once the user tried to leave this step; turns on live validation.
  final bool submitted;
  final bool showErrorBanner;
  final VoidCallback onDismissErrorBanner;
  final CvFormControllers fields;

  /// Field currently being AI-enhanced, or null.
  final String? enhancingField;
  final Map<String, Map<String, dynamic>> fieldResults;
  final void Function(String field) onEnhance;
  final void Function(String field) onDismissResult;

  /// Next / Generate — also fired by keyboard Done on a step's last field.
  final VoidCallback onPrimaryAction;

  AutovalidateMode get autovalidateMode => submitted
      ? AutovalidateMode.onUserInteraction
      : AutovalidateMode.disabled;

  bool isEnhancing(String field) => enhancingField == field;
}

/// Form wrapper shared by all steps: heading, error banner, then fields.
class WizardStepForm extends StatelessWidget {
  const WizardStepForm({
    super.key,
    required this.props,
    required this.title,
    required this.icon,
    required this.children,
  });

  final WizardStepProps props;
  final String title;
  final IconData icon;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Form(
      key: props.formKey,
      autovalidateMode: props.autovalidateMode,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SectionHeading(text: title, icon: icon),
          const SizedBox(height: 18),
          if (props.showErrorBanner)
            AppFormErrorBanner(
              message: props.english
                  ? 'Please review the highlighted fields before continuing.'
                  : 'يرجى مراجعة الحقول المميزة قبل المتابعة.',
              onDismiss: props.onDismissErrorBanner,
            ),
          ...children,
        ],
      ),
    );
  }
}

class SectionHeading extends StatelessWidget {
  const SectionHeading({super.key, required this.text, required this.icon});

  final String text;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    return Row(
      children: [
        Container(
          padding: const EdgeInsets.all(7),
          decoration: BoxDecoration(
            color: c.primaryLight,
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: c.primary.withValues(alpha: .2)),
          ),
          child: Icon(icon, size: 18, color: c.primaryDark),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            text,
            textAlign: TextAlign.start,
            style: TextStyle(
              fontSize: 17,
              fontWeight: FontWeight.w800,
              color: c.primaryDark,
            ),
          ),
        ),
      ],
    );
  }
}

/// Label above a field.
class FieldLabel extends StatelessWidget {
  const FieldLabel(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: Text(
        text,
        textAlign: TextAlign.start,
        style: TextStyle(
          fontSize: 13,
          fontWeight: FontWeight.w600,
          color: context.sirati.textSecondary,
        ),
      ),
    );
  }
}

/// Label + field, the unit every step is built from.
class FieldGroup extends StatelessWidget {
  const FieldGroup({super.key, required this.label, required this.child});

  final String label;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        FieldLabel(label),
        const SizedBox(height: 6),
        child,
      ],
    );
  }
}
