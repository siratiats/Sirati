<?php

return [
    'default_renderer' => 'classic_rtl',

    'renderers' => [
        'classic_rtl' => 'generated-cvs.pdf',
        'modern_rtl' => 'generated-cvs.templates.modern-rtl',
    ],

    'fallback_template' => [
        'name_ar' => 'كلاسيكي',
        'name_en' => 'Classic',
        'slug' => 'classic-rtl',
        'renderer_key' => 'classic_rtl',
        'language_direction' => 'rtl',
        'supported_languages' => ['ar', 'en'],
        'supported_sections' => ['summary', 'skills', 'experience', 'education', 'certifications'],
        'color_tokens' => [
            'primary' => '#1f2937',
            'accent' => '#2563eb',
        ],
        'config_json' => [],
    ],
];
