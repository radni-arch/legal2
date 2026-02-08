<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Testing Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for testing and seeding.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Download Real Court Decisions
    |--------------------------------------------------------------------------
    |
    | Enable this flag to download real court decisions from the Odluke API
    | during test data seeding. This is SLOW and should only be used when
    | you need real data for testing.
    |
    */
    'download_real_decisions' => env('TEST_DOWNLOAD_DECISIONS', false),

    /*
    |--------------------------------------------------------------------------
    | Court Decision Download Count
    |--------------------------------------------------------------------------
    |
    | Number of court decisions to download when using the download seeder.
    |
    */
    'court_decision_download_count' => env('TEST_COURT_DECISION_COUNT', 100),

    /*
    |--------------------------------------------------------------------------
    | Court Decision Download Query
    |--------------------------------------------------------------------------
    |
    | Search query to use when downloading court decisions.
    | Examples: 'kazneni', 'građanski', 'trgovački'
    |
    */
    'court_decision_download_query' => env('TEST_COURT_DECISION_QUERY', ''),

    /*
    |--------------------------------------------------------------------------
    | Court Decision Download Parameters
    |--------------------------------------------------------------------------
    |
    | Additional URL parameters for the Odluke API search.
    | Example: 'DateFrom=2023-01-01&DateTo=2023-12-31'
    |
    */
    'court_decision_download_params' => env('TEST_COURT_DECISION_PARAMS', null),

    /*
    |--------------------------------------------------------------------------
    | Court Decision Download Batch Size
    |--------------------------------------------------------------------------
    |
    | Number of decision IDs to fetch per batch from the API.
    |
    */
    'court_decision_download_batch_size' => env('TEST_COURT_DECISION_BATCH_SIZE', 20),
];
