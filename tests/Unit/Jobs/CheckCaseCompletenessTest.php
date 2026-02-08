<?php

namespace Tests\Unit\Jobs;

use App\Events\CaseReadyForReview;
use App\Jobs\CheckCaseCompleteness;
use App\Models\LegalCase;
use App\Models\TextractJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CheckCaseCompletenessTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_implements_should_queue_interface()
    {
        $job = new CheckCaseCompleteness('case-123');

        $this->assertInstanceOf(ShouldQueue::class, $job);
    }

    /** @test */
    public function it_stores_case_id()
    {
        $job = new CheckCaseCompleteness('case-456');

        $this->assertEquals('case-456', $job->caseId);
    }

    /** @test */
    public function it_fires_event_when_all_jobs_synced()
    {
        $case = LegalCase::factory()->create();

        // Create all synced jobs for this case (before faking events)
        $job1 = TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'succeeded',
            'graph_sync_status' => 'synced',
        ]);

        $job2 = TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'succeeded',
            'graph_sync_status' => 'synced',
        ]);

        // Verify database state
        $totalJobs = TextractJob::where('case_id', $case->id)->count();
        $this->assertEquals(2, $totalJobs, 'Expected 2 total jobs for this case');

        $pendingJobs = TextractJob::where('case_id', $case->id)
            ->where('status', '!=', 'failed')
            ->where('graph_sync_status', '!=', 'synced')
            ->count();
        $this->assertEquals(0, $pendingJobs, 'Expected 0 pending jobs');

        // Fake events AFTER creating models
        Event::fake([CaseReadyForReview::class]);

        $checkJob = new CheckCaseCompleteness($case->id);
        $checkJob->handle();

        Event::assertDispatched(CaseReadyForReview::class);
    }

    /** @test */
    public function it_does_not_fire_event_when_jobs_pending()
    {
        Event::fake([CaseReadyForReview::class]);

        $case = LegalCase::factory()->create();

        // Create one synced and one pending job
        TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'succeeded',
            'graph_sync_status' => 'synced',
        ]);

        TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'succeeded',
            'graph_sync_status' => 'pending', // Not synced yet
        ]);

        $job = new CheckCaseCompleteness($case->id);
        $job->handle();

        Event::assertNotDispatched(CaseReadyForReview::class);
    }

    /** @test */
    public function it_does_not_fire_event_when_jobs_processing()
    {
        Event::fake([CaseReadyForReview::class]);

        $case = LegalCase::factory()->create();

        TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'succeeded',
            'graph_sync_status' => 'processing', // Still processing
        ]);

        $job = new CheckCaseCompleteness($case->id);
        $job->handle();

        Event::assertNotDispatched(CaseReadyForReview::class);
    }

    /** @test */
    public function it_excludes_failed_jobs_from_count()
    {
        $case = LegalCase::factory()->create();

        // Create synced job
        TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'succeeded',
            'graph_sync_status' => 'synced',
        ]);

        // Create failed job - should be excluded from pending count
        TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'failed', // Failed job
            'graph_sync_status' => 'pending',
        ]);

        // Fake events AFTER creating models
        Event::fake([CaseReadyForReview::class]);

        $job = new CheckCaseCompleteness($case->id);
        $job->handle();

        // Should fire because the failed job is excluded
        Event::assertDispatched(CaseReadyForReview::class);
    }

    /** @test */
    public function it_handles_case_with_no_jobs()
    {
        Event::fake([CaseReadyForReview::class]);

        $case = LegalCase::factory()->create();

        // No jobs for this case
        $job = new CheckCaseCompleteness($case->id);
        $job->handle();

        // Should fire because there are no pending jobs (0 pending means complete)
        Event::assertDispatched(CaseReadyForReview::class);
    }

    /** @test */
    public function it_only_checks_jobs_for_specified_case()
    {
        $case1 = LegalCase::factory()->create();
        $case2 = LegalCase::factory()->create();

        // Case 1: all synced
        TextractJob::factory()->create([
            'case_id' => $case1->id,
            'status' => 'succeeded',
            'graph_sync_status' => 'synced',
        ]);

        // Case 2: has pending job (should not affect case 1)
        TextractJob::factory()->create([
            'case_id' => $case2->id,
            'status' => 'succeeded',
            'graph_sync_status' => 'pending',
        ]);

        // Fake events AFTER creating models
        Event::fake([CaseReadyForReview::class]);

        $job = new CheckCaseCompleteness($case1->id);
        $job->handle();

        // Case 1 should be complete regardless of Case 2 status
        Event::assertDispatched(CaseReadyForReview::class);
    }

    /** @test */
    public function it_can_be_dispatched_to_queue()
    {
        Queue::fake();

        CheckCaseCompleteness::dispatch('case-queue-test');

        Queue::assertPushed(CheckCaseCompleteness::class, function ($job) {
            return $job->caseId === 'case-queue-test';
        });
    }

    /** @test */
    public function it_can_be_dispatched_with_delay()
    {
        Queue::fake();

        CheckCaseCompleteness::dispatch('case-delay-test')
            ->delay(now()->addSeconds(5));

        Queue::assertPushed(CheckCaseCompleteness::class, function ($job) {
            return $job->caseId === 'case-delay-test';
        });
    }

    /** @test */
    public function it_uses_required_traits()
    {
        $reflection = new \ReflectionClass(CheckCaseCompleteness::class);
        $traits = $reflection->getTraitNames();

        $this->assertContains('Illuminate\Foundation\Bus\Dispatchable', $traits);
        $this->assertContains('Illuminate\Queue\InteractsWithQueue', $traits);
        $this->assertContains('Illuminate\Bus\Queueable', $traits);
        $this->assertContains('Illuminate\Queue\SerializesModels', $traits);
    }

    /** @test */
    public function it_serializes_correctly()
    {
        $job = new CheckCaseCompleteness('case-serialize');

        $serialized = serialize($job);
        $unserialized = unserialize($serialized);

        $this->assertEquals('case-serialize', $unserialized->caseId);
    }
}
