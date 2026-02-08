<?php

/**
 * Configuration for Cross-Database Integrity Checks
 *
 * Task 4.3: Cross-Database Integrity Scheduling
 *
 * Controls how integrity checks between Neo4j and PostgreSQL are
 * scheduled and executed.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Email
    |--------------------------------------------------------------------------
    |
    | Email address to receive integrity check reports. Set this to a valid
    | email address to receive notifications when issues are found.
    |
    */
    'admin_email' => env('INTEGRITY_ADMIN_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Auto-Fix
    |--------------------------------------------------------------------------
    |
    | When enabled, the integrity check will automatically attempt to fix
    | issues where possible (e.g., removing orphan nodes, re-syncing missing
    | nodes). Use with caution in production.
    |
    */
    'auto_fix' => env('INTEGRITY_AUTO_FIX', false),

    /*
    |--------------------------------------------------------------------------
    | Duplicate Merge Threshold
    |--------------------------------------------------------------------------
    |
    | The similarity threshold (0.0 to 1.0) for considering two nodes as
    | duplicates. Higher values require closer matches. Default is 0.9 (90%).
    |
    */
    'duplicate_merge_threshold' => (float) env('INTEGRITY_DUPLICATE_MERGE_THRESHOLD', 0.9),

    /*
    |--------------------------------------------------------------------------
    | Email on Failure
    |--------------------------------------------------------------------------
    |
    | When enabled, sends an email notification whenever integrity checks
    | detect issues. The email is sent to the admin_email address.
    |
    */
    'email_on_failure' => env('INTEGRITY_EMAIL_ON_FAILURE', true),

    /*
    |--------------------------------------------------------------------------
    | Schedule Configuration
    |--------------------------------------------------------------------------
    |
    | Configure when integrity checks run. These values are used by the
    | Console Kernel to schedule automated checks.
    |
    */
    'schedule' => [
        // Time for daily integrity check (24-hour format)
        'integrity_check_time' => env('INTEGRITY_CHECK_TIME', '02:00'),

        // Day of week for quality check (0=Sunday, 1=Monday, ..., 6=Saturday)
        'quality_check_day' => env('QUALITY_CHECK_DAY', 'sunday'),

        // Time for weekly quality check (24-hour format)
        'quality_check_time' => env('QUALITY_CHECK_TIME', '03:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Report Retention
    |--------------------------------------------------------------------------
    |
    | How many days to keep integrity reports before pruning old entries.
    | Set to 0 to disable pruning.
    |
    */
    'report_retention_days' => env('INTEGRITY_REPORT_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Node Types to Check
    |--------------------------------------------------------------------------
    |
    | List of node types to include in integrity checks. Add or remove types
    | as needed based on your graph schema.
    |
    */
    'node_types' => [
        'CourtDecisionDocument',
        'LawDocument',
        'Court',
        'Keyword',
        'Tag',
        'Judge',
        'Party',
    ],

    /*
    |--------------------------------------------------------------------------
    | Relationship Types to Check
    |--------------------------------------------------------------------------
    |
    | List of relationship types to validate for dangling references.
    |
    */
    'relationship_types' => [
        'CITES',
        'REFERENCES',
        'DECIDED_BY',
        'HAS_KEYWORD',
        'HAS_TAG',
        'RELATED_TO',
    ],
];
