<?php

namespace Tests\Unit\Agents;

use App\Services\AgentCommunicationBus;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AgentCommunicationBusTest extends TestCase
{
    use UsesTestDatabase;

    private AgentCommunicationBus $bus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bus = app(AgentCommunicationBus::class);
    }

    /** @test */
    public function it_sends_message_from_agent_a_to_agent_b(): void
    {
        // Arrange
        $fromAgent = 'DecisionDiscoveryAgent';
        $toAgent = 'ResearchAgent';
        $messageType = 'REQUEST_ANALYSIS';
        $payload = [
            'case_id' => 'case-123',
            'question' => 'Find similar cases about proportionality',
        ];

        // Act
        $messageId = $this->bus->sendMessage($toAgent, $messageType, $payload, $fromAgent);

        // Assert
        $this->assertNotNull($messageId);
        $this->assertIsString($messageId);
    }

    /** @test */
    public function it_retrieves_messages_in_priority_order(): void
    {
        // Arrange - Send messages with different priorities (higher number = higher priority)
        $toAgent = 'ResearchAgent';

        $this->bus->sendMessage($toAgent, 'LOW_PRIORITY', ['task' => 'low'], 'Agent1', priority: 3);
        $this->bus->sendMessage($toAgent, 'HIGH_PRIORITY', ['task' => 'high'], 'Agent2', priority: 10);
        $this->bus->sendMessage($toAgent, 'MEDIUM_PRIORITY', ['task' => 'medium'], 'Agent3', priority: 7);

        // Act - Retrieve messages for ResearchAgent
        $messages = $this->bus->receiveMessages($toAgent);

        // Assert - Messages should be ordered by priority (high to low)
        $this->assertCount(3, $messages);
        $this->assertEquals('HIGH_PRIORITY', $messages[0]['message_type']);
        $this->assertEquals('MEDIUM_PRIORITY', $messages[1]['message_type']);
        $this->assertEquals('LOW_PRIORITY', $messages[2]['message_type']);
    }

    /** @test */
    public function it_acknowledges_message_to_prevent_duplicate_processing(): void
    {
        // Arrange - Send a message
        $toAgent = 'ResearchAgent';
        $messageId = $this->bus->sendMessage($toAgent, 'TASK', ['data' => 'test'], 'Agent1');

        // Act - Retrieve messages (should get 1)
        $messagesBefore = $this->bus->receiveMessages($toAgent);
        $this->assertCount(1, $messagesBefore);

        // Acknowledge the message
        $this->bus->acknowledgeMessage($messageId);

        // Act - Retrieve messages again
        $messagesAfter = $this->bus->receiveMessages($toAgent);

        // Assert - No messages should be returned (acknowledged message excluded)
        $this->assertCount(0, $messagesAfter);
    }

    /** @test */
    public function it_retries_failed_messages_up_to_three_times(): void
    {
        // Arrange - Send a message
        $toAgent = 'ResearchAgent';
        $messageId = $this->bus->sendMessage($toAgent, 'TASK', ['data' => 'test'], 'Agent1');

        // Act & Assert - Retry 1
        $this->bus->markMessageAsFailed($messageId);
        $retryCount = $this->bus->getRetryCount($messageId);
        $this->assertEquals(1, $retryCount);
        $this->assertTrue($this->bus->canRetry($messageId));

        // Act & Assert - Retry 2
        $this->bus->markMessageAsFailed($messageId);
        $retryCount = $this->bus->getRetryCount($messageId);
        $this->assertEquals(2, $retryCount);
        $this->assertTrue($this->bus->canRetry($messageId));

        // Act & Assert - Retry 3
        $this->bus->markMessageAsFailed($messageId);
        $retryCount = $this->bus->getRetryCount($messageId);
        $this->assertEquals(3, $retryCount);
        $this->assertFalse($this->bus->canRetry($messageId), 'Should not retry after 3 attempts');
    }
}
