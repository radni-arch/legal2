<?php

namespace Tests\Feature;

use App\Models\LegalCase;
use App\Modules\Evidence\EvidenceAnalysisModule;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EvidenceModuleTest extends TestCase
{
    use UsesTestDatabase;

    protected LegalCase $testCase;

    protected array $testEvidence;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testCase = LegalCase::create([
            'title' => 'Test Criminal Case - Evidence Analysis',
            'description' => 'Defendant charged with theft. Evidence includes warrantless home search and coerced confession.',
            'legal_area' => 'criminal',
            'case_type' => 'criminal',
            'status' => 'active',
        ]);

        $this->testEvidence = [
            [
                'id' => 'ev1',
                'type' => 'physical',
                'description' => 'Stolen items found during home search',
                'collection_method' => 'warrantless search',
                'collection_location' => 'defendant home',
            ],
            [
                'id' => 'ev2',
                'type' => 'testimonial',
                'description' => 'Defendant confession',
                'collection_method' => 'police interrogation',
                'lawyer_present' => false,
            ],
        ];
    }

    /** @test */
    public function it_can_analyze_evidence()
    {
        $module = app(EvidenceAnalysisModule::class);

        $result = $module->analyzeEvidence($this->testCase->id, $this->testEvidence);

        $this->assertArrayHasKey('evidence_analysis', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('recommended_motions', $result);
        $this->assertCount(2, $result['evidence_analysis']);
    }

    /** @test */
    public function it_detects_warrantless_search_violations()
    {
        $module = app(EvidenceAnalysisModule::class);

        $result = $module->analyzeEvidence($this->testCase->id, [$this->testEvidence[0]]);

        $evidence = $result['evidence_analysis'][0];

        // Should detect constitutional violation (Ustav RH Članak 34)
        $this->assertNotEmpty($evidence['constitutional_issues']);
        $this->assertGreaterThan(70, $evidence['excludability_score']);
    }

    /** @test */
    public function it_detects_right_to_counsel_violations()
    {
        $module = app(EvidenceAnalysisModule::class);

        $result = $module->analyzeEvidence($this->testCase->id, [$this->testEvidence[1]]);

        $evidence = $result['evidence_analysis'][0];

        // Should detect violation of right to defense (Ustav RH Članak 29)
        $this->assertNotEmpty($evidence['constitutional_issues']);
    }

    /** @test */
    public function it_calculates_excludability_score()
    {
        $module = app(EvidenceAnalysisModule::class);

        $result = $module->analyzeEvidence($this->testCase->id, $this->testEvidence);

        foreach ($result['evidence_analysis'] as $evidence) {
            $this->assertArrayHasKey('excludability_score', $evidence);
            $this->assertIsInt($evidence['excludability_score']);
            $this->assertGreaterThanOrEqual(0, $evidence['excludability_score']);
            $this->assertLessThanOrEqual(100, $evidence['excludability_score']);
        }
    }

    /** @test */
    public function it_generates_suppression_motion()
    {
        $module = app(EvidenceAnalysisModule::class);

        $evidenceIds = ['ev1', 'ev2'];
        $result = $module->generateSuppressionMotion($this->testCase->id, $evidenceIds);

        $this->assertArrayHasKey('motion_type', $result);
        $this->assertEquals('Prijedlog za isključenje dokaza', $result['motion_type']);
        $this->assertArrayHasKey('motion_text', $result);
        $this->assertArrayHasKey('legal_authorities', $result);
        $this->assertArrayHasKey('filing_instructions', $result);
    }

    /** @test */
    public function api_endpoint_analyzes_evidence()
    {
        $response = $this->postJson("/api/evidence/analyze/{$this->testCase->id}", [
            'evidence' => $this->testEvidence,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'evidence_analysis',
                    'summary',
                    'recommended_motions',
                ],
            ]);
    }

    /** @test */
    public function api_endpoint_generates_suppression_motion()
    {
        $response = $this->postJson("/api/evidence/suppress-motion/{$this->testCase->id}", [
            'evidence_ids' => ['ev1', 'ev2'],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'motion_type',
                    'motion_text',
                    'legal_authorities',
                ],
            ]);
    }

    /** @test */
    public function api_endpoint_checks_admissibility()
    {
        $response = $this->postJson("/api/evidence/check-admissibility/{$this->testCase->id}", [
            'evidence' => $this->testEvidence[0],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'admissible',
                    'confidence',
                    'issues',
                ],
            ]);
    }

    /** @test */
    public function api_endpoint_detects_constitutional_violations()
    {
        $response = $this->postJson("/api/evidence/constitutional-violations/{$this->testCase->id}", [
            'evidence' => $this->testEvidence[0],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'violations',
                    'count',
                    'severity_max',
                ],
            ]);
    }

    /** @test */
    public function highly_challengeable_evidence_is_sorted_first()
    {
        $module = app(EvidenceAnalysisModule::class);

        $result = $module->analyzeEvidence($this->testCase->id, $this->testEvidence);

        $scores = array_column($result['evidence_analysis'], 'excludability_score');

        // Should be sorted descending
        $sortedScores = $scores;
        rsort($sortedScores);

        $this->assertEquals($sortedScores, $scores);
    }

    /** @test */
    public function it_detects_selective_presentation_of_sms_messages()
    {
        $evidence = [
            'id' => 'ev_sms1',
            'type' => 'communication',
            'description' => 'SMS message',
            'prosecution_description' => "I'll get the stuff tonight",
            'full_content' => "Full conversation:\n[10:00] Friend: Can you pick up groceries?\n[10:05] Defendant: I'll get the stuff tonight\n[10:06] Friend: Thanks, we need milk and bread",
        ];

        $module = app(EvidenceAnalysisModule::class);
        $result = $module->recontextualizeEvidence($this->testCase->id, $evidence);

        $this->assertTrue($result['context_analysis']['selective_presentation']['detected']);
        $this->assertEquals('partial_message', $result['context_analysis']['selective_presentation']['type']);
        $this->assertNotEmpty($result['recontextualization']['defense_recontextualization']);
    }

    /** @test */
    public function it_identifies_omitted_timeline_context()
    {
        $evidence = [
            'id' => 'ev_photo1',
            'type' => 'photo',
            'description' => 'Photo of defendant at crime scene',
            'prosecution_description' => 'Defendant present at scene of crime',
            'full_content' => 'Photo metadata: timestamp 14:30, crime occurred at 16:45',
        ];

        $module = app(EvidenceAnalysisModule::class);
        $result = $module->recontextualizeEvidence($this->testCase->id, $evidence);

        $this->assertArrayHasKey('omitted_context', $result['context_analysis']);

        // Only check credibility score if recontextualization was needed
        if ($result['recontextualization']['recontextualization_needed'] ?? false) {
            $this->assertGreaterThan(60, $result['recontextualization']['credibility_score']);
        }
    }

    /** @test */
    public function api_endpoint_recontextualizes_evidence()
    {
        $evidence = [
            'id' => 'ev1',
            'type' => 'testimonial',
            'description' => 'Witness statement',
            'prosecution_description' => 'Witness said "he was angry"',
            'full_content' => 'Full statement: "He was angry at the referee during the soccer game, not at the victim"',
        ];

        $response = $this->postJson("/api/evidence/recontextualize/{$this->testCase->id}", [
            'evidence' => $evidence,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'evidence_id',
                    'context_analysis',
                    'recontextualization',
                ],
            ]);
    }

    /** @test */
    public function does_not_recontextualize_when_no_selective_presentation()
    {
        $evidence = [
            'id' => 'ev1',
            'type' => 'physical',
            'description' => 'Clear physical evidence with no context issues',
            'prosecution_description' => 'Defendant fingerprints on weapon',
            'full_content' => 'Defendant fingerprints on weapon (no additional context)',
        ];

        $module = app(EvidenceAnalysisModule::class);
        $result = $module->recontextualizeEvidence($this->testCase->id, $evidence);

        $this->assertFalse($result['recontextualization']['recontextualization_needed']);
    }
}
