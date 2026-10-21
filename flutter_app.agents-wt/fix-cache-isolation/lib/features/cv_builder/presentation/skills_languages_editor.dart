import 'package:flutter/material.dart';

import 'package:sirati/features/cv_builder/controllers/cv_builder_controller.dart';
import 'package:sirati/features/cv_builder/data/skill_storage_keys.dart';
import 'package:sirati/features/cv_builder/presentation/section_editor_framework.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/cv_document.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/components.dart';

/// Editors for Skills, Languages, Certifications, and Projects (SIRATI-39).
///
/// Features:
/// - Discrete skill items with proficiency chips and category grouping
/// - Standard language proficiency scale (Native, Fluent, Professional, etc.)
/// - Certifications with issuer, issue date, and optional expiry
/// - Projects with title, role, URL validation, and descriptions
///
/// Skill/language dropdown *values* are [SkillStorageKeys] /
/// [LanguageLevelStorageKeys] and are written into the CV document. Labels
/// come from [AppLocalizations]. Do not replace the stored keys with
/// translated strings.
class SkillsLanguagesEditor extends StatefulWidget {
  final CvBuilderController controller;

  const SkillsLanguagesEditor({
    super.key,
    required this.controller,
  });

  @override
  State<SkillsLanguagesEditor> createState() => _SkillsLanguagesEditorState();
}

class _SkillsLanguagesEditorState extends State<SkillsLanguagesEditor> {
  List<Skill> get _skills => widget.controller.document.skills ?? [];
  List<LanguageSkill> get _languages =>
      widget.controller.document.languages ?? [];
  List<Certification> get _certifications =>
      widget.controller.document.certifications ?? [];
  List<Project> get _projects => widget.controller.document.projects ?? [];

  // ── Skills Mutation Helpers ───────────────────────────────────────────────

  void _addSkill(String name, String level, String category) {
    if (name.trim().isEmpty) return;
    final updated = List<Skill>.from(_skills)
      ..add(
        Skill(
          id: 'skill_${DateTime.now().microsecondsSinceEpoch}',
          name: LocalizedText(ar: name.trim()),
          level: LocalizedText(
            ar: level,
            en: SkillStorageKeys.englishFor(level),
          ),
          category: LocalizedText(
            ar: category,
            en: SkillStorageKeys.englishFor(category),
          ),
        ),
      );
    widget.controller.setSkills(updated);
  }

  void _removeSkill(int index) {
    final updated = List<Skill>.from(_skills)..removeAt(index);
    widget.controller.setSkills(updated);
  }

  // ── Language Mutation Helpers ─────────────────────────────────────────────

  void _addLanguage(String name, String level) {
    if (name.trim().isEmpty) return;
    final updated = List<LanguageSkill>.from(_languages)
      ..add(
        LanguageSkill(
          id: 'lang_${DateTime.now().microsecondsSinceEpoch}',
          name: LocalizedText(ar: name.trim()),
          level: LocalizedText(
            ar: level,
            en: LanguageLevelStorageKeys.englishFor(level),
          ),
        ),
      );
    widget.controller.setLanguages(updated);
  }

  void _removeLanguage(int index) {
    final updated = List<LanguageSkill>.from(_languages)..removeAt(index);
    widget.controller.setLanguages(updated);
  }

  // ── Certification Mutation Helpers ────────────────────────────────────────

  void _addCertification() {
    final updated = List<Certification>.from(_certifications)
      ..add(
        Certification(
          id: 'cert_${DateTime.now().microsecondsSinceEpoch}',
          name: const LocalizedText(),
          authority: const LocalizedText(),
          issueDate: DateTime.now().year.toString(),
        ),
      );
    widget.controller.setCertifications(updated);
  }

  void _updateCertification(int index, Certification cert) {
    final updated = List<Certification>.from(_certifications);
    updated[index] = cert;
    widget.controller.setCertifications(updated);
  }

  void _removeCertification(int index) {
    final updated = List<Certification>.from(_certifications)..removeAt(index);
    widget.controller.setCertifications(updated);
  }

  // ── Project Mutation Helpers ──────────────────────────────────────────────

  void _addProject() {
    final updated = List<Project>.from(_projects)
      ..add(
        Project(
          id: 'proj_${DateTime.now().microsecondsSinceEpoch}',
          title: const LocalizedText(),
          role: const LocalizedText(),
          narrative: const LocalizedText(),
        ),
      );
    widget.controller.setProjects(updated);
  }

  void _updateProject(int index, Project proj) {
    final updated = List<Project>.from(_projects);
    updated[index] = proj;
    widget.controller.setProjects(updated);
  }

