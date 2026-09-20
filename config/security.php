<?php

return [
    'captcha' => [
        'ttl_seconds' => (int) env('CAPTCHA_TTL_SECONDS', 600),
        'reservation_seconds' => (int) env('CAPTCHA_RESERVATION_SECONDS', 30),
        'lock_seconds' => (int) env('SECURITY_LOCK_SECONDS', 10),
        'lock_wait_seconds' => (int) env('SECURITY_LOCK_WAIT_SECONDS', 5),
    ],

    // Route throttle remains burst protection. These are separate daily
    // product-policy limits and roll over at midnight in APP_TIMEZONE.
    'submission' => [
        'ip_daily_limit' => (int) env('SUBMISSION_IP_DAILY_LIMIT', 30),
        'account_daily_limit' => (int) env('SUBMISSION_ACCOUNT_DAILY_LIMIT', 100),
    ],
];
