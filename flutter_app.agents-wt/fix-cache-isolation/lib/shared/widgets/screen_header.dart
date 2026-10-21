import 'package:flutter/material.dart';

import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/motion.dart';

/// Shared top bar for the four main tabs so the notification button, language
/// and title all render identically across screens.
///
/// Layout is direction-aware via ambient [Directionality]:
/// - Avatar + title cluster at **start** (right in RTL, left in LTR)
/// - Notification bell at **end** (left in RTL, right in LTR)
class ScreenHeader extends StatelessWidget {
  final bool english;

  /// Primary line — a greeting ("Hello, …" / neutral "Welcome") or a screen title.
  final String title;

  /// Optional secondary line under the title.
  final String? subtitle;

  /// Optional short status shown as a subtle chip (used on Home only).
  final String? status;

  /// Unread notification count for the bell badge. 0 hides the badge.
  final int unreadCount;

  /// Opens the notifications screen. Required so the bell is always actionable.
  final VoidCallback onNotifications;

  /// Opens Settings when the avatar is tapped.
  final VoidCallback? onAvatarTap;

  final double titleSize;
  final String? avatarLabel;
  final IconData? icon;

  const ScreenHeader({
    super.key,
    required this.english,
    required this.title,
    required this.onNotifications,
    this.subtitle,
    this.status,
    this.unreadCount = 0,
    this.titleSize = 21,
    this.avatarLabel,
    this.icon,
    this.onAvatarTap,
  });

  @override
  Widget build(BuildContext context) {
    final hasStatus = status != null && status!.trim().isNotEmpty;
    final hasSubtitle = subtitle != null && subtitle!.trim().isNotEmpty;

    return LayoutBuilder(
      builder: (context, constraints) {
        final compact = constraints.maxWidth < 340;
        final resolvedTitleSize =
            compact ? titleSize.clamp(18.0, 19.0) : titleSize;

        final avatar = ProfileAvatar(
          icon: icon,
          label:
              avatarLabel ?? (icon == null ? _initialFromTitle(title) : null),
          onTap: onAvatarTap,
          english: english,
        );

        return Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            avatar,
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    textAlign: TextAlign.start,
                    style: TextStyle(
                      fontSize: resolvedTitleSize,
                      height: 1.22,
                      fontWeight: FontWeight.w800,
                      color: context.sirati.textPrimary,
                    ),
                  ),
                  if (hasSubtitle) ...[
                    const SizedBox(height: 4),
                    Text(
                      subtitle!,
                      maxLines: compact ? 1 : 2,
                      overflow: TextOverflow.ellipsis,
                      textAlign: TextAlign.start,
                      style: TextStyle(
                        fontSize: 13.5,
                        height: 1.4,
                        fontWeight: FontWeight.w500,
                        color: context.sirati.textSecondary,
                      ),
                    ),
                  ],
                  if (hasStatus) ...[
                    const SizedBox(height: 6),
                    _StatusDotChip(
                      label: status!,
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 10),
            NotificationBellButton(
              unreadCount: unreadCount,
              onTap: onNotifications,
              english: english,
            ),
          ],
        );
      },
    );
  }
}

String _initialFromTitle(String title) {
  final trimmed = title.trim();
  if (trimmed.isEmpty) {
    return 'S';
  }
  // Strip greeting prefixes and BiDi marks before taking the initial.
  final cleaned = trimmed
      .replaceFirst(RegExp(r'^(أهلاً،\s*|Hello,\s*)'), '')
      .replaceAll(RegExp(r'[\u200E\u200F\u202A-\u202E]'), '')
      .trim();
  final source = cleaned.isNotEmpty ? cleaned : trimmed;
  return source.substring(0, 1).toUpperCase();
}

/// Circular notification button with an unread badge — actionable on every tab.
class NotificationBellButton extends StatelessWidget {
  final int unreadCount;
  final VoidCallback onTap;
  final bool english;

