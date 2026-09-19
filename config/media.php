<?php

return [
    'transcription' => [
        // whisper-1 accepts requests up to 25 MiB. Keep one MiB of headroom
        // for the multipart request envelope sent to the provider.
        'max_bytes' => (int) env('MEDIA_TRANSCRIPTION_MAX_BYTES', 24 * 1024 * 1024),
        'minimum_bytes' => 1024,
        'max_duration_seconds' => (int) env('MEDIA_TRANSCRIPTION_MAX_DURATION', 300),

        'upload_types' => [
            'mp4' => ['video/mp4'],
            'mpeg' => ['video/mpeg'],
            'webm' => ['video/webm'],
        ],

        'remote_types' => [
            'flac' => ['audio/flac', 'audio/x-flac'],
            'mp3' => ['audio/mpeg'],
            'mp4' => ['audio/mp4', 'video/mp4'],
            'mpeg' => ['audio/mpeg', 'video/mpeg'],
            'mpga' => ['audio/mpeg'],
            'm4a' => ['audio/mp4', 'audio/x-m4a'],
            'ogg' => ['application/ogg', 'audio/ogg', 'video/ogg'],
            'wav' => ['audio/wav', 'audio/wave', 'audio/x-wav'],
            'webm' => ['audio/webm', 'video/webm'],
        ],

        'blocked_platform_hosts' => [
            'facebook.com',
            'fb.watch',
            'instagram.com',
            'tiktok.com',
            'youtube.com',
            'youtu.be',
        ],

        'download_timeout_seconds' => (int) env('MEDIA_DOWNLOAD_TIMEOUT', 20),
        'provider_timeout_seconds' => (int) env('MEDIA_TRANSCRIPTION_TIMEOUT', 90),
        'job_timeout_seconds' => (int) env('MEDIA_JOB_TIMEOUT', 150),
        'worker_timeout_seconds' => (int) env('MEDIA_WORKER_TIMEOUT', 180),
        'retry_after_seconds' => (int) env('MEDIA_QUEUE_RETRY_AFTER', 1800),

        'ffprobe_binary' => env('FFPROBE_BINARY', 'ffprobe'),
    ],
];
