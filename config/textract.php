<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Auto-Sync Settings
    |--------------------------------------------------------------------------
    |
    | Control automatic synchronization of TextractJob content changes.
    | When enabled, content updates trigger automatic embedding regeneration
    | and graph synchronization.
    |
    */

    'auto_sync' => env('TEXTRACT_AUTO_SYNC', true),

    /*
    |--------------------------------------------------------------------------
    | Embedding Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for generating embeddings from extracted text content.
    |
    */

    'embeddings' => [
        // OpenAI embedding model to use
        'model' => env('TEXTRACT_EMBEDDING_MODEL', 'text-embedding-3-small'),

        // Provider (currently only 'openai' supported)
        'provider' => env('TEXTRACT_EMBEDDING_PROVIDER', 'openai'),

        // Batch size for embedding generation (max chunks per API call)
        'batch_size' => env('TEXTRACT_EMBEDDING_BATCH_SIZE', 100),

        // Maximum retries for failed embedding requests
        'max_retries' => env('TEXTRACT_EMBEDDING_MAX_RETRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Text Chunking Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for splitting document content into chunks for embedding.
    |
    */

    'chunking' => [
        // Target chunk size in characters (~1000 chars ≈ 250 tokens)
        'chunk_size' => env('TEXTRACT_CHUNK_SIZE', 1000),

        // Overlap between consecutive chunks to preserve context
        'chunk_overlap' => env('TEXTRACT_CHUNK_OVERLAP', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for background job processing.
    |
    */

    'queue' => [
        // Queue name for textract jobs
        'name' => env('TEXTRACT_QUEUE', 'default'),

        // Timeout for embedding jobs (seconds)
        'embedding_timeout' => env('TEXTRACT_EMBEDDING_TIMEOUT', 600),

        // Timeout for graph sync jobs (seconds)
        'graph_timeout' => env('TEXTRACT_GRAPH_TIMEOUT', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Validation
    |--------------------------------------------------------------------------
    |
    | Validation rules for content editing.
    |
    */

    'validation' => [
        // Minimum content length (characters)
        'min_content_length' => env('TEXTRACT_MIN_CONTENT_LENGTH', 10),

        // Maximum content length (characters)
        'max_content_length' => env('TEXTRACT_MAX_CONTENT_LENGTH', 10000000), // 10MB

        // Allow empty content (for deletion)
        'allow_empty' => env('TEXTRACT_ALLOW_EMPTY_CONTENT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Enable/disable specific features.
    |
    */

    'features' => [
        // Enable content editing UI
        'content_editing' => env('TEXTRACT_ENABLE_CONTENT_EDITING', true),

        // Enable manual sync triggers
        'manual_sync' => env('TEXTRACT_ENABLE_MANUAL_SYNC', true),

        // Enable reset to original functionality
        'reset_content' => env('TEXTRACT_ENABLE_RESET_CONTENT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Existing Text Detection (Cost Optimization)
    |--------------------------------------------------------------------------
    |
    | Settings for detecting existing text in PDFs to skip AWS Textract.
    | PDFs with sufficient embedded text are routed to OcrmypdfService
    | with --skip-text flag instead, saving ~$1.50/1000 pages.
    |
    */

    // Text coverage threshold (0.0 - 1.0) above which Textract is skipped
    // Default 0.8 = 80% coverage (40+ words per page)
    'skip_text_threshold' => (float) env('TEXTRACT_SKIP_TEXT_THRESHOLD', 0.8),

    // Expected words per page baseline for "text-rich" documents
    'expected_words_per_page' => (int) env('TEXTRACT_EXPECTED_WORDS_PER_PAGE', 50),

    /*
    |--------------------------------------------------------------------------
    | PDF Preview Settings
    |--------------------------------------------------------------------------
    */
    'pdf_url_expiration' => env('TEXTRACT_PDF_URL_EXPIRATION', 3600), // 1 hour default

    /*
    |--------------------------------------------------------------------------
    | Timeout Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for controlling timeout behavior during Textract processing.
    |
    */

    // Maximum seconds to wait for a single Textract analysis step (default: 10 minutes)
    'max_wait_seconds' => env('TEXTRACT_MAX_WAIT_SECONDS', 600),

    // Maximum seconds for the entire pipeline execution (default: 30 minutes)
    'max_pipeline_seconds' => env('TEXTRACT_MAX_PIPELINE_SECONDS', 1800),

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Control how pipeline event notifications are delivered to users.
    | When enabled, users are notified via database channel on pipeline events.
    | Database channel is always active when notifications are enabled.
    | Mail channel is opt-in.
    |
    */

    'notifications' => [
        // Master switch for all pipeline notifications
        'enabled' => env('TEXTRACT_NOTIFICATIONS_ENABLED', false),
        'mail' => [
            'enabled' => env('TEXTRACT_NOTIFICATIONS_MAIL_ENABLED', false),
        ],
    ],
];