  void _removeProject(int index) {
    final updated = List<Project>.from(_projects)..removeAt(index);
    widget.controller.setProjects(updated);
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        // 1. Skills Card
        _buildSkillsCard(context),
        const SizedBox(height: AppSpacing.md),

        // 2. Languages Card
        _buildLanguagesCard(context),
        const SizedBox(height: AppSpacing.md),

        // 3. Certifications Card
        _buildCertificationsCard(context),
        const SizedBox(height: AppSpacing.md),

        // 4. Projects Card
        _buildProjectsCard(context),
      ],
    );
  }

  // ── Skills Card Widget ───────────────────────────────────────────────────

  Widget _buildSkillsCard(BuildContext context) {
    final c = context.sirati;
    final l10n = AppLocalizations.of(context);

    // Categories grouping — keys stay as stored identifiers.
    final categories = <String, List<MapEntry<int, Skill>>>{};
    for (var i = 0; i < _skills.length; i++) {
      final cat = _skills[i].category.ar.isEmpty
          ? SkillStorageKeys.general
          : _skills[i].category.ar;
      categories.putIfAbsent(cat, () => []).add(MapEntry(i, _skills[i]));
    }

    return SectionCardWrapper(
      title: l10n.skillsTitle,
      subtitle: l10n.skillsSubtitle,
      icon: Icons.psychology_outlined,
      entryCount: _skills.length,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Quick Add Bar
          _SkillQuickAddBar(onAdd: _addSkill),
          const SizedBox(height: AppSpacing.md),

          if (_skills.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
              child: Text(
                l10n.noSkillsHint,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: c.textHint,
                      fontStyle: FontStyle.italic,
                    ),
              ),
            )
          else ...[
            for (final entry in categories.entries) ...[
              Padding(
                padding: const EdgeInsets.only(
                    bottom: AppSpacing.xs, top: AppSpacing.xs),
                child: Text(
                  _skillCategoryLabel(l10n, entry.key),
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: c.primary,
                        fontWeight: FontWeight.bold,
                      ),
                ),
              ),
              Wrap(
                spacing: AppSpacing.xs,
                runSpacing: AppSpacing.xs,
                children: entry.value.map((pair) {
                  final index = pair.key;
                  final skill = pair.value;
                  final levelLabel = skill.level.ar.isEmpty
                      ? ''
                      : ' (${_skillLevelLabel(l10n, skill.level.ar)})';
                  return Chip(
                    label: Text(
                      '${skill.name.ar}$levelLabel',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: c.textPrimary,
                          ),
                    ),
                    backgroundColor: c.surfaceHigh,
                    side: BorderSide(color: c.border),
                    deleteIcon: const Icon(Icons.close, size: 16),
                    onDeleted: () => _removeSkill(index),
                  );
                }).toList(),
              ),
              const SizedBox(height: AppSpacing.xs),
            ],
          ],
        ],
      ),
    );
  }

  // ── Languages Card Widget ────────────────────────────────────────────────

  Widget _buildLanguagesCard(BuildContext context) {
    final c = context.sirati;
    final l10n = AppLocalizations.of(context);

    return SectionCardWrapper(
      title: l10n.languagesTitle,
      subtitle: l10n.languagesSubtitle,
      icon: Icons.translate_outlined,
      entryCount: _languages.length,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _LanguageQuickAddBar(onAdd: _addLanguage),
          const SizedBox(height: AppSpacing.md),
          if (_languages.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
              child: Text(
                l10n.noLanguagesHint,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: c.textHint,
                    ),
              ),
            )
          else
            Wrap(
              spacing: AppSpacing.xs,
              runSpacing: AppSpacing.xs,
              children: _languages.asMap().entries.map((e) {
                final idx = e.key;
                final lang = e.value;
                return Chip(
                  label: Text(
                    '${lang.name.ar} - ${_languageLevelLabel(l10n, lang.level.ar)}',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: c.textPrimary,
                        ),
                  ),
                  backgroundColor: c.surfaceHigh,
                  side: BorderSide(color: c.border),
                  deleteIcon: const Icon(Icons.close, size: 16),
                  onDeleted: () => _removeLanguage(idx),
                );
              }).toList(),
            ),
        ],
      ),
    );
  }

  // ── Certifications Card Widget ───────────────────────────────────────────

  Widget _buildCertificationsCard(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return SectionCardWrapper(
      title: l10n.certificationsTitle,
      subtitle: l10n.certificationsSubtitle,
      icon: Icons.verified_outlined,
      entryCount: _certifications.length,
      onAdd: _addCertification,
      addLabel: l10n.addCertification,
      child: ReorderableEntryList<Certification>(
        items: _certifications,
        onReorder: (oldIdx, newIdx) {
          final updated = List<Certification>.from(_certifications);
          if (oldIdx < newIdx) newIdx -= 1;
          final item = updated.removeAt(oldIdx);
          updated.insert(newIdx, item);
          widget.controller.setCertifications(updated);
        },
        onDelete: _removeCertification,
        itemBuilder: (context, item, index) {
          return _CertificationEntryTile(
            key: ValueKey(item.id ?? index),
            cert: item,
            onChanged: (updated) => _updateCertification(index, updated),
          );
        },
      ),
    );
  }

  // ── Projects Card Widget ─────────────────────────────────────────────────

  Widget _buildProjectsCard(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return SectionCardWrapper(
      title: l10n.projectsTitle,
      subtitle: l10n.projectsSubtitle,
      icon: Icons.rocket_launch_outlined,
      entryCount: _projects.length,
      onAdd: _addProject,
      addLabel: l10n.addProject,
      child: ReorderableEntryList<Project>(
        items: _projects,
        onReorder: (oldIdx, newIdx) {
          final updated = List<Project>.from(_projects);
          if (oldIdx < newIdx) newIdx -= 1;
          final item = updated.removeAt(oldIdx);
          updated.insert(newIdx, item);
          widget.controller.setProjects(updated);
        },
        onDelete: _removeProject,
        itemBuilder: (context, item, index) {
          return _ProjectEntryTile(
            key: ValueKey(item.id ?? index),
            project: item,
            onChanged: (updated) => _updateProject(index, updated),
          );
        },
      ),
    );
  }
}

