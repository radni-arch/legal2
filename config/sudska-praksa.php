<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Base URL for odluke.sudovi.hr search
    |--------------------------------------------------------------------------
    */
    'base_url' => env('SUDSKA_PRAKSA_BASE_URL', 'https://odluke.sudovi.hr/Document/DisplayList'),

    /*
    |--------------------------------------------------------------------------
    | Default court types (ct parameter)
    |--------------------------------------------------------------------------
    | vks = Vrhovni kazneni sud
    | vps = Visoki prekršajni sud
    | vs  = Vrhovni sud
    | zs  = Županijski sudovi
    */
    'default_courts' => env('SUDSKA_PRAKSA_COURTS', 'vks,vps,vs,zs'),

    /*
    |--------------------------------------------------------------------------
    | Request delay between queries (milliseconds)
    |--------------------------------------------------------------------------
    */
    'delay_ms' => (int) env('SUDSKA_PRAKSA_DELAY', 500),

    /*
    |--------------------------------------------------------------------------
    | HTTP timeout per request (seconds)
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('SUDSKA_PRAKSA_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Default keywords file path (relative to base_path)
    |--------------------------------------------------------------------------
    */
    'keywords_file' => env('SUDSKA_PRAKSA_KEYWORDS', 'storage/app/keywords/default.json'),

    /*
    |--------------------------------------------------------------------------
    | Default output directory (relative to base_path)
    |--------------------------------------------------------------------------
    */
    'output_dir' => env('SUDSKA_PRAKSA_OUTPUT', 'storage/app/results'),

    /*
    |--------------------------------------------------------------------------
    | Classification thresholds
    |--------------------------------------------------------------------------
    */
    'thresholds' => [
        'ultra'   => 5,    // ≤5 results = ULTRA ZLATO
        'zlato'   => 15,   // 6-15 = ZLATO
        'srebrno' => 50,   // 16-50 = SREBRNO
        'bronca'  => 150,  // 51-150 = BRONCA
    ],

    /*
    |--------------------------------------------------------------------------
    | User-Agent for HTTP requests
    |--------------------------------------------------------------------------
    */
    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',

    /*
    |--------------------------------------------------------------------------
    | Retry configuration
    |--------------------------------------------------------------------------
    */
    'max_retries' => (int) env('SUDSKA_PRAKSA_MAX_RETRIES', 3),
    'retry_delay_ms' => (int) env('SUDSKA_PRAKSA_RETRY_DELAY', 2000),
];
