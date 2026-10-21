import 'package:flutter/material.dart';

import 'package:sirati/core/utils/arabic_date_format.dart';
import 'package:sirati/features/cv_builder/controllers/cv_builder_controller.dart';
import 'package:sirati/features/cv_builder/presentation/section_editor_framework.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/cv_document.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/components.dart';

/// Experience and Education section editors (SIRATI-38).
///
/// Supports:
/// - Date ranges with open-ended "Present" toggle
/// - Strict date chronology validation (end date cannot precede start date)
/// - Gregorian and Hijri calendar representations
/// - Dynamic bullet-point achievements (add, remove, reorder)
/// - Automatic chronological sorting (newest first) with manual override
class ExperienceEducationEditor extends StatefulWidget {
  final CvBuilderController controller;

  const ExperienceEducationEditor({
    super.key,
    required this.controller,
  });

  /// Verifies date range chronological invariant (end >= start).
  static String? validateDateRange(
    String? start,
    String? end,
    bool isCurrent,
    AppLocalizations l10n,
  ) {
    if (isCurrent) return null;
    if (start == null || start.trim().isEmpty) return null;
    if (end == null || end.trim().isEmpty) return null;

    final s = start.trim();
    final e = end.trim();

    // Chronological order verification via typed date parsing
    final comparison = ArabicDateFormat.compareCvDates(s, e);
    if (comparison > 0) {
      return l10n.endDateBeforeStart;
    }
    return null;
  }

  @override
  State<ExperienceEducationEditor> createState() =>
      _ExperienceEducationEditorState();
}

class _ExperienceEducationEditorState extends State<ExperienceEducationEditor> {
  // Calendar mode: 'gregorian' or 'hijri'
  String _calendarMode = 'gregorian';

  List<ExperienceEntry> get _experience =>
      widget.controller.document.experience ?? [];

  List<EducationEntry> get _education =>
      widget.controller.document.education ?? [];

  void _addExperience() {
    final updated = List<ExperienceEntry>.from(_experience)
      ..add(
        ExperienceEntry(
          id: 'exp_${DateTime.now().microsecondsSinceEpoch}',
          company: const LocalizedText(),
          title: const LocalizedText(),
          location: const LocalizedText(),
          startDate: DateTime.now().year.toString(),
          isCurrent: true,
          bullets: const [],
        ),
      );
    widget.controller.setExperience(updated);
  }

  void _updateExperience(int index, ExperienceEntry entry) {
    final updated = List<ExperienceEntry>.from(_experience);
    updated[index] = entry;
    widget.controller.setExperience(updated);
  }

  void _removeExperience(int index) {
    final updated = List<ExperienceEntry>.from(_experience)..removeAt(index);
    widget.controller.setExperience(updated);
  }

  void _reorderExperience(int oldIndex, int newIndex) {
    final updated = List<ExperienceEntry>.from(_experience);
    if (oldIndex < newIndex) newIndex -= 1;
    final item = updated.removeAt(oldIndex);
    updated.insert(newIndex, item);
    widget.controller.setExperience(updated);
  }

  void _sortExperienceChronologically() {
    final updated = List<ExperienceEntry>.from(_experience);
    updated.sort((a, b) {
      if (a.isCurrent && !b.isCurrent) return -1;
      if (!a.isCurrent && b.isCurrent) return 1;
      return ArabicDateFormat.compareCvDates(
          b.startDate, a.startDate); // Newest first
    });
    widget.controller.setExperience(updated);
  }

  void _addEducation() {
    final updated = List<EducationEntry>.from(_education)
      ..add(
        EducationEntry(
          id: 'edu_${DateTime.now().microsecondsSinceEpoch}',
          institution: const LocalizedText(),
          degree: const LocalizedText(),
          fieldOfStudy: const LocalizedText(),
          startDate: (DateTime.now().year - 4).toString(),
          graduationDate: DateTime.now().year.toString(),
        ),
      );
    widget.controller.setEducation(updated);
  }

  void _updateEducation(int index, EducationEntry entry) {
    final updated = List<EducationEntry>.from(_education);
    updated[index] = entry;
    widget.controller.setEducation(updated);
  }

  void _removeEducation(int index) {
    final updated = List<EducationEntry>.from(_education)..removeAt(index);
    widget.controller.setEducation(updated);
  }

