import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/network/analytics_service.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/utils/ai_error_message.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/cv_builder/application/cv_draft_store.dart';
import 'package:sirati/features/cv_builder/application/cv_enhancement_controller.dart';
import 'package:sirati/features/cv_builder/application/cv_generation_controller.dart';
import 'package:sirati/features/cv_builder/domain/cv_form_data.dart';
import 'package:sirati/features/cv_builder/presentation/cv_generator/cv_form_controllers.dart';
import 'package:sirati/features/cv_builder/presentation/cv_generator/draft_restore_banner.dart';
import 'package:sirati/features/cv_builder/presentation/cv_generator/exit_dialogs.dart';
import 'package:sirati/features/cv_builder/presentation/cv_generator/steps/education_step.dart';
import 'package:sirati/features/cv_builder/presentation/cv_generator/steps/experience_step.dart';
import 'package:sirati/features/cv_builder/presentation/cv_generator/steps/personal_step.dart';
import 'package:sirati/features/cv_builder/presentation/cv_generator/steps/skills_step.dart';
import 'package:sirati/features/cv_builder/presentation/cv_generator/wizard_footer.dart';
import 'package:sirati/features/cv_builder/presentation/cv_generator/wizard_header.dart';
import 'package:sirati/features/cv_builder/presentation/cv_generator/wizard_widgets.dart';
import 'package:sirati/features/cv_builder/presentation/generated_cv_screen.dart';
import 'package:sirati/features/cv_builder/presentation/widgets/achievement_builder_sheet.dart';
import 'package:sirati/shared/models/cv_target_job.dart';
import 'package:sirati/shared/models/generated_cv.dart';
import 'package:sirati/shared/models/job_title.dart';
import 'package:sirati/shared/services/in_app_review_service.dart';
import 'package:sirati/shared/services/mobile_content_service_provider.dart';
import 'package:sirati/shared/services/notification_engagement_service.dart';
import 'package:sirati/shared/widgets/ai_cv_field.dart';
import 'package:sirati/shared/widgets/app_snack_bar.dart';
import 'package:sirati/shared/widgets/language_toggle.dart';
import 'package:sirati/shared/widgets/loading/ai_progress_overlay.dart';
import 'package:sirati/shared/widgets/motion.dart';
import 'package:sirati/shared/widgets/success_beat.dart';

/// Four-step CV wizard. Creates a CV, or edits [initialCv] when given.
///
/// This widget owns form/UI state (text controllers, step, validation,
/// dirty tracking, local draft). Network work lives in Riverpod controllers:
/// [CvGenerationController], [CvFieldEnhancer], [JobDescriptionEnhancer].
class CvGeneratorScreen extends ConsumerStatefulWidget {
  const CvGeneratorScreen({super.key, this.initialCv, this.targetJob});

  final GeneratedCv? initialCv;

  /// Job to tailor a new CV for (from a job post). Ignored when editing.
  final CvTargetJob? targetJob;

  @override
  ConsumerState<CvGeneratorScreen> createState() => _CvGeneratorScreenState();
}

class _CvGeneratorScreenState extends ConsumerState<CvGeneratorScreen> {
  static const _stepCount = 4;

  final _fields = CvFormControllers();
  final _experienceFocusNode = FocusNode();
  final _stepFormKeys =
      List.generate(_stepCount, (_) => GlobalKey<FormState>());

  /// Whether the user tried to leave each step (drives live validation).
  final _stepSubmitted = List<bool>.filled(_stepCount, false);
  final _stepShowBanner = List<bool>.filled(_stepCount, false);

  /// Review panel data from the last successful enhancement, per field.
  final Map<String, Map<String, dynamic>> _fieldResults = {};

  // Captured in initState: ref cannot be used from dispose().
  late final CvDraftStore _draftStore;

  int _step = 0;

  /// +1 when advancing, -1 when going back (drives the slide direction).
  int _stepDirection = 1;
  String _language = 'ar';
  List<JobTitle> _jobTitles = const [];

