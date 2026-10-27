<?php

return [
    // Directory that holds the bundled app_*.arb files (copied from flutter_app/lib/l10n).
    'arb_path' => resource_path('i18n'),

    // Locale of the template ARB (flutter_app/l10n.yaml: template-arb-file). It defines the key catalog.
    'template_locale' => 'ar',

    // Mirrors flutter_app/l10n.yaml `use-escaping`. While false (Flutter's default), an apostrophe is a
    // literal character and every `{` / `}` is ICU syntax. Keep both sides in sync.
    'icu_escaping' => (bool) env('I18N_ICU_ESCAPING', false),

    // How long a built strings payload stays cached. Cache keys include the language version, so a
    // publish switches to a fresh entry at once; this TTL only bounds how long old versions linger.
    'cache_ttl_seconds' => 86400,

    // Max-age for the public strings endpoint.
    'strings_max_age' => 300,

    // Longest value an admin may save for one key.
    'max_value_length' => 10000,
];
