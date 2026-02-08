<?php

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
            'permission' => 0664,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
            'permission' => 0664,
            'formatter' => env('LOG_DAILY_FORMATTER', JsonFormatter::class),
            'formatter_with' => [
                'appendNewline' => true,
            ],
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', 'Laravel Log'),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'sentry_logs' => [
            'driver' => 'sentry_logs',
            'level' => env('LOG_LEVEL', 'info'),
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER', JsonFormatter::class),
            'formatter_with' => [
                'appendNewline' => true,
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

        // Custom OpenAI JSON log channel
        'openai' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => storage_path('logs/openai.log'),
                'filePermission' => 0664,
            ],
            'formatter' => JsonFormatter::class,
            'formatter_with' => [
                // ensure each record is a single JSON line
                'append_newline' => true,
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        // Performance monitoring log channel
        'performance' => [
            'driver' => 'daily',
            'path' => storage_path('logs/performance.log'),
            'level' => env('LOG_PERFORMANCE_LEVEL', 'info'),
            'days' => 7,
            'replace_placeholders' => true,
            'permission' => 0664,
        ],

        // Queue jobs log channel
        'queue' => [
            'driver' => 'daily',
            'path' => storage_path('logs/queue.log'),
            'level' => env('LOG_QUEUE_LEVEL', 'info'),
            'days' => 7,
            'replace_placeholders' => true,
            'permission' => 0664,
        ],

        // Security events log channel
        'security' => [
            'driver' => 'daily',
            'path' => storage_path('logs/security.log'),
            'level' => env('LOG_SECURITY_LEVEL', 'warning'),
            'days' => 30, // Keep security logs longer
            'replace_placeholders' => true,
            'permission' => 0664,
        ],

        // API requests log channel
        'api' => [
            'driver' => 'daily',
            'path' => storage_path('logs/api.log'),
            'level' => env('LOG_API_LEVEL', 'info'),
            'days' => 7,
            'replace_placeholders' => true,
            'permission' => 0664,
        ],

        // Database queries log channel
        'database' => [
            'driver' => 'daily',
            'path' => storage_path('logs/database.log'),
            'level' => env('LOG_DATABASE_LEVEL', 'debug'),
            'days' => 3,
            'replace_placeholders' => true,
            'permission' => 0664,
        ],

        // Application monitoring log channel
        'monitoring' => [
            'driver' => 'daily',
            'path' => storage_path('logs/monitoring.log'),
            'level' => env('LOG_MONITORING_LEVEL', 'info'),
            'days' => 14,
            'replace_placeholders' => true,
            'permission' => 0664,
        ],

        // AI agents log channel with structured logging (JSON)
        'agents' => [
            'driver' => 'monolog',
            'level' => env('LOG_AGENTS_LEVEL', 'info'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => storage_path('logs/agents.log'),
                'filePermission' => 0664,
            ],
            'formatter' => JsonFormatter::class,
            'formatter_with' => [
                'appendNewline' => true,
            ],
            'processors' => [
                PsrLogMessageProcessor::class,
                \App\Logging\CorrelationIdProcessor::class,
            ],
        ],

        // Graph database log channel for query performance monitoring
        'graph' => [
            'driver' => 'daily',
            'path' => storage_path('logs/graph.log'),
            'level' => env('LOG_GRAPH_LEVEL', 'info'),
            'days' => env('LOG_GRAPH_DAYS', 7),
            'replace_placeholders' => true,
            'formatter' => JsonFormatter::class,
            'formatter_with' => [
                'appendNewline' => true,
            ],
        ],

    ],

];