  bool _showExperienceShapeHint = false;
  bool _experienceHintDismissed = false;

  /// True after any user edit since the last save/generate/restore.
  bool _isDirty = false;

  /// Suppresses dirty tracking while the form is filled programmatically.
  bool _suppressDirtyTracking = false;
  Timer? _autosaveTimer;

  /// Create-mode draft offered by the restore banner, if any.
  SavedCvDraft? _pendingDraft;

  bool get _isEditMode => widget.initialCv != null;

  @override
  void initState() {
    super.initState();
    _draftStore = ref.read(cvDraftStoreProvider);

    final cv = widget.initialCv;
    if (cv != null) {
      final data = CvFormData.fromGeneratedCv(cv);
      _fillSilently(data);
      _language = data.language;
    } else if (widget.targetJob case final job?) {
      // Prefill only: a pre-filled form is not a user edit, so it must not
      // mark the draft dirty on its own.
      _suppressDirtyTracking = true;
      _fields.jobTitle.text = job.title;
      _fields.jobDescription.text = job.description;
      _suppressDirtyTracking = false;
    }

    _fields.addListener(_onAnyFieldChanged);
    _experienceFocusNode.addListener(_onExperienceFocusChanged);

    unawaited(
      AnalyticsService.logWizardStarted(mode: _isEditMode ? 'edit' : 'create'),
    );
    // Drafts are create-mode only; never offer one while editing a saved CV.
    if (!_isEditMode) unawaited(_loadDraftOffer());
    unawaited(_loadJobTitles());
  }

  @override
  void dispose() {
    _autosaveTimer?.cancel();
    // Flush a pending create-mode draft so pop / background kill keeps data.
    // Read the form before the controllers are disposed below.
    if (!_isEditMode && _isDirty) {
      unawaited(
        _draftStore.save(_fields.read(language: _language), step: _step),
      );
    }
    _fields.removeListener(_onAnyFieldChanged);
    _fields.dispose();
    _experienceFocusNode.removeListener(_onExperienceFocusChanged);
    _experienceFocusNode.dispose();
    super.dispose();
  }

  // ── Form state ────────────────────────────────────────────────────────────

  void _fillSilently(CvFormData data) {
    _suppressDirtyTracking = true;
    _fields.fill(data);
    _suppressDirtyTracking = false;
  }

  void _setTextSilently(TextEditingController controller, String text) {
    _suppressDirtyTracking = true;
    controller.text = text;
    controller.selection = TextSelection.collapsed(offset: text.length);
    _suppressDirtyTracking = false;
  }

  void _onAnyFieldChanged() {
    if (!_suppressDirtyTracking) _markDirty();
  }

  void _markDirty() {
    if (_suppressDirtyTracking) return;
    if (!_isDirty || _pendingDraft != null) {
      setState(() {
        _isDirty = true;
        // Typing instead of restoring means the user declined the offer.
        _pendingDraft = null;
      });
    }
    if (!_isEditMode) _scheduleAutosave();
  }

  void _scheduleAutosave() {
    _autosaveTimer?.cancel();
    _autosaveTimer = Timer(const Duration(seconds: 2), () {
      unawaited(_saveDraft());
    });
  }

  Future<void> _saveDraft() {
    if (_isEditMode) return Future.value();
    return _draftStore.save(_fields.read(language: _language), step: _step);
  }

  void _onExperienceFocusChanged() {
    if (!_experienceFocusNode.hasFocus ||
        _fields.experience.text.trim().isNotEmpty ||
        _experienceHintDismissed ||
        !mounted) {
      return;
    }
    setState(() => _showExperienceShapeHint = true);
  }

  void _setLanguage(String code) {
    if (_language == code) return;
    setState(() => _language = code);
    _markDirty();
  }

  Future<void> _loadJobTitles() async {
    try {
      final titles = await ref.read(mobileContentServiceProvider).jobTitles();
      if (mounted) setState(() => _jobTitles = titles);
    } catch (_) {
      // Chip suggestions are optional; the free-text field still works.
    }
  }

