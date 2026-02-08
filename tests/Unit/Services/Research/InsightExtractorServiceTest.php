<?php

namespace Tests\Unit\Services\Research;

use App\Services\OpenAIService;
use App\Services\Research\InsightExtractorService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InsightExtractorServiceTest extends TestCase
{
    #[Test]
    public function it_extracts_insights_from_search_results(): void
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'insights' => [
                                [
                                    'title' => 'Proportionality Requirement',
                                    'summary' => 'Home searches must be proportionate to suspected crime',
                                    'legal_basis' => 'ZKP čl. 240, st. 2',
                                    'relevance_score' => 95,
                                    'related_concepts' => ['necessity', 'subsidiarity'],
                                ],
                            ],
                        ]),
                    ],
                ]],
                'usage' => ['total_tokens' => 400],
            ]);

        $service = new InsightExtractorService($mockOpenAI);

        $searchResults = [
            ['content' => 'Article 240 of ZKP requires proportionality...'],
        ];

        $insights = $service->extractInsights($searchResults, 'proportionality in home searches');

        $this->assertCount(1, $insights);
        $this->assertEquals('Proportionality Requirement', $insights[0]['title']);
        $this->assertEquals(95, $insights[0]['relevance_score']);
        $this->assertEquals('ZKP čl. 240, st. 2', $insights[0]['legal_basis']);
        $this->assertIsArray($insights[0]['related_concepts']);
        $this->assertContains('necessity', $insights[0]['related_concepts']);
    }

    #[Test]
    public function it_handles_empty_search_results(): void
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldNotReceive('chat');

        $service = new InsightExtractorService($mockOpenAI);

        $insights = $service->extractInsights([], 'test objective');

        $this->assertIsArray($insights);
        $this->assertEmpty($insights);
    }

    #[Test]
    public function it_handles_llm_errors_gracefully(): void
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andThrow(new \Exception('OpenAI API error'));

        $service = new InsightExtractorService($mockOpenAI);

        $searchResults = [
            ['content' => 'Test content'],
        ];

        $insights = $service->extractInsights($searchResults, 'test objective');

        $this->assertIsArray($insights);
        $this->assertEmpty($insights);
    }

    #[Test]
    public function it_validates_insight_structure(): void
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'insights' => [
                                [
                                    'title' => 'Valid Insight',
                                    'summary' => 'Summary text',
                                    'legal_basis' => 'ZKP čl. 100',
                                    'relevance_score' => 85,
                                    'related_concepts' => ['concept1'],
                                ],
                                [
                                    // Missing required fields - should be filtered out
                                    'title' => 'Invalid Insight',
                                ],
                            ],
                        ]),
                    ],
                ]],
                'usage' => ['total_tokens' => 300],
            ]);

        $service = new InsightExtractorService($mockOpenAI);

        $searchResults = [
            ['content' => 'Test content'],
        ];

        $insights = $service->extractInsights($searchResults, 'test objective');

        // Should only return the valid insight
        $this->assertCount(1, $insights);
        $this->assertEquals('Valid Insight', $insights[0]['title']);
    }
}
