<?php

namespace Tests\Unit\Models;

use App\Models\CourtDecision;
use App\Models\DecisionImpactMetric;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DecisionImpactMetricTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_belongs_to_court_decision()
    {
        // Arrange
        $decision = CourtDecision::factory()->create();
        $metric = DecisionImpactMetric::factory()->create(['decision_id' => $decision->id]);

        // Act
        $metric->load('decision');

        // Assert
        $this->assertInstanceOf(CourtDecision::class, $metric->decision);
        $this->assertEquals($decision->id, $metric->decision->id);
    }

    /** @test */
    public function it_casts_citations_over_time_to_array()
    {
        // Arrange
        $metric = DecisionImpactMetric::factory()->create([
            'citations_over_time' => [
                '2023-01' => 5,
                '2023-02' => 8,
            ],
        ]);

        // Assert
        $this->assertIsArray($metric->citations_over_time);
        $this->assertArrayHasKey('2023-01', $metric->citations_over_time);
    }

    /** @test */
    public function it_casts_jurisdictional_spread_to_array()
    {
        // Arrange
        $metric = DecisionImpactMetric::factory()->create([
            'jurisdictional_spread' => ['HR' => 10, 'EU' => 5],
        ]);

        // Assert
        $this->assertIsArray($metric->jurisdictional_spread);
        $this->assertEquals(10, $metric->jurisdictional_spread['HR']);
    }

    /** @test */
    public function it_casts_citing_courts_to_array()
    {
        // Arrange
        $metric = DecisionImpactMetric::factory()->create([
            'citing_courts' => ['Vrhovni sud', 'Županijski sud'],
        ]);

        // Assert
        $this->assertIsArray($metric->citing_courts);
        $this->assertCount(2, $metric->citing_courts);
    }

    /** @test */
    public function it_casts_influential_cases_to_array()
    {
        // Arrange
        $metric = DecisionImpactMetric::factory()->create([
            'influential_cases' => [
                ['id' => '123', 'case_number' => 'Rev-123'],
            ],
        ]);

        // Assert
        $this->assertIsArray($metric->influential_cases);
        $this->assertCount(1, $metric->influential_cases);
    }

    /** @test */
    public function it_stores_scores_as_floats()
    {
        // Arrange
        $metric = DecisionImpactMetric::factory()->create([
            'authority_score' => 0.85,
            'precedent_strength' => 0.75,
            'influence_score' => 0.80,
            'citation_velocity' => 2.5,
            'temporal_decay_factor' => 0.95,
        ]);

        // Assert
        $this->assertIsFloat($metric->authority_score);
        $this->assertIsFloat($metric->precedent_strength);
        $this->assertIsFloat($metric->influence_score);
        $this->assertIsFloat($metric->citation_velocity);
        $this->assertIsFloat($metric->temporal_decay_factor);
    }

    /** @test */
    public function it_casts_dates_correctly()
    {
        // Arrange
        $metric = DecisionImpactMetric::factory()->create([
            'last_calculated_at' => now(),
            'last_citation_at' => now()->subMonth(),
        ]);

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $metric->last_calculated_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $metric->last_citation_at);
    }

    /** @test */
    public function it_can_scope_high_authority_metrics()
    {
        // Arrange
        DecisionImpactMetric::factory()->highAuthority()->count(3)->create();
        DecisionImpactMetric::factory()->lowAuthority()->count(2)->create();

        // Act
        $highAuthority = DecisionImpactMetric::highAuthority()->get();

        // Assert
        $this->assertGreaterThanOrEqual(3, $highAuthority->count());
        foreach ($highAuthority as $metric) {
            $this->assertGreaterThanOrEqual(0.7, $metric->authority_score);
        }
    }

    /** @test */
    public function it_can_scope_recently_updated_metrics()
    {
        // Arrange
        DecisionImpactMetric::factory()->recentlyUpdated()->count(3)->create();
        DecisionImpactMetric::factory()->stale()->count(2)->create();

        // Act
        $recentlyUpdated = DecisionImpactMetric::recentlyUpdated(7)->get();

        // Assert
        $this->assertGreaterThanOrEqual(3, $recentlyUpdated->count());
        foreach ($recentlyUpdated as $metric) {
            $this->assertTrue($metric->last_calculated_at->gte(now()->subDays(7)));
        }
    }

    /** @test */
    public function it_can_filter_by_citation_count()
    {
        // Arrange
        DecisionImpactMetric::factory()->create(['citation_count' => 50]);
        DecisionImpactMetric::factory()->create(['citation_count' => 5]);
        DecisionImpactMetric::factory()->create(['citation_count' => 25]);

        // Act
        $highCitationMetrics = DecisionImpactMetric::where('citation_count', '>=', 20)->get();

        // Assert
        $this->assertCount(2, $highCitationMetrics);
    }

    /** @test */
    public function it_uses_string_primary_key()
    {
        // Arrange
        $metric = DecisionImpactMetric::factory()->create();

        // Assert
        $this->assertIsString($metric->id);
        $this->assertFalse($metric->incrementing);
        $this->assertEquals('string', $metric->getKeyType());
    }
}
