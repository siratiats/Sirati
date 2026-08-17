<?php

return [
    // Keep automation off until migrations, scheduler, queue worker, and a
    // small internal-user rollout have all been verified.
    'enabled' => (bool) env('SMART_NOTIFICATIONS_ENABLED', false),
    'default_language' => env('SMART_NOTIFICATIONS_DEFAULT_LANGUAGE', 'ar'),
    'default_timezone_offset_minutes' => (int) env('SMART_NOTIFICATIONS_TIMEZONE_OFFSET_MINUTES', 180),
    'default_preferred_time' => env('SMART_NOTIFICATIONS_PREFERRED_TIME', '18:30'),
    'default_quiet_hours_start' => env('SMART_NOTIFICATIONS_QUIET_START', '21:00'),
    'default_quiet_hours_end' => env('SMART_NOTIFICATIONS_QUIET_END', '09:00'),
    'default_max_per_week' => (int) env('SMART_NOTIFICATIONS_MAX_PER_WEEK', 4),
    'recent_activity_hours' => (int) env('SMART_NOTIFICATIONS_RECENT_ACTIVITY_HOURS', 3),
    'minimum_gap_hours' => (int) env('SMART_NOTIFICATIONS_MINIMUM_GAP_HOURS', 24),
    'delivery_window_minutes' => (int) env('SMART_NOTIFICATIONS_DELIVERY_WINDOW_MINUTES', 30),
    'rule_cooldowns_days' => [
        'first_analysis' => 5,
        'low_ats_score' => 3,
        'analysis_to_cv' => 4,
        'stale_cv' => 7,
        'matching_job' => 2,
        'relevant_education' => 4,
        'daily_tip' => 2,
    ],
    // Keyword-hit thresholds for job/education targeting (see DailyNotificationCandidateService).
    'job_match_min_score' => (int) env('SMART_NOTIFICATIONS_JOB_MATCH_MIN_SCORE', 2),
    'education_match_min_score' => (int) env('SMART_NOTIFICATIONS_EDUCATION_MATCH_MIN_SCORE', 1),
];
