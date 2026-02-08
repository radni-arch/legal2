<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Hybrid Keyword Extraction Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the advanced keyword extraction system that combines
    | OpenAI embeddings with TF-IDF for semantic and statistical analysis.
    |
    */

    // Enable/disable hybrid extraction (falls back to TF-IDF only when false)
    'use_hybrid' => env('KEYWORDS_USE_HYBRID', true),

    // Enable/disable OpenAI embeddings for semantic analysis
    'use_embeddings' => env('KEYWORDS_USE_EMBEDDINGS', true),

    // Maximum number of keywords to extract per document
    'max_keywords' => env('KEYWORDS_MAX_KEYWORDS', 10),

    // Weighting for hybrid extraction
    'semantic_weight' => env('KEYWORDS_SEMANTIC_WEIGHT', 0.6),  // Weight for embedding-based semantic similarity
    'tfidf_weight' => env('KEYWORDS_TFIDF_WEIGHT', 0.4),         // Weight for TF-IDF statistical importance

    // N-gram (multi-word phrase) extraction
    'extract_ngrams' => env('KEYWORDS_EXTRACT_NGRAMS', true),   // Enable extraction of multi-word phrases
    'max_ngram_size' => env('KEYWORDS_MAX_NGRAM_SIZE', 3),      // Maximum n-gram size (2=bigrams, 3=trigrams)
    'ngram_min_score' => env('KEYWORDS_NGRAM_MIN_SCORE', 0.3),  // Minimum score threshold for n-grams

    // Emerging terminology detection
    'emerging_term_threshold' => env('KEYWORDS_EMERGING_TERM_THRESHOLD', 0.7), // Semantic similarity threshold for emerging terms

    // Cache configuration
    'cache_embeddings' => env('KEYWORDS_CACHE_EMBEDDINGS', true),
    'embedding_cache_ttl' => env('KEYWORDS_EMBEDDING_CACHE_TTL', 30 * 24 * 60), // 30 days in minutes
    'concept_cache_ttl' => env('KEYWORDS_CONCEPT_CACHE_TTL', 90 * 24 * 60),     // 90 days in minutes

    // Performance tuning
    'batch_size' => env('KEYWORDS_BATCH_SIZE', 10),              // Words per API batch
    'rate_limit_delay_ms' => env('KEYWORDS_RATE_LIMIT_DELAY', 100), // Delay between batches in ms

    // Minimum word length to consider
    'min_word_length' => env('KEYWORDS_MIN_WORD_LENGTH', 3),

    // Logging
    'log_extraction' => env('KEYWORDS_LOG_EXTRACTION', false),   // Log each extraction
    'log_fallback' => env('KEYWORDS_LOG_FALLBACK', true),        // Log API failures/fallbacks

    // Benchmark configuration
    'benchmark' => [
        'enabled' => env('KEYWORDS_BENCHMARK_ENABLED', false),
        'sample_size' => env('KEYWORDS_BENCHMARK_SAMPLE_SIZE', 100),
        'ground_truth_file' => storage_path('app/benchmarks/keywords_ground_truth.json'),
        'results_file' => storage_path('app/benchmarks/keywords_benchmark_results.json'),
    ],
];
