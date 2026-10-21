import 'dart:async';

import 'package:flutter/material.dart';
import 'package:sirati/features/cv_builder/controllers/cv_builder_controller.dart';
import 'package:sirati/shared/models/cv_document.dart';
import 'package:sirati/shared/models/cv_template.dart';

abstract final class _PaperPalette {
  static const Color paperBg =
      Color(0xFFFFFFFF); // ignore: hardcoded_color -- a4_paper_simulation
  static const Color shadow =
      Color(0x1A000000); // ignore: hardcoded_color -- a4_paper_simulation
  static const Color inkPrimary =
      Color(0xFF111827); // ignore: hardcoded_color -- a4_paper_simulation
  static const Color accent =
      Color(0xFF2563EB); // ignore: hardcoded_color -- a4_paper_simulation
  static const Color sectionTitle =
      Color(0xFF1E3A8A); // ignore: hardcoded_color -- a4_paper_simulation
  static const Color dividerDark =
      Color(0xFF1F2937); // ignore: hardcoded_color -- a4_paper_simulation
  static const Color dividerLight =
      Color(0xFF93C5FD); // ignore: hardcoded_color -- a4_paper_simulation
  static const Color inkMuted =
      Color(0xFF4B5563); // ignore: hardcoded_color -- a4_paper_simulation
  static const Color inkBody =
      Color(0xFF374151); // ignore: hardcoded_color -- a4_paper_simulation
  static const Color inkSubtle =
      Color(0xFF6B7280); // ignore: hardcoded_color -- a4_paper_simulation
  static const Color tagBg =
      Color(0xFFF3F4F6); // ignore: hardcoded_color -- a4_paper_simulation
  static const Color tagBorder =
      Color(0xFFE5E7EB); // ignore: hardcoded_color -- a4_paper_simulation
  static const Color watermarkBorder =
      Color(0x33DC2626); // ignore: hardcoded_color -- a4_paper_simulation
  static const Color watermarkText =
      Color(0x33DC2626); // ignore: hardcoded_color -- a4_paper_simulation
}

/// Responsive live preview pane for the CV builder (SIRATI-41).
///
/// Features:
/// - 300ms debounce on edits to prevent lag on long documents
/// - Respects selected export language and layout direction (RTL / LTR)
/// - Renders structured ATS layout (A4 aspect ratio, headings, entries, tags)
/// - Displays watermark overlay for unentitled premium templates (SIRATI-49)
class CvLivePreviewPane extends StatefulWidget {
  final CvBuilderController controller;
  final CvTemplate template;
  final String? languageOverride;
  final bool isWatermarked;
  final double scale;

  const CvLivePreviewPane({
    super.key,
    required this.controller,
    required this.template,
    this.languageOverride,
    this.isWatermarked = false,
    this.scale = 1.0,
  });

  @override
  State<CvLivePreviewPane> createState() => _CvLivePreviewPaneState();
}

class _CvLivePreviewPaneState extends State<CvLivePreviewPane> {
  Timer? _debounceTimer;
  late CvDocument _debouncedDocument;

  @override
  void initState() {
    super.initState();
    _debouncedDocument = widget.controller.document;
    widget.controller.addListener(_onControllerDocumentChanged);
  }

  @override
  void dispose() {
    _debounceTimer?.cancel();
    widget.controller.removeListener(_onControllerDocumentChanged);
    super.dispose();
  }

