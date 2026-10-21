import 'package:flutter/material.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/shared/theme/app_theme.dart';

/// Card title used by every section of the analysis form.
class AnalysisSectionTitle extends StatelessWidget {
  const AnalysisSectionTitle(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) => Text(
        text,
        textAlign: TextAlign.start,
        style: AppTextStyles.titleSm(context.sirati),
      );
}

/// Collapsible "Job description (optional)" header with a one-line hint
/// while collapsed. Collapsed by default so the Analyze button stays above
/// the fold on small phones (SIRATI-94).
class CollapsibleSection extends StatelessWidget {
  const CollapsibleSection({
    super.key,
    required this.title,
    required this.collapsedHint,
    required this.expanded,
    required this.onToggle,
    required this.child,
  });

  final String title;
  final String collapsedHint;
  final bool expanded;
  final VoidCallback onToggle;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final colors = context.sirati;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Semantics(
          button: true,
          expanded: expanded,
          child: InkWell(
            onTap: onToggle,
            borderRadius: BorderRadius.circular(8),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Row(
                children: [
                  Expanded(child: AnalysisSectionTitle(title)),
                  AnimatedRotation(
                    turns: expanded ? 0.5 : 0,
                    duration: const Duration(milliseconds: 180),
                    child: Icon(
                      Icons.keyboard_arrow_down_rounded,
                      color: colors.textSecondary,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
        AnimatedCrossFade(
          duration: const Duration(milliseconds: 200),
          crossFadeState:
              expanded ? CrossFadeState.showSecond : CrossFadeState.showFirst,
          firstChild: Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text(
              collapsedHint,
              textAlign: TextAlign.start,
              style: TextStyle(fontSize: 12, color: colors.textSecondary),
            ),
          ),
          secondChild: child,
        ),
      ],
    );
  }
}

/// Tap target for choosing a resume file.
class UploadDropZone extends StatelessWidget {
  const UploadDropZone({super.key, required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    final colors = context.sirati;
    final radius = BorderRadius.circular(14);
    return Semantics(
      button: true,
      child: Material(
        color: colors.primaryLight,
        shape: RoundedRectangleBorder(
          borderRadius: radius,
          side: BorderSide(color: colors.primaryMid, width: 1.5),
        ),
        child: InkWell(
          onTap: onTap,
          borderRadius: radius,
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 28, horizontal: 12),
            child: Column(
              children: [
                ExcludeSemantics(
                  child: Container(
                    width: 52,
                    height: 52,
                    decoration: BoxDecoration(
                      color: colors.surface,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      Icons.cloud_upload_outlined,
                      size: 28,
                      color: colors.primary,
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  english
                      ? 'Tap to upload PDF or TXT'
                      : 'اضغط لرفع ملف PDF أو TXT',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    // primaryInk / primaryLight is the gated chip pair.
                    color: colors.primaryInk,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  english ? 'Maximum size 5 MB' : 'الحد الأقصى 5 ميجابايت',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 12, color: colors.primaryInk),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// The chosen file, with a control to remove it.
class SelectedFileTile extends StatelessWidget {
  const SelectedFileTile({
    super.key,
    required this.fileName,
    required this.onRemove,
  });

  final String fileName;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    final colors = context.sirati;
    return Container(
      padding: const EdgeInsetsDirectional.only(start: 14, top: 4, bottom: 4),
      decoration: BoxDecoration(
        color: colors.successLight,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: colors.success.withValues(alpha: .4)),
      ),
      child: Row(
        children: [
          Icon(
            Icons.insert_drive_file_outlined,
            color: colors.onSuccessContainer,
            size: 20,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              BidiText.isolateLtr(fileName),
              textAlign: TextAlign.start,
              textDirection: TextDirection.ltr,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: colors.onSuccessContainer,
              ),
            ),
          ),
          IconButton(
            tooltip: english ? 'Remove file' : 'إزالة الملف',
            icon: Icon(Icons.close, size: 18, color: colors.error),
            onPressed: onRemove,
          ),
        ],
      ),
    );
  }
}

/// "OR" separator between the upload and paste options.
class OrDivider extends StatelessWidget {
  const OrDivider({super.key});

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    final colors = context.sirati;
    return Row(
      children: [
        Expanded(child: Divider(color: colors.border)),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
            decoration: BoxDecoration(
              color: colors.background,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: colors.border),
            ),
            child: Text(
              english ? 'OR' : 'أو',
              style: TextStyle(fontSize: 12, color: colors.textSecondary),
            ),
          ),
        ),
        Expanded(child: Divider(color: colors.border)),
      ],
    );
  }
}
