import 'package:flutter/material.dart';
import 'package:sirati/core/network/api_client.dart';
import 'package:sirati/features/cv_export/controllers/pdf_export_controller.dart';
import 'package:sirati/shared/models/cv_template.dart';
import 'package:sirati/shared/models/generated_cv.dart';
import 'package:sirati/shared/theme/app_theme.dart';

class CvExportDialog extends StatefulWidget {
  final GeneratedCv cv;
  final List<CvTemplate> templates;
  final CvTemplate initialTemplate;
  final ApiClient apiClient;
  final VoidCallback? onUpgradeRequested;

  const CvExportDialog({
    super.key,
    required this.cv,
    required this.templates,
    required this.initialTemplate,
    required this.apiClient,
    this.onUpgradeRequested,
  });

  static Future<void> show(
    BuildContext context, {
    required GeneratedCv cv,
    required List<CvTemplate> templates,
    required CvTemplate initialTemplate,
    required ApiClient apiClient,
    VoidCallback? onUpgradeRequested,
  }) {
    return showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors
          .transparent, // ignore: hardcoded_color -- bottom_sheet_transparent_scrim
      builder: (ctx) => CvExportDialog(
        cv: cv,
        templates: templates,
        initialTemplate: initialTemplate,
        apiClient: apiClient,
        onUpgradeRequested: onUpgradeRequested,
      ),
    );
  }

  @override
  State<CvExportDialog> createState() => _CvExportDialogState();
}

class _CvExportDialogState extends State<CvExportDialog> {
  late final PdfExportController _controller;

  @override
  void initState() {
    super.initState();
    _controller = PdfExportController(
      apiClient: widget.apiClient,
      cv: widget.cv,
      initialTemplate: widget.initialTemplate,
      initialLanguage: widget.cv.language,
    );
    _controller.addListener(_onControllerChanged);
    _controller.loadPreview();
  }

  @override
  void dispose() {
    _controller.removeListener(_onControllerChanged);
    _controller.dispose();
    super.dispose();
  }

