<?php

namespace Tests\Feature;

use App\Models\LegalCase;
use App\Models\User;
use App\Modules\Evidence\EvidenceAnalysisModule;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EvidenceControllerTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user for tests
        $this->actingAs(User::factory()->create());
    }

    /** @test */
    public function it_analyzes_evidence_with_valid_data()
    {
        $case = LegalCase::factory()->create();

        $evidenceData = [
            'evidence' => [
                [
                    'id' => 'evidence-1',
                    'type' => 'physical',
                    'description' => 'Weapon found at scene',
                ],
                [
                    'id' => 'evidence-2',
                    'type' => 'testimonial',
                    'description' => 'Witness statement',
                ],
            ],
            'options' => ['include_constitutional_check' => true],
        ];

        $mockModule = Mockery::mock(EvidenceAnalysisModule::class);
        $mockModule->shouldReceive('analyzeEvidence')
            ->once()
            ->with($case->id, $evidenceData['evidence'], $evidenceData['options'])
            ->andReturn([
                'admissibility' => [
                    'evidence-1' => ['admissible' => false, 'reason' => 'Improper search'],
                    'evidence-2' => ['admissible' => true, 'reason' => 'Valid testimony'],
                ],
                'constitutional_issues' => ['Fourth Amendment violation'],
            ]);

        $this->app->instance(EvidenceAnalysisModule::class, $mockModule);

        $response = $this->postJson("/api/evidence/analyze/{$case->id}", $evidenceData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'admissibility',
                    'constitutional_issues',
                ],
            ]);
    }

    /** @test */
    public function it_validates_evidence_analysis_request()
    {
        $case = LegalCase::factory()->create();

        // Missing required fields in evidence array
        $response = $this->postJson("/api/evidence/analyze/{$case->id}", [
            'evidence' => [
                [
                    'id' => 'evidence-1',
                    // Missing 'type' and 'description'
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['error', 'details']);
    }

    /** @test */
    public function it_generates_suppression_motion_successfully()
    {
        $case = LegalCase::factory()->create();

        $mockModule = Mockery::mock(EvidenceAnalysisModule::class);
        $mockModule->shouldReceive('generateSuppressionMotion')
            ->once()
            ->with($case->id, ['evidence-1', 'evidence-2'])
            ->andReturn([
                'motion_text' => 'Motion to suppress evidence based on constitutional violations...',
                'legal_basis' => ['Ustav RH čl. 35', 'ZKP čl. 291'],
                'likelihood_success' => 0.75,
            ]);

        $this->app->instance(EvidenceAnalysisModule::class, $mockModule);

        $response = $this->postJson("/api/evidence/suppress-motion/{$case->id}", [
            'evidence_ids' => ['evidence-1', 'evidence-2'],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'likelihood_success' => 0.75,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'motion_text',
                    'legal_basis',
                    'likelihood_success',
                ],
            ]);
    }

    /** @test */
    public function it_validates_suppression_motion_requires_evidence_ids()
    {
        $case = LegalCase::factory()->create();

        // Missing required evidence_ids array
        $response = $this->postJson("/api/evidence/suppress-motion/{$case->id}", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['evidence_ids']);
    }

    /** @test */
    public function it_checks_admissibility_of_specific_evidence()
    {
        $case = LegalCase::factory()->create();

        $evidenceData = [
            'evidence' => [
                'type' => 'physical',
                'description' => 'Weapon seized during warrantless search',
            ],
        ];

        $mockModule = Mockery::mock(EvidenceAnalysisModule::class);
        $mockModule->shouldReceive('checkAdmissibility')
            ->once()
            ->with($evidenceData['evidence'], $case->id)
            ->andReturn([
                'admissible' => false,
                'reasoning' => 'Fourth Amendment violation - warrantless search',
                'legal_basis' => ['Ustav RH čl. 35', 'ZKP čl. 291'],
                'confidence' => 0.92,
            ]);

        $this->app->instance(EvidenceAnalysisModule::class, $mockModule);

        $response = $this->postJson("/api/evidence/check-admissibility/{$case->id}", $evidenceData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'admissible' => false,
                    'confidence' => 0.92,
                ],
            ]);
    }

    /** @test */
    public function it_validates_admissibility_check_requires_evidence()
    {
        $case = LegalCase::factory()->create();

        $response = $this->postJson("/api/evidence/check-admissibility/{$case->id}", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['evidence']);
    }

    /** @test */
    public function it_validates_admissibility_check_evidence_structure()
    {
        $case = LegalCase::factory()->create();

        $response = $this->postJson("/api/evidence/check-admissibility/{$case->id}", [
            'evidence' => [
                'type' => 'physical',
                // Missing required 'description' field
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['evidence.description']);
    }

    /** @test */
    public function it_detects_constitutional_violations()
    {
        $case = LegalCase::factory()->create();

        $evidenceData = [
            'evidence' => [
                ['id' => 'ev-1', 'description' => 'Coerced confession'],
                ['id' => 'ev-2', 'description' => 'Illegal wiretap'],
            ],
        ];

        $mockModule = Mockery::mock(EvidenceAnalysisModule::class);
        $mockModule->shouldReceive('detectConstitutionalViolations')
            ->once()
            ->with($evidenceData['evidence'], $case->id)
            ->andReturn([
                [
                    'evidence_id' => 'ev-1',
                    'violation' => 'Right against self-incrimination violated',
                    'severity' => 95,
                    'constitutional_basis' => 'Ustav RH čl. 29',
                ],
                [
                    'evidence_id' => 'ev-2',
                    'violation' => 'Privacy violation - illegal wiretap',
                    'severity' => 90,
                    'constitutional_basis' => 'Ustav RH čl. 35',
                ],
            ]);

        $this->app->instance(EvidenceAnalysisModule::class, $mockModule);

        $response = $this->postJson("/api/evidence/constitutional-violations/{$case->id}", $evidenceData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 2,
                    'severity_max' => 95,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'violations' => [
                        '*' => [
                            'evidence_id',
                            'violation',
                            'severity',
                            'constitutional_basis',
                        ],
                    ],
                    'count',
                    'severity_max',
                ],
            ]);
    }

    /** @test */
    public function it_validates_constitutional_violations_requires_evidence()
    {
        $case = LegalCase::factory()->create();

        $response = $this->postJson("/api/evidence/constitutional-violations/{$case->id}", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['evidence']);
    }

    /** @test */
    public function it_gets_alternative_interpretations()
    {
        $case = LegalCase::factory()->create();

        $evidenceData = [
            'evidence' => [
                ['id' => 'ev-1', 'description' => 'Fingerprint on weapon'],
            ],
        ];

        $mockModule = Mockery::mock(EvidenceAnalysisModule::class);
        $mockModule->shouldReceive('getAlternativeInterpretations')
            ->once()
            ->with($evidenceData['evidence'], $case->id)
            ->andReturn([
                [
                    'interpretation' => 'Fingerprint from earlier, lawful interaction',
                    'plausibility' => 0.65,
                    'supporting_arguments' => ['Defendant worked at location', 'No proof of temporal connection'],
                ],
                [
                    'interpretation' => 'Fingerprint planted by investigating officer',
                    'plausibility' => 0.35,
                    'supporting_arguments' => ['Officer had access to weapon', 'Procedural irregularities'],
                ],
            ]);

        $this->app->instance(EvidenceAnalysisModule::class, $mockModule);

        $response = $this->postJson("/api/evidence/alternative-interpretations/{$case->id}", $evidenceData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 2,
                ],
            ])
            ->assertJsonCount(2, 'data.interpretations');
    }

    /** @test */
    public function it_validates_alternative_interpretations_requires_evidence()
    {
        $case = LegalCase::factory()->create();

        $response = $this->postJson("/api/evidence/alternative-interpretations/{$case->id}", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['evidence']);
    }

    /** @test */
    public function it_handles_module_exceptions_gracefully()
    {
        $case = LegalCase::factory()->create();

        $mockModule = Mockery::mock(EvidenceAnalysisModule::class);
        $mockModule->shouldReceive('analyzeEvidence')
            ->once()
            ->andThrow(new \Exception('AI service unavailable'));

        $this->app->instance(EvidenceAnalysisModule::class, $mockModule);

        $response = $this->postJson("/api/evidence/analyze/{$case->id}", [
            'evidence' => [
                [
                    'id' => 'test',
                    'type' => 'physical',
                    'description' => 'test evidence',
                ],
            ],
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'error' => 'AI service unavailable',
            ]);
    }

    /** @test */
    public function it_handles_empty_violations_correctly()
    {
        $case = LegalCase::factory()->create();

        $mockModule = Mockery::mock(EvidenceAnalysisModule::class);
        $mockModule->shouldReceive('detectConstitutionalViolations')
            ->once()
            ->andReturn([]); // No violations found

        $this->app->instance(EvidenceAnalysisModule::class, $mockModule);

        $response = $this->postJson("/api/evidence/constitutional-violations/{$case->id}", [
            'evidence' => [['id' => 'ev-1', 'description' => 'Valid evidence']],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 0,
                    'severity_max' => 0,
                ],
            ]);
    }
}
