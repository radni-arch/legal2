<?php

return [
    'base_url' => env('HUDOC_BASE_URL', 'https://hudoc.echr.coe.int'),

    'endpoints' => [
        'search' => '/app/query/results',
        'document' => '/app/conversion/docx',
    ],

    'default_language' => env('HUDOC_LANGUAGE', 'ENG'),

    'rate_limit' => [
        'requests_per_minute' => 30,
        'pause_ms' => 2000,
    ],

    'cache' => [
        'enabled' => env('HUDOC_CACHE_ENABLED', true),
        'ttl' => 86400, // 24 hours
    ],

    'python_path' => env('HUDOC_PYTHON_PATH', 'python3'),

    'echr_od' => [
        'base_url' => 'https://echr-opendata.eu/api',
        'enabled' => env('ECHR_OD_ENABLED', true),
    ],
];
