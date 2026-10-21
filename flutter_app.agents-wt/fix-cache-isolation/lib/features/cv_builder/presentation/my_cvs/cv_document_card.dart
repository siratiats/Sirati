import 'package:flutter/material.dart';
import 'package:sirati/core/utils/app_format.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/cv_builder/domain/cv_list.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/motion.dart';

/// "Updated 3 days ago", or the server label when there's no timestamp.
String cvUpdatedText(CvListItem item, {required bool english}) {
  final at = item.updatedAt;
  if (at != null) return AppFormat.relativeTime(at, english: english);
  if (item.updatedLabel.isEmpty) return '';
  return AppFormat.digits(item.updatedLabel, english: english);
}

/// One saved CV with edit / download / delete actions.
class CvDocumentCard extends StatelessWidget {
  const CvDocumentCard({
    super.key,
    required this.item,
    required this.onEdit,
    required this.onDelete,
    required this.onDownload,
  });

  final CvListItem item;
  final VoidCallback onEdit;
  final VoidCallback onDelete;
  final VoidCallback onDownload;

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    final colors = context.sirati;
    final updated = cvUpdatedText(item, english: english);
    final editable = item.id != null;

    return PressScale(
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: colors.surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: colors.border),
          boxShadow: colors.softShadow,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _DocumentIcon(
                  color: item.isDraft ? colors.textHint : colors.primary,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.title.isEmpty
                            ? (english ? 'Untitled CV' : 'سيرة بلا عنوان')
                            : LocaleFormat.mixedTitle(
                                LocaleFormat.fixSampleTitle(item.title),
                                english: english,
                              ),
                        textAlign: TextAlign.start,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: AppTextStyles.titleMd(colors),
                      ),
                      if (updated.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(
                          updated,
                          textAlign: TextAlign.start,
                          style: AppTextStyles.bodySm(colors).copyWith(
                            color: colors.textHint,
                            fontSize: 12,
                          ),
                        ),
                      ],
                      if (item.badge.isNotEmpty) ...[
                        const SizedBox(height: 8),
                        Align(
                          alignment: AlignmentDirectional.centerStart,
                          child: _StatusBadge(
                            label: LocaleFormat.atsBadge(
                              item.badge,
                              english: english,
                            ),
                            isDraft: item.isDraft,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: editable ? onEdit : null,
                    icon: const Icon(Icons.edit_outlined, size: 16),
                    label: Text(english ? 'Edit' : 'تعديل'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: colors.primaryLight,
                      // primaryInk / primaryLight is the gated chip pair.
                      foregroundColor: colors.primaryInk,
                      elevation: 0,
                      minimumSize: const Size(0, 40),
                      padding: const EdgeInsets.symmetric(
                          horizontal: 12, vertical: 10),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                      textStyle: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                _CircleActionButton(
                  icon: Icons.download_rounded,
                  label: english ? 'Download' : 'تنزيل',
                  iconColor:
                      item.canDownload ? colors.primary : colors.textHint,
                  borderColor:
                      item.canDownload ? colors.borderStrong : colors.border,
                  onTap: item.canDownload ? onDownload : null,
                ),
                const SizedBox(width: 8),
                _CircleActionButton(
                  icon: Icons.delete_outline_rounded,
                  label: english ? 'Delete' : 'حذف',
                  iconColor: colors.red,
                  borderColor: colors.red.withValues(alpha: .18),
                  fillColor: colors.redLight,
                  onTap: editable ? onDelete : null,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _DocumentIcon extends StatelessWidget {
  const _DocumentIcon({required this.color});

  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 48,
      height: 48,
      decoration: BoxDecoration(
        color: color.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(12),
      ),
      child: const Center(child: SiratiMark(size: 22)),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.label, required this.isDraft});

  final String label;
  final bool isDraft;

  @override
  Widget build(BuildContext context) {
    final colors = context.sirati;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: isDraft ? colors.surfaceLow : colors.primaryLight,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11.5,
          fontWeight: FontWeight.w800,
          color: isDraft ? colors.textSecondary : colors.primaryInk,
        ),
      ),
    );
  }
}

class _CircleActionButton extends StatelessWidget {
  const _CircleActionButton({
    required this.icon,
    required this.label,
    required this.iconColor,
    required this.borderColor,
    this.fillColor,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final Color iconColor;
  final Color borderColor;
  final Color? fillColor;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: label,
      child: Semantics(
        label: label,
        button: true,
        enabled: onTap != null,
        child: Material(
          color: fillColor ?? context.sirati.surface,
          shape: CircleBorder(side: BorderSide(color: borderColor, width: 1.5)),
          child: InkWell(
            onTap: onTap,
            customBorder: const CircleBorder(),
            child: SizedBox(
              width: 40,
              height: 40,
              child: Icon(icon, color: iconColor, size: 18),
            ),
          ),
        ),
      ),
    );
  }
}

/// Outlined "Create New CV" call to action below the list.
class CreateCvCard extends StatelessWidget {
  const CreateCvCard({super.key, required this.english, required this.onTap});

  final bool english;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = context.sirati;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 56),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          decoration: BoxDecoration(
            color: colors.surface,
            border: Border.all(
              color: colors.primary.withValues(alpha: .35),
              width: 1.5,
            ),
            borderRadius: BorderRadius.circular(16),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.add_rounded, size: 18, color: colors.primary),
              const SizedBox(width: 8),
              Flexible(
                child: Text(
                  english ? 'Create New CV' : 'إنشاء سيرة جديدة',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 14,
                    height: 1.35,
                    fontWeight: FontWeight.w700,
                    // `primary` is a fill colour; text on surface uses ink.
                    color: colors.primaryInk,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
