<?php

namespace Tests\Unit\Services;

use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

class GraphDatabaseServiceArgumentsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_arguments_for_decision_with_correct_structure(): void
    {
        $service = $this->createPartialMock(GraphDatabaseService::class, ['run', 'isAvailable']);

        $mockResults = collect([
            [
                'a' => [
                    'id' => 'arg-1',
                    'argument_type' => 'plaintiff',
                    'summary' => 'Plaintiff claims damages',
                    'full_text' => 'Full argument text...',
                    'accepted' => true,
                ],
                'r' => ['sequence' => 1],
            ],
            [
                'a' => [
                    'id' => 'arg-2',
                    'argument_type' => 'defendant',
                    'summary' => 'Defendant denies liability',
                    'full_text' => 'Defense text...',
                    'accepted' => false,
                ],
                'r' => ['sequence' => 2],
            ],
        ]);

        $service->expects($this->once())
            ->method('isAvailable')
            ->willReturn(true);

        $service->expects($this->once())
            ->method('run')
            ->with($this->stringContains('CONTAINS_ARGUMENT'))
            ->willReturn($mockResults);

        $result = $service->getArgumentsForDecision('decision-123');

        $this->assertCount(2, $result);
        $this->assertEquals('plaintiff', $result[0]['party_type']);
        $this->assertEquals('Plaintiff claims damages', $result[0]['content']);
        $this->assertTrue($result[0]['accepted']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_arguments(): void
    {
        $service = $this->createPartialMock(GraphDatabaseService::class, ['run', 'isAvailable']);

        $service->expects($this->once())
            ->method('isAvailable')
            ->willReturn(true);

        $service->expects($this->once())
            ->method('run')
            ->willReturn(collect([]));

        $result = $service->getArgumentsForDecision('decision-999');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function it_handles_neo4j_unavailable_gracefully(): void
    {
        $service = $this->createPartialMock(GraphDatabaseService::class, ['run', 'isAvailable']);

        $service->expects($this->once())
            ->method('isAvailable')
            ->willReturn(false);

        $result = $service->getArgumentsForDecision('decision-123');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
