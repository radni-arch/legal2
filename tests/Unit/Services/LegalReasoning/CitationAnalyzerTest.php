<?php

namespace Tests\Unit\Services\LegalReasoning;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Models\DecisionImpactMetric;
use App\Services\GraphDatabaseService;
use App\Services\LegalReasoning\CitationAnalyzer;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CitationAnalyzerTest extends TestCase
{
    use UsesTestDatabase;

    protected CitationAnalyzer $citationAnalyzer;

    protected $graphDbMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphDbMock = Mockery::mock(GraphDatabaseService::class);

        $this->citationAnalyzer = new CitationAnalyzer(
            $this->graphDbMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_analyzes_authority_successfully()
    {
        // Arrange
        $decision = CourtDecision::factory()->supremeCourt()->final()->withECLI()->create([
            'case_number' => 'Rev-1234/2023',
            'court' => 'Vrhovni sud Republike Hrvatske',
            'decision_date' => now()->subYear(),
        ]);

        // Create citing decisions
        for ($i = 0; $i < 5; $i++) {
            $citingDecision = CourtDecision::factory()->create([
                'decision_date' => now()->subMonths($i + 1),
            ]);

            CourtDecisionDocument::factory()->create([
                'decision_id' => $citingDecision->id,
                'content' => "This follows precedent in {$decision->case_number}.",
            ]);
        }

        // Act
        $result = $this->citationAnalyzer->analyzeAuthority($decision->id);

        // Assert
        $this->assertArrayHasKey('decision_id', $result);
        $this->assertArrayHasKey('authority_score', $result);
        $this->assertArrayHasKey('precedent_strength', $result);
        $this->assertArrayHasKey('influence_score', $result);
        $this->assertArrayHasKey('citation_chain', $result);
        $this->assertArrayHasKey('influential_courts', $result);
        $this->assertArrayHasKey('temporal_decay_factor', $result);
        $this->assertArrayHasKey('citations_summary', $result);

        $this->assertEquals($decision->id, $result['decision_id']);
        $this->assertGreaterThan(0, $result['authority_score']);
        $this->assertCount(5, $result['citation_chain']);

        // Verify persistence
        $this->assertDatabaseHas('decision_impact_metrics', [
            'decision_id' => $decision->id,
        ]);

        $metric = DecisionImpactMetric::where('decision_id', $decision->id)->first();
        $this->assertNotNull($metric);
        $this->assertEquals(5, $metric->citation_count);
    }

    /** @test */
    public function it_calculates_higher_authority_for_supreme_court()
    {
        // Arrange
        $supremeDecision = CourtDecision::factory()->create([
            'court' => 'Vrhovni sud Republike Hrvatske',
            'case_number' => 'Rev-1234/2023',
            'finality' => 'preliminary', // Same finality for both
            'ecli' => null, // No ECLI for both
        ]);

        $municipalDecision = CourtDecision::factory()->create([
            'court' => 'Općinski sud u Zagrebu',
            'case_number' => 'Rev-5678/2023',
            'finality' => 'preliminary', // Same finality for both
            'ecli' => null, // No ECLI for both
        ]);

        // Act
        $supremeResult = $this->citationAnalyzer->analyzeAuthority($supremeDecision->id);
        $municipalResult = $this->citationAnalyzer->analyzeAuthority($municipalDecision->id);

        // Assert
        $this->assertGreaterThan($municipalResult['authority_score'], $supremeResult['authority_score']);
    }

    /** @test */
    public function it_gives_bonus_for_final_decisions()
    {
        // Arrange
        $finalDecision = CourtDecision::factory()->create([
            'court' => 'Županijski sud u Zagrebu', // Same court for both
            'finality' => 'final',
            'case_number' => 'Rev-1234/2023',
            'ecli' => null, // No ECLI for both
        ]);

        $preliminaryDecision = CourtDecision::factory()->create([
            'court' => 'Županijski sud u Zagrebu', // Same court for both
            'finality' => 'preliminary',
            'case_number' => 'Rev-5678/2023',
            'ecli' => null, // No ECLI for both
        ]);

        // Act
        $finalResult = $this->citationAnalyzer->analyzeAuthority($finalDecision->id);
        $preliminaryResult = $this->citationAnalyzer->analyzeAuthority($preliminaryDecision->id);

        // Assert
        $this->assertGreaterThan($preliminaryResult['authority_score'], $finalResult['authority_score']);
    }

    /** @test */
    public function it_gives_bonus_for_ecli()
    {
        // Arrange
        $withECLI = CourtDecision::factory()->create([
            'court' => 'Županijski sud u Zagrebu', // Same court for both
            'ecli' => 'ECLI:HR:VSRH:2023:ABC123',
            'case_number' => 'Rev-1234/2023',
        ]);

        $withoutECLI = CourtDecision::factory()->create([
            'court' => 'Županijski sud u Zagrebu', // Same court for both
            'ecli' => null,
            'case_number' => 'Rev-5678/2023',
        ]);

        // Act
        $ecliResult = $this->citationAnalyzer->analyzeAuthority($withECLI->id);
        $noEcliResult = $this->citationAnalyzer->analyzeAuthority($withoutECLI->id);

        // Assert
        $this->assertGreaterThan($noEcliResult['authority_score'], $ecliResult['authority_score']);
    }

    /** @test */
    public function it_counts_inbound_citations_correctly()
    {
        // Arrange
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Rev-1234/2023',
        ]);

        // Create 3 citing decisions
        for ($i = 0; $i < 3; $i++) {
            $citingDecision = CourtDecision::factory()->create();
            CourtDecisionDocument::factory()->create([
                'decision_id' => $citingDecision->id,
                'content' => "Reference to {$decision->case_number} in this decision.",
            ]);
        }

        // Act
        $result = $this->citationAnalyzer->analyzeAuthority($decision->id);

        // Assert
        $this->assertEquals(3, $result['citations_summary']['total_citations']);
        $this->assertCount(3, $result['citation_chain']);
    }

    /** @test */
    public function it_builds_citation_chain_ordered_by_date()
    {
        // Arrange
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Rev-1234/2023',
        ]);

        $oldCiting = CourtDecision::factory()->create([
            'decision_date' => now()->subYears(2),
            'case_number' => 'Old-123',
        ]);
        CourtDecisionDocument::factory()->create([
            'decision_id' => $oldCiting->id,
            'content' => "Cites {$decision->case_number}",
        ]);

        $recentCiting = CourtDecision::factory()->create([
            'decision_date' => now()->subMonths(1),
            'case_number' => 'Recent-456',
        ]);
        CourtDecisionDocument::factory()->create([
            'decision_id' => $recentCiting->id,
            'content' => "Cites {$decision->case_number}",
        ]);

        // Act
        $result = $this->citationAnalyzer->analyzeAuthority($decision->id);

        // Assert
        $this->assertCount(2, $result['citation_chain']);
        // First should be recent (DESC order)
        $this->assertEquals('Recent-456', $result['citation_chain'][0]['case_number']);
        $this->assertEquals('Old-123', $result['citation_chain'][1]['case_number']);
    }

    /** @test */
    public function it_identifies_influential_courts()
    {
        // Arrange
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Rev-1234/2023',
        ]);

        // Create citations from multiple courts
        for ($i = 0; $i < 3; $i++) {
            $citingDecision = CourtDecision::factory()->create([
                'court' => 'Vrhovni sud Republike Hrvatske',
            ]);
            CourtDecisionDocument::factory()->create([
                'decision_id' => $citingDecision->id,
                'content' => "Cites {$decision->case_number}",
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            $citingDecision = CourtDecision::factory()->create([
                'court' => 'Županijski sud u Zagrebu',
            ]);
            CourtDecisionDocument::factory()->create([
                'decision_id' => $citingDecision->id,
                'content' => "Cites {$decision->case_number}",
            ]);
        }

        // Act
        $result = $this->citationAnalyzer->analyzeAuthority($decision->id);

        // Assert
        $this->assertCount(2, $result['influential_courts']);
        $this->assertEquals('Vrhovni sud Republike Hrvatske', $result['influential_courts'][0]['court']);
        $this->assertEquals(3, $result['influential_courts'][0]['citation_count']);
        $this->assertEquals(5, $result['influential_courts'][0]['hierarchy_level']);
    }

    /** @test */
    public function it_applies_temporal_decay_correctly()
    {
        // Arrange
        $oldDecision = CourtDecision::factory()->create([
            'decision_date' => now()->subYears(20),
            'case_number' => 'Old-123',
        ]);

        $recentDecision = CourtDecision::factory()->create([
            'decision_date' => now()->subYear(),
            'case_number' => 'Recent-456',
        ]);

        // Act
        $oldResult = $this->citationAnalyzer->analyzeAuthority($oldDecision->id);
        $recentResult = $this->citationAnalyzer->analyzeAuthority($recentDecision->id);

        // Assert
        $this->assertLessThan($recentResult['temporal_decay_factor'], $oldResult['temporal_decay_factor']);
        $this->assertLessThan(1.0, $oldResult['temporal_decay_factor']);
        $this->assertGreaterThan(0.9, $recentResult['temporal_decay_factor']);
    }

    /** @test */
    public function it_defaults_temporal_decay_to_one_for_missing_date()
    {
        // Arrange
        $decision = CourtDecision::factory()->create([
            'decision_date' => null,
            'case_number' => 'No-Date-123',
        ]);

        // Act
        $result = $this->citationAnalyzer->analyzeAuthority($decision->id);

        // Assert
        $this->assertEquals(1.0, $result['temporal_decay_factor']);
    }

    /** @test */
    public function it_assesses_precedent_strength_with_multiple_factors()
    {
        // Arrange
        $decision = CourtDecision::factory()->create([
            'court' => 'Vrhovni sud Republike Hrvatske', // High court = +0.5
            'finality' => 'final', // Final = +0.2
            'decision_date' => now()->subYears(5), // Not recent, no penalty
            'case_number' => 'Strong-123',
        ]);

        // Add 11 citations (> 10 = +0.1)
        for ($i = 0; $i < 11; $i++) {
            $citingDecision = CourtDecision::factory()->create();
            CourtDecisionDocument::factory()->create([
                'decision_id' => $citingDecision->id,
                'content' => "Cites {$decision->case_number}",
            ]);
        }

        // Act
        $result = $this->citationAnalyzer->analyzeAuthority($decision->id);

        // Assert
        $this->assertGreaterThan(0.7, $result['precedent_strength']);
    }

    /** @test */
    public function it_penalizes_very_recent_decisions_in_precedent_strength()
    {
        // Arrange
        $veryRecentDecision = CourtDecision::factory()->create([
            'court' => 'Županijski sud u Zagrebu', // Same court for both
            'finality' => 'final', // Same finality for both
            'decision_date' => now()->subMonth(),
            'case_number' => 'VeryRecent-123',
        ]);

        $establishedDecision = CourtDecision::factory()->create([
            'court' => 'Županijski sud u Zagrebu', // Same court for both
            'finality' => 'final', // Same finality for both
            'decision_date' => now()->subYears(5),
            'case_number' => 'Established-456',
        ]);

        // Act
        $recentResult = $this->citationAnalyzer->analyzeAuthority($veryRecentDecision->id);
        $establishedResult = $this->citationAnalyzer->analyzeAuthority($establishedDecision->id);

        // Assert
        $this->assertLessThan($establishedResult['precedent_strength'], $recentResult['precedent_strength']);
    }

    /** @test */
    public function it_calculates_influence_score_as_weighted_average()
    {
        // Arrange
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Test-123',
        ]);

        // Act
        $result = $this->citationAnalyzer->analyzeAuthority($decision->id);

        // Assert
        // Influence = (authority * 0.6) + (precedent * 0.4)
        $expectedInfluence = ($result['authority_score'] * 0.6) + ($result['precedent_strength'] * 0.4);
        $this->assertEquals(round($expectedInfluence, 3), $result['influence_score']);
    }

    /** @test */
    public function it_categorizes_citations_by_court_hierarchy()
    {
        // Arrange
        $decision = CourtDecision::factory()->create([
            'court' => 'Županijski sud u Zagrebu', // County court (level 3)
            'case_number' => 'Test-123',
        ]);

        // Higher court citation
        $higherCourt = CourtDecision::factory()->create([
            'court' => 'Vrhovni sud Republike Hrvatske', // Level 5
        ]);
        CourtDecisionDocument::factory()->create([
            'decision_id' => $higherCourt->id,
            'content' => "Cites {$decision->case_number}",
        ]);

        // Same level court citation
        $sameCourt = CourtDecision::factory()->create([
            'court' => 'Županijski sud u Splitu', // Level 3
        ]);
        CourtDecisionDocument::factory()->create([
            'decision_id' => $sameCourt->id,
            'content' => "Cites {$decision->case_number}",
        ]);

        // Lower court citation
        $lowerCourt = CourtDecision::factory()->create([
            'court' => 'Općinski sud u Zagrebu', // Level 2
        ]);
        CourtDecisionDocument::factory()->create([
            'decision_id' => $lowerCourt->id,
            'content' => "Cites {$decision->case_number}",
        ]);

        // Act
        $result = $this->citationAnalyzer->analyzeAuthority($decision->id);

        // Assert
        $summary = $result['citations_summary'];
        $this->assertEquals(1, $summary['higher_court_citations']);
        $this->assertEquals(1, $summary['same_court_citations']);
        $this->assertEquals(1, $summary['lower_court_citations']);
    }

    /** @test */
    public function it_tracks_citation_velocity()
    {
        // Arrange
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Test-123',
        ]);

        // Create 6 citations in the past year
        for ($i = 0; $i < 6; $i++) {
            $citingDecision = CourtDecision::factory()->create([
                'decision_date' => now()->subMonths($i),
            ]);
            CourtDecisionDocument::factory()->create([
                'decision_id' => $citingDecision->id,
                'content' => "Cites {$decision->case_number}",
            ]);
        }

        // Create 2 older citations
        for ($i = 0; $i < 2; $i++) {
            $citingDecision = CourtDecision::factory()->create([
                'decision_date' => now()->subYears(2),
            ]);
            CourtDecisionDocument::factory()->create([
                'decision_id' => $citingDecision->id,
                'content' => "Cites {$decision->case_number}",
            ]);
        }

        // Act
        $result = $this->citationAnalyzer->analyzeAuthority($decision->id);

        // Assert
        $summary = $result['citations_summary'];
        $this->assertEquals(8, $summary['total_citations']);
        $this->assertEquals(6, $summary['recent_citations_1y']);
        $this->assertEquals(0.5, $summary['citation_velocity']); // 6 / 12 months
    }

    /** @test */
    public function it_generates_appropriate_impact_summary()
    {
        // Arrange
        $decision = CourtDecision::factory()->create([
            'court' => 'Vrhovni sud Republike Hrvatske',
            'case_number' => 'Test-123',
        ]);

        // Add 15 citations to get high authority
        for ($i = 0; $i < 15; $i++) {
            $citingDecision = CourtDecision::factory()->create();
            CourtDecisionDocument::factory()->create([
                'decision_id' => $citingDecision->id,
                'content' => "Cites {$decision->case_number}",
            ]);
        }

        // Act
        $result = $this->citationAnalyzer->analyzeAuthority($decision->id);

        // Assert
        $summary = $result['citations_summary']['impact_summary'] ?? '';
        $this->assertStringContainsString('15', $summary);
        $this->assertStringContainsString('authority', strtolower($summary));
    }

    /** @test */
    public function it_handles_decision_with_no_citations()
    {
        // Arrange
        $decision = CourtDecision::factory()->create([
            'case_number' => 'No-Citations-123',
        ]);

        // Act
        $result = $this->citationAnalyzer->analyzeAuthority($decision->id);

        // Assert
        $this->assertEquals(0, $result['citations_summary']['total_citations']);
        $this->assertEmpty($result['citation_chain']);
        $this->assertEmpty($result['influential_courts']);
        $this->assertStringContainsString('not been cited', $result['citations_summary']['impact_summary'] ?? '');
    }

    /** @test */
    public function it_persists_all_impact_metrics_to_database()
    {
        // Arrange
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Test-123',
        ]);

        // Add a citation
        $citingDecision = CourtDecision::factory()->create();
        CourtDecisionDocument::factory()->create([
            'decision_id' => $citingDecision->id,
            'content' => "Cites {$decision->case_number}",
        ]);

        // Act
        $result = $this->citationAnalyzer->analyzeAuthority($decision->id);

        // Assert
        $metric = DecisionImpactMetric::where('decision_id', $decision->id)->first();

        $this->assertNotNull($metric);
        $this->assertEquals($result['authority_score'], $metric->authority_score);
        $this->assertEquals($result['precedent_strength'], $metric->precedent_strength);
        $this->assertEquals($result['influence_score'], $metric->influence_score);
        $this->assertEquals(1, $metric->citation_count);
        $this->assertNotNull($metric->last_calculated_at);
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
            'authority_score' => 0.5,
        ]);

        // Add new citation
        $citingDecision = CourtDecision::factory()->create();
        CourtDecisionDocument::factory()->create([
            'decision_id' => $citingDecision->id,
            'content' => "Cites {$decision->case_number}",
        ]);

        // Act
        $this->citationAnalyzer->analyzeAuthority($decision->id);

        // Assert
        $this->assertDatabaseCount('decision_impact_metrics', 1); // Should update, not create new
        $metric = DecisionImpactMetric::where('decision_id', $decision->id)->first();
        $this->assertEquals(1, $metric->citation_count); // Updated with actual count
    }
}
