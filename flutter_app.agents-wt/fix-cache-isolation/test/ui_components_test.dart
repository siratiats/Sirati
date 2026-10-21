import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/features/ats_scanner/presentation/analysis_result_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/ai_status.dart';
import 'package:sirati/shared/models/cv_analysis.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/animated_ats_score_bar.dart';
import 'package:sirati/shared/widgets/auth_form_constraint.dart';
import 'package:sirati/shared/widgets/empty_state.dart';
import 'package:sirati/shared/widgets/form_fields.dart';
import 'package:sirati/shared/widgets/loading/ai_field_loading_overlay.dart';
import 'package:sirati/shared/widgets/loading/app_skeleton.dart';
import 'package:sirati/shared/widgets/motion.dart';
import 'package:sirati/shared/widgets/password_strength_meter.dart';
import 'package:sirati/shared/widgets/submit_button.dart';

void main() {
  Widget wrap(Widget child) {
    return MaterialApp(
      theme: AppTheme.light,
      home: Scaffold(body: child),
    );
  }

  testWidgets('AppEmptyState shows title and action', (tester) async {
    var tapped = false;
    await tester.pumpWidget(wrap(
      AppEmptyState(
        icon: Icons.description_outlined,
        title: 'No CVs yet',
        subtitle: 'Create one',
        actionLabel: 'Create',
        onAction: () => tapped = true,
        scrollable: false,
      ),
    ));

    expect(find.text('No CVs yet'), findsOneWidget);
    expect(find.text('Create one'), findsOneWidget);
    await tester.tap(find.text('Create'));
    expect(tapped, isTrue);
  });

  testWidgets('AppErrorState shows retry', (tester) async {
    var retried = false;
    await tester.pumpWidget(wrap(
      AppErrorState(
        english: true,
        message: 'Network down',
        onRetry: () => retried = true,
        scrollable: false,
      ),
    ));

    expect(find.text('Could not load'), findsOneWidget);
    expect(find.text('Network down'), findsOneWidget);
    await tester.tap(find.text('Retry'));
    expect(retried, isTrue);
  });

  testWidgets('AppSkeleton builds without throwing', (tester) async {
    await tester.pumpWidget(wrap(
      const DashboardSkeleton(horizontalPadding: 16),
    ));
    await tester.pump(const Duration(milliseconds: 100));
    expect(find.byType(AppSkeleton), findsWidgets);
  });

  testWidgets('SubmitButton locks height while loading', (tester) async {
    await tester.pumpWidget(wrap(
      const SubmitButton(
        label: 'Save',
        loadingLabel: 'Saving...',
        isLoading: true,
        onPressed: null,
      ),
    ));

    expect(find.text('Saving...'), findsOneWidget);
    expect(find.byType(CircularProgressIndicator), findsOneWidget);
    final box = tester.renderObject<RenderBox>(find.byType(SubmitButton));
    expect(box.size.height, 54);
  });

  test('PasswordStrengthMeter evaluates boundaries', () {
    expect(PasswordStrengthMeter.evaluate(''), PasswordStrength.empty);
    expect(PasswordStrengthMeter.evaluate('1234567'), PasswordStrength.weak);
    expect(PasswordStrengthMeter.evaluate('abcdefgh'), PasswordStrength.medium);
    expect(
        PasswordStrengthMeter.evaluate('Abcdef12!'), PasswordStrength.strong);
  });

  testWidgets('PasswordStrengthMeter exposes one Arabic live-region label',
      (tester) async {
    await tester.pumpWidget(wrap(
      const PasswordStrengthMeter(password: 'Abcdef12!', english: false),
    ));

    expect(find.text('قوية'), findsOneWidget);
    expect(find.bySemanticsLabel('قوة كلمة المرور: قوية'), findsOneWidget);
  });

  testWidgets('AnimatedAtsScoreBar reaches value and exposes percentage',
      (tester) async {
    await tester.pumpWidget(wrap(
      const AnimatedAtsScoreBar(
        value: .72,
        color: AppColors.primary,
        semanticLabel: 'ATS score',
      ),
    ));
    await tester.pumpAndSettle();

    final fill = tester.widget<FractionallySizedBox>(
      find.byType(FractionallySizedBox),
    );
    expect(fill.widthFactor, .72);
    expect(find.bySemanticsLabel('ATS score'), findsOneWidget);
  });

  testWidgets('AiFieldLoadingOverlay blocks interaction while loading',
      (tester) async {
    var taps = 0;
    await tester.pumpWidget(wrap(
      SizedBox(
        height: 80,
        child: AiFieldLoadingOverlay(
          isLoading: true,
          semanticsLabel: 'Improving content',
          statusMessages: const ['Working…'],
          child: ElevatedButton(
            onPressed: () => taps++,
            child: const Text('Edit'),
          ),
        ),
      ),
    ));

    await tester.tap(find.text('Edit'), warnIfMissed: false);
    expect(taps, 0);
    expect(find.byType(CircularProgressIndicator), findsOneWidget);
    expect(find.text('Working…'), findsOneWidget);
  });

  testWidgets('MotionTabStack first frame is fully visible', (tester) async {
    await tester.pumpWidget(wrap(
      const SizedBox(
        height: 220,
        child: MotionTabStack(
          currentIndex: 0,
          children: [Text('Dashboard tab'), Text('Second tab')],
        ),
      ),
    ));

    expect(find.text('Dashboard tab'), findsOneWidget);
    final opacity = tester.widget<Opacity>(
      find
          .ancestor(
            of: find.text('Dashboard tab'),
            matching: find.byType(Opacity),
          )
          .first,
    );
    expect(opacity.opacity, 1,
        reason: 'the first Home frame must not start at opacity zero');
  });

  testWidgets('AuthFormConstraint caps width on wide screens', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: SizedBox(
            width: 1024,
            child: AuthFormConstraint(
              child:
                  SizedBox(key: ValueKey('auth-form'), width: double.infinity),
            ),
          ),
        ),
      ),
    );

    final box = tester.getSize(find.byKey(const ValueKey('auth-form')));
    expect(box.width, AuthFormConstraint.maxWidth);
    expect(box.width, inInclusiveRange(430, 480));
  });

  testWidgets('MotionTabStack preserves tab state while transitioning',
      (tester) async {
    var currentIndex = 0;
    late StateSetter updateHost;

    await tester.pumpWidget(wrap(
      StatefulBuilder(
        builder: (context, setState) {
          updateHost = setState;
          return SizedBox(
            height: 220,
            child: MotionTabStack(
              currentIndex: currentIndex,
              children: const [
                TextField(key: ValueKey('motion-tab-field')),
                Center(child: Text('Second tab')),
              ],
            ),
          );
        },
      ),
    ));

    await tester.enterText(
      find.byKey(const ValueKey('motion-tab-field')),
      'Preserved draft',
    );
    updateHost(() => currentIndex = 1);
    await tester.pumpAndSettle();
    updateHost(() => currentIndex = 0);
    await tester.pumpAndSettle();

    expect(find.text('Preserved draft'), findsOneWidget);
  });

  testWidgets('MotionTabStack becomes static when animations are disabled',
      (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: const MediaQuery(
          data: MediaQueryData(disableAnimations: true),
          child: Scaffold(
            body: MotionTabStack(
              currentIndex: 0,
              children: [Text('One'), Text('Two')],
            ),
          ),
        ),
      ),
    );

    expect(find.byType(IndexedStack), findsOneWidget);
    expect(find.byType(AnimatedSlide), findsNothing);
  });

  testWidgets('AppTextFormField animates primary focus chrome', (tester) async {
    await tester.pumpWidget(wrap(
      AppTextFormField(hintText: 'Email'),
    ));

    await tester.tap(find.byType(TextField));
    await tester.pump();

    final animated = tester.widget<AnimatedContainer>(
      find
          .descendant(
            of: find.byType(AppTextFormField),
            matching: find.byType(AnimatedContainer),
          )
          .first,
    );
    final decoration = animated.decoration! as BoxDecoration;
    expect(decoration.border!.top.color, AppColors.primary);
  });

  testWidgets(
      'MotionNavIcon shows selected icon without opaque background overlay (SIRATI-60)',
      (tester) async {
    await tester.pumpWidget(wrap(
      const MotionNavIcon(
        icon: Icons.home_outlined,
        selectedIcon: Icons.home_rounded,
        selected: true,
        selectedBackgroundColor: AppColors.primaryLight,
      ),
    ));

    expect(find.byIcon(Icons.home_rounded), findsOneWidget);
    expect(
      find.descendant(
        of: find.byType(MotionNavIcon),
        matching: find.byType(AnimatedContainer),
      ),
      findsNothing,
    );
  });

  testWidgets('AnalysisResultScreen caps and expands long keyword groups',
      (tester) async {
    final foundKeywords = List.generate(24, (index) => 'found-$index');
    final missingKeywords = List.generate(2, (index) => 'missing-$index');

    await tester.pumpWidget(
      ProviderScope(
        child: MaterialApp(
          theme: AppTheme.light,
          locale: const Locale('en', 'US'),
          supportedLocales: AppLocalizations.supportedLocales,
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          home: AnalysisResultScreen(
            analysis: CvAnalysis(
              id: 1,
              targetJobTitle: 'Product Designer',
              originalFilename: null,
              inputMethod: 'paste',
              scoreTotal: 72,
              grade: 'B',
              jobMatch: 68,
              criteria: const [
                ScoreCriterion(label: 'Structure', score: 8, max: 10),
              ],
              strengths: const ['Clear summary'],
              weaknesses: const [],
              keywordsFound: foundKeywords,
              keywordsMissing: missingKeywords,
              quickWins: const ['Add measurable outcomes'],
              aiStatus: AiStatus.completed,
              aiFeedback: null,
              aiError: null,
              createdAt: null,
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.text('Keywords'),
      400,
      scrollable: find.descendant(
        of: find.byKey(const ValueKey('analysis-score-tab-list')),
        matching: find.byType(Scrollable),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('found-0'), findsOneWidget);
    expect(find.text('found-17'), findsOneWidget);
    expect(find.text('found-18'), findsNothing);
    expect(find.text('+6 more'), findsOneWidget);

    await tester.tap(find.text('+6 more'));
    await tester.pumpAndSettle();

    expect(find.text('found-18'), findsOneWidget);
    expect(find.text('found-23'), findsOneWidget);
    expect(find.text('Show less'), findsOneWidget);
  });
}