  void _onControllerChanged() {
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final state = _controller.state;
    final isArabic = state.exportLanguage == 'ar';
    final theme = Theme.of(context);
    final c = context.sirati;

    return Directionality(
      textDirection: isArabic ? TextDirection.rtl : TextDirection.ltr,
      child: Container(
        decoration: BoxDecoration(
          color: c.surface,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
        ),
        padding: EdgeInsets.only(
          top: 20,
          left: 20,
          right: 20,
          bottom: MediaQuery.of(context).viewInsets.bottom + 24,
        ),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Header
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    isArabic
                        ? 'خيارات التصدير والمشاركة'
                        : 'Export & Share Options',
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.bold,
                      color: c.textPrimary,
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close),
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              // Export language selection (SIRATI-48)
              Text(
                isArabic ? 'لغة التصدير:' : 'Export Language:',
                style:
                    const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
              ),
              const SizedBox(height: 6),
              SegmentedButton<String>(
                segments: const [
                  ButtonSegment(value: 'ar', label: Text('العربية')),
                  ButtonSegment(value: 'en', label: Text('English')),
                ],
                selected: {state.exportLanguage},
                onSelectionChanged: (selected) {
                  _controller.setExportLanguage(selected.first);
                  _controller.loadPreview();
                },
              ),
              const SizedBox(height: 16),

              // Template selection with Free / Premium badge (SIRATI-49)
              Text(
                isArabic ? 'القالب المختار:' : 'Selected Template:',
                style:
                    const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
              ),
              const SizedBox(height: 6),
              SizedBox(
                height: 44,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  itemCount: widget.templates.length,
                  separatorBuilder: (_, __) => const SizedBox(width: 8),
                  itemBuilder: (context, index) {
                    final t = widget.templates[index];
                    final isSelected = t.id == state.template.id;
                    return ChoiceChip(
                      selected: isSelected,
                      onSelected: (_) {
                        _controller.setTemplate(t);
                        _controller.loadPreview();
                      },
                      avatar: t.isPremium
                          ? Icon(Icons.star, size: 14, color: c.amber)
                          : null,
                      label: Text(isArabic ? t.nameAr : t.nameEn),
                    );
                  },
                ),
              ),
              const SizedBox(height: 16),

              // Filename preview & Latin option (SIRATI-48)
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: c.surfaceLow,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: c.border),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      isArabic ? 'اسم الملف المصدر:' : 'Export File Name:',
                      style: TextStyle(fontSize: 11, color: c.textSecondary),
                    ),
                    const SizedBox(height: 4),
                    SelectableText(
                      state.filename,
                      style: TextStyle(
                        fontFamily: 'monospace',
                        fontWeight: FontWeight.bold,
                        fontSize: 12,
                        color: c.textPrimary,
                      ),
                    ),
                    if (isArabic) ...[
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          Checkbox(
                            value: state.latinFallback,
                            onChanged: (val) =>
                                _controller.setLatinFallback(val == true),
                          ),
                          Text(
                            isArabic
                                ? 'استخدام أحرف لاتينية لاسم الملف'
                                : 'Use Latin filename',
                            style: const TextStyle(fontSize: 12),
                          ),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 16),

              // Premium Watermark Indicator (SIRATI-49)
              if (state.template.isPremium && state.isWatermarked) ...[
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: c.warningLight,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: c.warning),
                  ),
                  child: Row(
                    children: [
                      Icon(Icons.info_outline,
                          color: c.onWarningContainer, size: 18),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          isArabic
                              ? 'هذا قالب مميز. ستظهر علامة مائية للمعاينة حتى تقوم بالترقية.'
                              : 'This is a premium template. Previews will display a watermark until upgraded.',
                          style: TextStyle(
                              fontSize: 11, color: c.onWarningContainer),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
              ],

              // Locked notification with upgrade prompt (SIRATI-49)
              if (state.status == ExportStatus.premiumLocked) ...[
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: c.errorLight,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: c.error),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        children: [
                          Icon(Icons.lock, color: c.onErrorContainer, size: 18),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              isArabic
                                  ? 'تصدير هذا القالب متاح للمشتركين فقط'
                                  : 'Exporting this template requires a subscription',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 12,
                                color: c.onErrorContainer,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: c.error,
                          foregroundColor: c.onError,
                        ),
                        onPressed: widget.onUpgradeRequested,
                        child: Text(isArabic
                            ? 'الترقية الآن إلى باقة المحترفين'
                            : 'Upgrade to Pro'),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
              ],

              // Progress Indicator (SIRATI-45)
              if (state.status == ExportStatus.downloading) ...[
                Column(
                  children: [
                    LinearProgressIndicator(
                        value: state.progress > 0 ? state.progress : null),
                    const SizedBox(height: 8),
                    Text(
                      isArabic
                          ? 'جاري تجهيز وتصدير ملف PDF...'
                          : 'Preparing and exporting PDF...',
                      style: TextStyle(fontSize: 11, color: c.textSecondary),
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
                const SizedBox(height: 16),
              ],

              // Action buttons: Native Share Sheet & Download (SIRATI-48)
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      icon: const Icon(Icons.download),
                      label: Text(isArabic ? 'تحميل' : 'Download'),
                      onPressed: state.status == ExportStatus.downloading
                          ? null
                          : () async {
                              final success = await _controller.exportPdf();
                              if (success && context.mounted) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(
                                    content: Text(
                                      isArabic
                                          ? 'تم تصدير ملف PDF بنجاح'
                                          : 'PDF exported successfully',
                                    ),
                                  ),
                                );
                              }
                            },
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton.icon(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: c.primary,
                        foregroundColor: c.onPrimary,
                      ),
                      icon: const Icon(Icons.share),
                      label: Text(isArabic ? 'مشاركة (PDF)' : 'Share PDF'),
                      onPressed: state.status == ExportStatus.downloading
                          ? null
                          : () => _controller.sharePdf(),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
