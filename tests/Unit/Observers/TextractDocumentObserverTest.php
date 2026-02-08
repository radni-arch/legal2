<?php

namespace Tests\Unit\Observers;

use App\Models\TextractDocument;
use App\Observers\TextractDocumentObserver;
use App\Services\Graph\TextractGraphSyncService;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\TestCase;

class TextractDocumentObserverTest extends TestCase
{
    protected TextractDocumentObserver $observer;

    protected $mockSyncService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockSyncService = Mockery::mock(TextractGraphSyncService::class);
        $this->observer = new TextractDocumentObserver($this->mockSyncService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_calls_unsync_when_document_is_deleted()
    {
        // Create a mock document
        $document = Mockery::mock(TextractDocument::class);
        $document->shouldReceive('getAttribute')->with('id')->andReturn('test-doc-123');
        $document->shouldReceive('setAttribute')->andReturnSelf();
        $document->id = 'test-doc-123';

        // Mock Log facade (since unsync returns true, info will be logged)
        Log::shouldReceive('info')
            ->once()
            ->with(
                'TextractDocument removed from Neo4j graph via observer',
                ['textract_doc_id' => 'test-doc-123']
            );

        // Expect unsync to be called with the document ID
        $this->mockSyncService->shouldReceive('unsync')
            ->once()
            ->with('test-doc-123')
            ->andReturn(true);

        // Call the deleted method
        $this->observer->deleted($document);

        // Assert expectations were met (Mockery will verify)
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_unsync_exception_gracefully()
    {
        // Create a mock document
        $document = Mockery::mock(TextractDocument::class);
        $document->shouldReceive('getAttribute')->with('id')->andReturn('test-doc-456');
        $document->shouldReceive('setAttribute')->andReturnSelf();
        $document->id = 'test-doc-456';

        // Mock Log facade
        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Failed to unsync TextractDocument from Neo4j graph',
                Mockery::type('array')
            );

        // Unsync throws an exception
        $this->mockSyncService->shouldReceive('unsync')
            ->once()
            ->with('test-doc-456')
            ->andThrow(new \RuntimeException('Neo4j connection failed'));

        // Call the deleted method - should NOT throw
        $this->observer->deleted($document);

        // Assert that we handled the exception (didn't throw)
        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_success_when_unsync_succeeds()
    {
        // Create a mock document
        $document = Mockery::mock(TextractDocument::class);
        $document->shouldReceive('getAttribute')->with('id')->andReturn('test-doc-789');
        $document->shouldReceive('setAttribute')->andReturnSelf();
        $document->id = 'test-doc-789';

        // Mock Log facade
        Log::shouldReceive('info')
            ->once()
            ->with(
                'TextractDocument removed from Neo4j graph via observer',
                ['textract_doc_id' => 'test-doc-789']
            );

        // Unsync succeeds
        $this->mockSyncService->shouldReceive('unsync')
            ->once()
            ->with('test-doc-789')
            ->andReturn(true);

        // Call the deleted method
        $this->observer->deleted($document);

        // Verify
        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_info_when_unsync_returns_false()
    {
        // Create a mock document
        $document = Mockery::mock(TextractDocument::class);
        $document->shouldReceive('getAttribute')->with('id')->andReturn('test-doc-999');
        $document->shouldReceive('setAttribute')->andReturnSelf();
        $document->id = 'test-doc-999';

        // Mock Log facade
        Log::shouldReceive('info')
            ->once()
            ->with(
                'TextractDocument not found in Neo4j graph or Neo4j unavailable',
                ['textract_doc_id' => 'test-doc-999']
            );

        // Unsync returns false (not found or Neo4j down)
        $this->mockSyncService->shouldReceive('unsync')
            ->once()
            ->with('test-doc-999')
            ->andReturn(false);

        // Call the deleted method
        $this->observer->deleted($document);

        // Verify
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_force_deleted_event()
    {
        // Create a mock document
        $document = Mockery::mock(TextractDocument::class);
        $document->shouldReceive('getAttribute')->with('id')->andReturn('test-doc-force');
        $document->shouldReceive('setAttribute')->andReturnSelf();
        $document->id = 'test-doc-force';

        // Expect unsync to be called
        $this->mockSyncService->shouldReceive('unsync')
            ->once()
            ->with('test-doc-force')
            ->andReturn(true);

        Log::shouldReceive('info')->once();

        // Call the forceDeleted method
        $this->observer->forceDeleted($document);

        // Verify
        $this->assertTrue(true);
    }

    /** @test */
    public function it_skips_unsync_if_document_has_no_id()
    {
        // Create a mock document with no ID
        $document = Mockery::mock(TextractDocument::class);
        $document->shouldReceive('getAttribute')->with('id')->andReturn(null);
        $document->shouldReceive('setAttribute')->andReturnSelf();
        $document->id = null;

        // Unsync should NOT be called
        $this->mockSyncService->shouldNotReceive('unsync');

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'TextractDocument deletion observer called with null ID',
                Mockery::type('array')
            );

        // Call the deleted method
        $this->observer->deleted($document);

        // Verify
        $this->assertTrue(true);
    }
}
