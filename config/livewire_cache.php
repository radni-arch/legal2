<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Livewire Client-Side Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for client-side caching of Livewire component responses
    |
    */

    'enabled' => env('LIVEWIRE_CACHE_ENABLED', true),

    // Cache driver (browser_storage, indexeddb, memory)
    'driver' => env('LIVEWIRE_CACHE_DRIVER', 'browser_storage'),

    // TTL in seconds for cached responses
    'ttl' => env('LIVEWIRE_CACHE_TTL', 300), // 5 minutes

    // Components to cache
    'components' => [
        'legal-playground' => [
            'enabled' => true,
            'ttl' => 600, // 10 minutes
            'cache_methods' => [
                'analyzeConcept',
                'searchLaws',
            ],
        ],
        'graph-viewer' => [
            'enabled' => true,
            'ttl' => 300, // 5 minutes
            'cache_methods' => [
                'search',
                'getNeighbors',
            ],
        ],
        'citation-time-series-viewer' => [
            'enabled' => true,
            'ttl' => 1800, // 30 minutes
            'cache_methods' => [
                'loadTimeSeriesData',
            ],
        ],
    ],

    // Cache invalidation strategies
    'invalidation' => [
        // Invalidate on model updates
        'on_model_update' => true,

        // Invalidate on user action
        'on_user_action' => false,

        // Maximum cache size (KB)
        'max_size_kb' => 10240, // 10MB
    ],
];
