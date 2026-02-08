<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\Extractors\LegalArgumentExtractor;
use App\Services\Graph\LegalArgumentGraphSyncService;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LegalArgumentGraphSyncServiceTest extends TestCase
{
    protected LegalArgumentGraphSyncService $service;
    protected GraphDatabaseService $graphMock;
    protected LegalArgumentExtractor $extractorMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphMock = $this->createMock(GraphDatabaseService::class);
        $this->extractorMock = $this->createMock(LegalArgumentExtractor::class);

        $this->service = new LegalArgumentGraphSyncService(
            $this->graphMock,
            $this->extractorMock
        );
    }

    public function test_sync_creates_nodes_and_relationships(): void
    {
        $documentId = 'doc-123';
        $decisionId = 'decision-456';
        $content = 'Tužitelj navodi da je došlo do povrede ugovora.';

        $extractedArguments = [
            [
                'id' => 'arg-1',
                'decision_id' => $decisionId,
                'argument_type' => 'substantive',
                'position' => 'plaintiff',
                'summary' => 'Povreda ugovora',
                'full_text' => 'Tužitelj navodi da je došlo do povrede ugovora.',
                'accepted' => true,
                'sequence' => 1,
            ],
            [
                'id' => 'arg-2',
                'decision_id' => $decisionId,
                'argument_type' => 'evidentiary',
                'position' => 'court',
                'summary' => 'Sud nalazi',
                'full_text' => 'Sud nalazi da je dokazano.',
                'accepted' => null,
                'sequence' => 2,
            ],
        ];

        $this->extractorMock
            ->expects($this->once())
            ->method('extract')
            ->with($content, $decisionId)
            ->willReturn($extractedArguments);

        // Expect upsertNode for each argument
        $this->graphMock
            ->expects($this->exactly(2))
            ->method('upsertNode')
            ->willReturnCallback(function ($label, $id, $properties) {
                $this->assertEquals('LegalArgument', $label);
                $this->assertNotEmpty($id);
                $this->assertArrayHasKey('decision_id', $properties);
                $this->assertArrayHasKey('argument_type', $properties);
                $this->assertArrayHasKey('position', $properties);
                $this->assertArrayHasKey('summary', $properties);
                $this->assertArrayHasKey('full_text', $properties);
                $this->assertArrayHasKey('created_at', $properties);
            });

        // Expect createRelationship for each argument
        $this->graphMock
            ->expects($this->exactly(2))
            ->method('createRelationship')
            ->willReturnCallback(function ($fromLabel, $fromId, $relType, $toLabel, $toId, $props) use ($documentId) {
                $this->assertEquals('CourtDecisionDocument', $fromLabel);
                $this->assertEquals($documentId, $fromId);
                $this->assertEquals('CONTAINS_ARGUMENT', $relType);
                $this->assertEquals('LegalArgument', $toLabel);
                $this->assertNotEmpty($toId);
                $this->assertArrayHasKey('sequence', $props);
                $this->assertArrayHasKey('created_at', $props);
            });

        $metrics = $this->service->sync($documentId, $content, $decisionId);

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('arguments_created', $metrics);
        $this->assertArrayHasKey('arguments_linked', $metrics);
        $this->assertEquals(2, $metrics['arguments_created']);
        $this->assertEquals(2, $metrics['arguments_linked']);
    }

    public function test_sync_handles_empty_arguments(): void
    {
        $documentId = 'doc-123';
        $decisionId = 'decision-456';
        $content = 'Some text with no arguments.';

        $this->extractorMock
            ->expects($this->once())
            ->method('extract')
            ->with($content, $decisionId)
            ->willReturn([]);

        $this->graphMock
            ->expects($this->never())
            ->method('upsertNode');

        $this->graphMock
            ->expects($this->never())
            ->method('createRelationship');

        $metrics = $this->service->sync($documentId, $content, $decisionId);

        $this->assertEquals(0, $metrics['arguments_created']);
        $this->assertEquals(0, $metrics['arguments_linked']);
    }

    public function test_sync_continues_on_node_creation_error(): void
    {
        Log::shouldReceive('warning')->once();

        $documentId = 'doc-123';
        $decisionId = 'decision-456';
        $content = 'Test content';

        $extractedArguments = [
            [
                'id' => 'arg-1',
                'decision_id' => $decisionId,
                'argument_type' => 'substantive',
                'position' => 'plaintiff',
                'summary' => 'Summary',
                'full_text' => 'Full text',
                'accepted' => true,
                'sequence' => 1,
            ],
        ];

        $this->extractorMock
            ->expects($this->once())
            ->method('extract')
            ->willReturn($extractedArguments);

        $this->graphMock
            ->expects($this->once())
            ->method('upsertNode')
            ->willThrowException(new \Exception('Graph error'));

        $this->graphMock
            ->expects($this->never())
            ->method('createRelationship');

        $metrics = $this->service->sync($documentId, $content, $decisionId);

        $this->assertEquals(0, $metrics['arguments_created']);
        $this->assertEquals(0, $metrics['arguments_linked']);
    }

    public function test_sync_continues_on_relationship_creation_error(): void
    {
        Log::shouldReceive('warning')->once();

        $documentId = 'doc-123';
        $decisionId = 'decision-456';
        $content = 'Test content';

        $extractedArguments = [
            [
                'id' => 'arg-1',
                'decision_id' => $decisionId,
                'argument_type' => 'substantive',
                'position' => 'plaintiff',
                'summary' => 'Summary',
                'full_text' => 'Full text',
                'accepted' => true,
                'sequence' => 1,
            ],
        ];

        $this->extractorMock
            ->expects($this->once())
            ->method('extract')
            ->willReturn($extractedArguments);

        $this->graphMock
            ->expects($this->once())
            ->method('upsertNode');

        $this->graphMock
            ->expects($this->once())
            ->method('createRelationship')
            ->willThrowException(new \Exception('Relationship error'));

        $metrics = $this->service->sync($documentId, $content, $decisionId);

        $this->assertEquals(1, $metrics['arguments_created']);
        $this->assertEquals(0, $metrics['arguments_linked']);
    }
}