// ── Display mapping (storage key → locale label) ────────────────────────────

String _skillLevelLabel(AppLocalizations l10n, String stored) {
  return switch (stored) {
    SkillStorageKeys.beginner => l10n.skillLevelBeginner,
    SkillStorageKeys.intermediate => l10n.skillLevelIntermediate,
    SkillStorageKeys.advanced => l10n.skillLevelAdvanced,
    SkillStorageKeys.expert => l10n.skillLevelExpert,
    _ => stored,
  };
}

String _skillCategoryLabel(AppLocalizations l10n, String stored) {
  return switch (stored) {
    SkillStorageKeys.technical => l10n.skillCategoryTechnical,
    SkillStorageKeys.soft => l10n.skillCategorySoft,
    SkillStorageKeys.tools => l10n.skillCategoryTools,
    SkillStorageKeys.management => l10n.skillCategoryManagement,
    SkillStorageKeys.general => l10n.skillCategoryGeneral,
    _ => stored,
  };
}

String _languageLevelLabel(AppLocalizations l10n, String stored) {
  return switch (stored) {
    LanguageLevelStorageKeys.native => l10n.languageLevelNative,
    LanguageLevelStorageKeys.fluent => l10n.languageLevelFluent,
    LanguageLevelStorageKeys.c1 => l10n.languageLevelC1,
    LanguageLevelStorageKeys.b2 => l10n.languageLevelB2,
    LanguageLevelStorageKeys.a2 => l10n.languageLevelA2,
    _ => stored,
  };
}

// ── Quick Add Bars ──────────────────────────────────────────────────────────

class _SkillQuickAddBar extends StatefulWidget {
  final void Function(String name, String level, String category) onAdd;

  const _SkillQuickAddBar({required this.onAdd});

  @override
  State<_SkillQuickAddBar> createState() => _SkillQuickAddBarState();
}

class _SkillQuickAddBarState extends State<_SkillQuickAddBar> {
  final _nameCtrl = TextEditingController();
  String _level = SkillStorageKeys.advanced;
  String _category = SkillStorageKeys.technical;

  @override
  void dispose() {
    _nameCtrl.dispose();
    super.dispose();
  }

