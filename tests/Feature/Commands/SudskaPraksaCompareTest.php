<?php

namespace Tests\Feature\Commands;

use Tests\TestCase;
use App\Models\SudskaPraksaSearch;
use App\Models\SudskaPraksaResult;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SudskaPraksaCompareTest extends TestCase
{
    use RefreshDatabase;

    public function test_compare_shows_changes_between_two_runs(): void
    {
        // Create two search runs with different counts for same query
        $search1 = SudskaPraksaSearch::create([
            'name' => 'Run 1',
            'keywords_file' => 'test.json',
            'courts' => 'vks',
            'started_at' => now()->subDay(),
            'finished_at' => now()->subDay(),
        ]);
        SudskaPraksaResult::create([
            'search_id' => $search1->id,
            'category' => 'Test',
            'query' => 'pretraga AND doma',
            'count' => 10,
            'classification' => 'zlato',
            'url' => 'https://example.com',
            'fetched_at' => now()->subDay(),
        ]);

        $search2 = SudskaPraksaSearch::create([
            'name' => 'Run 2',
            'keywords_file' => 'test.json',
            'courts' => 'vks',
            'started_at' => now(),
            'finished_at' => now(),
        ]);
        SudskaPraksaResult::create([
            'search_id' => $search2->id,
            'category' => 'Test',
            'query' => 'pretraga AND doma',
            'count' => 15,
            'classification' => 'zlato',
            'url' => 'https://example.com',
            'fetched_at' => now(),
        ]);

        $this->artisan('sudska-praksa:compare', [
            'search1' => $search1->id,
            'search2' => $search2->id,
        ])->assertSuccessful();
    }

    public function test_compare_fails_with_invalid_search_id(): void
    {
        $this->artisan('sudska-praksa:compare', [
            'search1' => 9999,
            'search2' => 9998,
        ])->assertFailed();
    }

    public function test_compare_respects_threshold_option(): void
    {
        // Create two search runs with small difference (diff = 2)
        $search1 = SudskaPraksaSearch::create([
            'name' => 'Run 1',
            'keywords_file' => 'test.json',
            'courts' => 'vks',
            'started_at' => now()->subDay(),
            'finished_at' => now()->subDay(),
        ]);
        SudskaPraksaResult::create([
            'search_id' => $search1->id,
            'category' => 'Test',
            'query' => 'small AND change',
            'count' => 10,
            'classification' => 'zlato',
            'url' => 'https://example.com',
            'fetched_at' => now()->subDay(),
        ]);

        $search2 = SudskaPraksaSearch::create([
            'name' => 'Run 2',
            'keywords_file' => 'test.json',
            'courts' => 'vks',
            'started_at' => now(),
            'finished_at' => now(),
        ]);
        SudskaPraksaResult::create([
            'search_id' => $search2->id,
            'category' => 'Test',
            'query' => 'small AND change',
            'count' => 12,
            'classification' => 'zlato',
            'url' => 'https://example.com',
            'fetched_at' => now(),
        ]);

        // With threshold=5, the diff=2 should be filtered out
        $this->artisan('sudska-praksa:compare', [
            'search1' => $search1->id,
            'search2' => $search2->id,
            '--threshold' => 5,
        ])->assertSuccessful()
          ->expectsOutputToContain('Ukupno promjena: 0');
    }

    public function test_compare_handles_new_queries_in_second_run(): void
    {
        $search1 = SudskaPraksaSearch::create([
            'name' => 'Run 1',
            'keywords_file' => 'test.json',
            'courts' => 'vks',
            'started_at' => now()->subDay(),
            'finished_at' => now()->subDay(),
        ]);
        // No results in first search

        $search2 = SudskaPraksaSearch::create([
            'name' => 'Run 2',
            'keywords_file' => 'test.json',
            'courts' => 'vks',
            'started_at' => now(),
            'finished_at' => now(),
        ]);
        SudskaPraksaResult::create([
            'search_id' => $search2->id,
            'category' => 'Test',
            'query' => 'new AND query',
            'count' => 20,
            'classification' => 'srebrno',
            'url' => 'https://example.com',
            'fetched_at' => now(),
        ]);

        $this->artisan('sudska-praksa:compare', [
            'search1' => $search1->id,
            'search2' => $search2->id,
        ])->assertSuccessful();
    }

    public function test_compare_handles_removed_queries_in_second_run(): void
    {
        $search1 = SudskaPraksaSearch::create([
            'name' => 'Run 1',
            'keywords_file' => 'test.json',
            'courts' => 'vks',
            'started_at' => now()->subDay(),
            'finished_at' => now()->subDay(),
        ]);
        SudskaPraksaResult::create([
            'search_id' => $search1->id,
            'category' => 'Test',
            'query' => 'removed AND query',
            'count' => 30,
            'classification' => 'srebrno',
            'url' => 'https://example.com',
            'fetched_at' => now()->subDay(),
        ]);

        $search2 = SudskaPraksaSearch::create([
            'name' => 'Run 2',
            'keywords_file' => 'test.json',
            'courts' => 'vks',
            'started_at' => now(),
            'finished_at' => now(),
        ]);
        // No results in second search

        $this->artisan('sudska-praksa:compare', [
            'search1' => $search1->id,
            'search2' => $search2->id,
        ])->assertSuccessful();
    }
}
