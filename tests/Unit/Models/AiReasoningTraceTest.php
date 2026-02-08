<?php

namespace Tests\Unit\Models;

use App\Models\AiReasoningTrace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TDD Test: AI Reasoning Trace Model
 *
 * Tests for Sprint 1 User Story 1.1: Database Schema for Reasoning Traces
 *
 * Acceptance Criteria:
 * - Can insert trace with parent_trace_id (nested traces)
 * - Can query full trace tree via recursive CTE
 * - Model has proper relationships
 * - Factory generates valid test data
 */
class AiReasoningTraceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Create basic reasoning trace
     *
     * @test
     */
    public function test_can_create_reasoning_trace()
    {
        // Arrange
        $traceData = [
            'agent_type' => 'AutonomousResearchAgent',
            'step_type' => 'analysis',
            'operation' => 'analyze_evidence',
            'input_data' => ['evidence' => 'test evidence'],
            'output_data' => ['result' => 'analyzed'],
            'reasoning' => 'Analyzed evidence for constitutional violations',
            'confidence' => 0.95,
            'tokens_used' => 150,
            'duration_ms' => 1250,
        ];

        // Act
        $trace = AiReasoningTrace::create($traceData);

        // Assert
        $this->assertNotNull($trace->id);
        $this->assertNotNull($trace->trace_id); // UUID auto-generated
        $this->assertEquals('AutonomousResearchAgent', $trace->agent_type);
        $this->assertEquals('analysis', $trace->step_type);
        $this->assertEquals('analyze_evidence', $trace->operation);
        $this->assertEquals(['evidence' => 'test evidence'], $trace->input_data);
        $this->assertEquals(['result' => 'analyzed'], $trace->output_data);
        $this->assertEquals('Analyzed evidence for constitutional violations', $trace->reasoning);
        $this->assertEquals(0.95, $trace->confidence);
        $this->assertEquals(150, $trace->tokens_used);
        $this->assertEquals(1250, $trace->duration_ms);
    }

    /**
     * Test 2: Create nested reasoning trace with parent
     *
     * @test
     */
    public function test_can_create_nested_trace_with_parent()
    {
        // Arrange
        $parentTrace = AiReasoningTrace::create([
            'agent_type' => 'AutonomousResearchAgent',
            'step_type' => 'research',
            'operation' => 'search_laws',
            'reasoning' => 'Parent trace',
        ]);

        // Act
        $childTrace = AiReasoningTrace::create([
            'parent_trace_id' => $parentTrace->trace_id,
            'agent_type' => 'AutonomousResearchAgent',
            'step_type' => 'analysis',
            'operation' => 'analyze_law',
            'reasoning' => 'Child trace analyzing law from parent search',
        ]);

        // Assert
        $this->assertNotNull($childTrace->id);
        $this->assertEquals($parentTrace->trace_id, $childTrace->parent_trace_id);
    }

    /**
     * Test 3: Parent-child relationship works
     *
     * @test
     */
    public function test_parent_child_relationship()
    {
        // Arrange
        $parentTrace = AiReasoningTrace::create([
            'agent_type' => 'ResearchAgent',
            'step_type' => 'search',
            'operation' => 'search',
            'reasoning' => 'Parent',
        ]);

        $childTrace = AiReasoningTrace::create([
            'parent_trace_id' => $parentTrace->trace_id,
            'agent_type' => 'AnalysisAgent',
            'step_type' => 'analyze',
            'operation' => 'analyze',
            'reasoning' => 'Child',
        ]);

        // Act
        $parent = $childTrace->parent;
        $children = $parentTrace->children;

        // Assert
        $this->assertNotNull($parent);
        $this->assertEquals($parentTrace->id, $parent->id);
        $this->assertCount(1, $children);
        $this->assertEquals($childTrace->id, $children->first()->id);
    }

    /**
     * Test 4: JSONB columns work properly
     *
     * @test
     */
    public function test_jsonb_columns_store_and_retrieve_data()
    {
        // Arrange
        $complexInput = [
            'query' => 'search for ZKP violations',
            'filters' => ['court' => 'Županijski sud', 'year' => 2025],
            'metadata' => ['source' => 'user', 'session_id' => 'abc123'],
        ];

        $complexOutput = [
            'results' => [
                ['law' => 'ZKP Članak 9', 'relevance' => 0.95],
                ['law' => 'ZKP Članak 12', 'relevance' => 0.87],
            ],
            'count' => 2,
        ];

        // Act
        $trace = AiReasoningTrace::create([
            'agent_type' => 'SearchAgent',
            'step_type' => 'search',
            'operation' => 'vector_search',
            'input_data' => $complexInput,
            'output_data' => $complexOutput,
            'reasoning' => 'Performed vector search',
        ]);

        // Assert
        $this->assertEquals($complexInput, $trace->input_data);
        $this->assertEquals($complexOutput, $trace->output_data);
        $this->assertIsArray($trace->input_data);
        $this->assertIsArray($trace->output_data);
    }

    /**
     * Test 5: Trace ID is UUID format
     *
     * @test
     */
    public function test_trace_id_is_uuid()
    {
        // Act
        $trace = AiReasoningTrace::create([
            'agent_type' => 'TestAgent',
            'step_type' => 'test',
            'operation' => 'test_operation',
            'reasoning' => 'Test reasoning',
        ]);

        // Assert
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $trace->trace_id
        );
    }

    /**
     * Test 6: Timestamps are automatically set
     *
     * @test
     */
    public function test_timestamps_are_set()
    {
        // Act
        $trace = AiReasoningTrace::create([
            'agent_type' => 'TestAgent',
            'step_type' => 'test',
            'operation' => 'test_operation',
            'reasoning' => 'Test',
        ]);

        // Assert
        $this->assertNotNull($trace->created_at);
        $this->assertInstanceOf(\DateTimeInterface::class, $trace->created_at);
    }
}
