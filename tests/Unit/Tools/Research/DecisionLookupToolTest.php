<?php

namespace Tests\Unit\Tools\Research;

use App\Services\DecisionSearchService;
use App\Tools\Research\DecisionLookupTool;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

class DecisionLookupToolTest extends TestCase
{
    #[Test]
    public function it_has_correct_definition(): void
    {
        $mockDecisionSearch = Mockery::mock(DecisionSearchService::class);
        $tool = new DecisionLookupTool($mockDecisionSearch);

        $definition = $tool->definition();

        $this->assertEquals('decision_lookup', $definition['name']);
        $this->assertStringContainsString('lookup', strtolower($definition['description']));
        $this->assertArrayHasKey('parameters', $definition);
        $this->assertArrayHasKey('properties', $definition['parameters']);
    }

    #[Test]
    public function it_executes_lookup_by_case_number(): void
    {
        $mockDecisionSearch = Mockery::mock(DecisionSearchService::class);
        $mockDecisionSearch->shouldReceive('lookupByCriteria')
            ->once()
            ->with(Mockery::on(function ($criteria) {
                return $criteria['case_number'] === 'U-I-123/2024';
            }))
            ->andReturn([
                [
                    'id' => 1,
                    'case_number' => 'U-I-123/2024',
                    'title' => 'Constitutional Review Case',
                    'court' => 'Ustavni sud',
                    'jurisdiction' => 'constitutional',
                    'decision_date' => '2024-05-15',
                ],
            ]);

        $tool = new DecisionLookupTool($mockDecisionSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['case_number' => 'U-I-123/2024'],
            $context,
            $memory
        );

        $resultData = json_decode($result, true);

        $this->assertTrue($resultData['success']);
        $this->assertArrayHasKey('data', $resultData);
        $this->assertCount(1, $resultData['data']);
        $this->assertEquals('U-I-123/2024', $resultData['data'][0]['case_number']);
    }

    #[Test]
    public function it_executes_lookup_by_court_and_jurisdiction(): void
    {
        $mockDecisionSearch = Mockery::mock(DecisionSearchService::class);
        $mockDecisionSearch->shouldReceive('lookupByCriteria')
            ->once()
            ->with(Mockery::on(function ($criteria) {
                return $criteria['court'] === 'Vrhovni sud' &&
                       $criteria['jurisdiction'] === 'civil' &&
                       $criteria['limit'] === 15;
            }))
            ->andReturn([
                ['id' => 1, 'case_number' => 'Rev-123/2024', 'court' => 'Vrhovni sud'],
                ['id' => 2, 'case_number' => 'Rev-124/2024', 'court' => 'Vrhovni sud'],
            ]);

        $tool = new DecisionLookupTool($mockDecisionSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            [
                'court' => 'Vrhovni sud',
                'jurisdiction' => 'civil',
                'limit' => 15,
            ],
            $context,
            $memory
        );

        $resultData = json_decode($result, true);

        $this->assertTrue($resultData['success']);
        $this->assertCount(2, $resultData['data']);
    }

    #[Test]
    public function it_executes_lookup_by_date_range(): void
    {
        $mockDecisionSearch = Mockery::mock(DecisionSearchService::class);
        $mockDecisionSearch->shouldReceive('lookupByCriteria')
            ->once()
            ->with(Mockery::on(function ($criteria) {
                return $criteria['from_date'] === '2024-01-01' &&
                       $criteria['to_date'] === '2024-12-31';
            }))
            ->andReturn([
                ['id' => 1, 'decision_date' => '2024-06-15'],
                ['id' => 2, 'decision_date' => '2024-08-20'],
                ['id' => 3, 'decision_date' => '2024-11-05'],
            ]);

        $tool = new DecisionLookupTool($mockDecisionSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            [
                'from_date' => '2024-01-01',
                'to_date' => '2024-12-31',
            ],
            $context,
            $memory
        );

        $resultData = json_decode($result, true);

        $this->assertTrue($resultData['success']);
        $this->assertCount(3, $resultData['data']);
    }

    #[Test]
    public function it_handles_lookup_errors_gracefully(): void
    {
        $mockDecisionSearch = Mockery::mock(DecisionSearchService::class);
        $mockDecisionSearch->shouldReceive('lookupByCriteria')
            ->once()
            ->andThrow(new \Exception('Database query failed'));

        $tool = new DecisionLookupTool($mockDecisionSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['case_number' => 'U-I-999/2024'],
            $context,
            $memory
        );

        $resultData = json_decode($result, true);

        $this->assertFalse($resultData['success']);
        $this->assertArrayHasKey('error', $resultData);
        $this->assertStringContainsString('Database query failed', $resultData['error']);
    }

    #[Test]
    public function it_returns_empty_results_when_no_matches(): void
    {
        $mockDecisionSearch = Mockery::mock(DecisionSearchService::class);
        $mockDecisionSearch->shouldReceive('lookupByCriteria')
            ->once()
            ->andReturn([]);

        $tool = new DecisionLookupTool($mockDecisionSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['case_number' => 'NONEXISTENT'],
            $context,
            $memory
        );

        $resultData = json_decode($result, true);

        $this->assertTrue($resultData['success']);
        $this->assertArrayHasKey('data', $resultData);
        $this->assertCount(0, $resultData['data']);
    }
}
