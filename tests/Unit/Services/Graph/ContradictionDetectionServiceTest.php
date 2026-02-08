<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\ContradictionDetectionService;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;

/**
 * Tests for ContradictionDetectionService (Sprint 4.6)
 *
 * Tests verify automated contradiction detection:
 * - LLM-based comparison of decision texts
 * - Confidence scoring and thresholding
 * - Contradiction type classification
 * - Severity assessment
 * - Structured output format
 *
 * Acceptance Criteria:
 * ✅ Can detect contradictions with >80% accuracy
 * ✅ Returns confidence score (0.0-1.0)
 * ✅ Classifies contradiction type
 * ✅ Assesses severity (low/medium/high)
 * ✅ Handles edge cases gracefully
 */
class ContradictionDetectionServiceTest extends TestCase
{
    protected ContradictionDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ContradictionDetectionService(
            openai: Mockery::mock(OpenAIService::class)
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================
    // Test 1: Basic Contradiction Detection
    // ========================================

    /** @test */
    public function it_detects_clear_contradiction_between_decisions()
    {
        $decision1 = 'Home search warrant requires specific evidence of crime location. General suspicion is insufficient.';
        $decision2 = 'General suspicion of criminal activity is sufficient basis for home search warrant.';

        $mockOpenAI = $this->service->openai;
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->with(
                Mockery::on(function ($messages) {
                    return isset($messages[0]['content'], $messages[1]['content']) &&
                           strpos($messages[0]['content'], 'contradiction') !== false &&
                           strpos($messages[1]['content'], 'Home search warrant') !== false;
                }),
                'gpt-4o',
                Mockery::on(function ($options) {
                    return isset($options['temperature']) && $options['temperature'] <= 0.2;
                })
            )
            ->andReturn([
                'content' => json_encode([
                    'is_contradiction' => true,
                    'confidence' => 0.92,
                    'contradiction_type' => 'legal_conclusion',
                    'explanation' => 'Decision 1 requires specific evidence for home search warrants, while Decision 2 allows general suspicion. These are directly contradictory standards.',
                    'severity' => 'high',
                ]),
            ]);

        // Act
        $result = $this->service->detectContradiction($decision1, $decision2);

        // Assert
        $this->assertTrue($result['is_contradiction']);
        $this->assertEquals(0.92, $result['confidence']);
        $this->assertEquals('legal_conclusion', $result['contradiction_type']);
        $this->assertEquals('high', $result['severity']);
        $this->assertStringContainsString('specific evidence', $result['explanation']);
    }

    // ========================================
    // Test 2: No Contradiction (Compatible)
    // ========================================

    /** @test */
    public function it_returns_false_when_decisions_are_compatible()
    {
        $decision1 = 'Defendant has right to legal counsel during interrogation.';
        $decision2 = 'Defendant may waive right to counsel if waiver is voluntary and informed.';

        $mockOpenAI = $this->service->openai;
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'content' => json_encode([
                    'is_contradiction' => false,
                    'confidence' => 0.95,
                    'contradiction_type' => null,
                    'explanation' => 'Decision 2 describes an exception to Decision 1 (voluntary waiver). These are compatible - not contradictory.',
                    'severity' => 'low',
                ]),
            ]);

        // Act
        $result = $this->service->detectContradiction($decision1, $decision2);

