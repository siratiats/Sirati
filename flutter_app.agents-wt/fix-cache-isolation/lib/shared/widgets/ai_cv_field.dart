import 'package:flutter/material.dart';
import 'package:sirati/core/utils/bidi_text.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/loading/ai_field_loading_overlay.dart';
import 'package:sirati/shared/widgets/submit_button.dart';

class AiCvField extends StatelessWidget {
  const AiCvField({
    super.key,
    required this.field,
    required this.controller,
    required this.english,
    required this.isLoading,
    required this.helperText,
    required this.child,
    required this.onEnhance,
    required this.onDismissResult,
    this.result,
    this.minimumCharacters,
    this.leadingHint,
  });

  final String field;
  final TextEditingController controller;
  final bool english;
  final bool isLoading;
  final String helperText;
  final Widget child;
  final VoidCallback onEnhance;
  final VoidCallback onDismissResult;
  final Map<String, dynamic>? result;
  final int? minimumCharacters;
  final Widget? leadingHint;

  /// Default character threshold representing more than a single text line on mobile screens.
  static const int defaultMinimumCharacters = 40;

  /// Determines whether the input text contains sufficient context (more than a line)
  /// so the AI can generate accurate, grounded enhancements rather than guessing.
  static bool isSufficientForEnhancement(String text, {int? customMinimum}) {
    final trimmed = text.trim();
    if (trimmed.isEmpty) return false;

    if (customMinimum != null) {
      return trimmed.length >= customMinimum;
    }

    if (trimmed.length >= defaultMinimumCharacters) {
      return true;
    }

    // Check if the user entered multiple lines with line breaks.
    final lines = trimmed
        .split('\n')
        .map((line) => line.trim())
        .where((line) => line.isNotEmpty)
        .toList();

    return lines.length >= 2 && trimmed.length >= 20;
  }

