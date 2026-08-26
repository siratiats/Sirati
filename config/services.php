<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 30),
        'connect_timeout' => (int) env('OPENAI_CONNECT_TIMEOUT', 5),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        // Haiku 4.5 ($1/$5 per MTok) is the closest tier to gpt-4.1-mini for the
        // bake-off. Sonnet 4.5 is a superseded generation at $3/$15 and would
        // measure a model you would not ship on cost grounds anyway.
        'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        'timeout' => (int) env('ANTHROPIC_TIMEOUT', 30),
        'connect_timeout' => (int) env('ANTHROPIC_CONNECT_TIMEOUT', 5),
    ],

    'deepinfra' => [
        'api_key' => env('DEEPINFRA_API_KEY'),
        'model' => env('DEEPINFRA_MODEL', 'Qwen/Qwen2.5-72B-Instruct'),
        'base_url' => env('DEEPINFRA_BASE_URL', 'https://api.deepinfra.com/v1/openai'),
        'timeout' => (int) env('DEEPINFRA_TIMEOUT', 45),
        'connect_timeout' => (int) env('DEEPINFRA_CONNECT_TIMEOUT', 5),
    ],

    'cv_ai' => [
        // Production default is openai. claude is for bake-off / explicit opt-in only.
        'provider' => env('CV_AI_PROVIDER', 'openai'),
        'queue' => env('CV_AI_QUEUE', 'default'),
        'response_cache_enabled' => filter_var(
            env('CV_AI_RESPONSE_CACHE_ENABLED', true),
            FILTER_VALIDATE_BOOL,
        ),
        'response_cache_ttl' => [
            // analysisAdvice / generateCv: 7 days; enhanceJobDescription: 24 hours
            'analysis_advice' => (int) env('CV_AI_CACHE_TTL_ANALYSIS', 60 * 60 * 24 * 7),
            'generate_cv' => (int) env('CV_AI_CACHE_TTL_GENERATE', 60 * 60 * 24 * 7),
            'enhance_job_description' => (int) env('CV_AI_CACHE_TTL_ENHANCE', 60 * 60 * 24),
            'enhance_cv_field' => (int) env('CV_AI_CACHE_TTL_ENHANCE_FIELD', 60 * 60 * 24),
        ],
    ],

    'admin' => [
        'access_token' => env('ADMIN_ACCESS_TOKEN'),
        'emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADMIN_EMAILS', ''))))),
    ],

    'jobs_sheet' => [
        'csv_url' => env('JOBS_SHEET_CSV_URL'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
