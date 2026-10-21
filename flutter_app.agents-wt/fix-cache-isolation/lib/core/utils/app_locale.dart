import 'dart:async';
import 'package:flutter/material.dart';

import 'package:sirati/core/network/analytics_service.dart';
import 'package:sirati/core/storage/preference_store.dart';
import 'package:sirati/core/utils/bidi_text.dart';
import 'package:sirati/core/utils/locale_format.dart';

export 'package:sirati/core/utils/bidi_text.dart';
export 'package:sirati/core/utils/locale_format.dart';

/// GCC country codes: Arabic is the product default on these devices.
const gulfCountryCodes = <String>{'SA', 'AE', 'KW', 'QA', 'BH', 'OM'};

class AppLocale {
  static final ValueNotifier<String> languageCode = ValueNotifier<String>('ar');

  static PreferenceStore _prefs = const PreferenceStore();

  /// Load persisted language before [runApp].
  ///
  /// Priority: URL `?lang=` (web preview) → secure storage → device locales
  /// (Arabic / Gulf → `ar`, English → `en`) → Arabic product default.
  static Future<void> bootstrap({
    List<Locale>? deviceLocales,
    PreferenceStore? prefs,
  }) async {
    if (prefs != null) _prefs = prefs;
    final resolved = resolveLanguageCode(
      urlLang: Uri.base.queryParameters['lang'],
      storedLang: await _readStoredLanguage(),
      deviceLocales:
          deviceLocales ?? WidgetsBinding.instance.platformDispatcher.locales,
    );
    languageCode.value = resolved;
  }

  static Future<String?> _readStoredLanguage() async {
    try {
      return await _prefs.readLanguage();
    } catch (_) {
      return null;
    }
  }

  /// Pure locale policy. Tested across arbitrary device locales.
  static String resolveLanguageCode({
    String? urlLang,
    String? storedLang,
    List<Locale> deviceLocales = const [],
  }) {
    if (urlLang == 'en' || urlLang == 'ar') return urlLang!;
    if (storedLang == 'en' || storedLang == 'ar') return storedLang!;

    for (final locale in deviceLocales) {
      if (locale.languageCode.toLowerCase() == 'ar') return 'ar';
      final country = locale.countryCode?.toUpperCase();
      if (country != null && gulfCountryCodes.contains(country)) return 'ar';
    }
    for (final locale in deviceLocales) {
      if (locale.languageCode.toLowerCase() == 'en') return 'en';
    }
    return 'ar';
  }

  static Locale get locale {
    return languageCode.value == 'en'
        ? const Locale('en', 'US')
        : const Locale('ar', 'SA');
  }

  static bool isEnglish(BuildContext context) {
    return Localizations.localeOf(context).languageCode == 'en';
  }

  static bool get isRtl => languageCode.value != 'en';

  static TextDirection direction(BuildContext context) {
    return isEnglish(context) ? TextDirection.ltr : TextDirection.rtl;
  }

  static TextDirection get currentDirection {
    return languageCode.value == 'en' ? TextDirection.ltr : TextDirection.rtl;
  }

  /// Prefer this over left/right — follows ambient [Directionality].
  static const TextAlign textStart = TextAlign.start;

  /// Prefer this over left/right alignment branches.
  static const CrossAxisAlignment crossStart = CrossAxisAlignment.start;

  /// Prefer this over centerLeft/centerRight.
  static const AlignmentGeometry alignCenterStart =
      AlignmentDirectional.centerStart;

  static const AlignmentGeometry alignCenterEnd =
      AlignmentDirectional.centerEnd;

  /// BiDi-safe greeting for header titles.
  static String greeting(String name, BuildContext context) {
    return BidiText.greeting(name, english: isEnglish(context));
  }

  /// BiDi-safe mixed body for AR locale.
  static String mixedBody(String body, BuildContext context) {
    return LocaleFormat.mixedBody(body, english: isEnglish(context));
  }

  static Future<void> setLanguage(String code) async {
    final next = code == 'en' ? 'en' : 'ar';
    final changed = languageCode.value != next;
    languageCode.value = next;
    try {
      await _prefs.saveLanguage(next);
    } catch (_) {
      // Still apply in-memory even if persist fails.
    }
    if (changed) {
      // Fire-and-forget — analytics never blocks locale switch.
      unawaited(AnalyticsService.setAppLanguage(next));
      unawaited(AnalyticsService.logLanguageChanged(lang: next));
    }
  }

  static Future<void> toggle(BuildContext context) async {
    await setLanguage(isEnglish(context) ? 'ar' : 'en');
  }

  @visibleForTesting
  static void bindPreferences(PreferenceStore prefs) {
    _prefs = prefs;
  }

  @visibleForTesting
  static void resetForTest({String language = 'ar'}) {
    languageCode.value = language == 'en' ? 'en' : 'ar';
    _prefs = const PreferenceStore();
  }
}
