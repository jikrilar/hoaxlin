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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Internal BERT Inference Service
    |--------------------------------------------------------------------------
    |
    | The FastAPI service hosting the fine-tuned IndoBERT classifier. The HTTP
    | timeout must stay below the job timeout, which must stay below the queue
    | retry_after, otherwise a job can be reclaimed while it is still running.
    |
    */

    'bert' => [
        'url' => env('BERT_SERVICE_URL', 'http://127.0.0.1:8001'),
        'internal_token' => env('BERT_SERVICE_TOKEN'),
        'connect_timeout' => (int) env('BERT_SERVICE_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('BERT_SERVICE_TIMEOUT', 30),
        'tries' => (int) env('BERT_SERVICE_TRIES', 5),
        'backoff' => [5, 15, 45, 120, 300],
        'breaker' => [
            'failures' => (int) env('BERT_BREAKER_FAILURES', 5),
            'window' => (int) env('BERT_BREAKER_WINDOW', 60),
            'cooldown' => (int) env('BERT_BREAKER_COOLDOWN', 30),
        ],
        'cache_ttl' => (int) env('BERT_CACHE_TTL', 604800),
        'confidence_threshold' => (float) env('BERT_CONFIDENCE_THRESHOLD', 0.65),
        'model_version' => env('BERT_MODEL_VERSION', 'unknown'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI Support Services
    |--------------------------------------------------------------------------
    |
    | Used only for OCR, transcription, article cleanup and narrative phrasing.
    | Classification always remains with BERT. The monthly quota caps spend on
    | a publicly reachable endpoint.
    |
    */

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
        'chat_model' => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
        'translation_model' => env('OPENAI_TRANSLATION_MODEL', env('OPENAI_CHAT_MODEL', 'gpt-4o-mini')),
        'translation_prompt_version' => env('OPENAI_TRANSLATION_PROMPT_VERSION', '1.0'),
        'translation_max_output_tokens' => (int) env('OPENAI_TRANSLATION_MAX_OUTPUT_TOKENS', 4096),
        'vision_model' => env('OPENAI_VISION_MODEL', 'gpt-4o-mini'),
        'transcribe_model' => env('OPENAI_TRANSCRIBE_MODEL', 'whisper-1'),
        'connect_timeout' => (int) env('OPENAI_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('OPENAI_TIMEOUT', 45),
        'tries' => (int) env('OPENAI_TRIES', 3),
        'backoff' => [10, 60, 180],
        'breaker' => [
            'failures' => (int) env('OPENAI_BREAKER_FAILURES', 5),
            'window' => (int) env('OPENAI_BREAKER_WINDOW', 60),
            'cooldown' => (int) env('OPENAI_BREAKER_COOLDOWN', 60),
        ],
        'cache_ttl' => (int) env('OPENAI_CACHE_TTL', 2592000),
        'rate_limit_per_minute' => (int) env('OPENAI_RATE_LIMIT_PER_MINUTE', 60),
        'monthly_quota_usd' => (float) env('OPENAI_MONTHLY_QUOTA_USD', 25.0),
        'prompt_version' => env('OPENAI_PROMPT_VERSION', '1.0'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
