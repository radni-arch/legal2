<?php

namespace Tests\Unit\Listeners;

use App\Events\CaseReadyForReview;
use App\Events\TextractJobGraphSynced;
use App\Listeners\CheckCaseCompleteness;
use App\Models\LegalCase;
use App\Models\TextractJob;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CheckCaseCompletenessTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Test: When all case jobs are synced, CaseReadyForReview event is fired.
     */
    public function test_fires_case_ready_for_review_when_all_jobs_synced(): void
    {
        $case = LegalCase::factory()->create();

        // Create three jobs for the same case, all with graph_sync_status = 'synced'
        TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'succeeded',
            'graph_sync_status' => 'synced',
        ]);
        TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'succeeded',
            'graph_sync_status' => 'synced',
        ]);
        // The triggering job - just synced
        $triggeringJob = TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'succeeded',
            'graph_sync_status' => 'synced',
        ]);

        // Verify database state before faking events
        $pendingJobs = TextractJob::where('case_id', $case->id)
            ->where('status', '!=', 'failed')
            ->where('graph_sync_status', '!=', 'synced')
            ->count();
        $this->assertEquals(0, $pendingJobs, 'Expected 0 pending jobs');

        // Fake events AFTER creating models
        Event::fake([CaseReadyForReview::class]);

        $event = new TextractJobGraphSynced($triggeringJob);
        $listener = new CheckCaseCompleteness();
        $listener->handle($event);

        // Note: $case->id is a ULID object, $event->caseId is cast to string
        // by the CaseReadyForReview constructor (public string $caseId)
        Event::assertDispatched(CaseReadyForReview::class, function ($event) use ($case) {
            return $event->caseId === (string) $case->id;
        });
    }

    /**
     * Test: When some case jobs are not yet synced, CaseReadyForReview is NOT fired.
     */
    public function test_does_not_fire_when_some_jobs_not_synced(): void
    {
        $case = LegalCase::factory()->create();

        // One synced job (the triggering one)
        $syncedJob = TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'succeeded',
            'graph_sync_status' => 'synced',
        ]);

        // One job still pending
        TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'succeeded',
            'graph_sync_status' => 'pending',
        ]);

        // Fake events AFTER creating models
        Event::fake([CaseReadyForReview::class]);

        $event = new TextractJobGraphSynced($syncedJob);
        $listener = new CheckCaseCompleteness();
        $listener->handle($event);

        Event::assertNotDispatched(CaseReadyForReview::class);
    }

    /**
     * Test: Job with no case_id does nothing (no error, no event).
     */
    public function test_does_nothing_when_job_has_no_case_id(): void
    {
        $job = TextractJob::factory()->create([
            'case_id' => null,
            'status' => 'succeeded',
            'graph_sync_status' => 'synced',
        ]);

        // Fake events AFTER creating models
        Event::fake([CaseReadyForReview::class]);

        $event = new TextractJobGraphSynced($job);
        $listener = new CheckCaseCompleteness();
        $listener->handle($event);

        Event::assertNotDispatched(CaseReadyForReview::class);
    }

    /**
     * Test: Failed jobs are excluded from the incomplete check
     * (they don't block case completion).
     */
    public function test_failed_jobs_do_not_block_case_completion(): void
    {
        $case = LegalCase::factory()->create();

        // One synced job (the triggering one)
        $syncedJob = TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'succeeded',
            'graph_sync_status' => 'synced',
        ]);

        // One failed job - should be excluded from the incomplete check
        TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'failed',
            'graph_sync_status' => 'pending',
        ]);

        // Fake events AFTER creating models
        Event::fake([CaseReadyForReview::class]);

        $event = new TextractJobGraphSynced($syncedJob);
        $listener = new CheckCaseCompleteness();
        $listener->handle($event);

        // CaseReadyForReview SHOULD fire because the only non-failed job is synced
        // Note: $case->id is a ULID object, $event->caseId is cast to string
        Event::assertDispatched(CaseReadyForReview::class, function ($event) use ($case) {
            return $event->caseId === (string) $case->id;
        });
    }
}
