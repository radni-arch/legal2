<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Proactive Threshold
    |--------------------------------------------------------------------------
    |
    | When a key reaches this percentage of its quota, the rotator will
    | proactively switch to another key. Value between 0 and 1.
    | Default: 0.8 (80%)
    |
    */
    'proactive_threshold' => env('API_ROTATOR_THRESHOLD', 0.8),

    /*
    |--------------------------------------------------------------------------
    | Maximum Retries
    |--------------------------------------------------------------------------
    |
    | Maximum number of retry attempts when an API call fails due to
    | rate limiting or transient errors.
    |
    */
    'max_retries' => env('API_ROTATOR_MAX_RETRIES', 3),

    /*
    |--------------------------------------------------------------------------
    | Base Retry Delay
    |--------------------------------------------------------------------------
    |
    | Base delay in seconds between retries. Actual delay may be
    | multiplied based on exponential backoff.
    |
    */
    'base_retry_delay' => env('API_ROTATOR_RETRY_DELAY', 5),

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    |
    | Default rate limits and model configurations for each provider.
    | These are used as fallbacks when the database doesn't have specific limits.
    |
    */
    'providers' => [
        'gemini' => [
            'models' => [
                'gemini-2.5-flash-preview-05-20' => [
                    'rpm' => 10,
                    'rpd' => 250,
                    'tpm' => 250000,
                    'supports_pdf' => true,
                    'supports_vision' => true,
                ],
                'gemini-2.0-flash-exp' => [
                    'rpm' => 10,
                    'rpd' => 100,
                    'tpm' => 250000,
                    'supports_pdf' => true,
                    'supports_vision' => true,
                ],
            ],
            'reset_timezone' => 'America/Los_Angeles',
        ],

        'mistral' => [
            'models' => [
                'mistral-small-latest' => [
                    'rpm' => 60,
                    'rpd' => 1000,
                    'tpm' => 500000,
                    'supports_pdf' => false,
                    'supports_vision' => true,
                ],
            ],
            'reset_timezone' => 'UTC',
        ],

        'openrouter' => [
            'models' => [
                'google/gemini-2.0-flash-exp:free' => [
                    'rpm' => 20,
                    'rpd' => 50,
                    'tpm' => 0,
                    'supports_pdf' => true,
                    'supports_vision' => true,
                ],
            ],
            'reset_timezone' => 'UTC',
        ],
    ],
];