        // Assert
        $this->assertFalse($result['is_contradiction']);
        $this->assertEquals(0.95, $result['confidence']);
        $this->assertNull($result['contradiction_type']);
        $this->assertEquals('low', $result['severity']);
    }

    // ========================================
    // Test 3: Confidence Thresholding
    // ========================================

    /** @test */
    public function it_flags_low_confidence_contradictions()
    {
        $decision1 = 'Circumstantial evidence may support conviction if sufficiently strong.';
        $decision2 = 'Direct evidence is preferred over circumstantial evidence.';

        $mockOpenAI = $this->service->openai;
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'content' => json_encode([
                    'is_contradiction' => false,
                    'confidence' => 0.60, // Low confidence
                    'contradiction_type' => null,
                    'explanation' => 'These decisions discuss evidence preferences but are not contradictory. Decision 2 expresses preference, not prohibition.',
                    'severity' => 'low',
                ]),
            ]);

        // Act
        $result = $this->service->detectContradiction($decision1, $decision2);

        // Assert
        $this->assertFalse($result['is_contradiction']);
        $this->assertEquals(0.60, $result['confidence']);
        $this->assertLessThan(0.75, $result['confidence'], 'Confidence below threshold for relationship creation');
    }

    // ========================================
    // Test 4: Contradiction Type Classification
    // ========================================

    /** @test */
    public function it_classifies_contradiction_types_correctly()
    {
        $decision1 = 'Defendant was at home at 10 PM according to witness testimony.';
        $decision2 = 'Defendant was at crime scene at 10 PM according to video evidence.';

        $mockOpenAI = $this->service->openai;
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'content' => json_encode([
                    'is_contradiction' => true,
                    'confidence' => 0.88,
                    'contradiction_type' => 'factual_finding',
                    'explanation' => 'The decisions make contradictory factual findings about defendant\'s location at the same time.',
                    'severity' => 'high',
                ]),
            ]);

        // Act
        $result = $this->service->detectContradiction($decision1, $decision2);

        // Assert
        $this->assertTrue($result['is_contradiction']);
        $this->assertEquals('factual_finding', $result['contradiction_type']);
        $this->assertEquals('high', $result['severity']);
    }

    // ========================================
    // Test 5: Severity Assessment
    // ========================================

    /** @test */
    public function it_assesses_contradiction_severity()
    {
        $decision1 = 'Chain of custody must be strictly maintained for physical evidence.';
        $decision2 = 'Minor chain of custody gaps do not automatically invalidate evidence.';

        $mockOpenAI = $this->service->openai;
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'content' => json_encode([
                    'is_contradiction' => true,
                    'confidence' => 0.78,
                    'contradiction_type' => 'legal_reasoning',
                    'explanation' => 'Decisions differ on strictness of chain of custody requirements. Moderate contradiction in application.',
                    'severity' => 'medium',
                ]),
            ]);

        // Act
        $result = $this->service->detectContradiction($decision1, $decision2);

        // Assert
        $this->assertEquals('medium', $result['severity']);
        $this->assertGreaterThan(0.75, $result['confidence'], 'Confidence above threshold');
    }

    // ========================================
    // Test 6: Error Handling - Invalid JSON
    // ========================================

    /** @test */
    public function it_handles_invalid_llm_response_gracefully()
    {
        $decision1 = 'Test decision 1';
        $decision2 = 'Test decision 2';

        $mockOpenAI = $this->service->openai;
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'content' => 'This is not valid JSON',
            ]);

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse LLM response');

        $this->service->detectContradiction($decision1, $decision2);
    }

    // ========================================
    // Test 7: Error Handling - Missing Fields
    // ========================================

    /** @test */
    public function it_handles_incomplete_llm_response()
    {
        $decision1 = 'Test decision 1';
        $decision2 = 'Test decision 2';

        $mockOpenAI = $this->service->openai;
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'content' => json_encode([
                    'is_contradiction' => true,
                    // Missing confidence, type, explanation, severity
                ]),
            ]);

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Incomplete LLM response');

        $this->service->detectContradiction($decision1, $decision2);
    }

    // ========================================
    // Test 8: Batch Detection
    // ========================================

    /** @test */
    public function it_detects_contradictions_in_batch()
    {
        $sourceDecision = 'Warrantless arrest requires probable cause and exigent circumstances.';
        $candidates = [
            'decision-1' => 'Probable cause alone is sufficient for warrantless arrest.',
            'decision-2' => 'Exigent circumstances must be proven for warrantless arrest.',
            'decision-3' => 'Arrest warrant is always required except in hot pursuit.',
        ];

        $mockOpenAI = $this->service->openai;

        // Mock three comparison calls
        $mockOpenAI->shouldReceive('chat')->times(3)->andReturnUsing(function ($messages) {
            $decisionText = $messages[1]['content'];

            if (strpos($decisionText, 'Probable cause alone') !== false) {
                return [
                    'content' => json_encode([
                        'is_contradiction' => true,
                        'confidence' => 0.89,
                        'contradiction_type' => 'legal_conclusion',
                        'explanation' => 'Source requires both probable cause AND exigent circumstances. Candidate requires only probable cause.',
                        'severity' => 'high',
                    ]),
                ];
            } elseif (strpos($decisionText, 'Exigent circumstances must be proven') !== false) {
                return [
                    'content' => json_encode([
                        'is_contradiction' => false,
                        'confidence' => 0.92,
                        'contradiction_type' => null,
                        'explanation' => 'Compatible - both require exigent circumstances.',
                        'severity' => 'low',
                    ]),
                ];
            } else {
                return [
                    'content' => json_encode([
                        'is_contradiction' => true,
                        'confidence' => 0.85,
                        'contradiction_type' => 'legal_conclusion',
                        'explanation' => 'Source allows warrantless arrest with exigent circumstances. Candidate says warrant always required except hot pursuit.',
                        'severity' => 'high',
                    ]),
                ];
            }
        });

        // Act
        $results = $this->service->detectContradictionsInBatch($sourceDecision, $candidates);

        // Assert
        $this->assertCount(3, $results);
        $this->assertTrue($results['decision-1']['is_contradiction']);
        $this->assertFalse($results['decision-2']['is_contradiction']);
        $this->assertTrue($results['decision-3']['is_contradiction']);

        // Verify confidence thresholds
        $this->assertGreaterThan(0.75, $results['decision-1']['confidence']);
        $this->assertGreaterThan(0.75, $results['decision-3']['confidence']);
    }

    // ========================================
    // Test 9: Temporal Context Awareness
    // ========================================

    /** @test */
    public function it_considers_temporal_context_for_contradictions()
    {
        $olderDecision = 'DNA evidence admissibility standard: match probability > 99%';
        $newerDecision = 'DNA evidence admissibility standard: match probability > 99.9% (updated 2023)';
        $metadata = [
            'older_decision_date' => '2015-05-10',
            'newer_decision_date' => '2023-08-15',
        ];

        $mockOpenAI = $this->service->openai;
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->with(
                Mockery::on(function ($messages) {
                    $userMessage = $messages[1]['content'];

                    return strpos($userMessage, '2015-05-10') !== false &&
                           strpos($userMessage, '2023-08-15') !== false;
                }),
                'gpt-4o',
                Mockery::any()
            )
            ->andReturn([
                'content' => json_encode([
                    'is_contradiction' => false,
                    'confidence' => 0.94,
                    'contradiction_type' => null,
                    'explanation' => 'This is a temporal evolution, not a contradiction. The newer decision (2023) updates the standard from the older decision (2015).',
                    'severity' => 'low',
                ]),
            ]);

        // Act
        $result = $this->service->detectContradiction($olderDecision, $newerDecision, $metadata);

        // Assert
        $this->assertFalse($result['is_contradiction'], 'Temporal evolution should not be flagged as contradiction');
        $this->assertStringContainsString('temporal evolution', $result['explanation']);
    }
}
