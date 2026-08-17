$utf8 = [System.Text.UTF8Encoding]::new($false)

$path = Resolve-Path 'flutter_app\lib\services\cv_api_service.dart'
$text = [System.IO.File]::ReadAllText($path)
$needle = '  Future<Map<String, dynamic>> enhanceJobDescription({'
$method = @'
  Future<Map<String, dynamic>> enhanceCvField({
    required String field,
    required String draft,
    required String jobTitle,
    required String language,
  }) async {
    final response = await _apiClient.postJson(
      '/generated-cvs/enhance-field',
      {
        'field': field,
        'draft': draft,
        'job_title': jobTitle,
        'language': language,
      },
    );

    final data = response['data'];
    return data is Map<String, dynamic> ? data : const {};
  }

'@
$text = $text.Replace($needle, $method + $needle)
[System.IO.File]::WriteAllText($path, $text, $utf8)

$path = Resolve-Path 'flutter_app\lib\screens\cv_generator_screen.dart'
$text = [System.IO.File]::ReadAllText($path)
$text = $text.Replace(
    "import '../widgets/app_snack_bar.dart';",
    "import '../widgets/app_snack_bar.dart';`r`nimport '../widgets/ai_cv_field.dart';"
)
$text = $text.Replace(
    "  final GeneratedCv? initialCv;`r`n`r`n  const CvGeneratorScreen({super.key, this.initialCv});",
    "  final GeneratedCv? initialCv;`r`n  final CvApiService? apiService;`r`n`r`n  const CvGeneratorScreen({super.key, this.initialCv, this.apiService});"
)
$text = $text.Replace(
    "  bool _isEnhancingJobDescription = false;",
    "  bool _isEnhancingJobDescription = false;`r`n  String? _enhancingCvField;`r`n  final Map<String, Map<String, dynamic>> _fieldResults = {};`r`n  bool _showExperienceShapeHint = false;`r`n  bool _experienceHintDismissed = false;"
)
$text = $text.Replace(
    "  final _apiService = CvApiService();",
    "  late final CvApiService _apiService = widget.apiService ?? CvApiService();"
)
$text = $text.Replace(
    "  final _certsCtrl = TextEditingController();",
    "  final _certsCtrl = TextEditingController();`r`n  final _experienceFocusNode = FocusNode();"
)
$needle = "    for (final c in _allControllers) {`r`n      c.addListener(_onAnyFieldChanged);`r`n    }"
$replacement = $needle + @'

    _experienceFocusNode.addListener(_onExperienceFocusChanged);
'@
$text = $text.Replace($needle, $replacement)
$needle = "    for (final c in _allControllers) {`r`n      c.removeListener(_onAnyFieldChanged);`r`n      c.dispose();`r`n    }"
$replacement = $needle + "`r`n    _experienceFocusNode.removeListener(_onExperienceFocusChanged);`r`n    _experienceFocusNode.dispose();"
$text = $text.Replace($needle, $replacement)

$needle = '  void _onAnyFieldChanged() {'
$method = @'
  void _onExperienceFocusChanged() {
    if (!_experienceFocusNode.hasFocus ||
        _experienceCtrl.text.trim().isNotEmpty ||
        _experienceHintDismissed ||
        !mounted) {
      return;
    }
    setState(() => _showExperienceShapeHint = true);
  }

'@
$text = $text.Replace($needle, $method + $needle)

