<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Distributed Tracing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for distributed tracing across services (OpenTelemetry)
    |
    */

    'enabled' => env('TRACING_ENABLED', false),

    // Tracing backend (jaeger, zipkin, datadog, honeycomb)
    'driver' => env('TRACING_DRIVER', 'jaeger'),

    // Jaeger configuration
    'jaeger' => [
        'host' => env('JAEGER_HOST', 'localhost'),
        'port' => env('JAEGER_PORT', 6831),
        'agent_host' => env('JAEGER_AGENT_HOST', 'localhost'),
        'agent_port' => env('JAEGER_AGENT_PORT', 6831),
    ],

    // Zipkin configuration
    'zipkin' => [
        'endpoint' => env('ZIPKIN_ENDPOINT', 'http://localhost:9411/api/v2/spans'),
    ],

    // Sampling strategy
    'sampling' => [
        'strategy' => env('TRACING_SAMPLING_STRATEGY', 'probabilistic'), // always, never, probabilistic, rate_limiting
        'rate' => env('TRACING_SAMPLING_RATE', 0.1), // 10% of requests
    ],

    // Services to trace
    'services' => [
        'openai' => [
            'enabled' => true,
            'tags' => [
                'service.name' => 'openai-api',
                'service.version' => '1.0',
            ],
        ],
        'textract' => [
            'enabled' => true,
            'tags' => [
                'service.name' => 'aws-textract',
                'service.version' => '1.0',
            ],
        ],
        'neo4j' => [
            'enabled' => true,
            'tags' => [
                'service.name' => 'neo4j-graph',
                'service.version' => '1.0',
            ],
        ],
        'postgres' => [
            'enabled' => true,
            'tags' => [
                'service.name' => 'postgresql',
                'service.version' => '1.0',
            ],
        ],
    ],

    // Span attributes to capture
    'attributes' => [
        'http.method',
        'http.url',
        'http.status_code',
        'db.statement',
        'db.operation',
        'ai.model',
        'ai.prompt_tokens',
        'ai.completion_tokens',
        'user.id',
    ],

    // Performance thresholds for warnings
    'thresholds' => [
        'slow_request_ms' => 1000,
        'slow_database_query_ms' => 500,
        'slow_ai_call_ms' => 5000,
    ],

    // Trace context propagation
    'propagation' => [
        'headers' => [
            'traceparent',
            'tracestate',
            'x-request-id',
            'x-correlation-id',
        ],
    ],
];
