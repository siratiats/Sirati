import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/ats_scanner/presentation/cv_analysis_screen.dart';
import 'package:sirati/features/cv_builder/presentation/cv_generator_screen.dart';
import 'package:sirati/features/dashboard/application/dashboard_controller.dart';
import 'package:sirati/features/dashboard/domain/dashboard_summary.dart';
import 'package:sirati/features/dashboard/domain/dashboard_tab.dart';
import 'package:sirati/features/dashboard/presentation/history_screen.dart';
import 'package:sirati/features/dashboard/presentation/home/dashboard_action_card.dart';
import 'package:sirati/features/dashboard/presentation/home/dashboard_stat_card.dart';
import 'package:sirati/features/settings/presentation/notifications_screen.dart';
import 'package:sirati/features/settings/presentation/settings_screen.dart';
import 'package:sirati/shared/services/session_cache.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/app_list_tile.dart';
import 'package:sirati/shared/widgets/empty_state.dart';
import 'package:sirati/shared/widgets/loading/app_async_body.dart';
import 'package:sirati/shared/widgets/loading/app_skeleton.dart';
import 'package:sirati/shared/widgets/loading/async_value_snapshot.dart';
import 'package:sirati/shared/widgets/motion.dart';
import 'package:sirati/shared/widgets/offline_cache_banner.dart';
import 'package:sirati/shared/widgets/screen_header.dart';

/// Home tab: greeting, stats, primary actions, and latest activity.
class DashboardHomeTab extends ConsumerWidget {
  const DashboardHomeTab({super.key, required this.onNavigate});

  final ValueChanged<DashboardTab> onNavigate;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final english = AppLocale.isEnglish(context);
    final summary = ref.watch(dashboardProvider(english));

    return SafeArea(
      child: LayoutBuilder(
        builder: (context, constraints) {
          final maxContentWidth = constraints.maxWidth >= 1100
              ? 920.0
              : constraints.maxWidth >= 780
                  ? 760.0
                  : constraints.maxWidth;
          final horizontalPadding = AppSpacing.pageGutter(constraints.maxWidth);

          Widget shell(Widget child) => Align(
                alignment: Alignment.topCenter,
                child: ConstrainedBox(
                  constraints: BoxConstraints(maxWidth: maxContentWidth),
                  child: child,
                ),
              );

          return AppAsyncBody<DashboardSummary>(
            snapshot: summary.toSnapshot(),
            english: english,
            onRetry: () => ref.invalidate(dashboardProvider(english)),
            errorMessage: (error) => error is ApiException
                ? error.displayMessage
                : (english
                    ? 'Could not load dashboard.'
                    : 'تعذر تحميل الرئيسية.'),
            loading: shell(
              DashboardSkeleton(horizontalPadding: horizontalPadding),
            ),
            builder: (data) => shell(
              _DashboardList(
                summary: data,
                english: english,
                horizontalPadding: horizontalPadding,
                onRefresh: () => refreshDashboard(ref, english: english),
                onNavigate: onNavigate,
              ),
            ),
          );
        },
      ),
    );
  }
}

class _DashboardList extends StatelessWidget {
  const _DashboardList({
    required this.summary,
    required this.english,
    required this.horizontalPadding,
    required this.onRefresh,
    required this.onNavigate,
  });

  final DashboardSummary summary;
  final bool english;
  final double horizontalPadding;
  final Future<void> Function() onRefresh;
  final ValueChanged<DashboardTab> onNavigate;

