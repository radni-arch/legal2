<?php

namespace Tests\Unit\Models;

use App\Models\CaseFeature;
use App\Models\LegalCase;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CaseFeatureTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_belongs_to_legal_case()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        $feature = CaseFeature::factory()->create(['case_id' => $case->id]);

        // Act
        $feature->load('case');

        // Assert
        $this->assertInstanceOf(LegalCase::class, $feature->case);
        $this->assertEquals($case->id, $feature->case->id);
    }

    /** @test */
    public function it_casts_embedding_vector_to_array()
    {
        // Arrange
        $feature = CaseFeature::factory()->create([
            'embedding_vector' => [0.1, 0.2, 0.3],
        ]);

        // Assert
        $this->assertIsArray($feature->embedding_vector);
        $this->assertCount(3, $feature->embedding_vector);
    }

    /** @test */
    public function it_casts_legal_issues_to_array()
    {
        // Arrange
        $feature = CaseFeature::factory()->create([
            'legal_issues' => ['issue1', 'issue2'],
        ]);

        // Assert
        $this->assertIsArray($feature->legal_issues);
        $this->assertCount(2, $feature->legal_issues);
    }

    /** @test */
    public function it_stores_complexity_score_as_float()
    {
        // Arrange
        $feature = CaseFeature::factory()->create([
            'complexity_score' => 0.75,
        ]);

        // Assert
        $this->assertIsFloat($feature->complexity_score);
        $this->assertEquals(0.75, $feature->complexity_score);
    }

    /** @test */
    public function it_can_scope_recent_features()
    {
        // Arrange
        CaseFeature::factory()->recent()->count(3)->create();
        CaseFeature::factory()->old()->count(2)->create();

        // Act
        $recentFeatures = CaseFeature::where('features_extracted_at', '>', now()->subDays(7))->get();

        // Assert
        $this->assertGreaterThanOrEqual(3, $recentFeatures->count());
    }

    /** @test */
    public function it_can_scope_complex_cases()
    {
        // Arrange
        CaseFeature::factory()->complex()->count(2)->create();
        CaseFeature::factory()->simple()->count(3)->create();

        // Act
        $complexCases = CaseFeature::where('complexity_level', 'very_complex')->get();

        // Assert
        $this->assertCount(2, $complexCases);
    }
}
