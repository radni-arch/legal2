<?php

namespace Tests\Unit\Events\Graph;

use App\Events\Graph\GraphDataUpdated;
use Illuminate\Broadcasting\Channel;
use Tests\TestCase;

class GraphDataUpdatedTest extends TestCase
{
    public function test_event_can_be_instantiated_with_required_parameters(): void
    {
        $event = new GraphDataUpdated(
            updateType: 'sync_complete',
            nodesAffected: 10,
            relationshipsAffected: 20,
            decisionId: 'decision-123',
            message: 'Custom message'
        );

        $this->assertEquals('sync_complete', $event->updateType);
        $this->assertEquals(10, $event->nodesAffected);
        $this->assertEquals(20, $event->relationshipsAffected);
        $this->assertEquals('decision-123', $event->decisionId);
        $this->assertEquals('Custom message', $event->message);
    }

    public function test_generate_message_produces_correct_messages_for_each_update_type(): void
    {
        $syncEvent = new GraphDataUpdated(
            updateType: 'sync_complete',
            nodesAffected: 10,
            relationshipsAffected: 20
        );
        $this->assertEquals(
            'Graph sync complete: 10 nodes, 20 relationships',
            $syncEvent->message
        );

        $decisionEvent = new GraphDataUpdated(
            updateType: 'new_decision',
            nodesAffected: 1,
            relationshipsAffected: 0
        );
        $this->assertEquals(
            'New decision added to graph',
            $decisionEvent->message
        );

        $relationshipsEvent = new GraphDataUpdated(
            updateType: 'new_relationships',
            nodesAffected: 0,
            relationshipsAffected: 5
        );
        $this->assertEquals(
            '5 new relationships discovered',
            $relationshipsEvent->message
        );

        $defaultEvent = new GraphDataUpdated(
            updateType: 'unknown_type',
            nodesAffected: 0,
            relationshipsAffected: 0
        );
        $this->assertEquals(
            'Graph data updated',
            $defaultEvent->message
        );
    }

    public function test_broadcast_on_returns_correct_channel(): void
    {
        $event = new GraphDataUpdated(
            updateType: 'sync_complete',
            nodesAffected: 10,
            relationshipsAffected: 20
        );

        $channels = $event->broadcastOn();

        $this->assertIsArray($channels);
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertEquals('graph-updates', $channels[0]->name);
    }

    public function test_broadcast_as_returns_graph_updated(): void
    {
        $event = new GraphDataUpdated(
            updateType: 'sync_complete',
            nodesAffected: 10,
            relationshipsAffected: 20
        );

        $this->assertEquals('graph.updated', $event->broadcastAs());
    }

    public function test_broadcast_with_returns_expected_payload_structure(): void
    {
        $event = new GraphDataUpdated(
            updateType: 'sync_complete',
            nodesAffected: 10,
            relationshipsAffected: 20,
            decisionId: 'decision-123',
            message: 'Test message'
        );

        $payload = $event->broadcastWith();

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('type', $payload);
        $this->assertArrayHasKey('nodes', $payload);
        $this->assertArrayHasKey('relationships', $payload);
        $this->assertArrayHasKey('decisionId', $payload);
        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayHasKey('timestamp', $payload);

        $this->assertEquals('sync_complete', $payload['type']);
        $this->assertEquals(10, $payload['nodes']);
        $this->assertEquals(20, $payload['relationships']);
        $this->assertEquals('decision-123', $payload['decisionId']);
        $this->assertEquals('Test message', $payload['message']);
        $this->assertNotEmpty($payload['timestamp']);
    }
}