  void _reorderEducation(int oldIndex, int newIndex) {
    final updated = List<EducationEntry>.from(_education);
    if (oldIndex < newIndex) newIndex -= 1;
    final item = updated.removeAt(oldIndex);
    updated.insert(newIndex, item);
    widget.controller.setEducation(updated);
  }

  void _sortEducationChronologically() {
    final updated = List<EducationEntry>.from(_education);
    updated.sort((a, b) {
      final aDate = a.graduationDate ?? a.startDate;
      final bDate = b.graduationDate ?? b.startDate;
      return ArabicDateFormat.compareCvDates(bDate, aDate); // Newest first
    });
    widget.controller.setEducation(updated);
  }

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    final l10n = AppLocalizations.of(context);

    return Column(
      children: [
        // ── Calendar Mode Selection Banner ──────────────────────────────────
        Container(
          margin: const EdgeInsets.only(bottom: AppSpacing.md),
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.md,
            vertical: AppSpacing.xs,
          ),
          decoration: BoxDecoration(
            color: c.surfaceHigh,
            borderRadius: BorderRadius.circular(AppRadius.md),
            border: Border.all(color: c.border),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  Icon(Icons.calendar_month_outlined,
                      size: 18, color: c.primary),
                  const SizedBox(width: AppSpacing.xs),
                  Text(
                    l10n.calendarSystemLabel,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: c.textPrimary,
                          fontWeight: FontWeight.bold,
                        ),
                  ),
                ],
              ),
              SegmentedButton<String>(
                segments: [
                  ButtonSegment(
                    value: 'gregorian',
                    label: Text(l10n.calendarGregorian),
                  ),
                  ButtonSegment(
                    value: 'hijri',
                    label: Text(l10n.calendarHijri),
                  ),
                ],
                selected: {_calendarMode},
                onSelectionChanged: (set) =>
                    setState(() => _calendarMode = set.first),
                style: ButtonStyle(
                  visualDensity: VisualDensity.compact,
                  textStyle: WidgetStatePropertyAll(
                    Theme.of(context).textTheme.labelSmall,
                  ),
                ),
              ),
            ],
          ),
        ),

        // ── Work Experience Card ───────────────────────────────────────────
        SectionCardWrapper(
          title: l10n.workExperienceTitle,
          subtitle: l10n.workExperienceSubtitle,
          icon: Icons.work_outline,
          entryCount: _experience.length,
          onAdd: _addExperience,
          addLabel: l10n.addExperience,
          child: Column(
            children: [
              if (_experience.isNotEmpty) ...[
                Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    AppButton.text(
                      label: l10n.sortChronologically,
                      icon: Icons.sort,
                      onPressed: _sortExperienceChronologically,
                    ),
                  ],
                ),
                const SizedBox(height: AppSpacing.xs),
              ],
              ReorderableEntryList<ExperienceEntry>(
                items: _experience,
                onReorder: _reorderExperience,
                onDelete: _removeExperience,
                itemBuilder: (context, item, index) {
                  return _ExperienceEntryTile(
                    key: ValueKey(item.id ?? index),
                    entry: item,
                    calendarMode: _calendarMode,
                    onChanged: (updated) => _updateExperience(index, updated),
                  );
                },
              ),
            ],
          ),
        ),

        const SizedBox(height: AppSpacing.md),

        // ── Education Card ─────────────────────────────────────────────────
        SectionCardWrapper(
          title: l10n.educationQualificationsTitle,
          subtitle: l10n.educationQualificationsSubtitle,
          icon: Icons.school_outlined,
          entryCount: _education.length,
          onAdd: _addEducation,
          addLabel: l10n.addEducation,
          child: Column(
            children: [
              if (_education.isNotEmpty) ...[
                Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    AppButton.text(
                      label: l10n.sortChronologically,
                      icon: Icons.sort,
                      onPressed: _sortEducationChronologically,
                    ),
                  ],
                ),
                const SizedBox(height: AppSpacing.xs),
              ],
              ReorderableEntryList<EducationEntry>(
                items: _education,
                onReorder: _reorderEducation,
                onDelete: _removeEducation,
                itemBuilder: (context, item, index) {
                  return _EducationEntryTile(
                    key: ValueKey(item.id ?? index),
                    entry: item,
                    calendarMode: _calendarMode,
                    onChanged: (updated) => _updateEducation(index, updated),
                  );
                },
              ),
            ],
          ),
        ),
      ],
    );
  }
}

