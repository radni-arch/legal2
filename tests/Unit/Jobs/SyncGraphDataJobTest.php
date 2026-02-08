<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SyncGraphDataJob;
use App\Services\GraphRagService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SyncGraphDataJobTest extends TestCase
{
    protected function tearDown(): void
    {
        // Count Mockery expectations as PHPUnit assertions to avoid risky test warnings
        $container = Mockery::getContainer();
        if ($container) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_implements_should_queue_interface(): void
    {
        $job = new SyncGraphDataJob('law');

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    /** @test */
    public function it_stores_sync_type_and_id(): void
    {
        $job = new SyncGraphDataJob('case', 123);

        $this->assertEquals('case', $job->syncType);
        $this->assertEquals(123, $job->id);
    }

    /** @test */
    public function it_syncs_all_laws_when_type_is_law_and_no_id(): void
    {
        $mockGraphRag = Mockery::mock(GraphRagService::class);
        $mockGraphRag->shouldReceive('syncAllLaws')
            ->once()
            ->andReturn(['synced' => 10, 'errors' => 0]);

        $job = new SyncGraphDataJob('law');
        $job->handle($mockGraphRag);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_syncs_single_law_when_id_is_provided(): void
    {
        $mockGraphRag = Mockery::mock(GraphRagService::class);
        $mockGraphRag->shouldReceive('syncLaw')
            ->once()
            ->with(5)
            ->andReturn(['synced' => 1, 'errors' => 0]);

        $job = new SyncGraphDataJob('law', 5);
        $job->handle($mockGraphRag);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_syncs_all_cases_when_type_is_case_and_no_id(): void
    {
        $mockGraphRag = Mockery::mock(GraphRagService::class);
        $mockGraphRag->shouldReceive('syncAllCases')
            ->once()
            ->andReturn(['synced' => 25, 'errors' => 0]);

        $job = new SyncGraphDataJob('case');
        $job->handle($mockGraphRag);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_syncs_single_case_when_id_is_provided(): void
    {
        $mockGraphRag = Mockery::mock(GraphRagService::class);
        $mockGraphRag->shouldReceive('syncCase')
            ->once()
            ->with('test-case-123')
            ->andReturn(['synced' => 1, 'errors' => 0]);

        $job = new SyncGraphDataJob('case', 'test-case-123');
        $job->handle($mockGraphRag);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_syncs_all_decisions_when_type_is_decision(): void
    {
        $mockGraphRag = Mockery::mock(GraphRagService::class);
        $mockGraphRag->shouldReceive('syncAllCourtDecisions')
            ->once()
            ->andReturn(['synced' => 50, 'errors' => 2]);

        $job = new SyncGraphDataJob('decision');
        $job->handle($mockGraphRag);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_syncs_single_decision_when_id_is_provided(): void
    {
        $mockGraphRag = Mockery::mock(GraphRagService::class);
        $mockGraphRag->shouldReceive('syncCourtDecision')
            ->once()
            ->with(99)
            ->andReturn(['synced' => 1, 'errors' => 0]);

        $job = new SyncGraphDataJob('decision', 99);
        $job->handle($mockGraphRag);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_syncs_all_textract_jobs_when_type_is_textract(): void
    {
        $mockGraphRag = Mockery::mock(GraphRagService::class);
        $mockGraphRag->shouldReceive('syncAllTextractJobs')
            ->once()
            ->andReturn(['synced' => 100, 'errors' => 5]);

        $job = new SyncGraphDataJob('textract');
        $job->handle($mockGraphRag);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_syncs_single_textract_job_when_id_is_provided(): void
    {
        $mockGraphRag = Mockery::mock(GraphRagService::class);
        $mockGraphRag->shouldReceive('syncTextractJob')
            ->once()
            ->with(777)
            ->andReturn(['synced' => 1, 'errors' => 0]);

        $job = new SyncGraphDataJob('textract', 777);
        $job->handle($mockGraphRag);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_syncs_all_types_when_type_is_all(): void
    {
        $mockGraphRag = Mockery::mock(GraphRagService::class);
        $mockGraphRag->shouldReceive('syncAllLaws')
            ->once()
            ->andReturn(['synced' => 10, 'errors' => 0]);
        $mockGraphRag->shouldReceive('syncAllCases')
            ->once()
            ->andReturn(['synced' => 20, 'errors' => 1]);
        $mockGraphRag->shouldReceive('syncAllCourtDecisions')
            ->once()
            ->andReturn(['synced' => 30, 'errors' => 2]);
        $mockGraphRag->shouldReceive('syncAllTextractJobs')
            ->once()
            ->andReturn(['synced' => 40, 'errors' => 3]);

        $job = new SyncGraphDataJob('all');
        $job->handle($mockGraphRag);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_throws_exception_for_invalid_sync_type(): void
    {
        $mockGraphRag = Mockery::mock(GraphRagService::class);

        $job = new SyncGraphDataJob('invalid-type');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sync type: invalid-type');

        $job->handle($mockGraphRag);
    }

    /** @test */
    public function it_logs_on_successful_completion(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('SyncGraphDataJob started', Mockery::type('array'));

        Log::shouldReceive('info')
            ->once()
            ->with('SyncGraphDataJob completed', Mockery::type('array'));

        $mockGraphRag = Mockery::mock(GraphRagService::class);
        $mockGraphRag->shouldReceive('syncAllLaws')
            ->once()
            ->andReturn(['synced' => 5, 'errors' => 0]);

        $job = new SyncGraphDataJob('law');
        $job->handle($mockGraphRag);
    }

    /** @test */
    public function it_logs_and_rethrows_on_exception(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('SyncGraphDataJob started', Mockery::type('array'));

        Log::shouldReceive('error')
            ->once()
            ->with('SyncGraphDataJob failed', Mockery::type('array'));

        $mockGraphRag = Mockery::mock(GraphRagService::class);
        $mockGraphRag->shouldReceive('syncAllCases')
            ->once()
            ->andThrow(new \Exception('Sync failed'));

        $job = new SyncGraphDataJob('case');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Sync failed');

        $job->handle($mockGraphRag);
    }

    /** @test */
    public function it_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        SyncGraphDataJob::dispatch('law', 10);

        Queue::assertPushed(SyncGraphDataJob::class, function ($job) {
            return $job->syncType === 'law'
                && $job->id === 10;
        });
    }

    /** @test */
    public function it_has_correct_tags(): void
    {
        $job = new SyncGraphDataJob('decision', 123);

        $tags = $job->tags();

        $this->assertContains('graph:sync', $tags);
        $this->assertContains('graph:sync:decision', $tags);
        $this->assertContains('id:123', $tags);
    }

    /** @test */
    public function it_has_batch_tag_when_no_id_provided(): void
    {
        $job = new SyncGraphDataJob('case');

        $tags = $job->tags();

        $this->assertContains('graph:sync', $tags);
        $this->assertContains('graph:sync:case', $tags);
        $this->assertContains('batch', $tags);
    }

    /** @test */
    public function failed_method_logs_permanent_failure(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('SyncGraphDataJob failed permanently', Mockery::on(function ($context) {
                return $context['sync_type'] === 'textract'
                    && $context['id'] === 555
                    && $context['error'] === 'Permanent failure';
            }));

        $job = new SyncGraphDataJob('textract', 555);
        $exception = new \Exception('Permanent failure');

        $job->failed($exception);
    }

    /** @test */
    public function it_has_timeout_of_3600_seconds(): void
    {
        $job = new SyncGraphDataJob('all');

        $this->assertEquals(3600, $job->timeout);
    }

    /** @test */
    public function it_has_2_retry_attempts(): void
    {
        $job = new SyncGraphDataJob('law');

        $this->assertEquals(2, $job->tries);
    }
}
