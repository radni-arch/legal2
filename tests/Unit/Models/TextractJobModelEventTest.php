<?php

namespace Tests\Unit\Models;

use App\Jobs\RegenerateTextractEmbeddings;
use App\Jobs\SyncTextractToGraph;
use App\Models\TextractJob;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * TextractJobModelEventTest
 *
 * Tests for the TextractJob model's booted() event listeners,
 * specifically verifying that the race condition fix is in place:
 * - Only RegenerateTextractEmbeddings is dispatched from model events
 * - SyncTextractToGraph is NEVER dispatched from model events
 *   (it chains after embeddings complete in RegenerateTextractEmbeddings)
 */
class TextractJobModelEventTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake the queue to capture dispatched jobs
        Queue::fake();
    }

    /**
     * @test
     * Test 1: Content edit dispatches ONLY RegenerateTextractEmbeddings (not SyncTextractToGraph).
     *
     * When manual_content is updated on a succeeded job with auto_sync enabled,
     * only the embedding regeneration job should be dispatched. The graph sync
     * job must NOT be dispatched from the model event because it requires
     * embedding_status === 'synced' which won't be ready yet.
     */
    public function it_dispatches_only_embedding_regeneration_on_content_edit(): void
    {
        // Arrange
        Config::set('textract.auto_sync', true);

        $job = TextractJob::factory()->create([
            'status' => 'succeeded',
            'extracted_content' => 'Original extracted content',
            'manually_edited' => false,
            'embedding_status' => 'synced',
            'graph_sync_status' => 'synced',
        ]);

        // Act - Update manual content (triggers model updated event)
        $job->update([
            'manual_content' => 'Updated manual content by user',
            'manually_edited' => true,
        ]);

        // Assert - ONLY RegenerateTextractEmbeddings should be dispatched
        Queue::assertPushed(RegenerateTextractEmbeddings::class, function ($queuedJob) use ($job) {
            return $queuedJob->textractJobId === $job->id;
        });

        // Assert - SyncTextractToGraph must NOT be dispatched from model event
        // (This was the race condition bug - graph sync was dispatched simultaneously
        //  with embedding regeneration, but graph sync requires embeddings to be ready)
        Queue::assertNotPushed(SyncTextractToGraph::class);
    }

    /**
     * @test
     * Test 2: Content edit with neo4j.sync.enabled=true still does NOT dispatch
     *         SyncTextractToGraph from model event.
     *
     * Even when Neo4j sync is explicitly enabled in config, the model event
     * must NOT dispatch SyncTextractToGraph directly. Graph sync should only
     * be chained from RegenerateTextractEmbeddings after embeddings complete.
     */
    public function it_does_not_dispatch_graph_sync_even_when_neo4j_enabled(): void
    {
        // Arrange - Explicitly enable both auto_sync and neo4j sync
        Config::set('textract.auto_sync', true);
        Config::set('neo4j.sync.enabled', true);

        $job = TextractJob::factory()->create([
            'status' => 'succeeded',
            'extracted_content' => 'Original content for neo4j test',
            'manually_edited' => false,
            'embedding_status' => 'synced',
            'graph_sync_status' => 'synced',
        ]);

        // Act - Update extracted_content (triggers model updated event)
        $job->update([
            'extracted_content' => 'Updated extracted content with corrections',
        ]);

        // Assert - Embedding regeneration IS dispatched
        Queue::assertPushed(RegenerateTextractEmbeddings::class, function ($queuedJob) use ($job) {
            return $queuedJob->textractJobId === $job->id;
        });

        // Assert - Graph sync is NOT dispatched from model event, even with neo4j enabled
        Queue::assertNotPushed(SyncTextractToGraph::class);
    }

    /**
     * @test
     * Test 3: Content edit with autoSync disabled dispatches nothing.
     *
     * When textract.auto_sync config is set to false, NO jobs should be
     * dispatched from the model event, regardless of content changes.
     */
    public function it_dispatches_nothing_when_auto_sync_disabled(): void
    {
        // Arrange - Disable auto sync
        Config::set('textract.auto_sync', false);
        Config::set('neo4j.sync.enabled', true);

        $job = TextractJob::factory()->create([
            'status' => 'succeeded',
            'extracted_content' => 'Original content',
            'manually_edited' => false,
            'embedding_status' => 'synced',
            'graph_sync_status' => 'synced',
        ]);

        // Act - Update content with auto_sync disabled
        $job->update([
            'manual_content' => 'Updated content but auto sync is off',
            'manually_edited' => true,
        ]);

        // Assert - Nothing dispatched when auto_sync is disabled
        Queue::assertNotPushed(RegenerateTextractEmbeddings::class);
        Queue::assertNotPushed(SyncTextractToGraph::class);
    }

    /**
     * @test
     * Test 4: Content edit with empty effective_content dispatches nothing.
     *
     * When a content field changes but the effective_content evaluates to empty
     * (e.g., content cleared), no embedding regeneration should be dispatched
     * because there's nothing to embed.
     */
    public function it_dispatches_nothing_when_effective_content_is_empty(): void
    {
        // Arrange
        Config::set('textract.auto_sync', true);
        Config::set('neo4j.sync.enabled', true);

        $job = TextractJob::factory()->create([
            'status' => 'succeeded',
            'extracted_content' => 'Some original content',
            'manual_content' => null,
            'manually_edited' => false,
            'embedding_status' => 'synced',
            'graph_sync_status' => 'synced',
        ]);

        // Act - Clear extracted_content to empty (effective_content becomes empty)
        $job->update([
            'extracted_content' => '',
        ]);

        // Assert - Nothing dispatched because effective_content is empty
        Queue::assertNotPushed(RegenerateTextractEmbeddings::class);
        Queue::assertNotPushed(SyncTextractToGraph::class);
    }

    /**
     * @test
     * Test 5: Non-content field update does NOT dispatch embedding regeneration.
     *
     * Updating fields other than manual_content or extracted_content
     * (e.g., error, metadata, status) should not trigger any job dispatch.
     */
    public function it_does_not_dispatch_jobs_for_non_content_field_updates(): void
    {
        // Arrange
        Config::set('textract.auto_sync', true);
        Config::set('neo4j.sync.enabled', true);

        $job = TextractJob::factory()->create([
            'status' => 'succeeded',
            'extracted_content' => 'Existing content that should not trigger re-embedding',
            'manually_edited' => false,
            'embedding_status' => 'synced',
            'graph_sync_status' => 'synced',
            'error' => null,
        ]);

        // Act - Update non-content fields only
        $job->update([
            'error' => 'Some error message added later',
            'metadata' => ['updated_by' => 'test', 'reason' => 'testing non-content update'],
        ]);

        // Assert - No jobs dispatched for non-content field updates
        Queue::assertNotPushed(RegenerateTextractEmbeddings::class);
        Queue::assertNotPushed(SyncTextractToGraph::class);
    }
}
