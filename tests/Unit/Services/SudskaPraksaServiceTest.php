<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\SudskaPraksaService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SudskaPraksaServiceTest extends TestCase
{
    private SudskaPraksaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SudskaPraksaService();
    }

    public function test_fetch_result_count_parses_number(): void
    {
        Http::fake([
            'odluke.sudovi.hr/*' => Http::response(
                '<html><body>42 rezultata za vas upit</body></html>',
                200
            ),
        ]);

        $result = $this->service->fetchResultCount('pretraga AND doma');
        $this->assertEquals(42, $result);
    }

    public function test_fetch_result_count_returns_zero_for_no_results(): void
    {
        Http::fake([
            'odluke.sudovi.hr/*' => Http::response(
                '<html><body>Nema rezultata</body></html>',
                200
            ),
        ]);

        $result = $this->service->fetchResultCount('nonexistent AND query');
        $this->assertEquals(0, $result);
    }

    public function test_fetch_result_count_retries_on_server_error(): void
    {
        // First two calls return 500, third call succeeds
        Http::fake([
            'odluke.sudovi.hr/*' => Http::sequence()
                ->push('Server Error', 500)
                ->push('Server Error', 500)
                ->push('<html><body>10 rezultata</body></html>', 200),
        ]);

        // Temporarily reduce retry delay for testing
        config(['sudska-praksa.retry_delay_ms' => 1]);

        $result = $this->service->fetchResultCount('test AND query');
        $this->assertEquals(10, $result);

        // Verify 3 requests were made (2 retries + 1 success)
        Http::assertSentCount(3);
    }

    public function test_fetch_result_count_returns_negative_after_max_retries(): void
    {
        Http::fake([
            'odluke.sudovi.hr/*' => Http::response('Server Error', 500),
        ]);

        // Temporarily reduce retry delay for testing
        config(['sudska-praksa.retry_delay_ms' => 1]);

        $result = $this->service->fetchResultCount('error AND query');
        $this->assertEquals(-1, $result);
    }

    public function test_build_url_creates_correct_url(): void
    {
        $url = $this->service->buildUrl('pretraga AND doma', 'vks,vps');
        $this->assertStringContainsString('q=pretraga+AND+doma', $url);
        $this->assertStringContainsString('ct=vks%2Cvps', $url);
    }

    public function test_classify_result_count(): void
    {
        $this->assertEquals('ultra', $this->service->classify(3));
        $this->assertEquals('ultra', $this->service->classify(5));
        $this->assertEquals('zlato', $this->service->classify(6));
        $this->assertEquals('zlato', $this->service->classify(15));
        $this->assertEquals('srebrno', $this->service->classify(16));
        $this->assertEquals('srebrno', $this->service->classify(50));
        $this->assertEquals('bronca', $this->service->classify(51));
        $this->assertEquals('bronca', $this->service->classify(150));
        $this->assertEquals('bulk', $this->service->classify(500));
        $this->assertEquals('empty', $this->service->classify(0));
        $this->assertEquals('error', $this->service->classify(-1));
    }

    public function test_run_queries_returns_results(): void
    {
        Http::fake([
            'odluke.sudovi.hr/*' => Http::response(
                '<html><body>5 rezultata</body></html>',
                200
            ),
        ]);

        $categories = [
            [
                'name' => 'Test Category',
                'description' => 'Test category description',
                'queries' => [
                    ['q' => 'test AND query', 'comment' => 'Test comment'],
                ],
            ],
        ];

        $results = $this->service->runQueries($categories, delayMs: 0);

        $this->assertCount(1, $results);
        $this->assertEquals('test AND query', $results[0]['query']);
        $this->assertEquals(5, $results[0]['count']);
        $this->assertEquals('ultra', $results[0]['classification']);
        $this->assertEquals('Test Category', $results[0]['category']);
    }

    public function test_run_queries_handles_string_queries(): void
    {
        Http::fake([
            'odluke.sudovi.hr/*' => Http::response(
                '<html><body>8 rezultata</body></html>',
                200
            ),
        ]);

        $categories = [
            [
                'name' => 'Test',
                'queries' => ['simple AND query'],
            ],
        ];

        $results = $this->service->runQueries($categories, delayMs: 0);

        $this->assertCount(1, $results);
        $this->assertEquals('simple AND query', $results[0]['query']);
        $this->assertEquals(8, $results[0]['count']);
    }

    public function test_run_queries_calls_progress_callback(): void
    {
        Http::fake([
            'odluke.sudovi.hr/*' => Http::response(
                '<html><body>5 rezultata</body></html>',
                200
            ),
        ]);

        $categories = [
            [
                'name' => 'Test',
                'queries' => [
                    ['q' => 'query1 AND test'],
                    ['q' => 'query2 AND test'],
                ],
            ],
        ];

        $progressCalls = [];
        $results = $this->service->runQueries($categories, delayMs: 0, onProgress: function ($query, $count, $index, $total) use (&$progressCalls) {
            $progressCalls[] = compact('query', 'count', 'index', 'total');
        });

        $this->assertCount(2, $progressCalls);
        $this->assertEquals('query1 AND test', $progressCalls[0]['query']);
        $this->assertEquals(1, $progressCalls[0]['index']);
        $this->assertEquals(2, $progressCalls[0]['total']);
    }
}
