<?php

return [
    'default_renderer' => 'classic_rtl',

    // Single trusted layout. Every template record renders through this view;
    // records differ only by the style settings stored in the database.
    'renderers' => [
        'classic_rtl' => 'generated-cvs.templates.ats-classic-professional',
    ],

    /*
     * Default look of the CV layout. Admins override any key per template via
     * color_tokens (primary/accent) and config_json.style in the admin panel.
     */
    'style_defaults' => [
        'name_color' => '#1f3864',
        'heading_color' => '#1f3864',
        'accent_color' => '#2e4a7d',
        'text_color' => '#111111',
        'muted_color' => '#555555',
        'rule_color' => '#1f3864',
        'divider_color' => '#bfbfbf',
        'font_size' => 10,
        'name_size' => 21,
        'section_title_size' => 11,
        'uppercase_name' => true,
        'uppercase_section_titles' => true,
        'show_contact_separator' => true,
    ],

    // English section titles used by the layout; Arabic titles come from the
    // CV document. Admins override both per template in config_json.section_titles.
    'section_titles_en' => [
        'summary' => 'Professional Summary',
        'skills' => 'Core Competencies',
        'experience' => 'Professional Experience',
        'education' => 'Education',
        'certifications' => 'Certifications',
        'projects' => 'Projects',
        'languages' => 'Languages',
    ],

    'section_order' => ['summary', 'skills', 'experience', 'education', 'certifications', 'projects', 'custom_sections', 'languages'],

    'fallback_template' => [
        'name_ar' => 'كلاسيكي',
        'name_en' => 'Classic',
        'slug' => 'classic-rtl',
        'renderer_key' => 'classic_rtl',
        'language_direction' => 'rtl',
        'supported_languages' => ['ar', 'en'],
        'supported_sections' => ['summary', 'skills', 'experience', 'education', 'certifications'],
        'color_tokens' => [
            'primary' => '#1f3864',
            'accent' => '#2e4a7d',
        ],
        'config_json' => [],
    ],
];
