import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/ats_scanner/presentation/analysis_result_screen.dart';
import 'package:sirati/features/ats_scanner/presentation/cv_analysis_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/ai_status.dart';
import 'package:sirati/shared/models/cv_analysis.dart';
import 'package:sirati/shared/services/cv_api_service.dart';
import 'package:sirati/shared/services/cv_api_service_provider.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/submit_button.dart';

void main() {
  setUp(() {
    AppLocale.languageCode.value = 'en';
  });

  testWidgets('queued analysis polls through processing to completed',
      (tester) async {
    final service = _PollingCvApiService(_PollOutcome.completed);

    await _submitAnalysis(tester, service);
    await tester.pump();
    await tester.pump(const Duration(seconds: 1));
    await tester.pump();

    expect(service.statuses,
        [AiStatus.queued, AiStatus.processing, AiStatus.completed]);
    expect(find.byType(AnalysisResultScreen), findsOneWidget);
  });

  testWidgets('queued analysis failure shows the server error and result',
      (tester) async {
    final service = _PollingCvApiService(_PollOutcome.failed);

    await _submitAnalysis(tester, service);
    await tester.pump();
    await tester.pump(const Duration(seconds: 1));
    await tester.pump();

    expect(service.statuses,
        [AiStatus.queued, AiStatus.processing, AiStatus.failed]);
    expect(find.byType(AnalysisResultScreen), findsOneWidget);
    expect(
      find.textContaining(
          'AI suggestions could not be completed: Provider unavailable'),
      findsOneWidget,
    );
  });

  testWidgets('three-minute give-up still routes to deterministic result',
      (tester) async {
    final service = _PollingCvApiService(_PollOutcome.timedOut);

    await _submitAnalysis(tester, service);
    await tester.pump();
    await tester.pump(const Duration(seconds: 1));
    await tester.pump();

    final result = tester.widget<AnalysisResultScreen>(
      find.byType(AnalysisResultScreen),
    );
    expect(result.analysis.scoreTotal, 82);
    expect(find.textContaining('Your ATS score is ready'), findsOneWidget);
  });

  testWidgets('cancel stops polling without deleting the queued record',
      (tester) async {
    final service = _PollingCvApiService(_PollOutcome.waitForCancel);

    await _submitAnalysis(tester, service);
    await tester.pump(const Duration(seconds: 3));
    await tester.tap(find.text('Cancel'));
    await tester.pump(const Duration(milliseconds: 200));

    expect(service.cancelObserved, isTrue);
    expect(service.deleteCalls, 0);
    expect(find.byType(AnalysisResultScreen), findsNothing);
    expect(find.text('Cancelled'), findsOneWidget);
  });
}

Future<void> _submitAnalysis(
  WidgetTester tester,
  _PollingCvApiService service,
) async {
  await tester.pumpWidget(
    // The result screen reads its service from Riverpod; override it with the
    // same fake so submit and follow-up generation share one backend.
    ProviderScope(
      overrides: [cvApiServiceProvider.overrideWithValue(service)],
      child: MaterialApp(
        locale: const Locale('en'),
        theme: AppTheme.light,
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        home: const CvAnalysisScreen(),
      ),
    ),
  );
  await tester.pump();

  await tester.enterText(
    _fieldWithHint('e.g. Laravel Backend Developer'),
    'Laravel Developer',
  );
  // The paste field sits below the fold (JD card + 12-line textarea), and
  // ListView doesn't build off-screen children — scroll it into view first.
  await tester.scrollUntilVisible(
    _fieldWithHint('Paste the full resume text here...'),
    300,
    scrollable: find.byType(Scrollable).first,
  );
  await tester.enterText(
    _fieldWithHint('Paste the full resume text here...'),
    'Laravel developer resume with API, SQL, Git, and measurable results.',
  );
  await tester.drag(
    find.byType(Scrollable).first,
    const Offset(0, -1000),
  );
  await tester.pump();
  tester.widget<SubmitButton>(find.byType(SubmitButton)).onPressed!();
  await tester.pump();
}

Finder _fieldWithHint(String hint) => find.byWidgetPredicate(
      (widget) => widget is TextField && widget.decoration?.hintText == hint,
    );

enum _PollOutcome { completed, failed, timedOut, waitForCancel }

class _PollingCvApiService extends CvApiService {
  _PollingCvApiService(this.outcome);

  final _PollOutcome outcome;
  final List<String> statuses = [];
  bool cancelObserved = false;
  int deleteCalls = 0;

  @override
  Future<CvAnalysis> submitAnalysis({
    required String targetJobTitle,
    required String resumeText,
    String? jobDescription,
    http.MultipartFile? resumeFile,
    String? idempotencyKey,
  }) async {
    statuses.add(AiStatus.queued);
    return _analysis(AiStatus.queued);
  }

  @override
  Future<AiPollResult<CvAnalysis>> pollAnalysis(
    CvAnalysis initial, {
    bool Function()? isCancelled,
    bool Function()? isPaused,
    void Function(CvAnalysis value)? onProgress,
  }) async {
    statuses.add(AiStatus.processing);

    if (outcome == _PollOutcome.waitForCancel) {
      while (!isCancelled!.call()) {
        await Future<void>.delayed(const Duration(milliseconds: 50));
      }
      cancelObserved = true;
      return AiPollResult(value: initial, cancelled: true);
    }

    await Future<void>.delayed(Duration.zero);
    if (outcome == _PollOutcome.completed) {
      statuses.add(AiStatus.completed);
      return AiPollResult(value: _analysis(AiStatus.completed));
    }
    if (outcome == _PollOutcome.failed) {
      statuses.add(AiStatus.failed);
      return AiPollResult(value: _analysis(AiStatus.failed));
    }

    return AiPollResult(value: initial, timedOut: true);
  }

  @override
  Future<void> deleteGeneratedCv(int id) async {
    deleteCalls++;
  }
}

CvAnalysis _analysis(String status) => CvAnalysis.fromJson({
      'id': 42,
      'target_job_title': 'Laravel Developer',
      'original_filename': null,
      'input_method': 'paste',
      'score_total': 82,
      'grade': 'A',
      'job_match': 78,
      'criteria': [
        {'label': 'Keywords', 'score': 25, 'max': 30},
      ],
      'strengths': ['Strong keyword coverage'],
      'weaknesses': const [],
      'keywords_found': ['Laravel'],
      'keywords_missing': ['Docker'],
      'quick_wins': ['Add Docker'],
      'ai_status': status,
      'ai_feedback':
          status == AiStatus.completed ? {'executive_summary': 'Ready'} : null,
      'ai_error': status == AiStatus.failed ? 'Provider unavailable' : null,
      'created_at': '2026-07-29T00:00:00Z',
    });
