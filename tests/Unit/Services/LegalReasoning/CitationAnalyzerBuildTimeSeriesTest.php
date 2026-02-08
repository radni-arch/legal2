<?php

namespace Tests\Unit\Services\LegalReasoning;

use App\Models\CitationTimeSeries;
use App\Models\CourtDecision;
use App\Services\GraphDatabaseService;
use App\Services\LegalReasoning\CitationAnalyzer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * Citation Analyzer - Build Time Series Tests
 *
 * Tests the buildCitationTimeSeries() method that aggregates
 * citation time series data for inclusion in impact metrics.
 */
class CitationAnalyzerBuildTimeSeriesTest extends TestCase
{
    use DatabaseTransactions;

    protected CitationAnalyzer $analyzer;

    protected $graphDbMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock external APIs for offline testing
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
            ], 200),
        ]);

        $this->graphDbMock = Mockery::mock(GraphDatabaseService::class);
        $this->analyzer = new CitationAnalyzer($this->graphDbMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function test_builds_citation_time_series_from_existing_records(): void
    {
        // Arrange: Create a decision
        $decision = CourtDecision::factory()->create([
            'case_number' => 'TEST-TIME-SERIES/2025',
            'court' => 'Županijski sud u Osijeku',
        ]);

        // Create time series records at different dates
        // Month 1: 5 citations
        CitationTimeSeries::create([
            'decision_id' => $decision->id,
            'period_start' => now()->subMonths(2)->startOfMonth(),
            'period_end' => now()->subMonths(2)->endOfMonth(),
            'period_type' => 'monthly',
            'citation_count' => 5,
            'incoming_citations' => 5,
            'outgoing_citations' => 0,
            'avg_citation_importance' => 0.750,
            'citing_courts' => ['Općinski sud u Osijeku'],
            'top_citing_decisions' => ['dec-001', 'dec-002'],
        ]);

        // Month 2: 8 citations (increasing trend)
        CitationTimeSeries::create([
            'decision_id' => $decision->id,
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
            'period_type' => 'monthly',
            'citation_count' => 8,
            'incoming_citations' => 8,
            'outgoing_citations' => 0,
            'avg_citation_importance' => 0.850,
            'citing_courts' => ['Općinski sud u Osijeku', 'Vrhovni sud RH'],
            'top_citing_decisions' => ['dec-003', 'dec-004'],
        ]);

        // Month 3: 10 citations (continuing increase)
        CitationTimeSeries::create([
            'decision_id' => $decision->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'period_type' => 'monthly',
            'citation_count' => 10,
            'incoming_citations' => 10,
            'outgoing_citations' => 0,
            'avg_citation_importance' => 0.900,
            'citing_courts' => ['Općinski sud u Osijeku', 'Vrhovni sud RH', 'Županijski sud u Zagrebu'],
            'top_citing_decisions' => ['dec-005', 'dec-006'],
        ]);

        // Act: Build time series data
        $timeSeries = $this->invokePrivateMethod(
            $this->analyzer,
            'buildCitationTimeSeries',
            [$decision->id]
        );

        // Assert: Verify the structure and content
        $this->assertIsArray($timeSeries);
        $this->assertNotEmpty($timeSeries);

        // Should have 3 monthly records
        $monthlyRecords = array_filter($timeSeries, fn ($record) => $record['period_type'] === 'monthly');
        $this->assertCount(3, $monthlyRecords);

        // Verify the records are ordered by date (most recent first)
        $this->assertEquals(10, $monthlyRecords[0]['citation_count']);
        $this->assertEquals(8, $monthlyRecords[1]['citation_count']);
        $this->assertEquals(5, $monthlyRecords[2]['citation_count']);

        // Verify trends are included
        $this->assertEquals('up_2', $monthlyRecords[0]['trend']);
        $this->assertEquals('up_3', $monthlyRecords[1]['trend']);
        $this->assertEquals('new', $monthlyRecords[2]['trend']);

        // Verify all expected fields are present
        $firstRecord = $monthlyRecords[0];
        $this->assertArrayHasKey('period_start', $firstRecord);
        $this->assertArrayHasKey('period_end', $firstRecord);
        $this->assertArrayHasKey('citation_count', $firstRecord);
        $this->assertArrayHasKey('incoming_citations', $firstRecord);
        $this->assertArrayHasKey('outgoing_citations', $firstRecord);
        $this->assertArrayHasKey('avg_citation_importance', $firstRecord);
        $this->assertArrayHasKey('citing_courts', $firstRecord);
        $this->assertArrayHasKey('top_citing_decisions', $firstRecord);
    }

    /** @test */
    public function test_builds_time_series_with_multiple_period_types(): void
    {
        // Arrange: Create decision and time series for different periods
        $decision = CourtDecision::factory()->create();

        // Weekly
        CitationTimeSeries::create([
            'decision_id' => $decision->id,
            'period_start' => now()->startOfWeek(),
            'period_end' => now()->endOfWeek(),
            'period_type' => 'weekly',
            'citation_count' => 3,
            'incoming_citations' => 3,
            'outgoing_citations' => 0,
        ]);

        // Monthly
        CitationTimeSeries::create([
            'decision_id' => $decision->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'period_type' => 'monthly',
            'citation_count' => 15,
            'incoming_citations' => 15,
            'outgoing_citations' => 0,
        ]);

        // Yearly
        CitationTimeSeries::create([
            'decision_id' => $decision->id,
            'period_start' => now()->startOfYear(),
            'period_end' => now()->endOfYear(),
            'period_type' => 'yearly',
            'citation_count' => 60,
            'incoming_citations' => 60,
            'outgoing_citations' => 0,
        ]);

        // Act
        $timeSeries = $this->invokePrivateMethod(
            $this->analyzer,
            'buildCitationTimeSeries',
            [$decision->id]
        );

        // Assert: Should include all period types
        $periodTypes = array_unique(array_column($timeSeries, 'period_type'));
        $this->assertContains('weekly', $periodTypes);
        $this->assertContains('monthly', $periodTypes);
        $this->assertContains('yearly', $periodTypes);
    }

    /** @test */
    public function test_builds_empty_array_when_no_time_series_exists(): void
    {
        // Arrange: Decision with no time series records
        $decision = CourtDecision::factory()->create();

        // Act
        $timeSeries = $this->invokePrivateMethod(
            $this->analyzer,
            'buildCitationTimeSeries',
            [$decision->id]
        );

        // Assert: Should return empty array
        $this->assertIsArray($timeSeries);
        $this->assertEmpty($timeSeries);
    }

    /** @test */
    public function test_limits_time_series_to_recent_periods(): void
    {
        // Arrange: Create many historical records
        $decision = CourtDecision::factory()->create();

        // Create 24 months of data (2 years)
        for ($i = 0; $i < 24; $i++) {
            CitationTimeSeries::create([
                'decision_id' => $decision->id,
                'period_start' => now()->subMonths($i)->startOfMonth(),
                'period_end' => now()->subMonths($i)->endOfMonth(),
                'period_type' => 'monthly',
                'citation_count' => 5 + $i,
                'incoming_citations' => 5 + $i,
                'outgoing_citations' => 0,
            ]);
        }

        // Act
        $timeSeries = $this->invokePrivateMethod(
            $this->analyzer,
            'buildCitationTimeSeries',
            [$decision->id]
        );

        // Assert: Should limit to recent 12 months
        $monthlyRecords = array_filter($timeSeries, fn ($record) => $record['period_type'] === 'monthly');
        $this->assertLessThanOrEqual(12, count($monthlyRecords));
    }

    /** @test */
    public function test_time_series_includes_correct_trend_calculations(): void
    {
        // Arrange: Create decision with declining citations
        $decision = CourtDecision::factory()->create();

        // Month 1: 20 citations
        CitationTimeSeries::create([
            'decision_id' => $decision->id,
            'period_start' => now()->subMonths(2)->startOfMonth(),
            'period_end' => now()->subMonths(2)->endOfMonth(),
            'period_type' => 'monthly',
            'citation_count' => 20,
            'incoming_citations' => 20,
            'outgoing_citations' => 0,
        ]);

        // Month 2: 15 citations (down)
        CitationTimeSeries::create([
            'decision_id' => $decision->id,
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
            'period_type' => 'monthly',
            'citation_count' => 15,
            'incoming_citations' => 15,
            'outgoing_citations' => 0,
        ]);

        // Month 3: 15 citations (stable)
        CitationTimeSeries::create([
            'decision_id' => $decision->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'period_type' => 'monthly',
            'citation_count' => 15,
            'incoming_citations' => 15,
            'outgoing_citations' => 0,
        ]);

        // Act
        $timeSeries = $this->invokePrivateMethod(
            $this->analyzer,
            'buildCitationTimeSeries',
            [$decision->id]
        );

        // Assert: Verify trends
        $monthlyRecords = array_filter($timeSeries, fn ($record) => $record['period_type'] === 'monthly');
        $monthlyRecords = array_values($monthlyRecords); // Re-index

        $this->assertEquals('stable', $monthlyRecords[0]['trend']); // Current month
        $this->assertEquals('down_5', $monthlyRecords[1]['trend']); // Previous month
        $this->assertEquals('new', $monthlyRecords[2]['trend']); // First month
    }

    /**
     * Helper method to invoke private methods for testing
     */
    protected function invokePrivateMethod($object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