  void _submit() {
    if (_nameCtrl.text.trim().isNotEmpty) {
      widget.onAdd(_nameCtrl.text.trim(), _level, _category);
      _nameCtrl.clear();
      setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return Column(
      children: [
        Row(
          children: [
            Expanded(
              flex: 2,
              child: AppInput(
                controller: _nameCtrl,
                hint: l10n.skillNameHint,
                onChanged: (_) => setState(() {}),
              ),
            ),
            const SizedBox(width: AppSpacing.xs),
            AppButton.primary(
              label: l10n.addItem,
              icon: Icons.add,
              expand: false,
              onPressed: _nameCtrl.text.trim().isEmpty ? null : _submit,
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.xs),
        Row(
          children: [
            Expanded(
              child: DropdownButtonFormField<String>(
                // ignore: deprecated_member_use
                value: _level,
                isExpanded: true,
                decoration: InputDecoration(
                  labelText: l10n.proficiencyLevel,
                  isDense: true,
                ),
                items: [
                  for (final key in SkillStorageKeys.levels)
                    DropdownMenuItem(
                      value: key,
                      child: Text(
                        _skillLevelLabel(l10n, key),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                ],
                onChanged: (val) {
                  if (val != null) setState(() => _level = val);
                },
              ),
            ),
            const SizedBox(width: AppSpacing.sm),
            Expanded(
              child: DropdownButtonFormField<String>(
                // ignore: deprecated_member_use
                value: _category,
                isExpanded: true,
                decoration: InputDecoration(
                  labelText: l10n.skillCategory,
                  isDense: true,
                ),
                items: [
                  for (final key in SkillStorageKeys.categories)
                    DropdownMenuItem(
                      value: key,
                      child: Text(
                        _skillCategoryLabel(l10n, key),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                ],
                onChanged: (val) {
                  if (val != null) setState(() => _category = val);
                },
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _LanguageQuickAddBar extends StatefulWidget {
  final void Function(String name, String level) onAdd;

  const _LanguageQuickAddBar({required this.onAdd});

  @override
  State<_LanguageQuickAddBar> createState() => _LanguageQuickAddBarState();
}

class _LanguageQuickAddBarState extends State<_LanguageQuickAddBar> {
  final _nameCtrl = TextEditingController();
  String _level = LanguageLevelStorageKeys.fluent;

  @override
  void dispose() {
    _nameCtrl.dispose();
    super.dispose();
  }

  void _submit() {
    if (_nameCtrl.text.trim().isNotEmpty) {
      widget.onAdd(_nameCtrl.text.trim(), _level);
      _nameCtrl.clear();
      setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return Row(
      children: [
        Expanded(
          flex: 2,
          child: AppInput(
            controller: _nameCtrl,
            hint: l10n.languageNameHint,
            onChanged: (_) => setState(() {}),
          ),
        ),
        const SizedBox(width: AppSpacing.xs),
        Expanded(
          flex: 2,
          child: DropdownButtonFormField<String>(
            // ignore: deprecated_member_use
            value: _level,
            isExpanded: true,
            decoration: const InputDecoration(isDense: true),
            items: [
              for (final key in LanguageLevelStorageKeys.values)
                DropdownMenuItem(
                  value: key,
                  child: Text(
                    _languageLevelLabel(l10n, key),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
            ],
            onChanged: (val) {
              if (val != null) setState(() => _level = val);
            },
          ),
        ),
        const SizedBox(width: AppSpacing.xs),
        AppButton.primary(
          label: l10n.addItem,
          icon: Icons.add,
          expand: false,
          onPressed: _nameCtrl.text.trim().isEmpty ? null : _submit,
        ),
      ],
    );
  }
}

// ── Certification Tile ──────────────────────────────────────────────────────

class _CertificationEntryTile extends StatefulWidget {
  final Certification cert;
  final ValueChanged<Certification> onChanged;

  const _CertificationEntryTile({
    super.key,
    required this.cert,
    required this.onChanged,
  });

  @override
  State<_CertificationEntryTile> createState() =>
      _CertificationEntryTileState();
}

class _CertificationEntryTileState extends State<_CertificationEntryTile> {
  late final TextEditingController _nameCtrl;
  late final TextEditingController _authorityCtrl;
  late final TextEditingController _issueDateCtrl;
  late final TextEditingController _expiryDateCtrl;
  late bool _noExpiry;

  @override
  void initState() {
    super.initState();
    _nameCtrl = TextEditingController(text: widget.cert.name.ar);
    _authorityCtrl = TextEditingController(text: widget.cert.authority.ar);
    _issueDateCtrl = TextEditingController(text: widget.cert.issueDate ?? '');
    _expiryDateCtrl = TextEditingController(text: widget.cert.expiryDate ?? '');
    _noExpiry = widget.cert.expiryDate == null;
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _authorityCtrl.dispose();
    _issueDateCtrl.dispose();
    _expiryDateCtrl.dispose();
    super.dispose();
  }

  void _notify() {
    widget.onChanged(
      widget.cert.copyWith(
        name: LocalizedText(ar: _nameCtrl.text.trim()),
        authority: LocalizedText(ar: _authorityCtrl.text.trim()),
        issueDate: _issueDateCtrl.text.trim().isEmpty
            ? null
            : _issueDateCtrl.text.trim(),
        expiryDate: _noExpiry ? null : _expiryDateCtrl.text.trim(),
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
          AppInput(
            controller: _nameCtrl,
            label: l10n.certNameLabel,
            hint: l10n.certNameHint,
            onChanged: (_) => _notify(),
          ),
          const SizedBox(height: AppSpacing.sm),
          AppInput(
            controller: _authorityCtrl,
            label: l10n.certIssuerLabel,
            hint: l10n.certIssuerHint,
            onChanged: (_) => _notify(),
          ),
          const SizedBox(height: AppSpacing.sm),
          Row(
            children: [
              Expanded(
                child: AppInput(
                  controller: _issueDateCtrl,
                  label: l10n.issueYearLabel,
                  hint: l10n.issueYearHint,
                  onChanged: (_) => _notify(),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: _noExpiry
                    ? Container(
                        height: AppTouchTarget.min,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: c.primaryLight,
                          borderRadius: BorderRadius.circular(AppRadius.md),
                        ),
                        child: Text(
                          l10n.noExpiry,
                          style:
                              Theme.of(context).textTheme.bodySmall?.copyWith(
                                    color: c.primary,
                                    fontWeight: FontWeight.bold,
                                  ),
                        ),
                      )
                    : AppInput(
                        controller: _expiryDateCtrl,
                        label: l10n.expiryYearLabel,
                        hint: l10n.expiryYearHint,
                        onChanged: (_) => _notify(),
                      ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.xs),
          Row(
            children: [
              Checkbox(
                value: _noExpiry,
                onChanged: (val) {
                  setState(() => _noExpiry = val ?? false);
                  _notify();
                },
              ),
              Text(
                l10n.certPermanent,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: c.textPrimary,
                    ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

// ── Project Tile ────────────────────────────────────────────────────────────

class _ProjectEntryTile extends StatefulWidget {
  final Project project;
  final ValueChanged<Project> onChanged;

  const _ProjectEntryTile({
    super.key,
    required this.project,
    required this.onChanged,
  });

  @override
  State<_ProjectEntryTile> createState() => _ProjectEntryTileState();
}

class _ProjectEntryTileState extends State<_ProjectEntryTile> {
  late final TextEditingController _titleCtrl;
  late final TextEditingController _roleCtrl;
  late final TextEditingController _urlCtrl;
  late final TextEditingController _descCtrl;

  String? _urlError;

  @override
  void initState() {
    super.initState();
    _titleCtrl = TextEditingController(text: widget.project.title.ar);
    _roleCtrl = TextEditingController(text: widget.project.role.ar);
    _urlCtrl = TextEditingController(text: widget.project.url ?? '');
    _descCtrl = TextEditingController(text: widget.project.narrative.ar);
  }

  @override
  void dispose() {
    _titleCtrl.dispose();
    _roleCtrl.dispose();
    _urlCtrl.dispose();
    _descCtrl.dispose();
    super.dispose();
  }

  void _notify() {
    final l10n = AppLocalizations.of(context);
    final urlText = _urlCtrl.text.trim();
    if (urlText.isNotEmpty &&
        !urlText.startsWith('http://') &&
        !urlText.startsWith('https://')) {
      _urlError = l10n.invalidProjectUrl;
    } else {
      _urlError = null;
    }
    setState(() {});

    widget.onChanged(
      widget.project.copyWith(
        title: LocalizedText(ar: _titleCtrl.text.trim()),
        role: LocalizedText(ar: _roleCtrl.text.trim()),
        url: urlText.isEmpty ? null : urlText,
        narrative: LocalizedText(ar: _descCtrl.text.trim()),
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
          Row(
            children: [
              Expanded(
                child: AppInput(
                  controller: _titleCtrl,
                  label: l10n.projectTitleLabel,
                  hint: l10n.projectTitleHint,
                  onChanged: (_) => _notify(),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: AppInput(
                  controller: _roleCtrl,
                  label: l10n.projectRoleLabel,
                  hint: l10n.projectRoleHint,
                  onChanged: (_) => _notify(),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          AppInput(
            controller: _urlCtrl,
            label: l10n.projectUrlLabel,
            hint: 'https://github.com/example/project',
            textDirection: TextDirection.ltr,
            prefixIcon: const Icon(Icons.link, size: 20),
            errorText: _urlError,
            onChanged: (_) => _notify(),
          ),
          const SizedBox(height: AppSpacing.sm),
          AppInput(
            controller: _descCtrl,
            label: l10n.projectDescLabel,
            hint: l10n.projectDescHint,
            maxLines: 3,
            onChanged: (_) => _notify(),
          ),
        ],
      ),
    );
  }
}
