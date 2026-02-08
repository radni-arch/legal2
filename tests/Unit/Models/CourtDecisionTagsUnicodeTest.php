<?php

namespace Tests\Unit\Models;

use App\Models\CourtDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourtDecisionTagsUnicodeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_stores_and_retrieves_tags_with_proper_croatian_unicode(): void
    {
        $decision = CourtDecision::factory()->create([
            'tags' => ['Presuda', 'Prekršajni postupak', 'Pravomoćna odluka'],
        ]);

        // Reload from database
        $decision->refresh();

        // Check that Croatian characters are preserved
        $this->assertContains('Prekršajni postupak', $decision->tags);

        // Check raw database value has proper encoding
        $raw = \DB::table('court_decisions')
            ->where('id', $decision->id)
            ->value('tags');

        // Should NOT contain escaped unicode
        $this->assertStringNotContainsString('\u0161', $raw);
        $this->assertStringContainsString('š', $raw);
    }
}
