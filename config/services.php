<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AWS Configuration
    |--------------------------------------------------------------------------
    |
    | Shared AWS credentials and configuration used by various AWS services
    | including Textract, S3, and other SDK clients. These values are read
    | from environment variables and cached with the application config.
    |
    */

    'aws' => [
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'bucket' => env('AWS_BUCKET'),

        // Textract-specific configuration
        'textract' => [
            'input_prefix' => env('S3_INPUT_PREFIX', 'textract/input'),
            'json_prefix' => env('S3_JSON_PREFIX', 'textract/json'),
        ],
    ],

    'epredmet' => [
        'url' => env('EPREDMET_API_URL', 'https://e-predmet.pravosudje.hr/api'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
        'neo4j_webhook' => env('SLACK_NEO4J_WEBHOOK_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP (Model Context Protocol) Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for MCP tools authentication and rate limiting.
    | MCP tools provide access to legal data (laws, decisions, cases) for
    | external systems and AI agents.
    |
    */

    'openai' => [
        'reasoning_model' => env('OPENAI_REASONING_MODEL', 'gpt-4o'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Claude API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Anthropic Claude API used for AI-powered legal
    | document analysis including timeline construction, summaries, key facts
    | extraction, and contradiction detection.
    |
    */

    'claude' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('CLAUDE_MODEL', 'claude-sonnet-4-5-20250929'),
        'max_tokens' => env('CLAUDE_MAX_TOKENS', 4096)
    ],
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],
    'mcp' => [
        // Authentication
        'auth' => [
            'enabled' => env('MCP_AUTH_ENABLED', true),
            'token' => env('MCP_API_TOKEN'),
            'token_header' => env('MCP_TOKEN_HEADER', 'X-MCP-Token'),
        ],

        // Rate limiting configuration
        'rate_limit' => [
            'enabled' => env('MCP_RATE_LIMIT_ENABLED', true),
            'default_per_minute' => env('MCP_RATE_LIMIT_PER_MINUTE', 60),
            'default_per_hour' => env('MCP_RATE_LIMIT_PER_HOUR', 1000),

            // Per-tool quotas (requests per minute)
            'per_tool' => [
                'law.search' => env('MCP_RATE_LAW_SEARCH', 30),
                'law.get_article' => env('MCP_RATE_LAW_GET', 60),
                'decision.search' => env('MCP_RATE_DECISION_SEARCH', 30),
                'decision.get' => env('MCP_RATE_DECISION_GET', 60),
                'case.search' => env('MCP_RATE_CASE_SEARCH', 20), // Private: lower limit
            ],
        ],

        // Tool access control
        'access' => [
            'private_tools' => ['case.search'], // Require special authentication
            'public_tools' => ['law.search', 'law.get_article', 'decision.search', 'decision.get'],
        ],

        // Response configuration
        'response' => [
            'max_page_size' => env('MCP_MAX_PAGE_SIZE', 100),
            'default_page_size' => env('MCP_DEFAULT_PAGE_SIZE', 10),
            'signed_url_expiry' => env('MCP_SIGNED_URL_EXPIRY', 3600), // 1 hour
        ],
    ],

];
