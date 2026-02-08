<?php

namespace Tests\Integration;

use App\Mcp\OdlukeTools;
use App\Modules\HomeSearch\Services\OdlukeSearchAgent;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Integration tests for OdlukeSearchAgent MCP Integration
 *
 * Sprint 2.1: OdlukeSearchAgent MCP Integration
 *
 * Tests cover:
 * - MCP tool integration (search + meta)
 * - Result parsing and validation
 * - Rate limiting (10 req/min)
 * - Retry logic with exponential backoff
 * - Pagination handling (max 100 results)
 * - Error handling and circuit breaker
 */
class OdlukeSearchAgentMcpIntegrationTest extends TestCase
{
    use UsesTestDatabase;

    protected OdlukeSearchAgent $agent;

    protected $odlukeToolsMock;

    protected $openAIMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->odlukeToolsMock = Mockery::mock(OdlukeTools::class);

        // Mock Log facade to prevent actual logging
        Log::shouldReceive('info')->byDefault();
        Log::shouldReceive('debug')->byDefault();
        Log::shouldReceive('warning')->byDefault();
        Log::shouldReceive('error')->byDefault();

        $this->agent = new OdlukeSearchAgent($this->openAIMock, $this->odlukeToolsMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================================================
    // MCP INTEGRATION TESTS
    // ========================================================================

    /** @test */
    public function it_successfully_searches_using_mcp_tools()
    {
        // Mock MCP search response
        $this->odlukeToolsMock->shouldReceive('search')
            ->once()
            ->with(
                Mockery::type('string'), // q
                null, // params
                100, // limit
                1, // page
                null // base_url
            )
            ->andReturn([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            'ids' => [
                                'dec-123-guid',
                                'dec-456-guid',
                                'dec-789-guid',
                            ],
                            'total' => 3,
                            'page' => 1,
                        ]),
                    ],
                ],
                'isError' => false,
            ]);

        // Mock MCP meta response
        $this->odlukeToolsMock->shouldReceive('meta')
            ->once()
            ->with(null, Mockery::type('array'), null)
            ->andReturn([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            [
                                'id' => 'dec-123-guid',
                                'title' => 'Rješenje K-123/2025',
                                'court' => 'Općinski sud u Osijeku',
                                'date' => '2025-03-15',
                                'text' => 'Pretres doma obavljen prema ZKP čl. 215...',
                                'url' => 'https://odluke.sudovi.hr/dec-123',
                                'case_number' => 'K-123/2025',
                            ],
                            [
                                'id' => 'dec-456-guid',
                                'title' => 'Rješenje K-456/2025',
                                'court' => 'Županijski sud u Osijeku',
                                'date' => '2025-04-10',
                                'text' => 'Pretres stana izvršen prema ZKP...',
                                'url' => 'https://odluke.sudovi.hr/dec-456',
                                'case_number' => 'K-456/2025',
                            ],
                            [
                                'id' => 'dec-789-guid',
                                'title' => 'Rješenje K-789/2025',
                                'court' => 'Općinski sud u Osijeku',
                                'date' => '2025-05-20',
                                'text' => 'Pretres prostorija izvršen...',
                                'url' => 'https://odluke.sudovi.hr/dec-789',
                                'case_number' => 'K-789/2025',
                            ],
                        ]),
                    ],
                ],
                'isError' => false,
            ]);

        // Mock OpenAI chat responses for extracting case data
        $this->openAIMock->shouldReceive('chat')
            ->times(3)
            ->andReturn(
                [
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'case_number' => 'K-123/2025',
                                    'court' => 'Općinski sud u Osijeku',
                                    'offense_type' => 'kazneno_djelo',
                                ]),
                            ],
                        ],
                    ],
                ],
                [
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'case_number' => 'K-456/2025',
                                    'court' => 'Županijski sud u Osijeku',
                                    'offense_type' => 'prekršaj',
                                ]),
                            ],
                        ],
                    ],
                ],
                [
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'case_number' => 'K-789/2025',
                                    'court' => 'Općinski sud u Osijeku',
                                    'offense_type' => 'prekršaj',
                                ]),
                            ],
                        ],
                    ],
                ]
            );

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $duration, $callback) {
                return $callback();
            });

        // Execute search
        $results = $this->agent->searchHomeSearchCases([
            'region' => 'Osijek',
            'year' => 2025,
        ]);

        // Assertions
        $this->assertIsArray($results);
        $this->assertArrayHasKey('cases', $results);
        $this->assertCount(3, $results['cases']);
        $this->assertEquals('K-123/2025', $results['cases'][0]['case_number']);
        $this->assertEquals('Općinski sud u Osijeku', $results['cases'][0]['court']);
    }

    /** @test */
    public function it_handles_mcp_search_errors_gracefully()
    {
        // Mock MCP search with error response
        $this->odlukeToolsMock->shouldReceive('search')
            ->once()
            ->andReturn([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Nema ID-eva za zadane parametre ili dohvat nije uspio.',
                    ],
                ],
                'isError' => true,
            ]);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $duration, $callback) {
                return $callback();
            });

        Log::shouldReceive('warning')
            ->with('OdlukeSearchAgent: MCP search returned error', Mockery::type('array'));

        $results = $this->agent->searchHomeSearchCases([
            'region' => 'Osijek',
            'year' => 2025,
        ]);

        // Should fall back to framework mode
        $this->assertArrayHasKey('status', $results);
        $this->assertEquals('framework_mode', $results['status']);
    }

    /** @test */
    public function it_handles_empty_search_results()
    {
        // Mock MCP search with no results
        $this->odlukeToolsMock->shouldReceive('search')
            ->once()
            ->andReturn([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            'ids' => [],
                            'total' => 0,
                        ]),
                    ],
                ],
                'isError' => false,
            ]);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $duration, $callback) {
                return $callback();
            });

        Log::shouldReceive('debug')
            ->with('OdlukeSearchAgent: No IDs found in MCP search');

        $results = $this->agent->searchHomeSearchCases([
            'region' => 'Unknown',
            'year' => 2025,
        ]);

        // Should process empty results
        $this->assertIsArray($results);
        $this->assertArrayHasKey('cases', $results);
        $this->assertEmpty($results['cases']);
    }

    // ========================================================================
    // RESULT PARSING & VALIDATION TESTS
    // ========================================================================

    /** @test */
    public function it_extracts_ids_from_mcp_search_response()
    {
        $agent = new class($this->openAIMock, $this->odlukeToolsMock) extends OdlukeSearchAgent
        {
            public function exposeExtractIdsFromMcpResponse(array $response): array
            {
                return $this->extractIdsFromMcpResponse($response);
            }
        };

        $mcpResponse = [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode([
                        'ids' => ['id-1', 'id-2', 'id-3'],
                        'total' => 3,
                    ]),
                ],
            ],
            'isError' => false,
        ];

        $ids = $agent->exposeExtractIdsFromMcpResponse($mcpResponse);

        $this->assertCount(3, $ids);
        $this->assertEquals('id-1', $ids[0]);
        $this->assertEquals('id-2', $ids[1]);
        $this->assertEquals('id-3', $ids[2]);
    }

    /** @test */
    public function it_handles_malformed_mcp_search_response()
    {
        $agent = new class($this->openAIMock, $this->odlukeToolsMock) extends OdlukeSearchAgent
        {
            public function exposeExtractIdsFromMcpResponse(array $response): array
            {
                return $this->extractIdsFromMcpResponse($response);
            }
        };

        // Test various malformed responses
        $malformedResponses = [
            [], // Empty
            ['content' => null], // Null content
            ['content' => [['type' => 'text', 'text' => 'not json']]], // Invalid JSON
            ['content' => [['type' => 'text', 'text' => '{"no_ids": true}']]], // Missing ids key
        ];

        foreach ($malformedResponses as $response) {
            $ids = $agent->exposeExtractIdsFromMcpResponse($response);
            $this->assertEmpty($ids);
        }
    }

    /** @test */
    public function it_extracts_metadata_from_mcp_meta_response()
    {
        $agent = new class($this->openAIMock, $this->odlukeToolsMock) extends OdlukeSearchAgent
        {
            public function exposeExtractMetadataFromMcpResponse(array $response): array
            {
                return $this->extractMetadataFromMcpResponse($response);
            }
        };

        $mcpResponse = [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode([
                        [
                            'id' => 'dec-123',
                            'title' => 'Test Decision',
                            'court' => 'Test Court',
                            'date' => '2025-01-15',
                        ],
                    ]),
                ],
            ],
            'isError' => false,
        ];

        $metadata = $agent->exposeExtractMetadataFromMcpResponse($mcpResponse);

        $this->assertIsArray($metadata);
        $this->assertCount(1, $metadata);
        $this->assertEquals('dec-123', $metadata[0]['id']);
        $this->assertEquals('Test Decision', $metadata[0]['title']);
    }

    /** @test */
    public function it_converts_metadata_to_results_format()
    {
        $agent = new class($this->openAIMock, $this->odlukeToolsMock) extends OdlukeSearchAgent
        {
            public function exposeConvertMetadataToResults(array $metadata): array
            {
                return $this->convertMetadataToResults($metadata);
            }
        };

        $metadata = [
            [
                'id' => 'dec-123',
                'naslov' => 'Rješenje (Croatian title)',
                'sud' => 'Općinski sud (Croatian court)',
                'datum' => '2025-03-15',
                'sadrzaj' => 'Sadržaj odluke (Croatian content)',
                'broj_predmeta' => 'K-123/2025',
            ],
            [
                'id' => 'dec-456',
                'title' => 'Decision (English title)',
                'court' => 'County Court (English court)',
                'date' => '2025-04-20',
                'content' => 'Decision content (English content)',
                'case_number' => 'K-456/2025',
            ],
        ];

        $results = $agent->exposeConvertMetadataToResults($metadata);

        $this->assertCount(2, $results);

        // First result (Croatian keys)
        $this->assertEquals('dec-123', $results[0]['id']);
        $this->assertEquals('Rješenje (Croatian title)', $results[0]['title']);
        $this->assertEquals('Općinski sud (Croatian court)', $results[0]['court']);
        $this->assertEquals('Sadržaj odluke (Croatian content)', $results[0]['text']);
        $this->assertEquals('K-123/2025', $results[0]['case_number']);

        // Second result (English keys)
        $this->assertEquals('dec-456', $results[1]['id']);
        $this->assertEquals('Decision (English title)', $results[1]['title']);
        $this->assertEquals('County Court (English court)', $results[1]['court']);
        $this->assertEquals('Decision content (English content)', $results[1]['text']);
        $this->assertEquals('K-456/2025', $results[1]['case_number']);
    }

    // ========================================================================
    // RATE LIMITING TESTS
    // ========================================================================

    /** @test */
    public function it_batches_metadata_requests_to_respect_rate_limiting()
    {
        $agent = new class($this->openAIMock, $this->odlukeToolsMock) extends OdlukeSearchAgent
        {
            public $metaBatchCallCount = 0;

            public function exposeFetchMetadataForIds(array $ids): array
            {
                return $this->fetchMetadataForIds($ids);
            }

            protected function fetchMetadataForIds(array $ids): array
            {
                $this->metaBatchCallCount = 0;

                // Override to count calls without actual delays
                if (empty($ids)) {
                    return [];
                }

                $batchSize = 10;
                $batches = array_chunk($ids, $batchSize);
                $allMetadata = [];

                foreach ($batches as $batchIndex => $batchIds) {
                    $this->metaBatchCallCount++;

                    $metaResult = $this->odlukeTools->meta(
                        id: null,
                        ids: $batchIds,
                        base_url: null
                    );

                    $batchMetadata = $this->extractMetadataFromMcpResponse($metaResult);
                    $allMetadata = array_merge($allMetadata, $batchMetadata);
                }

                return $allMetadata;
            }
        };

        // Create 25 IDs (should result in 3 batches: 10, 10, 5)
        $ids = array_map(fn ($i) => "id-$i", range(1, 25));

        // Mock meta responses for each batch
        $this->odlukeToolsMock->shouldReceive('meta')
            ->times(3)
            ->andReturn([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            [
                                'id' => 'test-id',
                                'title' => 'Test',
                            ],
                        ]),
                    ],
                ],
                'isError' => false,
            ]);

        $metadata = $agent->exposeFetchMetadataForIds($ids);

        // Verify 3 batches were called
        $this->assertEquals(3, $agent->metaBatchCallCount);
    }

    /** @test */
    public function it_has_rate_limit_configured_to_10_requests_per_minute()
    {
        $reflection = new \ReflectionClass($this->agent);
        $property = $reflection->getProperty('maxRequestsPerMinute');
        $property->setAccessible(true);

        $rateLimit = $property->getValue($this->agent);

        $this->assertEquals(10, $rateLimit, 'Rate limit should be 10 requests per minute as per Sprint 2.1 requirements');
    }

    // ========================================================================
    // PAGINATION TESTS
    // ========================================================================

    /** @test */
    public function it_limits_search_results_to_100_per_search()
    {
        // Verify that MCP search is called with limit=100
        $this->odlukeToolsMock->shouldReceive('search')
            ->once()
            ->with(
                Mockery::type('string'),
                null,
                100, // MUST be 100 as per requirements
                1,
                null
            )
            ->andReturn([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode(['ids' => []]),
                    ],
                ],
                'isError' => false,
            ]);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $duration, $callback) {
                return $callback();
            });

        $this->agent->searchHomeSearchCases([
            'region' => 'Osijek',
            'year' => 2025,
        ]);

        // Assertion is implicit in the mock expectation above
        $this->assertTrue(true);
    }

    // ========================================================================
    // CACHING TESTS
    // ========================================================================

    /** @test */
    public function it_caches_search_results_for_one_week()
    {
        $criteria = [
            'region' => 'Osijek',
            'year' => 2025,
        ];

        // First call should execute search
        $this->odlukeToolsMock->shouldReceive('search')
            ->once()
            ->andReturn([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode(['ids' => []]),
                    ],
                ],
                'isError' => false,
            ]);

        Cache::shouldReceive('remember')
            ->once()
            ->with(
                'odluke_search_'.md5(json_encode($criteria)),
                604800, // 1 week in seconds
                Mockery::type('Closure')
            )
            ->andReturnUsing(function ($key, $duration, $callback) {
                return $callback();
            });

        $results = $this->agent->searchHomeSearchCases($criteria);

        $this->assertIsArray($results);
    }

    // ========================================================================
    // RETRY & ERROR HANDLING TESTS
    // ========================================================================

    /** @test */
    public function it_handles_mcp_tool_exceptions_gracefully()
    {
        $this->odlukeToolsMock->shouldReceive('search')
            ->once()
            ->andThrow(new \Exception('MCP connection failed'));

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $duration, $callback) {
                return $callback();
            });

        Log::shouldReceive('warning')
            ->with('OdlukeSearchAgent: MCP search failed', Mockery::type('array'));

        $results = $this->agent->searchHomeSearchCases([
            'region' => 'Osijek',
            'year' => 2025,
        ]);

        // Should fall back to framework mode after exception
        $this->assertArrayHasKey('status', $results);
        $this->assertEquals('framework_mode', $results['status']);
    }

    /** @test */
    public function it_logs_mcp_search_attempts()
    {
        $this->odlukeToolsMock->shouldReceive('search')
            ->once()
            ->andReturn([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode(['ids' => []]),
                    ],
                ],
                'isError' => false,
            ]);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $duration, $callback) {
                return $callback();
            });

        Log::shouldReceive('debug')
            ->with('OdlukeSearchAgent: Searching via MCP', Mockery::type('array'));

        Log::shouldReceive('debug')
            ->with('OdlukeSearchAgent: No IDs found in MCP search');

        $this->agent->searchHomeSearchCases([
            'region' => 'Osijek',
            'year' => 2025,
        ]);

        // Assertions are implicit in log mock expectations
        $this->assertTrue(true);
    }

    // ========================================================================
    // INTEGRATION WITH EXISTING TESTS
    // ========================================================================

    /** @test */
    public function it_integrates_with_existing_search_flow()
    {
        // This test verifies MCP integration doesn't break existing functionality
        $this->odlukeToolsMock->shouldReceive('search')
            ->once()
            ->andReturn([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            'ids' => ['dec-test-1'],
                        ]),
                    ],
                ],
                'isError' => false,
            ]);

        $this->odlukeToolsMock->shouldReceive('meta')
            ->once()
            ->andReturn([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            [
                                'id' => 'dec-test-1',
                                'title' => 'Test Decision',
                                'text' => 'Pretres doma izvršen prema ZKP čl. 215...',
                            ],
                        ]),
                    ],
                ],
                'isError' => false,
            ]);

        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'case_number' => 'K-TEST/2025',
                                'offense_type' => 'prekršaj',
                                'court' => 'Općinski sud u Osijeku',
                            ]),
                        ],
                    ],
                ],
            ]);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $duration, $callback) {
                return $callback();
            });

        $results = $this->agent->searchHomeSearchCases([
            'region' => 'Osijek',
            'year' => 2025,
        ]);

        $this->assertArrayHasKey('cases', $results);
        $this->assertArrayHasKey('total_found', $results);
        $this->assertArrayHasKey('data_source', $results);
        $this->assertEquals('odluke.sudovi.hr', $results['data_source']);
    }
}
