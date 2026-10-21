import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/features/auth/presentation/login_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/auth_form_constraint.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    const channel =
        MethodChannel('plugins.it_nomads.com/flutter_secure_storage');
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (call) async => null);
  });

  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
      const MethodChannel('plugins.it_nomads.com/flutter_secure_storage'),
      null,
    );
  });

  testWidgets('login centers the brand header and start-aligns fields',
      (tester) async {
    await tester.pumpWidget(ProviderScope(
      child: MaterialApp(
        theme: AppTheme.light,
        locale: const Locale('en', 'US'),
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        home: const LoginScreen(),
      ),
    ));

    expect(find.byType(AuthFormConstraint), findsOneWidget);
    expect(find.text('Welcome to Sirati'), findsOneWidget);
    expect(find.text('Sign in to continue'), findsOneWidget);

    final title = tester.widget<Text>(find.text('Welcome to Sirati'));
    expect(title.textAlign, TextAlign.center);

    final subtitle = tester.widget<Text>(find.text('Sign in to continue'));
    expect(subtitle.textAlign, TextAlign.center);

    final emailLabel = tester.widget<Text>(find.text('Email'));
    expect(emailLabel.textAlign, TextAlign.start);
  });
}
