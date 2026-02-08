<?php

namespace Tests\Integration;

use App\Models\Law;
use App\Services\LegalReasoning\ConflictResolver;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Integration tests for conflict resolution flow
 * Tests the complete workflow from finding conflicts to resolving them
 */
class ConflictResolutionFlowTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_completes_full_conflict_resolution_workflow()
    {
        $this->markTestSkipped('Requires live OpenAI API - enable for integration testing');

        // Arrange - Create laws that conflict
        $oldNationalLaw = Law::factory()->create([
            'law_number' => 'NN 100/2020',
            'title' => 'Old Tax Law',
            'jurisdiction' => 'HR',
            'effective_date' => now()->subYears(3),
            'content' => 'Corporate tax rate is 18%',
            'embedding_vector' => array_fill(0, 1536, 0.5),
        ]);

        $newNationalLaw = Law::factory()->create([
            'law_number' => 'NN 50/2023',
            'title' => 'New Tax Law',
            'jurisdiction' => 'HR',
            'effective_date' => now()->subYear(),
            'content' => 'Corporate tax rate is 20%',
            'embedding_vector' => array_fill(0, 1536, 0.51), // Very similar embedding
        ]);

        $conflictResolver = app(ConflictResolver::class);

        // Act - Find conflicts
        $conflicts = $conflictResolver->findConflicts($oldNationalLaw->id);

        // Assert - Conflict detected
        $this->assertNotEmpty($conflicts);
        $this->assertArrayHasKey('conflict_type', $conflicts[0]);

        // Act - Resolve conflict
        $laws = [
            [
                'id' => $oldNationalLaw->id,
                'law_number' => $oldNationalLaw->law_number,
                'title' => $oldNationalLaw->title,
                'jurisdiction' => $oldNationalLaw->jurisdiction,
                'effective_date' => $oldNationalLaw->effective_date->toDateString(),
                'content' => $oldNationalLaw->content,
            ],
            [
                'id' => $newNationalLaw->id,
                'law_number' => $newNationalLaw->law_number,
                'title' => $newNationalLaw->title,
                'jurisdiction' => $newNationalLaw->jurisdiction,
                'effective_date' => $newNationalLaw->effective_date->toDateString(),
                'content' => $newNationalLaw->content,
            ],
        ];

        $resolution = $conflictResolver->resolveConflict($laws);

        // Assert - Newer law wins
        $this->assertEquals($newNationalLaw->law_number, $resolution['winning_law']['law_number']);
        $this->assertArrayHasKey('reasoning', $resolution);
        $this->assertNotEmpty($resolution['reasoning']);
    }
}
