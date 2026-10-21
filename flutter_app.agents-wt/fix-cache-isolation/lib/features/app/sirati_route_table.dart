import 'package:flutter/material.dart';

import 'package:sirati/core/routing/app_router.dart';
import 'package:sirati/core/routing/app_routes.dart';
import 'package:sirati/features/ats_scanner/presentation/cv_analysis_loader_screen.dart';
import 'package:sirati/features/ats_scanner/presentation/cv_analysis_screen.dart';
import 'package:sirati/features/auth/presentation/login_screen.dart';
import 'package:sirati/features/auth/presentation/register_screen.dart';
import 'package:sirati/features/cv_builder/presentation/cv_builder_screen.dart';
import 'package:sirati/features/cv_builder/presentation/cv_generator_screen.dart';
import 'package:sirati/features/cv_builder/presentation/generated_cv_loader_screen.dart';
import 'package:sirati/features/dashboard/presentation/education_detail_screen.dart';
import 'package:sirati/features/dashboard/presentation/education_screen.dart';
import 'package:sirati/features/dashboard/presentation/gallery_screen.dart';
import 'package:sirati/features/dashboard/presentation/history_screen.dart';
import 'package:sirati/features/dashboard/presentation/home_screen.dart';
import 'package:sirati/features/dashboard/presentation/not_found_screen.dart';
import 'package:sirati/features/dashboard/presentation/premium_gate_screen.dart';
import 'package:sirati/features/dashboard/presentation/splash_screen.dart';
import 'package:sirati/features/jobs/presentation/job_news_screen.dart';
import 'package:sirati/features/settings/presentation/notifications_screen.dart';
import 'package:sirati/features/settings/presentation/privacy_policy_screen.dart';
import 'package:sirati/features/settings/presentation/settings_screen.dart';
import 'package:sirati/shared/models/cv_target_job.dart';

/// Composition root: maps route names onto feature screens.
void registerSiratiRouteWidgets() {
  AppRouter.registerWidgets(_widgetFor);
}

Widget _widgetFor(ParsedRoute parsed) {
  switch (parsed.name) {
    case AppRoutes.splash:
      return const SplashScreen();
    case AppRoutes.home:
      return const HomeScreen();
    case AppRoutes.login:
      return const LoginScreen();
    case AppRoutes.register:
      return const RegisterScreen();
    case AppRoutes.history:
      return const HistoryScreen();
    case AppRoutes.createCv:
      final job = parsed.arguments;
      return CvGeneratorScreen(targetJob: job is CvTargetJob ? job : null);
    case AppRoutes.cvBuilder:
      return const CvBuilderScreen();
    case AppRoutes.myCvs:
      return const HomeScreen(initialIndex: 1);
    case AppRoutes.education:
      return const EducationScreen();
    case AppRoutes.jobNews:
      return const JobNewsScreen();
    case AppRoutes.privacy:
      return const PrivacyPolicyScreen();
    case AppRoutes.settings:
      return const SettingsScreen();
    case AppRoutes.notifications:
      return const NotificationsScreen();
    case AppRoutes.premium:
      return const PremiumGateScreen();
    case AppRoutes.gallery:
      return const ComponentGalleryScreen();
    case AppRoutes.notFound:
      return const NotFoundScreen();
  }

  if (parsed.name.startsWith(AppRoutes.cvBuilderPrefix) &&
      parsed.stringId != null) {
    return CvBuilderScreen(cvId: parsed.stringId);
  }
  if (parsed.name.startsWith(AppRoutes.cvPrefix) && parsed.id != null) {
    return GeneratedCvLoaderScreen(cvId: parsed.id!);
  }
  if (parsed.name.startsWith(AppRoutes.analysisPrefix) && parsed.id != null) {
    return CvAnalysisLoaderScreen(analysisId: parsed.id!);
  }
  if (parsed.name.startsWith(AppRoutes.analysisPrefix)) {
    return const CvAnalysisScreen();
  }
  if (parsed.name.startsWith(AppRoutes.educationItemPrefix) &&
      parsed.id != null) {
    return EducationDetailScreen(id: parsed.id, fallback: const {});
  }
  return const NotFoundScreen();
}
