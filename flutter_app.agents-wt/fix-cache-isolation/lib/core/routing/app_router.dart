import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'package:sirati/core/routing/app_routes.dart';
import 'package:sirati/core/routing/entitlement_store.dart';

/// Feature screens register themselves so `core/` never imports `features/`.
typedef AppRouteWidgetBuilder = Widget Function(ParsedRoute parsed);

final GlobalKey<NavigatorState> siratiNavigatorKey =
    GlobalKey<NavigatorState>();

/// Declarative router with deep-link parsing and entitlement guards.
class AppRouter {
  AppRouter._();

  static final _DeepLinkObserver _observer = _DeepLinkObserver();
  static bool _bindingInstalled = false;
  static AppRouteWidgetBuilder? _widgets;

  static void registerWidgets(AppRouteWidgetBuilder builder) {
    _widgets = builder;
  }

  /// Consumed by [SplashScreen] after auth so cold-start deep links survive
  /// the session bootstrap `pushAndRemoveUntil`.
  static String? pendingLocation;

  /// Listen for warm-start OS route pushes (`sirati://app/...`).
  static void install() {
    if (_bindingInstalled) return;
    _bindingInstalled = true;
    WidgetsBinding.instance.addObserver(_observer);
  }

  static String consumePendingLocation() {
    final value = pendingLocation;
    pendingLocation = null;
    return value ?? AppRoutes.home;
  }

