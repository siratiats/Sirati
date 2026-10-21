import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'package:sirati/core/utils/bidi_text.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/form_fields.dart';

/// Starter verbs offered as one-tap chips for the action field.
const achievementVerbsEn = [
  'Developed',
  'Led a team to deliver',
  'Optimized',
  'Automated',
  'Increased revenue',
  'Reduced costs',
];
const achievementVerbsAr = [
  'طوّرت',
  'قدت فريقاً لتنفيذ',
  'حسّنت كفاءة',
  'أتمتت عمليات',
  'رفعت مبيعات',
  'قلّصت زمن استجابة',
];

/// Put [verb] at the start of the action, replacing any starter verb that is
/// already there, so repeated or successive taps never stack verbs.
String applyAchievementVerb(String current, String verb, List<String> verbs) {
  var rest = current.trim();
  // Longest first, so a verb that prefixes another is not half-stripped.
  final byLength = [...verbs]..sort((a, b) => b.length - a.length);
  for (final v in byLength) {
    if (rest == v || rest.startsWith('$v ')) {
      rest = rest.substring(v.length).trim();
      break;
    }
  }
  return rest.isEmpty ? verb : '$verb $rest';
}

/// [experience] with [bullet] on a new line, or null when that achievement is
/// already one of its lines.
String? appendAchievement(String experience, String bullet) {
  final line = bullet.trim();
  final current = experience.trim();
  if (current.isEmpty) return line;
  final lines = current.split('\n').map((l) => l.trim());
  if (lines.contains(line)) return null;
  return '$current\n$line';
}

/// Whether [action] says more than a bare starter verb.
bool isAchievementActionComplete(String action, List<String> verbs) {
  final t = action.trim();
  return t.isNotEmpty && !verbs.contains(t);
}

/// Modal bottom sheet that guides users to craft high-impact, ATS-optimized
/// quantifiable achievements using the Google XYZ Formula:
/// "Accomplished [X] as measured by [Y] by doing [Z]".
class AchievementBuilderSheet extends StatefulWidget {
  final bool english;
  final ValueChanged<String> onInsert;

  const AchievementBuilderSheet({
    super.key,
    required this.english,
    required this.onInsert,
  });

  static Future<void> show({
    required BuildContext context,
    required bool english,
    required ValueChanged<String> onInsert,
  }) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: context.sirati.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => AchievementBuilderSheet(
        english: english,
        onInsert: onInsert,
      ),
    );
  }

  @override
  State<AchievementBuilderSheet> createState() =>
      _AchievementBuilderSheetState();
}

