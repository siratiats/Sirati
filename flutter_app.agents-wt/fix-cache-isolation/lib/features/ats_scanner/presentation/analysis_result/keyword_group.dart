import 'package:flutter/material.dart';
import 'package:sirati/shared/theme/app_theme.dart';

/// Wrap of keyword chips, capped at [initialVisibleCount] with a
/// show-more / show-less toggle. Missing keywords open an advice sheet.
class KeywordGroup extends StatefulWidget {
  const KeywordGroup({
    super.key,
    required this.keywords,
    required this.found,
    required this.english,
  });

  static const initialVisibleCount = 18;

  final List<String> keywords;
  final bool found;
  final bool english;

  @override
  State<KeywordGroup> createState() => _KeywordGroupState();
}

class _KeywordGroupState extends State<KeywordGroup> {
  bool _expanded = false;

  @override
  Widget build(BuildContext context) {
    if (widget.keywords.isEmpty) {
      return Text(
        widget.english ? 'No keywords yet.' : 'لا توجد كلمات مفتاحية حالياً',
        style: TextStyle(fontSize: 12, color: context.sirati.textSecondary),
      );
    }

    final overflowCount =
        widget.keywords.length - KeywordGroup.initialVisibleCount;
    final visibleKeywords = _expanded || overflowCount <= 0
        ? widget.keywords
        : widget.keywords.take(KeywordGroup.initialVisibleCount);

    return Wrap(
      spacing: 6,
      runSpacing: 6,
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        for (final keyword in visibleKeywords)
          _KeywordChip(
            label: keyword,
            found: widget.found,
            onTap: widget.found
                ? null
                : () => _showKeywordAdvice(context, keyword, widget.english),
          ),
        if (overflowCount > 0)
          _KeywordOverflowButton(
            expanded: _expanded,
            remainingCount: overflowCount,
            english: widget.english,
            onPressed: () => setState(() => _expanded = !_expanded),
          ),
      ],
    );
  }
}

class _KeywordChip extends StatelessWidget {
  const _KeywordChip({
    required this.label,
    required this.found,
    this.onTap,
  });

  final String label;
  final bool found;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    final chip = Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        // Gated container pairs (missing on red was ~4.2:1).
        color: found ? c.successLight : c.errorLight,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: found
              ? c.tealDark.withValues(alpha: .18)
              : c.red.withValues(alpha: .18),
        ),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: found ? c.onSuccessContainer : c.onErrorContainer,
        ),
      ),
    );

    if (onTap == null) return chip;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: chip,
    );
  }
}

class _KeywordOverflowButton extends StatelessWidget {
  const _KeywordOverflowButton({
    required this.expanded,
    required this.remainingCount,
    required this.english,
    required this.onPressed,
  });

  final bool expanded;
  final int remainingCount;
  final bool english;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return TextButton(
      style: TextButton.styleFrom(
        minimumSize: const Size(48, 36),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      ),
      onPressed: onPressed,
      child: Text(
        expanded
            ? (english ? 'Show less' : 'عرض أقل')
            : (english ? '+$remainingCount more' : '+$remainingCount أخرى'),
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w700,
          color: context.sirati.primaryInk,
        ),
      ),
    );
  }
}

void _showKeywordAdvice(BuildContext context, String keyword, bool english) {
  showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    backgroundColor: context.sirati.surface,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
    ),
    builder: (context) {
      final c = context.sirati;
      return SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: c.primaryLight,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(
                      Icons.lightbulb_outline_rounded,
                      color: c.primary,
                      size: 20,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      english
                          ? 'ATS Keyword: $keyword'
                          : 'كلمة مفتاحية: $keyword',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: c.textPrimary,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              Text(
                english
                    ? 'Applicant Tracking Systems (ATS) scan for "$keyword" in your work experience and skills. Adding it in context with measurable results will increase your resume ranking.'
                    : 'تبحث أنظمة فرز السير الذاتية (ATS) عن كلمة "$keyword" في قسم الخبرات والمهارات. إضافتها ضمن سياق إنجازاتك المقاسة يرفع نسبة تطابق سيرتك بشكل ملحوظ.',
                style: TextStyle(
                  fontSize: 13.5,
                  height: 1.55,
                  color: c.textSecondary,
                ),
              ),
              const SizedBox(height: 18),
              Align(
                alignment: AlignmentDirectional.centerEnd,
                child: TextButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: Text(english ? 'Got it' : 'فهمت ذلك'),
                ),
              ),
            ],
          ),
        ),
      );
    },
  );
}
