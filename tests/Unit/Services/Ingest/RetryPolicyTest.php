<?php

namespace Tests\Unit\Services\Ingest;

use App\Services\Ingest\BackpressureMonitor;
use Tests\TestCase;

/**
 * Verify retry policy configuration per source type (SOT-018).
 */
class RetryPolicyTest extends TestCase
{
    protected BackpressureMonitor $monitor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monitor = new BackpressureMonitor();
    }

    public function test_uploader_retry_policy_has_required_keys(): void
    {
        $policy = $this->monitor->getRetryPolicy('uploader');

        $this->assertArrayHasKey('max_tries', $policy);
        $this->assertArrayHasKey('backoff', $policy);
        $this->assertArrayHasKey('retry_until_minutes', $policy);
        $this->assertArrayHasKey('max_exceptions', $policy);
    }

    public function test_uploader_has_aggressive_retry_policy(): void
    {
        $policy = $this->monitor->getRetryPolicy('uploader');

        // Uploader should fail fast (user-facing)
        $this->assertLessThanOrEqual(5, $policy['max_tries']);
        $this->assertLessThanOrEqual(60, $policy['retry_until_minutes']);
    }

    public function test_odluke_has_patient_retry_policy(): void
    {
        $policy = $this->monitor->getRetryPolicy('odluke');

        // Odluke scraping should be more patient (batch processing)
        $this->assertGreaterThanOrEqual(3, $policy['max_tries']);
        $this->assertGreaterThanOrEqual(60, $policy['retry_until_minutes']);
    }

    public function test_echr_has_longest_retry_window(): void
    {
        $policy = $this->monitor->getRetryPolicy('echr');

        // ECHR is external and slow, needs longest window
        $this->assertGreaterThanOrEqual(120, $policy['retry_until_minutes']);
    }

    public function test_drive_retry_policy_is_moderate(): void
    {
        $policy = $this->monitor->getRetryPolicy('drive');

        $this->assertGreaterThanOrEqual(3, $policy['max_tries']);
        $this->assertGreaterThanOrEqual(60, $policy['retry_until_minutes']);
    }

    public function test_backoff_arrays_are_increasing(): void
    {
        $sources = ['uploader', 'drive', 'odluke', 'usud', 'echr', 'api'];

        foreach ($sources as $source) {
            $policy = $this->monitor->getRetryPolicy($source);
            $backoff = $policy['backoff'];

            $this->assertIsArray($backoff, "Backoff must be array for {$source}");
            $this->assertNotEmpty($backoff, "Backoff must not be empty for {$source}");

            // Verify backoff values are non-decreasing
            for ($i = 1; $i < count($backoff); $i++) {
                $this->assertGreaterThanOrEqual(
                    $backoff[$i - 1],
                    $backoff[$i],
                    "Backoff values must be non-decreasing for {$source}"
                );
            }
        }
    }

    public function test_all_source_types_have_retry_policies(): void
    {
        $sources = ['uploader', 'drive', 'odluke', 'usud', 'echr', 'api'];

        foreach ($sources as $source) {
            $policy = config("ingest-queues.retry_policies.{$source}");
            $this->assertNotNull($policy, "Retry policy must be configured for source: {$source}");
        }
    }

    public function test_unknown_source_gets_default_policy(): void
    {
        $policy = $this->monitor->getRetryPolicy('unknown');

        $this->assertArrayHasKey('max_tries', $policy);
        $this->assertEquals(3, $policy['max_tries']);
        $this->assertEquals(30, $policy['retry_until_minutes']);
    }

    public function test_max_exceptions_is_less_than_max_tries(): void
    {
        $sources = ['uploader', 'drive', 'odluke', 'usud', 'echr', 'api'];

        foreach ($sources as $source) {
            $policy = $this->monitor->getRetryPolicy($source);
            $this->assertLessThanOrEqual(
                $policy['max_tries'],
                $policy['max_exceptions'],
                "max_exceptions should be <= max_tries for {$source}"
            );
        }
    }

    public function test_concurrency_limits_are_configured(): void
    {
        $concurrency = config('ingest-queues.concurrency');
        $this->assertIsArray($concurrency);
        $this->assertNotEmpty($concurrency);

        foreach ($concurrency as $queue => $limit) {
            $this->assertIsInt($limit);
            $this->assertGreaterThan(0, $limit, "Concurrency for {$queue} must be > 0");
        }
    }
}