class _AchievementBuilderSheetState extends State<AchievementBuilderSheet> {
  final TextEditingController _actionCtrl = TextEditingController();
  final TextEditingController _metricCtrl = TextEditingController();
  final TextEditingController _methodCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _actionCtrl.addListener(_onFieldChanged);
    _metricCtrl.addListener(_onFieldChanged);
    _methodCtrl.addListener(_onFieldChanged);
  }

  @override
  void dispose() {
    _actionCtrl.dispose();
    _metricCtrl.dispose();
    _methodCtrl.dispose();
    super.dispose();
  }

  void _onFieldChanged() {
    setState(() {});
  }

  String get _formattedSentence {
    final action = _actionCtrl.text.trim();
    final metric = _metricCtrl.text.trim();
    final method = _methodCtrl.text.trim();

    if (action.isEmpty && metric.isEmpty && method.isEmpty) {
      return '';
    }

    if (widget.english) {
      final buffer = StringBuffer('• $action');
      if (metric.isNotEmpty) {
        if (!metric.toLowerCase().startsWith('by') &&
            !metric.toLowerCase().startsWith('with') &&
            !metric.toLowerCase().startsWith('resulting')) {
          buffer.write(', resulting in $metric');
        } else {
          buffer.write(', $metric');
        }
      }
      if (method.isNotEmpty) {
        if (!method.toLowerCase().startsWith('by') &&
            !method.toLowerCase().startsWith('via') &&
            !method.toLowerCase().startsWith('using')) {
          buffer.write(' by $method');
        } else {
          buffer.write(' $method');
        }
      }
      if (!buffer.toString().endsWith('.')) {
        buffer.write('.');
      }
      return buffer.toString();
    } else {
      final buffer = StringBuffer('• $action');
      if (metric.isNotEmpty) {
        buffer.write('، $metric');
      }
      if (method.isNotEmpty) {
        if (!method.startsWith('عبر') &&
            !method.startsWith('من خلال') &&
            !method.startsWith('باستخدام')) {
          buffer.write('، عبر $method');
        } else {
          buffer.write('، $method');
        }
      }
      if (!buffer.toString().endsWith('.')) {
        buffer.write('.');
      }
      return buffer.toString();
    }
  }

  void _applyPreset({
    required String action,
    required String metric,
    required String method,
  }) {
    HapticFeedback.selectionClick();
    setState(() {
      _actionCtrl.text = action;
      _metricCtrl.text = metric;
      _methodCtrl.text = method;
    });
  }

  void _insert() {
    final verbs = widget.english ? achievementVerbsEn : achievementVerbsAr;
    if (!isAchievementActionComplete(_actionCtrl.text, verbs)) return;
    final sentence = _formattedSentence;
    HapticFeedback.mediumImpact();
    widget.onInsert(sentence);
    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    final english = widget.english;
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;
    final actionVerbs = english ? achievementVerbsEn : achievementVerbsAr;
    final canInsert =
        isAchievementActionComplete(_actionCtrl.text, actionVerbs);

    final metricPills = english
        ? [
            'by 25%',
            'by 40%',
            'saving 10 hrs/week',
            'generating \$50K revenue',
            'achieving 99.9% uptime'
          ]
        : [
            'بنسبة 25%',
            'بنسبة 40%',
            'توفير 10 ساعات أسبوعياً',
            'بقيمة 200,000 ريال',
            'بنسبة دقة 99.9%'
          ];

    return Padding(
      padding: EdgeInsets.only(bottom: bottomInset),
      child: SingleChildScrollView(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              // ── Handle bar ───────────────────────────────────────────────
              Center(
                child: Container(
                  width: 44,
                  height: 4,
                  decoration: BoxDecoration(
                    color: c.border,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // ── Header ───────────────────────────────────────────────────
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: c.primaryLight,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(
                      Icons.auto_awesome_rounded,
                      size: 20,
                      color: c.primaryDark,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          english
                              ? 'XYZ Achievement Builder'
                              : 'صانع الإنجازات المقاسة (معادلة XYZ)',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                            color: c.textPrimary,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          english
                              ? 'Accomplished [X] as measured by [Y] by doing [Z]'
                              : 'أنجزت [س] مقاساً بـ [ص] عبر [ع] — صيغة الـ ATS القياسية',
                          style: TextStyle(
                            fontSize: 12,
                            color: c.textSecondary,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close_rounded, size: 20),
                    color: c.textSecondary,
                  ),
                ],
              ),
              const SizedBox(height: 16),

              // ── Quick Presets Carousel ────────────────────────────────────
              Text(
                english
                    ? 'Quick Inspiration Presets:'
                    : 'نماذج إلهام سريعة بنقرة واحدة:',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: c.primaryDark,
                ),
              ),
              const SizedBox(height: 8),
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: [
                    _PresetChip(
                      label: english
                          ? 'Tech / Performance'
                          : 'تحسين الأداء التقني',
                      onTap: () => _applyPreset(
                        action: english
                            ? 'Optimized database queries and API response times'
                            : 'حسّنت سرعة استجابة النظام وواجهات البرمجة (APIs)',
                        metric: english ? 'by 35%' : 'بنسبة 35%',
                        method: english
                            ? 'using Redis caching and SQL indexing'
                            : 'عبر استخدام Redis وإعادة هيكلة فهارس SQL',
                      ),
                    ),
                    const SizedBox(width: 8),
                    _PresetChip(
                      label: english
                          ? 'Sales / Revenue'
                          : 'زيادة المبيعات والأرباح',
                      onTap: () => _applyPreset(
                        action: english
                            ? 'Increased quarterly sales pipeline and client deals'
                            : 'رفعت مبيعات الربع السنوي وحجم الصفقات الجديدة',
                        metric: english ? 'by 28%' : 'بنسبة 28%',
                        method: english
                            ? 'by targeting enterprise B2B accounts and restructuring pricing'
                            : 'عبر استهداف عملاء B2B وإعادة هيكلة خطة التسعير',
                      ),
                    ),
                    const SizedBox(width: 8),
                    _PresetChip(
                      label: english
                          ? 'Project / Leadership'
                          : 'إدارة وتسليم المشاريع',
                      onTap: () => _applyPreset(
                        action: english
                            ? 'Led cross-functional team to deliver 4 major releases'
                            : 'قدت فريقاً متعدد التخصصات لتسليم 4 مشاريع كبرى في موعدها',
                        metric: english
                            ? '100% on schedule and within budget'
                            : 'في الموعد المحدد وبوفورات 15% في الميزانية',
                        method: english
                            ? 'using Agile sprints and Jira milestone tracking'
                            : 'بتطبيق منهجية Agile ومتابعة مؤشرات الأداء عبر Jira',
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),

              // ── Field 1: Action (X) ──────────────────────────────────────
              Text(
                english
                    ? '1. What did you accomplish? (Action - X) *'
                    : '1. ماذا أنجزت أو طوّرت؟ (الإجراء - س) *',
                style: TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w700,
                  color: c.textPrimary,
                ),
              ),
              const SizedBox(height: 6),
              AppTextFormField(
                controller: _actionCtrl,
                textInputAction: TextInputAction.next,
                hintText: english
                    ? 'e.g. Developed REST APIs and payment gateway'
                    : 'مثال: طوّرت واجهات برمجة التطبيقات وبوابة الدفع',
              ),
              const SizedBox(height: 6),
              Wrap(
                spacing: 6,
                runSpacing: 4,
                children: actionVerbs
                    .map((pill) => ActionChip(
                          label: Text(pill,
                              style: TextStyle(
                                  fontSize: 11, color: c.textSecondary)),
                          padding: EdgeInsets.zero,
                          visualDensity: VisualDensity.compact,
                          backgroundColor: c.surfaceLow,
                          side: BorderSide(color: c.border),
                          onPressed: () => _actionCtrl.text =
                              applyAchievementVerb(
                                  _actionCtrl.text, pill, actionVerbs),
                        ))
                    .toList(),
              ),
              const SizedBox(height: 14),

              // ── Field 2: Metric / Number (Y) ──────────────────────────────
              Text(
                english
                    ? '2. What was the measurable result? (Metric - Y)'
                    : '2. ما النتيجة أو المقياس الرقمي؟ (المقياس - ص)',
                style: TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w700,
                  color: c.textPrimary,
                ),
              ),
              const SizedBox(height: 6),
              AppTextFormField(
                controller: _metricCtrl,
                textInputAction: TextInputAction.next,
                hintText: english
                    ? 'e.g. by 35% or saving 15 hours weekly'
                    : 'مثال: بنسبة 35% أو توفير 15 ساعة أسبوعياً',
              ),
              const SizedBox(height: 6),
              Wrap(
                spacing: 6,
                runSpacing: 4,
                children: metricPills
                    .map((pill) => ActionChip(
                          label: Text(pill,
                              style: TextStyle(
                                  fontSize: 11, color: c.textSecondary)),
                          padding: EdgeInsets.zero,
                          visualDensity: VisualDensity.compact,
                          backgroundColor: c.surfaceLow,
                          side: BorderSide(color: c.border),
                          onPressed: () {
                            _metricCtrl.text = pill;
                          },
                        ))
                    .toList(),
              ),
              const SizedBox(height: 14),

              // ── Field 3: Method / Tools (Z) ──────────────────────────────
              Text(
                english
                    ? '3. How did you achieve it? (Method / Tools - Z)'
                    : '3. كيف حققته وما الأدوات المستخدمة؟ (الأسلوب - ع)',
                style: TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w700,
                  color: c.textPrimary,
                ),
              ),
              const SizedBox(height: 6),
              AppTextFormField(
                controller: _methodCtrl,
                textInputAction: TextInputAction.done,
                hintText: english
                    ? 'e.g. using Laravel, Docker, and Redis caching'
                    : 'مثال: باستخدام Laravel و Docker وتقنيات التخزين المؤقت Redis',
              ),
              const SizedBox(height: 16),

              // ── Live Preview Box ──────────────────────────────────────────
              if (_formattedSentence.isNotEmpty) ...[
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: c.primaryLight,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: c.primary.withValues(alpha: .3)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Icon(Icons.visibility_outlined,
                              size: 16, color: c.primaryDark),
                          const SizedBox(width: 6),
                          Text(
                            english
                                ? 'Live Achievement Preview:'
                                : 'معاينة الصياغة النهائية:',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                              color: c.primaryDark,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        widget.english
                            ? _formattedSentence
                            : BidiText.protectLatinTokens(_formattedSentence),
                        style: TextStyle(
                          fontSize: 13,
                          height: 1.45,
                          fontWeight: FontWeight.w600,
                          color: c.textPrimary,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
              ],

              // ── Action Buttons ───────────────────────────────────────────
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        side: BorderSide(color: c.border),
                      ),
                      onPressed: () => Navigator.of(context).pop(),
                      child: Text(english ? 'Cancel' : 'إلغاء'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    flex: 2,
                    child: FilledButton.icon(
                      style: FilledButton.styleFrom(
                        backgroundColor: c.primary,
                        foregroundColor: c.onPrimary,
                        padding: const EdgeInsets.symmetric(vertical: 12),
                      ),
                      onPressed: canInsert ? _insert : null,
                      icon: const Icon(Icons.add_task_rounded, size: 18),
                      label: Text(
                        english ? 'Insert into Experience' : 'إدراج في الخبرة',
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
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

class _PresetChip extends StatelessWidget {
  final String label;
  final VoidCallback onTap;

  const _PresetChip({required this.label, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: c.surfaceLow,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: c.border),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.flash_on_rounded, size: 14, color: c.primary),
            const SizedBox(width: 4),
            Text(
              label,
              style: TextStyle(
                fontSize: 11.5,
                fontWeight: FontWeight.w600,
                color: c.textPrimary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
