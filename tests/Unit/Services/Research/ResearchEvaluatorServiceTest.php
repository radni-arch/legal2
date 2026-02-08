<?php

namespace Tests\Unit\Services\Research;

use App\Models\AgentRun;
use App\Services\OpenAIService;
use App\Services\Research\ResearchEvaluatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResearchEvaluatorServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_evaluates_iteration_results(): void
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'quality_score' => 75,
                            'coverage_assessment' => 'Good coverage of statutes',
                            'gaps_identified' => ['Need more case law'],
                            'insights' => [
                                ['title' => 'Key Finding', 'content' => 'Article 8 applies', 'citations' => ['ZKP čl. 8']],
                            ],
                            'recommendation' => 'Continue research',
                        ]),
                    ],
                ]],
                'usage' => ['total_tokens' => 300],
            ]);

        $service = new ResearchEvaluatorService($mockOpenAI);

        $run = AgentRun::factory()->create(['objective' => 'Test objective']);
        $iterationResults = [
            ['tool' => 'law_vector_search', 'success' => true, 'data' => [['content' => 'test']]],
        ];

        $evaluation = $service->evaluateIteration($run, $iterationResults);

        $this->assertEquals(75, $evaluation['quality_score']);
        $this->assertCount(1, $evaluation['insights']);
    }
}