  const NotificationBellButton({
    super.key,
    required this.unreadCount,
    required this.onTap,
    this.english = true,
  });

  @override
  Widget build(BuildContext context) {
    final baseLabel = english ? 'Notifications' : 'الإشعارات';
    final semanticsLabel = unreadCount > 0
        ? (english
            ? 'Notifications, $unreadCount unread'
            : 'الإشعارات، $unreadCount غير مقروء')
        : baseLabel;

    return Tooltip(
      message: baseLabel,
      child: Semantics(
        button: true,
        label: semanticsLabel,
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            Material(
              color: context.sirati.surface,
              shape: const CircleBorder(),
              child: InkWell(
                onTap: onTap,
                customBorder: const CircleBorder(),
                child: Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(color: context.sirati.border),
                  ),
                  child: Icon(Icons.notifications_none_rounded,
                      color: context.sirati.textPrimary, size: 21),
                ),
              ),
            ),
            if (unreadCount > 0)
              PositionedDirectional(
                top: -2,
                end: -2,
                child: ExcludeSemantics(
                  child: Container(
                    constraints:
                        const BoxConstraints(minWidth: 16, minHeight: 16),
                    padding:
                        const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: context.sirati.error,
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      unreadCount > 9 ? '9+' : '$unreadCount',
                      // Gated pair: onError on error.
                      style: TextStyle(
                        color: context.sirati.onError,
                        fontSize: 9,
                        fontWeight: FontWeight.w800,
                        height: 1.1,
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// Consistent profile avatar used in every tab header.
class ProfileAvatar extends StatelessWidget {
  final String? label;
  final IconData? icon;
  final VoidCallback? onTap;
  final double size;
  final bool english;
  final String? semanticLabel;

  const ProfileAvatar({
    super.key,
    this.label,
    this.icon,
    this.onTap,
    this.size = 46,
    this.english = true,
    this.semanticLabel,
  });

  @override
  Widget build(BuildContext context) {
    final Widget content;
    if (icon != null) {
      content = Icon(
        icon,
        size: size * 0.52,
        color: context.sirati.onBrandSurface,
      );
    } else {
      content = FittedBox(
        fit: BoxFit.scaleDown,
        child: Padding(
          padding: EdgeInsets.all(size * 0.12),
          child: Text(
            label ?? 'S',
            style: TextStyle(
              fontSize: size * 0.35,
              fontWeight: FontWeight.w800,
              color: context.sirati.onBrandSurface,
            ),
          ),
        ),
      );
    }

    final face = ExcludeSemantics(
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            // Gated pair across the whole gradient: onBrandSurface on both
            // brandSurface and brandSurfaceDeep is >= 4.5:1.
            colors: [
              context.sirati.brandSurface,
              context.sirati.brandSurfaceDeep,
            ],
          ),
        ),
        child: Center(child: content),
      ),
    );

    if (onTap == null) {
      return Semantics(
        label: semanticLabel ?? (english ? 'Profile' : 'الملف الشخصي'),
        child: face,
      );
    }

    final actionLabel = semanticLabel ?? (english ? 'Settings' : 'الإعدادات');
    return Tooltip(
      message: actionLabel,
      child: Semantics(
        button: true,
        label: actionLabel,
        child: PressScale(
          child: Material(
            type: MaterialType.transparency,
            shape: const CircleBorder(),
            child: InkWell(
              onTap: onTap,
              customBorder: const CircleBorder(),
              child: face,
            ),
          ),
        ),
      ),
    );
  }
}

class _StatusDotChip extends StatelessWidget {
  final String label;

  const _StatusDotChip({required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: context.sirati.amberLight,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 6,
            height: 6,
            decoration: BoxDecoration(
              color: context.sirati.amberAccent,
              shape: BoxShape.circle,
            ),
          ),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              color: context.sirati.amber,
            ),
          ),
        ],
      ),
    );
  }
}
