<?php

namespace Tests\Unit\Agents;

use App\Agents\DecisionDiscoveryAgent;
use App\Models\AiReasoningTrace;
use App\Services\Explainability\ReasoningTraceService;
use App\Services\Odluke\OdlukeClient;
use App\Services\Odluke\OdlukeIngestService;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Sprint 2.5: DecisionDiscoveryAgent Tracing Tests
 *
 * Tests for reasoning trace integration into DecisionDiscoveryAgent.
 *
 * Acceptance Criteria:
 * - Discovery creates traces for topic generation
 * - Each scored decision has trace explaining score
 * - Confidence scores stored in traces
 * - Can query "why was this decision scored 85?"
 * - Integration test validates traces
 */
class DecisionDiscoveryAgentTracingTest extends TestCase
{
    use UsesTestDatabase;

    protected DecisionDiscoveryAgent $agent;

    protected $openAIMock;

    protected $clientMock;

    protected $ingestMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock dependencies
        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->clientMock = Mockery::mock(OdlukeClient::class);
        $this->ingestMock = Mockery::mock(OdlukeIngestService::class);

        // Mock Log facade
        Log::shouldReceive('info')->byDefault();
        Log::shouldReceive('debug')->byDefault();
        Log::shouldReceive('warning')->byDefault();
        Log::shouldReceive('error')->byDefault();

