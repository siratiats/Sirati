/// Persisted identifiers for skill proficiency, category, and language level.
///
/// These Arabic strings are the on-disk schema (`LocalizedText.ar`). Changing
/// them is a document-schema change and belongs in `cv_schema_migrator.dart` — not an
/// i18n catalog swap. Editors must keep writing these values and resolve
/// display labels through `AppLocalizations`. Empty `en` on these keys is
/// backfilled by [CvSchemaMigrator] from [english].
abstract final class SkillStorageKeys {
  static const beginner = 'مبتدئ';
  static const intermediate = 'متوسط';
  static const advanced = 'متقدم';
  static const expert = 'خبير';

  static const technical = 'مهارات تقنية';
  static const soft = 'مهارات قيادية وشخصية';
  static const tools = 'أدوات وبرمجيات';
  static const management = 'إدارة وتخطيط';
  static const general = 'مهارات عامة';

  static const levels = <String>[
    beginner,
    intermediate,
    advanced,
    expert,
  ];

  static const categories = <String>[
    technical,
    soft,
    tools,
    management,
  ];

  static const all = <String>{
    beginner,
    intermediate,
    advanced,
    expert,
    technical,
    soft,
    tools,
    management,
    general,
  };

  /// English export strings for [stored] schema keys. Independent of UI locale
  /// so an Arabic-session write still produces a bilingual document.
  static const english = <String, String>{
    beginner: 'Beginner',
    intermediate: 'Intermediate',
    advanced: 'Advanced',
    expert: 'Expert',
    technical: 'Technical skills',
    soft: 'Interpersonal skills',
    tools: 'Tools and software',
    management: 'Management and planning',
    general: 'General skills',
  };

  static String englishFor(String stored) => english[stored] ?? '';
}

/// Persisted identifiers for language proficiency. Same schema rule as
/// [SkillStorageKeys]: display is localized, storage is not.
abstract final class LanguageLevelStorageKeys {
  static const native = 'اللغة الأم (Native)';
  static const fluent = 'طليق (C2 / Fluent)';
  static const c1 = 'مهني متقدم (C1)';
  static const b2 = 'متوسط (B2)';
  static const a2 = 'أساسي (A2)';

  static const values = <String>[native, fluent, c1, b2, a2];

  static const all = <String>{native, fluent, c1, b2, a2};

  static const english = <String, String>{
    native: 'Native',
    fluent: 'Fluent',
    c1: 'Advanced professional (C1)',
    b2: 'Intermediate (B2)',
    a2: 'Elementary (A2)',
  };

  static String englishFor(String stored) => english[stored] ?? '';
}
