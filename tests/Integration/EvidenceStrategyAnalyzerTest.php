<?php

namespace Tests\Integration;

use App\Models\LegalFactPattern;
use App\Models\User;
use App\Services\Evidence\EvidenceStrategyAnalyzer;

/**
 * Integration tests for Evidence Strategy Analyzer
 *
 * Tests the complete evidence analysis workflow including gap identification,
 * discovery planning, and presentation strategy development.
 *
 * NOTE: External dependencies (OpenAI, DecisionSearch) are mocked via IntegrationTestCase
 */
class EvidenceStrategyAnalyzerTest extends IntegrationTestCase
{
    protected User $user;

    protected EvidenceStrategyAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->analyzer = app(EvidenceStrategyAnalyzer::class);
    }

    /** @test */
    public function it_analyzes_evidence_needs_for_complete_case()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'legal_area' => 'contract',
            'structured_facts' => [
                'parties' => [
                    ['name' => 'Plaintiff Corp', 'role' => 'plaintiff', 'type' => 'corporation'],
                    ['name' => 'Defendant LLC', 'role' => 'defendant', 'type' => 'corporation'],
                ],
                'events' => [
                    [
                        'description' => 'Contract signed',
                        'date' => '2024-01-15',
                        'significance' => 'Formation',
                    ],
                    [
                        'description' => 'Breach occurred',
                        'date' => '2024-03-20',
                        'significance' => 'Violation',
                    ],
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
                    [
                        'type' => 'testimonial',
                        'description' => 'Witness testimony',
                        'strength' => 'moderate',
                        'availability' => 'needs_discovery',
                    ],
                    [
                        'type' => 'expert',
                        'description' => 'Damages calculation',
                        'strength' => 'strong',
                        'availability' => 'unknown',
                    ],
                ],
                'disputed_facts' => [
                    'Whether notice was properly given',
                    'Amount of actual damages',
                ],
                'undisputed_facts' => [
                    'Contract was signed on January 15, 2024',
                ],
                'summary' => 'Contract breach case with damages',
            ],
        ]);

        $strategy = $this->analyzer->analyzeEvidenceNeeds($factPattern->id);

        // Verify structure
        $this->assertArrayHasKey('existing_evidence', $strategy);
        $this->assertArrayHasKey('evidence_gaps', $strategy);
        $this->assertArrayHasKey('discovery_plan', $strategy);
        $this->assertArrayHasKey('presentation_strategy', $strategy);
        $this->assertArrayHasKey('overall_strength_score', $strategy);

        // Verify existing evidence analysis
        $existing = $strategy['existing_evidence'];
        $this->assertArrayHasKey('total_count', $existing);
        $this->assertEquals(3, $existing['total_count']);
        $this->assertArrayHasKey('by_type', $existing);
        $this->assertArrayHasKey('documentary', $existing['by_type']);
        $this->assertArrayHasKey('by_strength', $existing);
        $this->assertArrayHasKey('by_availability', $existing);

        // Verify evidence gaps identified
        $gaps = $strategy['evidence_gaps'];
        $this->assertNotEmpty($gaps);
        $this->assertArrayHasKey('missing_evidence', $gaps);
        $this->assertArrayHasKey('weak_evidence', $gaps);

        // Verify discovery plan generated
        $discovery = $strategy['discovery_plan'];
        $this->assertArrayHasKey('interrogatories', $discovery);
        $this->assertArrayHasKey('document_requests', $discovery);
        $this->assertArrayHasKey('deposition_targets', $discovery);
        $this->assertNotEmpty($discovery['interrogatories']);

        // Verify presentation strategy
        $presentation = $strategy['presentation_strategy'];
        $this->assertArrayHasKey('trial_strategy', $presentation);
        $this->assertArrayHasKey('evidence_order', $presentation);
        $this->assertArrayHasKey('key_evidence', $presentation);

        // Verify overall strength calculated
        $this->assertIsFloat($strategy['overall_strength_score']);
        $this->assertGreaterThanOrEqual(0, $strategy['overall_strength_score']);
        $this->assertLessThanOrEqual(1, $strategy['overall_strength_score']);
    }

    /** @test */
    public function it_identifies_evidence_gaps_correctly()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'legal_issues' => [
                    ['issue' => 'Negligence', 'elements' => ['Duty', 'Breach', 'Causation', 'Damages']],
                ],
                'evidence' => [
                    [
                        'type' => 'documentary',
                        'description' => 'Medical records',
                        'strength' => 'strong',
                        'availability' => 'available',
                    ],
                    // Missing evidence for causation and duty elements
                ],
                'disputed_facts' => [
                    'Whether defendant owed a duty',
                    'Whether defendant\'s actions caused injury',
                ],
                'parties' => [],
                'events' => [],
                'summary' => 'Test',
            ],
        ]);

        $strategy = $this->analyzer->analyzeEvidenceNeeds($factPattern->id);

        $gaps = $strategy['evidence_gaps'];

        // Should identify gaps for disputed facts
        $this->assertGreaterThan(0, count($gaps['missing_evidence']));

        // Should flag weak or unavailable evidence
        $this->assertArrayHasKey('weak_evidence', $gaps);
    }

    /** @test */
    public function it_generates_discovery_plan_with_interrogatories()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'parties' => [
                    ['name' => 'Plaintiff', 'role' => 'plaintiff'],
                    ['name' => 'Defendant', 'role' => 'defendant'],
                ],
                'disputed_facts' => [
                    'Whether defendant knew of the defect',
                    'When defendant became aware of the issue',
                ],
                'evidence' => [
                    [
                        'type' => 'testimonial',
                        'description' => 'Witness accounts',
                        'availability' => 'needs_discovery',
                    ],
                ],
                'events' => [],
                'legal_issues' => [],
                'summary' => 'Test',
            ],
        ]);

        $strategy = $this->analyzer->analyzeEvidenceNeeds($factPattern->id);

        $discovery = $strategy['discovery_plan'];

        // Should generate interrogatories for disputed facts
        $this->assertNotEmpty($discovery['interrogatories']);
        $this->assertIsArray($discovery['interrogatories']);

        // Should generate document requests
        $this->assertArrayHasKey('document_requests', $discovery);
        $this->assertNotEmpty($discovery['document_requests']);

        // Should identify deposition targets
        $this->assertArrayHasKey('deposition_targets', $discovery);

        // Should have timeline
        $this->assertArrayHasKey('timeline', $discovery);
        $this->assertArrayHasKey('phase_1', $discovery['timeline']);
        $this->assertArrayHasKey('phase_2', $discovery['timeline']);
    }

    /** @test */
    public function it_finds_evidence_precedents()
    {
        // External services (DecisionSearch) already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'legal_area' => 'tort',
            'structured_facts' => [
                'legal_issues' => [
                    ['issue' => 'Admissibility of expert testimony'],
                ],
                'evidence' => [
                    [
                        'type' => 'expert',
                        'description' => 'Medical expert',
                        'strength' => 'strong',
                    ],
                ],
                'parties' => [],
                'events' => [],
                'summary' => 'Test',
            ],
        ]);

        $precedents = $this->analyzer->findEvidencePrecedents($factPattern->id);

        $this->assertNotEmpty($precedents);
        $this->assertArrayHasKey('similar_cases', $precedents);
        $this->assertArrayHasKey('admissibility_standards', $precedents);
        $this->assertArrayHasKey('relevant_rulings', $precedents);
    }

    /** @test */
    public function it_calculates_evidence_strength_score()
    {
        // Strong case: all evidence available and strong
        $strongPattern = LegalFactPattern::factory()->create([
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
                        'type' => 'documentary',
                        'description' => 'Email chain',
                        'strength' => 'strong',
                        'availability' => 'available',
                    ],
                    [
                        'type' => 'expert',
                        'description' => 'Expert report',
                        'strength' => 'strong',
                        'availability' => 'available',
                    ],
                ],
                'disputed_facts' => [],
                'undisputed_facts' => ['Fact 1', 'Fact 2', 'Fact 3'],
                'parties' => [],
                'events' => [],
                'legal_issues' => [],
                'summary' => 'Strong case',
            ],
        ]);

        $strongStrategy = $this->analyzer->analyzeEvidenceNeeds($strongPattern->id);
        $strongScore = $strongStrategy['overall_strength_score'];

        // Weak case: evidence unavailable or weak
        $weakPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'evidence' => [
                    [
                        'type' => 'testimonial',
                        'description' => 'Hearsay',
                        'strength' => 'weak',
                        'availability' => 'unknown',
                    ],
                ],
                'disputed_facts' => ['Fact 1', 'Fact 2', 'Fact 3', 'Fact 4'],
                'undisputed_facts' => [],
                'parties' => [],
                'events' => [],
                'legal_issues' => [],
                'summary' => 'Weak case',
            ],
        ]);

        $weakStrategy = $this->analyzer->analyzeEvidenceNeeds($weakPattern->id);
        $weakScore = $weakStrategy['overall_strength_score'];

        // Strong case should have higher score
        $this->assertGreaterThan($weakScore, $strongScore);
        $this->assertGreaterThan(0.6, $strongScore);
        $this->assertLessThan(0.5, $weakScore);
    }

    /** @test */
    public function it_identifies_corroboration_needs()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'evidence' => [
                    [
                        'type' => 'testimonial',
                        'description' => 'Single witness account',
                        'strength' => 'moderate',
                        'availability' => 'available',
                    ],
                ],
                'disputed_facts' => [
                    'Critical disputed fact with no corroboration',
                ],
                'parties' => [],
                'events' => [],
                'legal_issues' => [],
                'summary' => 'Test',
            ],
        ]);

        $strategy = $this->analyzer->analyzeEvidenceNeeds($factPattern->id);

        $corroboration = $strategy['corroboration_needs'];

        $this->assertNotEmpty($corroboration);
        $this->assertArrayHasKey('uncorroborated_testimony', $corroboration);
        $this->assertArrayHasKey('disputed_facts_needing_support', $corroboration);
        $this->assertArrayHasKey('recommendations', $corroboration);
    }

    /** @test */
    public function it_develops_presentation_strategy()
    {
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
                        'type' => 'expert',
                        'description' => 'Damages expert',
                        'strength' => 'strong',
                        'availability' => 'available',
                    ],
                    [
                        'type' => 'testimonial',
                        'description' => 'Witness',
                        'strength' => 'moderate',
                        'availability' => 'available',
                    ],
                ],
                'events' => [
                    ['description' => 'Event 1', 'date' => '2024-01-01'],
                    ['description' => 'Event 2', 'date' => '2024-02-01'],
                ],
                'legal_issues' => [
                    ['issue' => 'Liability'],
                ],
                'parties' => [],
                'summary' => 'Test',
            ],
        ]);

        $strategy = $this->analyzer->analyzeEvidenceNeeds($factPattern->id);

        $presentation = $strategy['presentation_strategy'];

        // Should organize evidence by strength
        $this->assertArrayHasKey('key_evidence', $presentation);
        $this->assertArrayHasKey('supporting_evidence', $presentation);

        // Should suggest evidence order
        $this->assertArrayHasKey('evidence_order', $presentation);
        $this->assertIsArray($presentation['evidence_order']);

        // Should provide trial strategy
        $this->assertArrayHasKey('trial_strategy', $presentation);
        $this->assertArrayHasKey('opening', $presentation['trial_strategy']);
        $this->assertArrayHasKey('case_in_chief', $presentation['trial_strategy']);
        $this->assertArrayHasKey('closing', $presentation['trial_strategy']);
    }

    /** @test */
    public function it_handles_cases_with_minimal_evidence()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'evidence' => [],
                'parties' => [['name' => 'Test', 'role' => 'plaintiff']],
                'events' => [],
                'legal_issues' => [['issue' => 'Some issue']],
                'disputed_facts' => ['Everything is disputed'],
                'summary' => 'Case with no evidence',
            ],
        ]);

        $strategy = $this->analyzer->analyzeEvidenceNeeds($factPattern->id);

        // Should still provide analysis
        $this->assertArrayHasKey('existing_evidence', $strategy);
        $this->assertEquals(0, $strategy['existing_evidence']['total_count']);

        // Should identify extensive gaps
        $this->assertArrayHasKey('evidence_gaps', $strategy);
        $this->assertNotEmpty($strategy['evidence_gaps']['missing_evidence']);

        // Should have low strength score
        $this->assertLessThan(0.3, $strategy['overall_strength_score']);

        // Should recommend extensive discovery
        $this->assertArrayHasKey('discovery_plan', $strategy);
        $this->assertNotEmpty($strategy['discovery_plan']['interrogatories']);
    }

    /** @test */
    public function it_provides_expert_witness_recommendations()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'legal_issues' => [
                    [
                        'issue' => 'Medical malpractice',
                        'elements' => ['Standard of care', 'Breach', 'Causation', 'Damages'],
                    ],
                ],
                'evidence' => [
                    [
                        'type' => 'documentary',
                        'description' => 'Medical records',
                        'strength' => 'moderate',
                    ],
                ],
                'parties' => [],
                'events' => [],
                'summary' => 'Medical malpractice case',
            ],
        ]);

        $strategy = $this->analyzer->analyzeEvidenceNeeds($factPattern->id);

        $discovery = $strategy['discovery_plan'];

        $this->assertArrayHasKey('expert_witnesses_needed', $discovery);
        $this->assertNotEmpty($discovery['expert_witnesses_needed']);

        // Should recommend medical expert for malpractice case
        $experts = collect($discovery['expert_witnesses_needed']);
        $hasMedicalExpert = $experts->contains(function ($expert) {
            return str_contains(strtolower($expert['type']), 'medical');
        });

        $this->assertTrue($hasMedicalExpert);
    }

    /** @test */
    public function it_estimates_discovery_timeline()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'evidence' => [
                    [
                        'type' => 'documentary',
                        'availability' => 'needs_discovery',
                    ],
                    [
                        'type' => 'testimonial',
                        'availability' => 'needs_discovery',
                    ],
                ],
                'parties' => [['name' => 'Defendant', 'role' => 'defendant']],
                'disputed_facts' => ['Fact 1', 'Fact 2'],
                'events' => [],
                'legal_issues' => [],
                'summary' => 'Test',
            ],
        ]);

        $strategy = $this->analyzer->analyzeEvidenceNeeds($factPattern->id);

        $timeline = $strategy['discovery_plan']['timeline'];

        // Should have phased timeline
        $this->assertArrayHasKey('phase_1', $timeline);
        $this->assertArrayHasKey('phase_2', $timeline);
        $this->assertArrayHasKey('phase_3', $timeline);
        $this->assertArrayHasKey('phase_4', $timeline);

        // Each phase should have description and duration
        $this->assertArrayHasKey('description', $timeline['phase_1']);
        $this->assertArrayHasKey('duration', $timeline['phase_1']);
        $this->assertArrayHasKey('activities', $timeline['phase_1']);
    }
}
