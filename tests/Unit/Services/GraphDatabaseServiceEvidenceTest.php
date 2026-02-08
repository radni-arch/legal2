<?php

namespace Tests\Unit\Services;

use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

class GraphDatabaseServiceEvidenceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_evidence_for_decision_with_correct_structure(): void
    {
        $service = $this->createPartialMock(GraphDatabaseService::class, ['run', 'isAvailable']);

        $service->expects($this->once())
            ->method('isAvailable')
            ->willReturn(true);

        $mockResults = collect([
            [
                'e' => [
                    'id' => 'ev-1',
                    'evidence_type' => 'documentary',
                    'description' => 'Contract document',
                    'admitted' => true,
                    'weight' => 'high',
                ],
                'r' => ['ruling' => 'admitted'],
            ],
            [
                'e' => [
                    'id' => 'ev-2',
                    'evidence_type' => 'testimonial',
                    'description' => 'Witness statement',
                    'admitted' => true,
                    'weight' => 'medium',
                ],
                'r' => ['ruling' => 'admitted with limitation'],
            ],
        ]);

        $service->expects($this->once())
            ->method('run')
            ->with($this->stringContains('CONSIDERS_EVIDENCE'))
            ->willReturn($mockResults);

        $result = $service->getEvidenceForDecision('decision-123');

        $this->assertCount(2, $result);
        $this->assertEquals('documentary', $result[0]['evidence_type']);
        $this->assertEquals('Contract document', $result[0]['description']);
        $this->assertTrue($result[0]['admitted']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_evidence(): void
    {
        $service = $this->createPartialMock(GraphDatabaseService::class, ['run', 'isAvailable']);

        $service->expects($this->once())
            ->method('isAvailable')
            ->willReturn(true);

        $service->expects($this->once())
            ->method('run')
            ->willReturn(collect([]));

        $result = $service->getEvidenceForDecision('decision-999');

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

        $result = $service->getEvidenceForDecision('decision-123');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
