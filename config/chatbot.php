<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Chatbot Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the AI chatbot component including RAG settings,
    | pagination, and token limits.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Message Pagination
    |--------------------------------------------------------------------------
    |
    | Number of messages to load per page in the chatbot interface.
    |
    */
    'messages_per_page' => env('CHATBOT_MESSAGES_PER_PAGE', 50),

    /*
    |--------------------------------------------------------------------------
    | RAG (Retrieval-Augmented Generation) Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the RAG service including token limits and document
    | retrieval limits per source type.
    |
    */
    'rag' => [
        // Token budget limits
        'max_context_tokens' => env('CHATBOT_MAX_CONTEXT_TOKENS', 80000),
        'average_tokens_per_char' => env('CHATBOT_AVERAGE_TOKENS_PER_CHAR', 0.25),

        // Document limits per source type
        'max_laws_per_query' => env('CHATBOT_MAX_LAWS_PER_QUERY', 10),
        'max_cases_per_query' => env('CHATBOT_MAX_CASES_PER_QUERY', 7),
        'max_court_decisions_per_query' => env('CHATBOT_MAX_COURT_DECISIONS_PER_QUERY', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Types
    |--------------------------------------------------------------------------
    |
    | Available agent types for different chatbot specializations.
    |
    */
    'agent_types' => [
        'general' => 'General Chat',
        'law' => 'Legal Research',
        'court_decision' => 'Court Decisions',
        'case_analysis' => 'Case Analysis',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting settings for chatbot API calls.
    |
    */
    'rate_limit' => [
        'requests_per_minute' => env('CHATBOT_RATE_LIMIT', 60),
    ],
];
