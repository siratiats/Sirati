import 'package:flutter/material.dart';
import 'package:sirati/core/utils/bidi_text_utils.dart';
import 'package:sirati/features/cv_builder/controllers/cv_builder_controller.dart';
import 'package:sirati/features/cv_builder/presentation/section_editor_framework.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/cv_document.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/components.dart';

/// Validation utilities for Gulf phone numbers and emails (SIRATI-37).
class ContactValidators {
  /// Validates Gulf and international E.164 phone formats.
  /// Accepts formats:
  /// - KSA: +9665xxxxxxxx, 05xxxxxxxx
  /// - UAE: +9715xxxxxxxx, 05xxxxxxxx
  /// - Kuwait: +965xxxxxxxx
  /// - Qatar: +974xxxxxxxx
  /// - Bahrain: +973xxxxxxxx
  /// - Oman: +968xxxxxxxx
  /// - International E.164: +[country code][number]
  static String? validateGulfPhone(String? value, AppLocalizations l10n) {
    if (value == null || value.trim().isEmpty) return null;
    final cleaned = value.replaceAll(RegExp(r'[\s\-()]'), '');

    // Gulf country patterns with explicit country codes
    final ksaMobile = RegExp(r'^(\+966|00966|966)0?5[0-9]{8}$');
    final ksaLandline = RegExp(r'^(\+966|00966|966)0?1[1-7][0-9]{7}$');
    final uaeMobile = RegExp(r'^(\+971|00971|971)0?5[0-9]{8}$');
    final uaeLandline = RegExp(r'^(\+971|00971|971)0?[234679][0-9]{7}$');
    final kuwaitPattern = RegExp(r'^(\+965|00965|965)[2569][0-9]{7}$');
    final qatarPattern = RegExp(r'^(\+974|00974|974)[34567][0-9]{7}$');
    final bahrainPattern = RegExp(r'^(\+973|00973|973)[1367][0-9]{7}$');
    final omanPattern = RegExp(r'^(\+968|00968|968)[279][0-9]{7}$');

    // 1. Explicit country-code checks (enforcing strict country format and preventing fallback to generic E.164)
    if (cleaned.startsWith('+966') ||
        cleaned.startsWith('00966') ||
        cleaned.startsWith('966')) {
      return (ksaMobile.hasMatch(cleaned) || ksaLandline.hasMatch(cleaned))
          ? null
          : l10n.invalidSaudiPhone;
    }

    if (cleaned.startsWith('+971') ||
        cleaned.startsWith('00971') ||
        cleaned.startsWith('971')) {
      return (uaeMobile.hasMatch(cleaned) || uaeLandline.hasMatch(cleaned))
          ? null
          : l10n.invalidUaePhone;
    }

    if (cleaned.startsWith('+965') ||
        cleaned.startsWith('00965') ||
        cleaned.startsWith('965')) {
      return kuwaitPattern.hasMatch(cleaned) ? null : l10n.invalidKuwaitPhone;
    }

    if (cleaned.startsWith('+974') ||
        cleaned.startsWith('00974') ||
        cleaned.startsWith('974')) {
      return qatarPattern.hasMatch(cleaned) ? null : l10n.invalidQatarPhone;
    }

    if (cleaned.startsWith('+973') ||
        cleaned.startsWith('00973') ||
        cleaned.startsWith('973')) {
      return bahrainPattern.hasMatch(cleaned) ? null : l10n.invalidBahrainPhone;
    }

    if (cleaned.startsWith('+968') ||
        cleaned.startsWith('00968') ||
        cleaned.startsWith('968')) {
      return omanPattern.hasMatch(cleaned) ? null : l10n.invalidOmanPhone;
    }

    // 2. Domestic numbers with national trunk prefix '0' (Saudi and UAE local formats)
    if (cleaned.startsWith('0')) {
      final ksaLocalMobile = RegExp(r'^05[0-9]{8}$');
      final ksaLocalLandline = RegExp(r'^01[1-7][0-9]{7}$');
      final uaeLocalMobile = RegExp(r'^05[0-9]{8}$');
      final uaeLocalLandline = RegExp(r'^0[234679][0-9]{7}$');

      if (ksaLocalMobile.hasMatch(cleaned) ||
          ksaLocalLandline.hasMatch(cleaned) ||
          uaeLocalMobile.hasMatch(cleaned) ||
          uaeLocalLandline.hasMatch(cleaned)) {
        return null;
      }
    }

    // 3. International E.164 (must start with '+')
    final genericE164 = RegExp(r'^\+[1-9]\d{7,14}$');
    if (genericE164.hasMatch(cleaned)) {
      return null;
    }

    return l10n.invalidGulfOrIntlPhone;
  }

