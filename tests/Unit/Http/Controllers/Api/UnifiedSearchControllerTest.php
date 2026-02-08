<?php

namespace Tests\Unit\Http\Controllers\Api;

use App\Http\Middleware\ApiTokenAuth;
use App\Services\UnifiedSearchService;
use Mockery;
use Tests\TestCase;

/**
 * Unified Search Controller Tests
 *
 * Tests API controller for cross-vector-store search with result merging.
 * Uses HTTP-level testing to properly exercise FormRequest validation.
 */
class UnifiedSearchControllerTest extends TestCase
{
    protected $searchServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->searchServiceMock = Mockery::mock(UnifiedSearchService::class);
        $this->app->instance(UnifiedSearchService::class, $this->searchServiceMock);
        $this->withoutMiddleware(ApiTokenAuth::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_searches_across_all_corpora_by_default(): void
    {
        $query = 'Kazneni zakon';

        $expectedResults = [
            'query' => $query,
            'total_results' => 15,
            'returned_results' => 10,
            'deduplicated_count' => 5,
            'corpora' => ['laws', 'decisions', 'cases'],
            'results' => [
                [
                    'type' => 'law',
                    'id' => 'law-1',
                    'title' => 'Kazneni zakon',
                    'snippet' => 'Članak 1...',
                    'score' => 0.95,
                    'metadata' => ['law_number' => 'NN 125/11'],
                ],
                [
                    'type' => 'decision',
                    'id' => 'decision-1',
                    'title' => 'Decision regarding Kazneni zakon',
                    'snippet' => 'Court decision text...',
                    'score' => 0.88,
                    'metadata' => ['court' => 'Vrhovni sud'],
                ],
            ],
            'filters' => [],
            'pagination' => ['page' => 1, 'per_page' => 10, 'offset' => 0, 'total_pages' => 2],
            'performance' => ['total_time' => 0.5, 'corpus_timing' => []],
        ];

        $this->searchServiceMock
            ->shouldReceive('search')
            ->once()
            ->with($query, Mockery::on(function ($options) {
                return $options['corpora'] === ['laws', 'decisions', 'cases']
                    && $options['limit'] === 10
                    && $options['threshold'] === 0.7;
            }))
            ->andReturn($expectedResults);

        $response = $this->postJson('/api/unified-search', ['query' => $query]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals($query, $data['query']);
        $this->assertEquals(15, $data['total_results']);
        $this->assertCount(2, $data['results']);
    }

    /** @test */
    public function it_filters_search_by_specific_corpora(): void
    {
        $query = 'ugovor';
        $corpora = ['laws'];

        $expectedResults = [
            'query' => $query,
            'total_results' => 8,
            'returned_results' => 8,
            'deduplicated_count' => 0,
            'corpora' => ['laws'],
            'results' => [
                [
                    'type' => 'law',
                    'id' => 'law-1',
                    'title' => 'Zakon o obveznim odnosima',
                    'snippet' => 'Ugovor je...',
                    'score' => 0.92,
                    'metadata' => ['law_number' => 'NN 35/05'],
                ],
            ],
            'filters' => [],
            'pagination' => ['page' => 1, 'per_page' => 10, 'offset' => 0, 'total_pages' => 1],
            'performance' => ['total_time' => 0.3, 'corpus_timing' => []],
        ];

        $this->searchServiceMock
            ->shouldReceive('search')
            ->once()
            ->with($query, Mockery::on(function ($options) use ($corpora) {
                return $options['corpora'] === $corpora;
            }))
            ->andReturn($expectedResults);

        $response = $this->postJson('/api/unified-search', [
            'query' => $query,
            'corpora' => $corpora,
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(['laws'], $data['corpora']);
        $this->assertCount(1, $data['results']);
    }

    /** @test */
    public function it_applies_filters_to_search(): void
    {
        $query = 'presuda';
        $filters = [
            'court' => 'Vrhovni sud',
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31',
        ];

        $expectedResults = [
            'query' => $query,
            'total_results' => 5,
            'returned_results' => 5,
            'deduplicated_count' => 0,
            'corpora' => ['laws', 'decisions', 'cases'],
            'results' => [
                [
                    'type' => 'decision',
                    'id' => 'decision-1',
                    'title' => 'Vrhovni sud presuda',
                    'snippet' => 'Decision from Vrhovni sud...',
                    'score' => 0.90,
                    'metadata' => [
                        'court' => 'Vrhovni sud',
                        'decision_date' => '2024-06-15',
                    ],
                ],
            ],
            'filters' => $filters,
            'pagination' => ['page' => 1, 'per_page' => 10, 'offset' => 0, 'total_pages' => 1],
            'performance' => ['total_time' => 0.4, 'corpus_timing' => []],
        ];

        $this->searchServiceMock
            ->shouldReceive('search')
            ->once()
            ->with($query, Mockery::on(function ($options) use ($filters) {
                return $options['filters'] === $filters;
            }))
            ->andReturn($expectedResults);

        $response = $this->postJson('/api/unified-search', [
            'query' => $query,
            'filters' => $filters,
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals($filters, $data['filters']);
        $this->assertEquals('Vrhovni sud', $data['results'][0]['metadata']['court']);
    }

    /** @test */
    public function it_supports_pagination(): void
    {
        $query = 'postupak';

        $expectedResults = [
            'query' => $query,
            'total_results' => 50,
            'returned_results' => 20,
            'deduplicated_count' => 0,
            'corpora' => ['laws', 'decisions', 'cases'],
            'results' => array_fill(0, 20, [
                'type' => 'law',
                'id' => 'law-1',
                'title' => 'Test',
                'snippet' => 'Test...',
                'score' => 0.85,
                'metadata' => [],
            ]),
            'filters' => [],
            'pagination' => [
                'page' => 2,
                'per_page' => 20,
                'offset' => 20,
                'total_pages' => 3,
            ],
            'performance' => ['total_time' => 0.6, 'corpus_timing' => []],
        ];

        $this->searchServiceMock
            ->shouldReceive('search')
            ->once()
            ->with($query, Mockery::on(function ($options) {
                return $options['page'] === 2
                    && $options['per_page'] === 20;
            }))
            ->andReturn($expectedResults);

        $response = $this->postJson('/api/unified-search', [
            'query' => $query,
            'page' => 2,
            'per_page' => 20,
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(2, $data['pagination']['page']);
        $this->assertEquals(20, $data['pagination']['per_page']);
        $this->assertEquals(50, $data['total_results']);
        $this->assertCount(20, $data['results']);
    }

    /** @test */
    public function it_validates_required_query_parameter(): void
    {
        $response = $this->postJson('/api/unified-search', []);

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('query', $data['errors']);
    }
}
