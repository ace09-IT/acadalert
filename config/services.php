<?php

return [


    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

    'aistudio' => [
        'api_key' => env('AI_STUDIO_API_KEY'),
        'api_url' => env('AI_STUDIO_API_URL'),
        'timeout' => (int) env('AI_STUDIO_TIMEOUT', 30),
        'retry_limit' => (int) env('AI_STUDIO_RETRY_LIMIT', 2),

        'batch_size' => (int) env('AI_STUDIO_BATCH_SIZE', 5),
        'batch_delay_seconds' => (int) env('AI_STUDIO_BATCH_DELAY_SECONDS', 4),

        'batch_retry_limit' => (int) env('AI_STUDIO_BATCH_RETRY_LIMIT', 2),

        'cache_ttl_minutes' => (int) env('AI_STUDIO_CACHE_TTL_MINUTES', 60),
        'rate_limit_backoff_seconds' => (int) env('AI_STUDIO_RATE_LIMIT_BACKOFF_SECONDS', 5),

        // Per-request execution-time ceiling for the long, synchronous AI
        // endpoints (block risk scoring, block recommendation generation). A
        // fresh PHP install defaults to 30s, which a 20-student block blows
        // through; the controller raises it with set_time_limit() scoped to
        // that request only. 0 disables the override.
        'max_execution_seconds' => (int) env('AI_STUDIO_MAX_EXECUTION_SECONDS', 300),
    ],


    'mock_api' => [
        'base_url' => env('MOCK_API_BASE_URL', 'http://localhost:3000'),

        // Writable master server (mirror-write target).
        'core_url' => env('MOCK_API_CORE_URL', 'http://localhost:3001'),

        'timeout' => (int) env('MOCK_API_TIMEOUT', 15),
        'retry_times' => (int) env('MOCK_API_RETRY_TIMES', 3),
        'retry_sleep_ms' => (int) env('MOCK_API_RETRY_SLEEP_MS', 150),

        // Short-TTL memo of API collections; keeps a single page render from
        // issuing the same GET repeatedly. 0 disables the cross-request cache.
        'cache_ttl_seconds' => (int) env('MOCK_API_CACHE_TTL_SECONDS', 60),

        'concurrency' => (int) env('MOCK_API_CONCURRENCY', 10),

        // Student ids per attendance request. `attendance` holds 158,624 rows
        // (~104 per student), so the whole table is never fetched.
        'attendance_chunk' => (int) env('MOCK_API_ATTENDANCE_CHUNK', 25),

        // Global kill-switch for the mirror-write path.
        'writes_enabled' => filter_var(env('MOCK_API_WRITES_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

        // Which collections live on the writable CORE server. Anything absent
        // from this list is served by the read-only BULK server.
        'core_resources' => [
            'departments',
            'programs',
            'year_levels',
            'blocks',
            'subjects',
            'students',
            'users',
            'academic_heads',
            'counselors',
            'payments',
            'payment_history',
            'risk_thresholds',
            'school_years',
        ],
    ],

];
