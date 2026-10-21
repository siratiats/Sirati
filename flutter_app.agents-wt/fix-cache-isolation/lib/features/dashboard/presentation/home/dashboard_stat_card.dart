import 'package:flutter/material.dart';
import 'package:sirati/core/utils/app_format.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/motion.dart';

/// Label, large count, and icon for one dashboard statistic.
class DashboardStatCard extends StatelessWidget {
  const DashboardStatCard({
    super.key,
    required this.label,
    required this.count,
    required this.icon,
  });

  final String label;
  final int? count;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    // Design hierarchy: label (top) → large number → icon at start corner.
    // MergeSemantics → TalkBack: "My CVs, 12".
    return MergeSemantics(
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 120),
        child: Container(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
          decoration: BoxDecoration(
            color: context.sirati.surface,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: context.sirati.border),
            boxShadow: context.sirati.softShadow,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                label,
                textAlign: TextAlign.start,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: AppTextStyles.labelMd(context.sirati),
              ),
              const SizedBox(height: 6),
              CountUpValue(count: count),
              const SizedBox(height: 12),
              ExcludeSemantics(
                child: Container(
                  width: 30,
                  height: 30,
                  decoration: BoxDecoration(
                    color: context.sirati.primaryLight,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(icon, size: 16, color: context.sirati.primary),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Counts 0 → [count] once on first mount; later rebuilds show the final
/// value. Renders nothing when the count is zero or absent (SIRATI-63).
class CountUpValue extends StatefulWidget {
  const CountUpValue({super.key, required this.count});

  final int? count;

  @override
  State<CountUpValue> createState() => _CountUpValueState();
}

class _CountUpValueState extends State<CountUpValue> {
  /// After the first count-up finishes, stay static across rebuilds.
  bool _hasPlayed = false;

  /// Stable tween so parent rebuilds don't restart the animation.
  IntTween? _tween;

  @override
  void didUpdateWidget(covariant CountUpValue oldWidget) {
    super.didUpdateWidget(oldWidget);
    // Cached value replaced by fresh data mid-animation: retarget from where
    // the old tween was heading rather than finishing on a stale number.
    if (widget.count != oldWidget.count && !_hasPlayed) {
      _tween = null;
    }
  }

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    final count = widget.count;
    if (count == null || count <= 0) return const SizedBox.shrink();

    final style = AppTextStyles.displayStat(context.sirati);
    Text label(int n) => Text(
          AppFormat.digits(n.toString(), english: english),
          textAlign: TextAlign.start,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: style,
        );

    if (MotionSettings.reduce(context) || _hasPlayed) return label(count);

    final tween = _tween ??= IntTween(begin: 0, end: count);
    return TweenAnimationBuilder<int>(
      tween: tween,
      duration: const Duration(milliseconds: 600),
      curve: MotionCurves.enter,
      onEnd: () {
        if (mounted) setState(() => _hasPlayed = true);
      },
      builder: (context, value, _) => label(value),
    );
  }
}
