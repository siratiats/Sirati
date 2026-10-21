import 'package:flutter/material.dart';
import 'package:sirati/core/utils/locale_format.dart';
import 'package:sirati/shared/theme/app_theme.dart';

/// "Continue where you left off?" offer shown in create mode when a local
/// draft with real content exists.
class DraftRestoreBanner extends StatelessWidget {
  const DraftRestoreBanner({
    super.key,
    required this.english,
    required this.savedAt,
    required this.onStartFresh,
    required this.onRestore,
  });

  final bool english;
  final DateTime? savedAt;
  final VoidCallback onStartFresh;
  final VoidCallback onRestore;

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    final at = savedAt;
    final relative = at != null
        ? LocaleFormat.relativeSavedAt(at, english: english)
        : (english ? 'Draft available' : 'مسودة متاحة');

    return Material(
      color: c.infoLight,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
        decoration: BoxDecoration(
          border: Border(
            bottom: BorderSide(color: c.info.withValues(alpha: .28)),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.history_rounded, size: 20, color: c.info),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        english
                            ? 'Continue where you left off?'
                            : 'المتابعة من حيث توقفت؟',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: c.textPrimary,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        relative,
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w500,
                          color: c.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: onStartFresh,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: c.textSecondary,
                      side: BorderSide(color: c.border),
                      padding: const EdgeInsets.symmetric(vertical: 10),
                    ),
                    child: Text(english ? 'Start fresh' : 'البدء من جديد'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: FilledButton(
                    onPressed: onRestore,
                    style: FilledButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 10),
                    ),
                    child: Text(english ? 'Restore' : 'استعادة'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
