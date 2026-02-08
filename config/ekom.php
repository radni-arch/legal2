<?php

/*
|--------------------------------------------------------------------------
| e-Komunikacija API Configuration
|--------------------------------------------------------------------------
|
| Configuration for the e-Komunikacija court communication API.
| Supports test and production environments via EKOM_ENVIRONMENT.
|
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | The e-Komunikacija environment to use. Options: 'test' or 'production'.
    | Test environment connects to e-komunikacija-test.pravosudje.hr
    | Production environment connects to e-komunikacija.pravosudje.hr
    |
    */
    'environment' => env('EKOM_ENVIRONMENT', 'test'),

    /*
    |--------------------------------------------------------------------------
    | Environment URLs
    |--------------------------------------------------------------------------
    |
    | Base URLs for each environment. These are the official e-Komunikacija
    | API endpoints provided by the Croatian Ministry of Justice.
    |
    */
    'environments' => [
        'test' => 'https://e-komunikacija-test.pravosudje.hr',
        'production' => 'https://e-komunikacija.pravosudje.hr',
    ],

    /*
    |--------------------------------------------------------------------------
    | Base URL (Resolved)
    |--------------------------------------------------------------------------
    |
    | The actual base URL to use. If EKOM_BASE_URL is set, it takes precedence.
    | Otherwise, the URL is resolved from the environment setting.
    |
    | Priority:
    | 1. EKOM_BASE_URL env variable (explicit override)
    | 2. URL from environments array based on EKOM_ENVIRONMENT
    |
    */
    'base_url' => env('EKOM_BASE_URL') ?: (
        env('EKOM_ENVIRONMENT', 'test') === 'production'
            ? 'https://e-komunikacija.pravosudje.hr'
            : 'https://e-komunikacija-test.pravosudje.hr'
    ),

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */
    'token' => env('EKOM_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Request Settings
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('EKOM_TIMEOUT', 30),
    'retries' => (int) env('EKOM_RETRIES', 2),
    'retry_delay_ms' => (int) env('EKOM_RETRY_DELAY_MS', 300),
    'user_agent' => env('EKOM_USER_AGENT', 'Laravel-Ekom-Client/1.0'),

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Default page size for sync commands. API maximum is 100.
    |
    */
    'default_page_size' => (int) env('EKOM_DEFAULT_PAGE_SIZE', 50),

    /*
    |--------------------------------------------------------------------------
    | Token Management
    |--------------------------------------------------------------------------
    |
    | JWT token validity and management settings.
    |
    */
    'token_validity_days' => (int) env('EKOM_TOKEN_VALIDITY_DAYS', 30),
    'max_concurrent_tokens' => (int) env('EKOM_MAX_CONCURRENT_TOKENS', 5),
];
