import 'dart:async';

import 'package:flutter/material.dart';
import 'package:sirati/core/routing/app_routes.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/core/utils/safe_url.dart';
import 'package:sirati/features/jobs/domain/job_news_feed.dart';
import 'package:sirati/features/jobs/presentation/widgets/job_news_common.dart';
import 'package:sirati/features/jobs/presentation/widgets/job_news_format.dart';
import 'package:sirati/features/jobs/presentation/widgets/job_title_display.dart';
import 'package:sirati/shared/models/cv_target_job.dart';
import 'package:sirati/shared/models/job_news.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/app_snack_bar.dart';

class JobNewsDetailScreen extends StatelessWidget {
  final JobNews item;

  const JobNewsDetailScreen({super.key, required this.item});

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    final actionUrl = item.actionUrl;
    final matchScore = displayMatchScore(item.matchScore);
    final hasMatch = matchScore != null;
    final highMatch = isHighMatch(matchScore);

    return Scaffold(
      appBar: AppBar(title: Text(english ? 'Job Details' : 'تفاصيل الخبر')),
      body: CustomScrollView(
        slivers: [
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
            sliver: SliverToBoxAdapter(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (item.isVerified) ...[
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 10, vertical: 5),
                          decoration: BoxDecoration(
                            color: context.sirati.primaryLight,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(
                              color:
                                  context.sirati.primary.withValues(alpha: 0.3),
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
                                size: 14,
                                color: context.sirati.primaryInk,
                              ),
                              const SizedBox(width: 5),
                              Text(
                                item.sourceBadge ??
                                    (english ? 'Verified' : 'جهة موثقة معتمدة'),
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700,
                                  color: context.sirati.primaryInk,
                                ),
                              ),
                            ],
                          ),
                        ),
                        if (item.isTamheer) ...[
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 10, vertical: 5),
                            decoration: BoxDecoration(
                              color: context.sirati.warningLight,
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(
                                color:
                                    context.sirati.amber.withValues(alpha: 0.3),
                              ),
                            ),
                            child: Text(
                              english ? 'Tamheer Program' : 'برنامج تمهير 🇸🇦',
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w700,
                                color: context.sirati.onWarningContainer,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 12),
                  ],
                  JobTitleDisplay(
                    title: item.title,
                    primaryMaxLines: null,
                    secondaryMaxLines: null,
                    primaryStyle: TextStyle(
                      fontSize: 25,
                      height: 1.35,
                      fontWeight: FontWeight.w800,
                      color: context.sirati.textPrimary,
                    ),
                    secondaryStyle: TextStyle(
                      fontSize: 17,
                      height: 1.35,
                      fontWeight: FontWeight.w600,
                      color: context.sirati.textSecondary,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    alignment: WrapAlignment.start,
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      if ((item.company ?? '').isNotEmpty)
                        MetaChip(
                            text: LocaleFormat.mixedTitle(item.company!,
                                english: english)),
                      if ((item.location ?? '').isNotEmpty)
                        MetaChip(text: item.location!),
                      if (item.salaryRange != null &&
                          item.salaryRange!.isNotEmpty)
                        MetaChip(
                          text: '💰 ${item.salaryRange!}',
                          highlight: true,
                        ),
                      if (jobPublishedText(item, english: english).isNotEmpty)
                        MetaChip(
                            text: jobPublishedText(item, english: english)),
                      if (jobValidUntilText(item, english: english).isNotEmpty)
                        MetaChip(
                          text: jobValidUntilText(item, english: english),
                          highlight: true,
                        ),
                    ],
                  ),
                  if (hasMatch) ...[
                    const SizedBox(height: 16),
                    Container(
                      padding: const EdgeInsets.all(AppSpacing.md),
                      decoration: BoxDecoration(
                        color: context.sirati.surface,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(
                          color: highMatch
                              ? context.sirati.primary.withValues(alpha: 0.4)
                              : context.sirati.border,
                          width: 1.2,
                        ),
                        boxShadow: context.sirati.softShadow,
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Icon(Icons.stars_rounded,
                                  color: context.sirati.primary, size: 22),
                              const SizedBox(width: 8),
                              Text(
                                english
                                    ? 'Profile & CV Compatibility'
                                    : 'توافق الوظيفة مع ملفك المهني',
                                style: TextStyle(
                                  fontSize: 14.5,
                                  fontWeight: FontWeight.w800,
                                  color: context.sirati.textPrimary,
                                ),
                              ),
                              const Spacer(),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 9, vertical: 3),
                                decoration: BoxDecoration(
                                  color: highMatch
                                      ? context.sirati.successLight
                                      : context.sirati.primaryLight,
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: Text(
                                  '$matchScore%',
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w800,
                                    color: highMatch
                                        ? context.sirati.onSuccessContainer
                                        : context.sirati.primaryInk,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 10),
                          ClipRRect(
                            borderRadius: BorderRadius.circular(6),
                            child: LinearProgressIndicator(
                              value: matchScore / 100.0,
                              minHeight: 8,
                              backgroundColor: context.sirati.surfaceLow,
                              valueColor: AlwaysStoppedAnimation<Color>(
                                highMatch
                                    ? context.sirati.success
                                    : context.sirati.primary,
                              ),
                            ),
                          ),
                          if (item.matchReasons.isNotEmpty) ...[
                            const SizedBox(height: 12),
                            for (final reason in item.matchReasons)
                              Padding(
                                padding: const EdgeInsets.only(bottom: 4),
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Icon(Icons.check_circle_rounded,
                                        size: 15,
                                        color: context.sirati.primary),
                                    const SizedBox(width: 6),
                                    Expanded(
                                      child: Text(
                                        reason,
                                        style: TextStyle(
                                          fontSize: 12.5,
                                          fontWeight: FontWeight.w600,
                                          color: context.sirati.textSecondary,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                          ],
                        ],
                      ),
                    ),
                  ],
                  if (item.skillsRequired.isNotEmpty) ...[
                    const SizedBox(height: 20),
                    Text(
                      english
                          ? 'Key Skills Required'
                          : 'المهارات المطلوبة للوظيفة',
                      style: AppTextStyles.titleMd(context.sirati),
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: item.skillsRequired.map((skill) {
                        final isMatched =
                            isSkillMatched(skill, item.matchedSkills);
                        return Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 10, vertical: 5),
                          decoration: BoxDecoration(
                            color: isMatched
                                ? context.sirati.primaryLight
                                : context.sirati.surfaceLow,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(
                              color: isMatched
                                  ? context.sirati.primary
                                      .withValues(alpha: 0.35)
                                  : context.sirati.border,
                            ),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              if (isMatched) ...[
                                Icon(Icons.check_rounded,
                                    size: 13, color: context.sirati.primaryInk),
                                const SizedBox(width: 4),
                              ],
                              Text(
                                skill,
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: isMatched
                                      ? FontWeight.w700
                                      : FontWeight.w500,
                                  color: isMatched
                                      ? context.sirati.primaryInk
                                      : context.sirati.textSecondary,
                                ),
                              ),
                            ],
                          ),
                        );
                      }).toList(),
                    ),
                  ],
                  const SizedBox(height: 22),
                  Text(
                    english ? 'Job Description' : 'تفاصيل ومتطلبات الإعلان',
                    style: AppTextStyles.titleMd(context.sirati),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    LocaleFormat.mixedBody(item.body, english: english),
                    textAlign: TextAlign.start,
                    style: TextStyle(
                      fontSize: 15.5,
                      height: 1.75,
                      color: context.sirati.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 28),
                  ElevatedButton.icon(
                    // Carries this post into the generator so the CV is
                    // actually tailored to it (target title + description).
                    onPressed: () => unawaited(Navigator.of(context).pushNamed(
                      AppRoutes.createCv,
                      arguments: CvTargetJob(
                        title: item.title,
                        description: item.body,
                      ),
                    )),
                    icon: const Icon(Icons.auto_awesome_rounded),
                    label: Text(
                      english
                          ? 'Tailor My CV for this Job'
                          : 'تخصيص سيرتي الذاتية لهذه الوظيفة 🪄',
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: context.sirati.primary,
                      foregroundColor: context.sirati.onPrimary,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                  ),
                  if (actionUrl != null) ...[
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: () async {
                        final opened = await launchSafeExternalUrl(actionUrl);
                        if (!opened && context.mounted) {
                          AppSnackBar.error(
                            context,
                            english
                                ? 'Could not open the application page.'
                                : 'تعذر فتح صفحة التقديم.',
                          );
                        }
                      },
                      icon: const Icon(Icons.open_in_new_rounded),
                      label: Text(
                        english
                            ? 'Apply on Official Site'
                            : 'التقديم عبر الموقع الرسمي',
                      ),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: context.sirati.primaryInk,
                        side: BorderSide(color: context.sirati.borderStrong),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
