import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../theme/app_theme.dart';
import '../services/api_exception.dart';
import '../services/cv_api_service.dart';
import '../services/mobile_content_service.dart';
import '../app_locale.dart';
import '../models/generated_cv.dart';
import '../services/notification_engagement_service.dart';
import '../widgets/app_snack_bar.dart';
import '../widgets/form_fields.dart';
import '../widgets/language_toggle.dart';
import '../widgets/loading/ai_field_loading_overlay.dart';
import '../widgets/motion.dart';
import '../widgets/submit_button.dart';
import '../widgets/success_beat.dart';
import 'generated_cv_screen.dart';

class CvGeneratorScreen extends StatefulWidget {
  final GeneratedCv? initialCv;

  const CvGeneratorScreen({super.key, this.initialCv});

  @override
  State<CvGeneratorScreen> createState() => _CvGeneratorScreenState();
}

class _CvGeneratorScreenState extends State<CvGeneratorScreen> {
  int _step = 0;

  /// +1 when advancing a step, -1 when going back (drives slide direction).
  int _stepDirection = 1;
  bool _isLoading = false;
  bool _isEnhancingJobDescription = false;
  String _language = 'ar';
  final _apiService = CvApiService();

  // Per-step form keys so validate() only checks fields on the current step.
  final _stepFormKeys = List.generate(4, (_) => GlobalKey<FormState>());

  // Whether the user has attempted to advance from the current step —
  // controls autovalidateMode and the top banner.
  final _stepSubmitted = List<bool>.filled(4, false);
  final _stepShowBanner = List<bool>.filled(4, false);

  final _nameCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _linkedinCtrl = TextEditingController();
  final _locationCtrl = TextEditingController();
  final _jobTitleCtrl = TextEditingController();
  final _jobDescriptionCtrl = TextEditingController();
  final _summaryCtrl = TextEditingController();
  final _skillsCtrl = TextEditingController();
  final _experienceCtrl = TextEditingController();
  final _educationCtrl = TextEditingController();
  final _certsCtrl = TextEditingController();

  static const _steps = ['الشخصية', 'المهارات', 'الخبرات', 'التعليم'];

  bool get _isEditMode => widget.initialCv != null;

  @override
  void initState() {
    super.initState();
    final cv = widget.initialCv;
    if (cv == null) return;

    _nameCtrl.text = cv.fullName;
    _emailCtrl.text = cv.email ?? '';
    _phoneCtrl.text = cv.phone ?? '';
    _linkedinCtrl.text = cv.linkedin ?? '';
    _locationCtrl.text = cv.location ?? '';
    _jobTitleCtrl.text = cv.targetJobTitle;
    _jobDescriptionCtrl.text = cv.jobDescriptionInput ?? '';
    _language = cv.language;
    _summaryCtrl.text = cv.summaryInput ?? '';
    _skillsCtrl.text = cv.skillsInput;
    _experienceCtrl.text = cv.experienceInput;
    _educationCtrl.text = cv.educationInput;
    _certsCtrl.text = cv.certificationsInput ?? '';
  }

