import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/network/api_config.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/routing/app_routes.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/settings/application/settings_controllers.dart';
import 'package:sirati/features/settings/presentation/change_password_screen.dart';
import 'package:sirati/features/settings/presentation/delete_account_screen.dart';
import 'package:sirati/features/settings/presentation/privacy_policy_screen.dart';
import 'package:sirati/features/settings/presentation/profile_screen.dart';
import 'package:sirati/features/settings/presentation/settings/settings_widgets.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/services/auth_api_service_provider.dart';
import 'package:sirati/shared/services/session_cache.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/theme/app_theme_controller.dart';
import 'package:sirati/shared/widgets/app_list_tile.dart';
import 'package:sirati/shared/widgets/app_snack_bar.dart';
import 'package:sirati/shared/widgets/empty_state.dart';
import 'package:sirati/shared/widgets/loading/app_skeleton.dart';
import 'package:url_launcher/url_launcher.dart';

class SettingsScreen extends ConsumerStatefulWidget {
  const SettingsScreen({super.key});

  @override
  ConsumerState<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends ConsumerState<SettingsScreen> {
  bool _loggingOut = false;

  void _push(Widget screen) {
    unawaited(Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => screen),
    ));
  }

  Future<void> _editProfile() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const ProfileScreen()),
    );
    if (mounted) ref.invalidate(settingsUserProvider);
  }

  Future<void> _toggleNotifications(bool value) async {
    final ok = await ref
        .read(notificationPreferenceProvider.notifier)
        .setEnabled(value);
    if (!ok && mounted) {
      AppSnackBar.warning(
        context,
        AppLocalizations.of(context).notificationPrefUpdateFailed,
      );
    }
  }

  /// Opens the email client addressed to support (SIRATI-62).
  Future<void> _contactUs() async {
    final l10n = AppLocalizations.of(context);
    final uri = Uri(
      scheme: 'mailto',
      path: ApiConfig.supportEmail,
      queryParameters: {'subject': l10n.contactUsEmailSubject},
    );
    var launched = false;
    try {
      launched = await launchUrl(uri);
    } catch (_) {}
    if (!launched && mounted) {
      AppSnackBar.warning(
        context,
        l10n.contactUsFallback(ApiConfig.supportEmail),
      );
    }
  }

  Future<void> _logout() async {
    if (_loggingOut) return;
    final l10n = AppLocalizations.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(l10n.logOutConfirmTitle),
        content: Text(l10n.logOutConfirmBody),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text(l10n.cancel),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: TextButton.styleFrom(foregroundColor: context.sirati.error),
            child: Text(l10n.logOut),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _loggingOut = true);
    try {
      // Clears the token and local data first; server cleanup runs after
      // in the background. A throw here means the device is NOT signed
      // out, so the user must not be sent to Login as if it were.
      await ref.read(authApiServiceProvider).logout();
    } catch (_) {
      if (!mounted) return;
      setState(() => _loggingOut = false);
      AppSnackBar.error(context, l10n.logOutFailed);
      return;
    }
    if (!mounted) return;
    unawaited(Navigator.of(context).pushNamedAndRemoveUntil(
      AppRoutes.login,
      (route) => false,
    ));
  }

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    final l10n = AppLocalizations.of(context);
    final userAsync = ref.watch(settingsUserProvider);
    final notifications = ref.watch(notificationPreferenceProvider);
    final cachedUser = SessionCache.instance.user.value;
    final linkStyle = TextStyle(
      color: context.sirati.primaryInk,
      fontWeight: FontWeight.w700,
    );

    Widget body;
    if (userAsync.isLoading && !userAsync.hasValue && cachedUser == null) {
      body = const Padding(
        padding: EdgeInsets.all(AppSpacing.lg),
        child: AppSkeletonScope(
          child: Column(
            children: [
              AppSkeleton(width: 72, height: 72, radius: 36),
              SizedBox(height: AppSpacing.md),
              AppSkeleton(height: 18),
              SizedBox(height: AppSpacing.xs),
              AppSkeleton(width: 180, height: 14),
              SizedBox(height: AppSpacing.xl),
              AppSkeleton(height: 56, radius: 16),
              SizedBox(height: AppSpacing.sm),
              AppSkeleton(height: 56, radius: 16),
            ],
          ),
        ),
      );
    } else if (userAsync.hasError &&
        !userAsync.hasValue &&
        cachedUser == null) {
      final error = userAsync.error;
      body = AppErrorState(
        english: english,
        message: error is ApiException
            ? error.displayMessage
            : l10n.couldNotLoadProfile,
        onRetry: () => ref.invalidate(settingsUserProvider),
        exception: error is ApiException ? error : null,
      );
    } else {
      final user = userAsync.value ?? cachedUser;
      body = ListView(
        padding: const EdgeInsets.fromLTRB(
            AppSpacing.lg, AppSpacing.md, AppSpacing.lg, AppSpacing.xxl),
        children: [
          ProfileSummaryCard(user: user),
          const SizedBox(height: AppSpacing.xl),
          SettingsSectionLabel(l10n.accountSection),
          const SizedBox(height: AppSpacing.sm),
          AppListTile(
            leadingIcon: Icons.person_outline_rounded,
            title: l10n.editProfile,
            onTap: () => unawaited(_editProfile()),
          ),
          const SizedBox(height: AppSpacing.sm),
          AppListTile(
            leadingIcon: Icons.lock_outline_rounded,
            title: l10n.changePassword,
            onTap: () => _push(const ChangePasswordScreen()),
          ),
          const SizedBox(height: AppSpacing.xl),
          SettingsSectionLabel(l10n.preferencesSection),
          const SizedBox(height: AppSpacing.sm),
          AppListTile(
            leadingIcon: Icons.language_rounded,
            title: l10n.language,
            subtitle: english ? l10n.languageEnglish : l10n.languageArabic,
            showChevron: false,
            trailing: TextButton(
              onPressed: () => unawaited(AppLocale.setLanguage(
                english ? 'ar' : 'en',
              )),
              child: Text(
                english ? l10n.languageArabic : l10n.languageEnglish,
                style: linkStyle,
              ),
            ),
            onTap: () => unawaited(AppLocale.setLanguage(
              english ? 'ar' : 'en',
            )),
          ),
          const SizedBox(height: AppSpacing.sm),
          ValueListenableBuilder<ThemeMode>(
            valueListenable: AppThemeController.themeMode,
            builder: (context, mode, _) => Column(
              children: [
                AppListTile(
                  leadingIcon: Icons.palette_outlined,
                  title: l10n.appearance,
                  subtitle: switch (mode) {
                    ThemeMode.light => l10n.themeLight,
                    ThemeMode.dark => l10n.themeDark,
                    ThemeMode.system => l10n.themeSystem,
                  },
                  showChevron: false,
                  onTap: null,
                ),
                const SizedBox(height: AppSpacing.xs),
                ThemeModeSelector(
                  mode: mode,
                  onChanged: (m) => unawaited(AppThemeController.setMode(m)),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
          AppListTile(
            leadingIcon: Icons.notifications_none_rounded,
            title: l10n.pushNotifications,
            showChevron: false,
            trailing: Switch.adaptive(
              value: notifications.value ?? true,
              // Disabled until the stored value is known, so a quick tap
              // can't be overwritten by the late load.
              onChanged: notifications.hasValue
                  ? (v) => unawaited(_toggleNotifications(v))
                  : null,
            ),
            onTap: notifications.hasValue
                ? () => unawaited(_toggleNotifications(!notifications.value!))
                : null,
          ),
          const SizedBox(height: AppSpacing.xl),
          SettingsSectionLabel(l10n.aboutSection),
          const SizedBox(height: AppSpacing.sm),
          AppListTile(
            leadingIcon: Icons.privacy_tip_outlined,
            title: l10n.privacyPolicy,
            onTap: () => _push(const PrivacyPolicyScreen()),
          ),
          const SizedBox(height: AppSpacing.sm),
          AppListTile(
            leadingIcon: Icons.mail_outline_rounded,
            title: l10n.contactUs,
            subtitle: ApiConfig.supportEmail,
            onTap: () => unawaited(_contactUs()),
          ),
          const SizedBox(height: AppSpacing.sm),
          AppListTile(
            leadingIcon: Icons.info_outline_rounded,
            title: l10n.appVersion,
            subtitle: ApiConfig.appVersion,
            showChevron: false,
            onTap: null,
          ),
          const SizedBox(height: AppSpacing.xl),
          SettingsSectionLabel(l10n.dangerZone),
          const SizedBox(height: AppSpacing.sm),
          AppListTile(
            leadingIcon: Icons.logout_rounded,
            leadingBackground: context.sirati.errorLight,
            leadingIconColor: context.sirati.error,
            title: l10n.logOut,
            titleColor: context.sirati.error,
            showChevron: false,
            onTap: _loggingOut ? null : () => unawaited(_logout()),
          ),
          const SizedBox(height: AppSpacing.lg),
          AppListTile(
            leadingIcon: Icons.delete_forever_rounded,
            leadingBackground: context.sirati.errorLight,
            leadingIconColor: context.sirati.error,
            title: l10n.deleteAccount,
            titleColor: context.sirati.error,
            onTap: () => _push(const DeleteAccountScreen()),
          ),
        ],
      );
    }

    return Scaffold(
      backgroundColor: context.sirati.background,
      appBar: AppBar(title: Text(l10n.settings)),
      body: body,
    );
  }
}