  void _push(BuildContext context, Widget screen) {
    unawaited(Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => screen),
    ));
  }

  @override
  Widget build(BuildContext context) {
    final primary = summary.primaryAction;
    final analysis = summary.analysisAction;
    final textButtonStyle = TextButton.styleFrom(
      minimumSize: const Size(48, 36),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
      tapTargetSize: MaterialTapTargetSize.padded,
    );

    return RefreshIndicator(
      color: context.sirati.primary,
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.fromLTRB(horizontalPadding, 18, horizontalPadding,
            AppSpacing.scrollBottomNav),
        children: [
          MotionReveal(
            order: 0,
            child: ScreenHeader(
              english: english,
              title: AppLocale.greeting(summary.name, context),
              titleSize: 22,
              icon: Icons.person_rounded,
              status: summary.status.isEmpty ? null : summary.status,
              unreadCount: summary.unreadNotifications ??
                  SessionCache.instance.unreadCount,
              onNotifications: () async {
                await Navigator.of(context).push(
                  MaterialPageRoute(
                      builder: (_) => const NotificationsScreen()),
                );
                // Reading notifications changes the unread badge.
                await onRefresh();
              },
              onAvatarTap: () => _push(context, const SettingsScreen()),
            ),
          ),
          if (summary.offline) ...[
            const SizedBox(height: AppSpacing.sm),
            OfflineCacheBanner(english: english, surface: 'dashboard'),
          ],
          const SizedBox(height: AppSpacing.lg),
          MotionReveal(
            order: 1,
            child: Row(
              children: [
                Expanded(
                  child: DashboardStatCard(
                    label: english ? 'My CVs' : 'السير الذاتية',
                    count: summary.cvCount,
                    icon: Icons.description_outlined,
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: DashboardStatCard(
                    label: english ? 'Analyses' : 'التحليلات',
                    count: summary.analysisCount,
                    icon: Icons.analytics_outlined,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          MotionReveal(
            order: 2,
            child: DashboardActionCard(
              title: LocaleFormat.mixedTitle(primary.title, english: english),
              subtitle:
                  LocaleFormat.mixedBody(primary.subtitle, english: english),
              buttonLabel: primary.buttonLabel,
              tone: DashboardActionTone.brand,
              icon: Icons.description_outlined,
              watermark: Icons.description_outlined,
              onTap: () => _push(context, const CvGeneratorScreen()),
            ),
          ),
          const SizedBox(height: 16),
          MotionReveal(
            order: 3,
            child: DashboardActionCard(
              title: LocaleFormat.mixedTitle(analysis.title, english: english),
              subtitle:
                  LocaleFormat.mixedBody(analysis.subtitle, english: english),
              buttonLabel: analysis.buttonLabel,
              tone: DashboardActionTone.neutral,
              icon: Icons.analytics_outlined,
              watermark: Icons.bar_chart_rounded,
              onTap: () => _push(context, const CvAnalysisScreen()),
            ),
          ),
          const SizedBox(height: 24),
          Row(
            children: [
              Text(
                english ? 'Recent Activity' : 'النشاط الأخير',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                  color: context.sirati.textPrimary,
                ),
              ),
              const Spacer(),
              TextButton.icon(
                onPressed: () => _push(context, const HistoryScreen()),
                icon: const Icon(Icons.history_rounded, size: 16),
                label: Text(english ? 'History' : 'السجل'),
                style: textButtonStyle,
              ),
              const SizedBox(width: 4),
              TextButton(
                onPressed: () => onNavigate(DashboardTab.myCvs),
                style: textButtonStyle,
                child: Text(
                  english ? 'View All' : 'عرض الكل',
                  style: const TextStyle(fontSize: 12.5),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          MotionReveal(
            order: 4,
            child: summary.hasLatestNews
                ? AppListTile(
                    leadingIcon: Icons.schedule_rounded,
                    title: LocaleFormat.mixedTitle(
                      summary.latestNewsTitle,
                      english: english,
                    ),
                    subtitle: summary.latestNewsSubtitle,
                  )
                : AppEmptyState(
                    icon: Icons.history_toggle_off_rounded,
                    title: english
                        ? 'No recent activity yet'
                        : 'لا يوجد نشاط أخير بعد',
                    subtitle: english
                        ? 'Create a CV or run an analysis to see updates here.'
                        : 'أنشئ سيرة ذاتية أو نفّذ تحليلاً لتظهر التحديثات هنا.',
                    actionLabel: english ? 'Create CV' : 'إنشاء سيرة',
                    onAction: () => _push(context, const CvGeneratorScreen()),
                    scrollable: false,
                    topInsetFactor: 0,
                  ),
          ),
        ],
      ),
    );
  }
}
