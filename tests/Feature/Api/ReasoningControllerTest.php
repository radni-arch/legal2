<?php

namespace Tests\Feature\Api;

use App\Models\CourtDecision;
use App\Models\Law;
use App\Services\LegalReasoning\CitationAnalyzer;
use App\Services\LegalReasoning\ConflictResolver;
use App\Services\LegalReasoning\LogicEngine;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ReasoningControllerTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withApiAuth();

        // Provide default mocks for services - shouldIgnoreMissing() prevents errors when services aren't explicitly mocked
        $this->app->instance(ConflictResolver::class, Mockery::mock(ConflictResolver::class)->shouldIgnoreMissing());
        $this->app->instance(CitationAnalyzer::class, Mockery::mock(CitationAnalyzer::class)->shouldIgnoreMissing());
        $this->app->instance(LogicEngine::class, Mockery::mock(LogicEngine::class)->shouldIgnoreMissing());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper method to make authenticated POST requests
     */
    protected function authenticatedPostJson(string $uri, array $data = [])
    {
        // TestCase already handles API auth via $this->apiUser, so just call postJson directly
        return $this->postJson($uri, $data);
    }

    /** @test */
    public function it_analyzes_conflict_successfully()
    {
        // Arrange
        $law = Law::factory()->create();
        $lawId = (string) $law->id;

        $conflictResolverMock = Mockery::mock(ConflictResolver::class);
        $conflictResolverMock
            ->shouldReceive('findConflicts')
            ->once()
            ->with(Mockery::type('string'))
            ->andReturn([
                [
                    'law_id' => 'NN 50/2022',
                    'conflict_type' => 'contradiction',
                    'severity' => 'high',
                    'description' => 'Conflicting provisions',
                ],
            ]);

        $this->app->instance(ConflictResolver::class, $conflictResolverMock);

        // Act
        $response = $this->authenticatedPostJson('/api/reasoning/analyze-conflict', [
            'law_id' => $lawId,
        ]);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'request_id',
            'law_id',
            'conflicts',
            'conflict_count',
        ]);

        $response->assertJson([
            'success' => true,
            'law_id' => $lawId,
            'conflict_count' => 1,
        ]);
    }

    /** @test */
    public function it_validates_law_id_for_analyze_conflict()
    {
        // Act
        $response = $this->authenticatedPostJson('/api/reasoning/analyze-conflict', [
            // Missing law_id
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['law_id']);
    }

    /** @test */
    public function it_handles_errors_in_analyze_conflict()
    {
        // Arrange
        $law = Law::factory()->create();

        $conflictResolverMock = Mockery::mock(ConflictResolver::class);
        $conflictResolverMock
            ->shouldReceive('findConflicts')
            ->once()
            ->andThrow(new \Exception('Service unavailable'));

        $this->app->instance(ConflictResolver::class, $conflictResolverMock);

        // Act
        $response = $this->authenticatedPostJson('/api/reasoning/analyze-conflict', [
            'law_id' => $law->id,
        ]);

        // Assert
        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'error' => 'Service unavailable',
        ]);
    }

    /** @test */
    public function it_resolves_conflict_successfully()
    {
        // Arrange
        $laws = [
            ['id' => '1', 'title' => 'Law 1', 'jurisdiction' => 'HR', 'effective_date' => '2020-01-01'],
            ['id' => '2', 'title' => 'Law 2', 'jurisdiction' => 'EU', 'effective_date' => '2022-01-01'],
        ];

        $conflictResolverMock = Mockery::mock(ConflictResolver::class);
        $conflictResolverMock
            ->shouldReceive('resolveConflict')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return is_array($arg) && count($arg) === 2
                    && $arg[0]['id'] === '1' && $arg[0]['title'] === 'Law 1'
                    && $arg[1]['id'] === '2' && $arg[1]['title'] === 'Law 2';
            }))
            ->andReturn([
                'winning_law' => $laws[1],
                'reasoning' => 'EU law takes precedence',
                'citations' => [],
                'precedence_factors' => [
                    'jurisdiction_level' => 5,
                ],
            ]);

        $this->app->instance(ConflictResolver::class, $conflictResolverMock);

        // Act
        $response = $this->authenticatedPostJson('/api/reasoning/resolve-conflict', [
            'laws' => $laws,
        ]);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'request_id',
            'resolution' => [
                'winning_law',
                'reasoning',
                'citations',
                'precedence_factors',
            ],
        ]);

        $response->assertJson([
            'success' => true,
        ]);
    }

    /** @test */
    public function it_validates_laws_array_for_resolve_conflict()
    {
        // Act - Missing laws
        $response = $this->authenticatedPostJson('/api/reasoning/resolve-conflict', []);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['laws']);
    }

    /** @test */
    public function it_requires_at_least_two_laws_for_resolve_conflict()
    {
        // Act - Only one law
        $response = $this->authenticatedPostJson('/api/reasoning/resolve-conflict', [
            'laws' => [
                ['id' => '1', 'title' => 'Law 1'],
            ],
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['laws']);
    }

    /** @test */
    public function it_validates_law_structure_in_resolve_conflict()
    {
        // Act - Missing required fields
        $response = $this->authenticatedPostJson('/api/reasoning/resolve-conflict', [
            'laws' => [
                ['id' => '1'], // Missing title
                ['title' => 'Law 2'], // Missing id
            ],
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['laws.0.title', 'laws.1.id']);
    }

    /** @test */
    public function it_calculates_authority_score_successfully()
    {
        // Arrange
        $decision = CourtDecision::factory()->create();

        $citationAnalyzerMock = Mockery::mock(CitationAnalyzer::class);
        $citationAnalyzerMock
            ->shouldReceive('analyzeAuthority')
            ->once()
            ->with($decision->id)
            ->andReturn([
                'decision_id' => $decision->id,
                'authority_score' => 0.85,
                'precedent_strength' => 0.75,
                'influence_score' => 0.80,
                'citation_chain' => [],
                'influential_courts' => [],
            ]);

        $this->app->instance(CitationAnalyzer::class, $citationAnalyzerMock);

        // Act
        $response = $this->authenticatedPostJson('/api/reasoning/authority-score', [
            'decision_id' => $decision->id,
        ]);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'request_id',
            'decision_id',
            'analysis' => [
                'authority_score',
                'precedent_strength',
                'influence_score',
            ],
        ]);

        $response->assertJson([
            'success' => true,
            'decision_id' => $decision->id,
        ]);

        $this->assertEquals(0.85, $response->json('analysis.authority_score'));
    }

    /** @test */
    public function it_validates_decision_id_for_authority_score()
    {
        // Act
        $response = $this->authenticatedPostJson('/api/reasoning/authority-score', []);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['decision_id']);
    }

    /** @test */
    public function it_handles_errors_in_authority_score()
    {
        // Arrange
        $decision = CourtDecision::factory()->create();

        $citationAnalyzerMock = Mockery::mock(CitationAnalyzer::class);
        $citationAnalyzerMock
            ->shouldReceive('analyzeAuthority')
            ->once()
            ->andThrow(new \Exception('Analysis failed'));

        $this->app->instance(CitationAnalyzer::class, $citationAnalyzerMock);

        // Act
        $response = $this->authenticatedPostJson('/api/reasoning/authority-score', [
            'decision_id' => $decision->id,
        ]);

        // Assert
        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'error' => 'Analysis failed',
        ]);
    }

    /** @test */
    public function it_parses_logic_successfully()
    {
        // Arrange
        $lawText = 'If the debtor fails to pay within 30 days, then the creditor may sue for damages.';

        $logicEngineMock = Mockery::mock(LogicEngine::class);
        $logicEngineMock
            ->shouldReceive('parseLogicStructure')
            ->once()
            ->with($lawText)
            ->andReturn([
                'premises' => [
                    ['id' => 'P1', 'statement' => 'debtor fails to pay within 30 days'],
                ],
                'conclusions' => [
                    ['id' => 'C1', 'statement' => 'creditor may sue for damages'],
                ],
                'conditionals' => [],
                'exceptions' => [],
                'definitions' => [],
                'metadata' => [
                    'complexity_score' => 0.2,
                    'total_components' => 2,
                ],
            ]);

        $this->app->instance(LogicEngine::class, $logicEngineMock);

        // Act
        $response = $this->authenticatedPostJson('/api/reasoning/parse-logic', [
            'law_text' => $lawText,
        ]);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'request_id',
            'structure' => [
                'premises',
                'conclusions',
                'conditionals',
                'exceptions',
                'definitions',
                'metadata',
            ],
        ]);

        $response->assertJson([
            'success' => true,
        ]);

        $this->assertCount(1, $response->json('structure.premises'));
        $this->assertCount(1, $response->json('structure.conclusions'));
    }

    /** @test */
    public function it_validates_law_text_for_parse_logic()
    {
        // Act - Missing law_text
        $response = $this->authenticatedPostJson('/api/reasoning/parse-logic', []);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['law_text']);
    }

    /** @test */
    public function it_requires_minimum_length_for_parse_logic()
    {
        // Act - Too short
        $response = $this->authenticatedPostJson('/api/reasoning/parse-logic', [
            'law_text' => 'Short',
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['law_text']);
    }

    /** @test */
    public function it_applies_deductive_reasoning_successfully()
    {
        // Arrange
        $facts = [
            ['id' => 'F1', 'statement' => 'The debtor failed to pay within 30 days'],
        ];

        $rules = [
            [
                'id' => 'R1',
                'premises' => [
                    ['id' => 'P1', 'statement' => 'debtor failed to pay'],
                ],
                'conclusion' => 'Creditor may sue',
            ],
        ];

        $logicEngineMock = Mockery::mock(LogicEngine::class);
        $logicEngineMock
            ->shouldReceive('applyDeductiveReasoning')
            ->once()
            ->with($facts, $rules)
            ->andReturn([
                [
                    'rule_id' => 'R1',
                    'conclusion' => 'Creditor may sue',
                    'confidence' => 0.85,
                    'supporting_facts' => $facts,
                    'reasoning_chain' => [],
                ],
            ]);

        $this->app->instance(LogicEngine::class, $logicEngineMock);

        // Act
        $response = $this->authenticatedPostJson('/api/reasoning/apply-deductive', [
            'facts' => $facts,
            'rules' => $rules,
        ]);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'request_id',
            'inferences',
            'inference_count',
        ]);

        $response->assertJson([
            'success' => true,
            'inference_count' => 1,
        ]);

        $this->assertEquals('R1', $response->json('inferences.0.rule_id'));
        $this->assertEquals(0.85, $response->json('inferences.0.confidence'));
    }

    /** @test */
    public function it_validates_facts_array_for_deductive_reasoning()
    {
        // Act
        $response = $this->authenticatedPostJson('/api/reasoning/apply-deductive', [
            'rules' => [],
            // Missing facts
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['facts']);
    }

    /** @test */
    public function it_validates_rules_array_for_deductive_reasoning()
    {
        // Act
        $response = $this->authenticatedPostJson('/api/reasoning/apply-deductive', [
            'facts' => [],
            // Missing rules
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['rules']);
    }

    /** @test */
    public function it_handles_errors_in_deductive_reasoning()
    {
        // Arrange
        $logicEngineMock = Mockery::mock(LogicEngine::class);
        $logicEngineMock
            ->shouldReceive('applyDeductiveReasoning')
            ->once()
            ->andThrow(new \Exception('Reasoning failed'));

        $this->app->instance(LogicEngine::class, $logicEngineMock);

        // Act
        $response = $this->authenticatedPostJson('/api/reasoning/apply-deductive', [
            'facts' => [['statement' => 'fact']],
            'rules' => [['conclusion' => 'result']],
        ]);

        // Assert
        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'error' => 'Reasoning failed',
        ]);
    }

    /** @test */
    public function it_includes_request_id_in_all_responses()
    {
        // Arrange
        $law = Law::factory()->create();

        $conflictResolverMock = Mockery::mock(ConflictResolver::class);
        $conflictResolverMock
            ->shouldReceive('findConflicts')
            ->andReturn([]);

        $this->app->instance(ConflictResolver::class, $conflictResolverMock);

        // Act
        $response = $this->authenticatedPostJson('/api/reasoning/analyze-conflict', [
            'law_id' => $law->id,
        ]);

        // Assert
        $response->assertJsonStructure(['request_id']);
        $this->assertStringStartsWith('reasoning_', $response->json('request_id'));
    }
}
