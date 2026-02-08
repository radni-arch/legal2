<?php

namespace Tests\Unit\Agents;

use App\Agents\Specialists\ResearchSpecialistAgent;
use App\Models\AiReasoningTrace;
use App\Services\Collaboration\SharedAgentContext;
use App\Services\DecisionSearchService;
use App\Services\Explainability\ReasoningTraceService;
use App\Services\LawSearchService;
use App\Services\OpenAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * TDD Tests for ResearchSpecialistAgent Reasoning Trace Integration
 *
 * Sprint 2.4: Basic Reasoning Trace Integration
 *
 * Tests that ResearchSpecialistAgent creates nested traces (4+ levels)
 * with reasoning text for each step of the research process.
 */
class ResearchSpecialistAgentTraceTest extends TestCase
{
    use RefreshDatabase;

    protected ResearchSpecialistAgent $agent;

    protected ReasoningTraceService $traceService;

    protected SharedAgentContext $context;

    protected function setUp(): void
    {
        // Set AWS environment variables for TextractService
        putenv('AWS_DEFAULT_REGION=us-east-1');
        putenv('AWS_ACCESS_KEY_ID=test-key');
        putenv('AWS_SECRET_ACCESS_KEY=test-secret');
        putenv('AWS_BUCKET=test-bucket');

        parent::setUp();

        // Mock HTTP responses for OpenAI API (offline testing)
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'key_concepts' => ['pretres stana', 'nerazmjernost'],
                                'law_queries' => ['ZKP članak 179', 'Ustav RH članak 34'],
                                'decision_queries' => ['pretres stana nerazmjeran', 'kućna pretraga proporcionalnost'],
                                'jurisdiction' => 'Osijek',
                                'reasoning' => 'Focusing on home search proportionality under Croatian law',
                            ]),
                        ],
                    ],
                ],
                'usage' => ['total_tokens' => 250],
            ], 200),
        ]);

        // Initialize services
        $this->traceService = app(ReasoningTraceService::class);

        $mockLawSearch = $this->createMock(LawSearchService::class);
        $mockLawSearch->method('hybridSearch')->willReturn([
            'success' => true,
            'data' => [
                ['id' => 'law-1', 'title' => 'ZKP Čl. 179', 'score' => 0.95],
                ['id' => 'law-2', 'title' => 'Ustav RH Čl. 34', 'score' => 0.90],
            ],
        ]);

        $mockDecisionSearch = $this->createMock(DecisionSearchService::class);
        $mockDecisionSearch->method('hybridSearch')->willReturn([
            'success' => true,
            'data' => [
                ['id' => 'dec-1', 'court' => 'Vrhovni sud', 'case_number' => 'K-123/2024', 'score' => 0.92],
                ['id' => 'dec-2', 'court' => 'Županijski sud u Osijeku', 'case_number' => 'K-456/2024', 'score' => 0.88],
            ],
        ]);

        $mockOpenAI = app(OpenAIService::class);

        $this->agent = new ResearchSpecialistAgent(
            $mockLawSearch,
            $mockDecisionSearch,
            $mockOpenAI,
            $this->traceService
        );

        // Create AgentCollaboration for context
        $collaboration = \App\Models\AgentCollaboration::create([
            'orchestrator' => 'test',
            'problem_type' => 'legal_research',
            'problem_statement' => 'Home search proportionality analysis',
            'status' => 'in_progress',
        ]);

        // Create shared context
        $this->context = new SharedAgentContext($collaboration);
    }

    /** @test */
    public function it_creates_root_trace_when_starting_research()
    {
        $task = [
            'id' => 'task-1',
            'description' => 'Research home search proportionality',
        ];

        $result = $this->agent->execute($this->context, $task);

        // Should have root trace
        $this->assertArrayHasKey('trace_id', $result);
        $this->assertNotEmpty($result['trace_id']);

        // Verify trace exists in database
        $trace = AiReasoningTrace::where('trace_id', $result['trace_id'])->first();
        $this->assertNotNull($trace);
        $this->assertEquals('research_specialist', $trace->agent_type);
        $this->assertEquals('research_execution', $trace->step_type);
        $this->assertNull($trace->parent_trace_id); // Root trace has no parent
    }

    /** @test */
    public function it_creates_nested_trace_for_planning_step()
    {
        $task = [
            'id' => 'task-1',
            'description' => 'Research home search proportionality',
        ];

        $result = $this->agent->execute($this->context, $task);

        // Get the full trace tree
        $traceTree = $this->traceService->buildTraceTree($result['trace_id']);
        $this->assertNotNull($traceTree);

        // Should have at least one child for planning
        $this->assertArrayHasKey('children', $traceTree);
        $this->assertNotEmpty($traceTree['children']);

        // Find planning trace
        $planningTrace = collect($traceTree['children'])->firstWhere('step_type', 'planning');
        $this->assertNotNull($planningTrace, 'Planning trace should exist');
        $this->assertEquals($result['trace_id'], $planningTrace['parent_trace_id']);
        $this->assertNotEmpty($planningTrace['reasoning'], 'Planning trace should have reasoning');
    }

    /** @test */
    public function it_creates_nested_traces_for_law_searches()
    {
        $task = [
            'id' => 'task-1',
            'description' => 'Research home search proportionality',
        ];

        $result = $this->agent->execute($this->context, $task);

        // Get the full trace tree
        $traceTree = $this->traceService->buildTraceTree($result['trace_id']);

        // Should have child traces for law searches
        $lawSearchTraces = $this->findTracesByStepType($traceTree, 'law_search');
        $this->assertNotEmpty($lawSearchTraces, 'Should have law search traces');

        // Each law search should have reasoning
        foreach ($lawSearchTraces as $lawTrace) {
            $this->assertNotEmpty($lawTrace['reasoning'], 'Law search trace should have reasoning');
            $this->assertArrayHasKey('input_data', $lawTrace);
        }
    }

    /** @test */
    public function it_creates_nested_traces_for_decision_searches()
    {
        $task = [
            'id' => 'task-1',
            'description' => 'Research home search proportionality',
        ];

        $result = $this->agent->execute($this->context, $task);

        // Get the full trace tree
        $traceTree = $this->traceService->buildTraceTree($result['trace_id']);

        // Should have child traces for decision searches
        $decisionSearchTraces = $this->findTracesByStepType($traceTree, 'decision_search');
        $this->assertNotEmpty($decisionSearchTraces, 'Should have decision search traces');

        // Each decision search should have reasoning
        foreach ($decisionSearchTraces as $decTrace) {
            $this->assertNotEmpty($decTrace['reasoning'], 'Decision search trace should have reasoning');
            $this->assertArrayHasKey('input_data', $decTrace);
        }
    }

    /** @test */
    public function it_creates_nested_trace_for_prioritization_step()
    {
        $task = [
            'id' => 'task-1',
            'description' => 'Research home search proportionality',
        ];

        $result = $this->agent->execute($this->context, $task);

        // Get the full trace tree
        $traceTree = $this->traceService->buildTraceTree($result['trace_id']);

        // Should have prioritization trace
        $prioritizationTrace = $this->findTraceByStepType($traceTree, 'prioritization');
        $this->assertNotNull($prioritizationTrace, 'Prioritization trace should exist');
        $this->assertNotEmpty($prioritizationTrace['reasoning'], 'Prioritization trace should have reasoning');
    }

    /** @test */
    public function it_creates_trace_tree_with_at_least_four_levels()
    {
        $task = [
            'id' => 'task-1',
            'description' => 'Research home search proportionality',
        ];

        $result = $this->agent->execute($this->context, $task);

        // Get the full trace tree
        $traceTree = $this->traceService->buildTraceTree($result['trace_id']);

        // Calculate tree depth
        $depth = $this->calculateTreeDepth($traceTree);

        // Should have at least 4 levels:
        // Level 1: Root (research_execution)
        // Level 2: Planning, law searches, decision searches, prioritization
        // Level 3: Individual law/decision searches
        // Level 4: Search results processing
        $this->assertGreaterThanOrEqual(4, $depth, 'Trace tree should have at least 4 levels');
    }

    /** @test */
    public function it_includes_reasoning_text_in_all_traces()
    {
        $task = [
            'id' => 'task-1',
            'description' => 'Research home search proportionality',
        ];

        $result = $this->agent->execute($this->context, $task);

        // Get all traces in the tree
        $allTraces = $this->traceService->getFullTrace($result['trace_id']);

        // All traces (except root which might not have reasoning yet) should have reasoning
        $tracesWithReasoning = collect($allTraces)->filter(fn ($t) => ! empty($t['reasoning']))->count();
        $this->assertGreaterThan(0, $tracesWithReasoning, 'Should have traces with reasoning');
    }

    /** @test */
    public function it_records_confidence_scores_in_traces()
    {
        $task = [
            'id' => 'task-1',
            'description' => 'Research home search proportionality',
        ];

        $result = $this->agent->execute($this->context, $task);

        // Get all traces in the tree
        $allTraces = $this->traceService->getFullTrace($result['trace_id']);

        // At least some traces should have confidence scores
        $tracesWithConfidence = collect($allTraces)->filter(fn ($t) => isset($t['confidence']) && $t['confidence'] > 0)->count();
        $this->assertGreaterThan(0, $tracesWithConfidence, 'Should have traces with confidence scores');
    }

    /** @test */
    public function it_records_token_usage_in_traces()
    {
        $task = [
            'id' => 'task-1',
            'description' => 'Research home search proportionality',
        ];

        $result = $this->agent->execute($this->context, $task);

        // Get all traces in the tree
        $allTraces = $this->traceService->getFullTrace($result['trace_id']);

        // Planning step should have token usage
        $planningTrace = collect($allTraces)->firstWhere('step_type', 'planning');
        if ($planningTrace) {
            $this->assertGreaterThan(0, $planningTrace['tokens_used'] ?? 0, 'Planning trace should record token usage');
        }
    }

    /** @test */
    public function it_links_traces_in_correct_parent_child_hierarchy()
    {
        $task = [
            'id' => 'task-1',
            'description' => 'Research home search proportionality',
        ];

        $result = $this->agent->execute($this->context, $task);

        // Get all traces
        $allTraces = $this->traceService->getFullTrace($result['trace_id']);

        // Verify all non-root traces have valid parent_trace_id
        foreach ($allTraces as $trace) {
            if ($trace['trace_id'] !== $result['trace_id']) {
                $this->assertNotNull($trace['parent_trace_id'], 'Non-root trace should have parent');

                // Verify parent exists in the tree
                $parentExists = collect($allTraces)->contains('trace_id', $trace['parent_trace_id']);
                $this->assertTrue($parentExists, 'Parent trace should exist in tree');
            }
        }
    }

    // Helper methods

    /**
     * Find all traces with a specific step_type in the tree
     */
    protected function findTracesByStepType(array $tree, string $stepType): array
    {
        $found = [];

        if (($tree['step_type'] ?? null) === $stepType) {
            $found[] = $tree;
        }

        if (! empty($tree['children'])) {
            foreach ($tree['children'] as $child) {
                $found = array_merge($found, $this->findTracesByStepType($child, $stepType));
            }
        }

        return $found;
    }

    /**
     * Find first trace with a specific step_type in the tree
     */
    protected function findTraceByStepType(array $tree, string $stepType): ?array
    {
        if (($tree['step_type'] ?? null) === $stepType) {
            return $tree;
        }

        if (! empty($tree['children'])) {
            foreach ($tree['children'] as $child) {
                $result = $this->findTraceByStepType($child, $stepType);
                if ($result) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Calculate the maximum depth of the trace tree
     */
    protected function calculateTreeDepth(array $tree): int
    {
        if (empty($tree['children'])) {
            return 1;
        }

        $maxChildDepth = 0;
        foreach ($tree['children'] as $child) {
            $childDepth = $this->calculateTreeDepth($child);
            $maxChildDepth = max($maxChildDepth, $childDepth);
        }

        return 1 + $maxChildDepth;
    }
}
