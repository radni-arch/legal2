<?php

namespace Tests\Unit\Services;

use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

class GraphDatabaseServiceDateEventsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_date_events_for_decision_with_correct_structure(): void
    {
        $service = $this->createPartialMock(GraphDatabaseService::class, ['run', 'isAvailable']);

        $service->expects($this->once())
            ->method('isAvailable')
            ->willReturn(true);

        $mockResults = collect([
            [
                'e' => [
                    'id' => 'event-1',
                    'date' => '2024-01-15',
                    'event_type' => 'filing',
                    'description' => 'Case filed',
                ],
            ],
            [
                'e' => [
                    'id' => 'event-2',
                    'date' => '2024-03-20',
                    'event_type' => 'judgment',
                    'description' => 'Judgment rendered',
                ],
            ],
        ]);

        $service->expects($this->once())
            ->method('run')
            ->with($this->stringContains('HAS_EVENT'))
            ->willReturn($mockResults);

        $result = $service->getDateEventsForDecision('decision-123');

        $this->assertCount(2, $result);
        $this->assertEquals('2024-01-15', $result[0]['date']);
        $this->assertEquals('filing', $result[0]['event_type']);
        $this->assertEquals('Case filed', $result[0]['description']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_events(): void
    {
        $service = $this->createPartialMock(GraphDatabaseService::class, ['run', 'isAvailable']);

        $service->expects($this->once())
            ->method('isAvailable')
            ->willReturn(true);

        $service->expects($this->once())
            ->method('run')
            ->willReturn(collect([]));

        $result = $service->getDateEventsForDecision('decision-999');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function it_handles_neo4j_unavailable_gracefully(): void
    {
        $service = $this->createPartialMock(GraphDatabaseService::class, ['isAvailable']);

        $service->expects($this->once())
            ->method('isAvailable')
            ->willReturn(false);

        $result = $service->getDateEventsForDecision('decision-123');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
