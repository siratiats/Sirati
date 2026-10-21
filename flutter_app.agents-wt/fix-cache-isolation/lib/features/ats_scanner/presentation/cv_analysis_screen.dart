import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;
import 'package:sirati/core/network/analytics_service.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/utils/ai_error_message.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/core/utils/idempotency_key.dart';
import 'package:sirati/features/ats_scanner/domain/analysis_draft.dart';
import 'package:sirati/features/ats_scanner/presentation/analysis_result_screen.dart';
import 'package:sirati/features/ats_scanner/presentation/cv_analysis/analysis_form_sections.dart';
import 'package:sirati/shared/models/ai_status.dart';
import 'package:sirati/shared/services/cv_api_service_provider.dart';
import 'package:sirati/shared/services/notification_engagement_service.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/app_snack_bar.dart';
import 'package:sirati/shared/widgets/form_fields.dart';
import 'package:sirati/shared/widgets/loading/ai_progress_overlay.dart';
import 'package:sirati/shared/widgets/submit_button.dart';

/// Polling waits while the app is not in the foreground; background time
/// doesn't count toward the poll timeout.
bool pausesPolling(AppLifecycleState state) =>
    state == AppLifecycleState.paused ||
    state == AppLifecycleState.hidden ||
    state == AppLifecycleState.detached;

class CvAnalysisScreen extends ConsumerStatefulWidget {
  const CvAnalysisScreen({super.key});

  @override
  ConsumerState<CvAnalysisScreen> createState() => _CvAnalysisScreenState();
}

