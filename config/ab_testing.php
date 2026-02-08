<?php

return [
    /*
    |--------------------------------------------------------------------------
    | A/B Testing Framework Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for A/B testing AI prompts and analyzing performance
    |
    */

    'enabled' => env('AB_TESTING_ENABLED', false),

    // Experiment tracking
    'tracking' => [
        'driver' => env('AB_TESTING_DRIVER', 'database'), // database, redis, file
        'store_responses' => env('AB_TESTING_STORE_RESPONSES', true),
    ],

    // Active experiments
    'experiments' => [
        'case_strength_analysis' => [
            'enabled' => true,
            'variants' => [
                'control' => [
                    'weight' => 50, // 50% traffic
                    'prompt_template' => 'prompts.case_strength.control',
                ],
                'variant_a' => [
                    'weight' => 25, // 25% traffic
                    'prompt_template' => 'prompts.case_strength.variant_a',
                    'description' => 'More detailed context about Croatian law',
                ],
                'variant_b' => [
                    'weight' => 25, // 25% traffic
                    'prompt_template' => 'prompts.case_strength.variant_b',
                    'description' => 'Chain-of-thought reasoning',
                ],
            ],
            'metrics' => [
                'response_time',
                'token_count',
                'user_satisfaction',
                'accuracy_score',
            ],
        ],

        'evidence_recontextualization' => [
            'enabled' => false,
            'variants' => [
                'control' => [
                    'weight' => 70,
                    'prompt_template' => 'prompts.evidence.control',
                ],
                'variant_a' => [
                    'weight' => 30,
                    'prompt_template' => 'prompts.evidence.variant_a',
                    'description' => 'Constitutional rights emphasis',
                ],
            ],
            'metrics' => [
                'response_time',
                'token_count',
                'legal_accuracy',
            ],
        ],
    ],

    // Statistical significance
    'statistics' => [
        'min_sample_size' => env('AB_TESTING_MIN_SAMPLE_SIZE', 100),
        'confidence_level' => env('AB_TESTING_CONFIDENCE_LEVEL', 0.95), // 95%
        'auto_promote_winner' => env('AB_TESTING_AUTO_PROMOTE', false),
    ],

    // Safety controls
    'safety' => [
        // Kill switch for problematic variants
        'auto_disable_on_error_rate' => 0.15, // 15% error rate

        // Minimum performance threshold (vs control)
        'min_performance_ratio' => 0.9, // 90% of control performance
    ],
];
