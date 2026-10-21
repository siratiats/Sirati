import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/logging/app_log.dart';
import 'package:sirati/features/cv_builder/controllers/cv_builder_controller.dart';
import 'package:sirati/features/cv_builder/data/cv_repository_provider.dart';
import 'package:sirati/features/cv_builder/presentation/cv_builder/save_status_badge.dart';
import 'package:sirati/features/cv_builder/presentation/cv_builder/section_order_tab.dart';
import 'package:sirati/features/cv_builder/presentation/cv_live_preview_pane.dart';
import 'package:sirati/features/cv_builder/presentation/experience_education_editor.dart';
import 'package:sirati/features/cv_builder/presentation/personal_details_editor.dart';
import 'package:sirati/features/cv_builder/presentation/skills_languages_editor.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/cv_document.dart';
import 'package:sirati/shared/models/cv_template.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/components.dart';

/// Background, hidden, or closing: persist pending edits now.
bool savesOnLifecycle(AppLifecycleState state) =>
    state == AppLifecycleState.paused ||
    state == AppLifecycleState.inactive ||
    state == AppLifecycleState.hidden ||
    state == AppLifecycleState.detached;

/// Interactive CV Builder Screen (SIRATI-36, 37, 38, 39, 40, 42).
class CvBuilderScreen extends ConsumerStatefulWidget {
  const CvBuilderScreen({super.key, this.cvId, this.initialDocument});

  final String? cvId;
  final CvDocument? initialDocument;

  @override
  ConsumerState<CvBuilderScreen> createState() => _CvBuilderScreenState();
}

