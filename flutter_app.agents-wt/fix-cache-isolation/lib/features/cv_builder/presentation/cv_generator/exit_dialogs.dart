import 'package:flutter/material.dart';
import 'package:sirati/shared/theme/app_theme.dart';

enum WizardExitAction { cancel, discard, saveDraft }

/// Edit mode: confirm throwing away unsaved edits. Returns true to discard.
Future<bool> confirmDiscardEdits(BuildContext context,
    {required bool english}) {
  final c = context.sirati;
  return showDialog<bool>(
    context: context,
    builder: (ctx) => AlertDialog(
      backgroundColor: c.surface,
      title: Text(
        english ? 'Discard changes?' : 'تجاهل التعديلات؟',
        style: TextStyle(color: c.textPrimary),
      ),
      content: Text(
        english
            ? 'Your unsaved edits will be lost.'
            : 'ستُفقد تعديلاتك غير المحفوظة.',
        style: TextStyle(color: c.textSecondary),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(ctx, false),
          child: Text(english ? 'Cancel' : 'إلغاء'),
        ),
        TextButton(
          onPressed: () => Navigator.pop(ctx, true),
          style: TextButton.styleFrom(foregroundColor: c.red),
          child: Text(english ? 'Discard' : 'تجاهل'),
        ),
      ],
    ),
  ).then((value) => value ?? false);
}

/// Create mode: save a local draft, discard, or stay.
Future<WizardExitAction> askSaveDraft(
  BuildContext context, {
  required bool english,
}) {
  final c = context.sirati;
  return showDialog<WizardExitAction>(
    context: context,
    builder: (ctx) => AlertDialog(
      backgroundColor: c.surface,
      title: Text(
        english ? 'Save your progress?' : 'حفظ تقدمك؟',
        style: TextStyle(color: c.textPrimary),
      ),
      content: Text(
        english
            ? 'A draft will be kept on this device so you can continue later.'
            : 'سيتم الاحتفاظ بمسودة على هذا الجهاز لتتمكن من المتابعة لاحقاً.',
        style: TextStyle(color: c.textSecondary),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(ctx, WizardExitAction.cancel),
          child: Text(english ? 'Cancel' : 'إلغاء'),
        ),
        TextButton(
          onPressed: () => Navigator.pop(ctx, WizardExitAction.discard),
          style: TextButton.styleFrom(foregroundColor: c.red),
          child: Text(english ? 'Discard' : 'تجاهل'),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(ctx, WizardExitAction.saveDraft),
          child: Text(english ? 'Save draft' : 'حفظ مسودة'),
        ),
      ],
    ),
  ).then((value) => value ?? WizardExitAction.cancel);
}
