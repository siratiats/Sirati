import 'package:flutter/material.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/motion.dart';

class PresetChips extends StatelessWidget {
  final bool english;
  final String selectedPreset;
  final ValueChanged<String> onSelected;

  const PresetChips({
    super.key,
    required this.english,
    required this.selectedPreset,
    required this.onSelected,
  });

  @override
  Widget build(BuildContext context) {
    final presets = [
      _PresetOption('all', english ? 'All' : 'الكل', Icons.dashboard_outlined),
      _PresetOption('matched', english ? 'Best Match' : 'مناسبة لي 🎯',
          Icons.stars_rounded),
      _PresetOption('gov', english ? 'Government' : 'جهات حكومية 🏛️',
          Icons.account_balance_outlined),
      _PresetOption('enterprise', english ? 'Enterprise' : 'شركات كبرى 🏢',
          Icons.business_outlined),
      _PresetOption('tamheer', english ? 'Tamheer' : 'تمهير وتدريب 🇸🇦',
          Icons.school_outlined),
      _PresetOption(
          'remote', english ? 'Remote' : 'عن بعد 🌐', Icons.public_rounded),
    ];

    return Material(
      type: MaterialType.transparency,
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            for (final preset in presets)
              Padding(
                padding: const EdgeInsetsDirectional.only(end: 6),
                child: PressScale(
                  pressedScale: 0.96,
                  child: ChoiceChip(
                    label: Text(preset.label),
                    selected: selectedPreset == preset.key,
                    onSelected: (_) => onSelected(preset.key),
                    showCheckmark: false,
                    labelStyle: TextStyle(
                      fontSize: 12,
                      fontWeight: selectedPreset == preset.key
                          ? FontWeight.w700
                          : FontWeight.w500,
                      color: selectedPreset == preset.key
                          ? context.sirati.onPrimary
                          : context.sirati.textSecondary,
                    ),
                    selectedColor: context.sirati.primary,
                    backgroundColor: context.sirati.surface,
                    side: BorderSide(
                      color: selectedPreset == preset.key
                          ? context.sirati.primary
                          : context.sirati.border,
                      width: selectedPreset == preset.key ? 1.2 : 1,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                    materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    visualDensity: VisualDensity.compact,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _PresetOption {
  final String key;
  final String label;
  final IconData icon;

  const _PresetOption(this.key, this.label, this.icon);
}

class JobSearchBar extends StatelessWidget {
  final bool english;
  final TextEditingController controller;
  final ValueChanged<String> onChanged;

  const JobSearchBar({
    super.key,
    required this.english,
    required this.controller,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      type: MaterialType.transparency,
      child: TextField(
        controller: controller,
        onChanged: onChanged,
        textAlign: TextAlign.start,
        decoration: InputDecoration(
          hintText: english
              ? 'Search for a job or company...'
              : 'ابحث عن وظيفة أو شركة...',
          // Keep the search affordance on the physical leading edge of the field
          // (left) in both locales — matches the current product screenshots.
          prefixIcon:
              english ? const Icon(Icons.search_rounded, size: 18) : null,
          suffixIcon:
              english ? null : const Icon(Icons.search_rounded, size: 18),
          filled: true,
          fillColor: context.sirati.surface,
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide(color: context.sirati.border),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide(color: context.sirati.border),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide(color: context.sirati.primary, width: 1.4),
          ),
        ),
        style: TextStyle(
            fontSize: 13.5, height: 1.3, color: context.sirati.textPrimary),
      ),
    );
  }
}

class CategoryChips extends StatelessWidget {
  final bool english;
  final String selectedCategory;
  final ValueChanged<String> onSelected;

  const CategoryChips({
    super.key,
    required this.english,
    required this.selectedCategory,
    required this.onSelected,
  });

  @override
  Widget build(BuildContext context) {
    final options = [
      _CategoryOption('all', english ? 'All' : 'الكل'),
      _CategoryOption('tech', english ? 'Tech' : 'تقنية'),
      _CategoryOption('finance', english ? 'Finance' : 'تمويل'),
      _CategoryOption('health', english ? 'Health' : 'صحة'),
    ];

    return Material(
      type: MaterialType.transparency,
      child: Wrap(
        spacing: AppSpacing.xs,
        runSpacing: AppSpacing.xs,
        children: [
          for (final option in options)
            PressScale(
              pressedScale: .98,
              child: ChoiceChip(
                label: Text(option.label),
                selected: selectedCategory == option.key,
                onSelected: (_) => onSelected(option.key),
                showCheckmark: false,
                labelStyle: TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w700,
                  color: selectedCategory == option.key
                      ? context.sirati.onPrimary
                      : context.sirati.textSecondary,
                ),
                selectedColor: context.sirati.primary,
                backgroundColor: context.sirati.primaryLight,
                side: BorderSide.none,
                shape: const StadiumBorder(),
                materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                visualDensity: VisualDensity.compact,
              ),
            ),
        ],
      ),
    );
  }
}

class _CategoryOption {
  final String key;
  final String label;

  const _CategoryOption(this.key, this.label);
}

class CityChips extends StatelessWidget {
  final bool english;
  final String selectedCity;
  final ValueChanged<String> onSelected;

  const CityChips({
    super.key,
    required this.english,
    required this.selectedCity,
    required this.onSelected,
  });

  @override
  Widget build(BuildContext context) {
    final options = [
      _CategoryOption('all', english ? 'All Locations' : 'كل المناطق'),
      _CategoryOption('riyadh', english ? 'Riyadh' : 'الرياض'),
      _CategoryOption('jeddah', english ? 'Jeddah' : 'جدة'),
      _CategoryOption('dammam', english ? 'Dammam' : 'الدمام'),
      _CategoryOption('khobar', english ? 'Khobar' : 'الخبر'),
      _CategoryOption('mecca', english ? 'Mecca' : 'مكة'),
      _CategoryOption('medina', english ? 'Medina' : 'المدينة'),
      _CategoryOption('remote', english ? 'Remote' : 'عن بعد 🌐'),
    ];

    return Material(
      type: MaterialType.transparency,
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            for (final option in options)
              Padding(
                padding: const EdgeInsetsDirectional.only(end: 6),
                child: PressScale(
                  pressedScale: .98,
                  child: FilterChip(
                    label: Text(option.label),
                    selected: selectedCity == option.key,
                    onSelected: (_) => onSelected(option.key),
                    showCheckmark: false,
                    avatar: option.key == 'remote'
                        ? const Icon(Icons.public_rounded, size: 14)
                        : const Icon(Icons.location_on_outlined, size: 14),
                    labelStyle: TextStyle(
                      fontSize: 11.5,
                      fontWeight: FontWeight.w600,
                      color: selectedCity == option.key
                          ? context.sirati.primaryDark
                          : context.sirati.textSecondary,
                    ),
                    selectedColor: context.sirati.primaryLight,
                    backgroundColor: context.sirati.surface,
                    side: BorderSide(
                      color: selectedCity == option.key
                          ? context.sirati.primary
                          : context.sirati.border,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                    materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    visualDensity: VisualDensity.compact,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
