<?php

namespace Tests\Unit\Services\LegalReasoning;

use App\Models\LegalCase;
use App\Services\LegalReasoning\DurationEstimator;
use App\Services\LegalReasoning\FeatureExtractor;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DurationEstimatorTest extends TestCase
{
    use UsesTestDatabase;

    protected DurationEstimator $durationEstimator;

    protected $featureExtractorMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->featureExtractorMock = Mockery::mock(FeatureExtractor::class);

        $this->durationEstimator = new DurationEstimator(
            $this->featureExtractorMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_estimates_duration_successfully()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'court' => 'Commercial Court',
            'jurisdiction' => 'HR',
            'filing_date' => now()->subMonths(2),
        ]);

        // Create historical cases
        for ($i = 0; $i < 10; $i++) {
            LegalCase::factory()->create([
                'court' => 'Commercial Court',
                'jurisdiction' => 'HR',
                'filing_date' => now()->subYears(2),
                'status' => 'closed',
            ]);
        }

        $this->featureExtractorMock
            ->shouldReceive('getCaseFeatures')
            ->once()
            ->andReturn([
                'case_type' => 'civil',
                'complexity_score' => 0.6,
                'document_count' => 15,
                'party_count' => 2,
            ]);

        // Act
        $result = $this->durationEstimator->estimateDuration($case->id);

        // Assert
        $this->assertArrayHasKey('estimated_days', $result);
        $this->assertArrayHasKey('estimated_completion_date', $result);
        $this->assertArrayHasKey('confidence_interval', $result);
        $this->assertArrayHasKey('milestones', $result);
        $this->assertArrayHasKey('factors', $result);
        $this->assertArrayHasKey('case_features', $result);

        $this->assertGreaterThan(0, $result['estimated_days']);
        $this->assertNotEmpty($result['milestones']);
    }

    /** @test */
    public function it_calculates_confidence_interval()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'filing_date' => now(),
        ]);

        $this->featureExtractorMock
            ->shouldReceive('getCaseFeatures')
            ->once()
            ->andReturn([
                'case_type' => 'civil',
                'complexity_score' => 0.5,
                'document_count' => 10,
                'party_count' => 2,
            ]);

        // Act
        $result = $this->durationEstimator->estimateDuration($case->id);

        // Assert
        $this->assertArrayHasKey('min_days', $result['confidence_interval']);
        $this->assertArrayHasKey('max_days', $result['confidence_interval']);
        $this->assertArrayHasKey('min_date', $result['confidence_interval']);
        $this->assertArrayHasKey('max_date', $result['confidence_interval']);

        // Min should be ~70% of estimate
        $this->assertEquals(
            round($result['estimated_days'] * 0.7),
            $result['confidence_interval']['min_days']
        );

        // Max should be ~150% of estimate
        $this->assertEquals(
            round($result['estimated_days'] * 1.5),
            $result['confidence_interval']['max_days']
        );
    }

    /** @test */
    public function it_generates_milestones()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'filing_date' => now(),
        ]);

        $this->featureExtractorMock
            ->shouldReceive('getCaseFeatures')
            ->once()
            ->andReturn([
                'case_type' => 'civil',
                'complexity_score' => 0.5,
                'document_count' => 10,
                'party_count' => 2,
            ]);

        // Act
        $result = $this->durationEstimator->estimateDuration($case->id);

        // Assert
        $this->assertNotEmpty($result['milestones']);
        $this->assertArrayHasKey('phase', $result['milestones'][0]);
        $this->assertArrayHasKey('estimated_date', $result['milestones'][0]);
        $this->assertArrayHasKey('days_from_filing', $result['milestones'][0]);
    }

    /** @test */
    public function it_adjusts_for_case_complexity()
    {
        // Arrange
        $simpleCase = LegalCase::factory()->create();
        $complexCase = LegalCase::factory()->create();

        $this->featureExtractorMock
            ->shouldReceive('getCaseFeatures')
            ->once()
            ->andReturn([
                'case_type' => 'civil',
                'complexity_score' => 0.2, // Simple
                'document_count' => 3,
                'party_count' => 2,
            ]);

        $simpleResult = $this->durationEstimator->estimateDuration($simpleCase->id);

        $this->featureExtractorMock
            ->shouldReceive('getCaseFeatures')
            ->once()
            ->andReturn([
                'case_type' => 'civil',
                'complexity_score' => 0.9, // Complex
                'document_count' => 50,
                'party_count' => 10,
            ]);

        $complexResult = $this->durationEstimator->estimateDuration($complexCase->id);

        // Assert
        $this->assertLessThan(
            $complexResult['estimated_days'],
            $simpleResult['estimated_days']
        );
    }

    /** @test */
    public function it_includes_factors_breakdown()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        $this->featureExtractorMock
            ->shouldReceive('getCaseFeatures')
            ->once()
            ->andReturn([
                'case_type' => 'civil',
                'complexity_score' => 0.5,
                'document_count' => 10,
                'party_count' => 2,
            ]);

        // Act
        $result = $this->durationEstimator->estimateDuration($case->id);

        // Assert
        $this->assertArrayHasKey('baseline_days', $result['factors']);
        $this->assertArrayHasKey('complexity_multiplier', $result['factors']);
        $this->assertArrayHasKey('backlog_delay_days', $result['factors']);
        $this->assertArrayHasKey('historical_cases_analyzed', $result['factors']);
    }

    /** @test */
    public function it_uses_historical_data_from_similar_cases()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'court' => 'Commercial Court',
            'jurisdiction' => 'HR',
        ]);

        // Create 5 historical similar cases
        for ($i = 0; $i < 5; $i++) {
            LegalCase::factory()->create([
                'court' => 'Commercial Court',
                'jurisdiction' => 'HR',
                'status' => 'closed',
                'filing_date' => now()->subYears(2),
            ]);
        }

        $this->featureExtractorMock
            ->shouldReceive('getCaseFeatures')
            ->once()
            ->andReturn([
                'case_type' => 'civil',
                'complexity_score' => 0.5,
                'document_count' => 10,
                'party_count' => 2,
            ]);

        // Act
        $result = $this->durationEstimator->estimateDuration($case->id);

        // Assert
        $this->assertGreaterThan(0, $result['factors']['historical_cases_analyzed']);
    }

    /** @test */
    public function it_handles_case_with_no_historical_data()
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'court' => 'Rare Court',
            'jurisdiction' => 'XX',
            'status' => 'active', // Ensure it's not 'closed' so it doesn't appear in historical search
        ]);

        $this->featureExtractorMock
            ->shouldReceive('getCaseFeatures')
            ->once()
            ->andReturn([
                'case_type' => 'civil',
                'complexity_score' => 0.5,
                'document_count' => 10,
                'party_count' => 2,
            ]);

        // Act
        $result = $this->durationEstimator->estimateDuration($case->id);

        // Assert - Should still provide estimate even without historical data
        $this->assertGreaterThan(0, $result['estimated_days']);
        $this->assertEquals(0, $result['factors']['historical_cases_analyzed']);
    }

    /** @test */
    public function it_throws_exception_for_invalid_case()
    {
        // Arrange
        $invalidCaseId = 'non-existent-case-id';

        $this->featureExtractorMock
            ->shouldNotReceive('getCaseFeatures');

        // Act & Assert
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->durationEstimator->estimateDuration($invalidCaseId);
    }
}