        // Create agent
        $this->agent = new DecisionDiscoveryAgent(
            $this->clientMock,
            $this->ingestMock,
            $this->openAIMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================================================
    // TOPIC GENERATION TRACING TESTS
    // ========================================================================

    /**
     * Test that topic generation creates a reasoning trace
     */
    public function test_topic_generation_creates_reasoning_trace()
    {
        // Mock LLM response for topic generation
        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'topics' => ['Radno pravo', 'Ugovorno pravo', 'Potrošačka zaštita'],
                        ]),
                    ],
                ]],
            ]);

        // Call the agent's topic generation
        $agent = $this->createExtendedAgent();
        $topics = $agent->exposeGenerateResearchTopics();

        // Assert trace was created
        $trace = AiReasoningTrace::where('agent_type', 'decision_discovery_agent')
            ->where('step_type', 'topic_generation')
            ->first();

        $this->assertNotNull($trace, 'Topic generation should create a reasoning trace');
        $this->assertEquals('generate_research_topics', $trace->operation);
        $this->assertNotNull($trace->output_data);
        $this->assertArrayHasKey('topics', $trace->output_data);
        $this->assertCount(3, $trace->output_data['topics']);
    }

    /**
     * Test that topic generation trace includes confidence score
     */
    public function test_topic_generation_trace_includes_confidence_score()
    {
        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'topics' => ['Radno pravo', 'Ugovorno pravo'],
                        ]),
                    ],
                ]],
            ]);

        $agent = $this->createExtendedAgent();
        $agent->exposeGenerateResearchTopics();

        $trace = AiReasoningTrace::where('step_type', 'topic_generation')->first();

        $this->assertNotNull($trace->confidence);
        $this->assertGreaterThanOrEqual(0, $trace->confidence);
        $this->assertLessThanOrEqual(1, $trace->confidence);
    }

    /**
     * Test that topic generation trace includes reasoning explanation
     */
    public function test_topic_generation_trace_includes_reasoning()
    {
        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'topics' => ['Radno pravo'],
                        ]),
                    ],
                ]],
            ]);

        $agent = $this->createExtendedAgent();
        $agent->exposeGenerateResearchTopics();

        $trace = AiReasoningTrace::where('step_type', 'topic_generation')->first();

        $this->assertNotNull($trace->reasoning);
        $this->assertStringContainsString('Generated', $trace->reasoning);
    }

    // ========================================================================
    // DECISION SCORING TRACING TESTS
    // ========================================================================

    /**
     * Test that each scored decision creates a reasoning trace
     */
    public function test_each_scored_decision_creates_trace()
    {
        $metadata = [
            'dec-1' => [
                'title' => 'Odluka o radnom pravu',
                'court' => 'Vrhovni sud',
                'date' => '2025-01-15',
                'type' => 'Presuda',
                'description' => 'Test decision description',
            ],
            'dec-2' => [
                'title' => 'Odluka o ugovornom pravu',
                'court' => 'Županijski sud',
                'date' => '2025-01-10',
                'type' => 'Rješenje',
                'description' => 'Another test decision',
            ],
        ];

        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'scores' => [
                                ['id' => 'dec-1', 'score' => 85, 'reasoning' => 'Highly relevant Supreme Court ruling'],
                                ['id' => 'dec-2', 'score' => 72, 'reasoning' => 'Relevant regional court decision'],
                            ],
                        ]),
                    ],
                ]],
            ]);

        $agent = $this->createExtendedAgent();
        $scored = $agent->exposeScoreDecisions($metadata, 'Radno pravo');

        // Assert traces were created for each decision
        $tracesCount = AiReasoningTrace::where('step_type', 'decision_scoring')->count();
        $this->assertGreaterThanOrEqual(2, $tracesCount, 'Should create trace for each scored decision');

        // Check first decision trace (query database directly for JSONB)
        $trace1 = AiReasoningTrace::where('step_type', 'decision_scoring')
            ->where('output_data->decision_id', 'dec-1')
            ->first();
        $this->assertNotNull($trace1);
        $this->assertEquals(85, $trace1->output_data['score']);
        $this->assertStringContainsString('Supreme Court', $trace1->reasoning);
        $this->assertNotNull($trace1->confidence);
    }

    /**
     * Test that decision scoring trace stores confidence score
     */
    public function test_decision_scoring_stores_confidence_score()
    {
        $metadata = [
            'dec-1' => [
                'title' => 'Test decision',
                'court' => 'Vrhovni sud',
                'date' => '2025-01-15',
                'type' => 'Presuda',
                'description' => 'Test',
            ],
        ];

        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'scores' => [
                                ['id' => 'dec-1', 'score' => 90, 'reasoning' => 'Excellent ruling'],
                            ],
                        ]),
                    ],
                ]],
            ]);

        $agent = $this->createExtendedAgent();
        $agent->exposeScoreDecisions($metadata, 'Radno pravo');

        $trace = AiReasoningTrace::where('step_type', 'decision_scoring')->first();

        $this->assertNotNull($trace->confidence);
        $this->assertGreaterThan(0.8, $trace->confidence, 'High score (90) should have high confidence');
    }

    /**
     * Test that low scoring decisions have lower confidence
     */
    public function test_low_score_decisions_have_lower_confidence()
    {
        $metadata = [
            'dec-low' => [
                'title' => 'Marginally relevant decision',
                'court' => 'Općinski sud',
                'date' => '2020-01-01',
                'type' => 'Rješenje',
                'description' => 'Old decision',
            ],
        ];

        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'scores' => [
                                ['id' => 'dec-low', 'score' => 45, 'reasoning' => 'Marginally relevant, old decision'],
                            ],
                        ]),
                    ],
                ]],
            ]);

        $agent = $this->createExtendedAgent();
        $agent->exposeScoreDecisions($metadata, 'Radno pravo');

        $trace = AiReasoningTrace::where('step_type', 'decision_scoring')->first();

        $this->assertLessThan(0.6, $trace->confidence, 'Low score (45) should have lower confidence');
    }

    // ========================================================================
    // TRACE QUERYING TESTS ("Why was this decision scored 85?")
    // ========================================================================

    /**
     * Test that we can query trace by decision ID to explain score
     */
    public function test_can_query_trace_by_decision_id()
    {
        $metadata = [
            'dec-123' => [
                'title' => 'Important labor law ruling',
                'court' => 'Vrhovni sud',
                'date' => '2025-01-15',
                'type' => 'Presuda',
                'description' => 'Landmark decision on employment rights',
            ],
        ];

        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'scores' => [
                                [
                                    'id' => 'dec-123',
                                    'score' => 85,
                                    'reasoning' => 'Highly relevant Supreme Court ruling on unlawful termination',
                                ],
                            ],
                        ]),
                    ],
                ]],
            ]);

        $agent = $this->createExtendedAgent();
        $agent->exposeScoreDecisions($metadata, 'Nezakonit otkaz');

        // Query: "Why was this decision scored 85?"
        $trace = AiReasoningTrace::where('output_data->decision_id', 'dec-123')->first();

        $this->assertNotNull($trace);
        $this->assertEquals(85, $trace->output_data['score']);
        $this->assertStringContainsString('Supreme Court', $trace->reasoning);
        $this->assertStringContainsString('unlawful termination', $trace->reasoning);
    }

    /**
     * Test that we can explain why a decision scored low
     */
    public function test_can_explain_why_decision_scored_low()
    {
        $metadata = [
            'dec-456' => [
                'title' => 'Unrelated administrative decision',
                'court' => 'Općinski sud',
                'date' => '2018-05-10',
                'type' => 'Rješenje',
                'description' => 'Administrative matter unrelated to labor law',
            ],
        ];

        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'scores' => [
                                [
                                    'id' => 'dec-456',
                                    'score' => 20,
                                    'reasoning' => 'Not relevant to topic, administrative matter, outdated',
                                ],
                            ],
                        ]),
                    ],
                ]],
            ]);

        $agent = $this->createExtendedAgent();
        $agent->exposeScoreDecisions($metadata, 'Radno pravo');

        $trace = AiReasoningTrace::where('output_data->decision_id', 'dec-456')->first();

        $this->assertNotNull($trace);
        $this->assertEquals(20, $trace->output_data['score']);
        $this->assertStringContainsString('Not relevant', $trace->reasoning);
        $this->assertLessThan(0.4, $trace->confidence);
    }

    // ========================================================================
    // NESTED TRACE TESTS (Topic -> Batch -> Individual Decisions)
    // ========================================================================

    /**
     * Test that scoring creates nested traces: root -> batch -> individual decisions
     */
    public function test_scoring_creates_nested_trace_hierarchy()
    {
        $metadata = [
            'dec-1' => ['title' => 'Decision 1', 'court' => 'Vrhovni sud', 'date' => '2025-01-15', 'type' => 'Presuda', 'description' => 'Test'],
            'dec-2' => ['title' => 'Decision 2', 'court' => 'Županijski sud', 'date' => '2025-01-10', 'type' => 'Rješenje', 'description' => 'Test'],
        ];

        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'scores' => [
                                ['id' => 'dec-1', 'score' => 85, 'reasoning' => 'Relevant'],
                                ['id' => 'dec-2', 'score' => 70, 'reasoning' => 'Relevant'],
                            ],
                        ]),
                    ],
                ]],
            ]);

        $agent = $this->createExtendedAgent();
        $agent->exposeScoreDecisions($metadata, 'Radno pravo');

        // Get root trace (topic scoring)
        $rootTrace = AiReasoningTrace::where('step_type', 'topic_scoring')
            ->whereNull('parent_trace_id')
            ->first();

        $this->assertNotNull($rootTrace, 'Should have root trace for topic scoring');

        // Get batch trace
        $batchTrace = AiReasoningTrace::where('step_type', 'batch_scoring')
            ->where('parent_trace_id', $rootTrace->trace_id)
            ->first();

        $this->assertNotNull($batchTrace, 'Should have batch trace under root');

        // Get individual decision traces
        $decisionTraces = AiReasoningTrace::where('step_type', 'decision_scoring')
            ->where('parent_trace_id', $batchTrace->trace_id)
            ->get();

        $this->assertCount(2, $decisionTraces, 'Should have 2 decision traces under batch');
    }

    /**
     * Test that we can retrieve full trace tree for a scoring operation
     */
    public function test_can_retrieve_full_scoring_trace_tree()
    {
        $metadata = [
            'dec-1' => ['title' => 'Decision 1', 'court' => 'Vrhovni sud', 'date' => '2025-01-15', 'type' => 'Presuda', 'description' => 'Test'],
        ];

        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'scores' => [
                                ['id' => 'dec-1', 'score' => 85, 'reasoning' => 'Relevant'],
                            ],
                        ]),
                    ],
                ]],
            ]);

        $agent = $this->createExtendedAgent();
        $agent->exposeScoreDecisions($metadata, 'Radno pravo');

        $rootTrace = AiReasoningTrace::where('step_type', 'topic_scoring')->first();

        // Use ReasoningTraceService to retrieve full tree
        $traceService = app(ReasoningTraceService::class);
        $tree = $traceService->buildTraceTree($rootTrace->trace_id);

        $this->assertNotNull($tree);
        $this->assertArrayHasKey('children', $tree);
        $this->assertNotEmpty($tree['children'], 'Root should have child traces');
    }

    // ========================================================================
    // INTEGRATION TEST
    // ========================================================================

    /**
     * Integration test: Full discovery flow creates comprehensive traces
     */
    public function test_full_discovery_creates_comprehensive_traces()
    {
        // Mock OpenAI chat calls (topic generation + scoring)
        $this->openAIMock->shouldReceive('chat')
            ->andReturnUsing(function ($messages, $model = null) {
                // Check if this is topic generation (no specific model) or scoring (gpt-4o-mini)
                if ($model === 'gpt-4o-mini' || (isset($messages[1]['content']) && str_contains($messages[1]['content'], 'Score these'))) {
                    return [
                        'choices' => [[
                            'message' => [
                                'content' => json_encode([
                                    'scores' => [
                                        ['id' => 'dec-1', 'score' => 85, 'reasoning' => 'Highly relevant'],
                                    ],
                                ]),
                            ],
                        ]],
                    ];
                } else {
                    return [
                        'choices' => [[
                            'message' => [
                                'content' => json_encode([
                                    'topics' => ['Radno pravo'],
                                ]),
                            ],
                        ]],
                    ];
                }
            });

        // Mock search
        $this->clientMock->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn(['ids' => ['dec-1'], 'status' => 200]);

        // Mock metadata fetch
        $this->clientMock->shouldReceive('fetchDecisionMeta')
            ->once()
            ->andReturn([
                'title' => 'Important decision',
                'court' => 'Vrhovni sud',
                'date' => '2025-01-15',
                'type' => 'Presuda',
                'description' => 'Test',
            ]);

        // Mock ingestion
        $this->ingestMock->shouldReceive('ingestByIds')
            ->once()
            ->andReturn(['inserted' => 1, 'errors' => 0, 'skipped' => 0]);

        // Configure agent for test
        $this->agent->setTopicsPerRun(1);
        $this->agent->setDecisionsPerTopic(10);
        $this->agent->setIngestPerTopic(1);
        $this->agent->setRelevanceThreshold(70);

        // Run discovery
        $this->agent->discover();

        // Verify traces were created
        $topicTrace = AiReasoningTrace::where('step_type', 'topic_generation')->first();
        $this->assertNotNull($topicTrace, 'Should create topic generation trace');

        $scoringTrace = AiReasoningTrace::where('step_type', 'decision_scoring')
            ->where('output_data->decision_id', 'dec-1')
            ->first();
        $this->assertNotNull($scoringTrace, 'Should create decision scoring trace');

        $this->assertEquals(85, $scoringTrace->output_data['score']);
        $this->assertStringContainsString('relevant', $scoringTrace->reasoning);
        $this->assertGreaterThan(0.8, $scoringTrace->confidence);
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    protected function createExtendedAgent()
    {
        return new class($this->clientMock, $this->ingestMock, $this->openAIMock) extends DecisionDiscoveryAgent
        {
            public function exposeGenerateResearchTopics(): array
            {
                return $this->generateResearchTopics();
            }

            public function exposeScoreDecisions(array $metadata, string $topic): array
            {
                return $this->scoreDecisions($metadata, $topic);
            }

            public function exposeScoreBatch(array $batch, string $topic): array
            {
                return $this->scoreBatch($batch, $topic);
            }
        };
    }
}
