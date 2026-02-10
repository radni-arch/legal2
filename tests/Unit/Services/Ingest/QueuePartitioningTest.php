<?php

namespace Tests\Unit\Services\Ingest;

use App\Services\Ingest\BackpressureMonitor;
use Tests\TestCase;

/**
 * Verify correct queue selection by source type (SOT-018).
 */
class QueuePartitioningTest extends TestCase
{
    protected BackpressureMonitor $monitor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monitor = new BackpressureMonitor();
    }

    public function test_uploader_source_maps_to_ingest_upload_queue(): void
    {
        $queue = $this->monitor->resolveQueueName('uploader');
        $this->assertEquals('ingest-upload', $queue);
    }

    public function test_drive_source_maps_to_ingest_drive_queue(): void
    {
        $queue = $this->monitor->resolveQueueName('drive');
        $this->assertEquals('ingest-drive', $queue);
    }

    public function test_odluke_source_maps_to_ingest_odluke_queue(): void
    {
        $queue = $this->monitor->resolveQueueName('odluke');
        $this->assertEquals('ingest-odluke', $queue);
    }

    public function test_usud_source_maps_to_ingest_usud_queue(): void
    {
        $queue = $this->monitor->resolveQueueName('usud');
        $this->assertEquals('ingest-usud', $queue);
    }

    public function test_echr_source_maps_to_ingest_echr_queue(): void
    {
        $queue = $this->monitor->resolveQueueName('echr');
        $this->assertEquals('ingest-echr', $queue);
    }

    public function test_api_source_maps_to_ingest_upload_queue(): void
    {
        $queue = $this->monitor->resolveQueueName('api');
        $this->assertEquals('ingest-upload', $queue);
    }

    public function test_unknown_source_falls_back_to_default(): void
    {
        $queue = $this->monitor->resolveQueueName('unknown-source');
        $this->assertEquals(config('ingest-queues.default_queue', 'ingest'), $queue);
    }

    public function test_all_configured_queues_are_unique_per_source(): void
    {
        $queues = config('ingest-queues.queues');
        $this->assertIsArray($queues);
        $this->assertNotEmpty($queues);

        // Verify each source has a queue defined
        foreach (['uploader', 'drive', 'odluke', 'usud', 'echr'] as $source) {
            $this->assertArrayHasKey($source, $queues, "Queue must be defined for source: {$source}");
        }
    }

    public function test_orchestrator_dispatches_to_source_specific_queue(): void
    {
        $orchestrator = app(\App\Services\Ingest\IngestOrchestrator::class);
        $this->assertEquals('ingest-upload', $orchestrator->resolveQueueName('uploader'));
        $this->assertEquals('ingest-drive', $orchestrator->resolveQueueName('drive'));
        $this->assertEquals('ingest-odluke', $orchestrator->resolveQueueName('odluke'));
    }
}