  // ── Drafts ────────────────────────────────────────────────────────────────

  Future<void> _loadDraftOffer() async {
    final draft = await _draftStore.read(stepCount: _stepCount);
    if (!mounted || _isEditMode || draft == null) return;
    setState(() => _pendingDraft = draft);
  }

  void _restoreDraft() {
    final draft = _pendingDraft;
    if (draft == null) return;
    unawaited(AnalyticsService.logDraftRestored());
    _fillSilently(draft.data);
    setState(() {
      _language = draft.data.language;
      _stepDirection = 1;
      _step = draft.step;
      _isDirty = false;
      _pendingDraft = null;
    });
  }

  Future<void> _startFresh() async {
    await _draftStore.clear();
    if (mounted) setState(() => _pendingDraft = null);
  }

  // ── Navigation ────────────────────────────────────────────────────────────

  void _goToStep(int target) {
    if (target == _step || target < 0 || target >= _stepCount) return;
    setState(() {
      _stepDirection = target < _step ? -1 : 1;
      _step = target;
    });
    // Persist the step position immediately (create mode only).
    unawaited(_saveDraft());
  }

  bool _validateCurrentStep() {
    setState(() {
      _stepSubmitted[_step] = true;
      _stepShowBanner[_step] = false;
    });
    final ok = _stepFormKeys[_step].currentState?.validate() ?? true;
    if (!ok) {
      setState(() => _stepShowBanner[_step] = true);
      unawaited(HapticFeedback.selectionClick());
    }
    return ok;
  }

  /// Footer Next/Generate, and keyboard Done on a step's last field.
  void _onPrimaryAction() {
    if (!_validateCurrentStep()) return;
    unawaited(AnalyticsService.logWizardStepCompleted(step: _step));
    if (_step < _stepCount - 1) {
      _goToStep(_step + 1);
    } else {
      unawaited(_submit());
    }
  }

  Future<void> _handleExitAttempt() async {
    if (!_isDirty) {
      if (mounted) Navigator.of(context).pop();
      return;
    }
    final english = AppLocale.isEnglish(context);

    if (_isEditMode) {
      final discard = await confirmDiscardEdits(context, english: english);
      if (discard && mounted) {
        unawaited(
          AnalyticsService.logWizardAbandoned(step: _step, dirty: _isDirty),
        );
        Navigator.of(context).pop();
      }
      return;
    }

    final action = await askSaveDraft(context, english: english);
    if (!mounted) return;
    switch (action) {
      case WizardExitAction.cancel:
        return;
      case WizardExitAction.discard:
        unawaited(
          AnalyticsService.logWizardAbandoned(step: _step, dirty: _isDirty),
        );
        _autosaveTimer?.cancel();
        await _draftStore.clear();
        if (mounted) Navigator.of(context).pop();
      case WizardExitAction.saveDraft:
        _autosaveTimer?.cancel();
        await _saveDraft();
        if (!mounted) return;
        _isDirty = false;
        unawaited(AnalyticsService.logDraftSaved());
        AppSnackBar.info(context, english ? 'Draft saved' : 'تم حفظ المسودة');
        Navigator.of(context).pop();
    }
  }

  void _openAchievementBuilder(bool english) {
    AchievementBuilderSheet.show(
      context: context,
      english: english,
      onInsert: (bullet) {
        final updated = appendAchievement(_fields.experience.text, bullet);
        if (updated == null) {
          if (mounted) {
            AppSnackBar.info(
              context,
              english
                  ? 'This achievement is already in your experience.'
                  : 'هذا الإنجاز موجود مسبقاً في خبراتك.',
            );
          }
          return;
        }
        _fields.experience.text = updated;
        _markDirty();
        if (mounted) {
          AppSnackBar.success(
            context,
            english
                ? 'Achievement added to experience.'
                : 'تمت إضافة الإنجاز إلى الخبرات بنجاح.',
          );
        }
      },
    );
  }

