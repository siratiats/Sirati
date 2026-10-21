import 'dart:async';
import 'dart:ui';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_crashlytics/firebase_crashlytics.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sentry_flutter/sentry_flutter.dart';
import 'package:sirati/core/errors/app_crash_view.dart';
import 'package:sirati/core/flavors/app_flavor.dart';
import 'package:sirati/core/flavors/flavor_banner.dart';
import 'package:sirati/core/logging/app_log.dart';
import 'package:sirati/core/network/analytics_service.dart';
import 'package:sirati/core/routing/app_router.dart';
import 'package:sirati/core/routing/app_routes.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/app/sirati_route_table.dart';
import 'package:sirati/features/auth/data/auth_session_guard.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/services/notification_service.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/theme/app_theme_controller.dart';

/// Top-level background message handler — MUST be a top-level function.
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  try {
    if (Firebase.apps.isEmpty) {
      await Firebase.initializeApp();
    }
  } catch (e, st) {
    debugPrint('[FCM] background init failed: $e\n$st');
    return;
  }
  debugPrint('[FCM] Background message: ${message.messageId}');
}

Future<void> main() => startSiratiApp();

/// Shared bootstrap used by [main] and flavor entrypoints.
Future<void> startSiratiApp() async {
  AppFlavor.ensureInitialized();
  await SentryFlutter.init(
    (options) {
      options.dsn = const String.fromEnvironment('SENTRY_DSN');
      options.sendDefaultPii = false;
      options.maxRequestBodySize = MaxRequestBodySize.never;
      options.attachScreenshot = false;
      // View hierarchies can include text entered into CV fields.
      // ignore: experimental_member_use
      options.attachViewHierarchy = false;
      options.recordHttpBreadcrumbs = false;
      options.enablePrintBreadcrumbs = false;

      const environment = String.fromEnvironment('SENTRY_ENVIRONMENT');
      if (environment.isNotEmpty) {
        options.environment = environment;
      }

      const release = String.fromEnvironment('SENTRY_RELEASE');
      if (release.isNotEmpty) {
        options.release = release;
      }

      options.beforeSend = (event, hint) {
        if (event.exceptions != null) {
          for (final ex in event.exceptions!) {
            if (ex.value != null) {
              ex.value = AppLog.redact(ex.value!);
            }
          }
        }
        if (event.message?.formatted != null) {
          event.message =
              SentryMessage(AppLog.redact(event.message!.formatted));
        }
        return event;
      };
    },
    appRunner: () async {
      WidgetsFlutterBinding.ensureInitialized();
      registerSiratiRouteWidgets();
      AppRouter.install();
      _installAppLogErrorHandlers();
      ErrorWidget.builder = (details) {
        AppLog.event(
          AppLogEvent.uncaughtWidgetError,
          level: AppLogLevel.error,
          error: details.exception,
        );
        return const AppCrashView();
      };

      // Local prefs first — never block the UI shell on Firebase.
      try {
        await AppLocale.bootstrap();
      } catch (e, st) {
        debugPrint('[Boot] AppLocale failed: $e\n$st');
      }
      try {
        await AppThemeController.bootstrap();
      } catch (e, st) {
        debugPrint('[Boot] AppThemeController failed: $e\n$st');
      }

      final firebaseReady = await _initFirebaseStack();

      AuthSessionGuard.install(navigatorKey: siratiNavigatorKey);

      try {
        await SystemChrome.setPreferredOrientations([
          DeviceOrientation.portraitUp,
        ]);
        // Edge-to-edge on Android 15+; SafeArea on screens paints content insets.
        await SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
        SystemChrome.setSystemUIOverlayStyle(
          AppTheme.bootstrapSystemUiOverlayStyle(),
        );
      } catch (e, st) {
        debugPrint('[Boot] SystemChrome failed: $e\n$st');
      }

      // Always launch UI — Codemagic previews and devices without Firebase config
      // must still open SplashScreen instead of dying on a white/native shell.
      final initialRoute = AppRouter.resolveInitialRoute();
      runApp(ProviderScope(child: SiratiApp(initialRoute: initialRoute)));

      if (kDebugMode) {
        debugPrint(
          firebaseReady
              ? '[Boot] Firebase ready'
              : '[Boot] Running without Firebase (push/analytics limited)',
        );
      }
    },
  );
}

/// Returns true when Firebase + messaging hooks initialized successfully.
Future<bool> _initFirebaseStack() async {
  try {
    if (Firebase.apps.isEmpty) {
      await Firebase.initializeApp();
    }
  } catch (e, st) {
    // Missing GoogleService-Info.plist (iOS) / google-services.json (Android)
    // must not kill the process — App Preview would "open then close".
    debugPrint('[Firebase] initializeApp failed: $e\n$st');
    return false;
  }

  try {
    _installCrashlyticsErrorHandlers();
  } catch (e, st) {
    debugPrint('[Firebase] Crashlytics setup failed: $e\n$st');
  }

  try {
    await AnalyticsService.initialize();
    unawaited(AnalyticsService.setAppLanguage(AppLocale.languageCode.value));
    unawaited(AnalyticsService.setThemeMode(
      AppThemeController.themeMode.value.name,
    ));
  } catch (e, st) {
    debugPrint('[Firebase] Analytics setup failed: $e\n$st');
  }

  try {
    FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);
    await NotificationService.instance.initialize();
    await NotificationService.instance.handleTerminatedLaunchNotification();
  } catch (e, st) {
    debugPrint('[Firebase] Messaging setup failed: $e\n$st');
  }

  return true;
}