$needle = '  Future<void> _enhanceJobDescription() async {'
$method = @'
  Future<void> _enhanceCvField(
    String field,
    TextEditingController controller,
  ) async {
    final english = AppLocale.isEnglish(context);
    final draft = controller.text;
    final jobTitle = _jobTitleCtrl.text.trim();

    if (draft.trim().length < 10) return;
    if (jobTitle.isEmpty) {
      AppSnackBar.warning(
        context,
        english
            ? 'Enter the target job title first.'
            : 'أدخل المسمى الوظيفي المستهدف أولاً.',
      );
      return;
    }

    final requestId = ++_aiRequestGen;
    setState(() {
      _enhancingCvField = field;
      _fieldResults.remove(field);
    });

    try {
      final result = await _apiService.enhanceCvField(
        field: field,
        draft: draft.trim(),
        jobTitle: jobTitle,
        language: _language,
      );
      if (!mounted || requestId != _aiRequestGen) return;

      final enhanced = result['enhanced_text']?.toString().trim() ?? '';
      if (enhanced.isEmpty) {
        throw const ApiException(
          'لم يُرجع التحسين نصاً صالحاً.',
          type: ApiErrorType.unknown,
        );
      }

      _suppressDirtyTracking = true;
      controller.text = enhanced;
      controller.selection = TextSelection.collapsed(offset: enhanced.length);
      _suppressDirtyTracking = false;
      _markDirty();

      setState(() {
        _enhancingCvField = null;
        _fieldResults[field] = result;
      });

      AppSnackBar.show(
        context,
        message: english
            ? 'Field enhanced. Review the missing facts before continuing.'
            : 'تم تحسين الحقل. راجع المعلومات الناقصة قبل المتابعة.',
        variant: AppSnackBarVariant.success,
        actionLabel: english ? 'Undo' : 'تراجع',
        onAction: () {
          if (!mounted) return;
          _suppressDirtyTracking = true;
          controller.text = draft;
          controller.selection = TextSelection.collapsed(offset: draft.length);
          _suppressDirtyTracking = false;
          _markDirty();
          setState(() => _fieldResults.remove(field));
        },
      );
    } on ApiException catch (exception) {
      if (!mounted || requestId != _aiRequestGen) return;
      setState(() => _enhancingCvField = null);
      AppSnackBar.fromException(
        context,
        exception,
        retryLabel: english ? 'Retry' : 'إعادة',
        onRetry: () => _enhanceCvField(field, controller),
      );
    } catch (_) {
      if (!mounted || requestId != _aiRequestGen) return;
      setState(() => _enhancingCvField = null);
      AppSnackBar.error(
        context,
        english
            ? 'Could not enhance this field. Try again.'
            : 'تعذر تحسين هذا الحقل. حاول مرة أخرى.',
      );
    }
  }

'@
$text = $text.Replace($needle, $method + $needle)

$step1 = @'
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
          const SizedBox(height: 18),
          if (_stepShowBanner[1])
            AppFormErrorBanner(
              message: _bannerCopy(english),
              onDismiss: () => setState(() => _stepShowBanner[1] = false),
            ),
          _fieldGroup(
            english ? 'Core Skills *' : 'المهارات الأساسية *',
            AiCvField(
              field: 'skills',
              controller: _skillsCtrl,
              english: english,
              isLoading: _enhancingCvField == 'skills',
              helperText: english
                  ? 'Example: Laravel, PHP, REST APIs, SQL, Git, Docker.'
                  : 'مثال: Laravel، PHP، REST APIs، SQL، Git، Docker.',
              result: _fieldResults['skills'],
              onEnhance: () => _enhanceCvField('skills', _skillsCtrl),
              onDismissResult: () =>
                  setState(() => _fieldResults.remove('skills')),
              child: AppTextFormField(
                controller: _skillsCtrl,
                textAlign: TextAlign.start,
                maxLines: 4,
                textInputAction: TextInputAction.next,
                onFieldSubmitted: (_) => _focusNext(),
                enabled: _enhancingCvField != 'skills',
                hintText: english
                    ? 'PHP, Laravel, API, SQL, Git, Agile, Docker'
                    : 'PHP، Laravel، API، SQL، Git، Agile، Docker',
                validator: (value) => (value?.trim().isEmpty ?? true)
                    ? (english
                        ? 'Core skills are required.'
                        : 'المهارات الأساسية مطلوبة.')
                    : null,
              ),
            ),
            english,
          ),
          const SizedBox(height: 18),
          _fieldGroup(
            english
                ? 'Professional Summary (optional)'
                : 'الملخص المهني (اختياري)',
            AiCvField(
              field: 'summary',
              controller: _summaryCtrl,
              english: english,
              isLoading: _enhancingCvField == 'summary',
              helperText: english
                  ? 'Example: Backend developer focused on reliable APIs and measurable product outcomes.'
                  : 'مثال: مطور Backend متخصص في بناء APIs موثوقة وتحقيق نتائج قابلة للقياس.',
              result: _fieldResults['summary'],
              onEnhance: () => _enhanceCvField('summary', _summaryCtrl),
              onDismissResult: () =>
                  setState(() => _fieldResults.remove('summary')),
              child: AppTextFormField(
                controller: _summaryCtrl,
                textAlign: TextAlign.start,
                maxLines: 4,
                textInputAction: TextInputAction.done,
                onFieldSubmitted: (_) => _onPrimaryAction(),
                enabled: _enhancingCvField != 'summary',
                hintText: english
                    ? 'Briefly describe your experience and achievements...'
                    : 'نبذة مختصرة عن خبرتك وإنجازاتك...',
              ),
            ),
            english,
          ),
        ],
      ),
    );
  }

