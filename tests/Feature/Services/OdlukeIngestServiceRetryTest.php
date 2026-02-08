<?php

namespace Tests\Feature\Services;

use App\Jobs\IngestOdlukeDecision;
use App\Services\Odluke\OdlukeIngestService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class OdlukeIngestServiceRetryTest extends TestCase
{
    use UsesTestDatabase;

    protected OdlukeIngestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OdlukeIngestService::class);
        Log::spy();
    }

    /** @test */
    public function it_uses_sync_mode_by_default()
    {
        Queue::fake();

        // Note: This will actually try to fetch from odluke.hr
        // We're just testing the routing logic, not the full ingestion
        $result = $this->service->ingestByIds(['non-existent-id'], [
            'queue' => false,
        ]);

        // Verify no jobs were queued
        Queue::assertNothingPushed();

        // Verify we got a synchronous result structure
        $this->assertArrayHasKey('inserted', $result);
        $this->assertArrayNotHasKey('queued', $result);
    }

    /** @test */
    public function it_queues_jobs_when_queue_option_is_true()
    {
        Queue::fake();

        $result = $this->service->ingestByIds(['test-1', 'test-2', 'test-3'], [
            'queue' => true,
        ]);

        // Verify result structure
        $this->assertArrayHasKey('queued', $result);
        $this->assertArrayHasKey('mode', $result);
        $this->assertEquals(3, $result['queued']);
        $this->assertEquals('queued', $result['mode']);

        // Verify jobs were dispatched
        Queue::assertPushed(IngestOdlukeDecision::class, 3);

        Queue::assertPushed(IngestOdlukeDecision::class, function ($job) {
            return $job->decisionId === 'test-1';
        });

        Queue::assertPushed(IngestOdlukeDecision::class, function ($job) {
            return $job->decisionId === 'test-2';
        });

        Queue::assertPushed(IngestOdlukeDecision::class, function ($job) {
            return $job->decisionId === 'test-3';
        });
    }

    /** @test */
    public function it_dispatches_to_specified_queue_name()
    {
        Queue::fake();

        $this->service->ingestByIds(['test-queue'], [
            'queue' => true,
            'queue_name' => 'ingestion-priority',
        ]);

        Queue::assertPushedOn('ingestion-priority', IngestOdlukeDecision::class);
    }

    /** @test */
    public function it_uses_default_queue_when_not_specified()
    {
        Queue::fake();

        $this->service->ingestByIds(['test-default'], [
            'queue' => true,
        ]);

        Queue::assertPushedOn('default', IngestOdlukeDecision::class);
    }

    /** @test */
    public function it_passes_options_to_queued_job()
    {
        Queue::fake();

        $options = [
            'queue' => true,
            'force' => true,
            'sync_graph' => false,
            'custom_option' => 'value',
        ];

        $this->service->ingestByIds(['test-options'], $options);

        Queue::assertPushed(IngestOdlukeDecision::class, function ($job) use ($options) {
            return $job->options === $options;
        });
    }

    /** @test */
    public function it_passes_max_attempts_to_job()
    {
        Queue::fake();

        $this->service->ingestByIds(['test-attempts'], [
            'queue' => true,
            'max_attempts' => 10,
        ]);

        Queue::assertPushed(IngestOdlukeDecision::class, function ($job) {
            return $job->options['max_attempts'] === 10;
        });
    }

    /** @test */
    public function it_logs_queued_ingestions()
    {
        Queue::fake();

        $this->service->ingestByIds(['test-log-1', 'test-log-2'], [
            'queue' => true,
        ]);

        Log::shouldHaveReceived('info')
            ->with('Decision queued for ingestion', ['decision_id' => 'test-log-1']);

        Log::shouldHaveReceived('info')
            ->with('Decision queued for ingestion', ['decision_id' => 'test-log-2']);
    }

    /** @test */
    public function it_handles_empty_ids_array_in_queue_mode()
    {
        Queue::fake();

        $result = $this->service->ingestByIds([], [
            'queue' => true,
        ]);

        $this->assertEquals(0, $result['queued']);
        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_maintains_backward_compatibility_for_sync_ingestion()
    {
        // This test verifies that existing code calling ingestByIds without queue option
        // continues to work as before

        // We can't easily test the full sync flow without external dependencies,
        // but we can verify the routing logic works correctly

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('ingestByIds');

        // Verify the method still accepts the same signature
        $this->assertTrue($method->isPublic());
        $this->assertEquals(2, $method->getNumberOfParameters());
    }

    /** @test */
    public function it_routes_to_sync_when_queue_is_false()
    {
        Queue::fake();

        // This should use sync mode
        $result = $this->service->ingestByIds(['test-sync'], [
            'queue' => false,
        ]);

        Queue::assertNothingPushed();

        // Sync mode returns different structure
        $this->assertArrayNotHasKey('queued', $result);
    }

    /** @test */
    public function it_routes_to_sync_when_queue_option_is_missing()
    {
        Queue::fake();

        // No queue option = sync mode (backward compatible)
        $result = $this->service->ingestByIds(['test-no-option'], [
            'force' => true,
        ]);

        Queue::assertNothingPushed();
    }

    /** @test */
    public function queued_mode_returns_immediately()
    {
        Queue::fake();

        $startTime = microtime(true);

        $result = $this->service->ingestByIds(['test-1', 'test-2', 'test-3'], [
            'queue' => true,
        ]);

        $duration = microtime(true) - $startTime;

        // Queue mode should return almost immediately (< 100ms)
        $this->assertLessThan(0.1, $duration);

        // Verify we got the expected quick response
        $this->assertEquals(3, $result['queued']);
    }

    /** @test */
    public function it_creates_separate_job_per_decision()
    {
        Queue::fake();

        $this->service->ingestByIds(['id-1', 'id-2', 'id-3', 'id-4', 'id-5'], [
            'queue' => true,
        ]);

        // Each decision should get its own job for independent retry
        Queue::assertPushed(IngestOdlukeDecision::class, 5);
    }
}
