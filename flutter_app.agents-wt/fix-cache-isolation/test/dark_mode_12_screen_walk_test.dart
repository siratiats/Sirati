import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/ats_scanner/presentation/cv_analysis_screen.dart';
import 'package:sirati/features/auth/presentation/forgot_password_screen.dart';
import 'package:sirati/features/cv_builder/presentation/my_cvs_screen.dart';
import 'package:sirati/features/dashboard/presentation/education_detail_screen.dart';
import 'package:sirati/features/dashboard/presentation/education_screen.dart';
import 'package:sirati/features/dashboard/presentation/history_screen.dart';
import 'package:sirati/features/dashboard/presentation/home_screen.dart';
import 'package:sirati/features/jobs/presentation/job_news_screen.dart';
import 'package:sirati/features/jobs/presentation/widgets/job_title_display.dart';
import 'package:sirati/features/settings/presentation/notifications_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/job_title.dart';
import 'package:sirati/shared/services/mobile_content_service.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/app_list_tile.dart';
import 'package:sirati/shared/widgets/job_title_picker_field.dart';

/// Exhaustive dark-mode walk across all 12 audited screens and shared components (SIRATI-80).
///
/// Per AGENTS.md Rule 2: Strict quality gates (zero tolerance slack).
/// Every contrast evaluation is a strict mathematical assertion: `expect(ratio, greaterThanOrEqualTo(4.5))`.
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    AppLocale.languageCode.value = 'en';
    MobileContentService.invalidate();
    SharedPreferences.setMockInitialValues({});
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

  Widget darkSubject(Widget child, {bool arabic = false}) {
    // Several audited screens read their data through Riverpod providers.
    return ProviderScope(
        child: MaterialApp(
      theme: AppTheme.darkFor(arabic: arabic),
      locale: Locale(arabic ? 'ar' : 'en'),
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      home: Scaffold(
        backgroundColor: SiratiColors.dark.background,
        body: child,
      ),
    ));
  }

  void assertDarkAaContrast(
    Color text, {
    Color? surface,
    required String contextDescription,
  }) {
    final bg = surface ?? SiratiColors.dark.surface;
    final ratio = AppContrast.ratio(text, bg);

    // Must strictly pass WCAG AA (4.5:1)
    expect(
      ratio,
      greaterThanOrEqualTo(4.5),
      reason:
          '$contextDescription: ratio ${ratio.toStringAsFixed(2)} must be >= 4.5:1 (fg: $text, bg: $bg)',
    );

    // Must never resolve to light palette colors
    expect(
      text,
      isNot(SiratiColors.light.textPrimary),
      reason:
          '$contextDescription: must not resolve to light textPrimary (0xFF171D1B)',
    );
    expect(
      text,
      isNot(SiratiColors.light.textSecondary),
      reason:
          '$contextDescription: must not resolve to light textSecondary (0xFF3C4947)',
    );
  }

  group('SIRATI-80: Core Type Ramp Dark Mode Contrast Invariants', () {
    test(
        'all AppTextStyles ramp helpers resolve to dark palette with AA contrast',
        () {
      const c = SiratiColors.dark;
      final styles = <String, TextStyle>{
        'titleLg': AppTextStyles.titleLg(c),
        'titleMd': AppTextStyles.titleMd(c),
        'titleSm': AppTextStyles.titleSm(c),
        'bodyMd': AppTextStyles.bodyMd(c),
        'bodySm': AppTextStyles.bodySm(c),
        'labelMd': AppTextStyles.labelMd(c),
        'displayStat': AppTextStyles.displayStat(c),
      };

      for (final entry in styles.entries) {
        final color = entry.value.color!;
        assertDarkAaContrast(
          color,
          surface: c.surface,
          contextDescription: '${entry.key} on dark surface',
        );
        assertDarkAaContrast(
          color,
          surface: c.background,
          contextDescription: '${entry.key} on dark background',
        );
      }
    });
  });

  group('SIRATI-80: 12 Audited Screens & Shared Components Dark Mode Walk', () {
    testWidgets('1. AppListTile renders title and subtitle with AA contrast',
        (tester) async {
      await tester.pumpWidget(
        darkSubject(
          AppListTile(
            title: 'Audit Settings Tile',
            subtitle: 'Clear description of feature behavior',
            onTap: () {},
          ),
        ),
      );
      await tester.pump();

      final titleWidget = tester.widget<Text>(find.text('Audit Settings Tile'));
      final subtitleWidget = tester
          .widget<Text>(find.text('Clear description of feature behavior'));

      expect(titleWidget.style?.color, SiratiColors.dark.textPrimary);
      expect(subtitleWidget.style?.color, SiratiColors.dark.textSecondary);

      assertDarkAaContrast(
        titleWidget.style!.color!,
        contextDescription: 'AppListTile title',
      );
      assertDarkAaContrast(
        subtitleWidget.style!.color!,
        contextDescription: 'AppListTile subtitle',
      );
    });

    testWidgets('2. JobTitleDisplay renders bilingual titles with AA contrast',
        (tester) async {
      await tester.pumpWidget(
        darkSubject(
          const JobTitleDisplay(
            title: 'مهندس برمجيات (Software Engineer)',
          ),
        ),
      );
      await tester.pump();

      final primaryText = tester.widget<Text>(find.text('مهندس برمجيات'));
      final secondaryText = tester.widget<Text>(find.text('Software Engineer'));

      expect(primaryText.style?.color, SiratiColors.dark.textPrimary);
      expect(secondaryText.style?.color, SiratiColors.dark.textSecondary);

      assertDarkAaContrast(
        primaryText.style!.color!,
        contextDescription: 'JobTitleDisplay primary title',
      );
      assertDarkAaContrast(
        secondaryText.style!.color!,
        contextDescription: 'JobTitleDisplay secondary title',
      );
    });

    testWidgets(
        '3. JobTitlePickerField bottom sheet header renders with AA contrast',
        (tester) async {
      final titles = [
        const JobTitle(
          id: 1,
          slug: 'general-practitioner',
          nameAr: 'طبيب عام',
          nameEn: 'General Practitioner',
          category: 'healthcare',
          keywords: ['medicine', 'doctor'],
          sortOrder: 1,
        ),
      ];

      await tester.pumpWidget(
        darkSubject(
          JobTitlePickerField(
            titles: titles,
            value: null,
            onChanged: (_) {},
            english: false,
          ),
          arabic: true,
        ),
      );
      await tester.pump();

      // Tap picker to show bottom sheet
      await tester.tap(find.byType(InkWell));
      await tester.pumpAndSettle();

      final headerFinder = find.text('المسمى الوظيفي');
      expect(headerFinder, findsOneWidget);

      final headerText = tester.widget<Text>(headerFinder);
      expect(headerText.style?.color, SiratiColors.dark.textPrimary);

      assertDarkAaContrast(
        headerText.style!.color!,
        contextDescription: 'JobTitlePickerField modal header',
      );
    });

    testWidgets(
        '4. EducationDetailScreen renders title and body with AA contrast',
        (tester) async {
      await tester.pumpWidget(
        darkSubject(
          const EducationDetailScreen(
            id: null,
            fallback: {
              'title': 'ATS Optimization Guide',
              'body': 'This detailed guide explains ATS score invariants.',
            },
          ),
        ),
      );
      await tester.pump();

      final titleWidget =
          tester.widget<Text>(find.text('ATS Optimization Guide'));
      final bodyWidget = tester.widget<Text>(
          find.text('This detailed guide explains ATS score invariants.'));

      expect(titleWidget.style?.color, SiratiColors.dark.textPrimary);
      expect(bodyWidget.style?.color, SiratiColors.dark.textPrimary);

      assertDarkAaContrast(
        titleWidget.style!.color!,
        contextDescription: 'EducationDetailScreen title',
      );
      assertDarkAaContrast(
        bodyWidget.style!.color!,
        contextDescription: 'EducationDetailScreen body',
      );
    });

    testWidgets(
        '5. ForgotPasswordScreen renders instructions and labels with AA contrast',
        (tester) async {
      await tester.pumpWidget(
        darkSubject(const ForgotPasswordScreen()),
      );
      await tester.pump();

      final instructions = tester.widget<Text>(
          find.text('Enter your email and we will send a 6-digit reset code.'));
      final emailLabel = tester.widget<Text>(find.text('Email Address'));

      expect(instructions.style?.color, SiratiColors.dark.textPrimary);
      expect(emailLabel.style?.color, SiratiColors.dark.textSecondary);

      assertDarkAaContrast(
        instructions.style!.color!,
        contextDescription: 'ForgotPasswordScreen instructions',
      );
      assertDarkAaContrast(
        emailLabel.style!.color!,
        contextDescription: 'ForgotPasswordScreen email label',
      );
    });

    testWidgets(
        '6. EducationScreen renders hub, topic, and CTA with AA contrast',
        (tester) async {
      await tester.pumpWidget(
        darkSubject(const EducationScreen()),
      );
      await tester.pump();

      final hubTitle =
          tester.widget<Text>(find.text('Educational Hub & Courses'));
      final hubDesc = tester.widget<Text>(find.text(
          'We are building interactive courses, interview guides, and certifications to accelerate your career.'));

      expect(hubTitle.style?.color, SiratiColors.dark.textPrimary);
      expect(hubDesc.style?.color, SiratiColors.dark.textSecondary);

      assertDarkAaContrast(
        hubTitle.style!.color!,
        contextDescription: 'EducationScreen hub title',
      );
      assertDarkAaContrast(
        hubDesc.style!.color!,
        contextDescription: 'EducationScreen hub description',
      );

      // Scroll down to reveal CTA card
      await tester.drag(find.byType(Scrollable).first, const Offset(0, -600));
      await tester.pump();

      final ctaTitle =
          tester.widget<Text>(find.text('Ready to build your CV now?'));
      final ctaSub = tester.widget<Text>(
          find.text('Create an ATS-optimized CV in simple steps.'));

      expect(ctaTitle.style?.color, SiratiColors.dark.textPrimary);
      expect(ctaSub.style?.color, SiratiColors.dark.textSecondary);

      assertDarkAaContrast(
        ctaTitle.style!.color!,
        contextDescription: 'EducationScreen CTA title',
      );
      assertDarkAaContrast(
        ctaSub.style!.color!,
        contextDescription: 'EducationScreen CTA subtitle',
      );
    });

    testWidgets('7. CvAnalysisScreen renders section labels with AA contrast',
        (tester) async {
      await tester.pumpWidget(
        darkSubject(const CvAnalysisScreen()),
      );
      await tester.pump();

      final jobTitleLabel = tester.widget<Text>(find.text('Target job title'));
      final uploadLabel = tester.widget<Text>(find.text('Upload resume file'));
      // Below the fold since the JD card was added; build it by scrolling.
      await tester.scrollUntilVisible(
        find.text('Paste resume text'),
        300,
        scrollable: find.byType(Scrollable).first,
      );
      final pasteLabel = tester.widget<Text>(find.text('Paste resume text'));

      expect(jobTitleLabel.style?.color, SiratiColors.dark.textPrimary);
      expect(uploadLabel.style?.color, SiratiColors.dark.textPrimary);
      expect(pasteLabel.style?.color, SiratiColors.dark.textPrimary);

      assertDarkAaContrast(
        jobTitleLabel.style!.color!,
        contextDescription: 'CvAnalysisScreen target job title label',
      );
      assertDarkAaContrast(
        uploadLabel.style!.color!,
        contextDescription: 'CvAnalysisScreen upload file label',
      );
      assertDarkAaContrast(
        pasteLabel.style!.color!,
        contextDescription: 'CvAnalysisScreen paste text label',
      );
    });

    http.Client createMockClient() {
      return MockClient((request) async {
        final path = request.url.path;

        if (path.contains('/cv-analyses')) {
          return http.Response(
            jsonEncode({
              'data': [
                {
                  'id': 1,
                  'target_job_title': 'Software Engineer',
                  'original_filename': null,
                  'input_method': 'paste',
                  'score_total': 85,
                  'grade': 'B',
                  'job_match': 80,
                  'criteria': [],
                  'strengths': [],
                  'weaknesses': [],
                  'keywords_found': [],
                  'keywords_missing': [],
                  'quick_wins': [],
                  'ai_status': 'completed',
                  'ai_feedback': null,
                  'ai_error': null,
                  'created_at': '2026-09-01T12:00:00Z',
                },
              ],
              'meta': {'current_page': 1, 'last_page': 1},
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }

        if (path.contains('/generated-cvs')) {
          return http.Response(
            jsonEncode({
              'data': [
                {
                  'id': 1,
                  'full_name': 'Ahmad Al-Mansoor',
                  'email': 'ahmad@example.com',
                  'phone': '+966500000000',
                  'linkedin': null,
                  'location': 'Riyadh',
                  'target_job_title': 'Senior Flutter Developer',
                  'job_description_input': null,
                  'language': 'ar',
                  'summary_input': null,
                  'skills_input': 'Flutter, Dart',
                  'experience_input': '3 years',
                  'education_input': 'BS CS',
                  'certifications_input': null,
                  'generated_markdown': '# Ahmad',
                  'ai_status': 'completed',
                  'ai_output': null,
                  'ai_error': null,
                  'score_total': 90,
                  'grade': 'A',
                  'criteria': [],
                  'pdf_url': null,
                  'template_pdf_url': null,
                  'created_at': '2026-09-01T12:00:00Z',
                },
              ],
              'meta': {'current_page': 1, 'last_page': 1},
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }

        if (path.contains('/mobile/job-news')) {
          return http.Response(
            jsonEncode({
              'data': {
                'title': 'Job News',
                'items': [
                  {
                    'id': 101,
                    'language': 'en',
                    'title': 'Featured Flutter Architect',
                    'company': 'Tech Corp',
                    'location': 'Riyadh',
                    'body': 'Design high scale mobile applications.',
                    'category': 'engineering',
                    'url': 'https://example.com/101',
                    'apply_url': 'https://example.com/101/apply',
                    'valid_from': '2026-09-01T00:00:00Z',
                    'valid_until': '2026-10-01T00:00:00Z',
                    'valid_until_label': '30 days left',
                    'published_label': 'Today',
                    'published_at': '2026-09-01T00:00:00Z',
                  },
                  {
                    'id': 102,
                    'language': 'en',
                    'title': 'Senior Mobile Developer',
                    'company': 'Solutions Co',
                    'location': 'Jeddah',
                    'body': 'Build beautiful Flutter apps.',
                    'category': 'engineering',
                    'url': 'https://example.com/102',
                    'apply_url': 'https://example.com/102/apply',
                    'valid_from': '2026-09-01T00:00:00Z',
                    'valid_until': '2026-10-01T00:00:00Z',
                    'valid_until_label': '15 days left',
                    'published_label': 'Yesterday',
                    'published_at': '2026-09-01T00:00:00Z',
                  },
                ],
              },
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }

        if (path.contains('/mobile/my-cvs')) {
          return http.Response(
            jsonEncode({
              'data': {
                'items': [
                  {
                    'id': 1,
                    'title': 'Lead Flutter Engineer',
                    'target_job_title': 'Lead Flutter Engineer',
                    'updated_at': '2026-09-01',
                    'status': 'completed',
                  },
                ],
              },
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }

        if (path.contains('/mobile/notifications')) {
          return http.Response(
            jsonEncode({
              'data': {
                'items': [
                  {
                    'id': 1,
                    'title': 'CV Analysis Completed',
                    'body': 'Your CV has been analyzed with ATS score 85.',
                    'created_at': '2026-09-01T12:00:00Z',
                    'is_read': false,
                  },
                ],
              },
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }

        if (path.contains('/mobile/dashboard')) {
          return http.Response(
            jsonEncode({
              'data': {
                'profile': {
                  'name': 'Sarah',
                  'status': 'Job Seeker',
                },
                'stats': {
                  'generated_cvs': 3,
                  'analyses': 5,
                },
                'primary_action': {
                  'title': 'Create CV',
                  'subtitle': 'Build an ATS-optimized CV',
                },
                'analysis_action': {
                  'title': 'Analyze CV',
                  'subtitle': 'Check your ATS match',
                },
                'latest_news': {},
              },
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }

        return http.Response(
          jsonEncode({'data': {}}),
          200,
          headers: {'content-type': 'application/json'},
        );
      });
    }

    testWidgets(
        '8. HistoryScreen renders analysis title and CV card text with AA contrast',
        (tester) async {
      await http.runWithClient(() async {
        await tester.pumpWidget(
          darkSubject(const HistoryScreen()),
        );
        await tester.pumpAndSettle();

        // 1. History Analyses Tab (audited line 313: target job title)
        final analysisTitleFinder = find.text('Software Engineer');
        expect(analysisTitleFinder, findsOneWidget);
        final analysisTitle = tester.widget<Text>(analysisTitleFinder);
        expect(analysisTitle.style?.color, SiratiColors.dark.textPrimary);
        assertDarkAaContrast(
          analysisTitle.style!.color!,
          contextDescription:
              'HistoryScreen analysis target job title (line 313)',
        );

        // 2. Switch to Generated CVs tab
        await tester.tap(find.text('Generated CVs'));
        await tester.pumpAndSettle();

        // Audited line 460: cv full name
        final cvNameFinder = find.text('Ahmad Al-Mansoor');
        expect(cvNameFinder, findsOneWidget);
        final cvName = tester.widget<Text>(cvNameFinder);
        expect(cvName.style?.color, SiratiColors.dark.textPrimary);
        assertDarkAaContrast(
          cvName.style!.color!,
          contextDescription: 'HistoryScreen CV full name (line 460)',
        );

        // Audited line 466: cv target job title
        final cvJobFinder = find.text('Senior Flutter Developer');
        expect(cvJobFinder, findsOneWidget);
        final cvJob = tester.widget<Text>(cvJobFinder);
        expect(cvJob.style?.color, SiratiColors.dark.textSecondary);
        assertDarkAaContrast(
          cvJob.style!.color!,
          contextDescription: 'HistoryScreen CV target job (line 466)',
        );
      }, createMockClient);
    });

    testWidgets(
        '9. JobNewsScreen renders section header and job card title with AA contrast',
        (tester) async {
      await http.runWithClient(() async {
        await tester.pumpWidget(
          darkSubject(const JobNewsScreen(isActive: true)),
        );
        await tester.pumpAndSettle();

        // Audited line 289: 'Latest Postings' section heading
        final latestHeaderFinder = find.text('Latest Postings');
        expect(latestHeaderFinder, findsOneWidget);
        final latestHeader = tester.widget<Text>(latestHeaderFinder);
        expect(latestHeader.style?.color, SiratiColors.dark.textPrimary);
        assertDarkAaContrast(
          latestHeader.style!.color!,
          contextDescription:
              'JobNewsScreen latest postings section heading (line 289)',
        );

        // Audited line 482: JobTitleDisplay in job card
        final jobTitleFinder = find.text('Senior Mobile Developer');
        expect(jobTitleFinder, findsOneWidget);
        final jobTitle = tester.widget<Text>(jobTitleFinder);
        expect(jobTitle.style?.color, SiratiColors.dark.textPrimary);
        assertDarkAaContrast(
          jobTitle.style!.color!,
          contextDescription: 'JobNewsScreen job card title (line 482)',
        );
      }, createMockClient);
    });

    testWidgets(
        '10. MyCvsScreen renders CV count and CV card title with AA contrast',
        (tester) async {
      await http.runWithClient(() async {
        await tester.pumpWidget(
          darkSubject(const MyCvsScreen()),
        );
        await tester.pumpAndSettle();

        // Audited line 146: cv count
        final cvCountFinder = find.text('1 CV');
        expect(cvCountFinder, findsOneWidget);
        final cvCount = tester.widget<Text>(cvCountFinder);
        expect(cvCount.style?.color, SiratiColors.dark.textSecondary);
        assertDarkAaContrast(
          cvCount.style!.color!,
          contextDescription: 'MyCvsScreen CV count (line 146)',
        );

        // Audited line 462: CV card title
        final cardTitleFinder = find.text('Lead Flutter Engineer');
        expect(cardTitleFinder, findsOneWidget);
        final cardTitle = tester.widget<Text>(cardTitleFinder);
        expect(cardTitle.style?.color, SiratiColors.dark.textPrimary);
        assertDarkAaContrast(
          cardTitle.style!.color!,
          contextDescription: 'MyCvsScreen CV card title (line 462)',
        );
      }, createMockClient);
    });

    testWidgets(
        '11. NotificationsScreen renders item title and body with AA contrast',
        (tester) async {
      await http.runWithClient(() async {
        await tester.pumpWidget(
          darkSubject(const NotificationsScreen()),
        );
        await tester.pumpAndSettle();

        // Audited line 161: notification item title
        final notifTitleFinder = find.text('CV Analysis Completed');
        expect(notifTitleFinder, findsOneWidget);
        final notifTitle = tester.widget<Text>(notifTitleFinder);
        expect(notifTitle.style?.color, SiratiColors.dark.textPrimary);
        assertDarkAaContrast(
          notifTitle.style!.color!,
          contextDescription: 'NotificationsScreen item title (line 161)',
        );

        // Audited line 182: notification item body
        final notifBodyFinder =
            find.text('Your CV has been analyzed with ATS score 85.');
        expect(notifBodyFinder, findsOneWidget);
        final notifBody = tester.widget<Text>(notifBodyFinder);
        expect(notifBody.style?.color, SiratiColors.dark.textSecondary);
        assertDarkAaContrast(
          notifBody.style!.color!,
          contextDescription: 'NotificationsScreen item body (line 182)',
        );
      }, createMockClient);
    });

    testWidgets(
        '12. HomeScreen renders dashboard quick stat labels with AA contrast',
        (tester) async {
      await http.runWithClient(() async {
        await tester.pumpWidget(
          darkSubject(const HomeScreen()),
        );
        await tester.pumpAndSettle();

        // Audited line 646: Quick stat card label in HomeScreen
        final statLabelFinder = find.text('Analyses');
        expect(statLabelFinder, findsOneWidget);
        final statLabel = tester.widget<Text>(statLabelFinder);
        expect(statLabel.style?.color, SiratiColors.dark.textSecondary);
        assertDarkAaContrast(
          statLabel.style!.color!,
          contextDescription: 'HomeScreen dashboard stat card label (line 646)',
        );
      }, createMockClient);
    });
  });
}