void _installAppLogErrorHandlers() {
  final previousFlutterOnError = FlutterError.onError;
  FlutterError.onError = (details) {
    final sanitizedException = AppLog.sanitizeError(details.exception);
    final sanitizedMessage = AppLog.redact(details.exceptionAsString());
    final sanitizedDetails = FlutterErrorDetails(
      exception: sanitizedException,
      stack: details.stack,
      library: details.library,
      context: details.context,
      informationCollector: () => [
        DiagnosticsNode.message(sanitizedMessage),
      ],
      silent: details.silent,
    );
    AppLog.event(
      AppLogEvent.uncaughtWidgetError,
      level: AppLogLevel.error,
      error: sanitizedException,
      stackTrace: details.stack,
    );
    previousFlutterOnError?.call(sanitizedDetails);
  };

  final previousPlatformOnError = PlatformDispatcher.instance.onError;
  PlatformDispatcher.instance.onError = (error, stack) {
    final sanitizedError = AppLog.sanitizeError(error);
    AppLog.event(
      AppLogEvent.platformError,
      level: AppLogLevel.error,
      error: sanitizedError,
      stackTrace: stack,
    );
    return previousPlatformOnError?.call(sanitizedError, stack) ?? false;
  };
}

void _installCrashlyticsErrorHandlers() {
  unawaited(FirebaseCrashlytics.instance.setCrashlyticsCollectionEnabled(true));

  final previousFlutterOnError = FlutterError.onError;
  FlutterError.onError = (details) {
    final sanitizedException = AppLog.sanitizeError(details.exception);
    final sanitizedMessage = AppLog.redact(details.exceptionAsString());
    final sanitizedDetails = FlutterErrorDetails(
      exception: sanitizedException,
      stack: details.stack,
      library: details.library,
      context: details.context,
      informationCollector: () => [
        DiagnosticsNode.message(sanitizedMessage),
      ],
      silent: details.silent,
    );
    unawaited(
        FirebaseCrashlytics.instance.recordFlutterFatalError(sanitizedDetails));
    previousFlutterOnError?.call(sanitizedDetails);
  };

  final previousPlatformOnError = PlatformDispatcher.instance.onError;
  PlatformDispatcher.instance.onError = (error, stack) {
    final sanitizedError = AppLog.sanitizeError(error);
    unawaited(
      FirebaseCrashlytics.instance
          .recordError(sanitizedError, stack, fatal: true),
    );
    return previousPlatformOnError?.call(sanitizedError, stack) ?? false;
  };
}

class SiratiApp extends StatelessWidget {
  const SiratiApp({super.key, this.initialRoute});

  final String? initialRoute;

  @override
  Widget build(BuildContext context) {
    registerSiratiRouteWidgets();
    return ValueListenableBuilder<String>(
      valueListenable: AppLocale.languageCode,
      builder: (context, language, _) {
        return ValueListenableBuilder<ThemeMode>(
          valueListenable: AppThemeController.themeMode,
          builder: (context, themeMode, _) {
            return MaterialApp(
              navigatorKey: siratiNavigatorKey,
              title: language == 'en' ? 'Sirati' : 'سيرتي',
              debugShowCheckedModeBanner: false,
              onGenerateTitle: (context) {
                final l10n = AppLocalizations.of(context);
                return l10n.appTitle;
              },
              theme: AppTheme.lightFor(arabic: language != 'en'),
              darkTheme: AppTheme.darkFor(arabic: language != 'en'),
              themeMode: themeMode,
              locale: AppLocale.locale,
              navigatorObservers: AnalyticsService.navigatorObservers,
              supportedLocales: const [
                Locale('ar', 'SA'),
                Locale('en', 'US'),
              ],
              localizationsDelegates: const [
                AppLocalizations.delegate,
                GlobalMaterialLocalizations.delegate,
                GlobalWidgetsLocalizations.delegate,
                GlobalCupertinoLocalizations.delegate,
              ],
              builder: (context, child) {
                final sirati = context.sirati;
                final brightness = Theme.of(context).brightness;
                final overlayStyle =
                    AppTheme.systemUiOverlayStyle(sirati, brightness);
                return AnnotatedRegion<SystemUiOverlayStyle>(
                  value: overlayStyle,
                  child: FlavorBanner(
                    child: LayoutBuilder(
                      builder: (context, constraints) {
                        final platform = Theme.of(context).platform;
                        final isNativeMobile = !kIsWeb &&
                            (platform == TargetPlatform.android ||
                                platform == TargetPlatform.iOS);
                        final width = isNativeMobile
                            ? constraints.maxWidth
                            : (constraints.maxWidth > 480
                                ? 430.0
                                : constraints.maxWidth);

                        return ColoredBox(
                          color: sirati.background,
                          child: Align(
                            alignment: Alignment.topCenter,
                            child: SizedBox(
                              width: width,
                              height: constraints.maxHeight,
                              child: MediaQuery.withClampedTextScaling(
                                minScaleFactor: 0.8,
                                maxScaleFactor: 2.0,
                                child: Directionality(
                                  textDirection: AppLocale.direction(context),
                                  child: child ?? const SizedBox.shrink(),
                                ),
                              ),
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                );
              },
              initialRoute: initialRoute ?? AppRoutes.splash,
              onGenerateInitialRoutes: AppRouter.onGenerateInitialRoutes,
              onGenerateRoute: AppRouter.onGenerateRoute,
              onUnknownRoute: AppRouter.onUnknownRoute,
            );
          },
        );
      },
    );
  }
}
