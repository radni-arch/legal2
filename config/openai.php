<?php

return [
    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORG'),
    'project' => env('OPENAI_PROJECT'),
    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),

    // Default model hints for convenience
    'models' => [
        'responses' => env('OPENAI_RESPONSES_MODEL', 'gpt-4.1-mini'),
        'chat' => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
        'embeddings' => env('OPENAI_EMBEDDINGS_MODEL', 'text-embedding-3-small'),
        'embeddings_analysis' => env('OPENAI_EMBEDDINGS_ANALYSIS_MODEL', 'text-embedding-3-large'), // For keyword extraction
        'image' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
        'stt' => env('OPENAI_STT_MODEL', 'whisper-1'),
        'tts' => env('OPENAI_TTS_MODEL', 'gpt-4o-mini-tts'),
    ],

    // HTTP options
    'timeout' => env('OPENAI_TIMEOUT', 60),
    'responses_timeout' => env('OPENAI_RESPONSES_TIMEOUT', 240),
    'connect_timeout' => env('OPENAI_CONNECT_TIMEOUT', 10),

    // Retry configuration
    'retry' => [
        'times' => env('OPENAI_RETRY_TIMES', 3),
        'sleep_ms' => env('OPENAI_RETRY_SLEEP_MS', 200),
        'exponential_backoff' => env('OPENAI_RETRY_EXPONENTIAL_BACKOFF', true),
        'jitter' => env('OPENAI_RETRY_JITTER', true),
        'when' => [
            // Retry on connection errors
            'connection_exception' => true,
            // Retry on specific HTTP status codes
            'status_codes' => [408, 429, 500, 502, 503, 504],
        ],
    ],

    // Circuit breaker configuration
    'circuit_breaker' => [
        'enabled' => env('OPENAI_CIRCUIT_BREAKER_ENABLED', true),
        'failure_threshold' => env('OPENAI_CIRCUIT_BREAKER_FAILURE_THRESHOLD', 5),
        'success_threshold' => env('OPENAI_CIRCUIT_BREAKER_SUCCESS_THRESHOLD', 2),
        'timeout' => env('OPENAI_CIRCUIT_BREAKER_TIMEOUT', 60),
        'retry_after' => env('OPENAI_CIRCUIT_BREAKER_RETRY_AFTER', 30),
    ],

    // Rate limiting
    'rate_limit' => [
        'enabled' => env('OPENAI_RATE_LIMIT_ENABLED', true),
        'requests_per_minute' => env('OPENAI_RATE_LIMIT_RPM', 60),
        'tokens_per_minute' => env('OPENAI_RATE_LIMIT_TPM', 90000),
    ],
];
