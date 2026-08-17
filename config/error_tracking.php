<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Candidate data that must never leave Sirati
    |--------------------------------------------------------------------------
    |
    | Sentry is configured not to collect request bodies at all. This list is
    | also applied recursively in before_send as a second line of defence.
    |
    */
    'scrub_fields' => [
        'resume_text',
        'experience_input',
        'education_input',
        'summary_input',
        'skills_input',
        'certifications_input',
        'draft',
        'full_name',
        'email',
        'phone',
        'linkedin',
        'location',
    ],

    'protected_path_fragments' => [
        '/cv-analyses',
        '/generated-cvs',
        '/analyses',
        '/mobile/my-cvs',
        '/mobile/cv-templates',
    ],
];
