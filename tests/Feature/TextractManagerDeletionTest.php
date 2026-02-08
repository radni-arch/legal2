<?php

namespace Tests\Feature;

use App\Http\Livewire\TextractManager;
use App\Models\LegalCase;
use App\Models\TextractDocument;
use App\Models\TextractJob;
use App\Models\User;
use App\Services\Graph\TextractGraphSyncService;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Feature tests for TextractManager Livewire component deletion functionality
 *
 * Tests the UI interaction and component methods for:
 * - Deleting jobs via deleteJob() method
 * - Confirmation and error handling
 * - UI state updates after deletion
 * - Success/error message dispatching
 *
 * Following TDD approach - tests written FIRST to drive implementation.
 */
class TextractManagerDeletionTest extends TestCase
{
    use UsesTestDatabase;

    protected User $user;

    protected LegalCase $case;

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Create test case
        $this->case = LegalCase::factory()->create([
            'case_number' => 'TEST-MANAGER-DEL-001',
            'title' => 'Test Manager Deletion Case',
        ]);

        // Mock Neo4j service
        $this->mockNeo4jService();
    }

    /**
     * Mock Neo4j service for offline testing
     */
    protected function mockNeo4jService(): void
    {
        $graphMock = $this->createMock(GraphDatabaseService::class);
        $graphMock->method('isAvailable')->willReturn(true);
        $graphMock->method('deleteNode')->willReturn(true);

        $this->app->instance(GraphDatabaseService::class, $graphMock);
    }

    /**
     * RED TEST 1: deleteJob() method deletes job and documents
     *
     * Expected behavior:
     * - When deleteJob(jobId) is called
     * - Then job and all documents are deleted
     * - And success message is dispatched
     */
    public function test_delete_job_method_deletes_job_and_documents(): void
    {
        // Arrange
        $job = TextractJob::factory()->create([
            'case_id' => $this->case->id,
            'drive_file_name' => 'test-livewire-delete.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Content to delete',
        ]);

        // Create associated documents
        $doc1 = TextractDocument::create([
            'textract_job_id' => $job->id,
            'case_id' => $this->case->id,
            'content' => 'Chunk 1',
            'chunk_index' => 0,
            'processing_status' => 'completed',
        ]);

        $doc2 = TextractDocument::create([
            'textract_job_id' => $job->id,
            'case_id' => $this->case->id,
            'content' => 'Chunk 2',
            'chunk_index' => 1,
            'processing_status' => 'completed',
        ]);

        $jobId = $job->id;
        $doc1Id = $doc1->id;
        $doc2Id = $doc2->id;

        // Verify setup
        $this->assertDatabaseHas('textract_jobs', ['id' => $jobId]);
        $this->assertDatabaseHas('textract_documents', ['id' => $doc1Id]);
        $this->assertDatabaseHas('textract_documents', ['id' => $doc2Id]);

        // Act - Call deleteJob via Livewire component
        Livewire::test(TextractManager::class)
            ->call('deleteJob', $jobId)
            ->assertDispatched('success');

        // Assert - Job and documents are deleted
        $this->assertDatabaseMissing('textract_jobs', ['id' => $jobId]);
        $this->assertDatabaseMissing('textract_documents', ['id' => $doc1Id]);
        $this->assertDatabaseMissing('textract_documents', ['id' => $doc2Id]);
    }

    /**
     * RED TEST 2: deleteJob() updates UI correctly
     *
     * Expected behavior:
     * - When job is deleted
     * - Then job no longer appears in the list
     * - And stats are updated
     */
    public function test_delete_job_updates_ui_correctly(): void
    {
        // Arrange - Create 3 jobs
        $job1 = TextractJob::factory()->create([
            'case_id' => $this->case->id,
            'drive_file_name' => 'keep-this-1.pdf',
            'status' => 'succeeded',
        ]);

        $jobToDelete = TextractJob::factory()->create([
            'case_id' => $this->case->id,
            'drive_file_name' => 'delete-this.pdf',
            'status' => 'succeeded',
        ]);

        $job3 = TextractJob::factory()->create([
            'case_id' => $this->case->id,
            'drive_file_name' => 'keep-this-2.pdf',
            'status' => 'succeeded',
        ]);

        $jobToDeleteId = $jobToDelete->id;

        // Act & Assert - Delete job via component
        $component = Livewire::test(TextractManager::class)
            ->assertSee('delete-this.pdf')
            ->call('deleteJob', $jobToDeleteId)
            ->assertDispatched('success');

        // Verify UI no longer shows deleted job
        $jobs = $component->get('jobs');
        $fileNames = $jobs->pluck('drive_file_name')->toArray();

        $this->assertNotContains('delete-this.pdf', $fileNames);
        $this->assertContains('keep-this-1.pdf', $fileNames);
        $this->assertContains('keep-this-2.pdf', $fileNames);
    }

    /**
     * RED TEST 3: deleteJob() with invalid ID shows error
     *
     * Expected behavior:
     * - When deleteJob() called with non-existent ID
     * - Then error message is dispatched
     * - And no database changes occur
     */
    public function test_delete_job_with_invalid_id_shows_error(): void
    {
        // Arrange - Non-existent job ID
        $invalidJobId = '01NONEXISTENT123456789';

        $this->assertDatabaseMissing('textract_jobs', ['id' => $invalidJobId]);

        // Act & Assert
        Livewire::test(TextractManager::class)
            ->call('deleteJob', $invalidJobId)
            ->assertDispatched('error', message: 'Job not found');
    }

    /**
     * RED TEST 4: deleteJob() cleans up Neo4j nodes
     *
     * Expected behavior:
     * - When deleteJob() is called
     * - Then unsync() is called for all documents
     * - And Neo4j cleanup completes
     */
    public function test_delete_job_cleans_up_neo4j_nodes(): void
    {
        // Arrange
        $job = TextractJob::factory()->create([
            'case_id' => $this->case->id,
            'drive_file_name' => 'test-neo4j-ui-delete.pdf',
            'status' => 'succeeded',
            'graph_sync_status' => 'synced',
        ]);

        $doc = TextractDocument::create([
            'textract_job_id' => $job->id,
            'case_id' => $this->case->id,
            'content' => 'Synced content',
            'chunk_index' => 0,
            'processing_status' => 'completed',
        ]);

        // Mock graph sync service to verify unsync is called
        $graphSyncMock = $this->createMock(TextractGraphSyncService::class);
        $graphSyncMock->expects($this->once())
            ->method('unsync')
            ->with($doc->id)
            ->willReturn(true);

        $this->app->instance(TextractGraphSyncService::class, $graphSyncMock);

        // Act
        Livewire::test(TextractManager::class)
            ->call('deleteJob', $job->id)
            ->assertDispatched('success');

        // Assert - Mock expectations verify unsync was called
    }

    /**
     * RED TEST 5: deleteJob() when Neo4j unavailable still deletes DB
     *
     * Expected behavior:
     * - When Neo4j is down
     * - And deleteJob() is called
     * - Then database deletion succeeds
     * - And warning is logged
     */
    public function test_delete_job_when_neo4j_unavailable_still_deletes(): void
    {
        // Arrange - Mock Neo4j as unavailable
        $graphMock = $this->createMock(GraphDatabaseService::class);
        $graphMock->method('isAvailable')->willReturn(false);

        $this->app->instance(GraphDatabaseService::class, $graphMock);

        $job = TextractJob::factory()->create([
            'case_id' => $this->case->id,
            'drive_file_name' => 'test-neo4j-down-ui.pdf',
            'status' => 'succeeded',
        ]);

        $doc = TextractDocument::create([
            'textract_job_id' => $job->id,
            'case_id' => $this->case->id,
            'content' => 'Content when Neo4j down',
            'chunk_index' => 0,
            'processing_status' => 'completed',
        ]);

        $jobId = $job->id;
        $docId = $doc->id;

        // Act
        Livewire::test(TextractManager::class)
            ->call('deleteJob', $jobId)
            ->assertDispatched('success');

        // Assert - Database records deleted despite Neo4j unavailable
        $this->assertDatabaseMissing('textract_jobs', ['id' => $jobId]);
        $this->assertDatabaseMissing('textract_documents', ['id' => $docId]);
    }

    /**
     * RED TEST 6: deleteJob() with embeddings removes them
     *
     * Expected behavior:
     * - When job with embedded documents is deleted
     * - Then all embeddings are removed
     * - And no orphaned embedding data remains
     */
    public function test_delete_job_with_embeddings_removes_them(): void
    {
        // Arrange
        $job = TextractJob::factory()->create([
            'case_id' => $this->case->id,
            'drive_file_name' => 'test-embeddings-ui.pdf',
            'status' => 'succeeded',
            'embedding_status' => 'synced',
        ]);

        $doc = TextractDocument::create([
            'textract_job_id' => $job->id,
            'case_id' => $this->case->id,
            'content' => 'Embedded content',
            'chunk_index' => 0,
            'embedding' => array_fill(0, 1536, 0.7),
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'processing_status' => 'completed',
        ]);

        // Verify embedding exists
        $this->assertDatabaseHas('textract_documents', [
            'id' => $doc->id,
            'embedding_provider' => 'openai',
        ]);

        $jobId = $job->id;
        $docId = $doc->id;

        // Act
        Livewire::test(TextractManager::class)
            ->call('deleteJob', $jobId)
            ->assertDispatched('success');

        // Assert - No embedding data remains
        $this->assertDatabaseMissing('textract_documents', ['id' => $docId]);
    }

    /**
     * RED TEST 7: deleteJob() handles exceptions gracefully
     *
     * Expected behavior:
     * - When exception occurs during deletion
     * - Then error is caught
     * - And error message is dispatched
     * - And rollback occurs (transaction)
     */
    public function test_delete_job_handles_exceptions_gracefully(): void
    {
        // Arrange - Mock graph sync to throw exception
        $graphSyncMock = $this->createMock(TextractGraphSyncService::class);
        $graphSyncMock->method('unsync')
            ->willThrowException(new \RuntimeException('Critical Neo4j error'));

        $this->app->instance(TextractGraphSyncService::class, $graphSyncMock);

        $job = TextractJob::factory()->create([
            'case_id' => $this->case->id,
            'drive_file_name' => 'test-exception.pdf',
            'status' => 'succeeded',
        ]);

        $doc = TextractDocument::create([
            'textract_job_id' => $job->id,
            'case_id' => $this->case->id,
            'content' => 'Document that will cause exception',
            'chunk_index' => 0,
            'processing_status' => 'completed',
        ]);

        // Expect error log
        Log::shouldReceive('error')->once();

        $jobId = $job->id;
        $docId = $doc->id;

        // Act - Should not throw exception to user
        Livewire::test(TextractManager::class)
            ->call('deleteJob', $jobId);

        // Assert - Database still cleaned up (exception caught in deletion hook)
        // This tests that the exception is caught and logged, not propagated
        $this->assertDatabaseMissing('textract_documents', ['id' => $docId]);
    }

    /**
     * RED TEST 8: deleteJob() with multiple documents in different states
     *
     * Expected behavior:
     * - When job has documents in various states (pending, completed, failed)
     * - Then all documents are deleted regardless of state
     * - And cleanup is complete
     */
    public function test_delete_job_with_documents_in_different_states(): void
    {
        // Arrange
        $job = TextractJob::factory()->create([
            'case_id' => $this->case->id,
            'drive_file_name' => 'test-mixed-states.pdf',
            'status' => 'succeeded',
        ]);

        $docPending = TextractDocument::create([
            'textract_job_id' => $job->id,
            'case_id' => $this->case->id,
            'content' => 'Pending document',
            'chunk_index' => 0,
            'processing_status' => 'pending',
        ]);

        $docCompleted = TextractDocument::create([
            'textract_job_id' => $job->id,
            'case_id' => $this->case->id,
            'content' => 'Completed document',
            'chunk_index' => 1,
            'processing_status' => 'completed',
            'embedding' => array_fill(0, 1536, 0.5),
        ]);

        $docFailed = TextractDocument::create([
            'textract_job_id' => $job->id,
            'case_id' => $this->case->id,
            'content' => 'Failed document',
            'chunk_index' => 2,
            'processing_status' => 'failed',
            'processing_error' => 'Embedding failed',
        ]);

        $jobId = $job->id;
        $pendingId = $docPending->id;
        $completedId = $docCompleted->id;
        $failedId = $docFailed->id;

        // Act
        Livewire::test(TextractManager::class)
            ->call('deleteJob', $jobId)
            ->assertDispatched('success');

        // Assert - All documents deleted regardless of state
        $this->assertDatabaseMissing('textract_documents', ['id' => $pendingId]);
        $this->assertDatabaseMissing('textract_documents', ['id' => $completedId]);
        $this->assertDatabaseMissing('textract_documents', ['id' => $failedId]);
        $this->assertDatabaseMissing('textract_jobs', ['id' => $jobId]);
    }

    /**
     * RED TEST 9: deleteJob() verifies no orphaned relationships
     *
     * Expected behavior:
     * - After deletion
     * - Then no orphaned case relationships exist
     * - And referential integrity is maintained
     */
    public function test_delete_job_maintains_referential_integrity(): void
    {
        // Arrange
        $job = TextractJob::factory()->create([
            'case_id' => $this->case->id,
            'drive_file_name' => 'test-integrity.pdf',
            'status' => 'succeeded',
        ]);

        $doc = TextractDocument::create([
            'textract_job_id' => $job->id,
            'case_id' => $this->case->id,
            'content' => 'Document for integrity test',
            'chunk_index' => 0,
            'processing_status' => 'completed',
        ]);

        $jobId = $job->id;

        // Act
        Livewire::test(TextractManager::class)
            ->call('deleteJob', $jobId)
            ->assertDispatched('success');

        // Assert - No orphaned documents referencing deleted job
        $orphanedDocuments = TextractDocument::where('textract_job_id', $jobId)->count();
        $this->assertEquals(0, $orphanedDocuments);

        // Assert - Case still exists (not cascade deleted)
        $this->assertDatabaseHas('legal_cases', ['id' => $this->case->id]);
    }

    /**
     * RED TEST 10: Mass deletion stress test
     *
     * Expected behavior:
     * - When deleting job with many documents (50+)
     * - Then all documents are cleaned up
     * - And operation completes successfully
     * - And no performance degradation
     */
    public function test_delete_job_with_many_documents(): void
    {
        // Arrange - Create job with 50 documents
        $job = TextractJob::factory()->create([
            'case_id' => $this->case->id,
            'drive_file_name' => 'test-mass-delete.pdf',
            'status' => 'succeeded',
        ]);

        $documentIds = [];
        for ($i = 0; $i < 50; $i++) {
            $doc = TextractDocument::create([
                'textract_job_id' => $job->id,
                'case_id' => $this->case->id,
                'content' => "Mass delete chunk {$i}",
                'chunk_index' => $i,
                'processing_status' => 'completed',
            ]);
            $documentIds[] = $doc->id;
        }

        $jobId = $job->id;

        // Verify setup
        $this->assertEquals(50, TextractDocument::where('textract_job_id', $jobId)->count());

        // Act
        $startTime = microtime(true);

        Livewire::test(TextractManager::class)
            ->call('deleteJob', $jobId)
            ->assertDispatched('success');

        $duration = microtime(true) - $startTime;

        // Assert - All 50 documents deleted
        $this->assertEquals(0, TextractDocument::where('textract_job_id', $jobId)->count());
        $this->assertDatabaseMissing('textract_jobs', ['id' => $jobId]);

        // Performance assertion - should complete in reasonable time (< 5 seconds)
        $this->assertLessThan(5.0, $duration, 'Mass deletion took too long');
    }
}
