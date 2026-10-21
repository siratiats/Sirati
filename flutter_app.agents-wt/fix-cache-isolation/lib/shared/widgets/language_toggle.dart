import 'package:flutter/material.dart';

import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/theme/app_theme.dart';

class LanguageToggle extends StatelessWidget {
  const LanguageToggle({super.key});

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    final l10n = AppLocalizations.of(context);
    final tooltip = english ? l10n.switchToArabic : l10n.switchToEnglish;

    return Tooltip(
      message: tooltip,
      child: Material(
        color: context.sirati.surfaceLow,
        shape: const CircleBorder(),
        child: InkWell(
          onTap: () {
            AppLocale.toggle(context);
          },
          customBorder: const CircleBorder(),
          child: SizedBox(
            width: 42,
            height: 42,
            child: Center(
              child: FittedBox(
                fit: BoxFit.scaleDown,
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4),
                  child: Text(
                    english ? 'AR' : 'EN',
                    style: TextStyle(
                      // `primary` is a fill; ink is the AA text tone.
                      color: context.sirati.primaryInk,
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
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
}