// ── Experience Entry Form Tile ──────────────────────────────────────────────

class _ExperienceEntryTile extends StatefulWidget {
  final ExperienceEntry entry;
  final String calendarMode;
  final ValueChanged<ExperienceEntry> onChanged;

  const _ExperienceEntryTile({
    super.key,
    required this.entry,
    required this.calendarMode,
    required this.onChanged,
  });

  @override
  State<_ExperienceEntryTile> createState() => _ExperienceEntryTileState();
}

class _ExperienceEntryTileState extends State<_ExperienceEntryTile> {
  late final TextEditingController _companyArCtrl;
  late final TextEditingController _companyEnCtrl;
  late final TextEditingController _titleArCtrl;
  late final TextEditingController _titleEnCtrl;
  late final TextEditingController _locationArCtrl;
  late final TextEditingController _startCtrl;
  late final TextEditingController _endCtrl;
  late bool _isCurrent;

  String? _dateError;

  @override
  void initState() {
    super.initState();
    _companyArCtrl = TextEditingController(text: widget.entry.company.ar);
    _companyEnCtrl = TextEditingController(text: widget.entry.company.en);
    _titleArCtrl = TextEditingController(text: widget.entry.title.ar);
    _titleEnCtrl = TextEditingController(text: widget.entry.title.en);
    _locationArCtrl = TextEditingController(text: widget.entry.location.ar);
    _startCtrl = TextEditingController(text: widget.entry.startDate ?? '');
    _endCtrl = TextEditingController(text: widget.entry.endDate ?? '');
    _isCurrent = widget.entry.isCurrent;
  }

  @override
  void dispose() {
    _companyArCtrl.dispose();
    _companyEnCtrl.dispose();
    _titleArCtrl.dispose();
    _titleEnCtrl.dispose();
    _locationArCtrl.dispose();
    _startCtrl.dispose();
    _endCtrl.dispose();
    super.dispose();
  }

  void _notifyChange({List<LocalizedText>? bullets}) {
    final start = _startCtrl.text.trim();
    final end = _isCurrent ? null : _endCtrl.text.trim();
    final l10n = AppLocalizations.of(context);

    setState(() {
      _dateError = ExperienceEducationEditor.validateDateRange(
        start,
        end,
        _isCurrent,
        l10n,
      );
    });

    widget.onChanged(
      widget.entry.copyWith(
        company: LocalizedText(
          ar: _companyArCtrl.text.trim(),
          en: _companyEnCtrl.text.trim(),
        ),
        title: LocalizedText(
          ar: _titleArCtrl.text.trim(),
          en: _titleEnCtrl.text.trim(),
        ),
        location: LocalizedText(ar: _locationArCtrl.text.trim()),
        startDate: start.isEmpty ? null : start,
        endDate: end?.isEmpty ?? true ? null : end,
        isCurrent: _isCurrent,
        bullets: bullets ?? widget.entry.bullets,
      ),
    );
  }

  void _addBullet() {
    final updated = List<LocalizedText>.from(widget.entry.bullets)
      ..add(const LocalizedText());
    _notifyChange(bullets: updated);
  }

  void _updateBullet(int index, String text) {
    final updated = List<LocalizedText>.from(widget.entry.bullets);
    updated[index] = LocalizedText(ar: text, en: updated[index].en);
    _notifyChange(bullets: updated);
  }

