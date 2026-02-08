<?php

namespace Tests\Unit\Services\LegalReasoning;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Models\DecisionImpactMetric;
use App\Services\LegalReasoning\CitationAnalyzer;
use App\Services\LegalReasoning\ImpactAnalyzer;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ImpactAnalyzerTest extends TestCase
{
    use UsesTestDatabase;

    protected ImpactAnalyzer $impactAnalyzer;

    protected $citationAnalyzerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citationAnalyzerMock = Mockery::mock(CitationAnalyzer::class);
        $this->impactAnalyzer = new ImpactAnalyzer($this->citationAnalyzerMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_analyzes_decision_impact_successfully()
    {
        // Arrange
        $decision = CourtDecision::factory()->supremeCourt()->create([
            'case_number' => 'Rev-1234/2023',
            'decision_date' => now()->subYear(),
        ]);

        // Create citing decisions and citation relationships
        for ($i = 0; $i < 5; $i++) {
            $citingDecision = CourtDecision::factory()->create([
                'decision_date' => now()->subMonths($i + 1),
            ]);

            CourtDecisionDocument::factory()->create([
                'decision_id' => $citingDecision->id,
                'content' => "This follows precedent in {$decision->case_number}.",
            ]);

            // Create citation relationship
            DB::table('citation_relationships')->insert([
                'citing_decision_id' => $citingDecision->id,
                'cited_decision_id' => $decision->id,
                'citation_type' => 'direct',
                'context' => "This follows precedent in {$decision->case_number}.",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Mock graph database response
        $this->citationAnalyzerMock
            ->shouldReceive('query')
            ->andReturn([
                'nodes' => [],
                'relationships' => [],
            ]);

        // Act
        $result = $this->impactAnalyzer->analyzeDecisionImpact($decision->id);

        // Assert
        $this->assertArrayHasKey('decision_id', $result);
        $this->assertArrayHasKey('impact_score', $result);
        $this->assertArrayHasKey('citation_count', $result);
        $this->assertArrayHasKey('citation_velocity', $result);
        $this->assertArrayHasKey('trend', $result);

        $this->assertEquals($decision->id, $result['decision_id']);
        $this->assertGreaterThan(0, $result['impact_score']);
        $this->assertEquals(5, $result['citation_count']);
    }

    /** @test */
    public function it_persists_impact_metrics_to_database()
    {
        // Arrange
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Rev-1234/2023',
        ]);

        // Add citations
        $citingDecision = CourtDecision::factory()->create();
        CourtDecisionDocument::factory()->create([
            'decision_id' => $citingDecision->id,
            'content' => "Cites {$decision->case_number}",
        ]);

        // Create citation relationship
        DB::table('citation_relationships')->insert([
            'citing_decision_id' => $citingDecision->id,
            'cited_decision_id' => $decision->id,
            'citation_type' => 'direct',
            'context' => "Cites {$decision->case_number}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->citationAnalyzerMock->shouldReceive('query')->andReturn([]);

        // Act
        $this->impactAnalyzer->analyzeDecisionImpact($decision->id);

        // Assert
        $this->assertDatabaseHas('decision_impact_metrics', [
            'decision_id' => $decision->id,
        ]);

        $metric = DecisionImpactMetric::where('decision_id', $decision->id)->first();
        $this->assertNotNull($metric);
        $this->assertEquals(1, $metric->citation_count);
    }

    /** @test */
    public function it_calculates_citation_velocity()
    {
        // Arrange
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Rev-1234/2023',
            'decision_date' => now()->subYears(2),
        ]);

        // Create 6 recent citations (past year)
        for ($i = 0; $i < 6; $i++) {
            $citingDecision = CourtDecision::factory()->create([
                'decision_date' => now()->subMonths($i),
            ]);
            CourtDecisionDocument::factory()->create([
                'decision_id' => $citingDecision->id,
                'content' => "Cites {$decision->case_number}",
            ]);
            DB::table('citation_relationships')->insert([
                'citing_decision_id' => $citingDecision->id,
                'cited_decision_id' => $decision->id,
                'citation_type' => 'direct',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create 2 old citations
        for ($i = 0; $i < 2; $i++) {
            $citingDecision = CourtDecision::factory()->create([
                'decision_date' => now()->subYears(3),
            ]);
            CourtDecisionDocument::factory()->create([
                'decision_id' => $citingDecision->id,
                'content' => "Cites {$decision->case_number}",
            ]);
            DB::table('citation_relationships')->insert([
                'citing_decision_id' => $citingDecision->id,
                'cited_decision_id' => $decision->id,
                'citation_type' => 'direct',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->citationAnalyzerMock->shouldReceive('query')->andReturn([]);

        // Act
        $result = $this->impactAnalyzer->analyzeDecisionImpact($decision->id);

        // Assert
        $this->assertGreaterThan(0, $result['citation_velocity']);
        // Should reflect recent citations
    }

    /** @test */
    public function it_tracks_citations_over_time()
    {
        // Arrange
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Rev-1234/2023',
            'decision_date' => now()->subYears(3),
        ]);

        // Create citations spread over time
        $months = ['2023-01', '2023-06', '2024-01', '2024-06'];
        foreach ($months as $month) {
            $citingDecision = CourtDecision::factory()->create([
                'decision_date' => \Carbon\Carbon::parse($month),
            ]);
            CourtDecisionDocument::factory()->create([
                'decision_id' => $citingDecision->id,
                'content' => "Cites {$decision->case_number}",
            ]);
            DB::table('citation_relationships')->insert([
                'citing_decision_id' => $citingDecision->id,
                'cited_decision_id' => $decision->id,
                'citation_type' => 'direct',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->citationAnalyzerMock->shouldReceive('query')->andReturn([]);

        // Act
        $result = $this->impactAnalyzer->analyzeDecisionImpact($decision->id);

        // Assert
        $this->assertArrayHasKey('citations_over_time', $result);
        $this->assertNotEmpty($result['citations_over_time']);
    }

    /** @test */
    public function it_identifies_trend_as_increasing_decreasing_or_stable()
    {
        // Arrange
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Rev-1234/2023',
            'decision_date' => now()->subYears(2),
        ]);

        // Create increasing trend - more recent citations
        for ($i = 0; $i < 10; $i++) {
            $citingDecision = CourtDecision::factory()->create([
                'decision_date' => now()->subMonths($i),
            ]);
            CourtDecisionDocument::factory()->create([
                'decision_id' => $citingDecision->id,
                'content' => "Cites {$decision->case_number}",
            ]);
        }

        $this->citationAnalyzerMock->shouldReceive('query')->andReturn([]);

        // Act
        $result = $this->impactAnalyzer->analyzeDecisionImpact($decision->id);

        // Assert
        $this->assertArrayHasKey('trend', $result);
        $this->assertContains($result['trend'], ['increasing', 'decreasing', 'stable']);
    }

    /** @test */
    public function it_analyzes_jurisdictional_spread()
    {
        // Arrange
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Rev-1234/2023',
            'jurisdiction' => 'HR',
        ]);

        // Create citations from different courts/jurisdictions
        $courts = [
            'Vrhovni sud Republike Hrvatske',
            'Županijski sud u Zagrebu',
            'Općinski sud u Zagrebu',
        ];

        foreach ($courts as $court) {
            $citingDecision = CourtDecision::factory()->create([
                'court' => $court,
            ]);
            CourtDecisionDocument::factory()->create([
                'decision_id' => $citingDecision->id,
                'content' => "Cites {$decision->case_number}",
            ]);
        }

        $this->citationAnalyzerMock->shouldReceive('query')->andReturn([]);

        // Act
        $result = $this->impactAnalyzer->analyzeDecisionImpact($decision->id);

        // Assert
        $this->assertArrayHasKey('jurisdictional_spread', $result);
    }

    /** @test */
    public function it_calculates_impact_score_correctly()
    {
        // Arrange
        $decision = CourtDecision::factory()->supremeCourt()->create([
            'case_number' => 'Rev-1234/2023',
        ]);

        // Add many citations
        for ($i = 0; $i < 20; $i++) {
            $citingDecision = CourtDecision::factory()->create();
            CourtDecisionDocument::factory()->create([
                'decision_id' => $citingDecision->id,
                'content' => "Cites {$decision->case_number}",
            ]);
        }

        $this->citationAnalyzerMock->shouldReceive('query')->andReturn([]);

        // Act
        $result = $this->impactAnalyzer->analyzeDecisionImpact($decision->id);

        // Assert
        $this->assertGreaterThan(0, $result['impact_score']);
        $this->assertLessThanOrEqual(1.0, $result['impact_score']);
    }

    /** @test */
    public function it_handles_decision_with_no_citations()
    {
        // Arrange
        $decision = CourtDecision::factory()->create([
            'case_number' => 'No-Citations-123',
        ]);

        $this->citationAnalyzerMock->shouldReceive('query')->andReturn([]);

        // Act
        $result = $this->impactAnalyzer->analyzeDecisionImpact($decision->id);

        // Assert
        $this->assertEquals(0, $result['citation_count']);
        $this->assertEquals(0, $result['citation_velocity']);
        $this->assertArrayHasKey('impact_score', $result);
    }

    /** @test */
    public function it_updates_existing_impact_metrics()
    {
        // Arrange
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Test-123',
        ]);

        // Create initial metric
        DecisionImpactMetric::factory()->create([
            'decision_id' => $decision->id,
            'citation_count' => 5,
        ]);

        // Add new citations
        $citingDecision = CourtDecision::factory()->create();
        CourtDecisionDocument::factory()->create([
            'decision_id' => $citingDecision->id,
            'content' => "Cites {$decision->case_number}",
        ]);

        // Create citation relationship
        DB::table('citation_relationships')->insert([
            'citing_decision_id' => $citingDecision->id,
            'cited_decision_id' => $decision->id,
            'citation_type' => 'direct',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->citationAnalyzerMock->shouldReceive('query')->andReturn([]);

        // Act
        $this->impactAnalyzer->analyzeDecisionImpact($decision->id);

        // Assert
        $this->assertDatabaseCount('decision_impact_metrics', 1); // Should update, not create new
        $metric = DecisionImpactMetric::where('decision_id', $decision->id)->first();
        $this->assertEquals(1, $metric->citation_count); // Updated count
    }
}
