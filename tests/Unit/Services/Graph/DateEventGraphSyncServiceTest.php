<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\DateEventGraphSyncService;
use App\Services\Graph\Extractors\DateEventExtractor;
use App\Services\GraphDatabaseService;
use Tests\TestCase;
use Mockery;

class DateEventGraphSyncServiceTest extends TestCase
{
    protected DateEventGraphSyncService $service;
    protected $graphMock;
    protected $extractorMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphMock = Mockery::mock(GraphDatabaseService::class);
        $this->extractorMock = Mockery::mock(DateEventExtractor::class);

        $this->service = new DateEventGraphSyncService(
            $this->graphMock,
            $this->extractorMock
        );
    }

    /** @test */
    public function it_syncs_extracted_events_to_graph()
    {
        $documentId = 'doc-123';
        $content = 'Presuda donesena 15.01.2024. godine.';

        $extractedEvents = [
            [
                'id' => 'event-ulid-1',
                'date' => '2024-01-15',
                'event_type' => 'judgment',
                'description' => 'Presuda donesena 15.01.2024.',
                'source_type' => 'decision',
                'source_id' => 'doc-123',
            ],
        ];

        $this->extractorMock->shouldReceive('extract')
            ->once()
            ->with($content, $documentId, 'decision')
            ->andReturn($extractedEvents);

        $this->graphMock->shouldReceive('upsertNode')
            ->once()
            ->with('DateEvent', 'event-ulid-1', Mockery::on(function ($properties) {
                return $properties['date'] === '2024-01-15'
                    && $properties['event_type'] === 'judgment'
                    && $properties['source_id'] === 'doc-123'
                    && isset($properties['created_at']);
            }));

        $this->graphMock->shouldReceive('createRelationship')
            ->once()
            ->with(
                'CourtDecisionDocument',
                $documentId,
                'HAS_EVENT',
                'DateEvent',
                'event-ulid-1',
                Mockery::on(function ($properties) {
                    return isset($properties['created_at']);
                })
            );

        $metrics = $this->service->sync($documentId, $content);

        $this->assertEquals(1, $metrics['events_created']);
        $this->assertEquals(1, $metrics['events_linked']);
    }

    /** @test */
    public function it_syncs_multiple_events()
    {
        $documentId = 'doc-456';
        $content = 'Tužba 10.01.2024., ročište 15.02.2024., presuda 20.03.2024.';

        $extractedEvents = [
            [
                'id' => 'event-1',
                'date' => '2024-01-10',
                'event_type' => 'filing',
                'description' => 'Context 1',
                'source_type' => 'decision',
                'source_id' => 'doc-456',
            ],
            [
                'id' => 'event-2',
                'date' => '2024-02-15',
                'event_type' => 'hearing',
                'description' => 'Context 2',
                'source_type' => 'decision',
                'source_id' => 'doc-456',
            ],
            [
                'id' => 'event-3',
                'date' => '2024-03-20',
                'event_type' => 'judgment',
                'description' => 'Context 3',
                'source_type' => 'decision',
                'source_id' => 'doc-456',
            ],
        ];

        $this->extractorMock->shouldReceive('extract')
            ->once()
            ->andReturn($extractedEvents);

        $this->graphMock->shouldReceive('upsertNode')->times(3);
        $this->graphMock->shouldReceive('createRelationship')->times(3);

        $metrics = $this->service->sync($documentId, $content);

        $this->assertEquals(3, $metrics['events_created']);
        $this->assertEquals(3, $metrics['events_linked']);
    }

    /** @test */
    public function it_handles_sync_errors_gracefully()
    {
        $documentId = 'doc-789';
        $content = 'Presuda 15.01.2024.';

        $extractedEvents = [
            [
                'id' => 'event-1',
                'date' => '2024-01-15',
                'event_type' => 'judgment',
                'description' => 'Context',
                'source_type' => 'decision',
                'source_id' => 'doc-789',
            ],
            [
                'id' => 'event-2',
                'date' => '2024-02-15',
                'event_type' => 'hearing',
                'description' => 'Context',
                'source_type' => 'decision',
                'source_id' => 'doc-789',
            ],
        ];

        $this->extractorMock->shouldReceive('extract')
            ->once()
            ->andReturn($extractedEvents);

        // First event succeeds
        $this->graphMock->shouldReceive('upsertNode')
            ->once()
            ->with('DateEvent', 'event-1', Mockery::any());

        $this->graphMock->shouldReceive('createRelationship')
            ->once()
            ->with('CourtDecisionDocument', $documentId, 'HAS_EVENT', 'DateEvent', 'event-1', Mockery::any());

        // Second event fails
        $this->graphMock->shouldReceive('upsertNode')
            ->once()
            ->with('DateEvent', 'event-2', Mockery::any())
            ->andThrow(new \Exception('Graph error'));

        // Should continue despite error and return partial metrics
        $metrics = $this->service->sync($documentId, $content);

        $this->assertEquals(1, $metrics['events_created']);
        $this->assertEquals(1, $metrics['events_linked']);
    }

    /** @test */
    public function it_accepts_custom_source_type()
    {
        $documentId = 'law-123';
        $content = 'Stupa na snagu 01.01.2025.';
        $sourceType = 'law';

        $extractedEvents = [
            [
                'id' => 'event-1',
                'date' => '2025-01-01',
                'event_type' => 'effective',
                'description' => 'Context',
                'source_type' => 'law',
                'source_id' => 'law-123',
            ],
        ];

        $this->extractorMock->shouldReceive('extract')
            ->once()
            ->with($content, $documentId, $sourceType)
            ->andReturn($extractedEvents);

        $this->graphMock->shouldReceive('upsertNode')
            ->once()
            ->with('DateEvent', 'event-1', Mockery::on(function ($properties) {
                return $properties['source_type'] === 'law';
            }));

        $this->graphMock->shouldReceive('createRelationship')->once();

        $metrics = $this->service->sync($documentId, $content, $sourceType);

        $this->assertEquals(1, $metrics['events_created']);
    }

    /** @test */
    public function it_returns_zero_metrics_when_no_events_extracted()
    {
        $documentId = 'doc-empty';
        $content = 'Some text without dates.';

        $this->extractorMock->shouldReceive('extract')
            ->once()
            ->andReturn([]);

        $this->graphMock->shouldNotReceive('upsertNode');
        $this->graphMock->shouldNotReceive('createRelationship');

        $metrics = $this->service->sync($documentId, $content);

        $this->assertEquals(0, $metrics['events_created']);
        $this->assertEquals(0, $metrics['events_linked']);
    }
}
