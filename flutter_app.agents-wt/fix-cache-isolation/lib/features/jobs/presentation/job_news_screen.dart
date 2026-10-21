import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/routing/app_routes.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/jobs/application/job_news_controllers.dart';
import 'package:sirati/features/jobs/domain/job_news_feed.dart';
import 'package:sirati/features/jobs/domain/job_news_filters.dart';
import 'package:sirati/features/jobs/presentation/job_news_detail_screen.dart';
import 'package:sirati/features/jobs/presentation/widgets/featured_job_card.dart';
import 'package:sirati/features/jobs/presentation/widgets/job_news_card.dart';
import 'package:sirati/features/jobs/presentation/widgets/job_news_common.dart';
import 'package:sirati/features/jobs/presentation/widgets/job_news_filters_bar.dart';
import 'package:sirati/features/jobs/presentation/widgets/profile_context_banner.dart';
import 'package:sirati/shared/models/job_news.dart';
import 'package:sirati/shared/services/session_cache.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/loading/app_async_body.dart';
import 'package:sirati/shared/widgets/loading/app_skeleton.dart';
import 'package:sirati/shared/widgets/loading/async_value_snapshot.dart';
import 'package:sirati/shared/widgets/motion.dart';
import 'package:sirati/shared/widgets/offline_cache_banner.dart';
import 'package:sirati/shared/widgets/screen_header.dart';

/// Job-news tab: search, preset / category / city filters, a featured
/// posting, then the rest of the list. Data comes from [jobNewsFeedProvider].
class JobNewsScreen extends ConsumerStatefulWidget {
  const JobNewsScreen({super.key, this.isActive = true});

  /// Whether this tab is currently visible in the dashboard shell.
  final bool isActive;

  @override
  ConsumerState<JobNewsScreen> createState() => _JobNewsScreenState();
}

class _JobNewsScreenState extends ConsumerState<JobNewsScreen> {
  final _searchController = TextEditingController();
  Timer? _searchDebounce;

  @override
  void didUpdateWidget(covariant JobNewsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    // Returning to the tab always refetches so backend changes show up
    // immediately instead of after a restart or cache expiry.
    if (widget.isActive && !oldWidget.isActive) {
      unawaited(refreshJobNews(ref, english: AppLocale.isEnglish(context)));
    }
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  void _onSearchChanged(String value) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 350), () {
      if (mounted) ref.read(jobNewsFiltersProvider.notifier).setQuery(value);
    });
  }

  void _openJob(JobNews item) {
    unawaited(Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => JobNewsDetailScreen(item: item)),
    ));
  }

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    final feed = ref.watch(jobNewsFeedProvider(english));
    final filters = ref.watch(jobNewsFiltersProvider);

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

          return AppAsyncBody<JobNewsFeed>(
            snapshot: feed.toSnapshot(),
            english: english,
            onRetry: () => ref.invalidate(jobNewsFeedProvider(english)),
            fallbackOnEmptyError: JobNewsFeed.empty(english: english),
            errorMessage: (error) => error is ApiException
                ? error.displayMessage
                : (english
                    ? 'Could not load job news.'
                    : 'تعذر تحميل أخبار الوظائف.'),
            loading: shell(
              JobNewsSkeleton(horizontalPadding: horizontalPadding),
            ),
            builder: (data) => shell(
              _JobNewsList(
                feed: data,
                filters: filters,
                english: english,
                horizontalPadding: horizontalPadding,
                isReloading: feed.isLoading,
                searchController: _searchController,
                onSearchChanged: _onSearchChanged,
                onRefresh: () => refreshJobNews(ref, english: english),
                onOpenJob: _openJob,
              ),
            ),
          );
        },
      ),
    );
  }
}

class _JobNewsList extends ConsumerWidget {
  const _JobNewsList({
    required this.feed,
    required this.filters,
    required this.english,
    required this.horizontalPadding,
    required this.isReloading,
    required this.searchController,
    required this.onSearchChanged,
    required this.onRefresh,
    required this.onOpenJob,
  });

