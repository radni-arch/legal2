<?php

namespace Tests\Unit\Modules\Evidence;

use App\Models\LegalCase;
use App\Modules\Evidence\Services\RecontextualizationService;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class RecontextualizationServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected RecontextualizationService $service;

    protected $openAIMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->service = new RecontextualizationService($this->openAIMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_generates_recontextualization_when_selective_presentation_detected()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'id' => 'EV-001',
            'type' => 'sms',
            'description' => 'SMS conversation between suspect and witness',
        ];

        $contextAnalysis = [
            'selective_presentation' => [
                'detected' => true,
                'type' => 'partial_excerpt',
                'severity' => 80,
                'what_prosecutor_showed' => 'Only the message: "I need the money now"',
                'what_prosecutor_omitted' => 'Previous messages showing it was about legitimate debt repayment',
                'why_omission_matters' => 'Changes interpretation from extortion to legal debt collection',
            ],
            'omitted_context' => [
                'omissions' => [
                    [
                        'omitted_fact' => 'Previous message: "Thanks for lending me 500 EUR last month"',
                        'where_in_full_evidence' => 'Line 15 of SMS thread',
                        'how_it_changes_interpretation' => 'Shows legitimate debt relationship',
                        'exculpatory_value' => 80,
                    ],
                ],
                'total_exculpatory_value' => 80,
            ],
            'full_context' => [
                'full_content' => 'Complete SMS thread showing lending arrangement',
                'metadata' => ['timestamp' => '2024-01-01 10:00:00'],
            ],
            'prosecution_presentation' => [
                'description' => 'SMS showing demand for money',
                'interpretation' => 'Extortion',
                'emphasis' => 'Demanding tone',
            ],
        ];

        $this->mockOpenAIRecontextualization([
            'narrative' => 'Defense recontextualization showing this was legitimate debt repayment, not extortion.',
            'key_points' => [
                'Previous messages establish lending relationship',
                'Prosecution omitted context of original loan',
                'Full thread shows consensual financial arrangement',
            ],
            'alternative_interpretation' => 'This was a request for repayment of a legitimate debt',
            'supporting_facts' => [
                'SMS from previous month shows loan agreement',
                'Metadata confirms timeline of lending and repayment',
            ],
            'croatian_legal_basis' => 'ZKP Članak 9 - Objektivnost',
            'fair_trial_argument' => 'Selective presentation violates right to fair trial',
        ]);

        // Act
        $result = $this->service->recontextualize($evidence, $contextAnalysis, $case);

        // Assert
        $this->assertTrue($result['recontextualization_needed']);
        $this->assertEquals('EV-001', $result['evidence_id']);
        $this->assertEquals('sms', $result['evidence_type']);
        $this->assertEquals('partial_excerpt', $result['selective_presentation_type']);
        $this->assertEquals(80, $result['selective_presentation_severity']);

        // Verify prosecution narrative extraction
        $this->assertArrayHasKey('prosecution_narrative', $result);
        $this->assertEquals('SMS showing demand for money', $result['prosecution_narrative']['summary']);

        // Verify defense recontextualization
        $this->assertArrayHasKey('defense_recontextualization', $result);
        $this->assertStringContainsString('legitimate debt', $result['defense_recontextualization']['narrative']);
        $this->assertIsArray($result['defense_recontextualization']['key_points']);
        $this->assertCount(3, $result['defense_recontextualization']['key_points']);

        // Verify key differences highlighted
        $this->assertArrayHasKey('key_differences', $result);
        $this->assertNotEmpty($result['key_differences']);

        // Verify supporting evidence identified
        $this->assertArrayHasKey('supporting_evidence', $result);
        $this->assertNotEmpty($result['supporting_evidence']);

        // Verify credibility score
        $this->assertArrayHasKey('credibility_score', $result);
        $this->assertIsInt($result['credibility_score']);
        $this->assertGreaterThanOrEqual(50, $result['credibility_score']);
        $this->assertLessThanOrEqual(100, $result['credibility_score']);

        // Verify credibility level
        $this->assertArrayHasKey('credibility_level', $result);
        $this->assertContains($result['credibility_level'], ['very_low', 'low', 'moderate', 'high', 'very_high']);

        // Verify recommended use
        $this->assertArrayHasKey('recommended_use', $result);
        $this->assertIsString($result['recommended_use']);

        // Verify timestamp
        $this->assertArrayHasKey('generated_at', $result);
    }

    /** @test */
    public function it_returns_no_recontextualization_needed_when_not_detected()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'id' => 'EV-002',
            'type' => 'document',
            'description' => 'Complete contract document',
        ];

        $contextAnalysis = [
            'selective_presentation' => [
                'detected' => false,
            ],
        ];

        // Act
        $result = $this->service->recontextualize($evidence, $contextAnalysis, $case);

        // Assert
        $this->assertFalse($result['recontextualization_needed']);
        $this->assertEquals('No selective presentation detected by prosecution', $result['reason']);
        $this->assertEquals('EV-002', $result['evidence_id']);
        $this->assertArrayNotHasKey('defense_recontextualization', $result);
        $this->assertArrayNotHasKey('credibility_score', $result);
    }

    /** @test */
    public function it_calculates_credibility_score_base_50_with_no_factors()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'id' => 'EV-003',
            'type' => 'document',
        ];

        // Minimal context analysis - no significant factors
        $contextAnalysis = [
            'selective_presentation' => [
                'detected' => true,
                'type' => 'minimal',
                'what_prosecutor_showed' => 'Some content',
                'what_prosecutor_omitted' => 'Minor details',
                // Missing 'why_omission_matters' - no +20
            ],
            'omitted_context' => [
                // Empty omissions array - no +15
                'omissions' => [],
            ],
            'full_context' => [
                'full_content' => 'Some content',
                // No metadata or timestamps - no +15
            ],
            'prosecution_presentation' => [],
        ];

        $this->mockOpenAIRecontextualization([
            'narrative' => 'Basic recontextualization',
            'key_points' => ['Point 1'],
            'alternative_interpretation' => 'Alternative view',
            'supporting_facts' => [],
            'croatian_legal_basis' => 'ZKP Članak 9',
            'fair_trial_argument' => 'Fair trial argument',
        ]);

        // Act
        $result = $this->service->recontextualize($evidence, $contextAnalysis, $case);

        // Assert
        // Base score only: 50 (no factors applied)
        $this->assertEquals(50, $result['credibility_score']);
        $this->assertEquals('low', $result['credibility_level']); // 50 is in 'low' range (40-54)
    }

    /** @test */
    public function it_calculates_credibility_score_with_significant_omission_factor()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = ['id' => 'EV-004', 'type' => 'document'];

        $contextAnalysis = [
            'selective_presentation' => [
                'detected' => true,
                'type' => 'significant_omission',
                'what_prosecutor_showed' => 'Incriminating excerpt',
                'what_prosecutor_omitted' => 'Exculpatory context',
                'why_omission_matters' => 'Completely changes the meaning', // +20
            ],
            'omitted_context' => [
                'omissions' => [], // No +15
            ],
            'full_context' => [
                'full_content' => 'Content',
                // No metadata - no +15
            ],
            'prosecution_presentation' => [],
        ];

        $this->mockOpenAIRecontextualization([
            'narrative' => 'Recontextualization',
            'key_points' => ['Point'],
            'alternative_interpretation' => 'Alternative',
            'supporting_facts' => [],
            'croatian_legal_basis' => 'ZKP',
            'fair_trial_argument' => 'Fair trial',
        ]);

        // Act
        $result = $this->service->recontextualize($evidence, $contextAnalysis, $case);

        // Assert
        // Base 50 + significant omission 20 = 70
        $this->assertEquals(70, $result['credibility_score']);
        $this->assertEquals('high', $result['credibility_level']);
    }

    /** @test */
    public function it_calculates_credibility_score_with_omitted_context_factor()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = ['id' => 'EV-005', 'type' => 'document'];

        $contextAnalysis = [
            'selective_presentation' => [
                'detected' => true,
                'type' => 'omission',
                'what_prosecutor_showed' => 'Partial evidence',
                'what_prosecutor_omitted' => 'Supporting context',
                'why_omission_matters' => 'Matters', // +20
            ],
            'omitted_context' => [
                'omissions' => [ // +15 (has omissions)
                    [
                        'omitted_fact' => 'Fact 1',
                        'where_in_full_evidence' => 'Location',
                        'how_it_changes_interpretation' => 'Changes view',
                        'exculpatory_value' => 50,
                    ],
                ],
                'total_exculpatory_value' => 50,
            ],
            'full_context' => [
                'full_content' => 'Content',
                // No metadata - no +15
            ],
            'prosecution_presentation' => [],
        ];

        $this->mockOpenAIRecontextualization([
            'narrative' => 'Recontextualization',
            'key_points' => ['Point'],
            'alternative_interpretation' => 'Alternative',
            'supporting_facts' => [],
            'croatian_legal_basis' => 'ZKP',
            'fair_trial_argument' => 'Fair trial',
        ]);

        // Act
        $result = $this->service->recontextualize($evidence, $contextAnalysis, $case);

        // Assert
        // Base 50 + omission matters 20 + omissions array 15 = 85
        $this->assertEquals(85, $result['credibility_score']);
        $this->assertEquals('very_high', $result['credibility_level']);
    }

    /** @test */
    public function it_calculates_maximum_credibility_score_with_all_factors()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = ['id' => 'EV-006', 'type' => 'document'];

        $contextAnalysis = [
            'selective_presentation' => [
                'detected' => true,
                'type' => 'major_omission',
                'what_prosecutor_showed' => 'Incriminating excerpt',
                'what_prosecutor_omitted' => 'Extensive exculpatory context',
                'why_omission_matters' => 'Fundamentally changes interpretation', // +20
            ],
            'omitted_context' => [
                'omissions' => [ // +15
                    [
                        'omitted_fact' => 'Fact 1',
                        'where_in_full_evidence' => 'Location 1',
                        'how_it_changes_interpretation' => 'Major change',
                        'exculpatory_value' => 80,
                    ],
                    [
                        'omitted_fact' => 'Fact 2',
                        'where_in_full_evidence' => 'Location 2',
                        'how_it_changes_interpretation' => 'Supports defense',
                        'exculpatory_value' => 75,
                    ],
                ],
                'total_exculpatory_value' => 155, // +5 (>= 150)
            ],
            'full_context' => [
                'full_content' => 'Complete evidence with full context',
                'metadata' => [ // +15 (objective support)
                    'timestamp' => '2024-01-01 10:00:00',
                    'location' => 'GPS coordinates',
                    'device_id' => 'ABC123',
                ],
                'timestamps' => ['2024-01-01', '2024-01-02'],
            ],
            'prosecution_presentation' => [],
        ];

        $this->mockOpenAIRecontextualization([
            'narrative' => 'Strong defense recontextualization',
            'key_points' => ['Point 1', 'Point 2', 'Point 3'],
            'alternative_interpretation' => 'Defense interpretation',
            'supporting_facts' => ['Fact 1', 'Fact 2'],
            'croatian_legal_basis' => 'ZKP Članak 9',
            'fair_trial_argument' => 'Strong fair trial argument',
        ]);

        // Act
        $result = $this->service->recontextualize($evidence, $contextAnalysis, $case);

        // Assert
        // Base 50 + significant omission 20 + omissions 15 + objective support 15 + high exculpatory 5 + multiple evidence 5 = 110
        // Capped at 100
        $this->assertEquals(100, $result['credibility_score']);
        $this->assertEquals('very_high', $result['credibility_level']);
        $this->assertStringContainsString('Strong defense argument', $result['recommended_use']);
    }

    /** @test */
    public function it_calculates_credibility_with_objective_support_from_metadata()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = ['id' => 'EV-007', 'type' => 'document'];

        $contextAnalysis = [
            'selective_presentation' => [
                'detected' => true,
                'what_prosecutor_showed' => 'Content',
                'what_prosecutor_omitted' => 'Context',
                'why_omission_matters' => 'Matters', // +20
            ],
            'omitted_context' => [
                'omissions' => [], // No +15
            ],
            'full_context' => [
                'full_content' => 'Content',
                'metadata' => ['timestamp' => '2024-01-01'], // +15 (objective support)
            ],
            'prosecution_presentation' => [],
        ];

        $this->mockOpenAIRecontextualization([
            'narrative' => 'Recontextualization',
            'key_points' => ['Point'],
            'alternative_interpretation' => 'Alternative',
            'supporting_facts' => [],
            'croatian_legal_basis' => 'ZKP',
            'fair_trial_argument' => 'Fair trial',
        ]);

        // Act
        $result = $this->service->recontextualize($evidence, $contextAnalysis, $case);

        // Assert
        // Base 50 + omission matters 20 + objective support (metadata) 15 = 85
        $this->assertEquals(85, $result['credibility_score']);
    }

    /** @test */
    public function it_falls_back_to_template_when_openai_fails()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'id' => 'EV-008',
            'type' => 'document',
            'description' => 'Test evidence',
        ];

        $contextAnalysis = [
            'selective_presentation' => [
                'detected' => true,
                'type' => 'omission',
                'what_prosecutor_showed' => 'Incriminating part',
                'what_prosecutor_omitted' => 'Exculpatory context',
                'why_omission_matters' => 'Changes interpretation completely',
            ],
            'omitted_context' => [
                'omissions' => [],
            ],
            'full_context' => [
                'full_content' => 'Full content',
            ],
            'prosecution_presentation' => [],
        ];

        // Mock OpenAI to throw exception
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andThrow(new \Exception('OpenAI API error'));

        // Act
        $result = $this->service->recontextualize($evidence, $contextAnalysis, $case);

        // Assert
        $this->assertTrue($result['recontextualization_needed']);
        $this->assertArrayHasKey('defense_recontextualization', $result);

        // Verify fallback template was used (Croatian text)
        $narrative = $result['defense_recontextualization']['narrative'];
        $this->assertStringContainsString('Tužiteljstvo', $narrative);
        $this->assertStringContainsString('ZKP', $narrative);
        $this->assertStringContainsString('Incriminating part', $narrative);
        $this->assertStringContainsString('Exculpatory context', $narrative);

        // Verify template structure
        $this->assertIsArray($result['defense_recontextualization']['key_points']);
        $this->assertArrayHasKey('alternative_interpretation', $result['defense_recontextualization']);
        $this->assertArrayHasKey('supporting_facts', $result['defense_recontextualization']);
    }

    /** @test */
    public function it_refuses_to_fabricate_when_openai_returns_invalid_json()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'id' => 'EV-009',
            'type' => 'document',
        ];

        $contextAnalysis = [
            'selective_presentation' => [
                'detected' => true,
                'what_prosecutor_showed' => 'Part A',
                'what_prosecutor_omitted' => 'Part B',
                'why_omission_matters' => 'Important',
            ],
            'omitted_context' => [
                'omissions' => [],
            ],
            'full_context' => [
                'full_content' => 'Content',
            ],
            'prosecution_presentation' => [],
        ];

        // Mock OpenAI to return invalid JSON
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'INVALID JSON {{{',
                        ],
                    ],
                ],
            ]);

        // Act
        $result = $this->service->recontextualize($evidence, $contextAnalysis, $case);

        // Assert
        // Should fall back to template (not fabricate)
        $this->assertTrue($result['recontextualization_needed']);
        $this->assertArrayHasKey('defense_recontextualization', $result);

        // Verify template structure (proves it didn't fabricate)
        $narrative = $result['defense_recontextualization']['narrative'];
        $this->assertStringContainsString('Part A', $narrative);
        $this->assertStringContainsString('Part B', $narrative);
    }

    /** @test */
    public function it_identifies_supporting_evidence_types()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = ['id' => 'EV-010', 'type' => 'document'];

        $contextAnalysis = [
            'selective_presentation' => [
                'detected' => true,
                'what_prosecutor_showed' => 'Content',
                'what_prosecutor_omitted' => 'Context',
                'why_omission_matters' => 'Matters',
            ],
            'omitted_context' => [
                'omissions' => [
                    [
                        'omitted_fact' => 'Important fact',
                        'where_in_full_evidence' => 'Page 5',
                        'how_it_changes_interpretation' => 'Major change',
                        'exculpatory_value' => 80,
                    ],
                ],
            ],
            'full_context' => [
                'full_content' => 'Complete content',
                'metadata' => ['timestamp' => '2024-01-01'],
                'surrounding_evidence' => ['EV-001', 'EV-002'],
                'related_documents' => ['DOC-001'],
            ],
            'prosecution_presentation' => [],
        ];

        $this->mockOpenAIRecontextualization([
            'narrative' => 'Recontextualization',
            'key_points' => ['Point'],
            'alternative_interpretation' => 'Alternative',
            'supporting_facts' => [],
            'croatian_legal_basis' => 'ZKP',
            'fair_trial_argument' => 'Fair trial',
        ]);

        // Act
        $result = $this->service->recontextualize($evidence, $contextAnalysis, $case);

        // Assert
        $supportingEvidence = $result['supporting_evidence'];
        $this->assertNotEmpty($supportingEvidence);

        // Verify different types of supporting evidence are identified
        $types = array_column($supportingEvidence, 'type');
        $this->assertContains('full_evidence', $types);
        $this->assertContains('metadata', $types);
        $this->assertContains('surrounding_evidence', $types);
        $this->assertContains('related_documents', $types);
        $this->assertContains('omitted_context', $types);

        // Verify credibility gets bonus for multiple evidence types (>= 3)
        $this->assertGreaterThanOrEqual(90, $result['credibility_score']); // Should have multiple bonuses
    }

    // Helper Methods

    protected function mockOpenAIRecontextualization(array $recontextualization): void
    {
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode($recontextualization),
                        ],
                    ],
                ],
            ]);
    }
}