  @override
  Widget build(BuildContext context) {
    final displayHelper =
        english ? helperText : BidiText.protectLatinTokens(helperText);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (leadingHint != null) ...[
          leadingHint!,
          const SizedBox(height: 10),
        ],
        AiFieldLoadingOverlay(
          key: Key('ai_overlay_$field'),
          isLoading: isLoading,
          statusMessages:
              AiFieldLoadingOverlay.defaultStatusMessages(english: english),
          semanticsLabel:
              english ? 'Enhancing $field' : 'جارٍ تحسين حقل السيرة الذاتية',
          child: child,
        ),
        const SizedBox(height: 7),
        Text(
          displayHelper,
          textAlign: TextAlign.start,
          style: TextStyle(
            color: context.sirati.textSecondary,
            fontSize: 12,
            height: 1.45,
          ),
        ),
        if (minimumCharacters != null) ...[
          const SizedBox(height: 8),
          ValueListenableBuilder<TextEditingValue>(
            valueListenable: controller,
            builder: (context, value, _) {
              final count = value.text.trim().length;
              final minimum = minimumCharacters!;
              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  LinearProgressIndicator(
                    key: Key('character_progress_$field'),
                    minHeight: 4,
                    value: (count / minimum).clamp(0, 1),
                    color: count >= minimum
                        ? context.sirati.success
                        : context.sirati.primary,
                    backgroundColor: context.sirati.surfaceHigh,
                    borderRadius: BorderRadius.circular(999),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    english
                        ? '$count / $minimum characters minimum'
                        : '$count / $minimum حرفاً كحد أدنى',
                    key: Key('character_count_$field'),
                    style: TextStyle(
                      color: count >= minimum
                          ? context.sirati.success
                          : context.sirati.textSecondary,
                      fontSize: 11.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              );
            },
          ),
        ],
        const SizedBox(height: 10),
        ValueListenableBuilder<TextEditingValue>(
          valueListenable: controller,
          builder: (context, value, _) {
            final enabled = isSufficientForEnhancement(
                  value.text,
                  customMinimum: minimumCharacters,
                ) &&
                !isLoading;
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SubmitButton(
                  key: Key('enhance_$field'),
                  label: english ? 'Enhance' : 'تحسين',
                  loadingLabel: english ? 'Enhancing...' : 'جارٍ التحسين...',
                  isLoading: isLoading,
                  outlined: true,
                  height: 44,
                  icon: Icons.auto_fix_high_rounded,
                  onPressed: enabled ? onEnhance : null,
                ),
                // Always say why the button is off — an empty field included —
                // so a disabled Enhance never looks broken.
                if (!enabled && !isLoading) ...[
                  const SizedBox(height: 5),
                  Row(
                    children: [
                      Icon(
                        Icons.info_outline_rounded,
                        size: 13,
                        color: context.sirati.textSecondary,
                      ),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          key: Key('enhance_hint_$field'),
                          english
                              ? 'Write at least ${minimumCharacters ?? defaultMinimumCharacters} characters for AI to enhance'
                              : 'اكتب ${minimumCharacters ?? defaultMinimumCharacters} حرفاً على الأقل ليحسّنها الذكاء الاصطناعي',
                          style: TextStyle(
                            color: context.sirati.textSecondary,
                            fontSize: 11,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            );
          },
        ),
        if (result != null) ...[
          const SizedBox(height: 12),
          _EnhancementResultCard(
            field: field,
            result: result!,
            english: english,
            onDismiss: onDismissResult,
          ),
        ],
      ],
    );
  }
}

class _EnhancementResultCard extends StatelessWidget {
  const _EnhancementResultCard({
    required this.field,
    required this.result,
    required this.english,
    required this.onDismiss,
  });

  final String field;
  final Map<String, dynamic> result;
  final bool english;
  final VoidCallback onDismiss;

  List<String> _strings(String key) => (result[key] as List? ?? const [])
      .map((item) => item.toString().trim())
      .where((item) => item.isNotEmpty)
      .toList();

  List<Map<String, String>> _claims() =>
      (result['unverified_claims'] as List? ?? const [])
          .whereType<Map>()
          .map((claim) => {
                'text': claim['text']?.toString().trim() ?? '',
                'kind': claim['kind']?.toString() ?? '',
              })
          .where((claim) => claim['text']!.isNotEmpty)
          .toList();

  @override
  Widget build(BuildContext context) {
    final changes = _strings('changes_made');
    final missing = _strings('missing_facts');
    final keywords = _strings('ats_keywords_added');
    final claims = _claims();
    final c = context.sirati;

    return Container(
      key: Key('enhancement_result_$field'),
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: c.surfaceLow,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.auto_awesome_rounded, size: 18, color: c.primary),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  english ? 'What Sirati improved' : 'ما الذي حسّنته سيرتي',
                  style: TextStyle(
                    color: c.textPrimary,
                    fontSize: 13.5,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              IconButton(
                tooltip: english ? 'Dismiss' : 'إخفاء',
                onPressed: onDismiss,
                visualDensity: VisualDensity.compact,
                icon: const Icon(Icons.close_rounded, size: 18),
              ),
            ],
          ),
          if (changes.isNotEmpty)
            ...changes.map((item) => _ResultLine(
                  icon: Icons.check_rounded,
                  text: item,
                  color: c.success,
                )),
          if (missing.isNotEmpty) ...[
            const SizedBox(height: 10),
            Container(
              key: Key('missing_facts_$field'),
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: c.warningLight,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    english
                        ? 'Complete these facts yourself'
                        : 'أكمل هذه المعلومات بنفسك',
                    style: TextStyle(
                      color: c.textPrimary,
                      fontSize: 12.5,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 6),
                  ...missing.map((item) => _ResultLine(
                        icon: Icons.check_box_outline_blank_rounded,
                        text: item,
                        color: c.warning,
                      )),
                ],
              ),
            ),
          ],
          if (claims.isNotEmpty) ...[
            const SizedBox(height: 10),
            Container(
              key: Key('unverified_claims_$field'),
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: c.warningLight,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: c.warning.withValues(alpha: 0.35)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    english
                        ? 'Verify these details in the enhanced text'
                        : 'تحقق من هذه التفاصيل في النص المحسّن',
                    style: TextStyle(
                      color: c.textPrimary,
                      fontSize: 12.5,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 7),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: claims
                        .map((claim) => Chip(
                              key: Key(
                                  'unverified_${field}_${claim['kind']}_${claim['text']}'),
                              avatar: Icon(
                                claim['kind'] == 'date'
                                    ? Icons.event_outlined
                                    : Icons.business_outlined,
                                size: 16,
                                color: c.warning,
                              ),
                              label: Text(
                                BidiText.protectLatinTokens(claim['text']!),
                              ),
                              visualDensity: VisualDensity.compact,
                              backgroundColor: c.surfaceLow,
                              side: BorderSide(color: c.warning),
                            ))
                        .toList(),
                  ),
                ],
              ),
            ),
          ],
          if (keywords.isNotEmpty) ...[
            const SizedBox(height: 10),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: keywords
                  .map((keyword) => Chip(
                        key: Key('ats_keyword_${field}_$keyword'),
                        label: Text(BidiText.looksLatin(keyword)
                            ? BidiText.isolateLtr(keyword)
                            : keyword),
                        visualDensity: VisualDensity.compact,
                        backgroundColor: c.primaryLight,
                        side: BorderSide.none,
                      ))
                  .toList(),
            ),
          ],
        ],
      ),
    );
  }
}

class _ResultLine extends StatelessWidget {
  const _ResultLine({
    required this.icon,
    required this.text,
    required this.color,
  });

  final IconData icon;
  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 5),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 17, color: color),
          const SizedBox(width: 7),
          Expanded(
            child: Text(
              BidiText.protectLatinTokens(text),
              textAlign: TextAlign.start,
              style: TextStyle(
                color: context.sirati.textSecondary,
                fontSize: 12,
                height: 1.4,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