  final JobNewsFeed feed;
  final JobNewsFilters filters;
  final bool english;
  final double horizontalPadding;
  final bool isReloading;
  final TextEditingController searchController;
  final ValueChanged<String> onSearchChanged;
  final Future<void> Function() onRefresh;
  final ValueChanged<JobNews> onOpenJob;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final filterController = ref.read(jobNewsFiltersProvider.notifier);
    final featured = feed.featured;
    final latest = feed.latest;
    final profile = feed.profile;
    // Changing filters or results swaps the featured block with an animation.
    final resultsKey = '${filters.hashCode}|${feed.items.length}|'
        '${featured?.id ?? 0}';

    return RefreshIndicator(
      color: context.sirati.primary,
      onRefresh: onRefresh,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverPadding(
            padding: EdgeInsets.fromLTRB(
                horizontalPadding, 18, horizontalPadding, 0),
            sliver: SliverToBoxAdapter(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  ScreenHeader(
                    english: english,
                    title: feed.title,
                    icon: Icons.work_rounded,
                    unreadCount: SessionCache.instance.unreadCount,
                    onNotifications: () => Navigator.of(context)
                        .pushNamed(AppRoutes.notifications),
                    onAvatarTap: () =>
                        Navigator.of(context).pushNamed(AppRoutes.settings),
                  ),
                  if (feed.offline) ...[
                    const SizedBox(height: AppSpacing.sm),
                    OfflineCacheBanner(english: english, surface: 'news'),
                  ],
                  if (isReloading)
                    const Padding(
                      padding: EdgeInsets.only(top: 10, bottom: 2),
                      child: LinearProgressIndicator(minHeight: 3),
                    ),
                  if (profile != null && profile.hasProfile) ...[
                    const SizedBox(height: AppSpacing.md),
                    ProfileContextBanner(
                      english: english,
                      profile: profile,
                      isMatchedActive: filters.preset == 'matched',
                      onToggleMatched: filterController.toggleMatched,
                    ),
                  ],
                  const SizedBox(height: AppSpacing.md),
                  JobSearchBar(
                    english: english,
                    controller: searchController,
                    onChanged: onSearchChanged,
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  PresetChips(
                    english: english,
                    selectedPreset: filters.preset,
                    onSelected: filterController.setPreset,
                  ),
                  const SizedBox(height: AppSpacing.xs),
                  CategoryChips(
                    english: english,
                    selectedCategory: filters.category,
                    onSelected: filterController.setCategory,
                  ),
                  const SizedBox(height: AppSpacing.xs),
                  CityChips(
                    english: english,
                    selectedCity: filters.city,
                    onSelected: filterController.setCity,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  AnimatedSwitcher(
                    duration: MotionSettings.reduce(context)
                        ? Duration.zero
                        : MotionDurations.medium,
                    switchInCurve: MotionCurves.enter,
                    switchOutCurve: MotionCurves.exit,
                    child: Column(
                      key: ValueKey(resultsKey),
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        if (featured != null) ...[
                          MotionReveal(
                            order: 0,
                            child: FeaturedJobCard(
                              item: featured,
                              english: english,
                              onTap: () => onOpenJob(featured),
                            ),
                          ),
                          const SizedBox(height: AppSpacing.lg),
                        ],
                        Text(
                          english ? 'Latest Postings' : 'آخر الإعلانات',
                          textAlign: TextAlign.start,
                          style: AppTextStyles.titleMd(context.sirati),
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        if (latest.isEmpty) EmptyJobNews(english: english),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          SliverPadding(
            padding: EdgeInsets.fromLTRB(
                horizontalPadding, 0, horizontalPadding, 112),
            sliver: SliverList.builder(
              itemCount: latest.length,
              itemBuilder: (context, index) {
                final item = latest[index];
                return Padding(
                  padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                  // +1: the featured card is reveal order 0.
                  child: MotionReveal(
                    order: index + 1,
                    child: JobNewsCard(
                      item: item,
                      english: english,
                      isNew: index == 0,
                      onTap: () => onOpenJob(item),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
