<?php

namespace Tests\Unit\Jobs;

use App\Agents\LegalArtilleryOrchestrator;
use App\Jobs\GenerateLegalDocumentJob;
use App\Models\DocumentGenerationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SOT-006: Tests that GenerateLegalDocumentJob persists send options
 * to canonical DB columns via setSendOptions().
 *
 * Verifies:
 * - Job persists send options to DB columns when provided
 * - Job sets dispatch_status to 'pending_dispatch' when send_email is true
 * - Job handles null sendOptions gracefully (no error, no DB changes)
 * - Job handles sendOptions with send_email=false (no pending_dispatch status)
 */
class GenerateLegalDocumentJobSendOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function createRun(array $overrides = []): DocumentGenerationRun
    {
        return DocumentGenerationRun::factory()->create(array_merge([
            'status' => 'pending',
        ], $overrides));
    }

    public function test_persists_send_options_to_db_columns(): void
    {
        $run = $this->createRun();

        $job = new GenerateLegalDocumentJob(
            runId: $run->id,
            profileKey: 'predsjednik_suda',
            userId: $run->user_id,
            sendOptions: [
                'send_email' => true,
                'as_draft' => true,
                'to_email' => 'test@example.com',
            ],
        );

        // Mock the orchestrator so handle() doesn't actually run AI generation
        $orchestrator = $this->mock(LegalArtilleryOrchestrator::class);
        $orchestrator->shouldReceive('generate')->once();

        $job->handle($orchestrator);

        $run->refresh();

        $this->assertTrue($run->send_email);
        $this->assertTrue($run->as_draft);
        $this->assertSame('test@example.com', $run->to_email);
    }

    public function test_sets_dispatch_status_pending_when_send_email_true(): void
    {
        $run = $this->createRun();

        $job = new GenerateLegalDocumentJob(
            runId: $run->id,
            profileKey: 'predsjednik_suda',
            userId: $run->user_id,
            sendOptions: [
                'send_email' => true,
                'as_draft' => false,
                'to_email' => null,
            ],
        );

        $orchestrator = $this->mock(LegalArtilleryOrchestrator::class);
        $orchestrator->shouldReceive('generate')->once();

        $job->handle($orchestrator);

        $run->refresh();

        $this->assertSame('pending_dispatch', $run->dispatch_status);
    }

    public function test_handles_null_send_options_gracefully(): void
    {
        $run = $this->createRun();

        $job = new GenerateLegalDocumentJob(
            runId: $run->id,
            profileKey: 'predsjednik_suda',
            userId: $run->user_id,
            sendOptions: null,
        );

        $orchestrator = $this->mock(LegalArtilleryOrchestrator::class);
        $orchestrator->shouldReceive('generate')->once();

        $job->handle($orchestrator);

        $run->refresh();

        $this->assertFalse($run->send_email);
        $this->assertFalse($run->as_draft);
        $this->assertNull($run->to_email);
        $this->assertNull($run->dispatch_status);
    }

    public function test_no_pending_dispatch_when_send_email_false(): void
    {
        $run = $this->createRun();

        $job = new GenerateLegalDocumentJob(
            runId: $run->id,
            profileKey: 'predsjednik_suda',
            userId: $run->user_id,
            sendOptions: [
                'send_email' => false,
                'as_draft' => true,
                'to_email' => 'test@example.com',
            ],
        );

        $orchestrator = $this->mock(LegalArtilleryOrchestrator::class);
        $orchestrator->shouldReceive('generate')->once();

        $job->handle($orchestrator);

        $run->refresh();

        $this->assertFalse($run->send_email);
        $this->assertTrue($run->as_draft);
        $this->assertSame('test@example.com', $run->to_email);
        $this->assertNull($run->dispatch_status);
    }
}
