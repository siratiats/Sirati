import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:sirati/features/cv_builder/cv_builder_controller.dart';
import 'package:sirati/features/cv_builder/cv_live_preview_pane.dart';
import 'package:sirati/features/cv_builder/data/cv_repository.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/cv_document.dart';
import 'package:sirati/shared/models/cv_template.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/animated_ats_score_bar.dart';
import 'package:sirati/shared/widgets/form_fields.dart';
import 'package:sirati/shared/widgets/score_booster_card.dart';
import 'package:sirati/shared/widgets/submit_button.dart';

/// Fixed phone-like viewport for deterministic goldens.
const goldenSurfaceSize = Size(390, 844);

/// Light/dark × LTR/RTL shell matching [SiratiApp] theme + direction wiring.
Widget goldenShell({
  required ThemeMode themeMode,
  required TextDirection textDirection,
  required Widget child,
  bool english = true,
}) {
  final locale = textDirection == TextDirection.rtl
      ? const Locale('ar')
      : const Locale('en');

  return MaterialApp(
    debugShowCheckedModeBanner: false,
    theme: AppTheme.light,
    darkTheme: AppTheme.dark,
    themeMode: themeMode,
    locale: locale,
    supportedLocales: const [Locale('en'), Locale('ar')],
    localizationsDelegates: const [
      AppLocalizations.delegate,
      GlobalMaterialLocalizations.delegate,
      GlobalWidgetsLocalizations.delegate,
      GlobalCupertinoLocalizations.delegate,
    ],
    home: MediaQuery(
      data: const MediaQueryData(
        size: goldenSurfaceSize,
        textScaler: TextScaler.linear(1.0),
        disableAnimations: true,
        boldText: false,
        highContrast: false,
        accessibleNavigation: false,
        invertColors: false,
      ),
      child: Builder(
        builder: (context) {
          final bg = Theme.of(context).scaffoldBackgroundColor;
          return Directionality(
            textDirection: textDirection,
            child: Scaffold(
              backgroundColor: bg,
              body: ColoredBox(
                color: bg,
                child: Align(
                  alignment: Alignment.topCenter,
                  child: SizedBox(
                    width: goldenSurfaceSize.width,
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: child,
                    ),
                  ),
                ),
              ),
            ),
          );
        },
      ),
    ),
  );
}

String goldenVariantName({
  required String base,
  required ThemeMode themeMode,
  required TextDirection direction,
}) {
  final theme = themeMode == ThemeMode.dark ? 'dark' : 'light';
  final dir = direction == TextDirection.rtl ? 'rtl' : 'ltr';
  return 'goldens/${base}_${theme}_$dir.png';
}

/// Representative dashboard stat + primary action card cluster (theme-sensitive).
class GoldenDashboardCluster extends StatelessWidget {
  final bool english;

