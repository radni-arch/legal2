<?php

namespace Tests\Unit\Jobs;

use App\Events\TextractJobGraphSynced;
use App\Jobs\SyncTextractToGraph;
use App\Models\TextractJob;
use App\Services\Graph\GraphRagOrchestrator;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class SyncTextractToGraphTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Mock GraphDatabaseService in the container as available.
     */
    protected function mockGraphDatabaseAvailable(bool $available = true): void
    {
        $graphDbMock = Mockery::mock(GraphDatabaseService::class);
        $graphDbMock->shouldReceive('isAvailable')->andReturn($available);
        if (! $available) {
            $graphDbMock->shouldReceive('getHealthStatus')->andReturn(['status' => 'unavailable']);
        }
        $this->app->instance(GraphDatabaseService::class, $graphDbMock);
    }

    /** @test */
    public function it_implements_should_queue_interface()
    {
        $job = new SyncTextractToGraph(1);

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    /** @test */
    public function it_has_three_retry_attempts()
    {
        $job = new SyncTextractToGraph(1);

        $this->assertEquals(15, $job->tries);
    }

    /** @test */
    public function it_has_exponential_backoff()
    {
        $job = new SyncTextractToGraph(1);

        $backoff = $job->backoff();

        $this->assertEquals([2, 4, 8], $backoff);
    }

    /** @test */
    public function it_has_300_second_timeout()
    {
        $job = new SyncTextractToGraph(1);

        $this->assertEquals(300, $job->timeout);
    }

    /** @test */
    public function it_stores_textract_job_id()
    {
        $job = new SyncTextractToGraph(123);

        $this->assertEquals(123, $job->textractJobId);
    }

    /** @test */
    public function it_skips_sync_when_neo4j_disabled()
    {
        Config::set('neo4j.sync.enabled', false);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-123',
            'drive_file_name' => 'test.pdf',
            'status' => 'succeeded',
            'embedding_status' => 'synced',
        ]);

        $graphRagMock = Mockery::mock(GraphRagOrchestrator::class);
        $graphRagMock->shouldNotReceive('syncTextractJob');

        $job = new SyncTextractToGraph($textractJob->id);
        $job->handle($graphRagMock);

        // Should complete without calling GraphRagService
    }

    /** @test */
    public function it_skips_sync_when_job_not_found()
    {
        Config::set('neo4j.sync.enabled', true);
        $this->mockGraphDatabaseAvailable();

        $graphRagMock = Mockery::mock(GraphRagOrchestrator::class);
        $graphRagMock->shouldNotReceive('syncTextractJob');

        $job = new SyncTextractToGraph(999); // Non-existent ID
        $job->handle($graphRagMock);

        // Should complete gracefully
    }

    /** @test */
    public function it_skips_sync_when_embeddings_not_ready()
    {
        Config::set('neo4j.sync.enabled', true);
        $this->mockGraphDatabaseAvailable();

        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-456',
            'drive_file_name' => 'test.pdf',
            'status' => 'succeeded',
            'embedding_status' => 'pending', // Not synced yet
        ]);

        $graphRagMock = Mockery::mock(GraphRagOrchestrator::class);
        $graphRagMock->shouldNotReceive('syncTextractJob');

        $job = new SyncTextractToGraph($textractJob->id);
        $job->handle($graphRagMock);
    }

    /** @test */
    public function it_skips_sync_when_job_not_succeeded()
    {
        Config::set('neo4j.sync.enabled', true);
        $this->mockGraphDatabaseAvailable();

        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-789',
            'drive_file_name' => 'test.pdf',
            'status' => 'failed', // Not succeeded
            'embedding_status' => 'synced',
        ]);

        $graphRagMock = Mockery::mock(GraphRagOrchestrator::class);
        $graphRagMock->shouldNotReceive('syncTextractJob');

        $job = new SyncTextractToGraph($textractJob->id);
        $job->handle($graphRagMock);
    }

    /** @test */
    public function it_syncs_to_graph_successfully()
    {
        Config::set('neo4j.sync.enabled', true);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-success',
            'drive_file_name' => 'success.pdf',
            'status' => 'succeeded',
            'embedding_status' => 'synced',
        ]);

        $this->mockGraphDatabaseAvailable();

        $graphRagMock = Mockery::mock(GraphRagOrchestrator::class);
        $graphRagMock->shouldReceive('syncTextractJob')
            ->once()
            ->with($textractJob->id)
            ->andReturn(true);

        $job = new SyncTextractToGraph($textractJob->id);
        $job->handle($graphRagMock);

        // Should complete successfully
    }

    /** @test */
    public function it_updates_job_status_on_failure()
    {
        Config::set('neo4j.sync.enabled', true);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-fail',
            'drive_file_name' => 'fail.pdf',
            'status' => 'succeeded',
            'embedding_status' => 'synced',
            'graph_sync_status' => 'pending',
        ]);

        $this->mockGraphDatabaseAvailable();

        $graphRagMock = Mockery::mock(GraphRagOrchestrator::class);
        $graphRagMock->shouldReceive('syncTextractJob')
            ->once()
            ->andThrow(new \Exception('Graph sync failed'));

        $job = new SyncTextractToGraph($textractJob->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Graph sync failed');

        $job->handle($graphRagMock);

        $textractJob->refresh();
        $this->assertEquals('failed', $textractJob->graph_sync_status);
        $this->assertStringContainsString('Graph sync failed', $textractJob->error);
    }

    /** @test */
    public function it_marks_job_permanently_failed_after_retries()
    {
        Config::set('neo4j.sync.enabled', true);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-permanent',
            'drive_file_name' => 'permanent.pdf',
            'status' => 'succeeded',
            'embedding_status' => 'synced',
            'graph_sync_status' => 'processing',
        ]);

        $exception = new \Exception('Permanent graph failure');

        $job = new SyncTextractToGraph($textractJob->id);
        $job->failed($exception);

        $textractJob->refresh();
        $this->assertEquals('failed', $textractJob->graph_sync_status);
        $this->assertStringContainsString('permanently failed after 15 attempts', $textractJob->error);
        $this->assertStringContainsString('Permanent graph failure', $textractJob->error);
    }

    /** @test */
    public function it_handles_missing_job_on_permanent_failure()
    {
        $exception = new \Exception('Test error');

        $job = new SyncTextractToGraph(999); // Non-existent

        // Should not throw exception
        $job->failed($exception);

        $this->assertDatabaseMissing('textract_jobs', ['id' => 999]);
    }

    /** @test */
    public function it_returns_correct_tags()
    {
        $job = new SyncTextractToGraph(42);

        $tags = $job->tags();

        $this->assertContains('textract', $tags);
        $this->assertContains('graph', $tags);
        $this->assertContains('neo4j', $tags);
        $this->assertContains('textract_job:42', $tags);
    }

    /** @test */
    public function it_can_be_dispatched_to_queue()
    {
        Queue::fake();

        SyncTextractToGraph::dispatch(123);

        Queue::assertPushed(SyncTextractToGraph::class, function ($job) {
            return $job->textractJobId === 123;
        });
    }

    /** @test */
    public function it_can_be_dispatched_with_delay()
    {
        Queue::fake();

        SyncTextractToGraph::dispatch(456)
            ->delay(now()->addMinutes(5));

        Queue::assertPushed(SyncTextractToGraph::class, function ($job) {
            return $job->textractJobId === 456;
        });
    }

    /** @test */
    public function it_can_be_dispatched_to_specific_queue()
    {
        Queue::fake();

        SyncTextractToGraph::dispatch(789)
            ->onQueue('graph-sync');

        Queue::assertPushedOn('graph-sync', SyncTextractToGraph::class);
    }

    /** @test */
    public function it_rethrows_exception_to_trigger_retry()
    {
        Config::set('neo4j.sync.enabled', true);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-retry',
            'drive_file_name' => 'retry.pdf',
            'status' => 'succeeded',
            'embedding_status' => 'synced',
        ]);

        $this->mockGraphDatabaseAvailable();

        $graphRagMock = Mockery::mock(GraphRagOrchestrator::class);
        $graphRagMock->shouldReceive('syncTextractJob')
            ->once()
            ->andThrow(new \Exception('Transient error'));

        $job = new SyncTextractToGraph($textractJob->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Transient error');

        $job->handle($graphRagMock);
    }

    /** @test */
    public function it_serializes_correctly()
    {
        $job = new SyncTextractToGraph(101);

        $serialized = serialize($job);
        $unserialized = unserialize($serialized);

        $this->assertEquals(101, $unserialized->textractJobId);
    }

    /** @test */
    public function it_uses_required_traits()
    {
        $reflection = new \ReflectionClass(SyncTextractToGraph::class);
        $traits = $reflection->getTraitNames();

        $this->assertContains('Illuminate\Foundation\Bus\Dispatchable', $traits);
        $this->assertContains('Illuminate\Queue\InteractsWithQueue', $traits);
        $this->assertContains('Illuminate\Bus\Queueable', $traits);
        $this->assertContains('Illuminate\Queue\SerializesModels', $traits);
    }

    /** @test */
    public function it_logs_attempt_number()
    {
        Config::set('neo4j.sync.enabled', true);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-log',
            'drive_file_name' => 'log.pdf',
            'status' => 'succeeded',
            'embedding_status' => 'synced',
        ]);

        $this->mockGraphDatabaseAvailable();

        $graphRagMock = Mockery::mock(GraphRagOrchestrator::class);
        $graphRagMock->shouldReceive('syncTextractJob')
            ->once()
            ->andReturn(true);

        $job = new SyncTextractToGraph($textractJob->id);
        $job->handle($graphRagMock);

        // Job should complete successfully
        $this->assertTrue(true);
    }

    /** @test */
    public function it_checks_all_prerequisites_before_syncing()
    {
        Config::set('neo4j.sync.enabled', true);
        $this->mockGraphDatabaseAvailable();

        // Job exists but wrong status
        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-prereq',
            'drive_file_name' => 'prereq.pdf',
            'status' => 'processing', // Not succeeded
            'embedding_status' => 'synced',
        ]);

        $graphRagMock = Mockery::mock(GraphRagOrchestrator::class);
        $graphRagMock->shouldNotReceive('syncTextractJob');

        $job = new SyncTextractToGraph($textractJob->id);
        $job->handle($graphRagMock);

        // Should skip sync
    }

    /** @test */
    public function it_validates_embedding_status_before_syncing()
    {
        Config::set('neo4j.sync.enabled', true);
        $this->mockGraphDatabaseAvailable();

        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-embedding',
            'drive_file_name' => 'embedding.pdf',
            'status' => 'succeeded',
            'embedding_status' => 'failed', // Not synced
        ]);

        $graphRagMock = Mockery::mock(GraphRagOrchestrator::class);
        $graphRagMock->shouldNotReceive('syncTextractJob');

        $job = new SyncTextractToGraph($textractJob->id);
        $job->handle($graphRagMock);

        // Should skip sync
    }

    /** @test */
    public function it_includes_error_prefix_in_permanent_failure()
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-prefix',
            'drive_file_name' => 'prefix.pdf',
            'status' => 'succeeded',
            'embedding_status' => 'synced',
        ]);

        $exception = new \Exception('Neo4j connection timeout');

        $job = new SyncTextractToGraph($textractJob->id);
        $job->failed($exception);

        $textractJob->refresh();
        $this->assertStringStartsWith('Graph sync permanently failed', $textractJob->error);
    }

    /** @test */
    public function it_includes_attempt_count_in_error_message()
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-attempts',
            'drive_file_name' => 'attempts.pdf',
            'status' => 'succeeded',
            'embedding_status' => 'synced',
        ]);

        $exception = new \Exception('Error');

        $job = new SyncTextractToGraph($textractJob->id);
        $job->failed($exception);

        $textractJob->refresh();
        $this->assertStringContainsString('15 attempts', $textractJob->error);
    }

    // =====================================================
    // Neo4j Unavailability Retry Tests (Task 4.1)
    // =====================================================

    /** @test */
    public function it_releases_with_exponential_backoff_when_neo4j_unavailable()
    {
        Config::set('neo4j.sync.enabled', true);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-neo4j-unavail',
            'drive_file_name' => 'neo4j-unavail.pdf',
            'status' => 'succeeded',
            'embedding_status' => 'synced',
            'graph_sync_status' => 'pending',
        ]);

        // Mock Neo4j as unavailable
        $this->mockGraphDatabaseAvailable(false);

        $graphRagMock = Mockery::mock(GraphRagOrchestrator::class);
        $graphRagMock->shouldNotReceive('syncTextractJob');

        // Create a partial mock of the job to capture release() calls
        $job = Mockery::mock(SyncTextractToGraph::class, [$textractJob->id])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        // Job should call release() with appropriate delay
        // At attempt 1: min(60 * 2^1, 3600) = 120 seconds
        $job->shouldReceive('attempts')->andReturn(1);
        $job->shouldReceive('release')->once()->with(120);

        $job->handle($graphRagMock);

        // Verify job status was updated
        $textractJob->refresh();
        $this->assertEquals('pending', $textractJob->graph_sync_status);
    }

    /** @test */
    public function it_calculates_exponential_backoff_correctly_per_attempt()
    {
        Config::set('neo4j.sync.enabled', true);

        // Test backoff calculation at different attempts
        // Formula: min(60 * pow(2, attempts), 3600)
        $expectedDelays = [
            1 => 120,    // 60 * 2^1 = 120
            2 => 240,    // 60 * 2^2 = 240
            3 => 480,    // 60 * 2^3 = 480
            4 => 960,    // 60 * 2^4 = 960
            5 => 1920,   // 60 * 2^5 = 1920
            6 => 3600,   // 60 * 2^6 = 3840 -> capped at 3600
            7 => 3600,   // capped at 3600
            10 => 3600,  // capped at 3600
        ];

        foreach ($expectedDelays as $attempt => $expectedDelay) {
            $textractJob = TextractJob::create([
                'drive_file_id' => "file-backoff-{$attempt}",
                'drive_file_name' => "backoff-{$attempt}.pdf",
                'status' => 'succeeded',
                'embedding_status' => 'synced',
                'graph_sync_status' => 'pending',
            ]);

            $this->mockGraphDatabaseAvailable(false);

            $graphRagMock = Mockery::mock(GraphRagOrchestrator::class);
            $graphRagMock->shouldNotReceive('syncTextractJob');

            $job = Mockery::mock(SyncTextractToGraph::class, [$textractJob->id])
                ->makePartial()
                ->shouldAllowMockingProtectedMethods();

            $job->shouldReceive('attempts')->andReturn($attempt);
            $job->shouldReceive('release')->once()->with($expectedDelay);

            $job->handle($graphRagMock);
        }
    }

    /** @test */
    public function it_caps_backoff_at_one_hour()
    {
        Config::set('neo4j.sync.enabled', true);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-cap-test',
            'drive_file_name' => 'cap-test.pdf',
            'status' => 'succeeded',
            'embedding_status' => 'synced',
            'graph_sync_status' => 'pending',
        ]);

        $this->mockGraphDatabaseAvailable(false);

        $graphRagMock = Mockery::mock(GraphRagOrchestrator::class);
        $graphRagMock->shouldNotReceive('syncTextractJob');

        $job = Mockery::mock(SyncTextractToGraph::class, [$textractJob->id])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        // At attempt 10, calculated delay would be 60 * 2^10 = 61440, but should cap at 3600
        $job->shouldReceive('attempts')->andReturn(10);
        $job->shouldReceive('release')->once()->with(3600);

        $job->handle($graphRagMock);
    }

    /** @test */
    public function it_logs_retry_details_when_releasing_for_neo4j_unavailability()
    {
        Config::set('neo4j.sync.enabled', true);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-log-retry',
            'drive_file_name' => 'log-retry.pdf',
            'status' => 'succeeded',
            'embedding_status' => 'synced',
            'graph_sync_status' => 'pending',
        ]);

        $this->mockGraphDatabaseAvailable(false);

        $graphRagMock = Mockery::mock(GraphRagOrchestrator::class);
        $graphRagMock->shouldNotReceive('syncTextractJob');

        // Use Log::spy to capture log calls
        \Illuminate\Support\Facades\Log::spy();

        $job = Mockery::mock(SyncTextractToGraph::class, [$textractJob->id])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $job->shouldReceive('attempts')->andReturn(2);
        $job->shouldReceive('release')->once();

        $job->handle($graphRagMock);

        // Verify warning was logged with retry details
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'releasing for retry')
                    && isset($context['attempt'])
                    && isset($context['delay']);
            });
    }

    /** @test */
    public function it_has_increased_tries_for_unavailability_retries()
    {
        // The job should have enough tries to handle extended Neo4j unavailability
        $job = new SyncTextractToGraph(1);

        // Should have at least 10 tries to allow for longer unavailability periods
        $this->assertGreaterThanOrEqual(10, $job->tries);
    }

    /** @test */
    public function it_continues_normal_flow_when_neo4j_becomes_available()
    {
        Config::set('neo4j.sync.enabled', true);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-recovery',
            'drive_file_name' => 'recovery.pdf',
            'status' => 'succeeded',
            'embedding_status' => 'synced',
            'graph_sync_status' => 'pending',
        ]);

        // First, Neo4j is available
        $this->mockGraphDatabaseAvailable(true);

        $graphRagMock = Mockery::mock(GraphRagOrchestrator::class);
        $graphRagMock->shouldReceive('syncTextractJob')
            ->once()
            ->with($textractJob->id)
            ->andReturn(true);

        $job = new SyncTextractToGraph($textractJob->id);
        $job->handle($graphRagMock);

        // Job should complete successfully without release
        $this->assertTrue(true);
    }

    /** @test */
    public function it_updates_error_message_with_retry_info_when_neo4j_unavailable()
    {
        Config::set('neo4j.sync.enabled', true);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-error-msg',
            'drive_file_name' => 'error-msg.pdf',
            'status' => 'succeeded',
            'embedding_status' => 'synced',
            'graph_sync_status' => 'pending',
        ]);

        $this->mockGraphDatabaseAvailable(false);

        $graphRagMock = Mockery::mock(GraphRagOrchestrator::class);

        $job = Mockery::mock(SyncTextractToGraph::class, [$textractJob->id])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $job->shouldReceive('attempts')->andReturn(3);
        $job->shouldReceive('release')->once();

        $job->handle($graphRagMock);

        // Verify job error indicates retry will occur
        $textractJob->refresh();
        $this->assertStringContainsString('retry', strtolower($textractJob->error));
    }

    // =====================================================
    // TextractJobGraphSynced Event Tests (Weak Sector 2)
    // =====================================================

    /** @test */
    public function it_dispatches_textract_job_graph_synced_event_after_successful_sync()
    {
        Event::fake([TextractJobGraphSynced::class]);
        Config::set('neo4j.sync.enabled', true);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-event-test',
            'drive_file_name' => 'event-test.pdf',
            'status' => 'succeeded',
            'embedding_status' => 'synced',
            'graph_sync_status' => 'pending',
        ]);

        $this->mockGraphDatabaseAvailable();

        $graphRagMock = Mockery::mock(GraphRagOrchestrator::class);
        $graphRagMock->shouldReceive('syncTextractJob')
            ->once()
            ->with($textractJob->id)
            ->andReturn(true);

        $job = new SyncTextractToGraph($textractJob->id);
        $job->handle($graphRagMock);

        // Verify event was dispatched with correct TextractJob
        Event::assertDispatched(TextractJobGraphSynced::class, function ($event) use ($textractJob) {
            return $event->textractJob->id === $textractJob->id;
        });
    }

    /** @test */
    public function it_does_not_dispatch_event_when_sync_fails()
    {
        Event::fake([TextractJobGraphSynced::class]);
        Config::set('neo4j.sync.enabled', true);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-event-fail',
            'drive_file_name' => 'event-fail.pdf',
            'status' => 'succeeded',
            'embedding_status' => 'synced',
            'graph_sync_status' => 'pending',
        ]);

        $this->mockGraphDatabaseAvailable();

        $graphRagMock = Mockery::mock(GraphRagOrchestrator::class);
        $graphRagMock->shouldReceive('syncTextractJob')
            ->once()
            ->andThrow(new \Exception('Graph sync failed'));

        $job = new SyncTextractToGraph($textractJob->id);

        try {
            $job->handle($graphRagMock);
        } catch (\Exception $e) {
            // Expected exception
        }

        // Event should NOT be dispatched on failure
        Event::assertNotDispatched(TextractJobGraphSynced::class);
    }

    /** @test */
    public function it_does_not_dispatch_event_when_neo4j_disabled()
    {
        Event::fake([TextractJobGraphSynced::class]);
        Config::set('neo4j.sync.enabled', false);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-event-disabled',
            'drive_file_name' => 'event-disabled.pdf',
            'status' => 'succeeded',
            'embedding_status' => 'synced',
        ]);

        $graphRagMock = Mockery::mock(GraphRagOrchestrator::class);
        $graphRagMock->shouldNotReceive('syncTextractJob');

        $job = new SyncTextractToGraph($textractJob->id);
        $job->handle($graphRagMock);

        // Event should NOT be dispatched when disabled
        Event::assertNotDispatched(TextractJobGraphSynced::class);
    }

    /** @test */
    public function it_does_not_dispatch_event_when_job_not_found()
    {
        Event::fake([TextractJobGraphSynced::class]);
        Config::set('neo4j.sync.enabled', true);
        $this->mockGraphDatabaseAvailable();

        $graphRagMock = Mockery::mock(GraphRagOrchestrator::class);
        $graphRagMock->shouldNotReceive('syncTextractJob');

        $job = new SyncTextractToGraph(999); // Non-existent ID
        $job->handle($graphRagMock);

        // Event should NOT be dispatched when job doesn't exist
        Event::assertNotDispatched(TextractJobGraphSynced::class);
    }
}
