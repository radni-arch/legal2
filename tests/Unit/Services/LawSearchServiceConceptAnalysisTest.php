<?php

namespace Tests\Unit\Services;

use App\Mcp\Tools\LawGetArticleTool;
use App\Mcp\Tools\LawSearchTool;
use App\Services\CourtDecisionVectorStoreService;
use App\Services\LawSearchService;
use App\Services\LawVectorStoreService;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;

class LawSearchServiceConceptAnalysisTest extends TestCase
{
    protected LawSearchService $service;

    protected $mockOpenAI;

    protected $mockLawSearchTool;

    protected $mockLawGetArticleTool;

    protected $mockLawVectorStore;

    protected $mockDecisionVectorStore;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock dependencies directly to avoid circular dependency with container
        $this->mockOpenAI = Mockery::mock(OpenAIService::class);
        $this->mockLawSearchTool = Mockery::mock(LawSearchTool::class);
        $this->mockLawGetArticleTool = Mockery::mock(LawGetArticleTool::class);
        $this->mockLawVectorStore = Mockery::mock(LawVectorStoreService::class);
        $this->mockDecisionVectorStore = Mockery::mock(CourtDecisionVectorStoreService::class);

        // Bind mocks to container for methods using app() helper
        $this->app->instance(LawVectorStoreService::class, $this->mockLawVectorStore);
        $this->app->instance(CourtDecisionVectorStoreService::class, $this->mockDecisionVectorStore);

        // Default AI response for concept analysis
        $this->mockOpenAI
            ->shouldReceive('chat')
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'definition' => 'Načelo neposrednosti znači da sud mora neposredno upoznati sve dokaze.',
                            'legal_basis' => ['ZKP Članak 9', 'ZKP Članak 326'],
                            'examples' => [
                                'Sud mora saslušati svjedoke uživo',
                                'Dokazi se ocjenjuju na ročištu',
                            ],
                            'related_concepts' => ['načelo kontradiktornosti', 'načelo usmenog raspravljanja'],
                            'doctrine_type' => 'procedural',
                            'origin' => 'Continental European legal tradition',
                            'application' => 'Applied in all criminal proceedings in Croatia',
                            'exceptions' => ['Witness statements in preliminary proceedings'],
                            'croatian_equivalent' => 'neposrednost',
                        ]),
                    ],
                ]],
            ]);

        // Mock embedding generation
        $this->mockOpenAI
            ->shouldReceive('createEmbedding')
            ->andReturn(array_fill(0, 1536, 0.1));

        // Mock vector store searches
        $this->mockLawVectorStore
            ->shouldReceive('search')
            ->andReturn([
                [
                    'content' => 'Načelo neposrednosti se primjenjuje zajedno s načelom kontradiktornosti.',
                    'similarity' => 0.85,
                ],
                [
                    'content' => 'Pravo na obranu je temeljno načelo kaznenog postupka.',
                    'similarity' => 0.72,
                ],
            ]);

        $this->mockDecisionVectorStore
            ->shouldReceive('search')
            ->andReturn([
                [
                    'content' => 'Sud je utvrdio da je načelo neposrednosti povrijeđeno.',
                    'similarity' => 0.88,
                    'metadata' => [
                        'decision_id' => 'KŽ-123/2024',
                        'court' => 'Vrhovni sud RH',
                        'date' => '2024-03-15',
                    ],
                ],
            ]);

        $this->service = new LawSearchService(
            $this->mockOpenAI,
            $this->mockLawSearchTool,
            $this->mockLawGetArticleTool
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_analyzes_legal_concepts_with_ai(): void
    {
        // Test 'define' operation - full AI-powered definition
        $result = $this->service->analyzeConcept('neposrednost', [
            'operation' => 'define',
            'jurisdiction' => 'HR',
        ]);

        $this->assertArrayHasKey('concept', $result);
        $this->assertArrayHasKey('definition', $result);
        $this->assertArrayHasKey('legal_basis', $result);
        $this->assertArrayHasKey('examples', $result);
        $this->assertEquals('neposrednost', $result['concept']);

        // Verify AI was actually called (not stub response)
        $this->assertNotEquals('Definition of neposrednost', $result['definition']);
        $this->assertNotEmpty($result['definition']);
        $this->assertIsArray($result['legal_basis']);
        $this->assertIsArray($result['examples']);
    }

    public function test_finds_related_concepts(): void
    {
        $result = $this->service->analyzeConcept('neposrednost', [
            'operation' => 'related',
        ]);

        $this->assertArrayHasKey('related_concepts', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertIsArray($result['related_concepts']);
    }

    public function test_finds_concept_precedents(): void
    {
        $result = $this->service->analyzeConcept('search warrant', [
            'operation' => 'precedents',
        ]);

        $this->assertArrayHasKey('precedents', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertIsArray($result['precedents']);

        // Should have structure for precedents (even if empty)
        $this->assertIsInt($result['count']);
    }

    public function test_analyzes_doctrine_origin(): void
    {
        $result = $this->service->analyzeConcept('fruit of poisonous tree', [
            'operation' => 'doctrine',
        ]);

        $this->assertArrayHasKey('doctrine_type', $result);
        $this->assertArrayHasKey('origin', $result);
        $this->assertArrayHasKey('application', $result);
        $this->assertArrayHasKey('croatian_equivalent', $result);

        // Verify AI was actually called (not stub response)
        $this->assertNotEquals('unknown', $result['doctrine_type']);
        $this->assertNotEquals('Unknown', $result['origin']);
    }

    public function test_handles_unknown_operation_gracefully(): void
    {
        $result = $this->service->analyzeConcept('test', [
            'operation' => 'invalid_operation',
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Unknown operation', $result['error']);
    }
}