  @override
  void dispose() {
    for (final c in [
      _nameCtrl,
      _emailCtrl,
      _phoneCtrl,
      _linkedinCtrl,
      _locationCtrl,
      _jobTitleCtrl,
      _jobDescriptionCtrl,
      _summaryCtrl,
      _skillsCtrl,
      _experienceCtrl,
      _educationCtrl,
      _certsCtrl
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    final english = AppLocale.isEnglish(context);
    setState(() => _isLoading = true);

    try {
      final payload = {
        'full_name': _nameCtrl.text.trim(),
        'email': _nullable(_emailCtrl.text),
        'phone': _nullable(_phoneCtrl.text),
        'linkedin': _nullable(_linkedinCtrl.text),
        'location': _nullable(_locationCtrl.text),
        'target_job_title': _jobTitleCtrl.text.trim(),
        'job_description_input': _nullable(_jobDescriptionCtrl.text),
        'language': _language,
        'summary_input': _nullable(_summaryCtrl.text),
        'skills_input': _skillsCtrl.text.trim(),
        'experience_input': _experienceCtrl.text.trim(),
        'education_input': _educationCtrl.text.trim(),
        'certifications_input': _nullable(_certsCtrl.text),
      };

      final generatedCv = _isEditMode
          ? await _apiService.updateGeneratedCv(widget.initialCv!.id, payload)
          : await _apiService.generateCv(payload);

      if (!mounted) return;
      MobileContentService.invalidateCvRelated();
      if (!_isEditMode) {
        NotificationEngagementService.instance.reportConversion('cv_generated');
      }
      // Success check + haptic, then navigate (instant when reduced motion).
      await SuccessBeat.play(context);
      if (!mounted) return;
      Navigator.of(context).push(
        MaterialPageRoute(
            builder: (_) => GeneratedCvScreen(generatedCv: generatedCv)),
      );
    } on ApiException catch (exception) {
      if (mounted) {
        AppSnackBar.fromException(
          context,
          exception,
          retryLabel: english ? 'Retry' : 'إعادة',
          onRetry: _submit,
        );
      }
    } catch (_) {
      if (mounted) {
        _showError(english
            ? 'An unexpected error occurred while generating the CV.'
            : 'حدث خطأ غير متوقع أثناء توليد السيرة.');
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  String? _nullable(String value) {
    final trimmed = value.trim();
    return trimmed.isEmpty ? null : trimmed;
  }

  Future<void> _enhanceJobDescription() async {
    final english = AppLocale.isEnglish(context);
    final targetJobTitle = _jobTitleCtrl.text.trim();

    if (targetJobTitle.isEmpty) {
      AppSnackBar.warning(
        context,
        english
            ? 'Enter the target job title first.'
            : 'أدخل المسمى الوظيفي المستهدف أولاً.',
      );
      return;
    }

    setState(() => _isEnhancingJobDescription = true);

    try {
      final data = await _apiService.enhanceJobDescription(
        targetJobTitle: targetJobTitle,
        jobDescription: _jobDescriptionCtrl.text.trim(),
        language: _language,
      );
      final enhanced = data['enhanced_description']?.toString() ?? '';
      if (enhanced.isEmpty) return;
      _jobDescriptionCtrl.text = enhanced;
      if (mounted) {
        AppSnackBar.success(
          context,
          english ? 'Job description enhanced.' : 'تم تحسين الوصف الوظيفي.',
        );
      }
    } on ApiException catch (exception) {
      if (mounted) {
        AppSnackBar.fromException(
          context,
          exception,
          retryLabel: english ? 'Retry' : 'إعادة',
          onRetry: _enhanceJobDescription,
        );
      }
    } finally {
      if (mounted) setState(() => _isEnhancingJobDescription = false);
    }
  }

  bool _validateCurrentStep() {
    setState(() {
      _stepSubmitted[_step] = true;
      _stepShowBanner[_step] = false;
    });

    final formState = _stepFormKeys[_step].currentState;
    final ok = formState?.validate() ?? true;
    if (!ok) {
      setState(() => _stepShowBanner[_step] = true);
      HapticFeedback.selectionClick();
    }
    return ok;
  }

  void _showError(String message) {
    AppSnackBar.error(context, message);
  }

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);

    return Scaffold(
      appBar: AppBar(
        // System back (auto-mirrors in RTL) when this screen is pushed.
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
      // resizeToAvoidBottomInset (default true) keeps the footer above the keyboard.
      body: Column(
        children: [
          Builder(builder: (context) {
            final steps = english
                ? ['Personal', 'Skills', 'Experience', 'Education']
                : _steps;
            final progress = (_step + 1) / steps.length;

            return Container(
              color: context.sirati.background,
              padding: const EdgeInsets.fromLTRB(20, 4, 20, 14),
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        english
                            ? 'Step ${_step + 1} of ${steps.length}'
                            : 'خطوة ${_step + 1} من ${steps.length}',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w700,
                          color: context.sirati.primary,
                        ),
                      ),
                      Text(
                        steps[_step],
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: context.sirati.textSecondary,
                        ),
                      ),
                    ],
                  ),
                  SizedBox(height: 12),
                  _WizardProgressBar(progress: progress),
                  SizedBox(height: 14),
                  Row(
                    children: List.generate(steps.length * 2 - 1, (i) {
                      if (i.isOdd) {
                        final done = i ~/ 2 < _step;
                        return Expanded(
                            child: Container(
                                height: 2,
                                color: done
                                    ? context.sirati.primaryContainer
                                    : context.sirati.surfaceHigh));
                      }
                      final idx = i ~/ 2;
                      final done = idx < _step;
                      final active = idx == _step;
                      final canJump = idx <= _step;
                      return PressScale(
                        enabled: canJump,
                        child: GestureDetector(
                          onTap: canJump && idx != _step
                              ? () => setState(() {
                                    _stepDirection = idx < _step ? -1 : 1;
                                    _step = idx;
                                  })
                              : null,
                          child: AnimatedContainer(
                            duration: MotionSettings.reduce(context)
                                ? Duration.zero
                                : MotionDurations.medium,
                            curve: MotionCurves.state,
                            width: 32,
                            height: 32,
                            decoration: BoxDecoration(
                              color: done
                                  ? context.sirati.primaryContainer
                                  : active
                                      ? context.sirati.amberAccent
                                      : context.sirati.surfaceHigh,
                              shape: BoxShape.circle,
                            ),
                            child: Center(
                              child: done
                                  ? Icon(Icons.check_rounded,
                                      size: 16, color: Colors.white)
                                  : FittedBox(
                                      fit: BoxFit.scaleDown,
                                      child: Text('${idx + 1}',
                                          style: TextStyle(
                                              fontSize: 12,
                                              fontWeight: FontWeight.w700,
                                              color: active
                                                  ? context.sirati.primaryDark
                                                  : context.sirati.textHint)),
                                    ),
                            ),
                          ),
                        ),
                      );
                    }),
                  ),
                  SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: steps
                        .asMap()
                        .entries
                        .map((e) => Text(
                              e.value,
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w600,
                                color: e.key <= _step
                                    ? context.sirati.primary
                                    : context.sirati.textHint,
                              ),
                            ))
                        .toList(),
                  ),
                ],
              ),
            );
          }),

          // ── Step content (buttons live in the fixed footer below) ──
          Expanded(
            child: AnimatedSwitcher(
              duration: MotionSettings.reduce(context)
                  ? Duration.zero
                  : MotionDurations.slow,
              switchInCurve: MotionCurves.enter,
              switchOutCurve: MotionCurves.exit,
              transitionBuilder: (child, animation) {
                if (MotionSettings.reduce(context)) return child;
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
                // Footer is outside the scroll view — modest bottom inset only.
                padding: const EdgeInsets.fromLTRB(20, 20, 20, 20),
                children: [
                  if (_step == 0) _buildStep0(english),
                  if (_step == 1) _buildStep1(english),
                  if (_step == 2) _buildStep2(english),
                  if (_step == 3) _buildStep3(english),
                ],
              ),
            ),
          ),

          // ── Fixed CTA bar (stable across step transitions) ──
          SafeArea(
            top: false,
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
              decoration: BoxDecoration(
                color: context.sirati.surface,
                border: Border(
                  top: BorderSide(color: context.sirati.border),
                ),
                boxShadow: [
                  BoxShadow(
                    color: Color(0x0D000000),
                    blurRadius: 12,
                    offset: Offset(0, -4),
                  ),
                ],
              ),
              child: Row(
                children: [
                  if (_step > 0) ...[
                    Expanded(
                      child: PressScale(
                        child: OutlinedButton.icon(
                          onPressed: () => setState(() {
                            _stepDirection = -1;
                            _step--;
                          }),
                          icon: Icon(
                            Icons.arrow_back_rounded,
                            size: 18,
                          ),
                          label: Text(english ? 'Back' : 'السابق'),
                        ),
                      ),
                    ),
                    SizedBox(width: 12),
                  ],
                  Expanded(
                    flex: 2,
                    child: SubmitButton(
                      label: _step == _steps.length - 1
                          ? (_isEditMode
                              ? (english ? 'Update CV' : 'تحديث السيرة')
                              : (english ? 'Generate CV' : 'توليد السيرة'))
                          : (english ? 'Next' : 'التالي'),
                      loadingLabel:
                          english ? 'Generating...' : 'جارٍ التوليد...',
                      isLoading: _isLoading,
                      icon: _step == _steps.length - 1
                          ? Icons.auto_awesome
                          : Icons.arrow_forward_rounded,
                      onPressed: () {
                        if (!_validateCurrentStep()) return;
                        if (_step < _steps.length - 1) {
                          setState(() {
                            _stepDirection = 1;
                            _step++;
                          });
                        } else {
                          _submit();
                        }
                      },
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _fieldGroup(String label, Widget field, bool english) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: double.infinity,
          child: Text(label,
              textAlign: TextAlign.start,
              style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: context.sirati.textSecondary)),
        ),
        SizedBox(height: 6),
        field,
      ],
    );
  }

  /// Autovalidate mode derived from whether the user has attempted to
  /// advance the given step. Fields validate on every keystroke *after*
  /// the first Next tap, so the UI feels responsive without being noisy
  /// on initial focus.
  AutovalidateMode _autoValidateFor(int step) => _stepSubmitted[step]
      ? AutovalidateMode.onUserInteraction
      : AutovalidateMode.disabled;

  /// Localised copy for the sticky "please fix the errors below" banner
  /// shown when a step fails validation.
  String _bannerCopy(bool english) => english
      ? 'Please review the highlighted fields before continuing.'
      : 'يرجى مراجعة الحقول المميزة قبل المتابعة.';

  Widget _buildStep0(bool english) {
    return Form(
      key: _stepFormKeys[0],
      autovalidateMode: _autoValidateFor(0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SectionHeading(
            text: english ? 'Personal Information' : 'المعلومات الشخصية',
            english: english,
          ),
          SizedBox(height: 18),
          if (_stepShowBanner[0])
            AppFormErrorBanner(
              message: _bannerCopy(english),
              onDismiss: () => setState(() => _stepShowBanner[0] = false),
            ),
          _fieldGroup(
              english ? 'Full Name *' : 'الاسم الكامل *',
              AppTextFormField(
                controller: _nameCtrl,
                textAlign: TextAlign.start,
                hintText: english ? 'Salem Sayer' : 'سالم سيار',
                prefixIcon: Icon(Icons.person_outline),
                validator: (value) => (value?.trim().isEmpty ?? true)
                    ? (english
                        ? 'Full name is required.'
                        : 'الاسم الكامل مطلوب.')
                    : null,
              ),
              english),
          SizedBox(height: 14),
          _fieldGroup(
              english ? 'Email' : 'البريد الإلكتروني',
              AppTextFormField(
                controller: _emailCtrl,
                keyboardType: TextInputType.emailAddress,
                textDirection: TextDirection.ltr,
                textAlign: TextAlign.start,
                hintText: 'salem@example.com',
                prefixIcon: Icon(Icons.email_outlined),
                validator: (value) {
                  final email = value?.trim() ?? '';
                  if (email.isEmpty) return null; // optional
                  if (!email.contains('@')) {
                    return english
                        ? 'Enter a valid email address.'
                        : 'أدخل بريداً إلكترونياً صحيحاً.';
                  }
                  return null;
                },
              ),
              english),
          SizedBox(height: 14),
          _fieldGroup(
              english ? 'Phone' : 'رقم الهاتف',
              AppTextFormField(
                controller: _phoneCtrl,
                keyboardType: TextInputType.phone,
                textDirection: TextDirection.ltr,
                textAlign: TextAlign.start,
                hintText: '+966 5X XXX XXXX',
                prefixIcon: Icon(Icons.phone_outlined),
              ),
              english),
          SizedBox(height: 14),
          _fieldGroup(
              english ? 'LinkedIn URL' : 'رابط LinkedIn',
              AppTextFormField(
                controller: _linkedinCtrl,
                textDirection: TextDirection.ltr,
                textAlign: TextAlign.start,
                hintText: 'linkedin.com/in/username',
                prefixIcon: Icon(Icons.link_rounded),
              ),
              english),
          SizedBox(height: 14),
          _fieldGroup(
              english ? 'Target Job Title *' : 'المسمى الوظيفي المستهدف *',
              AppTextFormField(
                controller: _jobTitleCtrl,
                textAlign: TextAlign.start,
                hintText: 'Laravel Backend Developer',
                prefixIcon: Icon(Icons.work_outline_rounded),
                validator: (value) => (value?.trim().isEmpty ?? true)
                    ? (english
                        ? 'Target job title is required.'
                        : 'المسمى الوظيفي المستهدف مطلوب.')
                    : null,
              ),
              english),
          SizedBox(height: 14),
          _fieldGroup(
              english
                  ? 'Job Description (optional)'
                  : 'الوصف الوظيفي (اختياري)',
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  AiFieldLoadingOverlay(
                    isLoading: _isEnhancingJobDescription,
                    statusMessages:
                        AiFieldLoadingOverlay.defaultStatusMessages(
                      english: english,
                    ),
                    semanticsLabel: english
                        ? 'Enhancing job description'
                        : 'جارٍ تحسين الوصف الوظيفي',
                    child: AppTextFormField(
                      controller: _jobDescriptionCtrl,
                      textAlign: TextAlign.start,
                      maxLines: 5,
                      enabled: !_isEnhancingJobDescription,
                      hintText: english
                          ? 'Paste the job description or let Sirati complete it from the role...'
                          : 'الصق الوصف الوظيفي أو دع سيرتي يكمله من المسمى...',
                      prefixIcon: Icon(Icons.assignment_outlined),
                    ),
                  ),
                  SizedBox(height: 10),
                  SubmitButton(
                    label: english ? 'Enhance' : 'تحسين',
                    loadingLabel: english ? 'Enhancing...' : 'جارٍ التحسين...',
                    isLoading: _isEnhancingJobDescription,
                    outlined: true,
                    height: 44,
                    icon: Icons.auto_fix_high_rounded,
                    onPressed: _isEnhancingJobDescription
                        ? null
                        : _enhanceJobDescription,
                  ),
                ],
              ),
              english),
          SizedBox(height: 18),
          _FieldLabel(
            text: english ? 'CV Language' : 'لغة السيرة الذاتية',
            english: english,
          ),
          SizedBox(height: 8),
          Container(
            decoration: BoxDecoration(
              color: context.sirati.surface,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: context.sirati.border),
            ),
            child: Row(
              children: [
                Expanded(
                    child: _LangOption(
                        label: 'English',
                        value: 'en',
                        selected: _language == 'en',
                        onTap: () => setState(() => _language = 'en'))),
                Expanded(
                    child: _LangOption(
                        label: 'العربية',
                        value: 'ar',
                        selected: _language == 'ar',
                        onTap: () => setState(() => _language = 'ar'))),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStep1(bool english) {
    return Form(
      key: _stepFormKeys[1],
      autovalidateMode: _autoValidateFor(1),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SectionHeading(
            text: english ? 'Skills & Summary' : 'المهارات والملخص',
            english: english,
          ),
          SizedBox(height: 6),
          _HelperText(
            text: english
                ? 'Enter skills separated by commas'
                : 'أدخل مهاراتك مفصولة بفاصلة',
            english: english,
          ),
          SizedBox(height: 18),
          if (_stepShowBanner[1])
            AppFormErrorBanner(
              message: _bannerCopy(english),
              onDismiss: () => setState(() => _stepShowBanner[1] = false),
            ),
          _fieldGroup(
              english ? 'Core Skills *' : 'المهارات الأساسية *',
              AppTextFormField(
                controller: _skillsCtrl,
                textAlign: TextAlign.start,
                maxLines: 4,
                hintText: 'PHP, Laravel, API, SQL, Git, Agile, Docker',
                validator: (value) => (value?.trim().isEmpty ?? true)
                    ? (english
                        ? 'Core skills are required.'
                        : 'المهارات الأساسية مطلوبة.')
                    : null,
              ),
              english),
          SizedBox(height: 14),
          _fieldGroup(
              english
                  ? 'Professional Summary (optional)'
                  : 'الملخص المهني (اختياري، سيُولَّد تلقائياً)',
              AppTextFormField(
                controller: _summaryCtrl,
                textAlign: TextAlign.start,
                maxLines: 4,
                hintText: english
                    ? 'Briefly describe your experience and achievements...'
                    : 'نبذة مختصرة عن خبرتك وإنجازاتك...',
              ),
              english),
        ],
      ),
    );
  }

  Widget _buildStep2(bool english) {
    return Form(
      key: _stepFormKeys[2],
      autovalidateMode: _autoValidateFor(2),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SectionHeading(
            text: english ? 'Work Experience' : 'الخبرات العملية',
            english: english,
          ),
          SizedBox(height: 6),
          _HelperText(
            text: english
                ? 'Include title, company, dates, and measurable achievements'
                : 'اذكر المسمى، الشركة، التاريخ، والإنجازات بأرقام',
            english: english,
          ),
          SizedBox(height: 18),
          if (_stepShowBanner[2])
            AppFormErrorBanner(
              message: _bannerCopy(english),
              onDismiss: () => setState(() => _stepShowBanner[2] = false),
            ),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
                color: context.sirati.primaryLight,
                borderRadius: BorderRadius.circular(12)),
            child: Row(
              children: [
                Expanded(
                    child: Text(
                        english
                            ? 'Numbers like 35% or 20 users improve your ATS score.'
                            : 'كلما ذكرت أرقاماً (35%، 20 مستخدم)، زادت درجة ATS',
                        style: TextStyle(
                            fontSize: 12, color: context.sirati.primaryDark),
                        textAlign: TextAlign.start)),
                SizedBox(width: 8),
                Icon(Icons.tips_and_updates_outlined,
                    color: context.sirati.primary, size: 20),
              ],
            ),
          ),
          SizedBox(height: 12),
          AppTextFormField(
            controller: _experienceCtrl,
            textAlign: TextAlign.start,
            maxLines: 10,
            hintText:
                'مطور Backend، شركة X، 2021–2025\n- طورت APIs تستخدمها 25 فرقة داخلية\n- حسّنت أداء SQL بنسبة 35%\n- بنيت تكاملات API خفّضت الإدخال 20%',
            validator: (value) {
              final text = value?.trim() ?? '';
              if (text.length < 80) {
                return english
                    ? 'Write at least 80 characters about your experience.'
                    : 'اكتب الخبرات العملية بتفاصيل لا تقل عن 80 حرفاً.';
              }
              return null;
            },
          ),
        ],
      ),
    );
  }

  Widget _buildStep3(bool english) {
    return Form(
      key: _stepFormKeys[3],
      autovalidateMode: _autoValidateFor(3),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SectionHeading(
            text: english ? 'Education & Certifications' : 'التعليم والشهادات',
            english: english,
          ),
          SizedBox(height: 18),
          if (_stepShowBanner[3])
            AppFormErrorBanner(
              message: _bannerCopy(english),
              onDismiss: () => setState(() => _stepShowBanner[3] = false),
            ),
          _fieldGroup(
              english ? 'Education *' : 'التعليم *',
              AppTextFormField(
                controller: _educationCtrl,
                textAlign: TextAlign.start,
                maxLines: 4,
                hintText: 'بكالوريوس علوم الحاسب، جامعة الملك عبدالعزيز، 2020',
                validator: (value) => (value?.trim().isEmpty ?? true)
                    ? (english ? 'Education is required.' : 'التعليم مطلوب.')
                    : null,
              ),
              english),
          SizedBox(height: 14),
          _fieldGroup(
              english
                  ? 'Certifications & Courses (optional)'
                  : 'الشهادات والدورات (اختياري)',
              AppTextFormField(
                controller: _certsCtrl,
                textAlign: TextAlign.start,
                maxLines: 4,
                hintText:
                    'AWS Certified Cloud Practitioner، 2023\nGoogle Cloud Associate، 2022',
              ),
              english),
        ],
      ),
    );
  }
}

