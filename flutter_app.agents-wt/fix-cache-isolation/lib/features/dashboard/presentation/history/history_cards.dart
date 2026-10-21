import 'package:flutter/material.dart';
import 'package:sirati/core/utils/app_format.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/ats_scanner/domain/score_band.dart';
import 'package:sirati/features/ats_scanner/presentation/analysis_result/score_band_style.dart';
import 'package:sirati/features/dashboard/domain/history_page.dart';
import 'package:sirati/shared/models/cv_analysis.dart';
import 'package:sirati/shared/models/generated_cv.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/animated_ats_score_bar.dart';
import 'package:sirati/shared/widgets/motion.dart';

String _dateLabel(DateTime? date, {required bool english}) =>
    date == null ? '-' : AppFormat.relativeTime(date, english: english);

/// Score and grade in the band's container colours (AA in both themes).
class ScoreBadge extends StatelessWidget {
  const ScoreBadge({super.key, required this.score, required this.grade});

  final int score;
  final String grade;

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    final band = ScoreBand.of(score);
    final english = AppLocale.isEnglish(context);
    return Semantics(
      label: english
          ? 'Score $score, grade $grade'
          : 'الدرجة $score، التقدير $grade',
      excludeSemantics: true,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: band.container(c),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              AppFormat.digits('$score', english: english),
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w800,
                color: band.onContainer(c),
              ),
            ),
            if (grade.trim().isNotEmpty && grade != '-')
              Text(
                grade,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  color: band.onContainer(c),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _MetaChip extends StatelessWidget {
  const _MetaChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: c.primaryLight,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w600,
          color: c.primaryInk,
        ),
      ),
    );
  }
}

class _CardShell extends StatelessWidget {
  const _CardShell({required this.onTap, required this.child});

  final VoidCallback onTap;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    final radius = BorderRadius.circular(14);
    return PressScale(
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: radius,
          boxShadow: c.softShadow,
        ),
        child: Material(
          color: c.surface,
          shape: RoundedRectangleBorder(
            borderRadius: radius,
            side: BorderSide(color: c.border),
          ),
          child: InkWell(
            onTap: onTap,
            borderRadius: radius,
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: child,
            ),
          ),
        ),
      ),
    );
  }
}

class AnalysisHistoryCard extends StatelessWidget {
  const AnalysisHistoryCard({
    super.key,
    required this.analysis,
    required this.onTap,
  });

  final CvAnalysis analysis;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    final c = context.sirati;
    final band = ScoreBand.of(analysis.scoreTotal);
    return _CardShell(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: c.primaryLight,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(Icons.manage_search, size: 18, color: c.primary),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text(
                  LocaleFormat.mixedTitle(
                    analysis.targetJobTitle,
                    english: english,
                  ),
                  textAlign: TextAlign.start,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: AppTextStyles.titleSm(c),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              ScoreBadge(score: analysis.scoreTotal, grade: analysis.grade),
            ],
          ),
          const SizedBox(height: AppSpacing.xs),
          AnimatedAtsScoreBar(
            value: (analysis.scoreTotal / 100).clamp(0.0, 1.0),
            color: band.color(c),
            height: 5,
            borderRadius: 4,
          ),
          const SizedBox(height: AppSpacing.xs),
          Row(
            children: [
              _MetaChip(
                label: inputMethodLabel(analysis.inputMethod, english: english),
              ),
              const Spacer(),
              Text(
                _dateLabel(analysis.createdAt, english: english),
                style: TextStyle(fontSize: 11, color: c.textSecondary),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class GeneratedCvHistoryCard extends StatelessWidget {
  const GeneratedCvHistoryCard({
    super.key,
    required this.cv,
    required this.onTap,
  });

  final GeneratedCv cv;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    final c = context.sirati;
    final name = cv.fullName.trim();
    return _CardShell(
      onTap: onTap,
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: c.primaryLight,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Center(
              child: ExcludeSemantics(
                child: Text(
                  name.isEmpty ? 'س' : BidiText.avatarInitial(name),
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: c.primaryInk,
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  LocaleFormat.mixedTitle(cv.fullName, english: english),
                  textAlign: TextAlign.start,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTextStyles.titleSm(c),
                ),
                const SizedBox(height: 3),
                Text(
                  LocaleFormat.mixedTitle(cv.targetJobTitle, english: english),
                  textAlign: TextAlign.start,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTextStyles.bodySm(c),
                ),
                const SizedBox(height: 4),
                Row(
                  children: [
                    _MetaChip(label: cv.language == 'ar' ? 'عربي' : 'English'),
                    const SizedBox(width: 5),
                    Text(
                      _dateLabel(cv.createdAt, english: english),
                      style: TextStyle(fontSize: 11, color: c.textSecondary),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          ScoreBadge(score: cv.scoreTotal, grade: cv.grade),
        ],
      ),
    );
  }
}

/// "Load more" / "Loading…" / "Retry" footer for a history list.
class LoadMoreFooter extends StatelessWidget {
  const LoadMoreFooter({
    super.key,
    required this.loading,
    required this.failed,
    required this.onPressed,
  });

  final bool loading;
  final bool failed;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    final label = loading
        ? (english ? 'Loading…' : 'جارٍ التحميل...')
        : failed
            ? (english
                ? 'Couldn\'t load more. Retry'
                : 'تعذّر تحميل المزيد. إعادة المحاولة')
            : (english ? 'Load more' : 'تحميل المزيد');
    return TextButton(
      onPressed: loading ? null : onPressed,
      style: TextButton.styleFrom(
        foregroundColor: context.sirati.primaryInk,
        minimumSize: const Size.fromHeight(48),
      ),
      child: Text(label),
    );
  }
}
