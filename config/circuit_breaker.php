<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for automated circuit breaker threshold tuning and monitoring
    |
    */

    'auto_tuning' => [
        'enabled' => env('CIRCUIT_BREAKER_AUTO_TUNING_ENABLED', false),

        // Analyze metrics every N minutes
        'analysis_interval_minutes' => env('CIRCUIT_BREAKER_ANALYSIS_INTERVAL', 60),

        // Minimum data points before adjusting thresholds
        'min_data_points' => env('CIRCUIT_BREAKER_MIN_DATA_POINTS', 100),

        // Tuning parameters
        'failure_threshold' => [
            'min' => 3,
            'max' => 10,
            'target_false_positive_rate' => 0.05, // 5% false positives acceptable
        ],

        'success_threshold' => [
            'min' => 2,
            'max' => 5,
        ],

        'timeout' => [
            'min' => 30,
            'max' => 300,
        ],
    ],

    // Default thresholds per service type
    'defaults' => [
        'openai' => [
            'failure_threshold' => 5,
            'success_threshold' => 2,
            'timeout' => 60,
            'retry_after' => 30,
        ],
        'textract' => [
            'failure_threshold' => 3,
            'success_threshold' => 2,
            'timeout' => 120,
            'retry_after' => 60,
        ],
        'odluke' => [
            'failure_threshold' => 3,
            'success_threshold' => 2,
            'timeout' => 60,
            'retry_after' => 30,
        ],
        'neo4j' => [
            'failure_threshold' => 5,
            'success_threshold' => 2,
            'timeout' => 30,
            'retry_after' => 15,
        ],
    ],

    // Monitoring and alerting
    'monitoring' => [
        'enabled' => env('CIRCUIT_BREAKER_MONITORING_ENABLED', true),

        'alerts' => [
            // Alert when circuit opens
            'on_open' => env('CIRCUIT_BREAKER_ALERT_ON_OPEN', true),

            // Alert when circuit stays open for N minutes
            'prolonged_open_threshold_minutes' => env('CIRCUIT_BREAKER_PROLONGED_OPEN_MINUTES', 30),

            // Alert channels (slack, email, pagerduty)
            'channels' => explode(',', env('CIRCUIT_BREAKER_ALERT_CHANNELS', 'slack')),
        ],
    ],
];
