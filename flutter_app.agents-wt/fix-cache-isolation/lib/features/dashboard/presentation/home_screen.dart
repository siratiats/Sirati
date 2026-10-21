import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:sirati/core/network/analytics_service.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/core/utils/safe_url.dart';
import 'package:sirati/features/ats_scanner/presentation/cv_analysis_screen.dart';
import 'package:sirati/features/cv_builder/presentation/cv_generator_screen.dart';
import 'package:sirati/features/cv_builder/presentation/my_cvs_screen.dart';
import 'package:sirati/features/dashboard/domain/dashboard_tab.dart';
import 'package:sirati/features/dashboard/domain/notification_target.dart';
import 'package:sirati/features/dashboard/presentation/education_detail_screen.dart';
import 'package:sirati/features/dashboard/presentation/education_screen.dart';
import 'package:sirati/features/dashboard/presentation/history_screen.dart';
import 'package:sirati/features/dashboard/presentation/home/dashboard_home_tab.dart';
import 'package:sirati/features/dashboard/presentation/home/dashboard_navigation_bar.dart';
import 'package:sirati/features/jobs/presentation/job_news_screen.dart';
import 'package:sirati/features/settings/presentation/notifications_screen.dart';
import 'package:sirati/features/settings/presentation/settings_screen.dart';
import 'package:sirati/shared/services/notification_engagement_service.dart';
import 'package:sirati/shared/services/notification_service.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/app_snack_bar.dart';
import 'package:sirati/shared/widgets/motion.dart';

/// Authenticated shell: bottom-navigation tabs, Android back handling, and
/// push-notification routing.
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key, this.initialIndex = 0});

  final int initialIndex;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  late DashboardTab _tab = DashboardTab.fromIndex(widget.initialIndex);
  DateTime? _lastBackAtRoot;

  @override
  void initState() {
    super.initState();
    NotificationService.instance.onNotificationTap = _onNotificationTap;
    // Touch server activity so quiet/recent-activity policy stays accurate.
    unawaited(
      NotificationEngagementService.instance.reportActivity(event: 'app_open'),
    );
    _logTab(_tab);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      unawaited(_activatePushAfterVisible());
    });
  }

  @override
  void dispose() {
    // Detach so taps after logout are buffered for the next session instead
    // of navigating with this disposed context.
    final service = NotificationService.instance;
    if (service.onNotificationTap == _onNotificationTap) {
      service.onNotificationTap = null;
    }
    super.dispose();
  }

  Future<void> _activatePushAfterVisible() async {
    try {
      await NotificationService.instance.requestPermission();
      await NotificationService.instance.registerToken();
    } catch (e, st) {
      debugPrint('[FCM] post-home registration skipped: $e\n$st');
    }
  }

  void _setTab(DashboardTab tab) {
    if (tab == _tab) return;
    setState(() => _tab = tab);
    _logTab(tab);
  }

  void _logTab(DashboardTab tab) {
    unawaited(AnalyticsService.logScreenView(screenName: tab.screenName));
  }

  void _onRootBack(bool didPop, Object? result) {
    if (didPop) return;

    // Other tabs: first back returns to Home.
    if (_tab != DashboardTab.home) {
      _setTab(DashboardTab.home);
      _lastBackAtRoot = null;
      return;
    }

    // iOS: no system back / double-exit at the root.
    if (Theme.of(context).platform == TargetPlatform.iOS) return;

    final now = DateTime.now();
    final previous = _lastBackAtRoot;
    if (previous != null &&
        now.difference(previous) <= const Duration(seconds: 2)) {
      unawaited(SystemNavigator.pop());
      return;
    }

    _lastBackAtRoot = now;
    AppSnackBar.info(
      context,
      AppLocale.isEnglish(context)
          ? 'Press back again to exit'
          : 'اضغط رجوع مرة أخرى للخروج',
    );
  }

  void _onNotificationTap(Map<String, dynamic> data) {
    if (!mounted) return;
    switch (resolveNotificationTarget(data)) {
      case OpenExternalUrl(:final url):
        unawaited(launchSafeExternalUrl(url));
      case OpenTab(:final tab, :final educationId):
        _setTab(tab);
        if (educationId != null) {
          _push(EducationDetailScreen(id: educationId, fallback: const {}));
        }
      case OpenScreen(:final screen):
        _push(switch (screen) {
          NotificationScreen.notifications => const NotificationsScreen(),
          NotificationScreen.cvAnalysis => const CvAnalysisScreen(),
          NotificationScreen.history => const HistoryScreen(),
          NotificationScreen.cvGenerator => const CvGeneratorScreen(),
          NotificationScreen.settings => const SettingsScreen(),
        });
    }
  }

  void _push(Widget screen) {
    unawaited(Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => screen),
    ));
  }

  @override
  Widget build(BuildContext context) {
    // canPop:false keeps Home as the authenticated root (no return to splash).
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: _onRootBack,
      child: Scaffold(
        backgroundColor: context.sirati.background,
        body: MotionTabStack(
          currentIndex: _tab.index,
          children: [
            DashboardHomeTab(onNavigate: _setTab),
            const MyCvsScreen(),
            const EducationScreen(),
            JobNewsScreen(isActive: _tab == DashboardTab.jobNews),
          ],
        ),
        bottomNavigationBar: DashboardNavigationBar(
          current: _tab,
          onChanged: _setTab,
        ),
      ),
    );
  }
}
