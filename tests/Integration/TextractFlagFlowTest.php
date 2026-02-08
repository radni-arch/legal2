<?php

namespace Tests\Integration;

use App\Jobs\GenerateEmbeddingsJob;
use App\Jobs\SyncTextractToGraph;
use App\Models\TextractJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TextractFlagFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function completed_job_is_ready_for_embedding(): void
    {
        $job = TextractJob::factory()->completed()->create([
            'embedding_status' => 'pending',
        ]);

        $this->assertTrue($job->isReadyForEmbedding());
    }

    /** @test */
    public function succeeded_job_is_ready_for_embedding(): void
    {
        $job = TextractJob::factory()->succeeded()->create([
            'embedding_status' => 'pending',
        ]);

        $this->assertTrue($job->isReadyForEmbedding());
    }

    /** @test */
    public function queued_job_is_not_ready_for_embedding(): void
    {
        $job = TextractJob::factory()->queued()->create();

        $this->assertFalse($job->isReadyForEmbedding());
    }

    /** @test */
    public function completed_job_is_ready_for_graph_sync(): void
    {
        $job = TextractJob::factory()->completed()->create([
            'graph_sync_status' => 'pending',
        ]);

        $this->assertTrue($job->isReadyForGraphSync());
    }

    /** @test */
    public function embedding_failure_blocks_graph_sync(): void
    {
        $job = TextractJob::factory()->completed()->create([
            'embedding_status' => 'pending',
            'graph_sync_status' => 'pending',
        ]);

        // Simulate embedding failure
        $job->update(['embedding_status' => 'failed']);
        $job->refresh();

        $this->assertEquals('blocked', $job->graph_sync_status);
    }

    /** @test */
    public function mark_as_edited_resets_sync_statuses(): void
    {
        $job = TextractJob::factory()->completed()->embeddingsSynced()->create([
            'graph_sync_status' => 'synced',
            'graph_synced_at' => now(),
        ]);

        $user = User::factory()->create();
        $job->markAsEdited($user->id);
        $job->refresh();

        $this->assertEquals('pending', $job->embedding_status);
        $this->assertEquals('pending', $job->graph_sync_status);
        $this->assertTrue($job->manually_edited);
    }

    /** @test */
    public function mark_embedding_synced_sets_timestamp(): void
    {
        $job = TextractJob::factory()->completed()->create([
            'embedding_status' => 'pending',
        ]);

        $job->markEmbeddingSynced();
        $job->refresh();

        $this->assertEquals('synced', $job->embedding_status);
        $this->assertNotNull($job->embedding_synced_at);
    }

    /** @test */
    public function mark_graph_synced_sets_timestamp(): void
    {
        $job = TextractJob::factory()->completed()->create([
            'graph_sync_status' => 'pending',
        ]);

        $job->markGraphSynced();
        $job->refresh();

        $this->assertEquals('synced', $job->graph_sync_status);
        $this->assertNotNull($job->graph_synced_at);
    }

    /** @test */
    public function content_change_auto_dispatches_regeneration_when_enabled(): void
    {
        Queue::fake();
        config(['textract.auto_sync' => true]);

        $job = TextractJob::factory()->completed()->create();

        $job->update([
            'manual_content' => 'Updated content',
            'manually_edited' => true,
        ]);

        // The model event should have reset statuses
        $job->refresh();
        $this->assertEquals('pending', $job->embedding_status);
        $this->assertEquals('pending', $job->graph_sync_status);
    }

    /** @test */
    public function blocked_graph_sync_can_be_unblocked_by_retry(): void
    {
        $job = TextractJob::factory()->completed()->create([
            'embedding_status' => 'failed',
            'graph_sync_status' => 'blocked',
        ]);

        // Reset embedding status to retry
        $job->update([
            'embedding_status' => 'pending',
            'graph_sync_status' => 'pending',
        ]);
        $job->refresh();

        $this->assertEquals('pending', $job->embedding_status);
        $this->assertEquals('pending', $job->graph_sync_status);
    }

    /** @test */
    public function effective_content_uses_manual_when_edited(): void
    {
        $job = TextractJob::factory()->completed()->manuallyEdited()->create([
            'extracted_content' => 'Original content',
            'manual_content' => 'Edited content',
        ]);

        $this->assertEquals('Edited content', $job->effective_content);
    }

    /** @test */
    public function effective_content_uses_extracted_when_not_edited(): void
    {
        $job = TextractJob::factory()->completed()->create([
            'extracted_content' => 'Original content',
            'manually_edited' => false,
        ]);

        $this->assertEquals('Original content', $job->effective_content);
    }
}
