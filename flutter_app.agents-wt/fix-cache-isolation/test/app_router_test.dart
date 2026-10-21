import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/routing/app_routes.dart';
import 'package:sirati/core/routing/entitlement_store.dart';
import 'package:sirati/features/app/sirati_route_table.dart';
import 'package:sirati/features/ats_scanner/presentation/cv_analysis_loader_screen.dart';
import 'package:sirati/features/dashboard/presentation/not_found_screen.dart';
import 'package:sirati/features/dashboard/presentation/premium_gate_screen.dart';
import 'package:sirati/routing/app_router.dart';
import 'package:sirati/shared/theme/app_theme.dart';

void main() {
  setUpAll(registerSiratiRouteWidgets);

  tearDown(() {
    AppRouter.pendingLocation = null;
    EntitlementStore.hasPremium = false;
  });

  group('AppRouter.parse', () {
    test('named routes', () {
      expect(AppRouter.parse('/home').name, AppRoutes.home);
      expect(AppRouter.parse('/login').unknown, isFalse);
      expect(AppRouter.parse('/create-cv').name, AppRoutes.createCv);
      expect(AppRouter.parse('/mycvs').name, AppRoutes.myCvs);
    });

    test('strips trailing slash', () {
      expect(AppRouter.parse('/home/').name, AppRoutes.home);
    });

    test('parses CV and analysis ids', () {
      final cv = AppRouter.parse('/cv/42');
      expect(cv.id, 42);
      expect(cv.name, AppRoutes.cv(42));
      expect(cv.unknown, isFalse);

      final analysis = AppRouter.parse('/analysis/7');
      expect(analysis.id, 7);
      expect(analysis.name, AppRoutes.analysis(7));
    });

    test('sirati://app deep links', () {
      expect(AppRouter.parse('sirati://app').name, AppRoutes.splash);
      expect(AppRouter.parse('sirati://app/home').name, AppRoutes.home);
      expect(AppRouter.parse('sirati://app/cv/9').id, 9);
      expect(AppRouter.parse('sirati://app/cv/9').name, AppRoutes.cv(9));
    });

    test('unknown routes are flagged, never crash', () {
      final parsed = AppRouter.parse('/does-not-exist');
      expect(parsed.unknown, isTrue);
    });

    test('premium is entitlement-gated', () {
      final parsed = AppRouter.parse('/premium');
      expect(parsed.requiresEntitlement, isTrue);
      expect(parsed.unknown, isFalse);
    });
  });

  group('AppRouter.onGenerateRoute', () {
    testWidgets('unknown path builds NotFoundScreen', (tester) async {
      late Route<dynamic> generated;
      await tester.pumpWidget(
        MaterialApp(
          locale: const Locale('en', 'US'),
          supportedLocales: const [Locale('en', 'US'), Locale('ar', 'SA')],
          theme: AppTheme.light,
          onGenerateRoute: (settings) {
            generated = AppRouter.onGenerateRoute(settings);
            return generated;
          },
          home: Builder(
            builder: (context) {
              return TextButton(
                onPressed: () => Navigator.of(context).pushNamed('/nope'),
                child: const Text('go'),
              );
            },
          ),
        ),
      );

      await tester.tap(find.text('go'));
      await tester.pumpAndSettle();

      expect(find.byType(NotFoundScreen), findsOneWidget);
      expect(find.text('Page not found'), findsOneWidget);
    });

    testWidgets('gated route without entitlement shows premium gate',
        (tester) async {
      EntitlementStore.hasPremium = false;
      await tester.pumpWidget(
        MaterialApp(
          locale: const Locale('en', 'US'),
          supportedLocales: const [Locale('en', 'US'), Locale('ar', 'SA')],
          theme: AppTheme.light,
          onGenerateRoute: AppRouter.onGenerateRoute,
          home: Builder(
            builder: (context) {
              return TextButton(
                onPressed: () => Navigator.of(context).pushNamed('/premium'),
                child: const Text('go'),
              );
            },
          ),
        ),
      );

      await tester.tap(find.text('go'));
      await tester.pumpAndSettle();

      expect(find.byType(PremiumGateScreen), findsOneWidget);
      expect(find.text('Premium required'), findsOneWidget);
    });

    testWidgets('analysis deep link routes to CvAnalysisLoaderScreen with id',
        (tester) async {
      late Widget built;
      await tester.pumpWidget(
        Builder(
          builder: (context) {
            final route = AppRouter.onGenerateRoute(
              const RouteSettings(name: '/analysis/42'),
            ) as MaterialPageRoute;
            built = route.builder(context);
            return const SizedBox();
          },
        ),
      );
      expect(built, isA<CvAnalysisLoaderScreen>());
      expect((built as CvAnalysisLoaderScreen).analysisId, 42);
    });
  });
}
