import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/shared/services/mobile_content_service_provider.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/empty_state.dart';
import 'package:sirati/shared/widgets/language_toggle.dart';
import 'package:sirati/shared/widgets/loading/app_async_body.dart';
import 'package:sirati/shared/widgets/loading/app_skeleton.dart';

class EducationDetailScreen extends ConsumerStatefulWidget {
  final int? id;
  final Map<String, dynamic> fallback;

  const EducationDetailScreen({super.key, this.id, required this.fallback});

  @override
  ConsumerState<EducationDetailScreen> createState() =>
      _EducationDetailScreenState();
}

class _EducationDetailScreenState extends ConsumerState<EducationDetailScreen> {
  Future<Map<String, dynamic>>? _future;
  bool? _loadedEnglish;

  void _ensureFuture(bool english) {
    if (_future != null && _loadedEnglish == english) return;
    _loadedEnglish = english;
    final id = widget.id;
    _future = id == null
        ? Future.value(widget.fallback)
        : ref.read(mobileContentServiceProvider).educationContent(id, english);
  }

  void _retry(bool english) {
    setState(() {
      _loadedEnglish = null;
      _future = null;
    });
    _ensureFuture(english);
  }

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    _ensureFuture(english);

    return Scaffold(
      backgroundColor: context.sirati.background,
      appBar: AppBar(
        title: Text(english ? 'Learning Detail' : 'تفاصيل المحتوى'),
        actions: const [
          Padding(
            padding: EdgeInsetsDirectional.only(end: 12),
            child: LanguageToggle(),
          ),
        ],
      ),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          return AppAsyncBody<Map<String, dynamic>>(
            snapshot: snapshot,
            english: english,
            onRetry: () => _retry(english),
            fallbackOnEmptyError: widget.fallback,
            errorMessage: (error) => error is ApiException
                ? error.displayMessage
                : (english
                    ? 'Could not load this content.'
                    : 'تعذر تحميل هذا المحتوى.'),
            loading: const _EducationDetailSkeleton(),
            errorBuilder: (error, message) {
              // Prefer list-card fallback when API fails but we navigated with data.
              if (widget.fallback.isNotEmpty &&
                  (_text(widget.fallback['title']).isNotEmpty ||
                      _text(widget.fallback['body']).isNotEmpty)) {
                return _DetailBody(
                  data: widget.fallback,
                  english: english,
                );
              }
              return AppErrorState(
                english: english,
                message: message,
                onRetry: () => _retry(english),
                exception: error is ApiException ? error : null,
              );
            },
            builder: (data) => _DetailBody(data: data, english: english),
          );
        },
      ),
    );
  }
}

class _DetailBody extends StatelessWidget {
  final Map<String, dynamic> data;
  final bool english;

  const _DetailBody({required this.data, required this.english});

  @override
  Widget build(BuildContext context) {
    final title =
        LocaleFormat.mixedTitle(_text(data['title']), english: english);
    final body = LocaleFormat.mixedBody(_text(data['body']), english: english);
    final duration = _text(data['duration']);
    final role = _text(data['target_role']);
    final badge = _text(data['badge']);

    return ListView(
      padding: const EdgeInsets.fromLTRB(
          AppSpacing.lg, AppSpacing.lg, AppSpacing.lg, AppSpacing.xxl),
      children: [
        Wrap(
          spacing: AppSpacing.xs,
          runSpacing: AppSpacing.xs,
          alignment: WrapAlignment.start,
          children: [
            if (badge.isNotEmpty) _Chip(label: badge),
            if (role.isNotEmpty) _Chip(label: role),
            if (duration.isNotEmpty) _Chip(label: duration),
          ],
        ),
        const SizedBox(height: AppSpacing.xl),
        Text(
          title.isEmpty ? (english ? 'Untitled' : 'بدون عنوان') : title,
          textAlign: TextAlign.start,
          style: AppTextStyles.titleLg(context.sirati)
              .copyWith(fontSize: 26, height: 1.35),
        ),
        const SizedBox(height: AppSpacing.md),
        Container(
          padding: const EdgeInsets.all(AppSpacing.lg),
          decoration: BoxDecoration(
            color: context.sirati.surface,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: context.sirati.border),
            boxShadow: context.sirati.softShadow,
          ),
          child: Text(
            body.isEmpty
                ? (english
                    ? 'No content available for this item.'
                    : 'لا يوجد محتوى لهذا العنصر.')
                : body,
            textAlign: TextAlign.start,
            style: AppTextStyles.bodyMd(context.sirati).copyWith(
              fontSize: 16.5,
              height: 1.75,
            ),
          ),
        ),
      ],
    );
  }
}

class _EducationDetailSkeleton extends StatelessWidget {
  const _EducationDetailSkeleton();

  @override
  Widget build(BuildContext context) {
    return AppSkeletonScope(
      child: ListView(
        padding: const EdgeInsets.fromLTRB(
            AppSpacing.lg, AppSpacing.lg, AppSpacing.lg, AppSpacing.xxl),
        children: const [
          Row(
            children: [
              AppSkeleton(width: 72, height: 28, radius: 999),
              SizedBox(width: AppSpacing.xs),
              AppSkeleton(width: 96, height: 28, radius: 999),
              SizedBox(width: AppSpacing.xs),
              AppSkeleton(width: 80, height: 28, radius: 999),
            ],
          ),
          SizedBox(height: AppSpacing.xl),
          AppSkeleton(height: 28),
          SizedBox(height: AppSpacing.xs),
          AppSkeleton(width: 220, height: 28),
          SizedBox(height: AppSpacing.md),
          AppSkeleton(height: 220, radius: 16),
        ],
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  final String label;

  const _Chip({required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: context.sirati.primaryLight,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 13,
          fontWeight: FontWeight.w700,
          // Gated chip pair: primaryInk on primaryLight.
          color: context.sirati.primaryInk,
        ),
      ),
    );
  }
}

String _text(dynamic value) => value?.toString().trim() ?? '';
