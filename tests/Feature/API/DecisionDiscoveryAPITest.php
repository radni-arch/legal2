<?php

namespace Tests\Feature\API;

use App\Models\CourtDecision;
use App\Services\Odluke\OdlukeClient;
use App\Services\Odluke\OdlukeIngestService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Decision Discovery API Integration Tests
 *
 * Comprehensive API testing for the Decision Discovery endpoints created in Sprint 7.
 * Tests full request/response cycles, authentication, validation, and data persistence.
 *
 * Sprint 8: API Integration Testing
 *
 * Endpoints Tested:
 * - POST /api/decisions/discover
 * - GET /api/decisions/discoveries
 * - GET /api/decisions/discoveries/{id}
 * - POST /api/decisions/discoveries/{id}/ingest
 * - GET /api/decisions/discoveries/{id}/stats
 */
class DecisionDiscoveryAPITest extends TestCase
{
    use UsesTestDatabase;

    protected string $apiToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Set API token for authentication
        $this->apiToken = config('api.token', 'test-token-12345');
        config(['api.token' => $this->apiToken]);
    }

    /**
     * Test: POST /api/decisions/discover - Start new discovery
     *
     * @test
     */
    public function test_api_can_start_new_discovery(): void
    {
        // Mock OdlukeClient
        $mockClient = Mockery::mock(OdlukeClient::class);
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/...',
                'ids' => ['test-id-1', 'test-id-2'],
                'count' => 2,
            ]);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->with('test-id-1')
            ->andReturn([
                'broj_odluke' => 'K-API-001/2024',
                'sud' => 'Vrhovni sud',
                'datum_odluke' => '2024-11-01',
                'vrsta_odluke' => 'Presuda',
                'ecli' => 'ECLI:HR:VSRH:2024:001',
            ]);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->with('test-id-2')
            ->andReturn([
                'broj_odluke' => 'K-API-002/2024',
                'sud' => 'Županijski sud u Osijeku',
                'datum_odluke' => '2024-11-02',
                'vrsta_odluke' => 'Rješenje',
                'ecli' => 'ECLI:HR:ZUP:2024:002',
            ]);

        $this->app->instance(OdlukeClient::class, $mockClient);

        // Make API request
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Accept' => 'application/json',
        ])->postJson('/api/decisions/discover', [
            'keywords' => 'kazneno djelo',
            'court' => 'Vrhovni sud',
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31',
            'limit' => 50,
        ]);

        // Assert response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'discovery' => [
                    'id',
                    'search_params',
                    'results',
                    'total_found',
                    'execution_time_ms',
                    'created_at',
                    'status',
                ],
            ])
            ->assertJson([
                'success' => true,
                'discovery' => [
                    'status' => 'completed',
                    'total_found' => 2,
                ],
            ]);

        $discoveryId = $response->json('discovery.id');
        $this->assertNotNull($discoveryId);

        // Verify discovery is cached
        $cached = Cache::get("discovery:{$discoveryId}");
        $this->assertNotNull($cached);
        $this->assertEquals(2, $cached['total_found']);
    }

    /**
     * Test: POST /api/decisions/discover - Validation errors
     *
     * @test
     */
    public function test_api_discovery_validates_required_fields(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Accept' => 'application/json',
        ])->postJson('/api/decisions/discover', [
            // Missing required 'keywords' field
            'limit' => 50,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'error',
                'errors',
            ])
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonValidationErrors(['keywords']);
    }

    /**
     * Test: POST /api/decisions/discover - Validates date range
     *
     * @test
     */
    public function test_api_discovery_validates_date_range(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Accept' => 'application/json',
        ])->postJson('/api/decisions/discover', [
            'keywords' => 'test',
            'date_from' => '2024-12-31',
            'date_to' => '2024-01-01', // date_to before date_from
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    /**
     * Test: GET /api/decisions/discoveries/{id} - Get discovery details
     *
     * @test
     */
    public function test_api_can_retrieve_discovery_details(): void
    {
        // Create a discovery in cache
        $discoveryId = 'disc_test_'.uniqid();
        $discovery = [
            'id' => $discoveryId,
            'search_params' => ['keywords' => 'test'],
            'results' => [
                [
                    'id' => 'decision-1',
                    'broj_odluke' => 'K-123/2024',
                    'sud' => 'Vrhovni sud',
                ],
            ],
            'total_found' => 1,
            'execution_time_ms' => 123.45,
            'created_at' => now()->toIso8601String(),
            'status' => 'completed',
        ];
        Cache::put("discovery:{$discoveryId}", $discovery, 3600);

        // Retrieve via API
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Accept' => 'application/json',
        ])->getJson("/api/decisions/discoveries/{$discoveryId}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'discovery' => [
                    'id' => $discoveryId,
                    'status' => 'completed',
                    'total_found' => 1,
                ],
            ]);
    }

    /**
     * Test: GET /api/decisions/discoveries/{id} - Not found
     *
     * @test
     */
    public function test_api_returns_404_for_nonexistent_discovery(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Accept' => 'application/json',
        ])->getJson('/api/decisions/discoveries/nonexistent-id');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'Discovery not found or expired',
            ]);
    }

    /**
     * Test: POST /api/decisions/discoveries/{id}/ingest - Batch ingestion
     *
     * @test
     */
    public function test_api_can_ingest_decisions(): void
    {
        // Setup
        $discoveryId = 'disc_ingest_'.uniqid();
        $discovery = [
            'id' => $discoveryId,
            'search_params' => ['keywords' => 'test'],
            'results' => [
                ['id' => 'new-dec-1', 'ecli' => null],
                ['id' => 'new-dec-2', 'ecli' => null],
                ['id' => 'existing-dec', 'ecli' => 'ECLI:HR:EXISTING:2024:001'],
            ],
            'total_found' => 3,
            'status' => 'completed',
        ];
        Cache::put("discovery:{$discoveryId}", $discovery, 3600);

        // Create existing decision
        CourtDecision::factory()->create([
            'ecli' => 'ECLI:HR:EXISTING:2024:001',
            'title' => 'Existing Decision',
        ]);

        // Mock ingestion service
        $mockIngestService = Mockery::mock(OdlukeIngestService::class);
        $mockIngestService->shouldReceive('ingestByIds')
            ->with(['new-dec-1'])
            ->andReturn(['succeeded' => ['new-dec-1'], 'failed' => [], 'errors' => []]);

        $mockIngestService->shouldReceive('ingestByIds')
            ->with(['new-dec-2'])
            ->andReturn(['succeeded' => ['new-dec-2'], 'failed' => [], 'errors' => []]);

        $this->app->instance(OdlukeIngestService::class, $mockIngestService);

        // Make API request
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Accept' => 'application/json',
        ])->postJson("/api/decisions/discoveries/{$discoveryId}/ingest", [
            'decision_ids' => ['new-dec-1', 'new-dec-2', 'existing-dec'],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'result' => [
                    'discovery_id' => $discoveryId,
                    'requested_count' => 3,
                    'success_count' => 2,
                    'already_ingested_count' => 1,
                ],
            ]);
    }

    /**
     * Test: GET /api/decisions/discoveries/{id}/stats - Get statistics
     *
     * @test
     */
    public function test_api_can_get_discovery_statistics(): void
    {
        // Setup discovery with statistics
        $discoveryId = 'disc_stats_'.uniqid();

        CourtDecision::factory()->create([
            'ecli' => 'ECLI:HR:STATS:2024:001',
            'court' => 'Vrhovni sud',
        ]);

        $discovery = [
            'id' => $discoveryId,
            'search_params' => ['keywords' => 'stats test'],
            'results' => [
                [
                    'id' => 'stats-1',
                    'sud' => 'Vrhovni sud',
                    'vrsta_odluke' => 'Presuda',
                    'ecli' => 'ECLI:HR:STATS:2024:001',
                    'ingested' => true,
                ],
                [
                    'id' => 'stats-2',
                    'sud' => 'Županijski sud u Osijeku',
                    'vrsta_odluke' => 'Presuda',
                    'ecli' => null,
                    'ingested' => false,
                ],
                [
                    'id' => 'stats-3',
                    'sud' => 'Vrhovni sud',
                    'vrsta_odluke' => 'Rješenje',
                    'ecli' => null,
                    'ingested' => false,
                ],
            ],
            'total_found' => 3,
            'execution_time_ms' => 456.78,
            'created_at' => now()->toIso8601String(),
            'status' => 'completed',
        ];
        Cache::put("discovery:{$discoveryId}", $discovery, 3600);

        // Get statistics
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Accept' => 'application/json',
        ])->getJson("/api/decisions/discoveries/{$discoveryId}/stats");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'statistics' => [
                    'discovery_id' => $discoveryId,
                    'total_found' => 3,
                    'already_ingested' => 1,
                    'not_ingested' => 2,
                    'status' => 'completed',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'statistics' => [
                    'breakdown' => [
                        'by_court',
                        'by_decision_type',
                    ],
                ],
            ]);

        // Verify breakdown data
        $stats = $response->json('statistics');
        $this->assertEquals(2, $stats['breakdown']['by_court']['Vrhovni sud']);
        $this->assertEquals(1, $stats['breakdown']['by_court']['Županijski sud u Osijeku']);
        $this->assertEquals(2, $stats['breakdown']['by_decision_type']['Presuda']);
        $this->assertEquals(1, $stats['breakdown']['by_decision_type']['Rješenje']);
    }

    /**
     * Test: API requires authentication
     *
     * @test
     */
    public function test_api_requires_authentication(): void
    {
        // Request without token
        $response = $this->postJson('/api/decisions/discover', [
            'keywords' => 'test',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test: API respects rate limiting
     *
     * @test
     */
    public function test_api_respects_rate_limiting(): void
    {
        // Make multiple requests rapidly
        $responses = [];
        for ($i = 0; $i < 65; $i++) { // Rate limit is 60/min
            $response = $this->withHeaders([
                'Authorization' => 'Bearer '.$this->apiToken,
                'Accept' => 'application/json',
            ])->getJson('/api/decisions/discoveries');

            $responses[] = $response->status();

            if ($response->status() === 429) {
                break; // Rate limit hit
            }
        }

        // Should eventually hit rate limit
        $this->assertContains(429, $responses, 'Rate limiting should be enforced');
    }

    /**
     * Test: API handles invalid JSON
     *
     * @test
     */
    public function test_api_handles_invalid_json(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post('/api/decisions/discover', 'invalid json');

        $response->assertStatus(400);
    }

    /**
     * Test: API validates decision_ids array for ingestion
     *
     * @test
     */
    public function test_api_validates_ingestion_request(): void
    {
        $discoveryId = 'disc_test_'.uniqid();
        Cache::put("discovery:{$discoveryId}", ['id' => $discoveryId], 3600);

        // Empty array
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Accept' => 'application/json',
        ])->postJson("/api/decisions/discoveries/{$discoveryId}/ingest", [
            'decision_ids' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['decision_ids']);

        // Too many IDs (limit is 50)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Accept' => 'application/json',
        ])->postJson("/api/decisions/discoveries/{$discoveryId}/ingest", [
            'decision_ids' => array_fill(0, 51, 'id'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['decision_ids']);
    }
}
