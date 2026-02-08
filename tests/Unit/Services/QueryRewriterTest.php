<?php

namespace Tests\Unit\Services;

use App\Services\OpenAIService;
use App\Services\QueryRewriter;
use Mockery;
use Tests\TestCase;

class QueryRewriterTest extends TestCase
{
    /** @test */
    public function it_rewrites_query_into_three_variants()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'specific' => 'nezakonit otkaz članak 93 Zakon o radu',
                        'broad' => 'otkaz prestanak ugovora radni odnos',
                        'structured' => 'raskid ugovora o radu otkazni rok',
                    ])]],
                ],
            ]);

        $rewriter = new QueryRewriter($mockOpenAI);

        $variants = $rewriter->rewrite('Can employer fire me without notice?');

        $this->assertCount(3, $variants);
        $this->assertStringContainsString('nezakonit otkaz', $variants[0]);
        $this->assertStringContainsString('prestanak', $variants[1]);
        $this->assertStringContainsString('raskid', $variants[2]);
    }

    /** @test */
    public function it_falls_back_to_original_query_on_error()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andThrow(new \Exception('API error'));

        $rewriter = new QueryRewriter($mockOpenAI);

        $variants = $rewriter->rewrite('test query');

        $this->assertEquals(['test query', 'test query', 'test query'], $variants);
    }

    /** @test */
    public function it_returns_best_variant()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'specific' => 'specific variant',
                        'broad' => 'broad variant',
                        'structured' => 'structured variant',
                    ])]],
                ],
            ]);

        $rewriter = new QueryRewriter($mockOpenAI);

        $best = $rewriter->rewriteBest('test');

        $this->assertEquals('specific variant', $best);
    }

    /** @test */
    public function it_analyzes_query_intent()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'domain' => 'employment',
                        'law_references' => ['Zakon o radu'],
                        'query_type' => 'advisory',
                        'entities' => ['employer', 'employee'],
                    ])]],
                ],
            ]);

        $rewriter = new QueryRewriter($mockOpenAI);

        $intent = $rewriter->analyzeIntent('Can my boss fire me?');

        $this->assertEquals('employment', $intent['domain']);
        $this->assertContains('Zakon o radu', $intent['law_references']);
    }

    /** @test */
    public function it_handles_empty_llm_response()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => null]],
                ],
            ]);

        $rewriter = new QueryRewriter($mockOpenAI);

        $variants = $rewriter->rewrite('test query');

        $this->assertEquals(['test query', 'test query', 'test query'], $variants);
    }

    /** @test */
    public function it_handles_malformed_json_response()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'not valid json']],
                ],
            ]);

        $rewriter = new QueryRewriter($mockOpenAI);

        $variants = $rewriter->rewrite('test query');

        $this->assertEquals(['test query', 'test query', 'test query'], $variants);
    }

    /** @test */
    public function it_falls_back_on_intent_analysis_error()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andThrow(new \Exception('API error'));

        $rewriter = new QueryRewriter($mockOpenAI);

        $intent = $rewriter->analyzeIntent('test query');

        $this->assertEquals('unknown', $intent['domain']);
        $this->assertEquals([], $intent['law_references']);
        $this->assertEquals('unknown', $intent['query_type']);
        $this->assertEquals([], $intent['entities']);
    }

    /** @test */
    public function it_uses_croatian_language_by_default()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->withArgs(function ($messages, $model, $options) {
                // Check that system prompt mentions Croatian
                return str_contains($messages[0]['content'], 'Croatian');
            })
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'specific' => 'variant 1',
                        'broad' => 'variant 2',
                        'structured' => 'variant 3',
                    ])]],
                ],
            ]);

        $rewriter = new QueryRewriter($mockOpenAI);

        $variants = $rewriter->rewrite('test query', 'hr');

        $this->assertCount(3, $variants);
    }

    /** @test */
    public function it_supports_english_language()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->withArgs(function ($messages, $model, $options) {
                // Check that system prompt mentions English
                return str_contains($messages[0]['content'], 'English');
            })
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'specific' => 'variant 1',
                        'broad' => 'variant 2',
                        'structured' => 'variant 3',
                    ])]],
                ],
            ]);

        $rewriter = new QueryRewriter($mockOpenAI);

        $variants = $rewriter->rewrite('test query', 'en');

        $this->assertCount(3, $variants);
    }
}
