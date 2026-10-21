import 'package:flutter/material.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/theme/app_theme.dart';

/// Autosave state under the builder title (SIRATI-42). The icon carries the
/// status colour; the label stays in an AA text tone.
class SaveStatusBadge extends StatelessWidget {
  const SaveStatusBadge({
    super.key,
    required this.isSaving,
    required this.isDirty,
  });

  final bool isSaving;
  final bool isDirty;

  @override
  Widget build(BuildContext context) {
    final colors = context.sirati;
    final l10n = AppLocalizations.of(context);
    final style = Theme.of(context).textTheme.labelSmall?.copyWith(
          fontSize: 11,
          color: colors.textSecondary,
        );

    final (Widget icon, String label, Color? labelColor) = isSaving
        ? (
            SizedBox(
              width: 10,
              height: 10,
              child: CircularProgressIndicator(
                strokeWidth: 1.5,
                color: colors.primary,
              ),
            ),
            l10n.autosaving,
            null,
          )
        : isDirty
            ? (
                Icon(Icons.edit_note, size: 12, color: colors.warning),
                l10n.unsavedEdits,
                null,
              )
            : (
                Icon(Icons.check_circle, size: 12, color: colors.primary),
                l10n.saved,
                colors.primaryInk,
              );

    return Semantics(
      liveRegion: true,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          ExcludeSemantics(child: icon),
          const SizedBox(width: AppSpacing.xxs),
          Text(label, style: style?.copyWith(color: labelColor)),
        ],
      ),
    );
  }
}
