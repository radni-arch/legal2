<?php

return [
    'base_url' => env('USUD_BASE_URL', 'https://sljeme.usud.hr'),
    'search_path' => env('USUD_SEARCH_PATH', '/usud/praksaw.nsf/vSearchResults.xsp'),
    'pdf_base_url' => env('USUD_PDF_BASE_URL', 'https://sljeme.usud.hr/Usud/Praksaw.nsf'),
    'timeout' => env('USUD_TIMEOUT', 30),
    'retry' => env('USUD_RETRY', 2),
    'delay_ms' => env('USUD_DELAY_MS', 700),
    'rpm' => env('USUD_RPM', 20),
    'backoff_ms' => env('USUD_BACKOFF_MS', 800),
    'sync_graph' => env('USUD_SYNC_GRAPH', false),
];
