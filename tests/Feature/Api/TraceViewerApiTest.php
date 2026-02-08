<?php

namespace Tests\Feature\Api;

use App\Services\Explainability\ReasoningTraceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TDD Tests for Trace Viewer API Endpoint
 *
 * Sprint 2.4: Basic Reasoning Trace Integration
 *
 * Tests the /api/explainability/trace/{id} endpoint that returns
 * full trace trees for visualization.
 */
class TraceViewerApiTest extends TestCase
{
    use RefreshDatabase;

    protected ReasoningTraceService $traceService;

    protected function setUp(): void
    {
        // Set AWS environment variables for TextractService
        putenv('AWS_DEFAULT_REGION=us-east-1');
        putenv('AWS_ACCESS_KEY_ID=test-key');
        putenv('AWS_SECRET_ACCESS_KEY=test-secret');
        putenv('AWS_BUCKET=test-bucket');

        parent::setUp();

        $this->traceService = app(ReasoningTraceService::class);
    }

    /** @test */
    public function it_returns_trace_tree_for_valid_trace_id()
    {
        // Create a trace tree
        $rootId = $this->traceService->startTrace(
            'Research Execution',
            ['problem' => 'Home search proportionality'],
            null,
            'research_specialist',
            'research_execution'
        );

        $child1Id = $this->traceService->startTrace(
            'Planning Research',
            ['step' => 'planning'],
            $rootId,
            'research_specialist',
            'planning'
        );

        $this->traceService->endTrace(
            $child1Id,
            ['plan' => 'Created research plan'],
            'Analyzed problem and created research strategy focusing on proportionality',
            0.95,
            250,
            1200
        );

        $this->traceService->endTrace(
            $rootId,
            ['status' => 'complete'],
            'Research completed successfully',
            0.92,
            500,
            5000
        );

        // Call API endpoint
        $response = $this->getJson("/api/explainability/trace/{$rootId}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'trace_id',
            'trace_tree' => [
                'trace_id',
                'agent_type',
                'step_type',
                'operation',
                'reasoning',
                'confidence',
                'children',
            ],
        ]);

        $response->assertJson([
            'success' => true,
            'trace_id' => $rootId,
        ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_trace_id()
    {
        $fakeId = 'fake-uuid-that-does-not-exist';

        $response = $this->getJson("/api/explainability/trace/{$fakeId}");

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'error' => 'Trace not found',
        ]);
    }

    /** @test */
    public function it_returns_full_nested_tree_structure()
    {
        // Create 4-level tree
        $rootId = $this->traceService->startTrace(
            'Root Operation',
            null,
            null,
            'test_agent',
            'root'
        );

        $level2Id = $this->traceService->startTrace(
            'Level 2 Operation',
            null,
            $rootId,
            'test_agent',
            'level2'
        );

        $level3Id = $this->traceService->startTrace(
            'Level 3 Operation',
            null,
            $level2Id,
            'test_agent',
            'level3'
        );

        $level4Id = $this->traceService->startTrace(
            'Level 4 Operation',
            null,
            $level3Id,
            'test_agent',
            'level4'
        );

        $this->traceService->endTrace($level4Id, null, 'Level 4 reasoning', 0.9);
        $this->traceService->endTrace($level3Id, null, 'Level 3 reasoning', 0.9);
        $this->traceService->endTrace($level2Id, null, 'Level 2 reasoning', 0.9);
        $this->traceService->endTrace($rootId, null, 'Root reasoning', 0.9);

        // Call API endpoint
        $response = $this->getJson("/api/explainability/trace/{$rootId}");

        $response->assertStatus(200);

        $tree = $response->json('trace_tree');

        // Verify 4-level structure
        $this->assertNotEmpty($tree['children'], 'Level 1 should have children');
        $this->assertNotEmpty($tree['children'][0]['children'], 'Level 2 should have children');
        $this->assertNotEmpty($tree['children'][0]['children'][0]['children'], 'Level 3 should have children');
        $this->assertNotEmpty($tree['children'][0]['children'][0]['children'][0], 'Level 4 should exist');
    }

    /** @test */
    public function it_includes_all_trace_fields_in_response()
    {
        $rootId = $this->traceService->startTrace(
            'Test Operation',
            ['input' => 'test input'],
            null,
            'test_agent',
            'test_step'
        );

        $this->traceService->endTrace(
            $rootId,
            ['output' => 'test output'],
            'This is the reasoning explanation',
            0.87,
            125,
            2500
        );

        $response = $this->getJson("/api/explainability/trace/{$rootId}");

        $response->assertStatus(200);

        $tree = $response->json('trace_tree');

        $this->assertEquals($rootId, $tree['trace_id']);
        $this->assertEquals('test_agent', $tree['agent_type']);
        $this->assertEquals('test_step', $tree['step_type']);
        $this->assertEquals('Test Operation', $tree['operation']);
        $this->assertEquals('This is the reasoning explanation', $tree['reasoning']);
        $this->assertEquals(0.87, $tree['confidence']);
        $this->assertEquals(125, $tree['tokens_used']);
        $this->assertEquals(2500, $tree['duration_ms']);
        $this->assertArrayHasKey('input_data', $tree);
        $this->assertArrayHasKey('output_data', $tree);
    }

    /** @test */
    public function it_returns_empty_children_array_for_leaf_nodes()
    {
        $rootId = $this->traceService->startTrace(
            'Leaf Node',
            null,
            null,
            'test_agent',
            'leaf'
        );

        $this->traceService->endTrace($rootId, null, 'Leaf reasoning', 0.9);

        $response = $this->getJson("/api/explainability/trace/{$rootId}");

        $response->assertStatus(200);

        $tree = $response->json('trace_tree');

        $this->assertArrayHasKey('children', $tree);
        $this->assertEmpty($tree['children']);
    }

    /** @test */
    public function it_handles_malformed_trace_id()
    {
        $response = $this->getJson('/api/explainability/trace/not-a-uuid');

        $response->assertStatus(404);
    }

    /** @test */
    public function it_returns_trace_count_in_response()
    {
        // Create a tree with multiple nodes
        $rootId = $this->traceService->startTrace('Root', null, null, 'test', 'root');
        $child1Id = $this->traceService->startTrace('Child 1', null, $rootId, 'test', 'child');
        $child2Id = $this->traceService->startTrace('Child 2', null, $rootId, 'test', 'child');
        $grandchildId = $this->traceService->startTrace('Grandchild', null, $child1Id, 'test', 'grandchild');

        $this->traceService->endTrace($grandchildId, null, 'Reasoning', 0.9);
        $this->traceService->endTrace($child1Id, null, 'Reasoning', 0.9);
        $this->traceService->endTrace($child2Id, null, 'Reasoning', 0.9);
        $this->traceService->endTrace($rootId, null, 'Reasoning', 0.9);

        $response = $this->getJson("/api/explainability/trace/{$rootId}");

        $response->assertStatus(200);
        $response->assertJson([
            'trace_count' => 4, // Root + 2 children + 1 grandchild
        ]);
    }
}
