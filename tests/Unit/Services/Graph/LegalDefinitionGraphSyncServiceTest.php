<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\Extractors\LegalDefinitionExtractor;
use App\Services\Graph\LegalDefinitionGraphSyncService;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LegalDefinitionGraphSyncServiceTest extends TestCase
{
    protected LegalDefinitionGraphSyncService $service;
    protected GraphDatabaseService $mockGraph;
    protected LegalDefinitionExtractor $mockExtractor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockGraph = $this->createMock(GraphDatabaseService::class);
        $this->mockExtractor = $this->createMock(LegalDefinitionExtractor::class);

        $this->service = new LegalDefinitionGraphSyncService(
            $this->mockGraph,
            $this->mockExtractor
        );
    }

    /** @test */
    public function it_syncs_definitions_from_law_document()
    {
        $lawId = 'law_123';
        $content = 'Članak 5. "Pravna osoba" znači entitet s pravnom osobnošću.';

        $extractedDefinitions = [
            [
                'id' => 'def_abc',
                'term' => 'Pravna osoba',
                'definition' => 'entitet s pravnom osobnošću',
                'source_article' => '5',
                'law_id' => $lawId,
                'scope' => 'this_law',
            ],
        ];

        $this->mockExtractor
            ->expects($this->once())
            ->method('extract')
            ->with($content, $lawId, null)
            ->willReturn($extractedDefinitions);

        $this->mockGraph
            ->expects($this->once())
            ->method('upsertNode')
            ->with(
                'LegalDefinition',
                'def_abc',
                $this->callback(function ($props) {
                    return $props['term'] === 'Pravna osoba'
                        && $props['definition'] === 'entitet s pravnom osobnošću'
                        && $props['source_article'] === '5'
                        && $props['law_id'] === 'law_123'
                        && $props['scope'] === 'this_law'
                        && isset($props['created_at']);
                })
            );

        $this->mockGraph
            ->expects($this->once())
            ->method('createRelationship')
            ->with(
                'LawDocument',
                $lawId,
                'DEFINES',
                'LegalDefinition',
                'def_abc',
                $this->callback(function ($props) {
                    return $props['article'] === '5'
                        && isset($props['created_at']);
                })
            );

        $metrics = $this->service->syncFromLaw($lawId, $content);

        $this->assertEquals(1, $metrics['definitions_created']);
        $this->assertEquals(1, $metrics['defines_relationships']);
    }

    /** @test */
    public function it_syncs_multiple_definitions_from_law()
    {
        $lawId = 'law_456';
        $content = 'Multiple definitions here...';

        $extractedDefinitions = [
            [
                'id' => 'def_1',
                'term' => 'Term 1',
                'definition' => 'Definition 1',
                'source_article' => '1',
                'law_id' => $lawId,
                'scope' => 'this_law',
            ],
            [
                'id' => 'def_2',
                'term' => 'Term 2',
                'definition' => 'Definition 2',
                'source_article' => '2',
                'law_id' => $lawId,
                'scope' => 'general',
            ],
        ];

        $this->mockExtractor
            ->method('extract')
            ->willReturn($extractedDefinitions);

        $this->mockGraph
            ->expects($this->exactly(2))
            ->method('upsertNode');

        $this->mockGraph
            ->expects($this->exactly(2))
            ->method('createRelationship');

        $metrics = $this->service->syncFromLaw($lawId, $content);

        $this->assertEquals(2, $metrics['definitions_created']);
        $this->assertEquals(2, $metrics['defines_relationships']);
    }

    /** @test */
    public function it_uses_article_number_parameter_when_provided()
    {
        $lawId = 'law_789';
        $content = '"Test" znači test definition.';
        $articleNumber = '42';

        $this->mockExtractor
            ->expects($this->once())
            ->method('extract')
            ->with($content, $lawId, $articleNumber)
            ->willReturn([]);

        $this->service->syncFromLaw($lawId, $content, $articleNumber);
    }

    /** @test */
    public function it_handles_extraction_errors_gracefully()
    {
        Log::shouldReceive('warning')->once();

        $lawId = 'law_error';
        $content = 'Content with definition';

        $this->mockExtractor
            ->method('extract')
            ->willReturn([
                [
                    'id' => 'def_error',
                    'term' => 'Error term',
                    'definition' => 'Error def',
                    'source_article' => '1',
                    'law_id' => $lawId,
                    'scope' => 'this_law',
                ],
            ]);

        $this->mockGraph
            ->method('upsertNode')
            ->willThrowException(new \Exception('Graph error'));

        // Should not throw - errors are caught and logged
        $metrics = $this->service->syncFromLaw($lawId, $content);

        // Metrics should show 0 since the operation failed
        $this->assertEquals(0, $metrics['definitions_created']);
        $this->assertEquals(0, $metrics['defines_relationships']);
    }

    /** @test */
    public function it_links_court_decision_to_applied_definitions()
    {
        $documentId = 'doc_123';
        $content = 'Decision that mentions "pravna osoba" and applies its definition.';

        $extractedTerms = [
            [
                'id' => 'def_temp',
                'term' => 'pravna osoba',
                'definition' => 'some def',
                'source_article' => null,
                'law_id' => null,
                'scope' => 'this_law',
            ],
        ];

        $this->mockExtractor
            ->method('extract')
            ->with($content)
            ->willReturn($extractedTerms);

        // Mock finding the existing definition in graph
        $this->mockGraph
            ->expects($this->once())
            ->method('run')
            ->with(
                'MATCH (d:LegalDefinition) WHERE toLower(d.term) = toLower($term) RETURN d.id as id LIMIT 1',
                ['term' => 'pravna osoba']
            )
            ->willReturn([['id' => 'def_existing_123']]);

        $this->mockGraph
            ->expects($this->once())
            ->method('createRelationship')
            ->with(
                'CourtDecisionDocument',
                $documentId,
                'APPLIES_DEFINITION',
                'LegalDefinition',
                'def_existing_123',
                $this->callback(function ($props) {
                    return $props['interpretation'] === null
                        && isset($props['created_at']);
                })
            );

        $metrics = $this->service->linkDecisionToDefinitions($documentId, $content);

        $this->assertEquals(1, $metrics['definitions_linked']);
    }

    /** @test */
    public function it_skips_linking_when_definition_not_found_in_graph()
    {
        $documentId = 'doc_456';
        $content = 'Decision with unknown term.';

        $this->mockExtractor
            ->method('extract')
            ->willReturn([
                [
                    'id' => 'def_temp',
                    'term' => 'unknown term',
                    'definition' => 'def',
                    'source_article' => null,
                    'law_id' => null,
                    'scope' => 'this_law',
                ],
            ]);

        // Return empty - definition not found
        $this->mockGraph
            ->method('run')
            ->willReturn([]);

        // Should not attempt to create relationship
        $this->mockGraph
            ->expects($this->never())
            ->method('createRelationship');

        $metrics = $this->service->linkDecisionToDefinitions($documentId, $content);

        $this->assertEquals(0, $metrics['definitions_linked']);
    }

    /** @test */
    public function it_continues_linking_despite_individual_errors()
    {
        $documentId = 'doc_789';
        $content = 'Decision with multiple terms.';

        $this->mockExtractor
            ->method('extract')
            ->willReturn([
                ['id' => 't1', 'term' => 'term1', 'definition' => 'd1', 'source_article' => null, 'law_id' => null, 'scope' => 'this_law'],
                ['id' => 't2', 'term' => 'term2', 'definition' => 'd2', 'source_article' => null, 'law_id' => null, 'scope' => 'this_law'],
            ]);

        // First succeeds, second throws
        $callCount = 0;
        $this->mockGraph
            ->method('run')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return [['id' => 'def1']];
                }
                throw new \Exception('Graph error');
            });

        $this->mockGraph
            ->expects($this->once())
            ->method('createRelationship');

        // Should not throw - errors are silently caught
        $metrics = $this->service->linkDecisionToDefinitions($documentId, $content);

        // Only first one succeeded
        $this->assertEquals(1, $metrics['definitions_linked']);
    }
}
