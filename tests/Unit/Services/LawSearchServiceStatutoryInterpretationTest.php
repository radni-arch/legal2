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

class LawSearchServiceStatutoryInterpretationTest extends TestCase
{
    private LawSearchService $lawSearchService;

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

        // Mock chat response for AI-powered interpretation
        $this->mockOpenAI
            ->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Analiza: Članak 9 ZKP štiti pravo na pošteno suđenje kroz načelo kontradiktornosti i javnosti postupka. Gramatička analiza pokazuje da su ključni pojmovi "usmeno", "javno" i "kontradikatorno" međusobno povezani.',
                        ],
                    ],
                ],
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
                    'content' => 'ZKP Članak 9 regulira načelo neposrednosti i kontradiktornosti.',
                    'similarity' => 0.85,
                    'metadata' => ['law_number' => 'ZKP', 'article' => '9'],
                ],
            ]);

        $this->mockDecisionVectorStore
            ->shouldReceive('search')
            ->andReturn([]);

        // Mock law article retrieval
        $this->mockLawGetArticleTool
            ->shouldReceive('getArticle')
            ->andReturn([
                'content' => 'Članak 9. Kazneni postupak se vodi usmeno, neposredno i javno. Načelo kontradiktornosti osigurava pravo stranaka na sudjelovanje u postupku.',
                'title' => 'Načela kaznenog postupka',
                'law_code' => 'ZKP',
                'article_number' => '9',
            ]);

        $this->lawSearchService = new LawSearchService(
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

    /** @test */
    public function it_interprets_statute_history_operation()
    {
        $result = $this->lawSearchService->interpretStatute('ZKP Članak 9', [
            'operation' => 'history',
        ]);

        // Verify response structure
        $this->assertArrayHasKey('statute', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('amendments', $result);
        $this->assertArrayHasKey('legislative_intent', $result);
        $this->assertArrayHasKey('current_version', $result);
        $this->assertEquals('ZKP Članak 9', $result['statute']);

        // Should NOT be stub data
        $this->assertNotEquals('Statute Title', $result['title']);
        $this->assertIsArray($result['amendments']);
    }

    /** @test */
    public function it_interprets_statute_construction_operation()
    {
        $result = $this->lawSearchService->interpretStatute('ZKP Članak 9', [
            'operation' => 'construction',
            'method' => 'systematic',
        ]);

        // Verify response structure
        $this->assertArrayHasKey('statute', $result);
        $this->assertArrayHasKey('method', $result);
        $this->assertArrayHasKey('interpretation', $result);
        $this->assertArrayHasKey('plain_meaning', $result);
        $this->assertArrayHasKey('grammatical_analysis', $result);
        $this->assertArrayHasKey('key_terms', $result);
        $this->assertArrayHasKey('ambiguities', $result);

        // Should NOT be stub data
        $this->assertNotEquals('Plain meaning', $result['plain_meaning']);
        $this->assertNotEquals('Interpretation result', $result['interpretation']);
        $this->assertEquals('systematic', $result['method']);
    }

    /** @test */
    public function it_interprets_statute_conflicts_operation()
    {
        $result = $this->lawSearchService->interpretStatute('ZKP Članak 9', [
            'operation' => 'conflicts',
        ]);

        // Verify response structure
        $this->assertArrayHasKey('primary_statute', $result);
        $this->assertArrayHasKey('conflicts', $result);
        $this->assertArrayHasKey('resolution', $result);
        $this->assertArrayHasKey('recommended_interpretation', $result);

        // Should NOT be stub data
        $this->assertNotEquals('Resolution strategy', $result['resolution']);
        $this->assertIsArray($result['conflicts']);
    }

    /** @test */
    public function it_interprets_statute_framework_operation()
    {
        $result = $this->lawSearchService->interpretStatute('ZKP Članak 9', [
            'operation' => 'framework',
        ]);

        // Verify response structure
        $this->assertArrayHasKey('statute', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('framework', $result);
        $this->assertArrayHasKey('hierarchy', $result);

        // Verify framework structure
        $framework = $result['framework'];
        $this->assertIsArray($framework);
        $this->assertArrayHasKey('primary_law', $framework);
        $this->assertArrayHasKey('constitutional_basis', $framework);
        $this->assertArrayHasKey('implementing_regulations', $framework);
        $this->assertArrayHasKey('related_provisions', $framework);

        // Should NOT be stub data
        $this->assertNotEquals('Statute Title', $result['title']);
        $this->assertIsArray($result['hierarchy']);
    }

    /** @test */
    public function it_analyzes_statutory_history_with_amendments()
    {
        $result = $this->lawSearchService->interpretStatute('ZKP Članak 9', [
            'operation' => 'history',
        ]);

        $this->assertArrayHasKey('amendments', $result);
        $this->assertIsArray($result['amendments']);

        // If amendments exist, verify structure
        if (! empty($result['amendments'])) {
            foreach ($result['amendments'] as $amendment) {
                $this->assertArrayHasKey('date', $amendment);
                $this->assertArrayHasKey('description', $amendment);
            }
        }
    }

    /** @test */
    public function it_constructs_statutory_meaning_with_croatian_legal_context()
    {
        $result = $this->lawSearchService->interpretStatute('ZKP Članak 9', [
            'operation' => 'construction',
            'method' => 'teleological',
        ]);

        // Verify interpretation is not empty and contains content
        $this->assertNotEmpty($result['interpretation']);
        $this->assertIsString($result['interpretation']);

        // Should use AI to generate interpretation (mocked in setUp)
        // The interpretation should contain Croatian legal terminology
        $interpretation = strtolower($result['interpretation']);
        $this->assertTrue(
            str_contains($interpretation, 'članak') ||
            str_contains($interpretation, 'zakon') ||
            str_contains($interpretation, 'pravo') ||
            str_contains($interpretation, 'analiza'),
            'Interpretation should contain Croatian legal terminology'
        );
    }

    /** @test */
    public function it_resolves_statutory_conflicts_with_vector_search()
    {
        $result = $this->lawSearchService->interpretStatute('ZKP Članak 9', [
            'operation' => 'conflicts',
        ]);

        $this->assertIsArray($result['conflicts']);
        $this->assertArrayHasKey('resolution', $result);

        // Resolution should not be stub data
        $this->assertNotEquals('Resolution strategy', $result['resolution']);

        // If conflicts are found, verify their structure
        if (! empty($result['conflicts'])) {
            foreach ($result['conflicts'] as $conflict) {
                $this->assertArrayHasKey('conflicting_statute', $conflict);
                $this->assertArrayHasKey('conflict_type', $conflict);
                $this->assertArrayHasKey('similarity_score', $conflict);
            }
        }
    }

    /** @test */
    public function it_maps_regulatory_framework_hierarchy()
    {
        $result = $this->lawSearchService->interpretStatute('ZKP Članak 9', [
            'operation' => 'framework',
        ]);

        $this->assertArrayHasKey('hierarchy', $result);
        $this->assertIsArray($result['hierarchy']);

        $framework = $result['framework'];
        $this->assertArrayHasKey('constitutional_basis', $framework);
        $this->assertArrayHasKey('primary_law', $framework);

        // Verify hierarchy levels are properly structured
        if (! empty($result['hierarchy'])) {
            foreach ($result['hierarchy'] as $level) {
                $this->assertArrayHasKey('level', $level);
                $this->assertArrayHasKey('law', $level);
            }
        }
    }

    /** @test */
    public function it_handles_unknown_statute_gracefully()
    {
        $result = $this->lawSearchService->interpretStatute('NONEXISTENT Članak 999', [
            'operation' => 'history',
        ]);

        // Should still return a valid response structure
        $this->assertArrayHasKey('statute', $result);
        $this->assertEquals('NONEXISTENT Članak 999', $result['statute']);

        // Should handle gracefully (empty arrays for unknown statutes)
        $this->assertIsArray($result['amendments']);
    }

    /** @test */
    public function it_uses_default_operation_when_not_specified()
    {
        $result = $this->lawSearchService->interpretStatute('ZKP Članak 9');

        // Default should be 'history'
        $this->assertArrayHasKey('amendments', $result);
        $this->assertArrayHasKey('legislative_intent', $result);
        $this->assertArrayHasKey('current_version', $result);
    }

    /** @test */
    public function it_supports_different_construction_methods()
    {
        $methods = ['textualist', 'systematic', 'teleological'];

        foreach ($methods as $method) {
            $result = $this->lawSearchService->interpretStatute('ZKP Članak 9', [
                'operation' => 'construction',
                'method' => $method,
            ]);

            $this->assertEquals($method, $result['method']);
            $this->assertArrayHasKey('interpretation', $result);
        }
    }
}