  const GoldenDashboardCluster({super.key, this.english = true});

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          children: [
            Expanded(
              child: _GoldenStatCard(
                label: english ? 'My CVs' : 'السير الذاتية',
                count: '03',
                icon: Icons.description_outlined,
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: _GoldenStatCard(
                label: english ? 'Analyses' : 'التحليلات',
                count: '12',
                icon: Icons.analytics_outlined,
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        Material(
          color: c.primaryDark,
          borderRadius: BorderRadius.circular(20),
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 174),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(20),
              child: Stack(
                children: [
                  PositionedDirectional(
                    end: -36,
                    bottom: -40,
                    child: Icon(
                      Icons.auto_awesome,
                      size: 114,
                      color: Colors.white.withValues(alpha: .12),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.all(22),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          Icons.description_outlined,
                          color: Colors.white.withValues(alpha: .94),
                          size: 22,
                        ),
                        const SizedBox(height: 12),
                        Text(
                          english ? 'Create a new CV' : 'إنشاء سيرة جديدة',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 17,
                            height: 1.35,
                            fontWeight: FontWeight.w800,
                            color: Colors.white,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          english
                              ? 'ATS-ready templates in minutes'
                              : 'قوالب متوافقة مع ATS في دقائق',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            fontSize: 13,
                            height: 1.55,
                            fontWeight: FontWeight.w500,
                            color: Colors.white.withValues(alpha: .82),
                          ),
                        ),
                        const SizedBox(height: 16),
                        Align(
                          alignment: AlignmentDirectional.centerStart,
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 18, vertical: 9),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(999),
                            ),
                            child: Text(
                              english ? 'Start' : 'ابدأ',
                              style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w800,
                                color: c.primaryDark,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _GoldenStatCard extends StatelessWidget {
  final String label;
  final String count;
  final IconData icon;

  const _GoldenStatCard({
    required this.label,
    required this.count,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    return ConstrainedBox(
      constraints: const BoxConstraints(minHeight: 120),
      child: Container(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
        decoration: BoxDecoration(
          color: c.surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: c.border),
          boxShadow: c.softShadow,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: AppTextStyles.labelMd(c),
            ),
            const SizedBox(height: 6),
            Text(
              count,
              style: AppTextStyles.displayStat(c),
            ),
            const SizedBox(height: 12),
            Container(
              width: 30,
              height: 30,
              decoration: BoxDecoration(
                color: c.primaryLight,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, size: 16, color: c.primary),
            ),
          ],
        ),
      ),
    );
  }
}

/// Enabled / loading / disabled SubmitButton stack.
class GoldenSubmitButtonStates extends StatelessWidget {
  final bool english;

  const GoldenSubmitButtonStates({super.key, this.english = true});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SubmitButton(
          label: english ? 'Generate CV' : 'توليد السيرة',
          icon: Icons.auto_awesome,
          onPressed: () {},
        ),
        const SizedBox(height: 12),
        SubmitButton(
          label: english ? 'Generate CV' : 'توليد السيرة',
          loadingLabel: english ? 'Generating...' : 'جارٍ التوليد...',
          isLoading: true,
          onPressed: () {},
        ),
        const SizedBox(height: 12),
        SubmitButton(
          label: english ? 'Generate CV' : 'توليد السيرة',
          onPressed: null,
        ),
      ],
    );
  }
}

/// Idle / error / success field chrome.
class GoldenFormFieldStates extends StatefulWidget {
  final bool english;

  const GoldenFormFieldStates({super.key, this.english = true});

  @override
  State<GoldenFormFieldStates> createState() => _GoldenFormFieldStatesState();
}

class _GoldenFormFieldStatesState extends State<GoldenFormFieldStates> {
  final _formKey = GlobalKey<FormState>();
  final _errorFocus = FocusNode();
  final _successFocus = FocusNode();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (!mounted) return;
      // Blur-first policy: focus then unfocus to surface error / success chrome.
      _errorFocus.requestFocus();
      await Future<void>.delayed(Duration.zero);
      _successFocus.requestFocus();
      await Future<void>.delayed(Duration.zero);
      _successFocus.unfocus();
      _formKey.currentState?.validate();
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _errorFocus.dispose();
    _successFocus.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final english = widget.english;
    return Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AppTextFormField(
            initialValue: '',
            hintText: english ? 'Full name' : 'الاسم الكامل',
            prefixIcon: const Icon(Icons.person_outline),
          ),
          const SizedBox(height: 16),
          AppTextFormField(
            focusNode: _errorFocus,
            initialValue: '',
            hintText: english ? 'Email' : 'البريد',
            prefixIcon: const Icon(Icons.email_outlined),
            autovalidateMode: AutovalidateMode.always,
            validator: (_) => english ? 'Email is required' : 'البريد مطلوب',
          ),
          const SizedBox(height: 16),
          AppTextFormField(
            focusNode: _successFocus,
            initialValue: english ? 'Salem Sayer' : 'سالم سيار',
            hintText: english ? 'Full name' : 'الاسم الكامل',
            prefixIcon: const Icon(Icons.person_outline),
            showSuccessWhenValid: true,
            successMessage: english ? 'Looks good' : 'يبدو جيداً',
            autovalidateMode: AutovalidateMode.always,
            validator: (v) => (v == null || v.trim().isEmpty) ? 'x' : null,
          ),
        ],
      ),
    );
  }
}

/// Score booster with a fixed tip (no animation variance).
class GoldenScoreBooster extends StatefulWidget {
  final bool english;

  const GoldenScoreBooster({super.key, this.english = true});

  @override
  State<GoldenScoreBooster> createState() => _GoldenScoreBoosterState();
}

class _GoldenScoreBoosterState extends State<GoldenScoreBooster> {
  late final ScoreBoosterController _controller;

