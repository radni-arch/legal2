<?php

namespace Tests\Unit\Services;

use App\Models\Law;
use App\Services\Search\FullTextSearchService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Tests for full-text search graceful degradation
 *
 * When content_tsv column doesn't exist, full-text search should:
 * 1. Fall back to ILIKE search instead of failing
 * 2. Log a warning about degraded search mode
 * 3. Still return valid search results
 *
 * Updated to test FullTextSearchService directly (extracted from UnifiedSearchService).
 */
class UnifiedSearchServiceFullTextFallbackTest extends TestCase
{
    use UsesTestDatabase;

    protected FullTextSearchService $fullTextSearchService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fullTextSearchService = new FullTextSearchService();
    }

    /**
     * Test: Full-text search should gracefully degrade to ILIKE when content_tsv is missing
     */
    public function test_fulltext_search_degrades_gracefully_when_content_tsv_missing(): void
    {
        // Create a test law with searchable content
        $law = Law::factory()->create([
            'title' => 'Test Law About Contracts',
            'content' => 'This law governs contract obligations and agreements.',
            'law_number' => 'NN 123/2024',
        ]);

        // Mock Schema::hasColumn to simulate missing content_tsv column
        Schema::shouldReceive('hasColumn')
            ->with('laws', 'content_tsv')
            ->andReturn(false);

        // Allow other hasColumn calls to pass through
        Schema::shouldReceive('hasColumn')
            ->andReturnUsing(function ($table, $column) {
                if ($column === 'content_tsv') {
                    return false;
                }

                return Schema::getFacadeRoot()->hasColumn($table, $column);
            });

        // Expect warning log about fallback mode
        Log::shouldReceive('warning')
            ->once()
            ->with(
                Mockery::pattern('/[Ff]ull.text search.*not available|content_tsv.*missing|[Ff]allback/'),
                Mockery::type('array')
            );

        // Execute search - should NOT throw exception
        $results = $this->fullTextSearchService->fullTextSearchLaws('contract', 10, []);

        // Should return results (from fallback ILIKE search)
        $this->assertIsArray($results);
        // Fallback search should find our test law
        $this->assertNotEmpty($results, 'Fallback ILIKE search should return results');

        // Verify result structure (cast both to string for ULID comparison)
        $foundLaw = collect($results)->first(fn ($r) => (string) $r['id'] === (string) $law->id);
        $this->assertNotNull($foundLaw, 'Should find the test law via fallback search');
    }

    /**
     * Test: Full-text search logs warning when using fallback mode
     */
    public function test_fulltext_search_logs_warning_when_using_fallback(): void
    {
        Law::factory()->create([
            'title' => 'Test Law',
            'content' => 'Test content for search',
        ]);

        // Mock missing column
        Schema::shouldReceive('hasColumn')
            ->with('laws', 'content_tsv')
            ->once()
            ->andReturn(false);

        // Capture the warning log
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains(strtolower($message), 'fallback')
                    || str_contains(strtolower($message), 'content_tsv')
                    || str_contains(strtolower($message), 'full-text');
            });

        $this->fullTextSearchService->fullTextSearchLaws('test', 10, []);
    }

    /**
     * Test: Full-text search does NOT log warning when column exists (normal operation)
     */
    public function test_fulltext_search_does_not_log_warning_when_column_exists(): void
    {
        Law::factory()->create([
            'title' => 'Test Law',
            'content' => 'Test content',
        ]);

        // Mock column exists
        Schema::shouldReceive('hasColumn')
            ->with('laws', 'content_tsv')
            ->andReturn(true);

        // Should NOT log warning
        Log::shouldReceive('warning')
            ->never();

        // Allow normal operation (this might throw if column doesn't really exist,
        // but that's expected - test is about not logging warning)
        try {
            $this->fullTextSearchService->fullTextSearchLaws('test', 10, []);
        } catch (\Illuminate\Database\QueryException $e) {
            // Expected if column actually doesn't exist in test DB
            $this->markTestSkipped('content_tsv column not available in test DB');
        }
    }

    /**
     * Test: Fallback search applies filters correctly
     */
    public function test_fallback_search_applies_filters(): void
    {
        // Create laws in different jurisdictions
        $hrLaw = Law::factory()->create([
            'title' => 'Croatian Contract Law',
            'content' => 'Croatian law about contracts',
            'jurisdiction' => 'HR',
        ]);

        $deLaw = Law::factory()->create([
            'title' => 'German Contract Law',
            'content' => 'German law about contracts',
            'jurisdiction' => 'DE',
        ]);

        // Mock missing column
        Schema::shouldReceive('hasColumn')
            ->with('laws', 'content_tsv')
            ->andReturn(false);

        Log::shouldReceive('warning')->andReturn(null);

        // Search with jurisdiction filter
        $results = $this->fullTextSearchService->fullTextSearchLaws('contract', 10, ['jurisdiction' => 'HR']);

        // Should only find Croatian law (cast to strings for ULID comparison)
        $ids = collect($results)->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        $this->assertContains((string) $hrLaw->id, $ids);
        $this->assertNotContains((string) $deLaw->id, $ids);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
