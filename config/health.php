<?php

return [
    'queue' => [
        'enabled' => filter_var(env('HEALTH_QUEUE_CHECK_ENABLED', true), FILTER_VALIDATE_BOOL),
        'connection' => env('HEALTH_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
        'heartbeat_cache_store' => env('HEALTH_QUEUE_HEARTBEAT_CACHE_STORE'),
        'heartbeat_key' => env('HEALTH_QUEUE_HEARTBEAT_KEY', 'health:queue-worker-heartbeat'),
        'max_age_seconds' => (int) env('HEALTH_QUEUE_MAX_AGE_SECONDS', 120),
    ],
];