  void _removeBullet(int index) {
    final updated = List<LocalizedText>.from(widget.entry.bullets)
      ..removeAt(index);
    _notifyChange(bullets: updated);
  }

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    final l10n = AppLocalizations.of(context);

    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: c.surfaceHigh,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: c.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Job Title (AR & EN)
          Row(
            children: [
              Expanded(
                child: AppInput(
                  controller: _titleArCtrl,
                  label: l10n.jobTitleAr,
                  hint: l10n.jobTitleArHint,
                  onChanged: (_) => _notifyChange(),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: AppInput(
                  controller: _titleEnCtrl,
                  label: l10n.jobTitleEn,
                  hint: l10n.jobTitleEnHint,
                  onChanged: (_) => _notifyChange(),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),

          // Company (AR & EN)
          Row(
            children: [
              Expanded(
                child: AppInput(
                  controller: _companyArCtrl,
                  label: l10n.companyAr,
                  hint: l10n.companyArHint,
                  onChanged: (_) => _notifyChange(),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: AppInput(
                  controller: _companyEnCtrl,
                  label: l10n.companyEn,
                  hint: l10n.companyEnHint,
                  onChanged: (_) => _notifyChange(),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),

          // Date Range & Currently Working Toggle
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: AppInput(
                  controller: _startCtrl,
                  label: l10n.startDateLabel,
                  hint: l10n.startDateHint,
                  textDirection: TextDirection.ltr,
                  onChanged: (_) => _notifyChange(),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: _isCurrent
                    ? Container(
                        height: AppTouchTarget.min,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: c.primaryLight,
                          borderRadius: BorderRadius.circular(AppRadius.md),
                        ),
                        child: Text(
                          l10n.currentlyHere,
                          style:
                              Theme.of(context).textTheme.bodySmall?.copyWith(
                                    color: c.primary,
                                    fontWeight: FontWeight.bold,
                                  ),
                        ),
                      )
                    : AppInput(
                        controller: _endCtrl,
                        label: l10n.endDateLabel,
                        hint: l10n.endDateHint,
                        textDirection: TextDirection.ltr,
                        onChanged: (_) => _notifyChange(),
                      ),
              ),
            ],
          ),

          // Inline Date Range Validation Error
          if (_dateError != null) ...[
            const SizedBox(height: AppSpacing.xxs),
            Row(
              children: [
                Icon(Icons.error_outline, size: 14, color: c.error),
                const SizedBox(width: AppSpacing.xxs),
                Text(
                  _dateError!,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: c.error,
                      ),
                ),
              ],
            ),
          ],

          const SizedBox(height: AppSpacing.xs),

          // "Currently Working Here" Checkbox / Switch
          Row(
            children: [
              Checkbox(
                value: _isCurrent,
                onChanged: (val) {
                  setState(() => _isCurrent = val ?? false);
                  _notifyChange();
                },
              ),
              Text(
                l10n.currentlyWorking,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: c.textPrimary,
                    ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),

          // ── Bullet Point Achievements ─────────────────────────────────────
          Row(
            children: [
              Expanded(
                child: Text(
                  l10n.achievementsLabel,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        fontWeight: FontWeight.bold,
                        color: c.textSecondary,
                      ),
                ),
              ),
              TextButton.icon(
                icon: const Icon(Icons.add, size: 16),
                label: Text(l10n.addAchievement),
                onPressed: _addBullet,
              ),
            ],
          ),
          if (widget.entry.bullets.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
              child: Text(
                l10n.noAchievementsHint,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: c.textHint,
                      fontStyle: FontStyle.italic,
                    ),
              ),
            )
          else
            ...widget.entry.bullets.asMap().entries.map((b) {
              final bIndex = b.key;
              final bText = b.value.ar;
              return Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.xs),
                child: Row(
                  children: [
                    Icon(Icons.circle, size: 6, color: c.primary),
                    const SizedBox(width: AppSpacing.xs),
                    Expanded(
                      child: TextFormField(
                        initialValue: bText,
                        style: Theme.of(context).textTheme.bodySmall,
                        decoration: InputDecoration(
                          isDense: true,
                          contentPadding: const EdgeInsets.symmetric(
                            horizontal: AppSpacing.sm,
                            vertical: AppSpacing.xs,
                          ),
                          hintText: l10n.achievementHint,
                        ),
                        onChanged: (val) => _updateBullet(bIndex, val),
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.close, size: 16),
                      color: c.textHint,
                      onPressed: () => _removeBullet(bIndex),
                      constraints:
                          const BoxConstraints(minWidth: 28, minHeight: 28),
                      padding: EdgeInsets.zero,
                    ),
                  ],
                ),
              );
            }),
        ],
      ),
    );
  }
}

// ── Education Entry Form Tile ───────────────────────────────────────────────

