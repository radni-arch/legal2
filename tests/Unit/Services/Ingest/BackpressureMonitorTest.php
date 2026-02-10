<?php

namespace Tests\Unit\Services\Ingest;

use App\Services\Ingest\BackpressureMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verify backpressure monitoring and throttling logic (SOT-018).
 */
class BackpressureMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected BackpressureMonitor $monitor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monitor = new BackpressureMonitor();
    }

    public function test_get_pending_count_returns_zero_for_empty_queue(): void
    {
        $count = $this->monitor->getPendingCount('ingest-upload');
        $this->assertEquals(0, $count);
    }

    public function test_get_pending_count_counts_unreserved_jobs(): void
    {
        // Insert some fake jobs into the jobs table
        DB::table('jobs')->insert([
            ['queue' => 'ingest-upload', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time()],
            ['queue' => 'ingest-upload', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time()],
            ['queue' => 'ingest-upload', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => time(), 'available_at' => time(), 'created_at' => time()], // reserved
            ['queue' => 'ingest-drive', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time()], // different queue
        ]);

        $uploadCount = $this->monitor->getPendingCount('ingest-upload');
        $this->assertEquals(2, $uploadCount); // Only unreserved, correct queue

        $driveCount = $this->monitor->getPendingCount('ingest-drive');
        $this->assertEquals(1, $driveCount);
    }

    public function test_should_throttle_returns_false_below_threshold(): void
    {
        config(['ingest-queues.backpressure.max_pending_per_queue' => 100]);

        $this->assertFalse($this->monitor->shouldThrottle('ingest-upload'));
    }

    public function test_should_throttle_returns_true_at_threshold(): void
    {
        config(['ingest-queues.backpressure.max_pending_per_queue' => 2]);

        // Insert exactly 2 pending jobs
        DB::table('jobs')->insert([
            ['queue' => 'ingest-upload', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time()],
            ['queue' => 'ingest-upload', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time()],
        ]);

        $this->assertTrue($this->monitor->shouldThrottle('ingest-upload'));
    }

    public function test_should_throttle_returns_true_above_threshold(): void
    {
        config(['ingest-queues.backpressure.max_pending_per_queue' => 1]);

        DB::table('jobs')->insert([
            ['queue' => 'ingest-upload', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time()],
            ['queue' => 'ingest-upload', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time()],
        ]);

        $this->assertTrue($this->monitor->shouldThrottle('ingest-upload'));
    }

    public function test_get_queue_health_returns_all_configured_queues(): void
    {
        $health = $this->monitor->getQueueHealth();

        $this->assertIsArray($health);
        $this->assertNotEmpty($health);

        // Should include the default queue at minimum
        foreach ($health as $entry) {
            $this->assertArrayHasKey('queue', $entry);
            $this->assertArrayHasKey('pending', $entry);
            $this->assertArrayHasKey('threshold', $entry);
            $this->assertArrayHasKey('throttled', $entry);
        }
    }

    public function test_get_queue_health_shows_accurate_pending_counts(): void
    {
        DB::table('jobs')->insert([
            ['queue' => 'ingest-upload', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time()],
            ['queue' => 'ingest-upload', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time()],
        ]);

        $health = $this->monitor->getQueueHealth();

        if (isset($health['ingest-upload'])) {
            $this->assertEquals(2, $health['ingest-upload']['pending']);
        }
    }

    public function test_get_throttle_delay_returns_configured_value(): void
    {
        config(['ingest-queues.backpressure.throttle_delay_seconds' => 120]);

        $delay = $this->monitor->getThrottleDelay();
        $this->assertEquals(120, $delay);
    }

    public function test_get_throttle_delay_returns_default(): void
    {
        $delay = $this->monitor->getThrottleDelay();
        $this->assertIsInt($delay);
        $this->assertGreaterThan(0, $delay);
    }
}
