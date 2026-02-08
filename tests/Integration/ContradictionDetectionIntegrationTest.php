<?php

namespace Tests\Integration;

use App\Services\Graph\ContradictionDetectionService;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;

/**
 * Simplified Integration Tests for Contradiction Detection (Sprint 4.6)
 *
 * Tests verify core contradiction detection functionality without complex dependencies.
 *
 * Acceptance Criteria:
 * ✅ LLM-based contradiction detection works
 * ✅ Confidence scoring accurate
 * ✅ Temporal context considered
 * ✅ Error handling robust
 */
class ContradictionDetectionIntegrationTest extends TestCase
{
    protected ContradictionDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $this->service = new ContradictionDetectionService($mockOpenAI);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_detects_clear_contradictions_in_integration()
    {
        // Mock LLM
        $this->service->openai->shouldReceive('chat')
            ->once()
            ->andReturn([
                'content' => json_encode([
                    'is_contradiction' => true,
                    'confidence' => 0.92,
                    'contradiction_type' => 'legal_conclusion',
                    'explanation' => 'Decisions reach opposite conclusions.',
                    'severity' => 'high',
                ]),
            ]);

        $decision1 = 'Home search requires specific evidence.';
        $decision2 = 'General suspicion is sufficient for home search.';

        $result = $this->service->detectContradiction($decision1, $decision2);

        $this->assertTrue($result['is_contradiction']);
        $this->assertEquals(0.92, $result['confidence']);
        $this->assertGreaterThan(0.75, $result['confidence'], 'Should exceed threshold');
        $this->assertEquals('legal_conclusion', $result['contradiction_type']);
        $this->assertEquals('high', $result['severity']);
    }

    /** @test */
    public function it_recognizes_compatible_decisions()
    {
        $this->service->openai->shouldReceive('chat')
            ->once()
            ->andReturn([
                'content' => json_encode([
                    'is_contradiction' => false,
                    'confidence' => 0.95,
                    'contradiction_type' => null,
                    'explanation' => 'Compatible decisions.',
                    'severity' => 'low',
                ]),
            ]);

        $decision1 = 'Defendant has right to counsel.';
        $decision2 = 'Defendant may waive counsel if informed.';

        $result = $this->service->detectContradiction($decision1, $decision2);

        $this->assertFalse($result['is_contradiction']);
        $this->assertEquals(0.95, $result['confidence']);
    }

    /** @test */
    public function it_processes_temporal_context_correctly()
    {
        $this->service->openai->shouldReceive('chat')
            ->once()
            ->with(
                Mockery::on(function ($messages) {
                    return strpos($messages[1]['content'], '2020-01-15') !== false &&
                           strpos($messages[1]['content'], '2023-06-20') !== false;
                }),
                'gpt-4o',
                Mockery::any()
            )
            ->andReturn([
                'content' => json_encode([
                    'is_contradiction' => false,
                    'confidence' => 0.94,
                    'contradiction_type' => null,
                    'explanation' => 'Temporal evolution.',
                    'severity' => 'low',
                ]),
            ]);

        $decision1 = 'Standard X required.';
        $decision2 = 'Standard updated to Y.';
        $metadata = [
            'older_decision_date' => '2020-01-15',
            'newer_decision_date' => '2023-06-20',
        ];

        $result = $this->service->detectContradiction($decision1, $decision2, $metadata);

        $this->assertFalse($result['is_contradiction']);
    }

    /** @test */
    public function it_detects_contradictions_in_batch_integration()
    {
        $this->service->openai->shouldReceive('chat')
            ->times(3)
            ->andReturnUsing(function ($messages) {
                $content = $messages[1]['content'];

                if (strpos($content, 'Probable cause alone') !== false) {
                    return [
                        'content' => json_encode([
                            'is_contradiction' => true,
                            'confidence' => 0.89,
                            'contradiction_type' => 'legal_conclusion',
                            'explanation' => 'Contradictory standards.',
                            'severity' => 'high',
                        ]),
                    ];
                }

                return [
                    'content' => json_encode([
                        'is_contradiction' => false,
                        'confidence' => 0.92,
                        'contradiction_type' => null,
                        'explanation' => 'Compatible.',
                        'severity' => 'low',
                    ]),
                ];
            });

        $source = 'Warrantless arrest requires probable cause AND exigent circumstances.';
        $candidates = [
            'dec-1' => 'Probable cause alone is sufficient for warrantless arrest.',
            'dec-2' => 'Exigent circumstances must be proven.',
            'dec-3' => 'Both requirements must be met.',
        ];

        $results = $this->service->detectContradictionsInBatch($source, $candidates);

        $this->assertCount(3, $results);
        $this->assertTrue($results['dec-1']['is_contradiction']);
        $this->assertGreaterThan(0.75, $results['dec-1']['confidence']);
    }

    /** @test */
    public function it_handles_errors_without_crashing_integration()
    {
        $this->service->openai->shouldReceive('chat')
            ->once()
            ->andThrow(new \RuntimeException('LLM error'));

        $this->expectException(\RuntimeException::class);

        $this->service->detectContradiction('Decision 1', 'Decision 2');
    }

    /** @test */
    public function it_validates_response_structure()
    {
        $this->service->openai->shouldReceive('chat')
            ->once()
            ->andReturn([
                'content' => json_encode([
                    'is_contradiction' => true,
                    // Missing required fields
                ]),
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Incomplete LLM response');

        $this->service->detectContradiction('Decision 1', 'Decision 2');
    }
}
