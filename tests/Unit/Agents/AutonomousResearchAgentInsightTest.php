<?php

namespace Tests\Unit\Agents;

use App\Agents\AutonomousResearchAgent;
use App\Models\AgentRun;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AutonomousResearchAgentInsightTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_extracts_insight_from_law_results()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->withArgs(function ($messages, $model, $options) {
                return is_array($messages)
                    && count($messages) === 2
                    && $messages[0]['role'] === 'system'
                    && $messages[1]['role'] === 'user'
                    && $options['temperature'] === 0.3
                    && $options['max_tokens'] === 200;
            })
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'Article 93 of Croatian Labor Law (NN 93/14) requires 2-week notice period for employees with less than 2 years of service.']],
                ],
                'usage' => ['total_tokens' => 50],
            ]);

        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $agent = new AutonomousResearchAgent;
        $run = AgentRun::create([
            'agent_name' => 'test_agent',
            'objective' => 'Test',
            'context' => [],
            'tokens_used' => 0,
            'cost_spent' => 0,
        ]);

        // Set currentRun on agent
        $reflection = new \ReflectionClass($agent);
        $property = $reflection->getProperty('currentRun');
        $property->setAccessible(true);
        $property->setValue($agent, $run);

        $results = [
            'laws' => [
                [
                    'title' => 'Zakon o radu',
                    'law_number' => '93/14',
                    'content' => 'Employer must provide written notice 2 weeks before termination for employees with less than 2 years of service.',
                ],
            ],
        ];

        $insight = $this->invokeMethod(
            $agent,
            'extractInsight',
            [$results, 'Research notice period requirements']
        );

        $this->assertNotNull($insight);
        $this->assertStringContainsString('Article 93', $insight);
        $this->assertStringContainsString('93/14', $insight);
        $this->assertStringContainsString('2-week', $insight);
    }

    /** @test */
    public function it_extracts_insight_from_decision_results()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'Supreme Court in Gž-1234/2023 held that termination without cause during probation requires no notice under Article 52.']],
                ],
                'usage' => ['total_tokens' => 60],
            ]);

        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $agent = new AutonomousResearchAgent;

        $results = [
            'decisions' => [
                [
                    'title' => 'Termination During Probation',
                    'court' => 'Vrhovni sud Republike Hrvatske',
                    'case_number' => 'Gž-1234/2023',
                    'decision_date' => '2023-05-15',
                ],
            ],
        ];

        $insight = $this->invokeMethod(
            $agent,
            'extractInsight',
            [$results, 'Research probation period termination']
        );

        $this->assertNotNull($insight);
        $this->assertStringContainsString('Supreme Court', $insight);
        $this->assertStringContainsString('Gž-1234/2023', $insight);
    }

    /** @test */
    public function it_returns_null_for_irrelevant_results()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'null']],
                ],
                'usage' => ['total_tokens' => 30],
            ]);

        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $agent = new AutonomousResearchAgent;

        $results = [
            'laws' => [
                ['title' => 'Zakon o prometu', 'law_number' => '50/20', 'content' => 'Traffic regulations...'],
            ],
        ];

        $insight = $this->invokeMethod(
            $agent,
            'extractInsight',
            [$results, 'Research employment contracts']
        );

        $this->assertNull($insight);
    }

    /** @test */
    public function it_returns_null_for_empty_response()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => '']],
                ],
                'usage' => ['total_tokens' => 20],
            ]);

        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $agent = new AutonomousResearchAgent;

        $results = [
            'laws' => [
                ['title' => 'Test Law', 'content' => 'Test content'],
            ],
        ];

        $insight = $this->invokeMethod(
            $agent,
            'extractInsight',
            [$results, 'Research something']
        );

        $this->assertNull($insight);
    }

    /** @test */
    public function it_returns_null_for_empty_results()
    {
        $agent = new AutonomousResearchAgent;

        $insight = $this->invokeMethod(
            $agent,
            'extractInsight',
            [[], 'Research something']
        );

        $this->assertNull($insight);
    }

    /** @test */
    public function it_falls_back_on_extraction_error()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andThrow(new \Exception('API error'));

        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $agent = new AutonomousResearchAgent;

        $results = [
            'laws' => [
                ['title' => 'Zakon o radu', 'law_number' => '93/14'],
            ],
        ];

        $insight = $this->invokeMethod(
            $agent,
            'extractInsight',
            [$results, 'Research labor law']
        );

        // Should use fallback extraction
        $this->assertNotNull($insight);
        $this->assertStringContainsString('Found relevant law', $insight);
        $this->assertStringContainsString('Zakon o radu', $insight);
    }

    /** @test */
    public function it_formats_results_for_llm()
    {
        $agent = new AutonomousResearchAgent;

        $results = [
            'laws' => [
                [
                    'title' => 'Zakon o radu',
                    'law_number' => '93/14',
                    'content' => 'Article text here dealing with employment termination procedures and notice periods...',
                ],
            ],
            'decisions' => [
                [
                    'title' => 'Employment Termination Case',
                    'court' => 'Vrhovni sud Republike Hrvatske',
                    'case_number' => 'Gž-1234/2023',
                    'decision_date' => '2023-05-15',
                ],
            ],
        ];

        $formatted = $this->invokeMethod(
            $agent,
            'formatResultsForInsightExtraction',
            [$results]
        );

        $this->assertStringContainsString('## Laws Found:', $formatted);
        $this->assertStringContainsString('Zakon o radu', $formatted);
        $this->assertStringContainsString('93/14', $formatted);
        $this->assertStringContainsString('## Court Decisions Found:', $formatted);
        $this->assertStringContainsString('Vrhovni sud', $formatted);
        $this->assertStringContainsString('Gž-1234/2023', $formatted);
    }

    /** @test */
    public function it_formats_multiple_result_types()
    {
        $agent = new AutonomousResearchAgent;

        $results = [
            'laws' => [
                ['title' => 'Law 1', 'law_number' => '1/20', 'content' => 'Content 1'],
                ['title' => 'Law 2', 'law_number' => '2/20', 'content' => 'Content 2'],
            ],
            'decisions' => [
                ['title' => 'Decision 1', 'court' => 'Court 1', 'case_number' => 'C-1'],
            ],
            'cases' => [
                ['title' => 'Case 1', 'case_number' => 'CS-1', 'status' => 'active'],
            ],
            'rows' => [
                ['entity' => 'Entity 1'],
                ['entity' => 'Entity 2'],
            ],
        ];

        $formatted = $this->invokeMethod(
            $agent,
            'formatResultsForInsightExtraction',
            [$results]
        );

        $this->assertStringContainsString('## Laws Found:', $formatted);
        $this->assertStringContainsString('## Court Decisions Found:', $formatted);
        $this->assertStringContainsString('## Legal Cases Found:', $formatted);
        $this->assertStringContainsString('## Related Entities (Graph):', $formatted);
    }

    /** @test */
    public function it_limits_formatted_results_to_three_per_category()
    {
        $agent = new AutonomousResearchAgent;

        $results = [
            'laws' => [
                ['title' => 'Law 1', 'law_number' => '1/20', 'content' => 'Content'],
                ['title' => 'Law 2', 'law_number' => '2/20', 'content' => 'Content'],
                ['title' => 'Law 3', 'law_number' => '3/20', 'content' => 'Content'],
                ['title' => 'Law 4', 'law_number' => '4/20', 'content' => 'Content'],
                ['title' => 'Law 5', 'law_number' => '5/20', 'content' => 'Content'],
            ],
        ];

        $formatted = $this->invokeMethod(
            $agent,
            'formatResultsForInsightExtraction',
            [$results]
        );

        // Should only include first 3 laws
        $this->assertStringContainsString('Law 1', $formatted);
        $this->assertStringContainsString('Law 2', $formatted);
        $this->assertStringContainsString('Law 3', $formatted);
        $this->assertStringNotContainsString('Law 4', $formatted);
        $this->assertStringNotContainsString('Law 5', $formatted);
    }

    /** @test */
    public function it_truncates_long_formatted_results()
    {
        $agent = new AutonomousResearchAgent;
        $run = AgentRun::create([
            'agent_name' => 'test_agent',
            'objective' => 'Test',
            'context' => [],
        ]);

        // Set currentRun
        $reflection = new \ReflectionClass($agent);
        $property = $reflection->getProperty('currentRun');
        $property->setAccessible(true);
        $property->setValue($agent, $run);

        // Mock OpenAI - shouldn't be called if results are empty
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'Test insight']],
                ],
                'usage' => ['total_tokens' => 100],
            ]);

        $this->app->instance(OpenAIService::class, $mockOpenAI);

        // Create very long content
        $longContent = str_repeat('A', 15000);
        $results = [
            'laws' => [
                ['title' => 'Test Law', 'law_number' => '1/20', 'content' => $longContent],
            ],
        ];

        $insight = $this->invokeMethod(
            $agent,
            'extractInsight',
            [$results, 'Test objective']
        );

        // Should still extract insight despite truncation
        $this->assertNotNull($insight);
    }

    /** @test */
    public function it_tracks_token_usage_from_insight_extraction()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'Test insight with proper citation']],
                ],
                'usage' => ['total_tokens' => 75],
            ]);

        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $agent = new AutonomousResearchAgent;
        $run = AgentRun::create([
            'agent_name' => 'test_agent',
            'objective' => 'Test',
            'context' => [],
            'tokens_used' => 100,
            'cost_spent' => 0.01,
        ]);

        // Set currentRun
        $reflection = new \ReflectionClass($agent);
        $property = $reflection->getProperty('currentRun');
        $property->setAccessible(true);
        $property->setValue($agent, $run);

        $results = [
            'laws' => [
                ['title' => 'Test Law', 'law_number' => '1/20', 'content' => 'Test content'],
            ],
        ];

        $this->invokeMethod(
            $agent,
            'extractInsight',
            [$results, 'Test objective']
        );

        $run->refresh();
        $this->assertEquals(175, $run->tokens_used); // 100 + 75
        $this->assertGreaterThan(0.01, $run->cost_spent);
    }

    /** @test */
    public function it_uses_simple_extraction_for_laws()
    {
        $agent = new AutonomousResearchAgent;

        $results = [
            'laws' => [
                ['title' => 'Zakon o radu', 'law_number' => '93/14'],
            ],
        ];

        $insight = $this->invokeMethod(
            $agent,
            'extractSimpleInsight',
            [$results]
        );

        $this->assertStringContainsString('Found relevant law', $insight);
        $this->assertStringContainsString('Zakon o radu', $insight);
        $this->assertStringContainsString('93/14', $insight);
    }

    /** @test */
    public function it_uses_simple_extraction_for_decisions()
    {
        $agent = new AutonomousResearchAgent;

        $results = [
            'decisions' => [
                ['title' => 'Important Decision', 'court' => 'Vrhovni sud'],
            ],
        ];

        $insight = $this->invokeMethod(
            $agent,
            'extractSimpleInsight',
            [$results]
        );

        $this->assertStringContainsString('Found relevant decision', $insight);
        $this->assertStringContainsString('Important Decision', $insight);
        $this->assertStringContainsString('Vrhovni sud', $insight);
    }

    /** @test */
    public function it_uses_simple_extraction_for_graph_results()
    {
        $agent = new AutonomousResearchAgent;

        $results = [
            'rows' => [
                ['entity' => 'Entity 1'],
                ['entity' => 'Entity 2'],
                ['entity' => 'Entity 3'],
            ],
        ];

        $insight = $this->invokeMethod(
            $agent,
            'extractSimpleInsight',
            [$results]
        );

        $this->assertStringContainsString('Found 3 related entities', $insight);
    }

    /** @test */
    public function it_returns_null_from_simple_extraction_for_empty_results()
    {
        $agent = new AutonomousResearchAgent;

        $insight = $this->invokeMethod(
            $agent,
            'extractSimpleInsight',
            [[]]
        );

        $this->assertNull($insight);
    }

    /**
     * Helper to invoke protected methods
     */
    protected function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
