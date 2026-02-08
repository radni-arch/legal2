<?php

return [
    'models' => [
        'gpt-4o-mini' => [
            'input_per_1m' => (float) env('AI_PRICING_GPT4O_MINI_INPUT', 0.15),
            'output_per_1m' => (float) env('AI_PRICING_GPT4O_MINI_OUTPUT', 0.60),
        ],
        'gpt-4o' => [
            'input_per_1m' => (float) env('AI_PRICING_GPT4O_INPUT', 2.50),
            'output_per_1m' => (float) env('AI_PRICING_GPT4O_OUTPUT', 10.00),
        ],
        'text-embedding-3-small' => [
            'input_per_1m' => (float) env('AI_PRICING_EMBEDDING_SMALL_INPUT', 0.02),
            'output_per_1m' => 0.0,
        ],
        'text-embedding-3-large' => [
            'input_per_1m' => (float) env('AI_PRICING_EMBEDDING_LARGE_INPUT', 0.13),
            'output_per_1m' => 0.0,
        ],
    ],
    'default_model' => env('AI_DEFAULT_MODEL', 'gpt-4o-mini'),
];
