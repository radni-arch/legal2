<?php

namespace Tests\Feature;

use App\Events\Graph\GraphDataUpdated;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GraphNotificationTest extends TestCase
{
    /** @test */
    public function it_broadcasts_graph_updated_event(): void
    {
        Event::fake();

        event(new GraphDataUpdated(
            updateType: 'sync_complete',
            nodesAffected: 10,
            relationshipsAffected: 25
        ));

        Event::assertDispatched(GraphDataUpdated::class, function ($event) {
            return $event->updateType === 'sync_complete'
                && $event->nodesAffected === 10
                && $event->relationshipsAffected === 25;
        });
    }

    /** @test */
    public function it_generates_appropriate_message_for_sync_complete(): void
    {
        $event = new GraphDataUpdated(
            updateType: 'sync_complete',
            nodesAffected: 10,
            relationshipsAffected: 25
        );

        $this->assertStringContainsString('10 nodes', $event->message);
        $this->assertStringContainsString('25 relationships', $event->message);
    }

    /** @test */
    public function it_broadcasts_on_graph_updates_channel(): void
    {
        $event = new GraphDataUpdated(
            updateType: 'new_decision',
            nodesAffected: 1,
            relationshipsAffected: 5
        );

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertEquals('graph-updates', $channels[0]->name);
    }

    /** @test */
    public function it_uses_correct_broadcast_name(): void
    {
        $event = new GraphDataUpdated(
            updateType: 'new_decision',
            nodesAffected: 1,
            relationshipsAffected: 5
        );

        $this->assertEquals('graph.updated', $event->broadcastAs());
    }

    /** @test */
    public function it_includes_timestamp_in_broadcast_data(): void
    {
        $event = new GraphDataUpdated(
            updateType: 'sync_complete',
            nodesAffected: 10,
            relationshipsAffected: 25
        );

        $data = $event->broadcastWith();

        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('message', $data);
    }
}