'@
$text = [regex]::Replace(
    $text,
    '(?s)  Widget _buildStep1\(bool english\) \{.*?(?=  Widget _buildStep2)',
    $step1
)

$step2 = @'
  Widget _buildStep2(bool english) {
    final hint = _showExperienceShapeHint
        ? Container(
            key: const Key('experience_shape_hint'),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: context.sirati.infoLight,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.lightbulb_outline_rounded,
                    size: 19, color: context.sirati.info),
                const SizedBox(width: 9),
                Expanded(
                  child: Text(
                    english
                        ? 'Target shape: role — company — period, then one or two achievements with numbers.'
                        : 'الشكل المستهدف: المسمى — الشركة — الفترة، ثم إنجاز أو اثنان بأرقام.',
                    style: TextStyle(
                      color: context.sirati.textPrimary,
                      fontSize: 12.5,
                      height: 1.45,
                    ),
                  ),
                ),
                IconButton(
                  tooltip: english ? 'Dismiss' : 'إخفاء',
                  visualDensity: VisualDensity.compact,
                  onPressed: () => setState(() {
                    _showExperienceShapeHint = false;
                    _experienceHintDismissed = true;
                  }),
                  icon: const Icon(Icons.close_rounded, size: 18),
                ),
              ],
            ),
          )
        : null;

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
          const SizedBox(height: 18),
          if (_stepShowBanner[2])
            AppFormErrorBanner(
              message: _bannerCopy(english),
              onDismiss: () => setState(() => _stepShowBanner[2] = false),
            ),
          _fieldGroup(
            english ? 'Experience *' : 'الخبرة العملية *',
            AiCvField(
              field: 'experience',
              controller: _experienceCtrl,
              english: english,
              isLoading: _enhancingCvField == 'experience',
              minimumCharacters: 80,
              leadingHint: hint,
              helperText: english
                  ? 'Example: Backend Developer — Company — 2022–present; improved API response time by 35%.'
                  : 'مثال: مطور Backend — الشركة — 2022 حتى الآن؛ حسّنت سرعة API بنسبة 35%.',
              result: _fieldResults['experience'],
              onEnhance: () =>
                  _enhanceCvField('experience', _experienceCtrl),
              onDismissResult: () =>
                  setState(() => _fieldResults.remove('experience')),
              child: AppTextFormField(
                controller: _experienceCtrl,
                focusNode: _experienceFocusNode,
                textAlign: TextAlign.start,
                maxLines: 10,
                textInputAction: TextInputAction.done,
                onFieldSubmitted: (_) => _onPrimaryAction(),
                enabled: _enhancingCvField != 'experience',
                hintText: english
                    ? 'Role, company, period\n- Achievement with a measurable result'
                    : 'المسمى، الشركة، الفترة\n- إنجاز بنتيجة قابلة للقياس',
                validator: (value) {
                  final valueText = value?.trim() ?? '';
                  if (valueText.length < 80) {
                    return english
                        ? 'Write at least 80 characters about your experience.'
                        : 'اكتب الخبرات العملية بتفاصيل لا تقل عن 80 حرفاً.';
                  }
                  return null;
                },
              ),
            ),
            english,
          ),
        ],
      ),
    );
  }

