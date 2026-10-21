import 'package:flutter/material.dart';
import 'package:sirati/shared/theme/app_theme.dart';

/// Second tab: strengths, numbered quick wins, and the upgrade CTA.
class RecommendationsTab extends StatelessWidget {
  const RecommendationsTab({
    super.key,
    required this.strengths,
    required this.quickWins,
    required this.english,
  });

  final List<String> strengths;
  final List<String> quickWins;
  final bool english;

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 32),
      children: [
        _SectionHeader(
          icon: Icons.thumb_up_alt_rounded,
          iconColor: c.tealDark,
          iconBg: c.tealLight,
          label: english ? 'Strengths' : 'نقاط القوة',
        ),
        const SizedBox(height: 12),
        _ItemList(
          items: strengths,
          emptyText: english
              ? 'No strengths are available yet.'
              : 'لا توجد نقاط قوة متاحة حالياً',
          markerBuilder: (context, _) => const _CheckMarker(),
        ),
        const SizedBox(height: 24),
        _SectionHeader(
          icon: Icons.bolt_rounded,
          iconColor: c.amber,
          iconBg: c.amberLight,
          label: english ? 'Quick Wins' : 'تحسينات سريعة',
        ),
        const SizedBox(height: 12),
        _ItemList(
          items: quickWins,
          emptyText: english
              ? 'No quick wins are available yet.'
              : 'لا توجد تحسينات سريعة متاحة حالياً',
          markerBuilder: (context, i) => _NumberMarker(number: i + 1),
        ),
        const SizedBox(height: 28),
        _UpgradeCta(english: english),
      ],
    );
  }
}

class _ItemList extends StatelessWidget {
  const _ItemList({
    required this.items,
    required this.emptyText,
    required this.markerBuilder,
  });

  final List<String> items;
  final String emptyText;
  final Widget Function(BuildContext context, int index) markerBuilder;

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    return AppCard(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: items.isEmpty
          ? Padding(
              padding: const EdgeInsets.symmetric(vertical: 14),
              child: Text(
                emptyText,
                style: TextStyle(fontSize: 13, color: c.textSecondary),
              ),
            )
          : Column(
              children: [
                for (var i = 0; i < items.length; i++) ...[
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 13),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        markerBuilder(context, i),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            items[i],
                            style: TextStyle(
                              fontSize: 13,
                              color: c.textPrimary,
                              height: 1.55,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (i < items.length - 1) const Divider(height: 1),
                ],
              ],
            ),
    );
  }
}

class _CheckMarker extends StatelessWidget {
  const _CheckMarker();

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    return Container(
      margin: const EdgeInsets.only(top: 2),
      width: 20,
      height: 20,
      decoration: BoxDecoration(color: c.tealLight, shape: BoxShape.circle),
      child: Icon(Icons.check_rounded, size: 13, color: c.tealDark),
    );
  }
}

class _NumberMarker extends StatelessWidget {
  const _NumberMarker({required this.number});

  final int number;

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    return Container(
      margin: const EdgeInsets.only(top: 1),
      width: 22,
      height: 22,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: c.amberLight,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        '$number',
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w800,
          // onWarningContainer, not amber: amber on amberLight is ~3:1 and
          // fails AA for this 11px numeral.
          color: c.onWarningContainer,
        ),
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({
    required this.icon,
    required this.iconColor,
    required this.iconBg,
    required this.label,
  });

  final IconData icon;
  final Color iconColor;
  final Color iconBg;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          padding: const EdgeInsets.all(7),
          decoration: BoxDecoration(
            color: iconBg,
            borderRadius: BorderRadius.circular(9),
          ),
          child: Icon(icon, size: 16, color: iconColor),
        ),
        const SizedBox(width: 10),
        Text(
          label,
          style: TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w700,
            color: context.sirati.textPrimary,
          ),
        ),
      ],
    );
  }
}

class _UpgradeCta extends StatelessWidget {
  const _UpgradeCta({required this.english});

  final bool english;

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: c.primaryLight,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: c.primaryMid),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: c.primary.withValues(alpha: .10),
              borderRadius: BorderRadius.circular(10),
            ),
            child:
                Icon(Icons.auto_awesome_rounded, size: 20, color: c.primaryInk),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  english ? 'Ready to upgrade your CV?' : 'جاهز لترقية سيرتك؟',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    // primaryDark on primaryLight is ~3.7:1 in dark mode.
                    color: c.primaryInk,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  english
                      ? 'We will generate an improved CV from this analysis.'
                      : 'سنولّد لك سيرة ذاتية محسّنة تلقائياً بناءً على نتائج هذا التحليل',
                  style: TextStyle(
                    fontSize: 12,
                    // primaryInk, not primary: primary is a fill colour and
                    // sits near 2.6:1 on primaryLight (fails AA for text).
                    color: c.primaryInk,
                    height: 1.5,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
