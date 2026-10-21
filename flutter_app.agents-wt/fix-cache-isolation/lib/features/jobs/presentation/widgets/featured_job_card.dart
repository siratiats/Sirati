import 'package:flutter/material.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/jobs/presentation/widgets/job_news_format.dart';
import 'package:sirati/features/jobs/presentation/widgets/job_title_display.dart';
import 'package:sirati/shared/models/job_news.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/motion.dart';

class FeaturedJobCard extends StatelessWidget {
  final JobNews item;
  final bool english;
  final VoidCallback onTap;

  const FeaturedJobCard({
    super.key,
    required this.item,
    required this.english,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return PressScale(
      child: Material(
        borderRadius: BorderRadius.circular(20),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(20),
          child: Ink(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                // brandSurface, not primary: white text needs a dark fill in both
                // themes (primary -> white is ~2.8:1 light, ~2:1 dark).
                colors: [
                  context.sirati.brandSurface,
                  context.sirati.brandSurfaceDeep,
                ],
              ),
              borderRadius: BorderRadius.circular(20),
            ),
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: context.sirati.primaryLight,
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        english ? 'New' : 'جديد',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: context.sirati.primaryDark,
                        ),
                      ),
                    ),
                    if (item.isVerified)
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 9, vertical: 4),
                        decoration: BoxDecoration(
                          color: context.sirati.onBrandSurface
                              .withValues(alpha: 0.2),
                          borderRadius: BorderRadius.circular(999),
                          border: Border.all(
                            color: context.sirati.onBrandSurface
                                .withValues(alpha: 0.45),
                          ),
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
                              color: context.sirati.onBrandSurface,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              item.sourceBadge ??
                                  (english ? 'Verified' : 'جهة موثقة'),
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w700,
                                color: context.sirati.onBrandSurface,
                              ),
                            ),
                          ],
                        ),
                      ),
                    if (item.isTamheer)
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 9, vertical: 4),
                        decoration: BoxDecoration(
                          color: context.sirati.onBrandSurface
                              .withValues(alpha: 0.2),
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Text(
                          english ? 'Tamheer 🇸🇦' : 'تمهير 🇸🇦',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                            color: context.sirati.onBrandSurface,
                          ),
                        ),
                      ),
                    if (item.matchScore != null)
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 9, vertical: 4),
                        decoration: BoxDecoration(
                          color: context.sirati.onBrandSurface,
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              Icons.stars_rounded,
                              size: 13,
                              color: context.sirati.primaryDark,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              '${item.matchScore}% ${english ? 'Match' : 'تطابق'}',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                                color: context.sirati.primaryDark,
                              ),
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 14),
                JobTitleDisplay(
                  title: item.title,
                  primaryStyle: TextStyle(
                    fontSize: 17,
                    height: 1.35,
                    fontWeight: FontWeight.w800,
                    color: context.sirati.onBrandSurface,
                  ),
                  secondaryStyle: TextStyle(
                    fontSize: 13.5,
                    height: 1.35,
                    fontWeight: FontWeight.w600,
                    color: context.sirati.onBrandSurface.withValues(alpha: .9),
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  LocaleFormat.mixedTitle(item.company ?? '', english: english),
                  textAlign: TextAlign.start,
                  style: TextStyle(
                    fontSize: 13.5,
                    height: 1.45,
                    color: context.sirati.onBrandSurface.withValues(alpha: .88),
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  [
                    if ((item.location ?? '').isNotEmpty) item.location!,
                    jobPublishedText(item, english: english),
                  ].where((s) => s.isNotEmpty).join(' · '),
                  textAlign: TextAlign.start,
                  style: TextStyle(
                    fontSize: 12.5,
                    height: 1.45,
                    color: context.sirati.onBrandSurface.withValues(alpha: .85),
                  ),
                ),
                if (item.skillsRequired.isNotEmpty) ...[
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 6,
                    runSpacing: 4,
                    children: item.skillsRequired.take(3).map((skill) {
                      return Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: context.sirati.onBrandSurface
                              .withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(6),
                          border: Border.all(
                            color: context.sirati.onBrandSurface
                                .withValues(alpha: 0.3),
                            width: 0.8,
                          ),
                        ),
                        child: Text(
                          skill,
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: context.sirati.onBrandSurface,
                          ),
                        ),
                      );
                    }).toList(),
                  ),
                ],
                const SizedBox(height: 16),
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 18, vertical: 9),
                      decoration: BoxDecoration(
                        color: context.sirati.onBrandSurface,
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        english ? 'Apply Now' : 'تقدم الآن',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: context.sirati.primaryDark,
                        ),
                      ),
                    ),
                    const Spacer(),
                    if (item.salaryRange != null &&
                        item.salaryRange!.isNotEmpty)
                      Text(
                        item.salaryRange!,
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: context.sirati.onBrandSurface,
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
