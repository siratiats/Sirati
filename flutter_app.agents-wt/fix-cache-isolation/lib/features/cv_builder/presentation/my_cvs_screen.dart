import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/routing/app_routes.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/core/utils/safe_url.dart';
import 'package:sirati/features/cv_builder/application/my_cvs_controller.dart';
import 'package:sirati/features/cv_builder/domain/cv_list.dart';
import 'package:sirati/features/cv_builder/presentation/cv_generator_screen.dart';
import 'package:sirati/features/cv_builder/presentation/generated_cv_screen.dart';
import 'package:sirati/features/cv_builder/presentation/my_cvs/cv_document_card.dart';
import 'package:sirati/features/cv_builder/presentation/my_cvs/cv_template_picker.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/cv_template.dart';
import 'package:sirati/shared/models/generated_cv.dart';
import 'package:sirati/shared/services/cv_api_service_provider.dart';
import 'package:sirati/shared/services/session_cache.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/app_snack_bar.dart';
import 'package:sirati/shared/widgets/empty_state.dart';
import 'package:sirati/shared/widgets/loading/app_async_body.dart';
import 'package:sirati/shared/widgets/loading/app_skeleton.dart';
import 'package:sirati/shared/widgets/loading/async_value_snapshot.dart';
import 'package:sirati/shared/widgets/motion.dart';
import 'package:sirati/shared/widgets/offline_cache_banner.dart';
import 'package:sirati/shared/widgets/screen_header.dart';

/// "My CVs" tab: saved CVs with edit, download (per template), and delete.
class MyCvsScreen extends ConsumerStatefulWidget {
  const MyCvsScreen({super.key});

  @override
  ConsumerState<MyCvsScreen> createState() => _MyCvsScreenState();
}

class _MyCvsScreenState extends ConsumerState<MyCvsScreen> {
  bool get _english => AppLocale.isEnglish(context);

  Future<void> _refresh() => refreshMyCvs(ref, english: _english);