  // ── Generate / update ─────────────────────────────────────────────────────

  Future<void> _submit() async {
    final english = AppLocale.isEnglish(context);
    final generation = ref.read(cvGenerationControllerProvider.notifier);
    final startedAt = DateTime.now();

    final progress = await AiProgressOverlay.show(
      context,
      kind: AiProgressKind.generation,
      english: english,
      onCancelled: () {
        generation.cancel();
        if (mounted) {
          AppSnackBar.info(context, english ? 'Cancelled' : 'تم الإلغاء');
        }
      },
    );

    final outcome = await generation.submit(
      data: _fields.read(language: _language),
      editingId: widget.initialCv?.id,
      isCancelled: () => progress.isCancelled,
      onStatus: (status) =>
          progress.setPhase(AiProgressPhase.fromAiStatus(status)),
    );

    switch (outcome) {
      case CvGenerationCancelled():
        // Form stays intact; create-mode autosave already covers the draft.
        await progress.dismiss();

      case CvGenerationFailed(:final error):
        await progress.dismiss();
        unawaited(AnalyticsService.logCvGenerationFailed(
          errorType: error is ApiException ? error.type.name : 'unknown',
        ));
        if (!mounted) return;
        if (error is ApiException) {
          AppSnackBar.fromException(
            context,
            error,
            retryLabel: english ? 'Retry' : 'إعادة',
            onRetry: _submit,
          );
        } else {
          AppSnackBar.error(
            context,
            english
                ? 'An unexpected error occurred while generating the CV.'
                : 'حدث خطأ غير متوقع أثناء توليد السيرة.',
          );
        }

      case CvGenerated(:final cv, :final timedOut, :final aiFailed):
        unawaited(AnalyticsService.logCvGenerated(
          templateId: 'default',
          durationMs: DateTime.now().difference(startedAt).inMilliseconds,
        ));
        if (!_isEditMode) {
          unawaited(NotificationEngagementService.instance
              .reportConversion('cv_generated'));
          // Successful create: drop the local draft so reopening is clean.
          _autosaveTimer?.cancel();
          await _draftStore.clear();
        }
        if (mounted) setState(() => _isDirty = false);

        // Finish the overlay on a resolved state before it closes.
        if (!timedOut && !aiFailed) await progress.complete();
        await progress.dismiss();
        if (!mounted) return;

        if (timedOut) {
          AppSnackBar.warning(
            context,
            english
                ? 'AI is still working in the background. We will notify you when it finishes — this draft is not ready to send to an employer.'
                : 'لا يزال الذكاء الاصطناعي يعمل في الخلفية. سنُعلمك عند الانتهاء — هذه المسودة غير جاهزة للإرسال إلى جهة التوظيف.',
          );
          return;
        }
        if (aiFailed) {
          AppSnackBar.error(
            context,
            AiErrorMessage.generationFailed(cv.aiError, english: english),
            actionLabel: english ? 'Retry' : 'إعادة المحاولة',
            onAction: _submit,
          );
        }
        await SuccessBeat.play(context);
        if (!mounted) return;
        // Peak satisfaction: OS review prompt after SuccessBeat (create only).
        if (!_isEditMode) {
          unawaited(InAppReviewService.maybeRequestAfterCvGenerated());
        }
        if (_isEditMode) {
          // Hand the saved CV back: the screen that opened the editor shows
          // it, instead of stacking a second copy above a stale one.
          Navigator.of(context).pop<GeneratedCv>(cv);
          return;
        }
        unawaited(Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => GeneratedCvScreen(generatedCv: cv)),
        ));
    }
  }

  // ── AI enhancement ────────────────────────────────────────────────────────

  Future<void> _enhanceField(String field) async {
    final english = AppLocale.isEnglish(context);
    final controller = _fields.forEnhanceableField(field);
    final draft = controller.text;
    final jobTitle = _fields.jobTitle.text.trim();

    final minimum =
        field == 'experience' ? CvFormData.minExperienceChars : null;
    if (!AiCvField.isSufficientForEnhancement(draft, customMinimum: minimum)) {
      return;
    }
    if (jobTitle.isEmpty) {
      AppSnackBar.warning(
        context,
        english
            ? 'Enter the target job title first.'
            : 'أدخل المسمى الوظيفي المستهدف أولاً.',
      );
      return;
    }

    setState(() => _fieldResults.remove(field));
    final outcome = await ref.read(cvFieldEnhancerProvider.notifier).enhance(
          field: field,
          draft: draft,
          jobTitle: jobTitle,
          language: _language,
        );
    if (!mounted) return;

    switch (outcome) {
      case CvEnhanceStale():
        return;
      case CvEnhanceFailed(:final error):
        _showEnhanceError(
          error,
          english: english,
          onRetry: () => _enhanceField(field),
          fallback: english
              ? 'Could not enhance this field. Try again.'
              : 'تعذر تحسين هذا الحقل. حاول مرة أخرى.',
        );
      case CvEnhanced(:final text, :final details):
        _setTextSilently(controller, text);
        _markDirty();
        setState(() => _fieldResults[field] = details);
        AppSnackBar.show(
          context,
          message: english
              ? 'Field enhanced. Review the missing facts before continuing.'
              : 'تم تحسين الحقل. راجع المعلومات الناقصة قبل المتابعة.',
          variant: AppSnackBarVariant.success,
          actionLabel: english ? 'Undo' : 'تراجع',
          onAction: () {
            if (!mounted) return;
            _setTextSilently(controller, draft);
            _markDirty();
            setState(() => _fieldResults.remove(field));
          },
        );
    }
  }

  Future<void> _enhanceJobDescription() async {
    final english = AppLocale.isEnglish(context);
    final targetJobTitle = _fields.jobTitle.text.trim();

    if (targetJobTitle.isEmpty) {
      AppSnackBar.warning(
        context,
        english
            ? 'Enter the target job title first.'
            : 'أدخل المسمى الوظيفي المستهدف أولاً.',
      );
      return;
    }
    if (!AiCvField.isSufficientForEnhancement(_fields.jobDescription.text)) {
      AppSnackBar.warning(
        context,
        english
            ? 'Enter more than a line of job description for accurate AI enhancement.'
            : 'اكتب أكثر من سطر في الوصف الوظيفي لتوجيه الذكاء الاصطناعي بدقة.',
      );
      return;
    }

    final outcome =
        await ref.read(jobDescriptionEnhancerProvider.notifier).enhance(
              targetJobTitle: targetJobTitle,
              jobDescription: _fields.jobDescription.text,
              language: _language,
            );
    // The controllers are disposed once the screen is gone; never write
    // into them after an await without this check.
    if (!mounted) return;

    switch (outcome) {
      case CvEnhanceStale():
        return;
      case CvEnhanceFailed(:final error):
        _showEnhanceError(
          error,
          english: english,
          onRetry: _enhanceJobDescription,
          fallback: english
              ? 'Could not enhance the job description. Try again.'
              : 'تعذر تحسين الوصف الوظيفي. حاول مرة أخرى.',
        );
      case CvEnhanced(:final text):
        // A real edit: goes through dirty tracking and autosave.
        _fields.jobDescription.text = text;
        AppSnackBar.success(
          context,
          english ? 'Job description enhanced.' : 'تم تحسين الوصف الوظيفي.',
        );
    }
  }

  void _showEnhanceError(
    Object error, {
    required bool english,
    required VoidCallback onRetry,
    required String fallback,
  }) {
    if (error is ApiException) {
      AppSnackBar.fromException(
        context,
        error,
        retryLabel: english ? 'Retry' : 'إعادة',
        onRetry: onRetry,
      );
    } else {
      AppSnackBar.error(context, fallback);
    }
  }

  // ── Build ─────────────────────────────────────────────────────────────────

  WizardStepProps _propsFor(int step, bool english, String? enhancingField) {
    return WizardStepProps(
      english: english,
      formKey: _stepFormKeys[step],
      submitted: _stepSubmitted[step],
      showErrorBanner: _stepShowBanner[step],
      onDismissErrorBanner: () => setState(() => _stepShowBanner[step] = false),
      fields: _fields,
      enhancingField: enhancingField,
      fieldResults: _fieldResults,
      onEnhance: (field) => unawaited(_enhanceField(field)),
      onDismissResult: (field) => setState(() => _fieldResults.remove(field)),
      onPrimaryAction: _onPrimaryAction,
    );
  }

  Widget _buildStep(bool english, String? enhancingField) {
    final props = _propsFor(_step, english, enhancingField);
    return switch (_step) {
      0 => PersonalStep(
          props: props,
          language: _language,
          onLanguageChanged: _setLanguage,
          isEnhancingJobDescription: ref.watch(jobDescriptionEnhancerProvider),
          onEnhanceJobDescription: () => unawaited(_enhanceJobDescription()),
        ),
      1 => SkillsStep(props: props, jobTitles: _jobTitles),
      2 => ExperienceStep(
          props: props,
          focusNode: _experienceFocusNode,
          showShapeHint: _showExperienceShapeHint,
          onDismissShapeHint: () => setState(() {
            _showExperienceShapeHint = false;
            _experienceHintDismissed = true;
          }),
          onOpenAchievementBuilder: () => _openAchievementBuilder(english),
        ),
      _ => EducationStep(props: props),
    };
  }

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    final isGenerating = ref.watch(cvGenerationControllerProvider);
    final enhancingField = ref.watch(cvFieldEnhancerProvider);
    final pendingDraft = _pendingDraft;
    final reduceMotion = MotionSettings.reduce(context);

    // Clean form: back pops normally. Dirty: intercept and ask first.
    return PopScope(
      canPop: !_isDirty,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) unawaited(_handleExitAttempt());
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(_isEditMode
              ? (english ? 'Edit CV' : 'تعديل السيرة')
              : (english ? 'Create CV' : 'إنشاء سيرة ذاتية')),
          actions: const [
            Padding(
              padding: EdgeInsetsDirectional.only(end: 12),
              child: LanguageToggle(),
            ),
          ],
        ),
        body: Column(
          children: [
            WizardHeader(step: _step, english: english, onStepTap: _goToStep),
            if (pendingDraft != null && !_isEditMode)
              DraftRestoreBanner(
                english: english,
                savedAt: pendingDraft.savedAt,
                onStartFresh: () => unawaited(_startFresh()),
                onRestore: _restoreDraft,
              ),
            Expanded(
              child: AnimatedSwitcher(
                duration: reduceMotion ? Duration.zero : MotionDurations.slow,
                switchInCurve: MotionCurves.enter,
                switchOutCurve: MotionCurves.exit,
                transitionBuilder: (child, animation) {
                  if (reduceMotion) return child;
                  final curved = CurvedAnimation(
                    parent: animation,
                    curve: MotionCurves.enter,
                    reverseCurve: MotionCurves.exit,
                  );
                  return FadeTransition(
                    opacity: curved,
                    child: SlideTransition(
                      position: Tween<Offset>(
                        begin: MotionAxis.slideIn(
                          context: context,
                          distance: 0.05,
                          direction: _stepDirection,
                        ),
                        end: Offset.zero,
                      ).animate(curved),
                      child: child,
                    ),
                  );
                },
                child: ListView(
                  key: ValueKey('cv-step-$_step'),
                  padding: const EdgeInsets.all(20),
                  children: [_buildStep(english, enhancingField)],
                ),
              ),
            ),
            WizardFooter(
              english: english,
              step: _step,
              stepCount: _stepCount,
              isEditMode: _isEditMode,
              isLoading: isGenerating,
              onBack: () => _goToStep(_step - 1),
              onPrimary: _onPrimaryAction,
            ),
          ],
        ),
      ),
    );
  }
}