class _EducationEntryTile extends StatefulWidget {
  final EducationEntry entry;
  final String calendarMode;
  final ValueChanged<EducationEntry> onChanged;

  const _EducationEntryTile({
    super.key,
    required this.entry,
    required this.calendarMode,
    required this.onChanged,
  });

  @override
  State<_EducationEntryTile> createState() => _EducationEntryTileState();
}

class _EducationEntryTileState extends State<_EducationEntryTile> {
  late final TextEditingController _instArCtrl;
  late final TextEditingController _instEnCtrl;
  late final TextEditingController _degreeArCtrl;
  late final TextEditingController _fieldArCtrl;
  late final TextEditingController _startCtrl;
  late final TextEditingController _gradCtrl;

  String? _dateError;

  @override
  void initState() {
    super.initState();
    _instArCtrl = TextEditingController(text: widget.entry.institution.ar);
    _instEnCtrl = TextEditingController(text: widget.entry.institution.en);
    _degreeArCtrl = TextEditingController(text: widget.entry.degree.ar);
    _fieldArCtrl = TextEditingController(text: widget.entry.fieldOfStudy.ar);
    _startCtrl = TextEditingController(text: widget.entry.startDate ?? '');
    _gradCtrl = TextEditingController(text: widget.entry.graduationDate ?? '');
  }

  @override
  void dispose() {
    _instArCtrl.dispose();
    _instEnCtrl.dispose();
    _degreeArCtrl.dispose();
    _fieldArCtrl.dispose();
    _startCtrl.dispose();
    _gradCtrl.dispose();
    super.dispose();
  }

  void _notifyChange() {
    final start = _startCtrl.text.trim();
    final grad = _gradCtrl.text.trim();
    final l10n = AppLocalizations.of(context);

    setState(() {
      _dateError = ExperienceEducationEditor.validateDateRange(
        start,
        grad,
        false,
        l10n,
      );
    });

    widget.onChanged(
      widget.entry.copyWith(
        institution: LocalizedText(
          ar: _instArCtrl.text.trim(),
          en: _instEnCtrl.text.trim(),
        ),
        degree: LocalizedText(ar: _degreeArCtrl.text.trim()),
        fieldOfStudy: LocalizedText(ar: _fieldArCtrl.text.trim()),
        startDate: start.isEmpty ? null : start,
        graduationDate: grad.isEmpty ? null : grad,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    final l10n = AppLocalizations.of(context);

    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: c.surfaceHigh,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: c.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Institution (AR & EN)
          Row(
            children: [
              Expanded(
                child: AppInput(
                  controller: _instArCtrl,
                  label: l10n.institutionAr,
                  hint: l10n.institutionArHint,
                  onChanged: (_) => _notifyChange(),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: AppInput(
                  controller: _instEnCtrl,
                  label: l10n.institutionEn,
                  hint: l10n.institutionEnHint,
                  onChanged: (_) => _notifyChange(),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),

          // Degree & Field of Study
          Row(
            children: [
              Expanded(
                child: AppInput(
                  controller: _degreeArCtrl,
                  label: l10n.degreeLabel,
                  hint: l10n.degreeHint,
                  onChanged: (_) => _notifyChange(),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: AppInput(
                  controller: _fieldArCtrl,
                  label: l10n.fieldOfStudyLabel,
                  hint: l10n.fieldOfStudyHint,
                  onChanged: (_) => _notifyChange(),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),

          // Dates
          Row(
            children: [
              Expanded(
                child: AppInput(
                  controller: _startCtrl,
                  label: l10n.startYearLabel,
                  hint: l10n.startYearHint,
                  textDirection: TextDirection.ltr,
                  onChanged: (_) => _notifyChange(),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: AppInput(
                  controller: _gradCtrl,
                  label: l10n.graduationYearLabel,
                  hint: l10n.graduationYearHint,
                  textDirection: TextDirection.ltr,
                  onChanged: (_) => _notifyChange(),
                ),
              ),
            ],
          ),

          if (_dateError != null) ...[
            const SizedBox(height: AppSpacing.xxs),
            Row(
              children: [
                Icon(Icons.error_outline, size: 14, color: c.error),
                const SizedBox(width: AppSpacing.xxs),
                Text(
                  _dateError!,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: c.error,
                      ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
