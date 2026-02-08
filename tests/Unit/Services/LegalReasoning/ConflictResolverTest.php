<?php

namespace Tests\Unit\Services\LegalReasoning;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Models\Law;
use App\Services\GraphDatabaseService;
use App\Services\LegalReasoning\ConflictResolver;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ConflictResolverTest extends TestCase
{
    use UsesTestDatabase;

    protected ConflictResolver $conflictResolver;

    protected $graphDbMock;

    protected $openAIMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphDbMock = Mockery::mock(GraphDatabaseService::class);
        $this->openAIMock = Mockery::mock(OpenAIService::class);

        $this->conflictResolver = new ConflictResolver(
            $this->graphDbMock,
            $this->openAIMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_finds_conflicts_successfully()
    {
        // Arrange
        $law = Law::factory()->create([
            'jurisdiction' => 'HR',
            'law_number' => 'NN 100/2020',
            'title' => 'Tax Law',
            'content' => 'Corporate tax rate is 18%',
            'embedding_vector' => array_fill(0, 1536, 0.5),
        ]);

        $similarLaw = Law::factory()->create([
            'jurisdiction' => 'HR',
            'law_number' => 'NN 50/2022',
            'title' => 'Tax Amendment',
            'content' => 'Corporate tax rate is 20%',
            'embedding_vector' => array_fill(0, 1536, 0.51), // Very similar
            'effective_date' => now()->subMonth(),
        ]);

        $mockLLMResponse = [
            'content' => json_encode([
                'conflicts' => [
                    [
                        'law_id' => 'NN 50/2022',
                        'conflict_type' => 'contradiction',
                        'severity' => 'high',
                        'description' => 'Different corporate tax rates specified',
                        'conflicting_provisions' => 'Tax rate: 18% vs 20%',
                    ],
                ],
            ]),
        ];

        $this->openAIMock
            ->shouldReceive('complete')
            ->once()
            ->andReturn($mockLLMResponse);

        // Act
        $conflicts = $this->conflictResolver->findConflicts($law->id);

        // Assert
        $this->assertNotEmpty($conflicts);
        $this->assertCount(1, $conflicts);
        $this->assertEquals('contradiction', $conflicts[0]['conflict_type']);
        $this->assertEquals('high', $conflicts[0]['severity']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_similar_laws_found()
    {
        // Arrange
        $law = Law::factory()->create([
            'jurisdiction' => 'HR',
            'embedding_vector' => array_fill(0, 1536, 0.5),
        ]);

        // No other laws in the database

        // Act
        $conflicts = $this->conflictResolver->findConflicts($law->id);

        // Assert
        $this->assertEmpty($conflicts);
    }

    /** @test */
    public function it_excludes_repealed_laws_from_similarity_search()
    {
        // Arrange
        $law = Law::factory()->create([
            'jurisdiction' => 'HR',
            'embedding_vector' => array_fill(0, 1536, 0.5),
        ]);

        Law::factory()->repealed()->create([
            'jurisdiction' => 'HR',
            'embedding_vector' => array_fill(0, 1536, 0.51), // Similar
        ]);

        // Act
        $conflicts = $this->conflictResolver->findConflicts($law->id);

        // Assert
        $this->assertEmpty($conflicts); // Repealed law should not be considered
    }

    /** @test */
    public function it_filters_by_embedding_similarity_threshold()
    {
        // Arrange
        $law = Law::factory()->create([
            'jurisdiction' => 'HR',
            'embedding_vector' => array_fill(0, 1536, 1.0),
        ]);

        // Create a law with low similarity (below 0.75 threshold)
        Law::factory()->create([
            'jurisdiction' => 'HR',
            'embedding_vector' => array_fill(0, 1536, 0.1), // Very different
            'effective_date' => now()->subMonth(),
        ]);

        // Act
        $conflicts = $this->conflictResolver->findConflicts($law->id);

        // Assert
        $this->assertEmpty($conflicts); // Should be filtered out by similarity threshold
    }

    /** @test */
    public function it_resolves_conflict_with_precedence_rules()
    {
        // Arrange
        $laws = [
            [
                'id' => '1',
                'law_number' => 'NN 100/2020',
                'title' => 'Old National Law',
                'jurisdiction' => 'HR',
                'effective_date' => '2020-01-01',
                'content' => 'Some content',
                'tags' => ['tax'],
            ],
            [
                'id' => '2',
                'law_number' => 'EU 2022/500',
                'title' => 'EU Directive',
                'jurisdiction' => 'EU',
                'effective_date' => '2022-01-01',
                'content' => 'EU directive content',
                'tags' => ['tax', 'international'],
            ],
        ];

        $this->openAIMock
            ->shouldReceive('complete')
            ->once() // Once for explanation
            ->andReturn([
                'content' => 'The EU directive takes precedence due to higher jurisdiction level.',
            ]);

        // Mock the CourtDecision query
        // (In real test, we'd create actual decisions, but mocking for simplicity)

        // Act
        $result = $this->conflictResolver->resolveConflict($laws);

        // Assert
        $this->assertArrayHasKey('winning_law', $result);
        $this->assertArrayHasKey('reasoning', $result);
        $this->assertArrayHasKey('citations', $result);
        $this->assertArrayHasKey('precedence_factors', $result);

        // EU law should win due to higher jurisdiction
        $this->assertEquals('EU', $result['winning_law']['jurisdiction']);
    }

    /** @test */
    public function it_applies_jurisdiction_hierarchy_correctly()
    {
        // Arrange - EU > HR > regional > county > local
        $laws = [
            ['id' => '1', 'jurisdiction' => 'local', 'effective_date' => '2023-01-01', 'content' => 'a', 'tags' => [], 'law_number' => 'L-100', 'title' => 'Local Law'],
            ['id' => '2', 'jurisdiction' => 'EU', 'effective_date' => '2023-01-01', 'content' => 'b', 'tags' => [], 'law_number' => 'EU-200', 'title' => 'EU Law'],
            ['id' => '3', 'jurisdiction' => 'HR', 'effective_date' => '2023-01-01', 'content' => 'c', 'tags' => [], 'law_number' => 'NN-300', 'title' => 'HR Law'],
        ];

        $this->openAIMock->shouldReceive('complete')->andReturn(['content' => 'Test']);

        // Act
        $result = $this->conflictResolver->resolveConflict($laws);

        // Assert
        $this->assertEquals('EU', $result['winning_law']['jurisdiction']);
    }

    /** @test */
    public function it_prefers_more_specific_law_when_same_jurisdiction()
    {
        // Arrange
        $laws = [
            [
                'id' => '1',
                'jurisdiction' => 'HR',
                'effective_date' => '2023-01-01',
                'content' => str_repeat('Very detailed law. ', 500), // Long = specific
                'chapter' => 'Chapter 5',
                'section' => 'Section 10',
                'tags' => ['tax', 'corporate', 'international', 'compliance'],
                'law_number' => 'NN 100/2023',
                'title' => 'Specific Law',
            ],
            [
                'id' => '2',
                'jurisdiction' => 'HR',
                'effective_date' => '2023-01-01',
                'content' => 'Short general law.',
                'tags' => ['tax'],
                'law_number' => 'NN 50/2023',
                'title' => 'General Law',
            ],
        ];

        $this->openAIMock->shouldReceive('complete')->andReturn(['content' => 'Test']);

        // Act
        $result = $this->conflictResolver->resolveConflict($laws);

        // Assert
        $this->assertEquals('Specific Law', $result['winning_law']['title']);
    }

    /** @test */
    public function it_prefers_newer_law_when_same_jurisdiction_and_specificity()
    {
        // Arrange
        $laws = [
            [
                'jurisdiction' => 'HR',
                'effective_date' => '2020-01-01',
                'content' => 'Law content',
                'tags' => ['tax'],
                'law_number' => 'NN 100/2020',
                'title' => 'Old Law',
            ],
            [
                'jurisdiction' => 'HR',
                'effective_date' => '2023-01-01',
                'content' => 'Law content',
                'tags' => ['tax'],
                'law_number' => 'NN 50/2023',
                'title' => 'New Law',
            ],
        ];

        $this->openAIMock->shouldReceive('complete')->andReturn(['content' => 'Test']);

        // Act
        $result = $this->conflictResolver->resolveConflict($laws);

        // Assert
        $this->assertEquals('New Law', $result['winning_law']['title']);
    }

    /** @test */
    public function it_throws_exception_when_less_than_two_laws_provided()
    {
        // Arrange
        $laws = [
            ['jurisdiction' => 'HR', 'effective_date' => '2023-01-01'],
        ];

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least 2 laws required for conflict resolution');

        $this->conflictResolver->resolveConflict($laws);
    }

    /** @test */
    public function it_finds_supporting_court_decisions()
    {
        // Arrange
        $law1 = Law::factory()->create(['law_number' => 'NN 100/2020']);
        $law2 = Law::factory()->create(['law_number' => 'NN 50/2022']);

        $decision = CourtDecision::factory()->create([
            'case_number' => 'Rev-1234/2023',
            'title' => 'Tax Case Decision',
            'court' => 'Supreme Court',
            'decision_date' => now()->subMonths(3),
        ]);

        // Create a document that cites the law
        CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'This decision applies NN 100/2020 and NN 50/2022 in its reasoning.',
        ]);

        $laws = [
            [
                'id' => $law1->id,
                'law_number' => 'NN 100/2020',
                'jurisdiction' => 'HR',
                'effective_date' => '2020-01-01',
                'content' => 'Content',
                'tags' => [],
                'title' => 'Law 1',
            ],
            [
                'id' => $law2->id,
                'law_number' => 'NN 50/2022',
                'jurisdiction' => 'HR',
                'effective_date' => '2022-01-01',
                'content' => 'Content',
                'tags' => [],
                'title' => 'Law 2',
            ],
        ];

        $this->openAIMock->shouldReceive('complete')->andReturn(['content' => 'Test']);

        // Act
        $result = $this->conflictResolver->resolveConflict($laws);

        // Assert
        $this->assertArrayHasKey('citations', $result);
        $this->assertNotEmpty($result['citations']);
        $this->assertEquals('Rev-1234/2023', $result['citations'][0]['case_number']);
    }

    /** @test */
    public function it_calculates_precedence_factors_correctly()
    {
        // Arrange
        $laws = [
            [
                'jurisdiction' => 'EU',
                'effective_date' => '2023-01-01',
                'content' => str_repeat('Detailed ', 1000),
                'chapter' => 'Chapter 1',
                'tags' => ['tax', 'corporate', 'finance', 'compliance'],
                'law_number' => 'EU 2023/100',
                'title' => 'EU Law',
            ],
            [
                'jurisdiction' => 'HR',
                'effective_date' => '2020-01-01',
                'content' => 'Short law',
                'tags' => ['tax'],
                'law_number' => 'NN 50/2020',
                'title' => 'HR Law',
            ],
        ];

        $this->openAIMock->shouldReceive('complete')->andReturn(['content' => 'Test']);

        // Act
        $result = $this->conflictResolver->resolveConflict($laws);

        // Assert
        $factors = $result['precedence_factors'];
        $this->assertArrayHasKey('jurisdiction_level', $factors);
        $this->assertArrayHasKey('specificity_score', $factors);
        $this->assertArrayHasKey('is_most_recent', $factors);
        $this->assertArrayHasKey('has_express_repeal', $factors);

        $this->assertEquals(5, $factors['jurisdiction_level']); // EU level
        $this->assertTrue($factors['is_most_recent']);
    }

    /** @test */
    public function it_detects_express_repeal_provisions()
    {
        // Arrange
        $laws = [
            [
                'jurisdiction' => 'HR',
                'effective_date' => '2023-01-01',
                'content' => 'This law repeals all previous provisions. Ovaj zakon stavlja van snage prethodne zakone.',
                'tags' => [],
                'law_number' => 'NN 100/2023',
                'title' => 'New Repeal Law',
            ],
            [
                'jurisdiction' => 'HR',
                'effective_date' => '2020-01-01',
                'content' => 'Old law content',
                'tags' => [],
                'law_number' => 'NN 50/2020',
                'title' => 'Old Law',
            ],
        ];

        $this->openAIMock->shouldReceive('complete')->andReturn(['content' => 'Test']);

        // Act
        $result = $this->conflictResolver->resolveConflict($laws);

        // Assert
        $this->assertTrue($result['precedence_factors']['has_express_repeal']);
    }

    /** @test */
    public function it_handles_llm_failure_gracefully_in_conflict_analysis()
    {
        // Arrange
        $law = Law::factory()->create([
            'jurisdiction' => 'HR',
            'embedding_vector' => array_fill(0, 1536, 0.5),
        ]);

        Law::factory()->create([
            'jurisdiction' => 'HR',
            'embedding_vector' => array_fill(0, 1536, 0.51), // Similar
            'effective_date' => now()->subMonth(),
        ]);

        $this->openAIMock
            ->shouldReceive('complete')
            ->once()
            ->andThrow(new \Exception('OpenAI API unavailable'));

        // Act
        $conflicts = $this->conflictResolver->findConflicts($law->id);

        // Assert
        $this->assertEmpty($conflicts); // Should handle gracefully and return empty
    }

    /** @test */
    public function it_handles_llm_failure_gracefully_in_explanation()
    {
        // Arrange
        $laws = [
            [
                'jurisdiction' => 'HR',
                'effective_date' => '2020-01-01',
                'content' => 'Content',
                'tags' => [],
                'law_number' => 'NN 100/2020',
                'title' => 'Law 1',
            ],
            [
                'jurisdiction' => 'HR',
                'effective_date' => '2023-01-01',
                'content' => 'Content',
                'tags' => [],
                'law_number' => 'NN 50/2023',
                'title' => 'Law 2',
            ],
        ];

        $this->openAIMock
            ->shouldReceive('complete')
            ->once()
            ->andThrow(new \Exception('OpenAI API unavailable'));

        // Act
        $result = $this->conflictResolver->resolveConflict($laws);

        // Assert
        $this->assertArrayHasKey('reasoning', $result);
        $this->assertEquals('Precedence determined by standard legal principles.', $result['reasoning']);
    }

    /** @test */
    public function it_parses_json_from_llm_response_correctly()
    {
        // Arrange
        $law = Law::factory()->create([
            'jurisdiction' => 'HR',
            'embedding_vector' => array_fill(0, 1536, 0.5),
        ]);

        Law::factory()->create([
            'jurisdiction' => 'HR',
            'embedding_vector' => array_fill(0, 1536, 0.51),
            'effective_date' => now()->subMonth(),
        ]);

        // LLM returns JSON wrapped in markdown
        $mockLLMResponse = [
            'content' => "Here's the analysis:\n```json\n".json_encode([
                'conflicts' => [
                    [
                        'law_id' => 'NN 50/2022',
                        'conflict_type' => 'overlap',
                        'severity' => 'medium',
                        'description' => 'Overlapping jurisdiction',
                        'conflicting_provisions' => 'Sections 1-5',
                    ],
                ],
            ])."\n```\nThat's my analysis.",
        ];

        $this->openAIMock
            ->shouldReceive('complete')
            ->once()
            ->andReturn($mockLLMResponse);

        // Act
        $conflicts = $this->conflictResolver->findConflicts($law->id);

        // Assert
        $this->assertNotEmpty($conflicts);
        $this->assertEquals('overlap', $conflicts[0]['conflict_type']);
    }
}