class _CvBuilderScreenState extends ConsumerState<CvBuilderScreen>
    with SingleTickerProviderStateMixin, WidgetsBindingObserver {
  CvBuilderController? _controller;
  late final TabController _tabController;

  bool _isLoading = true;
  String? _errorMessage;
  bool _showPreviewPane = false;

  /// Set once the user starts leaving, so a second Back doesn't pop the
  /// route underneath this one.
  bool _leaving = false;

  static const _template = CvTemplate(
    id: 1,
    slug: 'ats-classic-professional',
    name: 'ATS Classic Professional',
    nameAr: 'كلاسيكي احترافي',
    nameEn: 'ATS Classic Professional',
    previewImageUrl: null,
    languageDirection: 'both',
    supportedLanguages: ['ar', 'en'],
    supportedSections: [],
    isDefault: true,
    isPremium: false,
  );

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _tabController = TabController(length: 4, vsync: this);
    unawaited(_initializeDocument());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _tabController.dispose();
    final controller = _controller;
    if (controller != null) {
      // The route can be removed without a Back (logout, deep link): finish
      // any pending save before releasing the controller.
      if (controller.isDirty || controller.isSaving) {
        unawaited(controller.flush().whenComplete(controller.dispose));
      } else {
        controller.dispose();
      }
    }
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final controller = _controller;
    if (savesOnLifecycle(state) && controller != null && controller.isDirty) {
      unawaited(controller.saveImmediately());
    }
  }

  Future<void> _initializeDocument() async {
    final repository = ref.read(cvRepositoryProvider);
    final CvDocument doc;
    try {
      final initial = widget.initialDocument;
      final id = widget.cvId;
      if (initial != null) {
        doc = initial;
      } else if (id != null) {
        doc = await repository.getCv(id) ?? CvDocument.createEmpty(id: id);
      } else {
        doc = CvDocument.createEmpty();
      }
    } catch (e, stack) {
      AppLog.event(
        AppLogEvent.cvLoadFailed,
        level: AppLogLevel.error,
        data: {'phase': 'builder_init'},
        error: e,
        stackTrace: stack,
      );
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _errorMessage = AppLocalizations.of(context).cvLoadFailed;
      });
      return;
    }
    if (!mounted) return;

    _controller?.dispose();
    final controller = CvBuilderController(
      initialDocument: doc,
      repository: repository,
    );
    setState(() {
      _controller = controller;
      _isLoading = false;
      _errorMessage = null;
    });

    // Crash / uncommitted draft recovery (SIRATI-42). Never blocks editing:
    // the check itself swallows storage errors and returns null.
    final id = doc.id;
    if (id == null) return;
    final draft =
        await CvBuilderController.checkDraftRecovery(id, doc.updatedAt);
    if (draft != null && mounted && identical(_controller, controller)) {
      unawaited(_showRecoveryPrompt(draft));
    }
  }

  Future<void> _showRecoveryPrompt(CvDocument draft) {
    final c = context.sirati;
    final l10n = AppLocalizations.of(context);
    return showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.lg),
        ),
        title: Row(
          children: [
            Icon(Icons.restore, color: c.primary, size: 24),
            const SizedBox(width: AppSpacing.xs),
            Flexible(child: Text(l10n.recoverDraftTitle)),
          ],
        ),
        content: Text(l10n.recoverDraftBody),
        actions: [
          TextButton(
            onPressed: () {
              final id = draft.id;
              if (id != null) unawaited(CvBuilderController.clearDraft(id));
              Navigator.of(ctx).pop();
            },
            style: TextButton.styleFrom(foregroundColor: c.textSecondary),
            child: Text(l10n.discardDraft),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: c.primary,
              foregroundColor: c.onPrimary,
            ),
            onPressed: () {
              _controller?.updateDocument(draft);
              Navigator.of(ctx).pop();
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text(l10n.draftRestored)),
              );
            },
            child: Text(l10n.restoreDraft),
          ),
        ],
      ),
    );
  }

  Future<void> _leave() async {
    if (_leaving) return;
    _leaving = true;
    final controller = _controller;
    final saved = controller == null || await controller.flush();
    if (!mounted) return;
    if (!saved) {
      // The crash draft still holds the edits; say so on the way out.
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppLocalizations.of(context).cvSaveFailed)),
      );
    }
    Navigator.of(context).pop();
  }

  Future<void> _saveNow() async {
    final controller = _controller;
    if (controller == null) return;
    final saved = await controller.flush();
    if (!mounted) return;
    final l10n = AppLocalizations.of(context);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(saved ? l10n.cvSaved : l10n.cvSaveFailed),
        duration: Duration(seconds: saved ? 1 : 4),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    final l10n = AppLocalizations.of(context);

    if (_isLoading) {
      return Scaffold(
        appBar: AppBar(title: Text(l10n.cvEditorTitle)),
        body: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const BrandedLoader(),
              const SizedBox(height: AppSpacing.md),
              Text(
                l10n.preparingEditor,
                style: Theme.of(context)
                    .textTheme
                    .bodyMedium
                    ?.copyWith(color: c.textSecondary),
              ),
            ],
          ),
        ),
      );
    }

    final controller = _controller;
    if (_errorMessage != null || controller == null) {
      return Scaffold(
        appBar: AppBar(title: Text(l10n.cvEditorTitle)),
        body: Center(
          child: AppEmptyState(
            icon: Icons.error_outline,
            title: l10n.loadErrorTitle,
            subtitle: _errorMessage ?? l10n.cvLoadFailed,
            actionLabel: l10n.retry,
            onAction: () {
              setState(() {
                _isLoading = true;
                _errorMessage = null;
              });
              unawaited(_initializeDocument());
            },
            scrollable: false,
          ),
        ),
      );
    }

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) unawaited(_leave());
      },
      child: ListenableBuilder(
        listenable: controller,
        builder: (context, _) {
          final doc = controller.document;
          return Scaffold(
            appBar: AppBar(
              title: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    doc.title ?? l10n.cvEditorTitle,
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium
                        ?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  SaveStatusBadge(
                    isSaving: controller.isSaving,
                    isDirty: controller.isDirty,
                  ),
                ],
              ),
              actions: [
                IconButton(
                  icon: Icon(
                    _showPreviewPane
                        ? Icons.edit_note
                        : Icons.visibility_outlined,
                  ),
                  tooltip:
                      _showPreviewPane ? l10n.backToEdit : l10n.livePreview,
                  onPressed: () =>
                      setState(() => _showPreviewPane = !_showPreviewPane),
                ),
                IconButton(
                  icon: const Icon(Icons.save_outlined),
                  tooltip: l10n.saveNow,
                  onPressed: () => unawaited(_saveNow()),
                ),
              ],
              bottom: TabBar(
                controller: _tabController,
                tabs: [
                  Tab(icon: const Icon(Icons.person), text: l10n.tabPersonal),
                  Tab(icon: const Icon(Icons.work), text: l10n.tabExperience),
                  Tab(icon: const Icon(Icons.psychology), text: l10n.tabSkills),
                  Tab(
                    icon: const Icon(Icons.format_line_spacing),
                    text: l10n.tabOrder,
                  ),
                ],
              ),
            ),
            body: LayoutBuilder(
              builder: (context, constraints) {
                Widget page(Widget child) => SingleChildScrollView(
                      padding: const EdgeInsets.all(AppSpacing.md),
                      child: child,
                    );
                final editor = TabBarView(
                  controller: _tabController,
                  children: [
                    page(PersonalDetailsEditor(controller: controller)),
                    page(ExperienceEducationEditor(controller: controller)),
                    page(SkillsLanguagesEditor(controller: controller)),
                    page(SectionOrderTab(controller: controller)),
                  ],
                );

                if (constraints.maxWidth >= 900) {
                  return Row(
                    children: [
                      Expanded(flex: 3, child: editor),
                      const VerticalDivider(width: 1),
                      Expanded(
                        flex: 2,
                        child: ColoredBox(
                          color: c.surfaceLow,
                          child: CvLivePreviewPane(
                            controller: controller,
                            template: _template,
                            scale: 0.85,
                          ),
                        ),
                      ),
                    ],
                  );
                }
                if (_showPreviewPane) {
                  return ColoredBox(
                    color: c.surfaceLow,
                    child: CvLivePreviewPane(
                      controller: controller,
                      template: _template,
                    ),
                  );
                }
                return editor;
              },
            ),
          );
        },
      ),
    );
  }
}