  /// Validates standard RFC-compliant email address (supporting ASCII and unicode apostrophes).
  static String? validateEmail(String? value, AppLocalizations l10n) {
    if (value == null || value.trim().isEmpty) return null;
    final emailRegex = RegExp(
      r"^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~’\-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)+$",
    );
    if (!emailRegex.hasMatch(value.trim())) {
      return l10n.invalidEmail;
    }
    return null;
  }
}

/// Personal details and professional summary section editor (SIRATI-37).
class PersonalDetailsEditor extends StatefulWidget {
  final CvBuilderController controller;

  const PersonalDetailsEditor({
    super.key,
    required this.controller,
  });

  @override
  State<PersonalDetailsEditor> createState() => _PersonalDetailsEditorState();
}

class _PersonalDetailsEditorState extends State<PersonalDetailsEditor> {
  // Bilingual view mode: 'dual', 'ar', 'en'
  String _languageMode = 'dual';

  late final TextEditingController _nameArCtrl;
  late final TextEditingController _nameEnCtrl;
  late final TextEditingController _headlineArCtrl;
  late final TextEditingController _headlineEnCtrl;
  late final TextEditingController _locationArCtrl;
  late final TextEditingController _locationEnCtrl;
  late final TextEditingController _emailCtrl;
  late final TextEditingController _phoneCtrl;
  late final TextEditingController _linkedinCtrl;
  late final TextEditingController _summaryArCtrl;
  late final TextEditingController _summaryEnCtrl;

  String? _phoneError;
  String? _emailError;
  bool _includePhoto = false;

  @override
  void initState() {
    super.initState();
    final p = widget.controller.document.personal;
    final s = widget.controller.document.summary;

    _nameArCtrl = TextEditingController(text: p.fullName.ar);
    _nameEnCtrl = TextEditingController(text: p.fullName.en);
    _headlineArCtrl = TextEditingController(text: p.headline.ar);
    _headlineEnCtrl = TextEditingController(text: p.headline.en);
    _locationArCtrl = TextEditingController(text: p.location.ar);
    _locationEnCtrl = TextEditingController(text: p.location.en);
    _emailCtrl = TextEditingController(text: p.email ?? '');
    _phoneCtrl = TextEditingController(text: p.phone ?? '');
    _linkedinCtrl = TextEditingController(text: p.linkedin ?? '');
    _summaryArCtrl = TextEditingController(text: s.ar);
    _summaryEnCtrl = TextEditingController(text: s.en);
  }

  @override
  void dispose() {
    _nameArCtrl.dispose();
    _nameEnCtrl.dispose();
    _headlineArCtrl.dispose();
    _headlineEnCtrl.dispose();
    _locationArCtrl.dispose();
    _locationEnCtrl.dispose();
    _emailCtrl.dispose();
    _phoneCtrl.dispose();
    _linkedinCtrl.dispose();
    _summaryArCtrl.dispose();
    _summaryEnCtrl.dispose();
    super.dispose();
  }

  void _onPersonalChanged() {
    final phoneText = _phoneCtrl.text.trim();
    final emailText = _emailCtrl.text.trim();

    final l10n = AppLocalizations.of(context);
    setState(() {
      _phoneError = ContactValidators.validateGulfPhone(phoneText, l10n);
      _emailError = ContactValidators.validateEmail(emailText, l10n);
    });

    widget.controller.updatePersonal(
      PersonalDetails(
        fullName: LocalizedText(
          ar: _nameArCtrl.text.trim(),
          en: _nameEnCtrl.text.trim(),
        ),
        headline: LocalizedText(
          ar: _headlineArCtrl.text.trim(),
          en: _headlineEnCtrl.text.trim(),
        ),
        location: LocalizedText(
          ar: _locationArCtrl.text.trim(),
          en: _locationEnCtrl.text.trim(),
        ),
        email: emailText.isEmpty ? null : emailText,
        phone: phoneText.isEmpty ? null : phoneText,
        linkedin: _linkedinCtrl.text.trim().isEmpty
            ? null
            : _linkedinCtrl.text.trim(),
      ),
    );
  }

  void _onSummaryChanged() {
    setState(() {}); // Update word / character counts
    widget.controller.updateSummary(
      LocalizedText(
        ar: _summaryArCtrl.text.trim(),
        en: _summaryEnCtrl.text.trim(),
      ),
    );
  }

  int _countWords(String text) {
    final trimmed = text.trim();
    if (trimmed.isEmpty) return 0;
    return trimmed.split(RegExp(r'\s+')).length;
  }

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    final l10n = AppLocalizations.of(context);
    final showAr = _languageMode == 'dual' || _languageMode == 'ar';
    final showEn = _languageMode == 'dual' || _languageMode == 'en';

