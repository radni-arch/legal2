<?php

namespace Tests\Unit\Modules\Evidence;

use App\Models\LegalCase;
use App\Modules\Evidence\Services\ContextAnalyzer;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ContextAnalyzerTest extends TestCase
{
    use UsesTestDatabase;

    protected ContextAnalyzer $service;

    protected $openAIMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->service = new ContextAnalyzer($this->openAIMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_analyzes_context_and_returns_complete_structure()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'id' => 'EV-001',
            'type' => 'sms',
            'description' => 'SMS message',
            'prosecution_description' => 'Incriminating SMS',
            'full_content' => 'Full SMS conversation showing context',
            'content' => 'Full SMS conversation showing context',
        ];

        // Mock OpenAI for detectSelectivePresentation
        $this->mockOpenAISelectivePresentation([
            'selective_presentation_detected' => true,
            'type' => 'partial_message',
            'severity' => 75,
            'what_prosecutor_showed' => 'Only incriminating part',
            'what_prosecutor_omitted' => 'Exculpatory context',
            'why_omission_matters' => 'Changes meaning completely',
            'legal_basis' => 'ZKP Članak 9 - Objektivnost',
            'fair_trial_violation' => true,
        ]);

        // Mock OpenAI for identifyOmittedContext
        $this->mockOpenAIIdentifyOmissions([
            'omissions' => [
                [
                    'omitted_fact' => 'Previous message clarifying intent',
                    'where_in_full_evidence' => 'Line 5 of full SMS thread',
                    'how_it_changes_interpretation' => 'Shows legitimate purpose',
                    'prosecutor_motivation' => 'To make message appear incriminating',
                    'exculpatory_value' => 80,
                ],
            ],
        ]);

        // Mock OpenAI for generateDefenseRecontextualization
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->with(Mockery::on(function ($messages) {
                return count($messages) === 2 &&
                    $messages[0]['role'] === 'system' &&
                    strpos($messages[0]['content'], 'defense attorney') !== false;
            }), 'gpt-4o-mini', ['temperature' => 0.3, 'max_tokens' => 200])
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'Defense recontextualization restoring full context']],
                ],
            ]);

        // Act
        $result = $this->service->analyzeContext($evidence, $case);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('EV-001', $result['evidence_id']);
        $this->assertEquals('sms', $result['evidence_type']);

        // Verify structure
        $this->assertArrayHasKey('prosecution_presentation', $result);
        $this->assertArrayHasKey('full_context', $result);
        $this->assertArrayHasKey('selective_presentation', $result);
        $this->assertArrayHasKey('omitted_context', $result);
        $this->assertArrayHasKey('recontextualization_opportunities', $result);
        $this->assertArrayHasKey('analysis_timestamp', $result);

        // Verify selective presentation
        $this->assertTrue($result['selective_presentation']['detected']);
        $this->assertEquals('partial_message', $result['selective_presentation']['type']);

        // Verify omitted context
        $this->assertIsArray($result['omitted_context']);
        $this->assertArrayHasKey('omissions', $result['omitted_context']);

        // Verify recontextualization opportunities
        $this->assertIsArray($result['recontextualization_opportunities']);
    }

    /** @test */
    public function it_detects_partial_message_selective_presentation()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'id' => 'EV-002',
            'type' => 'sms',
            'prosecution_description' => '"I\'ll get the stuff tomorrow"',
            'full_content' => 'Previous: "Can you pick up groceries?" Response: "I\'ll get the stuff tomorrow"',
            'metadata' => ['timestamp' => '2024-01-15 10:00:00'],
        ];

        // Mock OpenAI to detect partial_message type
        $this->mockOpenAISelectivePresentation([
            'selective_presentation_detected' => true,
            'type' => 'partial_message',
            'severity' => 85,
            'what_prosecutor_showed' => 'Only: "I\'ll get the stuff tomorrow"',
            'what_prosecutor_omitted' => 'Previous message asking about groceries',
            'why_omission_matters' => '"Stuff" refers to groceries, not contraband',
            'legal_basis' => 'ZKP Članak 9 - Objektivnost',
            'fair_trial_violation' => true,
        ]);

        // Mock identifyOmittedContext (returns no omissions for this focused test)
        $this->mockOpenAIIdentifyOmissions(['omissions' => []]);

        // Act
        $result = $this->service->analyzeContext($evidence, $case);

        // Assert
        $selectivePresentation = $result['selective_presentation'];
        $this->assertTrue($selectivePresentation['detected']);
        $this->assertEquals('partial_message', $selectivePresentation['type']);
        $this->assertEquals(85, $selectivePresentation['severity']);
        $this->assertStringContainsString('groceries', $selectivePresentation['what_prosecutor_omitted']);
        $this->assertTrue($selectivePresentation['fair_trial_violation']);
    }

    /** @test */
    public function it_detects_cherry_picked_timeline_selective_presentation()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'id' => 'EV-003',
            'type' => 'photo',
            'prosecution_description' => 'Defendant at crime scene',
            'full_content' => 'Photo of defendant at location',
            'metadata' => [
                'timestamp' => '2024-01-15 08:00:00',
                'exif_data' => ['DateTimeOriginal' => '2024-01-15 08:00:00'],
            ],
            'prosecution_timeline' => 'Photo taken at time of crime (10:00)',
        ];

        // Mock OpenAI to detect cherry_picked_timeline type
        $this->mockOpenAISelectivePresentation([
            'selective_presentation_detected' => true,
            'type' => 'cherry_picked_timeline',
            'severity' => 90,
            'what_prosecutor_showed' => 'Defendant at scene, implied timing of crime',
            'what_prosecutor_omitted' => 'Metadata shows photo taken 2 hours before crime',
            'why_omission_matters' => 'Defendant left scene well before crime occurred',
            'legal_basis' => 'ZKP Članak 9 - Objektivnost',
            'fair_trial_violation' => true,
        ]);

        // Mock identifyOmittedContext
        $this->mockOpenAIIdentifyOmissions(['omissions' => []]);

        // Act
        $result = $this->service->analyzeContext($evidence, $case);

        // Assert
        $selectivePresentation = $result['selective_presentation'];
        $this->assertTrue($selectivePresentation['detected']);
        $this->assertEquals('cherry_picked_timeline', $selectivePresentation['type']);
        $this->assertEquals(90, $selectivePresentation['severity']);
        $this->assertStringContainsString('before', $selectivePresentation['what_prosecutor_omitted']);
    }

    /** @test */
    public function it_detects_out_of_context_media_selective_presentation()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'id' => 'EV-004',
            'type' => 'photo',
            'prosecution_description' => 'Defendant with suspicious package',
            'full_content' => 'Photo shows defendant holding pizza delivery box with logo visible',
            'metadata' => [
                'location' => 'Pizza restaurant parking lot',
                'timestamp' => '2024-01-15 19:30:00',
            ],
        ];

        // Mock OpenAI to detect out_of_context_media type
        $this->mockOpenAISelectivePresentation([
            'selective_presentation_detected' => true,
            'type' => 'out_of_context_media',
            'severity' => 80,
            'what_prosecutor_showed' => 'Cropped photo showing "suspicious package"',
            'what_prosecutor_omitted' => 'Full photo shows it\'s a pizza delivery box',
            'why_omission_matters' => 'Package is obviously legitimate food delivery',
            'legal_basis' => 'ZKP Članak 9 - Objektivnost',
            'fair_trial_violation' => true,
        ]);

        // Mock identifyOmittedContext
        $this->mockOpenAIIdentifyOmissions(['omissions' => []]);

        // Act
        $result = $this->service->analyzeContext($evidence, $case);

        // Assert
        $selectivePresentation = $result['selective_presentation'];
        $this->assertTrue($selectivePresentation['detected']);
        $this->assertEquals('out_of_context_media', $selectivePresentation['type']);
        $this->assertEquals(80, $selectivePresentation['severity']);
        $this->assertStringContainsString('pizza', strtolower($selectivePresentation['what_prosecutor_omitted']));
    }

    /** @test */
    public function it_detects_partial_witness_statement_selective_presentation()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'id' => 'EV-005',
            'type' => 'witness_statement',
            'prosecution_description' => 'Witness said: "He was very angry"',
            'full_content' => 'Complete statement: "He was very angry, but in a friendly way - you know how people get during sports games. It was all in good fun."',
            'metadata' => ['witness_name' => 'John Doe', 'date' => '2024-01-10'],
        ];

        // Mock OpenAI to detect partial_statement type
        $this->mockOpenAISelectivePresentation([
            'selective_presentation_detected' => true,
            'type' => 'partial_statement',
            'severity' => 85,
            'what_prosecutor_showed' => 'Only: "He was very angry"',
            'what_prosecutor_omitted' => 'Clarification: "in a friendly way", "sports games", "all in good fun"',
            'why_omission_matters' => 'Full context shows friendly sports banter, not hostile confrontation',
            'legal_basis' => 'ZKP Članak 9 - Objektivnost',
            'fair_trial_violation' => true,
        ]);

        // Mock identifyOmittedContext
        $this->mockOpenAIIdentifyOmissions(['omissions' => []]);

        // Act
        $result = $this->service->analyzeContext($evidence, $case);

        // Assert
        $selectivePresentation = $result['selective_presentation'];
        $this->assertTrue($selectivePresentation['detected']);
        $this->assertEquals('partial_statement', $selectivePresentation['type']);
        $this->assertEquals(85, $selectivePresentation['severity']);
        $this->assertStringContainsString('friendly', $selectivePresentation['what_prosecutor_omitted']);
    }

    /** @test */
    public function it_detects_selective_financial_records_presentation()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'id' => 'EV-006',
            'type' => 'financial_record',
            'prosecution_description' => 'Suspicious $5000 cash withdrawal',
            'full_content' => 'Bank records show: $5000 withdrawal on Jan 15, followed by deposit to brother\'s account same day with memo "loan repayment"',
            'metadata' => [
                'account_holder' => 'Defendant',
                'bank' => 'Test Bank',
                'date' => '2024-01-15',
            ],
        ];

        // Mock OpenAI to detect selective_records type
        $this->mockOpenAISelectivePresentation([
            'selective_presentation_detected' => true,
            'type' => 'selective_records',
            'severity' => 75,
            'what_prosecutor_showed' => 'Large cash withdrawal without context',
            'what_prosecutor_omitted' => 'Immediate transfer to brother with "loan repayment" memo',
            'why_omission_matters' => 'Shows legitimate loan repayment, not illicit activity',
            'legal_basis' => 'ZKP Članak 9 - Objektivnost',
            'fair_trial_violation' => true,
        ]);

        // Mock identifyOmittedContext
        $this->mockOpenAIIdentifyOmissions(['omissions' => []]);

        // Act
        $result = $this->service->analyzeContext($evidence, $case);

        // Assert
        $selectivePresentation = $result['selective_presentation'];
        $this->assertTrue($selectivePresentation['detected']);
        $this->assertEquals('selective_records', $selectivePresentation['type']);
        $this->assertEquals(75, $selectivePresentation['severity']);
        $this->assertStringContainsString('loan', strtolower($selectivePresentation['what_prosecutor_omitted']));
    }

    /** @test */
    public function it_extracts_full_context_with_metadata_and_surrounding_evidence()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $evidence = [
            'id' => 'EV-007',
            'type' => 'document',
            'full_content' => 'Complete document content',
            'content' => 'Complete document content',
            'metadata' => [
                'timestamp' => '2024-01-15 10:00:00',
                'location' => 'Test Location',
                'source' => 'Test Source',
            ],
            'timestamps' => ['2024-01-15 10:00:00', '2024-01-15 11:00:00'],
            'collected_at' => '2024-01-15 10:00:00',
        ];

        // Mock OpenAI responses (minimal for this focused test)
        $this->mockOpenAISelectivePresentation([
            'selective_presentation_detected' => false,
            'type' => null,
            'severity' => 0,
        ]);

        // Act
        $result = $this->service->analyzeContext($evidence, $case);

        // Assert
        $fullContext = $result['full_context'];

        $this->assertArrayHasKey('full_content', $fullContext);
        $this->assertEquals('Complete document content', $fullContext['full_content']);

        $this->assertArrayHasKey('metadata', $fullContext);
        $this->assertEquals('Test Location', $fullContext['metadata']['location']);

        $this->assertArrayHasKey('timestamps', $fullContext);
        $this->assertCount(2, $fullContext['timestamps']);

        $this->assertArrayHasKey('surrounding_evidence', $fullContext);
        $this->assertIsArray($fullContext['surrounding_evidence']);

        $this->assertArrayHasKey('related_documents', $fullContext);
        $this->assertIsArray($fullContext['related_documents']);
    }

    /** @test */
    public function it_identifies_omitted_context_when_omissions_exist()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'id' => 'EV-008',
            'type' => 'document',
            'prosecution_description' => 'Partial document excerpt',
            'full_content' => 'Complete document with full context showing different meaning',
        ];

        // Mock OpenAI for detectSelectivePresentation
        $this->mockOpenAISelectivePresentation([
            'selective_presentation_detected' => true,
            'type' => 'partial_excerpt',
            'severity' => 70,
            'what_prosecutor_showed' => 'Incriminating excerpt',
            'what_prosecutor_omitted' => 'Exculpatory paragraphs',
            'why_omission_matters' => 'Full context changes interpretation',
            'legal_basis' => 'ZKP Članak 9',
            'fair_trial_violation' => true,
        ]);

        // Mock OpenAI for identifyOmittedContext - MULTIPLE OMISSIONS
        $this->mockOpenAIIdentifyOmissions([
            'omissions' => [
                [
                    'omitted_fact' => 'Paragraph 3 clarifies defendant\'s intent',
                    'where_in_full_evidence' => 'Page 2, paragraph 3',
                    'how_it_changes_interpretation' => 'Shows legitimate business purpose',
                    'prosecutor_motivation' => 'To hide legitimate explanation',
                    'exculpatory_value' => 85,
                ],
                [
                    'omitted_fact' => 'Footnote references supporting case law',
                    'where_in_full_evidence' => 'Page 3, footnote 5',
                    'how_it_changes_interpretation' => 'Supports defense legal argument',
                    'prosecutor_motivation' => 'To avoid precedent favorable to defense',
                    'exculpatory_value' => 70,
                ],
            ],
        ]);

        // Mock generateDefenseRecontextualization (called for each omission)
        $this->openAIMock
            ->shouldReceive('chat')
            ->times(2)
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'Defense recontextualization text']],
                ],
            ]);

        // Act
        $result = $this->service->analyzeContext($evidence, $case);

        // Assert
        $omittedContext = $result['omitted_context'];

        $this->assertTrue($omittedContext['omissions_found']);
        $this->assertEquals(2, $omittedContext['omissions_count']);
        $this->assertCount(2, $omittedContext['omissions']);

        // Verify total exculpatory value
        $this->assertEquals(155, $omittedContext['total_exculpatory_value']);

        // Verify omission structure
        $firstOmission = $omittedContext['omissions'][0];
        $this->assertArrayHasKey('omitted_fact', $firstOmission);
        $this->assertArrayHasKey('where_in_full_evidence', $firstOmission);
        $this->assertArrayHasKey('how_it_changes_interpretation', $firstOmission);
        $this->assertArrayHasKey('exculpatory_value', $firstOmission);

        // Verify recontextualization opportunities were created
        $opportunities = $result['recontextualization_opportunities'];
        $this->assertCount(2, $opportunities);

        // Verify opportunities are sorted by exculpatory value (highest first)
        $this->assertEquals(85, $opportunities[0]['exculpatory_value']);
        $this->assertEquals(70, $opportunities[1]['exculpatory_value']);
    }

    /** @test */
    public function it_returns_no_omissions_when_prosecution_showed_full_context()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'id' => 'EV-009',
            'type' => 'document',
            'prosecution_description' => 'Complete and fair presentation',
            'full_content' => 'Complete and fair presentation',
        ];

        // Mock OpenAI for detectSelectivePresentation
        $this->mockOpenAISelectivePresentation([
            'selective_presentation_detected' => false,
            'type' => null,
            'severity' => 0,
        ]);

        // Act
        $result = $this->service->analyzeContext($evidence, $case);

        // Assert
        $omittedContext = $result['omitted_context'];

        $this->assertFalse($omittedContext['omissions_found']);
        $this->assertEmpty($omittedContext['omissions']);

        // No recontextualization opportunities when no omissions
        $this->assertEmpty($result['recontextualization_opportunities']);
    }

    /** @test */
    public function it_handles_openai_error_gracefully_in_selective_presentation_detection()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'id' => 'EV-010',
            'type' => 'document',
            'prosecution_description' => 'Test evidence',
            'full_content' => 'Test content',
        ];

        // Mock OpenAI to throw exception in detectSelectivePresentation
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->with(Mockery::on(function ($messages) {
                return count($messages) === 2 &&
                    $messages[0]['role'] === 'system' &&
                    strpos($messages[0]['content'], 'Croatian defense expert') !== false;
            }), 'gpt-4o-mini', ['temperature' => 0.2, 'response_format' => ['type' => 'json_object']])
            ->andThrow(new \Exception('OpenAI API error'));

        // Act
        $result = $this->service->analyzeContext($evidence, $case);

        // Assert
        $selectivePresentation = $result['selective_presentation'];

        // Should return conservative default (no selective presentation detected)
        $this->assertFalse($selectivePresentation['detected']);
        $this->assertNull($selectivePresentation['type']);
        $this->assertEquals(0, $selectivePresentation['severity']);
        $this->assertArrayHasKey('error', $selectivePresentation);
        $this->assertStringContainsString('unable to detect', $selectivePresentation['error']);
    }

    /** @test */
    public function it_handles_openai_error_gracefully_in_omitted_context_identification()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'id' => 'EV-011',
            'type' => 'document',
            'prosecution_description' => 'Partial content',
            'full_content' => 'Full content with additional context',
        ];

        // Mock OpenAI for detectSelectivePresentation (success)
        $this->mockOpenAISelectivePresentation([
            'selective_presentation_detected' => true,
            'type' => 'partial_excerpt',
            'severity' => 60,
            'what_prosecutor_showed' => 'Part A',
            'what_prosecutor_omitted' => 'Part B',
            'why_omission_matters' => 'Important',
            'legal_basis' => 'ZKP',
            'fair_trial_violation' => true,
        ]);

        // Mock OpenAI for identifyOmittedContext (error)
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->with(Mockery::on(function ($messages) {
                return count($messages) === 2 &&
                    $messages[0]['role'] === 'system' &&
                    strpos($messages[0]['content'], 'defense expert identifying omitted context') !== false;
            }), 'gpt-4o-mini', ['temperature' => 0.2, 'response_format' => ['type' => 'json_object']])
            ->andThrow(new \Exception('OpenAI API error'));

        // Act
        $result = $this->service->analyzeContext($evidence, $case);

        // Assert
        $omittedContext = $result['omitted_context'];

        // Should return safe fallback
        $this->assertFalse($omittedContext['omissions_found']);
        $this->assertEmpty($omittedContext['omissions']);
        $this->assertArrayHasKey('error', $omittedContext);
        $this->assertEquals('Analysis failed', $omittedContext['error']);

        // No recontextualization opportunities due to error
        $this->assertEmpty($result['recontextualization_opportunities']);
    }

    /** @test */
    public function it_extracts_prosecution_presentation_from_evidence_data()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $evidence = [
            'id' => 'EV-012',
            'type' => 'document',
            'prosecution_description' => 'Prosecution\'s view of evidence',
            'prosecution_excerpt' => 'Specific excerpt shown',
            'prosecution_emphasis' => 'Emphasis on specific aspect',
            'prosecution_timeline' => 'Timeline framing',
            'prosecution_interpretation' => 'How prosecutor interprets evidence',
            'full_content' => 'Full content',
        ];

        // Mock OpenAI (minimal for this focused test)
        $this->mockOpenAISelectivePresentation([
            'selective_presentation_detected' => false,
            'type' => null,
            'severity' => 0,
        ]);

        // Act
        $result = $this->service->analyzeContext($evidence, $case);

        // Assert
        $prosecution = $result['prosecution_presentation'];

        $this->assertEquals('Prosecution\'s view of evidence', $prosecution['description']);
        $this->assertEquals('Specific excerpt shown', $prosecution['excerpt_shown']);
        $this->assertEquals('Emphasis on specific aspect', $prosecution['emphasis']);
        $this->assertEquals('Timeline framing', $prosecution['timeline_framing']);
        $this->assertEquals('How prosecutor interprets evidence', $prosecution['interpretation']);
    }

    // Helper Methods

    protected function mockOpenAISelectivePresentation(array $analysis): void
    {
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->with(Mockery::on(function ($messages) {
                return count($messages) === 2 &&
                    $messages[0]['role'] === 'system' &&
                    strpos($messages[0]['content'], 'Croatian defense expert') !== false;
            }), 'gpt-4o-mini', ['temperature' => 0.2, 'response_format' => ['type' => 'json_object']])
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode($analysis),
                        ],
                    ],
                ],
            ]);
    }

    protected function mockOpenAIIdentifyOmissions(array $result): void
    {
        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->with(Mockery::on(function ($messages) {
                return count($messages) === 2 &&
                    $messages[0]['role'] === 'system' &&
                    strpos($messages[0]['content'], 'defense expert identifying omitted context') !== false;
            }), 'gpt-4o-mini', ['temperature' => 0.2, 'response_format' => ['type' => 'json_object']])
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode($result),
                        ],
                    ],
                ],
            ]);
    }
}
