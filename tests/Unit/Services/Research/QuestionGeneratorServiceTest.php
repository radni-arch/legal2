<?php

namespace Tests\Unit\Services\Research;

use App\Contracts\AI\ChatServiceInterface;
use App\Models\AgentRun;
use App\Services\Research\QuestionGeneratorService;
use Tests\TestCase;

class QuestionGeneratorServiceTest extends TestCase
{
    protected QuestionGeneratorService $service;

    protected ChatServiceInterface $mockChat;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock chat service
        $this->mockChat = $this->createMock(ChatServiceInterface::class);

        $this->service = new QuestionGeneratorService($this->mockChat);
    }

    // ===== Generate Tests =====

    /** @test */
    public function test_generate_questions_basic()
    {
        $this->mockChat->expects($this->once())
            ->method('chat')
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'reasoning' => 'We should search for laws related to the query',
                                'actions' => [
                                    [
                                        'tool' => 'law_vector_search',
                                        'params' => ['query' => 'labor law', 'limit' => 5],
                                        'rationale' => 'Find relevant labor laws',
                                    ],
                                ],
                            ]),
                        ],
                    ],
                ],
            ]);

        $result = $this->service->generate('labor law termination');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('reasoning', $result);
        $this->assertArrayHasKey('actions', $result);
        $this->assertCount(1, $result['actions']);
        $this->assertEquals('law_vector_search', $result['actions'][0]['tool']);
    }

    /** @test */
    public function test_generate_returns_valid_plan_structure()
    {
        $this->mockChat->expects($this->once())
            ->method('chat')
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'reasoning' => 'Test reasoning',
                                'actions' => [
                                    [
                                        'tool' => 'law_vector_search',
                                        'params' => ['query' => 'test', 'limit' => 5],
                                        'rationale' => 'Test rationale',
                                    ],
                                ],
                            ]),
                        ],
                    ],
                ],
            ]);

        $result = $this->service->generate('test query');

        $this->assertArrayHasKey('reasoning', $result);
        $this->assertArrayHasKey('actions', $result);
        $this->assertIsString($result['reasoning']);
        $this->assertIsArray($result['actions']);
    }

    /** @test */
    public function test_generate_with_context()
    {
        $context = [
            'current_iteration' => 2,
            'max_iterations' => 10,
            'insights' => ['Previous insight 1', 'Previous insight 2'],
            'topics' => ['labor law', 'termination'],
        ];

        $this->mockChat->expects($this->once())
            ->method('chat')
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'reasoning' => 'Building on previous findings',
                                'actions' => [
                                    [
                                        'tool' => 'decision_vector_search',
                                        'params' => ['query' => 'termination', 'limit' => 3],
                                        'rationale' => 'Find court decisions',
                                    ],
                                ],
                            ]),
                        ],
                    ],
                ],
            ]);

        $result = $this->service->generate('labor law', $context);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('actions', $result);
    }

    /** @test */
    public function test_generate_with_past_insights()
    {
        $context = [
            'past_insights' => [
                ['content' => 'Article 93 requires notice', 'created_at' => '2024-01-15'],
                ['content' => 'Probationary period is 6 months', 'created_at' => '2024-01-16'],
            ],
            'current_iteration' => 0,
        ];

        $this->mockChat->expects($this->once())
            ->method('chat')
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'reasoning' => 'Building on past insights',
                                'actions' => [
                                    [
                                        'tool' => 'law_vector_search',
                                        'params' => ['query' => 'notice period', 'limit' => 5],
                                        'rationale' => 'Expand on Article 93',
                                    ],
                                ],
                            ]),
                        ],
                    ],
                ],
            ]);

        $result = $this->service->generate('labor law', $context);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result['actions']);
    }

    /** @test */
    public function test_generate_limits_actions_to_max()
    {
        $this->mockChat->expects($this->once())
            ->method('chat')
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'reasoning' => 'Multiple actions',
                                'actions' => [
                                    ['tool' => 'law_vector_search', 'params' => ['query' => 'test1', 'limit' => 5], 'rationale' => 'Test 1'],
                                    ['tool' => 'law_vector_search', 'params' => ['query' => 'test2', 'limit' => 5], 'rationale' => 'Test 2'],
                                    ['tool' => 'law_vector_search', 'params' => ['query' => 'test3', 'limit' => 5], 'rationale' => 'Test 3'],
                                    ['tool' => 'law_vector_search', 'params' => ['query' => 'test4', 'limit' => 5], 'rationale' => 'Test 4'],
                                ],
                            ]),
                        ],
                    ],
                ],
            ]);

        $result = $this->service->generate('test query');

        // Should be limited to 3 actions (maxActionsPerPlan)
        $this->assertCount(3, $result['actions']);
    }

    /** @test */
    public function test_generate_validates_action_format()
    {
        // Missing 'params' in action
        $this->mockChat->expects($this->once())
            ->method('chat')
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'reasoning' => 'Test reasoning',
                                'actions' => [
                                    ['tool' => 'law_vector_search'], // Missing params
                                ],
                            ]),
                        ],
                    ],
                ],
            ]);

        $result = $this->service->generate('test query');

        // Should use fallback due to validation failure
        $this->assertArrayHasKey('actions', $result);
        $this->assertStringContainsString('fallback', $result['reasoning']);
    }

    /** @test */
    public function test_generate_handles_empty_query()
    {
        $this->mockChat->expects($this->once())
            ->method('chat')
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'reasoning' => 'Empty query research',
                                'actions' => [],
                            ]),
                        ],
                    ],
                ],
            ]);

        $result = $this->service->generate('');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('actions', $result);
    }

    /** @test */
    public function test_generate_uses_fallback_on_llm_failure()
    {
        $this->mockChat->expects($this->once())
            ->method('chat')
            ->willThrowException(new \Exception('API error'));

        $run = new AgentRun([
            'agent_name' => 'test_agent',
            'objective' => 'test objective',
            'current_iteration' => 0,
            'topics' => [],
        ]);

        $result = $this->service->generate('test query', ['run' => $run]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('actions', $result);
        $this->assertStringContainsString('fallback', $result['reasoning']);
        $this->assertNotEmpty($result['actions']);
    }

    /** @test */
    public function test_generate_basic_fallback_without_run()
    {
        $this->mockChat->expects($this->once())
            ->method('chat')
            ->willThrowException(new \Exception('API error'));

        $result = $this->service->generate('test query');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('actions', $result);
        $this->assertStringContainsString('fallback', $result['reasoning']);
        $this->assertCount(1, $result['actions']);
        $this->assertEquals('law_vector_search', $result['actions'][0]['tool']);
    }

    /** @test */
    public function test_generate_deduplicates_actions()
    {
        $this->mockChat->expects($this->once())
            ->method('chat')
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'reasoning' => 'Test reasoning',
                                'actions' => [
                                    ['tool' => 'law_vector_search', 'params' => ['query' => 'test', 'limit' => 5], 'rationale' => 'Test 1'],
                                    ['tool' => 'law_vector_search', 'params' => ['query' => 'test', 'limit' => 5], 'rationale' => 'Test 2'], // Duplicate
                                ],
                            ]),
                        ],
                    ],
                ],
            ]);

        $result = $this->service->generate('test query');

        // Should deduplicate to 1 action
        $this->assertCount(1, $result['actions']);
    }

    // ===== Refine Tests =====

    /** @test */
    public function test_refine_questions_based_on_results()
    {
        $questions = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test', 'limit' => 5]],
        ];

        $results = [
            ['success' => true, 'result' => ['laws' => [['title' => 'Labor Law']]]],
        ];

        $evaluation = [
            'insights' => ['Found Labor Law'],
            'insights_count' => 1,
            'should_stop' => false,
        ];

        $this->mockChat->expects($this->once())
            ->method('chat')
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'reasoning' => 'Building on Labor Law finding',
                                'actions' => [
                                    ['tool' => 'decision_vector_search', 'params' => ['query' => 'labor law', 'limit' => 5], 'rationale' => 'Find decisions'],
                                ],
                            ]),
                        ],
                    ],
                ],
            ]);

        $result = $this->service->refine($questions, $results, $evaluation);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('reasoning', $result);
        $this->assertArrayHasKey('actions', $result);
        $this->assertNotEmpty($result['actions']);
    }

    /** @test */
    public function test_refine_builds_on_successful_findings()
    {
        $questions = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test', 'limit' => 5]],
        ];

        $results = [
            ['success' => true, 'result' => ['laws' => [['title' => 'Test Law', 'law_number' => 'NN 93/14']]]],
        ];

        $evaluation = [
            'insights' => ['Article 93 requires notice'],
            'insights_count' => 1,
        ];

        $this->mockChat->expects($this->once())
            ->method('chat')
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'reasoning' => 'Expanding on Article 93',
                                'actions' => [
                                    ['tool' => 'law_get_article', 'params' => ['doc_id' => 'law-93', 'chunk_index' => 93], 'rationale' => 'Get full article'],
                                ],
                            ]),
                        ],
                    ],
                ],
            ]);

        $result = $this->service->refine($questions, $results, $evaluation);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result['actions']);
    }

    /** @test */
    public function test_refine_handles_empty_insights()
    {
        $questions = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test', 'limit' => 5]],
        ];

        $results = [
            ['success' => false, 'error' => 'No results found'],
        ];

        $evaluation = [
            'insights' => [],
            'insights_count' => 0,
        ];

        $this->mockChat->expects($this->once())
            ->method('chat')
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'reasoning' => 'Trying different approach',
                                'actions' => [
                                    ['tool' => 'law_keyword_search', 'params' => ['query' => 'test', 'limit' => 5], 'rationale' => 'Try keyword search'],
                                ],
                            ]),
                        ],
                    ],
                ],
            ]);

        $result = $this->service->refine($questions, $results, $evaluation);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('actions', $result);
    }

    /** @test */
    public function test_refine_returns_empty_on_failure()
    {
        $this->mockChat->expects($this->once())
            ->method('chat')
            ->willThrowException(new \Exception('API error'));

        $result = $this->service->refine([], [], ['insights' => []]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('actions', $result);
        $this->assertEmpty($result['actions']);
        $this->assertStringContainsString('failed', strtolower($result['reasoning']));
    }

    // ===== GenerateFallback Tests =====

    /** @test */
    public function test_generate_fallback_first_iteration()
    {
        $run = new AgentRun([
            'agent_name' => 'test_agent',
            'objective' => 'labor law research',
            'current_iteration' => 0,
            'topics' => [],
        ]);

        $actions = $this->service->generateFallback($run);

        $this->assertIsArray($actions);
        $this->assertCount(1, $actions);
        $this->assertEquals('law_vector_search', $actions[0]['tool']);
        $this->assertEquals($run->objective, $actions[0]['params']['query']);
        $this->assertEquals(5, $actions[0]['params']['limit']);
    }

    /** @test */
    public function test_generate_fallback_with_topics()
    {
        $run = new AgentRun([
            'agent_name' => 'test_agent',
            'objective' => 'labor law research',
            'current_iteration' => 2,
            'topics' => ['labor law', 'termination', 'notice period'],
        ]);

        $actions = $this->service->generateFallback($run);

        $this->assertIsArray($actions);
        $this->assertGreaterThanOrEqual(1, count($actions));

        // Should have law_vector_search with topics
        $hasLawSearch = false;
        foreach ($actions as $action) {
            if ($action['tool'] === 'law_vector_search') {
                $hasLawSearch = true;
                $this->assertStringContainsString('labor law', $action['params']['query']);
            }
        }
        $this->assertTrue($hasLawSearch);

        // Should have graph_query
        $hasGraphQuery = false;
        foreach ($actions as $action) {
            if ($action['tool'] === 'graph_query') {
                $hasGraphQuery = true;
            }
        }
        $this->assertTrue($hasGraphQuery);
    }

    /** @test */
    public function test_generate_fallback_without_topics()
    {
        $run = new AgentRun([
            'agent_name' => 'test_agent',
            'objective' => 'test research',
            'current_iteration' => 1,
            'topics' => [],
        ]);

        $actions = $this->service->generateFallback($run);

        $this->assertIsArray($actions);
        // Should still generate some actions even without topics
        $this->assertNotEmpty($actions);
    }

    // ===== Helper Method Tests =====

    /** @test */
    public function test_build_planning_context()
    {
        $query = 'labor law termination';
        $context = [
            'current_iteration' => 2,
            'max_iterations' => 10,
            'insights' => ['Insight 1', 'Insight 2'],
            'past_insights' => [
                ['content' => 'Past insight 1', 'created_at' => '2024-01-15'],
            ],
        ];

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildPlanningContext');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $query, $context);

        $this->assertIsString($result);
        $this->assertStringContainsString($query, $result);
        $this->assertStringContainsString('Research Objective', $result);
        $this->assertStringContainsString('Current Progress', $result);
        $this->assertStringContainsString('2/10', $result);
        $this->assertStringContainsString('Insight 1', $result);
        $this->assertStringContainsString('Past insight 1', $result);
    }

    /** @test */
    public function test_build_refinement_context()
    {
        $questions = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test', 'limit' => 5]],
        ];

        $results = [
            ['success' => true, 'result' => ['laws' => []]],
        ];

        $evaluation = [
            'insights' => ['Found something interesting'],
            'insights_count' => 1,
        ];

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildRefinementContext');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $questions, $results, $evaluation);

        $this->assertIsString($result);
        $this->assertStringContainsString('Previous Actions', $result);
        $this->assertStringContainsString('law_vector_search', $result);
        $this->assertStringContainsString('Results Summary', $result);
        $this->assertStringContainsString('Successful actions: 1', $result);
        $this->assertStringContainsString('Found something interesting', $result);
    }

    /** @test */
    public function test_deduplicate_keeps_unique_actions()
    {
        $actions = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test1', 'limit' => 5]],
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test2', 'limit' => 5]],
            ['tool' => 'decision_vector_search', 'params' => ['query' => 'test3', 'limit' => 5]],
        ];

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('deduplicateActions');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $actions);

        $this->assertCount(3, $result);
    }

    /** @test */
    public function test_deduplicate_removes_duplicate_actions()
    {
        $actions = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test', 'limit' => 5]],
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test', 'limit' => 5]], // Duplicate
            ['tool' => 'law_vector_search', 'params' => ['query' => 'test', 'limit' => 5]], // Duplicate
        ];

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('deduplicateActions');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $actions);

        $this->assertCount(1, $result);
    }

    /** @test */
    public function test_deduplicate_handles_empty_array()
    {
        $actions = [];

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('deduplicateActions');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $actions);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function test_planning_system_prompt_is_valid()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getPlanningSystemPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($this->service);

        $this->assertIsString($prompt);
        $this->assertNotEmpty($prompt);
        $this->assertStringContainsString('legal research', strtolower($prompt));
        $this->assertStringContainsString('croatian law', strtolower($prompt));
        $this->assertStringContainsString('law_vector_search', $prompt);
    }

    /** @test */
    public function test_refinement_system_prompt_is_valid()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getRefinementSystemPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($this->service);

        $this->assertIsString($prompt);
        $this->assertNotEmpty($prompt);
        $this->assertStringContainsString('legal research', strtolower($prompt));
        $this->assertStringContainsString('previous', strtolower($prompt));
    }
}
