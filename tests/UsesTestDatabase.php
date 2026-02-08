<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Trait UsesTestDatabase
 *
 * Use this trait in tests that need database access.
 *
 * This trait uses DatabaseTransactions which:
 * - Wraps each test in a database transaction
 * - Automatically rolls back after each test
 * - Keeps your test database pristine
 * - Much faster than RefreshDatabase
 *
 * Usage:
 * ```php
 * class MyTest extends TestCase
 * {
 *     use UsesTestDatabase;
 *
 *     public function test_something()
 *     {
 *         // Your test code that uses the database
 *     }
 * }
 * ```
 *
 * Note: If you need to test database transactions themselves,
 * use RefreshDatabase instead.
 */
trait UsesTestDatabase
{
    use DatabaseTransactions;

    /**
     * Setup the test case with database transactions.
     */
    protected function setUpUsesTestDatabase(): void
    {
        // You can add any additional database setup here
        // For example, seeding specific test data that should be available in all tests
    }
}