  /// After login / splash session restore: home under the stack, then deep link.
  static void openAfterAuth(BuildContext context) {
    final pending = pendingLocation;
    pendingLocation = null;
    if (pending == null ||
        pending == AppRoutes.splash ||
        pending == AppRoutes.home) {
      Navigator.of(context).pushNamedAndRemoveUntil(
        AppRoutes.home,
        (route) => false,
      );
      return;
    }
    if (pending == AppRoutes.myCvs) {
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(
          settings: const RouteSettings(name: AppRoutes.myCvs),
          builder: (_) => _widgetFor(const ParsedRoute(name: AppRoutes.myCvs)),
        ),
        (route) => false,
      );
      return;
    }
    Navigator.of(context).pushNamedAndRemoveUntil(
      AppRoutes.home,
      (route) => false,
    );
    WidgetsBinding.instance.addPostFrameCallback((_) {
      siratiNavigatorKey.currentState?.pushNamed(pending);
    });
  }

  static String resolveInitialRoute() {
    final preview = _webPreviewRoute();
    if (preview != null) return preview;

    final raw = WidgetsBinding.instance.platformDispatcher.defaultRouteName;
    if (raw.isEmpty || raw == '/') return AppRoutes.splash;

    final parsed = parse(raw);
    if (parsed.unknown) {
      pendingLocation = AppRoutes.notFound;
      return AppRoutes.splash;
    }
    if (parsed.name != AppRoutes.splash) return parsed.name;
    return AppRoutes.splash;
  }

  static List<Route<dynamic>> onGenerateInitialRoutes(String initialRoute) {
    final preview = _webPreviewRoute();
    if (preview != null) {
      return [_page(_widgetFor(parse(preview)), preview)];
    }

    final parsed = parse(initialRoute);
    if (parsed.name == AppRoutes.splash || parsed.unknown) {
      if (parsed.unknown &&
          initialRoute != '/' &&
          initialRoute != AppRoutes.splash) {
        pendingLocation = AppRoutes.notFound;
      }
      return [
        _page(_widgetFor(const ParsedRoute(name: AppRoutes.splash)),
            AppRoutes.splash),
      ];
    }

    pendingLocation = parsed.name;
    return [
      _page(_widgetFor(const ParsedRoute(name: AppRoutes.splash)),
          AppRoutes.splash),
    ];
  }

  /// Warm-start / subsequent OS deep link.
  static bool handleIncoming(String location) {
    final parsed = parse(location);
    final name = parsed.unknown ? AppRoutes.notFound : parsed.name;
    if (name == AppRoutes.splash) return false;

    final nav = siratiNavigatorKey.currentState;
    if (nav == null) {
      pendingLocation = name;
      return true;
    }
    nav.pushNamed(name);
    return true;
  }

  static Route<dynamic> onGenerateRoute(RouteSettings settings) {
    final parsed = parse(settings.name ?? AppRoutes.splash);
    if (parsed.unknown) {
      return _page(
        _widgetFor(const ParsedRoute(name: AppRoutes.notFound)),
        AppRoutes.notFound,
      );
    }
    if (parsed.requiresEntitlement && !EntitlementStore.hasPremium) {
      return _page(
        _widgetFor(const ParsedRoute(name: AppRoutes.premium)),
        AppRoutes.premium,
      );
    }
    return _page(
      _widgetFor(parsed.withArguments(settings.arguments)),
      parsed.name,
    );
  }

  static Route<dynamic> onUnknownRoute(RouteSettings settings) {
    return _page(
      _widgetFor(const ParsedRoute(name: AppRoutes.notFound)),
      AppRoutes.notFound,
    );
  }

  static ParsedRoute parse(String location) {
    var path = location.trim();
    if (path.isEmpty) path = AppRoutes.splash;

    if (path.contains('://')) {
      final uri = Uri.tryParse(path);
      if (uri != null) {
        if (uri.scheme == 'sirati') {
          path = uri.path.isEmpty ? '/${uri.host}' : uri.path;
          if (path == '/app' || path == 'app') path = AppRoutes.splash;
          if (!path.startsWith('/')) path = '/$path';
          if (uri.host == 'app' && uri.path.isNotEmpty) {
            path = uri.path;
          }
        } else {
          path = uri.path;
        }
      }
    }

    if (!path.startsWith('/')) path = '/$path';
    if (path.length > 1 && path.endsWith('/')) {
      path = path.substring(0, path.length - 1);
    }

    switch (path) {
      case AppRoutes.splash:
      case AppRoutes.home:
      case AppRoutes.login:
      case AppRoutes.register:
      case AppRoutes.history:
      case AppRoutes.createCv:
      case AppRoutes.myCvs:
      case AppRoutes.education:
      case AppRoutes.jobNews:
      case AppRoutes.privacy:
      case AppRoutes.settings:
      case AppRoutes.notifications:
      case AppRoutes.gallery:
      case AppRoutes.notFound:
        return ParsedRoute(name: path);
      case AppRoutes.premium:
        return const ParsedRoute(
          name: AppRoutes.premium,
          requiresEntitlement: true,
        );
      case AppRoutes.cvBuilder:
        return const ParsedRoute(name: AppRoutes.cvBuilder);
    }

    if (path.startsWith(AppRoutes.cvBuilderPrefix)) {
      final builderId = path.substring(AppRoutes.cvBuilderPrefix.length);
      if (builderId.isNotEmpty) {
        return ParsedRoute(name: path, stringId: builderId);
      }
    }

    final cvId = _idAfter(path, AppRoutes.cvPrefix);
    if (cvId != null) {
      return ParsedRoute(name: AppRoutes.cv(cvId), id: cvId);
    }
    final analysisId = _idAfter(path, AppRoutes.analysisPrefix);
    if (analysisId != null) {
      return ParsedRoute(name: AppRoutes.analysis(analysisId), id: analysisId);
    }
    final educationId = _idAfter(path, AppRoutes.educationItemPrefix);
    if (educationId != null) {
      return ParsedRoute(
        name: AppRoutes.educationItem(educationId),
        id: educationId,
      );
    }

    return ParsedRoute(name: path, unknown: true);
  }

  static Widget _widgetFor(ParsedRoute parsed) {
    final builder = _widgets;
    assert(
      builder != null,
      'AppRouter.registerWidgets has not been called',
    );
    if (builder == null) {
      throw StateError('AppRouter.registerWidgets has not been called');
    }
    return builder(parsed);
  }

  static String? _webPreviewRoute() {
    if (!kIsWeb) return null;
    return _previewScreen(Uri.base.queryParameters['screen']);
  }

  static String? _previewScreen(String? screen) {
    return switch (screen) {
      'register' => AppRoutes.register,
      'create-cv' => AppRoutes.createCv,
      'mycvs' => AppRoutes.myCvs,
      'education' => AppRoutes.education,
      'history' => AppRoutes.history,
      'job-news' => AppRoutes.jobNews,
      'privacy' => AppRoutes.privacy,
      'home' => AppRoutes.home,
      _ => null,
    };
  }

  static int? _idAfter(String path, String prefix) {
    if (!path.startsWith(prefix)) return null;
    return int.tryParse(path.substring(prefix.length));
  }

  static MaterialPageRoute<dynamic> _page(Widget child, String name) {
    return MaterialPageRoute<dynamic>(
      settings: RouteSettings(name: name),
      builder: (_) => child,
    );
  }
}

class _DeepLinkObserver extends WidgetsBindingObserver {
  @override
  Future<bool> didPushRoute(String route) async {
    return AppRouter.handleIncoming(route);
  }

  @override
  Future<bool> didPushRouteInformation(
      RouteInformation routeInformation) async {
    return AppRouter.handleIncoming(routeInformation.uri.toString());
  }
}
