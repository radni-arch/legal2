<?php

namespace Tests\Unit\Services\Explainability;

use App\Models\AiReasoningTrace;
use App\Services\Explainability\ReasoningTraceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TDD Test: Reasoning Trace Service
 *
 * Tests for Sprint 1.2: ReasoningTraceService Foundation
 *
 * Acceptance Criteria:
 * - Can create nested traces (3+ levels deep)
 * - getFullTrace() returns correct tree structure
 * - Handles missing traces gracefully
 * - Unit tests achieve 90%+ coverage
 * - Performance: <50ms for trace with 100 steps
 */
class ReasoningTraceServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReasoningTraceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReasoningTraceService;
    }

    /**
     * Test 1: startTrace creates a new trace
     *
     * @test
     */
    public function test_start_trace_creates_new_trace()
    {
        // Act
        $traceId = $this->service->startTrace(
            'analyze_evidence',
            ['evidence' => 'test evidence', 'case_id' => 123]
        );

        // Assert
        $this->assertNotNull($traceId);
        $this->assertIsString($traceId);

        // Verify trace exists in database
        $trace = AiReasoningTrace::where('trace_id', $traceId)->first();
        $this->assertNotNull($trace);
        $this->assertEquals('analyze_evidence', $trace->operation);
        $this->assertEquals(['evidence' => 'test evidence', 'case_id' => 123], $trace->input_data);
    }

    /**
     * Test 2: startTrace with parent creates nested trace
     *
     * @test
     */
    public function test_start_trace_with_parent_creates_nested_trace()
    {
        // Arrange
        $parentId = $this->service->startTrace('research', ['query' => 'find laws']);

        // Act
        $childId = $this->service->startTrace(
            'search_database',
            ['database' => 'laws'],
            $parentId
        );

        // Assert
        $this->assertNotNull($childId);
        $this->assertNotEquals($parentId, $childId);

        $childTrace = AiReasoningTrace::where('trace_id', $childId)->first();
        $this->assertEquals($parentId, $childTrace->parent_trace_id);
        $this->assertEquals('search_database', $childTrace->operation);
    }

    /**
     * Test 3: endTrace updates trace with output and reasoning
     *
     * @test
     */
    public function test_end_trace_updates_trace_with_output()
    {
        // Arrange
        $traceId = $this->service->startTrace('analyze', ['input' => 'test']);

        // Act
        $result = $this->service->endTrace(
            $traceId,
            ['results' => 'analysis complete'],
            'Analyzed evidence for constitutional violations',
            0.95
        );

        // Assert
        $this->assertTrue($result);

        $trace = AiReasoningTrace::where('trace_id', $traceId)->first();
        $this->assertEquals(['results' => 'analysis complete'], $trace->output_data);
        $this->assertEquals('Analyzed evidence for constitutional violations', $trace->reasoning);
        $this->assertEquals(0.95, $trace->confidence);
    }

    /**
     * Test 4: endTrace returns false for non-existent trace
     *
     * @test
     */
    public function test_end_trace_returns_false_for_missing_trace()
    {
        // Act - Use valid UUID format that doesn't exist in database
        $result = $this->service->endTrace(
            '00000000-0000-4000-8000-000000000000',
            ['output' => 'test'],
            'reasoning',
            0.5
        );

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test 5: getFullTrace returns single trace
     *
     * @test
     */
    public function test_get_full_trace_returns_single_trace()
    {
        // Arrange
        $traceId = $this->service->startTrace('operation1', ['input' => 'data']);
        $this->service->endTrace($traceId, ['output' => 'result'], 'reasoning', 0.9);

        // Act
        $traces = $this->service->getFullTrace($traceId);

        // Assert
        $this->assertIsArray($traces);
        $this->assertCount(1, $traces);
        $this->assertEquals($traceId, $traces[0]['trace_id']);
        $this->assertEquals('operation1', $traces[0]['operation']);
    }

    /**
     * Test 6: getFullTrace returns parent and child traces
     *
     * @test
     */
    public function test_get_full_trace_returns_parent_and_children()
    {
        // Arrange
        $parentId = $this->service->startTrace('parent_op', ['p' => 1]);
        $child1Id = $this->service->startTrace('child1_op', ['c' => 1], $parentId);
        $child2Id = $this->service->startTrace('child2_op', ['c' => 2], $parentId);

        // Act
        $traces = $this->service->getFullTrace($parentId);

        // Assert
        $this->assertCount(3, $traces);

        $traceIds = array_column($traces, 'trace_id');
        $this->assertContains($parentId, $traceIds);
        $this->assertContains($child1Id, $traceIds);
        $this->assertContains($child2Id, $traceIds);
    }

    /**
     * Test 7: getFullTrace handles deeply nested traces (3+ levels)
     *
     * @test
     */
    public function test_get_full_trace_handles_deeply_nested_traces()
    {
        // Arrange - Create 4 levels of nesting
        $level1 = $this->service->startTrace('level1', ['l' => 1]);
        $level2 = $this->service->startTrace('level2', ['l' => 2], $level1);
        $level3 = $this->service->startTrace('level3', ['l' => 3], $level2);
        $level4 = $this->service->startTrace('level4', ['l' => 4], $level3);

        // Act
        $traces = $this->service->getFullTrace($level1);

        // Assert
        $this->assertCount(4, $traces);

        $operations = array_column($traces, 'operation');
        $this->assertContains('level1', $operations);
        $this->assertContains('level2', $operations);
        $this->assertContains('level3', $operations);
        $this->assertContains('level4', $operations);
    }

    /**
     * Test 8: getFullTrace returns empty array for non-existent trace
     *
     * @test
     */
    public function test_get_full_trace_handles_missing_trace_gracefully()
    {
        // Act - Use valid UUID format that doesn't exist in database
        $traces = $this->service->getFullTrace('00000000-0000-4000-8000-000000000000');

        // Assert
        $this->assertIsArray($traces);
        $this->assertEmpty($traces);
    }

    /**
     * Test 9: buildTraceTree creates nested structure
     *
     * @test
     */
    public function test_build_trace_tree_creates_nested_structure()
    {
        // Arrange
        $parentId = $this->service->startTrace('parent', ['p' => 1]);
        $child1Id = $this->service->startTrace('child1', ['c' => 1], $parentId);
        $child2Id = $this->service->startTrace('child2', ['c' => 2], $parentId);

        // Act
        $tree = $this->service->buildTraceTree($parentId);

        // Assert
        $this->assertIsArray($tree);
        $this->assertEquals($parentId, $tree['trace_id']);
        $this->assertEquals('parent', $tree['operation']);
        $this->assertArrayHasKey('children', $tree);
        $this->assertCount(2, $tree['children']);

        $childOperations = array_column($tree['children'], 'operation');
        $this->assertContains('child1', $childOperations);
        $this->assertContains('child2', $childOperations);
    }

    /**
     * Test 10: buildTraceTree handles deeply nested structure
     *
     * @test
     */
    public function test_build_trace_tree_handles_deep_nesting()
    {
        // Arrange
        $level1 = $this->service->startTrace('level1', ['l' => 1]);
        $level2 = $this->service->startTrace('level2', ['l' => 2], $level1);
        $level3 = $this->service->startTrace('level3', ['l' => 3], $level2);

        // Act
        $tree = $this->service->buildTraceTree($level1);

        // Assert
        $this->assertEquals('level1', $tree['operation']);
        $this->assertCount(1, $tree['children']);
        $this->assertEquals('level2', $tree['children'][0]['operation']);
        $this->assertCount(1, $tree['children'][0]['children']);
        $this->assertEquals('level3', $tree['children'][0]['children'][0]['operation']);
    }

    /**
     * Test 11: buildTraceTree returns null for missing trace
     *
     * @test
     */
    public function test_build_trace_tree_returns_null_for_missing_trace()
    {
        // Act - Use valid UUID format that doesn't exist in database
        $tree = $this->service->buildTraceTree('00000000-0000-4000-8000-000000000000');

        // Assert
        $this->assertNull($tree);
    }

    /**
     * Test 12: startTrace stores agent type and step type
     *
     * @test
     */
    public function test_start_trace_stores_metadata()
    {
        // Act
        $traceId = $this->service->startTrace(
            'analyze',
            ['input' => 'test'],
            null,
            'AnalysisAgent',
            'analysis'
        );

        // Assert
        $trace = AiReasoningTrace::where('trace_id', $traceId)->first();
        $this->assertEquals('AnalysisAgent', $trace->agent_type);
        $this->assertEquals('analysis', $trace->step_type);
    }

    /**
     * Test 13: endTrace stores performance metrics
     *
     * @test
     */
    public function test_end_trace_stores_performance_metrics()
    {
        // Arrange
        $traceId = $this->service->startTrace('operation', ['input' => 'test']);

        // Act
        $this->service->endTrace(
            $traceId,
            ['output' => 'result'],
            'reasoning',
            0.95,
            150,      // tokens_used
            1250      // duration_ms
        );

        // Assert
        $trace = AiReasoningTrace::where('trace_id', $traceId)->first();
        $this->assertEquals(150, $trace->tokens_used);
        $this->assertEquals(1250, $trace->duration_ms);
    }

    /**
     * Test 14: Complex nested trace structure
     *
     * @test
     */
    public function test_complex_nested_trace_structure()
    {
        // Arrange - Create complex tree
        $root = $this->service->startTrace('root', ['r' => 1]);

        $branch1 = $this->service->startTrace('branch1', ['b' => 1], $root);
        $leaf1a = $this->service->startTrace('leaf1a', ['l' => 1], $branch1);
        $leaf1b = $this->service->startTrace('leaf1b', ['l' => 2], $branch1);

        $branch2 = $this->service->startTrace('branch2', ['b' => 2], $root);
        $leaf2a = $this->service->startTrace('leaf2a', ['l' => 3], $branch2);

        // Act
        $traces = $this->service->getFullTrace($root);
        $tree = $this->service->buildTraceTree($root);

        // Assert
        $this->assertCount(6, $traces);
        $this->assertCount(2, $tree['children']); // 2 branches
        $this->assertCount(2, $tree['children'][0]['children']); // branch1 has 2 leaves
        $this->assertCount(1, $tree['children'][1]['children']); // branch2 has 1 leaf
    }

    /**
     * Test 15: getFullTrace orders traces chronologically
     *
     * @test
     */
    public function test_get_full_trace_orders_chronologically()
    {
        // Arrange
        $first = $this->service->startTrace('first', ['order' => 1]);
        sleep(1); // Ensure different timestamps
        $second = $this->service->startTrace('second', ['order' => 2], $first);
        sleep(1);
        $third = $this->service->startTrace('third', ['order' => 3], $second);

        // Act
        $traces = $this->service->getFullTrace($first);

        // Assert
        $this->assertEquals('first', $traces[0]['operation']);
        $this->assertEquals('second', $traces[1]['operation']);
        $this->assertEquals('third', $traces[2]['operation']);
    }

    /**
     * Test 16: Performance test - 100 traces in <50ms
     *
     * @test
     */
    public function test_performance_handles_100_traces_under_50ms()
    {
        // Arrange - Create 100 sequential nested traces
        $rootId = $this->service->startTrace('root', ['step' => 0]);
        $currentParent = $rootId;

        for ($i = 1; $i < 100; $i++) {
            $currentParent = $this->service->startTrace(
                "step_{$i}",
                ['step' => $i],
                $currentParent
            );
        }

        // Act
        $startTime = microtime(true);
        $traces = $this->service->getFullTrace($rootId);
        $duration = (microtime(true) - $startTime) * 1000; // Convert to ms

        // Assert
        $this->assertCount(100, $traces);
        $this->assertLessThan(50, $duration, "Performance test failed: {$duration}ms > 50ms");
    }

    /**
     * Test 17: startTrace with null input
     *
     * @test
     */
    public function test_start_trace_handles_null_input()
    {
        // Act
        $traceId = $this->service->startTrace('operation', null);

        // Assert
        $trace = AiReasoningTrace::where('trace_id', $traceId)->first();
        $this->assertNull($trace->input_data);
    }

    /**
     * Test 18: endTrace with partial data
     *
     * @test
     */
    public function test_end_trace_with_minimal_data()
    {
        // Arrange
        $traceId = $this->service->startTrace('operation', ['input' => 'test']);

        // Act - Only output, no reasoning or confidence
        $result = $this->service->endTrace($traceId, ['output' => 'result']);

        // Assert
        $this->assertTrue($result);
        $trace = AiReasoningTrace::where('trace_id', $traceId)->first();
        $this->assertEquals(['output' => 'result'], $trace->output_data);
        $this->assertNull($trace->reasoning);
        $this->assertNull($trace->confidence);
    }
}
