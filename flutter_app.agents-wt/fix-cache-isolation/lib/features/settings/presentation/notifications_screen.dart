import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/utils/app_format.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/settings/application/settings_controllers.dart';
import 'package:sirati/features/settings/domain/notification_item.dart';
import 'package:sirati/shared/services/notification_service.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/app_snack_bar.dart';
import 'package:sirati/shared/widgets/empty_state.dart';
import 'package:sirati/shared/widgets/loading/app_async_body.dart';
import 'package:sirati/shared/widgets/loading/app_skeleton.dart';
import 'package:sirati/shared/widgets/loading/async_value_snapshot.dart';
import 'package:sirati/shared/widgets/motion.dart';

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  Future<void> _refresh(WidgetRef ref) async {
    try {
      final next = ref.refresh(notificationsInboxProvider.future);
      await next;
    } catch (_) {}
  }

  void _reportFailure(BuildContext context) {
    if (!context.mounted) return;
    AppSnackBar.error(
      context,
      AppLocale.isEnglish(context)
          ? 'Could not update notifications. Check your connection.'
          : 'تعذر تحديث الإشعارات. تحقق من الاتصال.',
    );
  }

  Future<void> _open(
    BuildContext context,
    WidgetRef ref,
    NotificationItem item,
  ) async {
    final inbox = ref.read(notificationsInboxProvider.notifier);
    final handler = NotificationService.instance.onNotificationTap;
    if (item.opensSomething && handler != null) {
      // Same router as a tapped push. Screen targets may switch a Home tab,
      // so leave the inbox first to make the result visible.
      if (item.actionType == 'screen') Navigator.of(context).pop();
      handler(item.routePayload);
      unawaited(inbox.markRead(item));
      return;
    }
    if (!await inbox.markRead(item) && context.mounted) {
      _reportFailure(context);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final english = AppLocale.isEnglish(context);
    final inbox = ref.watch(notificationsInboxProvider);
    final hasUnread = inbox.value?.any((i) => !i.isRead) ?? false;

    return Scaffold(
      appBar: AppBar(
        title: Text(english ? 'Notifications' : 'الإشعارات'),
        actions: [
          TextButton(
            onPressed: hasUnread
                ? () async {
                    final ok = await ref
                        .read(notificationsInboxProvider.notifier)
                        .markAllRead();
                    if (!ok && context.mounted) _reportFailure(context);
                  }
                : null,
            child: Text(english ? 'Read all' : 'قراءة الكل'),
          ),
        ],
      ),
      body: AppAsyncBody<List<NotificationItem>>(
        snapshot: inbox.toSnapshot(),
        english: english,
        onRetry: () => ref.invalidate(notificationsInboxProvider),
        fallbackOnEmptyError: const [],
        errorMessage: (error) => error is ApiException
            ? error.displayMessage
            : (english
                ? 'Could not load notifications.'
                : 'تعذر تحميل الإشعارات.'),
        loading: const ListScreenSkeleton(
          itemCount: 5,
          padding: EdgeInsets.fromLTRB(AppSpacing.lg, 18, AppSpacing.lg, 32),
        ),
        isEmpty: (items) => items.isEmpty,
        empty: AppEmptyState(
          icon: Icons.notifications_none_rounded,
          title: english ? 'No notifications yet' : 'لا توجد إشعارات حالياً',
          subtitle: english
              ? 'Updates about jobs and CV activity will show up here.'
              : 'ستظهر هنا التحديثات حول الوظائف ونشاط سيرتك.',
          scrollable: true,
        ),
        builder: (items) => RefreshIndicator(
          onRefresh: () => _refresh(ref),
          child: ListView.separated(
            physics: const AlwaysScrollableScrollPhysics(),
            padding:
                const EdgeInsets.fromLTRB(AppSpacing.lg, 18, AppSpacing.lg, 32),
            itemCount: items.length,
            separatorBuilder: (_, __) => const SizedBox(height: AppSpacing.sm),
            itemBuilder: (context, index) {
              final item = items[index];
              return MotionReveal(
                order: index.clamp(0, 5),
                child: NotificationCard(
                  item: item,
                  onTap: () => unawaited(_open(context, ref, item)),
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}

String notificationTimeLabel(NotificationItem item, {required bool english}) {
  final at = item.createdAt;
  if (at != null) return AppFormat.relativeTime(at, english: english);
  if (item.createdLabel.isEmpty) return '';
  return AppFormat.digits(item.createdLabel, english: english);
}

class NotificationCard extends StatelessWidget {
  const NotificationCard({super.key, required this.item, required this.onTap});

  final NotificationItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    final c = context.sirati;
    final time = notificationTimeLabel(item, english: english);
    final radius = BorderRadius.circular(16);

    return PressScale(
      child: DecoratedBox(
        decoration:
            BoxDecoration(borderRadius: radius, boxShadow: c.softShadow),
        child: Material(
          color: c.surface,
          shape: RoundedRectangleBorder(
            borderRadius: radius,
            side: BorderSide(
              color: item.isRead ? c.border : c.primary.withValues(alpha: .28),
            ),
          ),
          child: InkWell(
            onTap: onTap,
            borderRadius: radius,
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          item.title,
                          textAlign: TextAlign.start,
                          style: AppTextStyles.titleSm(c).copyWith(
                            fontWeight:
                                item.isRead ? FontWeight.w600 : FontWeight.w800,
                          ),
                        ),
                      ),
                      if (!item.isRead)
                        Semantics(
                          label: english ? 'Unread' : 'غير مقروء',
                          child: Container(
                            width: 8,
                            height: 8,
                            decoration: BoxDecoration(
                              color: c.primaryInk,
                              shape: BoxShape.circle,
                            ),
                          ),
                        ),
                      if (item.opensSomething) ...[
                        const SizedBox(width: 6),
                        Icon(
                          Icons.chevron_right_rounded,
                          size: 18,
                          color: c.textSecondary,
                          textDirection: Directionality.of(context),
                        ),
                      ],
                    ],
                  ),
                  if (item.body.isNotEmpty) ...[
                    const SizedBox(height: AppSpacing.xs),
                    Text(
                      item.body,
                      textAlign: TextAlign.start,
                      style: AppTextStyles.bodySm(c),
                    ),
                  ],
                  if (time.isNotEmpty) ...[
                    const SizedBox(height: AppSpacing.xs),
                    Text(
                      time,
                      style: AppTextStyles.labelMd(c)
                          .copyWith(color: c.textSecondary),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
