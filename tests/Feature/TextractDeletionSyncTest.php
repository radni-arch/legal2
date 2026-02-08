<?php

namespace Tests\Feature;

use App\Models\TextractDocument;
use App\Models\TextractJob;
use App\Services\Graph\TextractGraphSyncService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class TextractDeletionSyncTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function deleting_textract_document_calls_unsync_service()
    {
        // Create a TextractJob first
        $job = TextractJob::factory()->create();

        // Create a TextractDocument
        $document = TextractDocument::factory()->create([
            'textract_job_id' => $job->id,
            'case_id' => null,
        ]);

        $documentId = $document->id;

        // Mock the sync service to verify it gets called
        $mockSyncService = Mockery::mock(TextractGraphSyncService::class);
        $mockSyncService->shouldReceive('unsync')
            ->once()
            ->with($documentId)
            ->andReturn(true);

        // Replace the service in the container
        $this->app->instance(TextractGraphSyncService::class, $mockSyncService);

        // Delete the document
        $document->delete();

        // Verify the document is deleted
        $this->assertDatabaseMissing('textract_documents', [
            'id' => $documentId,
        ]);

        // Mockery will verify the unsync call
    }

    /** @test */
    public function deletion_succeeds_even_when_neo4j_unavailable()
    {
        // Create a TextractJob first
        $job = TextractJob::factory()->create();

        // Create a TextractDocument
        $document = TextractDocument::factory()->create([
            'textract_job_id' => $job->id,
            'case_id' => null,
        ]);

        $documentId = $document->id;

        // Mock the sync service to simulate Neo4j being down
        $mockSyncService = Mockery::mock(TextractGraphSyncService::class);
        $mockSyncService->shouldReceive('unsync')
            ->once()
            ->with($documentId)
            ->andReturn(false); // Returns false when Neo4j is unavailable

        // Replace the service in the container
        $this->app->instance(TextractGraphSyncService::class, $mockSyncService);

        Log::shouldReceive('info')->once();

        // Delete the document - should succeed
        $document->delete();

        // Verify the document is deleted from database
        $this->assertDatabaseMissing('textract_documents', [
            'id' => $documentId,
        ]);
    }

    /** @test */
    public function deletion_succeeds_even_when_unsync_throws_exception()
    {
        // Create a TextractJob first
        $job = TextractJob::factory()->create();

        // Create a TextractDocument
        $document = TextractDocument::factory()->create([
            'textract_job_id' => $job->id,
            'case_id' => null,
        ]);

        $documentId = $document->id;

        // Mock the sync service to throw an exception
        $mockSyncService = Mockery::mock(TextractGraphSyncService::class);
        $mockSyncService->shouldReceive('unsync')
            ->once()
            ->with($documentId)
            ->andThrow(new \RuntimeException('Neo4j connection failed'));

        // Replace the service in the container
        $this->app->instance(TextractGraphSyncService::class, $mockSyncService);

        Log::shouldReceive('warning')->once();

        // Delete the document - should still succeed
        $document->delete();

        // Verify the document is deleted from database
        $this->assertDatabaseMissing('textract_documents', [
            'id' => $documentId,
        ]);
    }

    /** @test */
    public function force_deleting_textract_document_calls_unsync_service()
    {
        // Create a TextractJob first
        $job = TextractJob::factory()->create();

        // Create a TextractDocument
        $document = TextractDocument::factory()->create([
            'textract_job_id' => $job->id,
            'case_id' => null,
        ]);

        $documentId = $document->id;

        // Mock the sync service
        $mockSyncService = Mockery::mock(TextractGraphSyncService::class);
        $mockSyncService->shouldReceive('unsync')
            ->once()
            ->with($documentId)
            ->andReturn(true);

        // Replace the service in the container
        $this->app->instance(TextractGraphSyncService::class, $mockSyncService);

        Log::shouldReceive('info')->once();

        // Force delete the document
        $document->forceDelete();

        // Verify the document is gone
        $this->assertDatabaseMissing('textract_documents', [
            'id' => $documentId,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
