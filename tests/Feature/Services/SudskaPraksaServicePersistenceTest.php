<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Services\SudskaPraksaService;
use App\Models\SudskaPraksaSearch;
use App\Models\SudskaPraksaResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SudskaPraksaServicePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_and_persist_creates_search_and_results(): void
    {
        Http::fake([
            'odluke.sudovi.hr/*' => Http::response(
                '<html><body>8 rezultata</body></html>',
                200
            ),
        ]);

        $service = new SudskaPraksaService();

        $categories = [
            [
                'name' => 'Test Category',
                'description' => 'Test description',
                'queries' => [
                    ['q' => 'test AND query', 'comment' => 'Test'],
                ],
            ],
        ];

        $search = $service->runAndPersist(
            categories: $categories,
            name: 'Test Search',
            keywordsFile: 'test.json',
            delayMs: 0,
        );

        $this->assertInstanceOf(SudskaPraksaSearch::class, $search);
        $this->assertEquals(1, $search->total_queries);
        $this->assertEquals(1, $search->results()->count());

        $result = $search->results->first();
        $this->assertEquals('test AND query', $result->query);
        $this->assertEquals(8, $result->count);
        $this->assertEquals('zlato', $result->classification);
    }

    public function test_run_and_persist_computes_stats_correctly(): void
    {
        Http::fake([
            'odluke.sudovi.hr/*' => Http::sequence()
                ->push('<html><body>3 rezultata</body></html>', 200)   // ultra
                ->push('<html><body>10 rezultata</body></html>', 200)  // zlato
                ->push('<html><body>30 rezultata</body></html>', 200)  // srebrno
                ->push('<html><body>100 rezultata</body></html>', 200), // bronca
        ]);

        $service = new SudskaPraksaService();

        $categories = [
            [
                'name' => 'Mixed Results',
                'queries' => [
                    ['q' => 'query1', 'comment' => 'Ultra result'],
                    ['q' => 'query2', 'comment' => 'Zlato result'],
                    ['q' => 'query3', 'comment' => 'Srebrno result'],
                    ['q' => 'query4', 'comment' => 'Bronca result'],
                ],
            ],
        ];

        $search = $service->runAndPersist(
            categories: $categories,
            name: 'Stats Test',
            keywordsFile: 'stats.json',
            delayMs: 0,
        );

        $this->assertEquals(4, $search->total_queries);
        $this->assertEquals(1, $search->ultra_count);
        $this->assertEquals(1, $search->zlato_count);
        $this->assertEquals(1, $search->srebrno_count);
        $this->assertEquals(1, $search->bronca_count);
    }

    public function test_run_and_persist_stores_metadata(): void
    {
        Http::fake([
            'odluke.sudovi.hr/*' => Http::response(
                '<html><body>5 rezultata</body></html>',
                200
            ),
        ]);

        $service = new SudskaPraksaService();

        $categories = [
            [
                'name' => 'Test',
                'queries' => [['q' => 'test query']],
            ],
        ];

        $metadata = [
            'case_name' => 'Test Case',
            'version' => '1.0',
        ];

        $search = $service->runAndPersist(
            categories: $categories,
            name: 'Metadata Test',
            keywordsFile: 'metadata.json',
            metadata: $metadata,
            delayMs: 0,
        );

        $this->assertEquals('Test Case', $search->metadata['case_name']);
        $this->assertEquals('1.0', $search->metadata['version']);
    }

    public function test_run_and_persist_sets_timestamps(): void
    {
        Http::fake([
            'odluke.sudovi.hr/*' => Http::response(
                '<html><body>5 rezultata</body></html>',
                200
            ),
        ]);

        $service = new SudskaPraksaService();

        $categories = [
            [
                'name' => 'Test',
                'queries' => [['q' => 'test query']],
            ],
        ];

        $search = $service->runAndPersist(
            categories: $categories,
            name: 'Timestamp Test',
            keywordsFile: 'timestamps.json',
            delayMs: 0,
        );

        $this->assertNotNull($search->started_at);
        $this->assertNotNull($search->finished_at);
        $this->assertTrue($search->finished_at->gte($search->started_at));
    }

    public function test_search_results_relationship_works(): void
    {
        Http::fake([
            'odluke.sudovi.hr/*' => Http::response(
                '<html><body>5 rezultata</body></html>',
                200
            ),
        ]);

        $service = new SudskaPraksaService();

        $categories = [
            [
                'name' => 'Test',
                'queries' => [
                    ['q' => 'query1'],
                    ['q' => 'query2'],
                ],
            ],
        ];

        $search = $service->runAndPersist(
            categories: $categories,
            name: 'Relationship Test',
            keywordsFile: 'relationship.json',
            delayMs: 0,
        );

        // Verify relationship from search to results
        $this->assertEquals(2, $search->results()->count());

        // Verify relationship from result to search
        $result = SudskaPraksaResult::first();
        $this->assertEquals($search->id, $result->search->id);
    }

    public function test_gold_results_scope_filters_correctly(): void
    {
        Http::fake([
            'odluke.sudovi.hr/*' => Http::sequence()
                ->push('<html><body>3 rezultata</body></html>', 200)   // ultra
                ->push('<html><body>10 rezultata</body></html>', 200)  // zlato
                ->push('<html><body>100 rezultata</body></html>', 200), // bronca (not gold)
        ]);

        $service = new SudskaPraksaService();

        $categories = [
            [
                'name' => 'Test',
                'queries' => [
                    ['q' => 'ultra query'],
                    ['q' => 'zlato query'],
                    ['q' => 'bronca query'],
                ],
            ],
        ];

        $search = $service->runAndPersist(
            categories: $categories,
            name: 'Gold Test',
            keywordsFile: 'gold.json',
            delayMs: 0,
        );

        // goldResults should only include ultra and zlato
        $goldResults = $search->goldResults()->get();
        $this->assertEquals(2, $goldResults->count());

        // Verify the gold scope on the Result model
        $allGold = SudskaPraksaResult::gold()->get();
        $this->assertEquals(2, $allGold->count());
    }
}
