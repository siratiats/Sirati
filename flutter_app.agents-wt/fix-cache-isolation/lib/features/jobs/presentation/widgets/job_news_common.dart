import 'package:flutter/material.dart';
import 'package:sirati/shared/theme/app_theme.dart';

class MetaChip extends StatelessWidget {
  final String text;
  final bool highlight;

  const MetaChip({super.key, required this.text, this.highlight = false});

  @override
  Widget build(BuildContext context) {
    if (!highlight) {
      return Chip(label: Text(text));
    }
    return Chip(
      label: Text(
        text,
        style: TextStyle(
          color: context.sirati.primary,
          fontWeight: FontWeight.w700,
        ),
      ),
      backgroundColor: context.sirati.primaryLight,
      side: BorderSide(color: context.sirati.borderStrong),
    );
  }
}

class EmptyJobNews extends StatelessWidget {
  final bool english;

  const EmptyJobNews({super.key, required this.english});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        color: context.sirati.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: context.sirati.border),
      ),
      child: Text(
        english
            ? 'No job news is published yet.'
            : 'لا توجد أخبار وظائف منشورة حالياً.',
        textAlign: TextAlign.center,
        style: TextStyle(fontSize: 15, color: context.sirati.textSecondary),
      ),
    );
  }
}