  void _onControllerDocumentChanged() {
    _debounceTimer?.cancel();
    _debounceTimer = Timer(const Duration(milliseconds: 300), () {
      if (mounted) {
        setState(() {
          _debouncedDocument = widget.controller.document;
        });
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final language =
        widget.languageOverride ?? _debouncedDocument.exportLanguage;
    final isAr = language == 'ar';
    final doc = _debouncedDocument;

    final fullName = doc.fullName.resolve(language);
    final headline = doc.headline.resolve(language);
    final email = doc.email;
    final phone = doc.phone;
    final location = doc.location.resolve(language);
    final summary = doc.summary.resolve(language);
    final experience = doc.experience ?? const [];
    final education = doc.education ?? const [];
    final skills = doc.skills ?? const [];

    return Directionality(
      textDirection: isAr ? TextDirection.rtl : TextDirection.ltr,
      child: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Stack(
            children: [
              // A4 Sheet Container
              Container(
                width: 595 * widget.scale,
                constraints: BoxConstraints(
                  minHeight: 842 * widget.scale,
                ),
                padding:
                    const EdgeInsets.symmetric(horizontal: 32, vertical: 36),
                decoration: BoxDecoration(
                  color: _PaperPalette.paperBg,
                  borderRadius: BorderRadius.circular(4),
                  boxShadow: const [
                    BoxShadow(
                      color: _PaperPalette.shadow,
                      blurRadius: 12,
                      offset: Offset(0, 4),
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Candidate Identity Header
                    Text(
                      fullName.isNotEmpty
                          ? fullName
                          : (isAr ? 'الاسم الكامل' : 'Candidate Name'),
                      style: const TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.bold,
                        color: _PaperPalette.inkPrimary,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      headline.isNotEmpty
                          ? headline
                          : (isAr ? 'المسمى المهني' : 'Target Job Title'),
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: _PaperPalette.accent,
                      ),
                    ),
                    const SizedBox(height: 8),

                    // Contact Details (Body level with LTR directionality)
                    Wrap(
                      spacing: 12,
                      runSpacing: 4,
                      children: [
                        if (email != null && email.isNotEmpty)
                          _contactItem(email),
                        if (phone != null && phone.isNotEmpty)
                          _contactItem(phone),
                        if (location.isNotEmpty)
                          _contactItem(location, forceLtr: false),
                      ],
                    ),
                    const SizedBox(height: 16),
                    const Divider(
                        color: _PaperPalette.dividerDark, thickness: 1.5),
                    const SizedBox(height: 12),

                    // Professional Summary Section
                    if (summary.isNotEmpty) ...[
                      _sectionHeader(
                          isAr ? 'الملخص المهني' : 'Professional Summary'),
                      const SizedBox(height: 6),
                      Text(
                        summary,
                        style: const TextStyle(
                            fontSize: 11,
                            height: 1.6,
                            color: _PaperPalette.inkBody),
                      ),
                      const SizedBox(height: 16),
                    ],

                    // Experience Section
                    if (experience.isNotEmpty) ...[
                      _sectionHeader(
                          isAr ? 'الخبرة المهنية' : 'Work Experience'),
                      const SizedBox(height: 8),
                      for (final entry in experience)
                        _buildExperienceEntry(entry, language, isAr),
                      const SizedBox(height: 12),
                    ],

                    // Education Section
                    if (education.isNotEmpty) ...[
                      _sectionHeader(isAr ? 'التعليم' : 'Education'),
                      const SizedBox(height: 8),
                      for (final entry in education)
                        _buildEducationEntry(entry, language, isAr),
                      const SizedBox(height: 12),
                    ],

                    // Skills Section
                    if (skills.isNotEmpty) ...[
                      _sectionHeader(isAr ? 'المهارات' : 'Skills'),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 8,
                        runSpacing: 6,
                        children: [
                          for (final skill in skills)
                            if (skill.name.resolve(language).isNotEmpty)
                              Container(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 8, vertical: 4),
                                decoration: BoxDecoration(
                                  color: _PaperPalette.tagBg,
                                  borderRadius: BorderRadius.circular(4),
                                  border: Border.all(
                                      color: _PaperPalette.tagBorder),
                                ),
                                child: Text(
                                  skill.name.resolve(language),
                                  style: const TextStyle(
                                      fontSize: 10,
                                      color: _PaperPalette.inkPrimary),
                                ),
                              ),
                        ],
                      ),
                      const SizedBox(height: 16),
                    ],
                  ],
                ),
              ),

              // Watermark Overlay for Premium Templates (SIRATI-49)
              if (widget.isWatermarked || widget.template.isPremium)
                Positioned.fill(
                  child: IgnorePointer(
                    child: Center(
                      child: Transform.rotate(
                        angle: -0.5,
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 24, vertical: 12),
                          decoration: BoxDecoration(
                            border: Border.all(
                                color: _PaperPalette.watermarkBorder, width: 3),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            isAr
                                ? 'معاينة سيرتي · للاطلاع فقط'
                                : 'SIRATI PREVIEW · FOR EVALUATION',
                            style: const TextStyle(
                              fontSize: 22,
                              fontWeight: FontWeight.bold,
                              color: _PaperPalette.watermarkText,
                              letterSpacing: 2,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _contactItem(String text, {bool forceLtr = true}) {
    return Directionality(
      textDirection: forceLtr ? TextDirection.ltr : TextDirection.rtl,
      child: Text(
        text,
        style: const TextStyle(fontSize: 10, color: _PaperPalette.inkMuted),
      ),
    );
  }

  Widget _sectionHeader(String title) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.bold,
            color: _PaperPalette.sectionTitle,
            letterSpacing: 0.5,
          ),
        ),
        const SizedBox(height: 3),
        const Divider(color: _PaperPalette.dividerLight, thickness: 1),
      ],
    );
  }

  Widget _buildExperienceEntry(
      ExperienceEntry entry, String language, bool isAr) {
    final title = entry.title.resolve(language);
    final company = entry.company.resolve(language);
    final loc = entry.location.resolve(language);
    final start = entry.startDate ?? '';
    final end = entry.isCurrent
        ? (isAr ? 'حتى الآن' : 'Present')
        : (entry.endDate ?? '');
    final dateRange =
        (start.isNotEmpty || end.isNotEmpty) ? '$start - $end' : '';

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                title.isNotEmpty ? title : (isAr ? 'مسمى وظيفي' : 'Job Title'),
                style: const TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.bold,
                    color: _PaperPalette.inkPrimary),
              ),
              if (dateRange.isNotEmpty)
                Directionality(
                  textDirection: TextDirection.ltr,
                  child: Text(
                    dateRange,
                    style: const TextStyle(
                        fontSize: 9.5, color: _PaperPalette.inkSubtle),
                  ),
                ),
            ],
          ),
          if (company.isNotEmpty || loc.isNotEmpty)
            Text(
              '$company${company.isNotEmpty && loc.isNotEmpty ? ' · ' : ''}$loc',
              style: const TextStyle(
                  fontSize: 10.5,
                  color: _PaperPalette.inkMuted,
                  fontWeight: FontWeight.w500),
            ),
          if (entry.narrative.resolve(language).isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 2),
              child: Text(
                entry.narrative.resolve(language),
                style: const TextStyle(
                    fontSize: 10, height: 1.5, color: _PaperPalette.inkBody),
              ),
            ),
          for (final bullet in entry.bullets)
            if (bullet.resolve(language).isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 2),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('• ',
                        style: TextStyle(
                            fontSize: 10, color: _PaperPalette.accent)),
                    Expanded(
                      child: Text(
                        bullet.resolve(language),
                        style: const TextStyle(
                            fontSize: 10,
                            height: 1.4,
                            color: _PaperPalette.inkBody),
                      ),
                    ),
                  ],
                ),
              ),
        ],
      ),
    );
  }

  Widget _buildEducationEntry(
      EducationEntry entry, String language, bool isAr) {
    final degree = entry.degree.resolve(language);
    final school = entry.institution.resolve(language);
    final field = entry.fieldOfStudy.resolve(language);
    final title =
        '$degree${degree.isNotEmpty && field.isNotEmpty ? ' - ' : ''}$field';

    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            title.isNotEmpty ? title : (isAr ? 'الدرجة العلمية' : 'Degree'),
            style: const TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.bold,
                color: _PaperPalette.inkPrimary),
          ),
          if (school.isNotEmpty)
            Text(
              school,
              style:
                  const TextStyle(fontSize: 10, color: _PaperPalette.inkMuted),
            ),
        ],
      ),
    );
  }
}
