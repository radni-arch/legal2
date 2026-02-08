<?php

namespace Tests\Unit\Models;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Models\DecisionImpactMetric;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CourtDecisionTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_has_documents_relationship()
    {
        // Arrange
        $decision = CourtDecision::factory()->create();
        $documents = CourtDecisionDocument::factory()->count(3)->create([
            'decision_id' => $decision->id,
        ]);

        // Act
        $decision->load('documents');

        // Assert
        $this->assertCount(3, $decision->documents);
        $this->assertInstanceOf(CourtDecisionDocument::class, $decision->documents->first());
    }

    /** @test */
    public function it_has_impact_metrics_relationship()
    {
        // Arrange
        $decision = CourtDecision::factory()->create();
        $metric = DecisionImpactMetric::factory()->create([
            'decision_id' => $decision->id,
        ]);

        // Act
        $decision->load('impactMetrics');

        // Assert
        $this->assertNotNull($decision->impactMetrics);
        $this->assertInstanceOf(DecisionImpactMetric::class, $decision->impactMetrics);
        $this->assertEquals($metric->id, $decision->impactMetrics->id);
    }

    /** @test */
    public function it_uses_string_primary_key()
    {
        // Arrange
        $decision = CourtDecision::factory()->create();

        // Assert
        $this->assertIsString($decision->id);
        $this->assertFalse($decision->incrementing);
        $this->assertEquals('string', $decision->getKeyType());
    }

    /** @test */
    public function it_casts_dates_correctly()
    {
        // Arrange
        $decision = CourtDecision::factory()->create([
            'decision_date' => '2024-01-15',
            'publication_date' => '2024-01-20',
        ]);

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $decision->decision_date);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $decision->publication_date);
    }

    /** @test */
    public function it_casts_tags_to_array()
    {
        // Arrange
        $decision = CourtDecision::factory()->create([
            'tags' => ['commercial', 'contract', 'breach'],
        ]);

        // Assert
        $this->assertIsArray($decision->tags);
        $this->assertCount(3, $decision->tags);
    }

    /** @test */
    public function it_can_filter_by_court()
    {
        // Arrange
        CourtDecision::factory()->supremeCourt()->count(3)->create();
        CourtDecision::factory()->countyCourt()->count(2)->create();

        // Act
        $supremeDecisions = CourtDecision::where('court', 'like', '%Vrhovni sud%')->get();

        // Assert
        $this->assertCount(3, $supremeDecisions);
    }

    /** @test */
    public function it_can_filter_by_finality()
    {
        // Arrange
        CourtDecision::factory()->final()->count(4)->create();
        CourtDecision::factory()->preliminary()->count(2)->create();

        // Act
        $finalDecisions = CourtDecision::whereIn('finality', ['final', 'konačna', 'pravnomoćna'])->get();

        // Assert
        $this->assertGreaterThanOrEqual(4, $finalDecisions->count());
    }

    /** @test */
    public function it_can_filter_by_jurisdiction()
    {
        // Arrange
        CourtDecision::factory()->count(5)->create(['jurisdiction' => 'HR']);
        CourtDecision::factory()->count(2)->create(['jurisdiction' => 'EU']);

        // Act
        $hrDecisions = CourtDecision::where('jurisdiction', 'HR')->get();

        // Assert
        $this->assertCount(5, $hrDecisions);
    }

    /** @test */
    public function it_can_filter_decisions_with_ecli()
    {
        // Arrange
        CourtDecision::factory()->withECLI()->count(3)->create();
        CourtDecision::factory()->count(2)->create(['ecli' => null]);

        // Act
        $decisionsWithECLI = CourtDecision::whereNotNull('ecli')->get();

        // Assert
        $this->assertCount(3, $decisionsWithECLI);
    }
}
