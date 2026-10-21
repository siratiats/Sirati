import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/theme/app_theme_controller.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  final stored = <String, String>{};

  setUp(() {
    AppThemeController.resetForTest();
    stored.clear();
    const channel =
        MethodChannel('plugins.it_nomads.com/flutter_secure_storage');
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (call) async {
      switch (call.method) {
        case 'read':
          final key = (call.arguments as Map)['key'] as String?;
          return stored[key];
        case 'write':
          final args = call.arguments as Map;
          stored[args['key'] as String] = args['value'] as String;
          return null;
        default:
          return null;
      }
    });
  });

  tearDown(() {
    AppThemeController.resetForTest();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
      const MethodChannel('plugins.it_nomads.com/flutter_secure_storage'),
      null,
    );
  });

  test('bootstrap restores persisted dark mode before first frame', () async {
    stored['sirati_theme_mode'] = 'dark';
    await AppThemeController.bootstrap();
    expect(AppThemeController.themeMode.value, ThemeMode.dark);
  });

  test('setMode persists light / dark / system', () async {
    await AppThemeController.setMode(ThemeMode.light);
    expect(stored['sirati_theme_mode'], 'light');
    await AppThemeController.setMode(ThemeMode.dark);
    expect(stored['sirati_theme_mode'], 'dark');
    await AppThemeController.setMode(ThemeMode.system);
    expect(stored['sirati_theme_mode'], 'system');
  });

  testWidgets('switching theme keeps in-progress text field state',
      (tester) async {
    final controller = TextEditingController(text: 'draft title');

    await tester.pumpWidget(
      ValueListenableBuilder<ThemeMode>(
        valueListenable: AppThemeController.themeMode,
        builder: (context, mode, _) {
          return MaterialApp(
            theme: AppTheme.light,
            darkTheme: AppTheme.dark,
            themeMode: mode,
            home: Scaffold(
              body: TextField(controller: controller),
            ),
          );
        },
      ),
    );

    expect(find.text('draft title'), findsOneWidget);
    await AppThemeController.setMode(ThemeMode.dark);
    await tester.pump();
    expect(find.text('draft title'), findsOneWidget);
    expect(controller.text, 'draft title');
  });
}
