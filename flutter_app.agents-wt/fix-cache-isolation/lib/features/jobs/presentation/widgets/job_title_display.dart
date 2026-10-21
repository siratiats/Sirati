import 'package:flutter/material.dart';
import 'package:sirati/core/utils/parsed_job_title.dart';
import 'package:sirati/shared/theme/app_theme.dart';

/// Renders bilingual job titles with guaranteed fixed order:
/// Arabic primary title on top, English secondary title immediately below (with TextDirection.ltr).
class JobTitleDisplay extends StatelessWidget {
  final String title;
  final TextStyle? primaryStyle;
  final TextStyle? secondaryStyle;
  final Widget? trailingBadge;
  final int? primaryMaxLines;
  final int? secondaryMaxLines;

  const JobTitleDisplay({
    super.key,
    required this.title,
    this.primaryStyle,
    this.secondaryStyle,
    this.trailingBadge,
    this.primaryMaxLines = 2,
    this.secondaryMaxLines = 2,
  });

  @override
  Widget build(BuildContext context) {
    final parsed = ParsedJobTitle.parse(title);
    final primary = parsed.primaryTitle ?? title;
    final secondary = parsed.secondaryTitle;

    final isPrimaryAr = ParsedJobTitle.hasArabic(primary);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Flexible(
              child: Text(
                primary,
                textAlign: TextAlign.start,
                textDirection:
                    isPrimaryAr ? TextDirection.rtl : TextDirection.ltr,
                maxLines: primaryMaxLines,
                overflow:
                    primaryMaxLines != null ? TextOverflow.ellipsis : null,
                style: primaryStyle ?? AppTextStyles.titleMd(context.sirati),
              ),
            ),
            if (trailingBadge != null) ...[
              const SizedBox(width: 8),
              trailingBadge!,
            ],
          ],
        ),
        if (secondary != null && secondary.isNotEmpty) ...[
          const SizedBox(height: 3),
          Text(
            secondary,
            textAlign: TextAlign.start,
            textDirection: TextDirection.ltr,
            maxLines: secondaryMaxLines,
            overflow: secondaryMaxLines != null ? TextOverflow.ellipsis : null,
            style: secondaryStyle ??
                TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w600,
                  color: context.sirati.textSecondary,
                ),
          ),
        ],
      ],
    );
  }
}