    final summaryArWords = _countWords(_summaryArCtrl.text);
    final summaryArChars = _summaryArCtrl.text.length;
    final summaryEnWords = _countWords(_summaryEnCtrl.text);
    final summaryEnChars = _summaryEnCtrl.text.length;

    return Column(
      children: [
        // ── Personal Details Card ──────────────────────────────────────────
        SectionCardWrapper(
          title: l10n.personalDetailsTitle,
          subtitle: l10n.personalDetailsSubtitle,
          icon: Icons.person_outline,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Language Mode Selector
              Wrap(
                alignment: WrapAlignment.spaceBetween,
                crossAxisAlignment: WrapCrossAlignment.center,
                spacing: AppSpacing.sm,
                runSpacing: AppSpacing.xs,
                children: [
                  Text(
                    l10n.inputLanguageLabel,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: c.textSecondary,
                          fontWeight: FontWeight.bold,
                        ),
                  ),
                  SegmentedButton<String>(
                    segments: [
                      ButtonSegment(
                          value: 'dual', label: Text(l10n.languageModeDual)),
                      ButtonSegment(
                          value: 'ar', label: Text(l10n.languageModeArOnly)),
                      ButtonSegment(
                          value: 'en', label: Text(l10n.languageModeEnOnly)),
                    ],
                    selected: {_languageMode},
                    onSelectionChanged: (set) =>
                        setState(() => _languageMode = set.first),
                    style: ButtonStyle(
                      visualDensity: VisualDensity.compact,
                      textStyle: WidgetStatePropertyAll(
                        Theme.of(context).textTheme.labelSmall,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.md),

              // Full Name
              if (showAr) ...[
                AppInput(
                  controller: _nameArCtrl,
                  label: l10n.fullNameAr,
                  hint: l10n.fullNameArHint,
                  onChanged: (_) => _onPersonalChanged(),
                ),
                const SizedBox(height: AppSpacing.sm),
              ],
              if (showEn) ...[
                AppInput(
                  controller: _nameEnCtrl,
                  label: l10n.fullNameEn,
                  hint: l10n.fullNameEnHint,
                  onChanged: (_) => _onPersonalChanged(),
                ),
                const SizedBox(height: AppSpacing.sm),
              ],

              // Job Title / Headline
              if (showAr) ...[
                AppInput(
                  controller: _headlineArCtrl,
                  label: l10n.headlineAr,
                  hint: l10n.headlineArHint,
                  onChanged: (_) => _onPersonalChanged(),
                ),
                const SizedBox(height: AppSpacing.sm),
              ],
              if (showEn) ...[
                AppInput(
                  controller: _headlineEnCtrl,
                  label: l10n.headlineEn,
                  hint: l10n.headlineEnHint,
                  onChanged: (_) => _onPersonalChanged(),
                ),
                const SizedBox(height: AppSpacing.sm),
              ],

              // Location
              if (showAr) ...[
                AppInput(
                  controller: _locationArCtrl,
                  label: l10n.locationAr,
                  hint: l10n.locationArHint,
                  onChanged: (_) => _onPersonalChanged(),
                ),
                const SizedBox(height: AppSpacing.sm),
              ],
              if (showEn) ...[
                AppInput(
                  controller: _locationEnCtrl,
                  label: l10n.locationEn,
                  hint: l10n.locationEnHint,
                  onChanged: (_) => _onPersonalChanged(),
                ),
                const SizedBox(height: AppSpacing.sm),
              ],

              // Direct Contacts (Email, Phone, LinkedIn)
              AppInput(
                controller: _emailCtrl,
                label: l10n.emailLabel,
                hint: 'name@example.com',
                keyboardType: TextInputType.emailAddress,
                textDirection: TextDirection.ltr,
                prefixIcon: const Icon(Icons.email_outlined, size: 20),
                errorText: _emailError,
                onChanged: (_) => _onPersonalChanged(),
              ),
              const SizedBox(height: AppSpacing.sm),

              AppInput(
                controller: _phoneCtrl,
                label: l10n.phoneLabel,
                hint: '+966501234567',
                keyboardType: TextInputType.phone,
                textDirection: TextDirection.ltr,
                prefixIcon: const Icon(Icons.phone_outlined, size: 20),
                errorText: _phoneError,
                helperText: l10n.phoneHelper,
                inputFormatters: const [LogicalBidiInputFormatter()],
                onChanged: (_) => _onPersonalChanged(),
              ),
              const SizedBox(height: AppSpacing.sm),

              AppInput(
                controller: _linkedinCtrl,
                label: l10n.linkedinLabel,
                hint: 'linkedin.com/in/username',
                keyboardType: TextInputType.url,
                textDirection: TextDirection.ltr,
                prefixIcon: const Icon(Icons.link, size: 20),
                onChanged: (_) => _onPersonalChanged(),
              ),
              const SizedBox(height: AppSpacing.md),

              // ── ATS Photo Warning & Toggle ────────────────────────────────
              Container(
                padding: const EdgeInsets.all(AppSpacing.sm),
                decoration: BoxDecoration(
                  color: c.surfaceHigh,
                  borderRadius: BorderRadius.circular(AppRadius.md),
                  border: Border.all(color: c.border),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(Icons.info_outline, color: c.primary, size: 20),
                        const SizedBox(width: AppSpacing.xs),
                        Expanded(
                          child: Text(
                            l10n.atsPhotoWarningTitle,
                            style: Theme.of(context)
                                .textTheme
                                .titleSmall
                                ?.copyWith(
                                  fontWeight: FontWeight.bold,
                                  color: c.textPrimary,
                                ),
                          ),
                        ),
                        Switch(
                          value: _includePhoto,
                          onChanged: (val) =>
                              setState(() => _includePhoto = val),
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.xs),
                    Text(
                      l10n.atsPhotoWarningBody,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: c.textSecondary,
                            height: 1.4,
                          ),
                    ),
                    if (_includePhoto) ...[
                      const SizedBox(height: AppSpacing.sm),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: AppSpacing.sm,
                          vertical: AppSpacing.xs,
                        ),
                        decoration: BoxDecoration(
                          color: c.primaryLight,
                          borderRadius: BorderRadius.circular(AppRadius.sm),
                        ),
                        child: Row(
                          children: [
                            Icon(Icons.account_circle,
                                color: c.primary, size: 24),
                            const SizedBox(width: AppSpacing.xs),
                            Expanded(
                              child: Text(
                                l10n.atsPhotoEnabled,
                                style: Theme.of(context)
                                    .textTheme
                                    .labelSmall
                                    ?.copyWith(
                                      color: c.primary,
                                      fontWeight: FontWeight.bold,
                                    ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),

        const SizedBox(height: AppSpacing.md),

        // ── Professional Summary Card ──────────────────────────────────────
        SectionCardWrapper(
          title: l10n.professionalSummaryTitle,
          subtitle: l10n.professionalSummarySubtitle,
          icon: Icons.article_outlined,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (showAr) ...[
                Text(
                  l10n.summaryArLabel,
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                ),
                const SizedBox(height: AppSpacing.xs),
                AppInput(
                  controller: _summaryArCtrl,
                  hint: l10n.summaryArHint,
                  maxLines: 4,
                  onChanged: (_) => _onSummaryChanged(),
                ),
                const SizedBox(height: AppSpacing.xs),
                _buildWordCountGuidance(
                  context,
                  words: summaryArWords,
                  chars: summaryArChars,
                ),
                const SizedBox(height: AppSpacing.md),
              ],
              if (showEn) ...[
                Text(
                  l10n.summaryEnLabel,
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                ),
                const SizedBox(height: AppSpacing.xs),
                AppInput(
                  controller: _summaryEnCtrl,
                  hint: l10n.summaryEnHint,
                  maxLines: 4,
                  onChanged: (_) => _onSummaryChanged(),
                ),
                const SizedBox(height: AppSpacing.xs),
                _buildWordCountGuidance(
                  context,
                  words: summaryEnWords,
                  chars: summaryEnChars,
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildWordCountGuidance(
    BuildContext context, {
    required int words,
    required int chars,
  }) {
    final c = context.sirati;

    String advice;
    Color adviceColor;

    final l10n = AppLocalizations.of(context);
    if (words == 0) {
      advice = l10n.summaryAdviceEmpty;
      adviceColor = c.textSecondary;
    } else if (words < 25) {
      advice = l10n.summaryAdviceShort;
      adviceColor = c.textSecondary;
    } else if (words <= 200) {
      advice = l10n.summaryAdviceGood;
      adviceColor = c.primary;
    } else {
      advice = l10n.summaryAdviceLong;
      adviceColor = c.textSecondary;
    }

    return Row(
      children: [
        Icon(Icons.format_quote_outlined, size: 14, color: adviceColor),
        const SizedBox(width: AppSpacing.xxs),
        Expanded(
          child: Text(
            l10n.summaryWordCountLine(words, chars, advice),
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: adviceColor,
                  fontSize: 11,
                ),
          ),
        ),
      ],
    );
  }
}