class _CvAnalysisScreenState extends ConsumerState<CvAnalysisScreen>
    with WidgetsBindingObserver {
  final _formKey = GlobalKey<FormState>();
  final _jobTitleController = TextEditingController();
  final _jobDescriptionController = TextEditingController();
  final _resumeTextController = TextEditingController();
  final _keys = IdempotencyKeyRing(newIdempotencyKey);
  PlatformFile? _uploadedFile;
  bool _isLoading = false;
  bool _submitted = false;
  bool _jdExpanded = false;

  /// Bumped on each submit/cancel so a late AI response cannot apply.
  int _aiRequestGen = 0;
  bool _pollingPaused = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _pollingPaused = pausesPolling(state);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _jobTitleController.dispose();
    _jobDescriptionController.dispose();
    _resumeTextController.dispose();
    super.dispose();
  }

  AnalysisDraft get _draft => AnalysisDraft(
        jobTitle: _jobTitleController.text,
        jobDescription: _jobDescriptionController.text,
        resumeText: _resumeTextController.text,
        fileName: _uploadedFile?.name,
        fileSize: _uploadedFile?.size,
      );

  Future<void> _pickFile() async {
    final english = AppLocale.isEnglish(context);
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: AnalysisLimits.allowedExtensions,
      withData: true,
    );
    if (!mounted || result == null || result.files.isEmpty) return;

    final file = result.files.single;
    switch (AnalysisRules.upload(name: file.name, size: file.size)) {
      case UploadRejection.tooLarge:
        AppSnackBar.error(
          context,
          english
              ? 'File must be 5 MB or smaller.'
              : 'يجب أن يكون حجم الملف 5 ميغابايت أو أقل.',
        );
      case UploadRejection.unsupportedType:
        AppSnackBar.error(
          context,
          english
              ? 'Only PDF or TXT files are supported.'
              : 'يُدعم فقط ملف PDF أو TXT.',
        );
      case null:
        setState(() => _uploadedFile = file);
    }
  }

  Future<void> _submit() async {
    if (_isLoading) return;
    final english = AppLocale.isEnglish(context);
    setState(() => _submitted = true);
    if (!_formKey.currentState!.validate()) {
      unawaited(HapticFeedback.selectionClick());
      return;
    }
    final draft = _draft;
    if (!draft.hasResume) {
      AppSnackBar.error(
        context,
        english
            ? 'Paste resume text or upload a PDF/TXT file to start.'
            : 'الصق نص السيرة أو ارفع ملف PDF/TXT للبدء.',
      );
      return;
    }
    final api = ref.read(cvApiServiceProvider);
    final requestId = ++_aiRequestGen;
    final idempotencyKey = _keys.keyFor(draft);
    setState(() => _isLoading = true);
    final startedAt = DateTime.now();
    unawaited(AnalyticsService.logAnalysisStarted());

    final progress = await AiProgressOverlay.show(
      context,
      kind: AiProgressKind.analysis,
      english: english,
      onCancelled: () {
        if (_aiRequestGen == requestId) _aiRequestGen++;
        if (!mounted) return;
        setState(() => _isLoading = false);
        AppSnackBar.info(context, english ? 'Cancelled' : 'تم الإلغاء');
      },
    );
    bool stale() =>
        !mounted || progress.isCancelled || requestId != _aiRequestGen;

    try {
      final resumeFile = await _multipartFile();
      if (stale()) return;

      var analysis = await api.submitAnalysis(
        targetJobTitle: draft.jobTitle.trim(),
        jobDescription: draft.jobDescription,
        resumeText: draft.resumeText,
        resumeFile: resumeFile,
        idempotencyKey: idempotencyKey,
      );
      progress.setPhase(AiProgressPhase.fromAiStatus(analysis.aiStatus));

      final poll = await api.pollAnalysis(
        analysis,
        isCancelled: () => progress.isCancelled || requestId != _aiRequestGen,
        isPaused: () => _pollingPaused,
        onProgress: (value) =>
            progress.setPhase(AiProgressPhase.fromAiStatus(value.aiStatus)),
      );
      analysis = poll.value;
      if (poll.cancelled || stale()) return;

      // The server has a result for this draft; an identical resubmit is
      // now a new analysis.
      _keys.consume();
      unawaited(AnalyticsService.logAnalysisCompleted(
        // Bucketed by the service — never the exact score.
        score: analysis.scoreTotal,
        durationMs: DateTime.now().difference(startedAt).inMilliseconds,
      ));
      final aiFailed = analysis.aiStatus == AiStatus.failed;
      if (!poll.timedOut && !aiFailed) await progress.complete();
      await progress.dismiss();
      if (!mounted || stale()) return;

      if (poll.timedOut) {
        AppSnackBar.warning(
          context,
          english
              ? 'AI is still working. Your ATS score is ready, and you can retry the AI suggestions later.'
              : 'لا يزال الذكاء الاصطناعي يعمل. نتيجتك الأساسية جاهزة، ويمكنك إعادة محاولة التوصيات لاحقاً.',
        );
      } else if (aiFailed) {
        AppSnackBar.error(
          context,
          AiErrorMessage.suggestionsFailed(analysis.aiError, english: english),
          actionLabel: english ? 'Retry' : 'إعادة المحاولة',
          onAction: () => unawaited(_submit()),
        );
      }
      unawaited(HapticFeedback.lightImpact());
      unawaited(NotificationEngagementService.instance
          .reportConversion('analysis_completed'));
      final warning = analysis.classificationWarning;
      if (warning != null) {
        AppSnackBar.warning(
          context,
          english
              ? 'This file may not be a resume. Analysis results may not be accurate.'
              : warning,
        );
      }
      unawaited(Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => AnalysisResultScreen(analysis: analysis),
        ),
      ));
    } on ApiException catch (exception) {
      if (progress.isCancelled || requestId != _aiRequestGen) return;
      unawaited(
          AnalyticsService.logAnalysisFailed(errorType: exception.type.name));
      if (mounted) AppSnackBar.fromException(context, exception);
    } catch (_) {
      if (progress.isCancelled || requestId != _aiRequestGen) return;
      unawaited(AnalyticsService.logAnalysisFailed(errorType: 'unknown'));
      if (mounted) {
        AppSnackBar.error(
          context,
          english
              ? 'An unexpected error occurred while analyzing the CV.'
              : 'حدث خطأ غير متوقع أثناء تحليل السيرة.',
        );
      }
    } finally {
      await progress.dismiss();
      if (mounted && requestId == _aiRequestGen && !progress.isCancelled) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<http.MultipartFile?> _multipartFile() async {
    final file = _uploadedFile;
    if (file == null) return null;
    final bytes = file.bytes;
    if (bytes != null) {
      return http.MultipartFile.fromBytes(
        'resume_file',
        bytes,
        filename: file.name,
      );
    }
    final path = file.path;
    if (path != null) {
      return http.MultipartFile.fromPath(
        'resume_file',
        path,
        filename: file.name,
      );
    }
    return null;
  }

  AutovalidateMode get _autovalidate => _submitted
      ? AutovalidateMode.onUserInteraction
      : AutovalidateMode.disabled;

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    final file = _uploadedFile;

    return Scaffold(
      appBar: AppBar(
        title: Text(english ? 'CV Analysis' : 'تحليل السيرة الذاتية'),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 20, 16, 32),
          children: [
            AppCard(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  AnalysisSectionTitle(
                    english ? 'Target job title' : 'المسمى الوظيفي المستهدف',
                  ),
                  const SizedBox(height: 10),
                  AppTextFormField(
                    controller: _jobTitleController,
                    autovalidateMode: _autovalidate,
                    textAlign: TextAlign.start,
                    textInputAction: TextInputAction.next,
                    onFieldSubmitted: (_) => FocusScope.of(context).nextFocus(),
                    hintText: english
                        ? 'e.g. Laravel Backend Developer'
                        : 'مثال: مطوّر Laravel Backend',
                    prefixIcon: const Icon(Icons.work_outline_rounded),
                    validator: (v) => switch (AnalysisRules.jobTitle(v)) {
                      AnalysisFieldError.required => english
                          ? 'Please enter a target job title'
                          : 'يرجى إدخال المسمى الوظيفي المستهدف',
                      AnalysisFieldError.tooLong => english
                          ? 'Job title must be ${AnalysisLimits.jobTitleMax} characters or fewer.'
                          : 'يجب ألا يزيد المسمى الوظيفي عن ${AnalysisLimits.jobTitleMax} حرفاً.',
                      null => null,
                    },
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            AppCard(
              padding: const EdgeInsets.all(16),
              child: CollapsibleSection(
                title: english
                    ? 'Job description (optional)'
                    : 'وصف الوظيفة (اختياري)',
                collapsedHint: english
                    ? 'Tap to score your CV against the actual posting instead of a generic list.'
                    : 'اضغط لتقييم سيرتك مقارنة بالإعلان الفعلي بدل قائمة عامة.',
                expanded: _jdExpanded,
                onToggle: () => setState(() => _jdExpanded = !_jdExpanded),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SizedBox(height: 4),
                    Text(
                      english
                          ? 'Paste the posting to score your CV against the actual role, not a generic keyword list.'
                          : 'الصق نص الإعلان لتحصل على تقييم مقارنة بالوظيفة الفعلية، لا بقائمة كلمات عامة.',
                      textAlign: TextAlign.start,
                      style: TextStyle(
                        fontSize: 12,
                        color: context.sirati.textSecondary,
                      ),
                    ),
                    const SizedBox(height: 10),
                    AppTextFormField(
                      controller: _jobDescriptionController,
                      textAlign: TextAlign.start,
                      maxLines: 5,
                      keyboardType: TextInputType.multiline,
                      textInputAction: TextInputAction.newline,
                      hintText: english
                          ? 'Paste the full job posting here...'
                          : 'الصق نص الإعلان الكامل هنا...',
                      validator: (value) => AnalysisRules.jobDescription(
                                  value) ==
                              null
                          ? null
                          : (english
                              ? 'Job description must be ${AnalysisLimits.jobDescriptionMax} characters or fewer.'
                              : 'يجب ألا يزيد وصف الوظيفة عن ${AnalysisLimits.jobDescriptionMax} حرف.'),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            AppCard(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  AnalysisSectionTitle(
                    english ? 'Upload resume file' : 'رفع ملف السيرة الذاتية',
                  ),
                  const SizedBox(height: 12),
                  if (file == null)
                    UploadDropZone(onTap: () => unawaited(_pickFile()))
                  else
                    SelectedFileTile(
                      fileName: file.name,
                      onRemove: () => setState(() => _uploadedFile = null),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            const OrDivider(),
            const SizedBox(height: 12),
            AppCard(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  AnalysisSectionTitle(
                    english ? 'Paste resume text' : 'لصق نص السيرة الذاتية',
                  ),
                  const SizedBox(height: 10),
                  AppTextFormField(
                    controller: _resumeTextController,
                    autovalidateMode: _autovalidate,
                    textAlign: TextAlign.start,
                    // Real resumes run 400-1000 words; start at 12 lines and
                    // grow with the content (SIRATI-93).
                    minLines: 12,
                    maxLines: null,
                    keyboardType: TextInputType.multiline,
                    textInputAction: TextInputAction.newline,
                    hintText: english
                        ? 'Paste the full resume text here...'
                        : 'الصق نص السيرة الذاتية كاملاً هنا...',
                    validator: (value) {
                      if (_uploadedFile != null) return null;
                      if (value == null || value.trim().isEmpty) {
                        return english
                            ? 'Paste resume text or upload a PDF/TXT file.'
                            : 'الصق نص السيرة أو ارفع ملف PDF/TXT.';
                      }
                      return null;
                    },
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.xl),
            SubmitButton(
              label: english ? 'Analyze CV' : 'تحليل السيرة الذاتية',
              loadingLabel: english ? 'Analyzing...' : 'جارٍ التحليل...',
              isLoading: _isLoading,
              icon: Icons.analytics_outlined,
              onPressed: () => unawaited(_submit()),
            ),
          ],
        ),
      ),
    );
  }
}
