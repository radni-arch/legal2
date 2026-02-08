<?php

return [
    'base_url' => env('ESLJP_BASE_URL', 'https://sljeme.usud.hr'),
    'search_path' => env('ESLJP_SEARCH_PATH', '/usud/prakES.nsf/PraksaP/'),
    'search_order' => env('ESLJP_SEARCH_ORDER', 4),
    'count' => env('ESLJP_SEARCH_COUNT', 1000),
    'search_max' => env('ESLJP_SEARCH_MAX', 1000),
    'timeout' => env('ESLJP_TIMEOUT', 30),
    'retry' => env('ESLJP_RETRY', 2),
    'delay_ms' => env('ESLJP_DELAY_MS', 700),
    'rpm' => env('ESLJP_RPM', 20),
    'backoff_ms' => env('ESLJP_BACKOFF_MS', 800),
    'sync_graph' => env('ESLJP_SYNC_GRAPH', false),
];
