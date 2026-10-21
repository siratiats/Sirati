import 'package:flutter/material.dart';
import 'package:sirati/shared/theme/app_theme.dart';

/// Tells the user whether the score was built against the job description
/// they pasted, or fell back to the generic category keyword list (SIRATI-90).
class KeywordSourceBadge extends StatelessWidget {
  const KeywordSourceBadge({
    super.key,
    required this.keywordSource,
    required this.english,
  });

  final String keywordSource;
  final bool english;

  @override
  Widget build(BuildContext context) {
    final fromJd = keywordSource == 'job_description';
    final bg =
        fromJd ? context.sirati.primaryLight : context.sirati.warningLight;
    // Gated container pairs: primaryInk / primaryLight and
    // onWarningContainer / warningLight (warning text was ~2.7:1).
    final fg =
        fromJd ? context.sirati.primaryInk : context.sirati.onWarningContainer;
    final icon =
        fromJd ? Icons.verified_rounded : Icons.lightbulb_outline_rounded;
    final label = fromJd
        ? (english
            ? 'Scored against the job description you pasted'
            : 'التقييم مبني على وصف الوظيفة الذي أدخلته')
        : (english
            ? 'Generic list — paste a job description next time for a sharper score'
            : 'قائمة عامة — أضف وصف الوظيفة في المرة القادمة لتقييم أدق');

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 14, color: fg),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              label,
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: fg,
                height: 1.4,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
