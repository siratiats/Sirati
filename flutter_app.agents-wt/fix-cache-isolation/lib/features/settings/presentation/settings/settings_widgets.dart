import 'package:flutter/material.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/auth_session.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/screen_header.dart';

class SettingsSectionLabel extends StatelessWidget {
  const SettingsSectionLabel(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      header: true,
      child: Text(
        text,
        textAlign: TextAlign.start,
        style: AppTextStyles.labelMd(context.sirati).copyWith(
          color: context.sirati.textHint,
          letterSpacing: 0.2,
        ),
      ),
    );
  }
}

/// Display-only profile summary (SIRATI-61 & SIRATI-63).
class ProfileSummaryCard extends StatelessWidget {
  const ProfileSummaryCard({super.key, required this.user});

  final AuthUser? user;

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    final l10n = AppLocalizations.of(context);
    final colors = context.sirati;
    final name = (user?.name.trim().isNotEmpty ?? false)
        ? user!.name
        : l10n.userFallback;
    final email = user?.email ?? '';
    final jobTitle = user?.displayJobTitle(english: english);

    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: colors.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: colors.border),
        boxShadow: colors.softShadow,
      ),
      child: Row(
        children: [
          ProfileAvatar(
            label: BidiText.avatarInitial(name),
            size: 64,
            english: english,
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  textAlign: TextAlign.start,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: AppTextStyles.titleLg(colors),
                ),
                if (jobTitle != null) ...[
                  const SizedBox(height: 3),
                  Text(
                    jobTitle,
                    textAlign: TextAlign.start,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextStyles.bodySm(colors).copyWith(
                      color: colors.textSecondary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
                if (email.isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Text(
                    email,
                    textAlign: TextAlign.start,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    textDirection: TextDirection.ltr,
                    style: AppTextStyles.bodySm(colors).copyWith(
                      color: colors.textHint,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// System / Light / Dark segmented selector.
class ThemeModeSelector extends StatelessWidget {
  const ThemeModeSelector({
    super.key,
    required this.mode,
    required this.onChanged,
  });

  final ThemeMode mode;
  final ValueChanged<ThemeMode> onChanged;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final c = context.sirati;
    Widget option(ThemeMode m, String label) {
      final selected = mode == m;
      final radius = BorderRadius.circular(12);
      return Expanded(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 3),
          child: Semantics(
            button: true,
            selected: selected,
            inMutuallyExclusiveGroup: true,
            child: Material(
              color: selected ? c.primaryLight : c.surfaceLow,
              shape: RoundedRectangleBorder(
                borderRadius: radius,
                side: BorderSide(color: selected ? c.primary : c.border),
              ),
              child: InkWell(
                borderRadius: radius,
                onTap: () => onChanged(m),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(minHeight: 44),
                  child: Center(
                    child: Text(
                      label,
                      style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                        // primaryInk / primaryLight is the gated chip pair.
                        color: selected ? c.primaryInk : c.textSecondary,
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      );
    }

    return Row(
      children: [
        option(ThemeMode.system, l10n.themeSystem),
        option(ThemeMode.light, l10n.themeLight),
        option(ThemeMode.dark, l10n.themeDark),
      ],
    );
  }
}
