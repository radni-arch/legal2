<?php

namespace Tests\Unit\Services\LegalReasoning;

use App\Models\CitationTimeSeries;
use App\Models\CourtDecision;
use App\Services\GraphDatabaseService;
use App\Services\LegalReasoning\CitationAnalyzer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

/**
 * Citation Analyzer Time Series Tests
 *
 * Tests time series tracking for citation counts over time.
 * Follows TDD RED-GREEN-REFACTOR methodology.
 */
class CitationAnalyzerTimeSeriesTest extends TestCase
{
    use DatabaseTransactions;

    protected CitationAnalyzer $analyzer;

    protected $graphDbMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphDbMock = Mockery::mock(GraphDatabaseService::class);
        $this->analyzer = new CitationAnalyzer($this->graphDbMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_creates_monthly_time_series_when_persisting_impact_metrics(): void
    {
        // Create a decision
        $decision = CourtDecision::factory()->create([
            'case_number' => 'TEST-123/2025',
            'court' => 'Županijski sud u Osijeku',
            'decision_date' => now()->subMonths(2),
        ]);

        // Mock graph database to avoid actual Neo4j calls
        $this->graphDbMock->shouldReceive('run')->andReturn((object) ['records' => []]);

        // Run the analysis (which should create time series via persistence)
        $this->analyzer->analyzeAuthority($decision->id);

        // Assert that a time series record was created for this month
        $timeSeries = CitationTimeSeries::where('decision_id', $decision->id)
            ->where('period_type', 'monthly')
            ->first();

        $this->assertNotNull($timeSeries, 'Time series should be created during analysis');
        $this->assertEquals(now()->startOfMonth()->toDateString(), $timeSeries->period_start->toDateString());
        $this->assertEquals(now()->endOfMonth()->toDateString(), $timeSeries->period_end->toDateString());
        $this->assertGreaterThanOrEqual(0, $timeSeries->citation_count);
        $this->assertGreaterThanOrEqual(0, $timeSeries->incoming_citations);
    }

    /** @test */
    public function it_calculates_citation_trend_correctly(): void
    {
        $decision = CourtDecision::factory()->create();

        // Create previous month record (10 citations)
        $previousMonth = CitationTimeSeries::create([
            'decision_id' => $decision->id,
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
            'period_type' => 'monthly',
            'citation_count' => 10,
            'incoming_citations' => 10,
            'outgoing_citations' => 0,
        ]);

        // Create current month record (15 citations)
        $currentMonth = CitationTimeSeries::create([
            'decision_id' => $decision->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'period_type' => 'monthly',
            'citation_count' => 15,
            'incoming_citations' => 15,
            'outgoing_citations' => 0,
        ]);

        // Test trend calculation
        $trend = $currentMonth->trend;
        $this->assertEquals('up_5', $trend);

        // Test downward trend
        $nextMonth = CitationTimeSeries::create([
            'decision_id' => $decision->id,
            'period_start' => now()->addMonth()->startOfMonth(),
            'period_end' => now()->addMonth()->endOfMonth(),
            'period_type' => 'monthly',
            'citation_count' => 8,
            'incoming_citations' => 8,
            'outgoing_citations' => 0,
        ]);

        $this->assertEquals('down_7', $nextMonth->trend);
    }

    /** @test */
    public function it_returns_new_trend_for_first_time_series_entry(): void
    {
        $decision = CourtDecision::factory()->create();

        $firstEntry = CitationTimeSeries::create([
            'decision_id' => $decision->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'period_type' => 'monthly',
            'citation_count' => 5,
            'incoming_citations' => 5,
            'outgoing_citations' => 0,
        ]);

        $this->assertEquals('new', $firstEntry->trend);
    }

    /** @test */
    public function it_returns_stable_trend_when_citation_count_unchanged(): void
    {
        $decision = CourtDecision::factory()->create();

        CitationTimeSeries::create([
            'decision_id' => $decision->id,
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
            'period_type' => 'monthly',
            'citation_count' => 10,
            'incoming_citations' => 10,
            'outgoing_citations' => 0,
        ]);

        $current = CitationTimeSeries::create([
            'decision_id' => $decision->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'period_type' => 'monthly',
            'citation_count' => 10,
            'incoming_citations' => 10,
            'outgoing_citations' => 0,
        ]);

        $this->assertEquals('stable', $current->trend);
    }

    /** @test */
    public function it_stores_citing_courts_in_time_series(): void
    {
        $decision = CourtDecision::factory()->create([
            'case_number' => 'TEST-456/2025',
        ]);

        $this->graphDbMock->shouldReceive('run')->andReturn((object) ['records' => []]);

        $this->analyzer->analyzeAuthority($decision->id);

        $timeSeries = CitationTimeSeries::where('decision_id', $decision->id)->first();

        $this->assertNotNull($timeSeries, 'Time series should be created');
        $this->assertIsArray($timeSeries->citing_courts);
        // citing_courts should be an array (even if empty when no citations)
    }

    /** @test */
    public function it_stores_top_citing_decisions_in_time_series(): void
    {
        $decision = CourtDecision::factory()->create([
            'case_number' => 'TEST-789/2025',
        ]);

        $this->graphDbMock->shouldReceive('run')->andReturn((object) ['records' => []]);

        $this->analyzer->analyzeAuthority($decision->id);

        $timeSeries = CitationTimeSeries::where('decision_id', $decision->id)->first();

        $this->assertNotNull($timeSeries, 'Time series should be created');
        $this->assertIsArray($timeSeries->top_citing_decisions);
        // top_citing_decisions should be an array (empty when no citations)
    }

    /** @test */
    public function it_updates_existing_time_series_for_same_period(): void
    {
        $decision = CourtDecision::factory()->create([
            'case_number' => 'TEST-UPDATE/2025',
        ]);

        // Create initial time series
        $existing = CitationTimeSeries::create([
            'decision_id' => $decision->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'period_type' => 'monthly',
            'citation_count' => 5,
            'incoming_citations' => 5,
            'outgoing_citations' => 0,
        ]);

        $this->graphDbMock->shouldReceive('run')->andReturn((object) ['records' => []]);

        // Run analysis again
        $this->analyzer->analyzeAuthority($decision->id);

        // Should update the existing record, not create a new one
        $count = CitationTimeSeries::where('decision_id', $decision->id)
            ->where('period_type', 'monthly')
            ->where('period_start', now()->startOfMonth())
            ->count();

        $this->assertEquals(1, $count, 'Should not create duplicate time series for same period');

        $updated = CitationTimeSeries::where('decision_id', $decision->id)->first();
        $this->assertGreaterThanOrEqual(0, $updated->incoming_citations);
    }
}
