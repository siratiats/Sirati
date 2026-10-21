import 'package:flutter/material.dart';
import 'package:sirati/features/jobs/domain/job_news_feed.dart';
import 'package:sirati/features/jobs/presentation/widgets/job_news_format.dart';
import 'package:sirati/features/jobs/presentation/widgets/job_title_display.dart';
import 'package:sirati/shared/models/job_news.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/motion.dart';

class JobNewsCard extends StatelessWidget {
  final JobNews item;
  final bool english;
  final bool isNew;
  final VoidCallback onTap;

  const JobNewsCard({
    super.key,
    required this.item,
    required this.english,
    required this.isNew,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final meta = [item.company ?? '', item.location ?? '']
        .where((value) => value.isNotEmpty)
        .join(' · ');

    final hasMatch = item.matchScore != null;
    final highMatch = isHighMatch(item.matchScore);

    return PressScale(
      child: Material(
        color: context.sirati.surface,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(16),
          child: Container(
            padding: const EdgeInsets.all(AppSpacing.md),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: highMatch
                    ? context.sirati.primary.withValues(alpha: 0.35)
                    : context.sirati.border,
                width: highMatch ? 1.4 : 1.0,
              ),
              boxShadow: context.sirati.softShadow,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Top row: Badges (Verified / Tamheer / Match Score / New)
                Row(
                  children: [
                    if (item.isVerified) ...[
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: context.sirati.primaryLight,
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              item.isGov
                                  ? Icons.account_balance_rounded
                                  : item.isEnterprise
                                      ? Icons.business_rounded
                                      : Icons.verified_rounded,
                              size: 13,
                              color: context.sirati.primaryDark,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              item.sourceBadge ??
                                  (english ? 'Verified' : 'موثقة'),
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w700,
                                color: context.sirati.primaryDark,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 6),
                    ],
                    if (item.isTamheer) ...[
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: context.sirati.amberLight,
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          english ? 'Tamheer' : 'تمهير 🇸🇦',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                            color: context.sirati.amber,
                          ),
                        ),
                      ),
                      const SizedBox(width: 6),
                    ],
                    if (hasMatch) ...[
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: highMatch
                              ? context.sirati.successLight
                              : context.sirati.primaryLight,
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              Icons.stars_rounded,
                              size: 13,
                              color: highMatch
                                  ? context.sirati.onSuccessContainer
                                  : context.sirati.primaryDark,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              '${item.matchScore}% ${english ? 'Match' : 'تطابق'}',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                                color: highMatch
                                    ? context.sirati.onSuccessContainer
                                    : context.sirati.primaryDark,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                    const Spacer(),
                    if (isNew)
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(
                          color: context.sirati.primaryLight,
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Text(
                          english ? 'New' : 'جديد',
                          style: TextStyle(
                            fontSize: 10.5,
                            fontWeight: FontWeight.w700,
                            color: context.sirati.primary,
                          ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 10),

                // Title & Save icon
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: JobTitleDisplay(
                        title: item.title,
                        primaryStyle: AppTextStyles.titleMd(context.sirati),
                        secondaryStyle: TextStyle(
                          fontSize: 12.5,
                          fontWeight: FontWeight.w600,
                          color: context.sirati.textSecondary,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Icon(
                      Icons.bookmark_border_rounded,
                      size: 20,
                      color: context.sirati.textHint,
                    ),
                  ],
                ),
                const SizedBox(height: 6),

                // Company and location
                if (meta.isNotEmpty)
                  Text(
                    meta,
                    textAlign: TextAlign.start,
                    style: TextStyle(
                      fontSize: 12.5,
                      height: 1.35,
                      fontWeight: FontWeight.w500,
                      color: context.sirati.textSecondary,
                    ),
                  ),

                // Match reason snippet if available
                if (item.matchReasons.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                    decoration: BoxDecoration(
                      color: context.sirati.surfaceLow,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          Icons.check_circle_outline_rounded,
                          size: 14,
                          color: context.sirati.primaryDark,
                        ),
                        const SizedBox(width: 6),
                        Flexible(
                          child: Text(
                            item.matchReasons.first,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 11.5,
                              fontWeight: FontWeight.w600,
                              color: context.sirati.textSecondary,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],

                // Required skills chips
                if (item.skillsRequired.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 6,
                    runSpacing: 4,
                    children: item.skillsRequired.take(3).map((skill) {
                      final isMatched = item.matchedSkills.any((m) =>
                          m.toLowerCase() == skill.toLowerCase() ||
                          skill.toLowerCase().contains(m.toLowerCase()));
                      return Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 7, vertical: 2),
                        decoration: BoxDecoration(
                          color: isMatched
                              ? context.sirati.primaryLight
                              : context.sirati.surfaceLow,
                          borderRadius: BorderRadius.circular(6),
                          border: Border.all(
                            color: isMatched
                                ? context.sirati.primary.withValues(alpha: 0.3)
                                : context.sirati.border,
                            width: 0.8,
                          ),
                        ),
                        child: Text(
                          skill,
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight:
                                isMatched ? FontWeight.w700 : FontWeight.w500,
                            color: isMatched
                                ? context.sirati.primaryDark
                                : context.sirati.textSecondary,
                          ),
                        ),
                      );
                    }).toList(),
                  ),
                ],

                const SizedBox(height: 10),
                // Bottom row: Salary + Published time
                Row(
                  children: [
                    if (item.salaryRange != null &&
                        item.salaryRange!.isNotEmpty) ...[
                      Text(
                        item.salaryRange!,
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: context.sirati.primaryDark,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text('·',
                          style: TextStyle(color: context.sirati.textHint)),
                      const SizedBox(width: 8),
                    ],
                    Text(
                      jobPublishedText(item, english: english),
                      style: TextStyle(
                        fontSize: 11.5,
                        color: context.sirati.textHint,
                      ),
                    ),
                    const Spacer(),
                    Text(
                      english ? 'Details →' : 'التفاصيل ←',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: context.sirati.primary,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
