import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:share_plus/share_plus.dart';
import 'package:sirati/core/utils/ai_error_message.dart';
import 'package:sirati/core/utils/app_format.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/core/utils/bidi_text_utils.dart';
import 'package:sirati/core/utils/safe_url.dart';
import 'package:sirati/features/cv_builder/application/my_cvs_controller.dart';
import 'package:sirati/features/cv_builder/domain/cv_markdown.dart';
import 'package:sirati/features/cv_builder/presentation/cv_generator_screen.dart';
import 'package:sirati/features/cv_builder/presentation/generated_cv/cv_document_view.dart';
import 'package:sirati/features/cv_builder/presentation/my_cvs/cv_template_picker.dart';
import 'package:sirati/shared/models/ai_status.dart';
import 'package:sirati/shared/models/cv_template.dart';
import 'package:sirati/shared/models/generated_cv.dart';
import 'package:sirati/shared/services/cv_api_service_provider.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/app_snack_bar.dart';
import 'package:sirati/shared/widgets/language_toggle.dart';
import 'package:sirati/shared/widgets/motion.dart';

/// Reading direction for a CV: its declared language, else detected.
TextDirection cvContentDirection(GeneratedCv cv) => switch (cv.language) {
      'en' => TextDirection.ltr,
      'ar' => TextDirection.rtl,
      _ => BidiTextUtils.detectBaseDirection(
          cv.generatedMarkdown,
          fallback: TextDirection.rtl,
        ),
    };

/// Whether the CV can be handed to an employer (AI finished or not used).
bool isCvReadyToSend(GeneratedCv cv) =>
    !AiStatus.isPending(cv.aiStatus) && cv.aiStatus != AiStatus.failed;

class GeneratedCvScreen extends ConsumerStatefulWidget {
  const GeneratedCvScreen({super.key, required this.generatedCv});

  final GeneratedCv generatedCv;

  @override
  ConsumerState<GeneratedCvScreen> createState() => _GeneratedCvScreenState();
}

class _GeneratedCvScreenState extends ConsumerState<GeneratedCvScreen> {
  /// The CV on screen; replaced when the user edits it.
  late GeneratedCv _cv = widget.generatedCv;
  late List<CvBlock> _blocks = parseCvMarkdown(_cv.generatedMarkdown);
  bool _isDownloading = false;

