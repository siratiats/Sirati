import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/screen_header.dart';

void main() {
  group('ScreenHeader & ProfileAvatar Widget Tests', () {
    Widget buildSubject({
      required bool english,
      required String title,
      IconData? icon,
      String? avatarLabel,
      VoidCallback? onAvatarTap,
      VoidCallback? onNotifications,
      int unreadCount = 0,
      String? subtitle,
      String? status,
    }) {
      return MaterialApp(
        theme: AppTheme.light,
        home: Scaffold(
          body: ScreenHeader(
            english: english,
            title: title,
            icon: icon,
            avatarLabel: avatarLabel,
            onAvatarTap: onAvatarTap,
            onNotifications: onNotifications ?? () {},
            unreadCount: unreadCount,
            subtitle: subtitle,
            status: status,
          ),
        ),
      );
    }

    testWidgets('renders IconData when icon is passed', (tester) async {
      await tester.pumpWidget(buildSubject(
        english: false,
        title: 'سيرتي الذاتية',
        icon: Icons.description_rounded,
      ));
      await tester.pump();

      expect(find.byIcon(Icons.description_rounded), findsOneWidget);
      expect(find.text('سيرتي الذاتية'), findsOneWidget);
    });

    testWidgets('renders text label when only avatarLabel is passed', (tester) async {
      await tester.pumpWidget(buildSubject(
        english: true,
        title: 'Dashboard',
        avatarLabel: 'JD',
      ));
      await tester.pump();

      expect(find.text('JD'), findsOneWidget);
      expect(find.byType(Icon), findsWidgets); // Notification bell icon exists
    });

    testWidgets('falls back to initial when neither icon nor avatarLabel is passed', (tester) async {
      await tester.pumpWidget(buildSubject(
        english: false,
        title: 'أهلاً، محمد',
      ));
      await tester.pump();

      // Strips 'أهلاً، ' greeting and extracts 'M' / 'م'
      expect(find.text('م'), findsOneWidget);
    });

    testWidgets('triggers onAvatarTap when avatar circle is tapped', (tester) async {
      var tapped = false;
      await tester.pumpWidget(buildSubject(
        english: true,
        title: 'Profile',
        icon: Icons.person_rounded,
        onAvatarTap: () => tapped = true,
      ));
      await tester.pump();

      await tester.tap(find.byIcon(Icons.person_rounded));
      await tester.pump();

      expect(tapped, isTrue);
    });

    testWidgets('triggers onNotifications when bell button is tapped', (tester) async {
      var tapped = false;
      await tester.pumpWidget(buildSubject(
        english: true,
        title: 'Home',
        icon: Icons.person_rounded,
        onNotifications: () => tapped = true,
        unreadCount: 3,
      ));
      await tester.pump();

      expect(find.text('3'), findsOneWidget);
      await tester.tap(find.byIcon(Icons.notifications_none_rounded));
      await tester.pump();

      expect(tapped, isTrue);
    });

    testWidgets('renders page-specific icons for each of the 4 main application sections', (tester) async {
      const testCases = [
        (Icons.person_rounded, 'أهلاً، فلان', false),
        (Icons.description_rounded, 'سيرتي الذاتية', false),
        (Icons.school_rounded, 'التعليم', false),
        (Icons.work_rounded, 'أخبار الوظائف', false),
        (Icons.person_rounded, 'Hello, User', true),
        (Icons.description_rounded, 'My CVs', true),
        (Icons.school_rounded, 'Education', true),
        (Icons.work_rounded, 'Job News', true),
      ];

      for (final (icon, title, english) in testCases) {
        await tester.pumpWidget(buildSubject(
          english: english,
          title: title,
          icon: icon,
        ));
        await tester.pump();

        expect(find.byIcon(icon), findsOneWidget, reason: 'Failed for title: $title');
        expect(find.text(title), findsOneWidget);
      }
    });
  });
}