  void _openGenerator() {
    unawaited(Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const CvGeneratorScreen()),
    ));
  }

  Future<void> _edit(CvListItem item) async {
    final id = item.id;
    if (id == null) return;
    try {
      final cv = await ref.read(cvApiServiceProvider).getGeneratedCv(id);
      if (!mounted) return;
      final updated = await Navigator.of(context).push<GeneratedCv>(
        MaterialPageRoute(builder: (_) => CvGeneratorScreen(initialCv: cv)),
      );
      if (!mounted) return;
      unawaited(_refresh());
      if (updated != null) {
        unawaited(Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => GeneratedCvScreen(generatedCv: updated),
          ),
        ));
      }
    } catch (error) {
      _reportError(
        error,
        _english ? 'Could not open this CV.' : 'تعذر فتح هذه السيرة.',
      );
    }
  }

  Future<void> _delete(CvListItem item) async {
    final id = item.id;
    if (id == null) return;
    final english = _english;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(english ? 'Delete CV?' : 'حذف السيرة؟'),
        content: Text(english
            ? 'This action cannot be undone.'
            : 'لا يمكن التراجع عن هذا الإجراء.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(english ? 'Cancel' : 'إلغاء'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            style: TextButton.styleFrom(
              foregroundColor: context.sirati.error,
            ),
            child: Text(english ? 'Delete' : 'حذف'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    try {
      await ref.read(myCvsProvider(english).notifier).delete(id);
    } catch (error) {
      _reportError(
        error,
        english ? 'Could not delete this CV.' : 'تعذر حذف السيرة.',
      );
    }
  }

  Future<void> _download(CvListItem item) async {
    final english = _english;
    final api = ref.read(cvApiServiceProvider);
    final String? slug;
    if (item.offersTemplateChoice) {
      final choice = await _chooseTemplate(english);
      if (choice case _Cancelled()) return;
      slug = (choice as _Chosen).template?.slug;
    } else {
      slug = null;
    }
    if (!mounted) return;

    AppSnackBar.info(
      context,
      english ? 'Opening PDF download...' : 'جارٍ فتح رابط تنزيل الـ PDF...',
    );
    final url = await resolveCvDownloadUrl(api, item, templateSlug: slug);
    if (!mounted) return;
    if (url.isEmpty) {
      AppSnackBar.error(
        context,
        english ? 'Could not find PDF link.' : 'تعذر العثور على رابط الـ PDF.',
      );
      return;
    }
    final launched = await launchSafeExternalUrl(url);
    if (mounted && !launched) {
      AppSnackBar.error(
        context,
        english ? 'Could not open PDF.' : 'تعذر فتح ملف PDF.',
      );
    }
  }

  Future<_TemplateChoice> _chooseTemplate(bool english) async {
    try {
      final templates = await ref
          .read(cvApiServiceProvider)
          .listCvTemplates(english: english);
      if (!mounted) return const _Cancelled();
      if (templates.isEmpty) return const _Chosen(null);

      final picked = await showModalBottomSheet<CvTemplate>(
        context: context,
        showDragHandle: true,
        builder: (_) =>
            CvTemplatePicker(templates: templates, english: english),
      );
      return picked == null ? const _Cancelled() : _Chosen(picked);
    } catch (error) {
      _reportError(
        error,
        english
            ? 'Could not load CV designs. Default design will be used.'
            : 'تعذر تحميل التصاميم. سيتم استخدام التصميم الافتراضي.',
      );
      return const _Chosen(null);
    }
  }

  void _reportError(Object error, String fallback) {
    if (!mounted) return;
    if (error is ApiException) {
      AppSnackBar.fromException(context, error);
    } else {
      AppSnackBar.error(context, fallback);
    }
  }

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    final cvs = ref.watch(myCvsProvider(english));

    return SafeArea(
      child: LayoutBuilder(
        builder: (context, constraints) {
          final maxContentWidth = constraints.maxWidth >= 1100
              ? 920.0
              : constraints.maxWidth >= 780
                  ? 760.0
                  : constraints.maxWidth;
          final horizontalPadding = AppSpacing.pageGutter(constraints.maxWidth);

          Widget shell(Widget child) => Align(
                alignment: Alignment.topCenter,
                child: ConstrainedBox(
                  constraints: BoxConstraints(maxWidth: maxContentWidth),
                  child: child,
                ),
              );

          return AppAsyncBody<CvList>(
            snapshot: cvs.toSnapshot(),
            english: english,
            onRetry: () => ref.invalidate(myCvsProvider(english)),
            fallbackOnEmptyError: CvList.empty(english: english),
            errorMessage: (error) => error is ApiException
                ? error.displayMessage
                : (english
                    ? 'Could not load your CVs.'
                    : 'تعذر تحميل السير الذاتية.'),
            loading: shell(
              CvListSkeleton(horizontalPadding: horizontalPadding),
            ),
            builder: (data) => shell(
              RefreshIndicator(
                color: context.sirati.primary,
                onRefresh: _refresh,
                child: _buildList(context, data, english, horizontalPadding),
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildList(
    BuildContext context,
    CvList data,
    bool english,
    double horizontalPadding,
  ) {
    final items = data.items;
    // Header, then the empty state or every card plus the create CTA.
    final itemCount = items.isEmpty ? 2 : items.length + 2;

    return ListView.builder(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: EdgeInsets.fromLTRB(horizontalPadding, 18, horizontalPadding,
          AppSpacing.scrollBottomNavFab),
      itemCount: itemCount,
      itemBuilder: (context, index) {
        if (index == 0) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                english: english,
                title: data.title,
                icon: Icons.description_rounded,
                unreadCount: SessionCache.instance.unreadCount,
                onNotifications: () =>
                    Navigator.of(context).pushNamed(AppRoutes.notifications),
                onAvatarTap: () =>
                    Navigator.of(context).pushNamed(AppRoutes.settings),
              ),
              if (data.offline) ...[
                const SizedBox(height: AppSpacing.sm),
                OfflineCacheBanner(english: english, surface: 'cvs'),
              ],
              const SizedBox(height: AppSpacing.lg),
              Text(
                AppLocalizations.of(context).cvCount(items.length),
                textAlign: TextAlign.start,
                style: AppTextStyles.bodySm(context.sirati),
              ),
              const SizedBox(height: AppSpacing.md),
            ],
          );
        }

        if (items.isEmpty) {
          return AppEmptyState(
            icon: Icons.description_outlined,
            title: english ? 'No CVs yet' : 'لا توجد سير ذاتية بعد',
            subtitle: english
                ? 'Create your first ATS-ready CV in a few steps.'
                : 'أنشئ أول سيرة ذاتية متوافقة مع ATS بخطوات بسيطة.',
            actionLabel: english ? 'Create New CV' : 'إنشاء سيرة جديدة',
            onAction: _openGenerator,
            scrollable: false,
          );
        }

        if (index == items.length + 1) {
          return CreateCvCard(english: english, onTap: _openGenerator);
        }

        final item = items[index - 1];
        return Padding(
          // Stable key: a delete shifts rows without reusing card state.
          key: ValueKey(item.id ?? 'row-$index'),
          padding: const EdgeInsets.only(bottom: AppSpacing.sm),
          // order > maxStaggerIndex → no reveal controller.
          child: MotionReveal(
            order: index - 1,
            child: CvDocumentCard(
              item: item,
              onEdit: () => unawaited(_edit(item)),
              onDelete: () => unawaited(_delete(item)),
              onDownload: () => unawaited(_download(item)),
            ),
          ),
        );
      },
    );
  }
}

sealed class _TemplateChoice {
  const _TemplateChoice();
}

/// Download with [template], or the default design when null.
final class _Chosen extends _TemplateChoice {
  const _Chosen(this.template);
  final CvTemplate? template;
}

final class _Cancelled extends _TemplateChoice {
  const _Cancelled();
}