class _SectionHeading extends StatelessWidget {
  final String text;
  final bool english;

  const _SectionHeading({required this.text, required this.english});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: Text(
        text,
        textAlign: TextAlign.start,
        style: TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.w800,
            color: context.sirati.primaryDark),
      ),
    );
  }
}

class _FieldLabel extends StatelessWidget {
  final String text;
  final bool english;

  const _FieldLabel({required this.text, required this.english});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: Text(
        text,
        textAlign: TextAlign.start,
        style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: context.sirati.textSecondary),
      ),
    );
  }
}

/// Thin wizard progress fill — grows from the start edge (LTR left / RTL right).
class _WizardProgressBar extends StatefulWidget {
  final double progress;

  const _WizardProgressBar({required this.progress});

  @override
  State<_WizardProgressBar> createState() => _WizardProgressBarState();
}

class _WizardProgressBarState extends State<_WizardProgressBar> {
  /// Stable tween so rebuilds do not restart; begin tracks last settled value.
  late Tween<double> _tween =
      Tween<double>(begin: 0, end: widget.progress.clamp(0.0, 1.0));

  @override
  void didUpdateWidget(covariant _WizardProgressBar oldWidget) {
    super.didUpdateWidget(oldWidget);
    final next = widget.progress.clamp(0.0, 1.0);
    final prev = oldWidget.progress.clamp(0.0, 1.0);
    if (next != prev) {
      _tween = Tween<double>(begin: prev, end: next);
    }
  }

