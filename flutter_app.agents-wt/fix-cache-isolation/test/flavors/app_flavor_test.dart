import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/flavors/app_flavor.dart';
import 'package:sirati/core/flavors/flavor_banner.dart';
import 'package:sirati/core/network/api_config.dart';

void main() {
  tearDown(AppFlavor.resetForTest);

  test('dev and staging show banners; prod does not', () {
    AppFlavor.bootstrap(AppFlavor.dev);
    expect(AppFlavor.current.showBanner, isTrue);
    expect(AppFlavor.current.label, 'DEV');

    AppFlavor.bootstrap(AppFlavor.staging);
    expect(AppFlavor.current.showBanner, isTrue);
    expect(AppFlavor.current.label, 'STAGING');

    AppFlavor.bootstrap(AppFlavor.prod);
    expect(AppFlavor.current.showBanner, isFalse);
    expect(AppFlavor.current.isProd, isTrue);
  });

  testWidgets('FlavorBanner paints a Banner in non-prod and is a no-op in prod',
      (tester) async {
    AppFlavor.bootstrap(AppFlavor.dev);
    await tester.pumpWidget(
      const MaterialApp(
        debugShowCheckedModeBanner: false,
        home: FlavorBanner(child: Text('body')),
      ),
    );
    expect(find.byType(Banner), findsOneWidget);
    expect(find.text('body'), findsOneWidget);

    AppFlavor.bootstrap(AppFlavor.staging);
    await tester.pumpWidget(
      const MaterialApp(
        debugShowCheckedModeBanner: false,
        home: FlavorBanner(child: Text('body')),
      ),
    );
    expect(find.byType(Banner), findsOneWidget);

    AppFlavor.bootstrap(AppFlavor.prod);
    await tester.pumpWidget(
      const MaterialApp(
        debugShowCheckedModeBanner: false,
        home: FlavorBanner(child: Text('body')),
      ),
    );
    expect(find.byType(Banner), findsNothing);
    expect(find.text('body'), findsOneWidget);
  });

  test('API base URL is injected via dart-define, not a committed secret', () {
    expect(ApiConfig.baseUrl, isNot(contains('sk-')));
    expect(ApiConfig.baseUrl, isNot(contains('Bearer ')));
    expect(ApiConfig.baseUrl.startsWith('http'), isTrue);
  });
}