  @override
  void initState() {
    super.initState();
    _controller = ScoreBoosterController(
      fieldWeights: const {'name': 25, 'role': 30, 'study': 45},
      tipForIncomplete: (incomplete) {
        if (incomplete.isEmpty) {
          return ScoreBoosterTip(
            message: widget.english
                ? 'Great work — profile ready.'
                : 'عمل رائع — الملف جاهز.',
            boostPoints: 0,
          );
        }
        return ScoreBoosterTip(
          message: widget.english
              ? 'Adding your field of study will boost ATS by +15.'
              : 'إضافة تخصصك سترفع ATS بمقدار +15.',
          boostPoints: 15,
          icon: Icons.school_outlined,
        );
      },
    );
    _controller.setAll(const {
      'name': true,
      'role': true,
      'study': false,
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ScoreBoosterCard(
      controller: _controller,
      margin: EdgeInsets.zero,
    );
  }
}

class _GoldenCvRepository implements CvRepository {
  @override
  Future<List<CvMetadata>> listCvs() async => const [];

  @override
  Future<CvDocument?> getCv(String id) async => null;

  @override
  Future<void> saveCv(CvDocument document,
      {bool touchUpdatedAt = true}) async {}

  @override
  Future<CvDocument> duplicateCv(String id, {String? newTitle}) async {
    throw UnsupportedError('golden');
  }

  @override
  Future<void> renameCv(String id, String newTitle) async {}

  @override
  Future<void> deleteCv(String id) async {}

  @override
  Future<void> restoreCv(String id) async {}
}

const _goldenTemplate = CvTemplate(
  id: 1,
  slug: 'ats-classic-professional',
  name: 'كلاسيكي احترافي',
  nameAr: 'كلاسيكي احترافي',
  nameEn: 'ATS Classic Professional',
  previewImageUrl: null,
  languageDirection: 'both',
  supportedLanguages: ['ar', 'en'],
  supportedSections: [],
  isDefault: true,
  isPremium: false,
);

const _goldenDocument = CvDocument(
  id: 'golden',
  exportLanguage: 'ar',
  personal: PersonalDetails(
    fullName: LocalizedText(ar: 'سارة التميمي', en: 'Sara Al-Tamimi'),
    headline: LocalizedText(ar: 'مهندسة برمجيات', en: 'Software Engineer'),
    email: 'sara@example.com',
    location: LocalizedText(ar: 'الرياض', en: 'Riyadh'),
  ),
  summary: LocalizedText(ar: 'ملخص احترافي', en: 'Professional summary'),
  skills: [
    Skill(name: LocalizedText(ar: 'فلاتر', en: 'Flutter')),
    Skill(name: LocalizedText(ar: 'دارت', en: 'Dart')),
  ],
);

/// CV builder section list chrome (theme + direction sensitive).
class GoldenCvBuilderScreen extends StatelessWidget {
  final bool english;

  const GoldenCvBuilderScreen({super.key, this.english = true});

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          english ? 'CV builder' : 'منشئ السيرة',
          style: AppTextStyles.titleMd(c).copyWith(color: c.textPrimary),
        ),
        const SizedBox(height: 12),
        AppTextFormField(
          initialValue: english ? 'Sara Al-Tamimi' : 'سارة التميمي',
          hintText: english ? 'Full name' : 'الاسم الكامل',
          prefixIcon: const Icon(Icons.person_outline),
        ),
        const SizedBox(height: 12),
        AppTextFormField(
          initialValue: english ? 'Software Engineer' : 'مهندسة برمجيات',
          hintText: english ? 'Headline' : 'المسمى',
          prefixIcon: const Icon(Icons.work_outline),
        ),
        const SizedBox(height: 16),
        SubmitButton(
          label: english ? 'Save draft' : 'حفظ المسودة',
          icon: Icons.arrow_forward_rounded,
          onPressed: () {},
        ),
      ],
    );
  }
}

/// Live A4 preview pane used on the builder screen.
class GoldenCvLivePreviewScreen extends StatelessWidget {
  final bool english;

  const GoldenCvLivePreviewScreen({super.key, this.english = true});

  @override
  Widget build(BuildContext context) {
    final document = _goldenDocument.copyWith(
      exportLanguage: english ? 'en' : 'ar',
    );
    final controller = CvBuilderController(
      initialDocument: document,
      repository: _GoldenCvRepository(),
    );
    return CvLivePreviewPane(
      controller: controller,
      template: _goldenTemplate,
      languageOverride: english ? 'en' : 'ar',
      scale: 0.72,
    );
  }
}

/// ATS scanner / analysis chrome.
class GoldenAtsScannerScreen extends StatelessWidget {
  final bool english;

  const GoldenAtsScannerScreen({super.key, this.english = true});

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          english ? 'ATS analysis' : 'تحليل ATS',
          style: AppTextStyles.titleMd(c).copyWith(color: c.textPrimary),
        ),
        const SizedBox(height: 8),
        Text(
          english ? 'Match score 87' : 'درجة التوافق 87',
          style: AppTextStyles.bodyMd(c).copyWith(color: c.textSecondary),
        ),
        const SizedBox(height: 16),
        AnimatedAtsScoreBar(
          value: 0.87,
          color: c.primary,
          height: 12,
          celebrateOnComplete: false,
        ),
        const SizedBox(height: 16),
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: c.surface,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: c.border),
          ),
          child: Text(
            english
                ? 'Add a quantified achievement to the latest role.'
                : 'أضف إنجازاً قابلاً للقياس في آخر منصب.',
            style: AppTextStyles.bodyMd(c),
          ),
        ),
      ],
    );
  }
}