  @override
  Widget build(BuildContext context) {
    final target = widget.progress.clamp(0.0, 1.0);
    final reduce = MotionSettings.reduce(context);

    Widget fill(double factor) {
      return Align(
        alignment: AlignmentDirectional.centerStart,
        child: FractionallySizedBox(
          widthFactor: factor,
          heightFactor: 1,
          child: ColoredBox(color: context.sirati.primary),
        ),
      );
    }

    return ClipRRect(
      borderRadius: BorderRadius.circular(999),
      child: SizedBox(
        height: 4,
        width: double.infinity,
        child: ColoredBox(
          color: context.sirati.border,
          child: reduce
              ? fill(target)
              : TweenAnimationBuilder<double>(
                  // Key forces a new controller when the target step changes.
                  key: ValueKey(target),
                  tween: _tween,
                  duration: MotionDurations.slow,
                  curve: MotionCurves.state,
                  builder: (context, value, _) => fill(value),
                ),
        ),
      ),
    );
  }
}

class _HelperText extends StatelessWidget {
  final String text;
  final bool english;

  const _HelperText({required this.text, required this.english});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: Text(
        text,
        textAlign: TextAlign.start,
        style: TextStyle(fontSize: 13, color: context.sirati.textSecondary),
      ),
    );
  }
}

class _LangOption extends StatelessWidget {
  final String label, value;
  final bool selected;
  final VoidCallback onTap;

  const _LangOption(
      {required this.label,
      required this.value,
      required this.selected,
      required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          color: selected ? context.sirati.primary : Colors.transparent,
          borderRadius: BorderRadius.circular(11),
        ),
        alignment: Alignment.center,
        child: Text(
          label,
          style: TextStyle(
            fontSize: 14,
            fontWeight: selected ? FontWeight.w700 : FontWeight.w400,
            color: selected ? Colors.white : context.sirati.textSecondary,
          ),
        ),
      ),
    );
  }
}