'@
$text = [regex]::Replace(
    $text,
    '(?s)  Widget _buildStep2\(bool english\) \{.*?(?=  Widget _buildStep3)',
    $step2
)

$step3 = @'
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
          const SizedBox(height: 18),
          if (_stepShowBanner[3])
            AppFormErrorBanner(
              message: _bannerCopy(english),
              onDismiss: () => setState(() => _stepShowBanner[3] = false),
            ),
          _fieldGroup(
            english ? 'Education *' : 'التعليم *',
            AiCvField(
              field: 'education',
              controller: _educationCtrl,
              english: english,
              isLoading: _enhancingCvField == 'education',
              helperText: english
                  ? 'Example: BSc Computer Science — King Saud University — 2020.'
                  : 'مثال: بكالوريوس علوم الحاسب — جامعة الملك سعود — 2020.',
              result: _fieldResults['education'],
              onEnhance: () =>
                  _enhanceCvField('education', _educationCtrl),
              onDismissResult: () =>
                  setState(() => _fieldResults.remove('education')),
              child: AppTextFormField(
                controller: _educationCtrl,
                textAlign: TextAlign.start,
                maxLines: 4,
                textInputAction: TextInputAction.next,
                onFieldSubmitted: (_) => _focusNext(),
                enabled: _enhancingCvField != 'education',
                hintText: english
                    ? 'BSc Computer Science, King Abdulaziz University, 2020'
                    : 'بكالوريوس علوم الحاسب، جامعة الملك عبدالعزيز، 2020',
                validator: (value) => (value?.trim().isEmpty ?? true)
                    ? (english ? 'Education is required.' : 'التعليم مطلوب.')
                    : null,
              ),
            ),
            english,
          ),
          const SizedBox(height: 18),
          _fieldGroup(
            english
                ? 'Certifications & Courses (optional)'
                : 'الشهادات والدورات (اختياري)',
            AiCvField(
              field: 'certifications',
              controller: _certsCtrl,
              english: english,
              isLoading: _enhancingCvField == 'certifications',
              helperText: english
                  ? 'Example: AWS Certified Cloud Practitioner — Amazon Web Services — 2023.'
                  : 'مثال: AWS Certified Cloud Practitioner — Amazon Web Services — 2023.',
              result: _fieldResults['certifications'],
              onEnhance: () =>
                  _enhanceCvField('certifications', _certsCtrl),
              onDismissResult: () =>
                  setState(() => _fieldResults.remove('certifications')),
              child: AppTextFormField(
                controller: _certsCtrl,
                textAlign: TextAlign.start,
                maxLines: 4,
                textInputAction: TextInputAction.done,
                onFieldSubmitted: (_) => _onPrimaryAction(),
                enabled: _enhancingCvField != 'certifications',
                hintText: english
                    ? 'Certification — issuer — year'
                    : 'اسم الشهادة — الجهة المانحة — السنة',
              ),
            ),
            english,
          ),
        ],
      ),
    );
  }
'@
$text = [regex]::Replace(
    $text,
    '(?s)  Widget _buildStep3\(bool english\) \{.*?\n  \}\n\}',
    $step3 + "`r`n}"
)

[System.IO.File]::WriteAllText($path, $text, $utf8)
