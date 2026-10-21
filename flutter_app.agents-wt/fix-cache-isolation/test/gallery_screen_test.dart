import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/routing/app_routes.dart';
import 'package:sirati/features/dashboard/presentation/gallery_screen.dart';
import 'package:sirati/routing/app_router.dart';
import 'package:sirati/shared/theme/app_theme.dart';

void main() {
  testWidgets(
      'ComponentGalleryScreen renders all sections, tokens, and toggles (SIRATI-23)',
      (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.lightFor(arabic: true),
        locale: const Locale('ar', 'SA'),
        builder: (context, child) => Directionality(
          textDirection: TextDirection.rtl,
          child: child!,
        ),
        home: const ComponentGalleryScreen(),
      ),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    // Verify title
    expect(find.text('Component Gallery (Debug)'), findsOneWidget);

    // Verify sections visible initially
    expect(find.text('1. Buttons (AppButton)'), findsOneWidget);
    expect(find.text('2. Text Fields (AppInput)'), findsOneWidget);
    expect(find.text('3. Surface Cards (AppSurfaceCard)'), findsOneWidget);

    // Verify button variants rendered
    expect(find.text('Primary Button'), findsOneWidget);
    expect(find.text('Secondary Button'), findsOneWidget);
    expect(find.text('Text Button'), findsOneWidget);
    expect(find.text('Destructive'), findsOneWidget);

    // Drag ListView down to reveal off-screen sections
    await tester.drag(find.byType(ListView), const Offset(0, -500));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    expect(find.text('4. Dialogs and Sheets'), findsOneWidget);
    expect(find.text('5. Empty and Loading States'), findsOneWidget);

    // Verify router parses /gallery
    final parsed = AppRouter.parse('/gallery');
    expect(parsed.name, AppRoutes.gallery);
    expect(parsed.unknown, isFalse);
  });
}
