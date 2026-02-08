<?php

namespace Tests\Unit\Agents;

use App\Services\AgentCommunicationBus;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AgentCommunicationBusIntegrationTest extends TestCase
{
    use UsesTestDatabase;

    private AgentCommunicationBus $bus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bus = app(AgentCommunicationBus::class);
    }

    /** @test */
    public function it_handles_multiple_agents_sending_to_same_receiver(): void
    {
        $toAgent = 'OrchestratorAgent';

        $this->bus->sendMessage($toAgent, 'RESULT', ['data' => 'from_agent1'], 'Agent1');
        $this->bus->sendMessage($toAgent, 'RESULT', ['data' => 'from_agent2'], 'Agent2');
        $this->bus->sendMessage($toAgent, 'RESULT', ['data' => 'from_agent3'], 'Agent3');

        $messages = $this->bus->receiveMessages($toAgent);

        $this->assertCount(3, $messages);
        $this->assertEquals('Agent1', $messages[0]['from_agent']);
        $this->assertEquals('Agent2', $messages[1]['from_agent']);
        $this->assertEquals('Agent3', $messages[2]['from_agent']);
    }

    /** @test */
    public function it_handles_single_agent_sending_to_multiple_receivers(): void
    {
        $fromAgent = 'OrchestratorAgent';

        $this->bus->sendMessage('Agent1', 'TASK', ['task' => '1'], $fromAgent);
        $this->bus->sendMessage('Agent2', 'TASK', ['task' => '2'], $fromAgent);
        $this->bus->sendMessage('Agent3', 'TASK', ['task' => '3'], $fromAgent);

        $this->assertCount(1, $this->bus->receiveMessages('Agent1'));
        $this->assertCount(1, $this->bus->receiveMessages('Agent2'));
        $this->assertCount(1, $this->bus->receiveMessages('Agent3'));
    }

    /** @test */
    public function it_maintains_message_isolation_between_agents(): void
    {
        $this->bus->sendMessage('Agent1', 'TASK', ['data' => 'for_agent1'], 'Orchestrator');
        $this->bus->sendMessage('Agent2', 'TASK', ['data' => 'for_agent2'], 'Orchestrator');

        $agent1Messages = $this->bus->receiveMessages('Agent1');
        $agent2Messages = $this->bus->receiveMessages('Agent2');

        $this->assertCount(1, $agent1Messages);
        $this->assertCount(1, $agent2Messages);
        $this->assertEquals('for_agent1', $agent1Messages[0]['payload']['data']);
        $this->assertEquals('for_agent2', $agent2Messages[0]['payload']['data']);
    }

    /** @test */
    public function it_handles_empty_queue_gracefully(): void
    {
        $messages = $this->bus->receiveMessages('NonExistentAgent');

        $this->assertIsArray($messages);
        $this->assertCount(0, $messages);
    }

    /** @test */
    public function it_handles_complex_payload_data(): void
    {
        $complexPayload = [
            'case_id' => 'case-123',
            'analysis' => [
                'findings' => ['finding1', 'finding2'],
                'score' => 85.5,
                'metadata' => [
                    'timestamp' => '2025-01-15 10:30:00',
                    'agent' => 'AnalysisAgent',
                ],
            ],
            'recommendations' => ['rec1', 'rec2', 'rec3'],
        ];

        $messageId = $this->bus->sendMessage('Orchestrator', 'ANALYSIS_RESULT', $complexPayload, 'AnalysisAgent');

        $messages = $this->bus->receiveMessages('Orchestrator');
        $this->assertCount(1, $messages);
        $this->assertEquals($complexPayload, $messages[0]['payload']);
    }

    /** @test */
    public function it_preserves_message_order_with_same_priority(): void
    {
        $toAgent = 'Orchestrator';

        // Send 5 messages with the same priority
        $this->bus->sendMessage($toAgent, 'MSG_1', ['order' => 1], 'Agent1', priority: 5);
        $this->bus->sendMessage($toAgent, 'MSG_2', ['order' => 2], 'Agent1', priority: 5);
        $this->bus->sendMessage($toAgent, 'MSG_3', ['order' => 3], 'Agent1', priority: 5);

        $messages = $this->bus->receiveMessages($toAgent);

        // Should maintain FIFO order for same priority
        $this->assertEquals('MSG_1', $messages[0]['message_type']);
        $this->assertEquals('MSG_2', $messages[1]['message_type']);
        $this->assertEquals('MSG_3', $messages[2]['message_type']);
    }

    /** @test */
    public function it_handles_mixed_priority_messages(): void
    {
        $toAgent = 'Orchestrator';

        // Send messages in random order with different priorities
        $this->bus->sendMessage($toAgent, 'LOW', [], 'A1', priority: 1);
        $this->bus->sendMessage($toAgent, 'CRITICAL', [], 'A2', priority: 10);
        $this->bus->sendMessage($toAgent, 'MEDIUM', [], 'A3', priority: 5);
        $this->bus->sendMessage($toAgent, 'HIGH', [], 'A4', priority: 8);
        $this->bus->sendMessage($toAgent, 'VERY_LOW', [], 'A5', priority: 2);

        $messages = $this->bus->receiveMessages($toAgent);

        $this->assertEquals('CRITICAL', $messages[0]['message_type']); // priority 10
        $this->assertEquals('HIGH', $messages[1]['message_type']); // priority 8
        $this->assertEquals('MEDIUM', $messages[2]['message_type']); // priority 5
        $this->assertEquals('VERY_LOW', $messages[3]['message_type']); // priority 2
        $this->assertEquals('LOW', $messages[4]['message_type']); // priority 1
    }

    /** @test */
    public function it_acknowledges_multiple_messages(): void
    {
        $toAgent = 'Orchestrator';

        $id1 = $this->bus->sendMessage($toAgent, 'MSG_1', [], 'A1');
        $id2 = $this->bus->sendMessage($toAgent, 'MSG_2', [], 'A2');
        $id3 = $this->bus->sendMessage($toAgent, 'MSG_3', [], 'A3');

        $this->assertCount(3, $this->bus->receiveMessages($toAgent));

        $this->bus->acknowledgeMessage($id1);
        $this->assertCount(2, $this->bus->receiveMessages($toAgent));

        $this->bus->acknowledgeMessage($id2);
        $this->assertCount(1, $this->bus->receiveMessages($toAgent));

        $this->bus->acknowledgeMessage($id3);
        $this->assertCount(0, $this->bus->receiveMessages($toAgent));
    }

    /** @test */
    public function it_handles_acknowledge_of_nonexistent_message(): void
    {
        // Use a valid UUID format that doesn't exist in the database
        $result = $this->bus->acknowledgeMessage('00000000-0000-0000-0000-000000000000');

        $this->assertFalse($result);
    }

    /** @test */
    public function it_does_not_return_processed_messages(): void
    {
        $toAgent = 'Orchestrator';

        $id = $this->bus->sendMessage($toAgent, 'TASK', [], 'Agent1');

        $this->assertCount(1, $this->bus->receiveMessages($toAgent));

        $this->bus->acknowledgeMessage($id);

        $this->assertCount(0, $this->bus->receiveMessages($toAgent));
    }

    /** @test */
    public function it_tracks_retry_count_accurately(): void
    {
        $messageId = $this->bus->sendMessage('Agent1', 'TASK', [], 'Orchestrator');

        $this->assertEquals(0, $this->bus->getRetryCount($messageId));

        $this->bus->markMessageAsFailed($messageId);
        $this->assertEquals(1, $this->bus->getRetryCount($messageId));

        $this->bus->markMessageAsFailed($messageId);
        $this->assertEquals(2, $this->bus->getRetryCount($messageId));

        $this->bus->markMessageAsFailed($messageId);
        $this->assertEquals(3, $this->bus->getRetryCount($messageId));
    }

    /** @test */
    public function it_returns_zero_retry_count_for_nonexistent_message(): void
    {
        // Use a valid UUID format that doesn't exist in the database
        $retryCount = $this->bus->getRetryCount('00000000-0000-0000-0000-000000000000');

        $this->assertEquals(0, $retryCount);
    }

    /** @test */
    public function it_marks_message_as_failed_after_max_retries(): void
    {
        $toAgent = 'Agent1';
        $messageId = $this->bus->sendMessage($toAgent, 'TASK', [], 'Orchestrator');

        // Message should be pending initially
        $this->assertCount(1, $this->bus->receiveMessages($toAgent));

        // Retry 3 times
        $this->bus->markMessageAsFailed($messageId);
        $this->bus->markMessageAsFailed($messageId);
        $this->bus->markMessageAsFailed($messageId);

        // After 3 retries, message should be marked as 'failed' and not returned
        $this->assertCount(0, $this->bus->receiveMessages($toAgent));
        $this->assertEquals(3, $this->bus->getRetryCount($messageId));
    }

    /** @test */
    public function it_handles_concurrent_message_retrieval(): void
    {
        $toAgent = 'Orchestrator';

        // Send multiple messages
        for ($i = 1; $i <= 10; $i++) {
            $this->bus->sendMessage($toAgent, "MSG_$i", ['index' => $i], 'Agent1');
        }

        // Simulate two agents retrieving messages concurrently
        $messages1 = $this->bus->receiveMessages($toAgent);
        $messages2 = $this->bus->receiveMessages($toAgent);

        // Both should get all 10 messages (since no acknowledgment)
        $this->assertCount(10, $messages1);
        $this->assertCount(10, $messages2);
    }

    /** @test */
    public function it_handles_partial_acknowledgment(): void
    {
        $toAgent = 'Orchestrator';

        $id1 = $this->bus->sendMessage($toAgent, 'MSG_1', [], 'A1', priority: 10);
        $id2 = $this->bus->sendMessage($toAgent, 'MSG_2', [], 'A2', priority: 5);
        $id3 = $this->bus->sendMessage($toAgent, 'MSG_3', [], 'A3', priority: 1);

        // Acknowledge only the high priority message
        $this->bus->acknowledgeMessage($id1);

        $messages = $this->bus->receiveMessages($toAgent);

        $this->assertCount(2, $messages);
        $this->assertEquals('MSG_2', $messages[0]['message_type']);
        $this->assertEquals('MSG_3', $messages[1]['message_type']);
    }

    /** @test */
    public function it_supports_different_message_types(): void
    {
        $toAgent = 'Orchestrator';

        $this->bus->sendMessage($toAgent, 'TASK_REQUEST', [], 'A1');
        $this->bus->sendMessage($toAgent, 'STATUS_UPDATE', [], 'A2');
        $this->bus->sendMessage($toAgent, 'ERROR_REPORT', [], 'A3');
        $this->bus->sendMessage($toAgent, 'COMPLETION_NOTICE', [], 'A4');

        $messages = $this->bus->receiveMessages($toAgent);

        $this->assertCount(4, $messages);
        $types = array_column($messages, 'message_type');
        $this->assertContains('TASK_REQUEST', $types);
        $this->assertContains('STATUS_UPDATE', $types);
        $this->assertContains('ERROR_REPORT', $types);
        $this->assertContains('COMPLETION_NOTICE', $types);
    }

    /** @test */
    public function it_returns_message_metadata(): void
    {
        $messageId = $this->bus->sendMessage('Agent1', 'TASK', ['data' => 'test'], 'Orchestrator', priority: 7);

        $messages = $this->bus->receiveMessages('Agent1');

        $this->assertCount(1, $messages);
        $message = $messages[0];

        $this->assertArrayHasKey('id', $message);
        $this->assertArrayHasKey('from_agent', $message);
        $this->assertArrayHasKey('message_type', $message);
        $this->assertArrayHasKey('payload', $message);
        $this->assertArrayHasKey('priority', $message);
        $this->assertArrayHasKey('created_at', $message);

        $this->assertEquals($messageId, $message['id']);
        $this->assertEquals('Orchestrator', $message['from_agent']);
        $this->assertEquals(7, $message['priority']);
    }

    /** @test */
    public function it_handles_retry_boundary_conditions(): void
    {
        $messageId = $this->bus->sendMessage('Agent1', 'TASK', [], 'Orchestrator');

        // Test boundary: retry_count = 0
        $this->assertTrue($this->bus->canRetry($messageId));

        // Test boundary: retry_count = 1
        $this->bus->markMessageAsFailed($messageId);
        $this->assertTrue($this->bus->canRetry($messageId));

        // Test boundary: retry_count = 2
        $this->bus->markMessageAsFailed($messageId);
        $this->assertTrue($this->bus->canRetry($messageId));

        // Test boundary: retry_count = 3 (max)
        $this->bus->markMessageAsFailed($messageId);
        $this->assertFalse($this->bus->canRetry($messageId));
    }

    /** @test */
    public function it_validates_message_sending_returns_valid_uuid(): void
    {
        $messageId = $this->bus->sendMessage('Agent1', 'TASK', [], 'Orchestrator');

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $messageId,
            'Message ID should be a valid UUID'
        );
    }

    /** @test */
    public function it_handles_message_workflow_end_to_end(): void
    {
        // Simulate complete workflow: send -> receive -> process -> acknowledge
        $messageId = $this->bus->sendMessage(
            'AnalysisAgent',
            'ANALYZE_CASE',
            ['case_id' => 'case-123'],
            'OrchestratorAgent',
            priority: 8
        );

        // Agent receives message
        $messages = $this->bus->receiveMessages('AnalysisAgent');
        $this->assertCount(1, $messages);

        // Agent processes message
        $message = $messages[0];
        $this->assertEquals('ANALYZE_CASE', $message['message_type']);
        $this->assertEquals('case-123', $message['payload']['case_id']);

        // Agent acknowledges completion
        $this->bus->acknowledgeMessage($messageId);

        // Message no longer available
        $this->assertCount(0, $this->bus->receiveMessages('AnalysisAgent'));
    }

    /** @test */
    public function it_sends_message_in_less_than_10ms(): void
    {
        $iterations = 10;
        $totalTime = 0;

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);

            $this->bus->sendMessage(
                'TestAgent',
                'PERFORMANCE_TEST',
                ['iteration' => $i],
                'Orchestrator'
            );

            $endTime = microtime(true);
            $totalTime += ($endTime - $startTime) * 1000; // Convert to milliseconds
        }

        $averageTime = $totalTime / $iterations;

        $this->assertLessThan(
            10,
            $averageTime,
            "Average message send time ({$averageTime}ms) should be less than 10ms"
        );
    }
}
