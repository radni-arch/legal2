<?php

namespace Tests\Integration;

use App\Models\AgentCollaboration;
use App\Models\LegalFactPattern;
use App\Models\User;
use App\Services\Collaboration\FactDrivenMultiAgentAnalyzer;

/**
 * Integration tests for Fact-Driven Multi-Agent Analyzer
 *
 * Tests the complete multi-agent collaboration workflow including
 * agent orchestration, result synthesis, and recommendation generation.
 *
 * NOTE: External dependencies (OpenAI, DecisionSearch) are mocked via IntegrationTestCase
 */
class FactDrivenMultiAgentAnalyzerTest extends IntegrationTestCase
{
    protected User $user;

    protected FactDrivenMultiAgentAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->analyzer = app(FactDrivenMultiAgentAnalyzer::class);
    }

    /** @test */
    public function it_orchestrates_all_four_agents()
    {
        // External services (OpenAI, DecisionSearch) already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->contract()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'parties' => [
                    ['name' => 'Plaintiff', 'role' => 'plaintiff'],
                    ['name' => 'Defendant', 'role' => 'defendant'],
                ],
                'legal_issues' => [
                    [
                        'issue' => 'Breach of contract',
                        'area_of_law' => 'contract',
                        'elements' => ['Valid contract', 'Breach', 'Damages'],
                    ],
                ],
                'evidence' => [
                    [
                        'type' => 'documentary',
                        'description' => 'Signed contract',
                        'strength' => 'strong',
                        'availability' => 'available',
                    ],
                ],
                'events' => [
                    ['description' => 'Contract signed', 'date' => '2024-01-15'],
                    ['description' => 'Breach occurred', 'date' => '2024-03-20'],
                ],
                'disputed_facts' => ['Amount of damages'],
                'undisputed_facts' => ['Contract was signed'],
                'summary' => 'Contract breach case',
            ],
        ]);

        $result = $this->analyzer->analyzeCaseWithAgents($factPattern->id);

        // Should create AgentCollaboration record
        $this->assertInstanceOf(AgentCollaboration::class, $result);
        $this->assertEquals($this->user->id, $result->user_id);
        $this->assertEquals($factPattern->id, $result->fact_pattern_id);

        // Should have results from all 4 agents
        $agentResults = $result->agent_results;
        $this->assertArrayHasKey('research_agent', $agentResults);
        $this->assertArrayHasKey('strategy_agent', $agentResults);
        $this->assertArrayHasKey('risk_agent', $agentResults);
        $this->assertArrayHasKey('evidence_agent', $agentResults);

        // Should have synthesis
        $synthesis = $result->synthesis;
        $this->assertArrayHasKey('unified_analysis', $synthesis);
        $this->assertArrayHasKey('key_insights', $synthesis);
        $this->assertArrayHasKey('success_probability', $synthesis);
        $this->assertArrayHasKey('final_recommendations', $synthesis);

        // Should have valid success probability
        $this->assertIsFloat($synthesis['success_probability']);
        $this->assertGreaterThanOrEqual(0, $synthesis['success_probability']);
        $this->assertLessThanOrEqual(1, $synthesis['success_probability']);
    }

    /** @test */
    public function research_agent_finds_precedents()
    {
        // DecisionSearchService already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'legal_area' => 'contract',
            'structured_facts' => [
                'legal_issues' => [
                    ['issue' => 'Contract formation', 'area_of_law' => 'contract'],
                ],
                'parties' => [],
                'events' => [],
                'evidence' => [],
                'summary' => 'Contract issue',
            ],
        ]);

        $result = $this->analyzer->analyzeCaseWithAgents($factPattern->id);

        $researchResults = $result->agent_results['research_agent'];

        $this->assertArrayHasKey('precedents_found', $researchResults);
        $this->assertGreaterThan(0, $researchResults['precedents_found']);
        $this->assertArrayHasKey('key_precedents', $researchResults);
        $this->assertNotEmpty($researchResults['key_precedents']);
    }

    /** @test */
    public function strategy_agent_develops_legal_strategy()
    {
        // External services (OpenAI, DecisionSearch) already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'legal_issues' => [
                    ['issue' => 'Breach of contract', 'elements' => ['Valid contract', 'Breach', 'Damages']],
                ],
                'facts_favorable_to_plaintiff' => [
                    'Written contract with clear terms',
                    'Documented breach',
                ],
                'facts_favorable_to_defendant' => [],
                'parties' => [],
                'events' => [],
                'evidence' => [],
                'summary' => 'Strong contract case',
            ],
        ]);

        $result = $this->analyzer->analyzeCaseWithAgents($factPattern->id);

        $strategyResults = $result->agent_results['strategy_agent'];

        $this->assertArrayHasKey('recommended_approach', $strategyResults);
        $this->assertArrayHasKey('key_arguments', $strategyResults);
        $this->assertArrayHasKey('potential_defenses', $strategyResults);
        $this->assertArrayHasKey('settlement_considerations', $strategyResults);
    }

    /** @test */
    public function risk_agent_assesses_risks()
    {
        // DecisionSearchService already mocked by IntegrationTestCase

        // High-risk case: many disputed facts, weak evidence
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'extraction_confidence' => 0.35, // Low confidence
            'structured_facts' => [
                'disputed_facts' => [
                    'Disputed fact 1',
                    'Disputed fact 2',
                    'Disputed fact 3',
                    'Disputed fact 4',
                    'Disputed fact 5',
                ],
                'undisputed_facts' => [],
                'facts_favorable_to_plaintiff' => [],
                'facts_favorable_to_defendant' => [
                    'Strong defense position',
                    'Contradictory evidence',
                ],
                'evidence' => [
                    [
                        'type' => 'testimonial',
                        'strength' => 'weak',
                        'availability' => 'unknown',
                    ],
                ],
                'legal_issues' => [],
                'parties' => [],
                'events' => [],
                'summary' => 'Risky case',
            ],
        ]);

        $result = $this->analyzer->analyzeCaseWithAgents($factPattern->id);

        $riskResults = $result->agent_results['risk_agent'];

        $this->assertArrayHasKey('risk_level', $riskResults);
        $this->assertArrayHasKey('risk_score', $riskResults);
        $this->assertArrayHasKey('key_risks', $riskResults);
        $this->assertArrayHasKey('mitigation_strategies', $riskResults);

        // High-risk case should have high risk score
        $this->assertGreaterThan(0.5, $riskResults['risk_score']);
        $this->assertContains($riskResults['risk_level'], ['medium', 'high']);

        // Success probability should be lower
        $this->assertLessThan(0.5, $result->synthesis['success_probability']);
    }

    /** @test */
    public function evidence_agent_analyzes_evidence_needs()
    {
        // DecisionSearchService already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'evidence' => [
                    [
                        'type' => 'documentary',
                        'description' => 'Contract',
                        'strength' => 'strong',
                        'availability' => 'available',
                    ],
                    [
                        'type' => 'testimonial',
                        'description' => 'Witness',
                        'strength' => 'moderate',
                        'availability' => 'needs_discovery',
                    ],
                ],
                'disputed_facts' => ['Fact needing proof'],
                'legal_issues' => [
                    ['issue' => 'Liability', 'elements' => ['Element 1', 'Element 2']],
                ],
                'parties' => [],
                'events' => [],
                'summary' => 'Evidence test',
            ],
        ]);

        $result = $this->analyzer->analyzeCaseWithAgents($factPattern->id);

        $evidenceResults = $result->agent_results['evidence_agent'];

        $this->assertArrayHasKey('evidence_strength_score', $evidenceResults);
        $this->assertArrayHasKey('evidence_gaps', $evidenceResults);
        $this->assertArrayHasKey('discovery_needs', $evidenceResults);
        $this->assertArrayHasKey('corroboration_needs', $evidenceResults);

        // Should identify discovery needs
        $this->assertNotEmpty($evidenceResults['discovery_needs']);
    }

    /** @test */
    public function it_synthesizes_agent_results()
    {
        // External services (OpenAI, DecisionSearch) already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'legal_issues' => [['issue' => 'Test issue']],
                'evidence' => [
                    ['type' => 'documentary', 'strength' => 'strong', 'availability' => 'available'],
                ],
                'facts_favorable_to_plaintiff' => ['Strong fact'],
                'disputed_facts' => [],
                'parties' => [],
                'events' => [],
                'summary' => 'Test',
            ],
        ]);

        $result = $this->analyzer->analyzeCaseWithAgents($factPattern->id);

        $synthesis = $result->synthesis;

        // Should have unified analysis
        $this->assertArrayHasKey('unified_analysis', $synthesis);
        $this->assertIsString($synthesis['unified_analysis']);

        // Should extract key insights
        $this->assertArrayHasKey('key_insights', $synthesis);
        $this->assertIsArray($synthesis['key_insights']);
        $this->assertNotEmpty($synthesis['key_insights']);

        // Should calculate success probability
        $this->assertArrayHasKey('success_probability', $synthesis);
        $this->assertIsFloat($synthesis['success_probability']);

        // Should provide recommendations
        $this->assertArrayHasKey('final_recommendations', $synthesis);
        $this->assertNotEmpty($synthesis['final_recommendations']);
    }

    /** @test */
    public function it_generates_appropriate_recommendations_based_on_probability()
    {
        // DecisionSearchService already mocked by IntegrationTestCase

        // Strong case
        $strongPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'extraction_confidence' => 0.95,
            'structured_facts' => [
                'evidence' => [
                    ['type' => 'documentary', 'strength' => 'strong', 'availability' => 'available'],
                    ['type' => 'expert', 'strength' => 'strong', 'availability' => 'available'],
                ],
                'facts_favorable_to_plaintiff' => ['Fact 1', 'Fact 2', 'Fact 3'],
                'facts_favorable_to_defendant' => [],
                'disputed_facts' => [],
                'undisputed_facts' => ['Fact A', 'Fact B'],
                'legal_issues' => [['issue' => 'Clear liability']],
                'parties' => [],
                'events' => [],
                'summary' => 'Strong case',
            ],
        ]);

        $strongResult = $this->analyzer->analyzeCaseWithAgents($strongPattern->id);

        // High probability case should recommend proceeding
        $strongProbability = $strongResult->synthesis['success_probability'];
        $this->assertGreaterThan(0.6, $strongProbability);

        $recommendations = $strongResult->synthesis['final_recommendations'];
        $hasProceeedRecommendation = collect($recommendations)->contains(function ($rec) {
            return str_contains(strtolower($rec), 'proceed') || str_contains(strtolower($rec), 'strong');
        });
        $this->assertTrue($hasProceeedRecommendation);
    }

    /** @test */
    public function it_stores_collaboration_metadata()
    {
        // DecisionSearchService already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'legal_issues' => [['issue' => 'Test']],
                'parties' => [],
                'events' => [],
                'evidence' => [],
                'summary' => 'Test',
            ],
        ]);

        $result = $this->analyzer->analyzeCaseWithAgents($factPattern->id);

        // Should be persisted to database
        $this->assertDatabaseHas('agent_collaborations', [
            'id' => $result->id,
            'user_id' => $this->user->id,
            'fact_pattern_id' => $factPattern->id,
        ]);

        // Should have agent_results as JSON
        $this->assertNotNull($result->agent_results);
        $this->assertIsArray($result->agent_results);

        // Should have synthesis as JSON
        $this->assertNotNull($result->synthesis);
        $this->assertIsArray($result->synthesis);

        // Should have timestamps
        $this->assertNotNull($result->created_at);
        $this->assertNotNull($result->updated_at);
    }

    /** @test */
    public function it_handles_complex_multi_issue_cases()
    {
        // External services (OpenAI, DecisionSearch) already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'legal_issues' => [
                    ['issue' => 'Breach of contract', 'area_of_law' => 'contract'],
                    ['issue' => 'Fraud', 'area_of_law' => 'tort'],
                    ['issue' => 'Unjust enrichment', 'area_of_law' => 'equity'],
                ],
                'evidence' => [
                    ['type' => 'documentary', 'strength' => 'strong'],
                    ['type' => 'testimonial', 'strength' => 'moderate'],
                ],
                'parties' => [
                    ['name' => 'Plaintiff', 'role' => 'plaintiff'],
                    ['name' => 'Defendant 1', 'role' => 'defendant'],
                    ['name' => 'Defendant 2', 'role' => 'defendant'],
                ],
                'events' => [
                    ['description' => 'Event 1', 'date' => '2024-01-01'],
                    ['description' => 'Event 2', 'date' => '2024-02-01'],
                    ['description' => 'Event 3', 'date' => '2024-03-01'],
                ],
                'summary' => 'Complex multi-issue case',
            ],
        ]);

        $result = $this->analyzer->analyzeCaseWithAgents($factPattern->id);

        // Should handle multiple issues
        $researchResults = $result->agent_results['research_agent'];
        $this->assertGreaterThan(0, $researchResults['precedents_found']);

        // Strategy should address complexity
        $strategyResults = $result->agent_results['strategy_agent'];
        $this->assertNotEmpty($strategyResults['key_arguments']);

        // Synthesis should integrate all aspects
        $this->assertNotEmpty($result->synthesis['key_insights']);
    }

    /** @test */
    public function it_can_retrieve_past_collaborations_for_fact_pattern()
    {
        // DecisionSearchService already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'legal_issues' => [['issue' => 'Test']],
                'parties' => [],
                'events' => [],
                'evidence' => [],
                'summary' => 'Test',
            ],
        ]);

        // Run analysis twice
        $result1 = $this->analyzer->analyzeCaseWithAgents($factPattern->id);
        sleep(1); // Ensure different timestamps
        $result2 = $this->analyzer->analyzeCaseWithAgents($factPattern->id);

        // Should have two collaboration records
        $collaborations = AgentCollaboration::where('fact_pattern_id', $factPattern->id)->get();
        $this->assertCount(2, $collaborations);

        // Should be ordered by creation date
        $this->assertTrue($collaborations[0]->created_at->lessThan($collaborations[1]->created_at));
    }

    /** @test */
    public function it_shares_context_between_agents()
    {
        // External services (OpenAI, DecisionSearch) already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'legal_issues' => [['issue' => 'Contract dispute']],
                'parties' => [],
                'events' => [],
                'evidence' => [],
                'summary' => 'Test',
            ],
        ]);

        $result = $this->analyzer->analyzeCaseWithAgents($factPattern->id);

        // Research agent should find precedents
        $this->assertGreaterThan(0, $result->agent_results['research_agent']['precedents_found']);

        // Strategy agent should reference shared context (precedents from research)
        $this->assertNotEmpty($result->agent_results['strategy_agent']['recommended_approach']);

        // Evidence agent should consider the legal issues and strategy
        $this->assertArrayHasKey('evidence_strength_score', $result->agent_results['evidence_agent']);
    }
}