  @override
  void didUpdateWidget(covariant GeneratedCvScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!identical(oldWidget.generatedCv, widget.generatedCv)) {
      _show(widget.generatedCv);
    }
  }

  void _show(GeneratedCv cv) {
    _cv = cv;
    _blocks = parseCvMarkdown(cv.generatedMarkdown);
  }

  Future<void> _edit() async {
    final updated = await Navigator.of(context).push<GeneratedCv>(
      MaterialPageRoute(builder: (_) => CvGeneratorScreen(initialCv: _cv)),
    );
    if (updated != null && mounted) setState(() => _show(updated));
  }

  Future<void> _downloadPdf() async {
    if (_isDownloading) return;
    final english = AppLocale.isEnglish(context);
    if (!isCvReadyToSend(_cv)) {
      AppSnackBar.warning(
        context,
        english
            ? 'This CV is not ready to send to an employer yet.'
            : 'هذه السيرة غير جاهزة للإرسال إلى جهة التوظيف بعد.',
      );
      return;
    }
    final api = ref.read(cvApiServiceProvider);
    setState(() => _isDownloading = true);
    try {
      String? slug;
      if (_cv.templatePdfUrl?.isNotEmpty ?? false) {
        final choice = await _chooseTemplate(english);
        if (choice == null || !mounted) return; // cancelled
        slug = choice.template?.slug;
      }
      AppSnackBar.info(
        context,
        english ? 'Opening PDF download...' : 'جارٍ فتح رابط تنزيل الـ PDF...',
      );
      final url = await resolveCvPdfUrl(
        api,
        id: _cv.id,
        storedUrl: (_cv.templatePdfUrl?.isNotEmpty ?? false)
            ? _cv.templatePdfUrl!
            : (_cv.pdfUrl ?? ''),
        templateSlug: slug,
      );
      if (!mounted) return;
      if (url.isEmpty) {
        AppSnackBar.warning(
          context,
          english
              ? 'PDF link is not available yet.'
              : 'رابط PDF غير متاح حالياً.',
        );
        return;
      }
      final launched = await launchSafeExternalUrl(url);
      if (!launched && mounted) {
        AppSnackBar.warning(
          context,
          english ? 'Could not open the PDF link.' : 'تعذر فتح رابط PDF.',
        );
      }
    } finally {
      if (mounted) setState(() => _isDownloading = false);
    }
  }

  /// Null when the user dismissed the picker; a null template means "use
  /// the default design".
  Future<({CvTemplate? template})?> _chooseTemplate(bool english) async {
    try {
      final templates = await ref
          .read(cvApiServiceProvider)
          .listCvTemplates(english: english);
      if (!mounted) return null;
      if (templates.isEmpty) return (template: null);
      final picked = await showModalBottomSheet<CvTemplate>(
        context: context,
        showDragHandle: true,
        builder: (_) =>
            CvTemplatePicker(templates: templates, english: english),
      );
      return picked == null ? null : (template: picked);
    } catch (_) {
      if (mounted) {
        AppSnackBar.warning(
          context,
          english
              ? 'Could not load CV designs. Default design will be used.'
              : 'تعذر تحميل التصاميم. سيتم استخدام التصميم الافتراضي.',
        );
      }
      return (template: null);
    }
  }

  void _share() {
    final text = cvPlainText(
      fullName: _cv.fullName,
      headline: displayHeadline(_cv.targetJobTitle, language: _cv.language),
      markdown: _cv.generatedMarkdown,
    );
    if (text.isEmpty) return;
    unawaited(Share.share(text));
  }

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    final c = context.sirati;
    final contact = [_cv.email, _cv.phone]
        .whereType<String>()
        .where((value) => value.trim().isNotEmpty)
        .join(' · ');
    final aiError = _cv.aiError;

    return Scaffold(
      appBar: AppBar(
        title: Text(english ? 'Generated CV' : 'السيرة الذاتية'),
        actions: [
          const LanguageToggle(),
          IconButton(
            icon: const Icon(Icons.edit_outlined),
            tooltip: english ? 'Edit CV' : 'تعديل السيرة',
            onPressed: () => unawaited(_edit()),
          ),
        ],
      ),
      body: Column(
        children: [
          if (_cv.aiStatus == AiStatus.completed ||
              _cv.aiStatus == AiStatus.notConfigured)
            CvStatusBanner.success(
              message: english
                  ? 'CV generated · ATS score: ${_cv.scoreTotal}'
                  : 'تم إنشاء السيرة · درجة ATS: ${AppFormat.digits('${_cv.scoreTotal}', english: english)}',
              trailing: _cv.grade.trim().isEmpty || _cv.grade == '-'
                  ? null
                  : Text(_cv.grade),
            ),
          if (_cv.aiStatus == AiStatus.failed && aiError != null)
            CvStatusBanner.warning(
              message: AiErrorMessage.localFallback(aiError, english: english),
            ),
          Expanded(
            child: MotionReveal(
              child: Container(
                margin: const EdgeInsets.all(16),
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: c.surface,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: c.border),
                ),
                child: Directionality(
                  textDirection: cvContentDirection(_cv),
                  child: ListView(
                    children: [
                      Text(
                        _cv.fullName,
                        style: TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.w700,
                          color: c.primaryDark,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        displayHeadline(
                          _cv.targetJobTitle,
                          language: _cv.language,
                        ),
                        style: TextStyle(
                          fontSize: 14,
                          color: c.primaryInk,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                      const SizedBox(height: 4),
                      if (contact.isNotEmpty)
                        Text(
                          contact,
                          textDirection: TextDirection.ltr,
                          style:
                              TextStyle(fontSize: 12, color: c.textSecondary),
                        ),
                      Divider(height: 24, color: c.border),
                      if (_blocks.isEmpty)
                        Text(
                          english
                              ? 'No CV content is available yet.'
                              : 'لا يوجد محتوى للسيرة حالياً.',
                          style:
                              TextStyle(fontSize: 13, color: c.textSecondary),
                        )
                      else
                        CvDocumentBlocks(blocks: _blocks),
                    ],
                  ),
                ),
              ),
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 20),
              child: Row(
                children: [
                  Expanded(
                    child: PressScale(
                      child: OutlinedButton.icon(
                        onPressed: _blocks.isEmpty ? null : _share,
                        icon: const Icon(Icons.share_outlined, size: 18),
                        label: Text(english ? 'Share' : 'مشاركة'),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: PressScale(
                      child: ElevatedButton.icon(
                        onPressed: _isDownloading
                            ? null
                            : () => unawaited(_downloadPdf()),
                        icon: _isDownloading
                            ? SizedBox(
                                width: 16,
                                height: 16,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: c.primary,
                                ),
                              )
                            : const Icon(Icons.download_outlined, size: 18),
                        label: Text(
                          _isDownloading
                              ? (english ? 'Preparing...' : 'جارٍ التجهيز...')
                              : (english ? 'Download PDF' : 'تنزيل PDF'),
                        ),
                      ),
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
}
