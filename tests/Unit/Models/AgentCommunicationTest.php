<?php

namespace Tests\Unit\Models;

use App\Models\AgentCommunication;
use App\Models\AiReasoningTrace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TDD Test: Agent Communication Model
 *
 * Tests for Sprint 1 User Story 1.1: Database Schema for Reasoning Traces
 * Agent communications track inter-agent message passing and collaboration.
 *
 * Acceptance Criteria:
 * - Can create agent communication records
 * - Links to reasoning traces
 * - Stores message data as JSONB
 * - Tracks sender and receiver agents
 */
class AgentCommunicationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Create basic agent communication
     *
     * @test
     */
    public function test_can_create_agent_communication()
    {
        // Arrange
        $communicationData = [
            'sender_agent_type' => 'ResearchAgent',
            'receiver_agent_type' => 'AnalysisAgent',
            'message_type' => 'data_request',
            'message_data' => [
                'request' => 'Need ZKP case law',
                'filters' => ['year' => 2025],
            ],
            'response_data' => [
                'results' => ['case1', 'case2'],
                'count' => 2,
            ],
            'status' => 'completed',
        ];

        // Act
        $communication = AgentCommunication::create($communicationData);

        // Assert
        $this->assertNotNull($communication->id);
        $this->assertNotNull($communication->communication_id); // UUID auto-generated
        $this->assertEquals('ResearchAgent', $communication->sender_agent_type);
        $this->assertEquals('AnalysisAgent', $communication->receiver_agent_type);
        $this->assertEquals('data_request', $communication->message_type);
        $this->assertEquals(['request' => 'Need ZKP case law', 'filters' => ['year' => 2025]], $communication->message_data);
        $this->assertEquals(['results' => ['case1', 'case2'], 'count' => 2], $communication->response_data);
        $this->assertEquals('completed', $communication->status);
    }

    /**
     * Test 2: Communication links to reasoning trace
     *
     * @test
     */
    public function test_communication_links_to_reasoning_trace()
    {
        // Arrange
        $trace = AiReasoningTrace::create([
            'agent_type' => 'ResearchAgent',
            'step_type' => 'research',
            'operation' => 'search',
            'reasoning' => 'Searching for case law',
        ]);

        // Act
        $communication = AgentCommunication::create([
            'trace_id' => $trace->trace_id,
            'sender_agent_type' => 'ResearchAgent',
            'receiver_agent_type' => 'AnalysisAgent',
            'message_type' => 'result',
            'message_data' => ['results' => 'test'],
        ]);

        // Assert
        $this->assertEquals($trace->trace_id, $communication->trace_id);
        $this->assertNotNull($communication->trace);
        $this->assertEquals($trace->id, $communication->trace->id);
    }

    /**
     * Test 3: Communication UUID is generated
     *
     * @test
     */
    public function test_communication_id_is_uuid()
    {
        // Act
        $communication = AgentCommunication::create([
            'sender_agent_type' => 'Agent1',
            'receiver_agent_type' => 'Agent2',
            'message_type' => 'test',
        ]);

        // Assert
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $communication->communication_id
        );
    }

    /**
     * Test 4: JSONB message data works
     *
     * @test
     */
    public function test_jsonb_message_data_stores_complex_structures()
    {
        // Arrange
        $complexMessage = [
            'action' => 'analyze',
            'parameters' => [
                'case_id' => 123,
                'focus_areas' => ['constitutional', 'procedural'],
                'depth' => 'deep',
            ],
            'metadata' => [
                'priority' => 'high',
                'deadline' => '2025-11-15',
            ],
        ];

        // Act
        $communication = AgentCommunication::create([
            'sender_agent_type' => 'Coordinator',
            'receiver_agent_type' => 'Analyst',
            'message_type' => 'task_assignment',
            'message_data' => $complexMessage,
        ]);

        // Assert
        $this->assertEquals($complexMessage, $communication->message_data);
        $this->assertIsArray($communication->message_data);
        $this->assertEquals('analyze', $communication->message_data['action']);
    }

    /**
     * Test 5: Communication status tracking
     *
     * @test
     */
    public function test_communication_status_can_be_tracked()
    {
        // Act
        $communication = AgentCommunication::create([
            'sender_agent_type' => 'Agent1',
            'receiver_agent_type' => 'Agent2',
            'message_type' => 'request',
            'status' => 'pending',
        ]);

        // Assert
        $this->assertEquals('pending', $communication->status);

        // Update status
        $communication->update(['status' => 'completed']);
        $this->assertEquals('completed', $communication->fresh()->status);
    }

    /**
     * Test 6: Timestamps are automatically set
     *
     * @test
     */
    public function test_timestamps_are_set()
    {
        // Act
        $communication = AgentCommunication::create([
            'sender_agent_type' => 'Agent1',
            'receiver_agent_type' => 'Agent2',
            'message_type' => 'test',
        ]);

        // Assert
        $this->assertNotNull($communication->created_at);
        $this->assertInstanceOf(\DateTimeInterface::class, $communication->created_at);
    }
}
