<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UnifiedSearchService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class SearchControllerTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        // Clear cache before each test
        Cache::flush();
    }

    /** @test */
    public function it_performs_unified_search_successfully()
    {
        $mockSearchService = Mockery::mock(UnifiedSearchService::class);
        $mockSearchService->shouldReceive('search')
            ->once()
            ->with('kazneni postupak', Mockery::type('array'))
            ->andReturn([
                'results' => [
                    [
                        'id' => 'law-1',
                        'corpus' => 'laws',
                        'title' => 'Zakon o kaznenom postupku',
                        'score' => 0.95,
                    ],
                    [
                        'id' => 'decision-1',
                        'corpus' => 'decisions',
                        'title' => 'Decision Rev-123/2024',
                        'score' => 0.88,
                    ],
                ],
                'total' => 2,
                'query' => 'kazneni postupak',
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockSearchService);

        $response = $this->postJson('/api/search', [
            'query' => 'kazneni postupak',
            'corpora' => ['laws', 'decisions'],
            'limit' => 10,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total' => 2,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'results' => [
                        '*' => ['id', 'corpus', 'title', 'score'],
                    ],
                    'total',
                ],
                'request_id',
                'response_time_ms',
                'cached',
            ]);
    }

    /** @test */
    public function it_searches_laws_only()
    {
        $mockSearchService = Mockery::mock(UnifiedSearchService::class);
        $mockSearchService->shouldReceive('search')
            ->once()
            ->with('Ustav RH', Mockery::on(function ($options) {
                return $options['corpora'] === ['laws'];
            }))
            ->andReturn([
                'results' => [
                    [
                        'id' => 'law-const',
                        'title' => 'Ustav Republike Hrvatske',
                        'law_number' => 'NN 56/90',
                        'score' => 0.98,
                    ],
                ],
                'total' => 1,
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockSearchService);

        $response = $this->postJson('/api/search/laws', [
            'query' => 'Ustav RH',
            'limit' => 5,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total' => 1,
                ],
            ]);
    }

    /** @test */
    public function it_searches_court_decisions_only()
    {
        $mockSearchService = Mockery::mock(UnifiedSearchService::class);
        $mockSearchService->shouldReceive('search')
            ->once()
            ->with('ECLI:HR:VSRH', Mockery::on(function ($options) {
                return $options['corpora'] === ['decisions'];
            }))
            ->andReturn([
                'results' => [
                    [
                        'id' => 'decision-1',
                        'ecli' => 'ECLI:HR:VSRH:2024:1234',
                        'court' => 'Vrhovni sud RH',
                        'score' => 0.92,
                    ],
                ],
                'total' => 1,
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockSearchService);

        $response = $this->postJson('/api/search/decisions', [
            'query' => 'ECLI:HR:VSRH',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function it_searches_case_documents_only()
    {
        $mockSearchService = Mockery::mock(UnifiedSearchService::class);
        $mockSearchService->shouldReceive('search')
            ->once()
            ->with('witness testimony', Mockery::on(function ($options) {
                return $options['corpora'] === ['cases'];
            }))
            ->andReturn([
                'results' => [
                    [
                        'id' => 'case-doc-1',
                        'category' => 'witness',
                        'title' => 'Witness Statement #1',
                        'score' => 0.87,
                    ],
                ],
                'total' => 1,
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockSearchService);

        $response = $this->postJson('/api/search/cases', [
            'query' => 'witness testimony',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function it_performs_hybrid_search()
    {
        $mockSearchService = Mockery::mock(UnifiedSearchService::class);
        $mockSearchService->shouldReceive('hybridSearch')
            ->once()
            ->with('kazneno djelo', Mockery::type('array'))
            ->andReturn([
                'results' => [
                    [
                        'id' => 'law-1',
                        'title' => 'Kazneni zakon',
                        'score' => 0.94,
                        'match_type' => 'hybrid',
                    ],
                ],
                'total' => 1,
                'search_type' => 'hybrid',
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockSearchService);

        $response = $this->postJson('/api/search/hybrid', [
            'query' => 'kazneno djelo',
            'corpora' => ['laws'],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'search_type' => 'hybrid',
                ],
            ]);
    }

    /** @test */
    public function it_searches_with_citations()
    {
        $mockSearchService = Mockery::mock(UnifiedSearchService::class);
        $mockSearchService->shouldReceive('searchWithCitations')
            ->once()
            ->with('ZKP čl. 291', Mockery::type('array'))
            ->andReturn([
                'results' => [
                    [
                        'id' => 'law-1',
                        'title' => 'Zakon o kaznenom postupku',
                        'score' => 0.96,
                        'citations' => [
                            ['article' => '291', 'context' => 'Procedure rules'],
                        ],
                    ],
                ],
                'total' => 1,
                'citation_count' => 1,
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockSearchService);

        $response = $this->postJson('/api/search/with-citations', [
            'query' => 'ZKP čl. 291',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'citation_count' => 1,
                ],
            ]);
    }

    /** @test */
    public function it_caches_search_results()
    {
        $mockSearchService = Mockery::mock(UnifiedSearchService::class);
        $mockSearchService->shouldReceive('search')
            ->once() // Should only be called once due to caching
            ->andReturn([
                'results' => [],
                'total' => 0,
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockSearchService);

        // First request - hits the service
        $response1 = $this->postJson('/api/search', [
            'query' => 'test query',
        ]);

        $response1->assertStatus(200)
            ->assertJson(['cached' => false]);

        // Second request - should be cached
        $response2 = $this->postJson('/api/search', [
            'query' => 'test query',
        ]);

        $response2->assertStatus(200)
            ->assertJson(['cached' => true]);
    }

    /** @test */
    public function it_includes_response_time_in_results()
    {
        $mockSearchService = Mockery::mock(UnifiedSearchService::class);
        $mockSearchService->shouldReceive('search')
            ->once()
            ->andReturn(['results' => [], 'total' => 0]);

        $this->app->instance(UnifiedSearchService::class, $mockSearchService);

        $response = $this->postJson('/api/search', [
            'query' => 'test',
        ]);

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertArrayHasKey('response_time_ms', $data);
        $this->assertIsNumeric($data['response_time_ms']);
    }

    /** @test */
    public function it_includes_unique_request_id()
    {
        $mockSearchService = Mockery::mock(UnifiedSearchService::class);
        $mockSearchService->shouldReceive('search')
            ->once()
            ->andReturn(['results' => [], 'total' => 0]);

        $this->app->instance(UnifiedSearchService::class, $mockSearchService);

        $response = $this->postJson('/api/search', [
            'query' => 'test',
        ]);

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertArrayHasKey('request_id', $data);
        $this->assertStringStartsWith('search_', $data['request_id']);
    }

    /** @test */
    public function it_handles_search_service_errors_gracefully()
    {
        $mockSearchService = Mockery::mock(UnifiedSearchService::class);
        $mockSearchService->shouldReceive('search')
            ->once()
            ->andThrow(new \Exception('Vector database unavailable'));

        $this->app->instance(UnifiedSearchService::class, $mockSearchService);

        $response = $this->postJson('/api/search', [
            'query' => 'test',
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonStructure([
                'success',
                'error',
                'request_id',
            ]);
    }

    /** @test */
    public function it_respects_pagination_parameters()
    {
        $mockSearchService = Mockery::mock(UnifiedSearchService::class);
        $mockSearchService->shouldReceive('search')
            ->once()
            ->with('test', Mockery::on(function ($options) {
                return $options['limit'] === 5
                    && $options['page'] === 2
                    && $options['per_page'] === 5;
            }))
            ->andReturn(['results' => [], 'total' => 0]);

        $this->app->instance(UnifiedSearchService::class, $mockSearchService);

        $response = $this->postJson('/api/search', [
            'query' => 'test',
            'limit' => 5,
            'page' => 2,
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_respects_threshold_parameter()
    {
        $mockSearchService = Mockery::mock(UnifiedSearchService::class);
        $mockSearchService->shouldReceive('search')
            ->once()
            ->with('test', Mockery::on(function ($options) {
                return $options['threshold'] === 0.85;
            }))
            ->andReturn(['results' => [], 'total' => 0]);

        $this->app->instance(UnifiedSearchService::class, $mockSearchService);

        $response = $this->postJson('/api/search', [
            'query' => 'test',
            'threshold' => 0.85,
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_respects_corpus_weights()
    {
        $mockSearchService = Mockery::mock(UnifiedSearchService::class);
        $mockSearchService->shouldReceive('search')
            ->once()
            ->with('test', Mockery::on(function ($options) {
                return $options['weights']['laws'] === 2.0
                    && $options['weights']['decisions'] === 1.5
                    && $options['weights']['cases'] === 1.0;
            }))
            ->andReturn(['results' => [], 'total' => 0]);

        $this->app->instance(UnifiedSearchService::class, $mockSearchService);

        $response = $this->postJson('/api/search', [
            'query' => 'test',
            'weights' => [
                'laws' => 2.0,
                'decisions' => 1.5,
                'cases' => 1.0,
            ],
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_respects_filters_parameter()
    {
        $mockSearchService = Mockery::mock(UnifiedSearchService::class);
        $mockSearchService->shouldReceive('search')
            ->once()
            ->with('test', Mockery::on(function ($options) {
                return $options['filters']['jurisdiction'] === 'HR'
                    && $options['filters']['year'] === 2024;
            }))
            ->andReturn(['results' => [], 'total' => 0]);

        $this->app->instance(UnifiedSearchService::class, $mockSearchService);

        $response = $this->postJson('/api/search', [
            'query' => 'test',
            'filters' => [
                'jurisdiction' => 'HR',
                'year' => 2024,
            ],
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_handles_croatian_unicode_queries()
    {
        $mockSearchService = Mockery::mock(UnifiedSearchService::class);
        $mockSearchService->shouldReceive('search')
            ->once()
            ->with('Člankom 291. ZKP-a', Mockery::type('array'))
            ->andReturn(['results' => [], 'total' => 0]);

        $this->app->instance(UnifiedSearchService::class, $mockSearchService);

        $response = $this->postJson('/api/search', [
            'query' => 'Člankom 291. ZKP-a',
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_uses_default_values_when_parameters_not_provided()
    {
        $mockSearchService = Mockery::mock(UnifiedSearchService::class);
        $mockSearchService->shouldReceive('search')
            ->once()
            ->with('test', Mockery::on(function ($options) {
                return $options['corpora'] === ['laws', 'decisions', 'cases']
                    && $options['limit'] === 10
                    && $options['threshold'] === 0.7
                    && $options['deduplicate'] === true;
            }))
            ->andReturn(['results' => [], 'total' => 0]);

        $this->app->instance(UnifiedSearchService::class, $mockSearchService);

        $response = $this->postJson('/api/search', [
            'query' => 'test',
        ]);

        $response->assertStatus(200);
    }
}
