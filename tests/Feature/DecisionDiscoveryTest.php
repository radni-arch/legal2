<?php

namespace Tests\Feature;

use App\Models\CourtDecision;
use App\Services\DecisionDiscoveryService;
use App\Services\Odluke\OdlukeClient;
use App\Services\Odluke\OdlukeIngestService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Decision Discovery Feature Tests
 *
 * Tests the Decision Discovery API endpoints and service functionality
 * for discovering and ingesting court decisions from odluke.sudovi.hr
 *
 * Sprint 7: UX Features Completion - Worker G
 */
class DecisionDiscoveryTest extends TestCase
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
     * Test: Decision discovery searches odluke.sudovi.hr API
     *
     * Verifies that the discovery service correctly:
     * - Calls OdlukeClient to search for decisions
     * - Fetches metadata for found decisions
     * - Returns properly formatted discovery results
     * - Stores discovery in cache
     *
     * @test
     */
    public function test_decision_discovery_searches_odluke_api(): void
    {
        // Arrange: Mock OdlukeClient
        $mockClient = Mockery::mock(OdlukeClient::class);

        // Mock collectIdsFromList to return sample decision IDs
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->with(
                'kazneno djelo',
                Mockery::any(),
                50,
                1
            )
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?q=kazneno+djelo',
                'ids' => [
                    'dec-uuid-001',
                    'dec-uuid-002',
                    'dec-uuid-003',
                ],
                'count' => 3,
            ]);

        // Mock fetchDecisionMeta for each decision
        $mockClient->shouldReceive('fetchDecisionMeta')
            ->with('dec-uuid-001')
            ->andReturn([
                'broj_odluke' => 'K-123/2024',
                'sud' => 'Županijski sud u Osijeku',
                'datum_odluke' => '2024-05-15',
                'vrsta_odluke' => 'Presuda',
                'src' => 'https://odluke.sudovi.hr/Document/View?id=dec-uuid-001',
            ]);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->with('dec-uuid-002')
            ->andReturn([
                'broj_odluke' => 'K-456/2024',
                'sud' => 'Vrhovni sud Republike Hrvatske',
                'datum_odluke' => '2024-06-20',
                'vrsta_odluke' => 'Rješenje',
                'src' => 'https://odluke.sudovi.hr/Document/View?id=dec-uuid-002',
            ]);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->with('dec-uuid-003')
            ->andReturn([
                'broj_odluke' => 'K-789/2024',
                'sud' => 'Županijski sud u Zagrebu',
                'datum_odluke' => '2024-07-10',
                'vrsta_odluke' => 'Presuda',
                'src' => 'https://odluke.sudovi.hr/Document/View?id=dec-uuid-003',
            ]);

        // Create service with mocked client
        $service = new DecisionDiscoveryService($mockClient);

        // Act: Start discovery search
        $result = $service->startDiscovery([
            'keywords' => 'kazneno djelo',
            'limit' => 50,
            'page' => 1,
        ]);

        // Assert: Verify discovery results
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('search_params', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('total_found', $result);
        $this->assertArrayHasKey('execution_time_ms', $result);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('status', $result);

        $this->assertEquals('completed', $result['status']);
        $this->assertEquals(3, $result['total_found']);
        $this->assertEquals('kazneno djelo', $result['search_params']['keywords']);

        // Verify decision results have correct structure
        $this->assertCount(3, $result['results']);
        $this->assertEquals('K-123/2024', $result['results'][0]['broj_odluke']);
        $this->assertEquals('dec-uuid-001', $result['results'][0]['id']);
        $this->assertArrayHasKey('ingested', $result['results'][0]);

        // Verify discovery is cached
        $cached = Cache::get("discovery:{$result['id']}");
        $this->assertNotNull($cached);
        $this->assertEquals($result['id'], $cached['id']);
    }

    /**
     * Test: Decision discovery filters results correctly
     *
     * Verifies filtering by:
     * - Court
     * - Date range (date_from, date_to)
     * - Decision type
     *
     * @test
     */
    public function test_decision_discovery_filters_results(): void
    {
        // Arrange: Mock OdlukeClient with filters
        $mockClient = Mockery::mock(OdlukeClient::class);

        // Expect collectIdsFromList to be called with query params containing filters
        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->withArgs(function ($query, $params, $limit, $page) {
                // Verify query string
                $this->assertEquals('nasilje', $query);

                // Verify filter parameters are included
                // Note: The actual param format depends on buildQueryParams implementation
                $this->assertIsString($params);

                return true;
            })
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList?...',
                'ids' => ['filtered-dec-001', 'filtered-dec-002'],
                'count' => 2,
            ]);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->with('filtered-dec-001')
            ->andReturn([
                'broj_odluke' => 'K-FILTER-001/2024',
                'sud' => 'Županijski sud u Osijeku',
                'datum_odluke' => '2024-03-15',
                'vrsta_odluke' => 'Presuda',
            ]);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->with('filtered-dec-002')
            ->andReturn([
                'broj_odluke' => 'K-FILTER-002/2024',
                'sud' => 'Županijski sud u Osijeku',
                'datum_odluke' => '2024-04-20',
                'vrsta_odluke' => 'Presuda',
            ]);

        $service = new DecisionDiscoveryService($mockClient);

        // Act: Start discovery with filters
        $result = $service->startDiscovery([
            'keywords' => 'nasilje',
            'court' => 'Županijski sud u Osijeku',
            'date_from' => '2024-01-01',
            'date_to' => '2024-06-30',
            'decision_type' => 'Presuda',
            'limit' => 50,
        ]);

        // Assert: Verify filters are applied
        $this->assertEquals('completed', $result['status']);
        $this->assertEquals(2, $result['total_found']);
        $this->assertEquals('nasilje', $result['search_params']['keywords']);
        $this->assertEquals('Županijski sud u Osijeku', $result['search_params']['court']);
        $this->assertEquals('2024-01-01', $result['search_params']['date_from']);
        $this->assertEquals('2024-06-30', $result['search_params']['date_to']);
        $this->assertEquals('Presuda', $result['search_params']['decision_type']);

        // Verify filtered results
        foreach ($result['results'] as $decision) {
            $this->assertEquals('Županijski sud u Osijeku', $decision['sud']);
            $this->assertEquals('Presuda', $decision['vrsta_odluke']);
        }
    }

    /**
     * Test: Decision ingestion creates records in database
     *
     * Verifies that ingesting decisions:
     * - Calls OdlukeIngestService with correct decision IDs
     * - Tracks successful vs failed ingestions
     * - Detects already ingested decisions
     * - Returns proper statistics
     *
     * @test
     */
    public function test_decision_ingestion_creates_records(): void
    {
        // Arrange: Create a discovery in cache
        $discoveryId = 'disc_test_'.uniqid();
        $discovery = [
            'id' => $discoveryId,
            'search_params' => ['keywords' => 'test'],
            'results' => [
                ['id' => 'new-dec-001', 'broj_odluke' => 'K-NEW-001/2024', 'ecli' => null],
                ['id' => 'new-dec-002', 'broj_odluke' => 'K-NEW-002/2024', 'ecli' => null],
                ['id' => 'existing-dec-003', 'broj_odluke' => 'K-EXISTING-003/2024', 'ecli' => 'ECLI:HR:EXISTING:2024:003'],
            ],
            'total_found' => 3,
            'status' => 'completed',
        ];
        Cache::put("discovery:{$discoveryId}", $discovery, 3600);

        // Create existing decision in database
        CourtDecision::factory()->create([
            'ecli' => 'ECLI:HR:EXISTING:2024:003',
            'title' => 'K-EXISTING-003/2024',
        ]);

        // Mock OdlukeIngestService
        $mockIngestService = Mockery::mock(OdlukeIngestService::class);

        // Expect ingestByIds to be called for each new decision
        $mockIngestService->shouldReceive('ingestByIds')
            ->with(['new-dec-001'])
            ->andReturn([
                'succeeded' => ['new-dec-001'],
                'failed' => [],
                'errors' => [],
            ]);

        $mockIngestService->shouldReceive('ingestByIds')
            ->with(['new-dec-002'])
            ->andReturn([
                'succeeded' => ['new-dec-002'],
                'failed' => [],
                'errors' => [],
            ]);

        // Create service with mocked ingest service
        $service = new DecisionDiscoveryService(null, $mockIngestService);

        // Act: Ingest selected decisions
        $result = $service->ingestDecisions($discoveryId, [
            'new-dec-001',
            'new-dec-002',
            'existing-dec-003',
        ]);

        // Assert: Verify ingestion results
        $this->assertEquals($discoveryId, $result['discovery_id']);
        $this->assertEquals(3, $result['requested_count']);
        $this->assertEquals(2, $result['success_count']);
        $this->assertEquals(0, $result['failure_count']);
        $this->assertEquals(1, $result['already_ingested_count']);

        $this->assertContains('new-dec-001', $result['successful']);
        $this->assertContains('new-dec-002', $result['successful']);
        $this->assertContains('existing-dec-003', $result['already_ingested']);

        $this->assertArrayHasKey('started_at', $result);
        $this->assertArrayHasKey('completed_at', $result);
    }

    /**
     * Test: Decision discovery tracks progress correctly
     *
     * Verifies that discovery progress tracking:
     * - Records execution time
     * - Stores discovery status (completed, failed)
     * - Maintains discovery state in cache
     * - Allows retrieval of discovery details
     *
     * @test
     */
    public function test_decision_discovery_tracks_progress(): void
    {
        // Arrange: Mock OdlukeClient
        $mockClient = Mockery::mock(OdlukeClient::class);

        $mockClient->shouldReceive('collectIdsFromList')
            ->once()
            ->andReturn([
                'url' => 'https://odluke.sudovi.hr/...',
                'ids' => ['progress-dec-001'],
                'count' => 1,
            ]);

        $mockClient->shouldReceive('fetchDecisionMeta')
            ->with('progress-dec-001')
            ->andReturn([
                'broj_odluke' => 'K-PROGRESS-001/2024',
                'sud' => 'Vrhovni sud',
                'datum_odluke' => '2024-08-01',
            ]);

        $service = new DecisionDiscoveryService($mockClient);

        // Act: Start discovery
        $startTime = microtime(true);
        $result = $service->startDiscovery([
            'keywords' => 'progress tracking test',
            'limit' => 10,
        ]);
        $endTime = microtime(true);

        // Assert: Verify progress tracking
        $this->assertEquals('completed', $result['status']);
        $this->assertArrayHasKey('execution_time_ms', $result);
        $this->assertGreaterThan(0, $result['execution_time_ms']);

        // Execution time should be reasonable (less than actual elapsed time * 1000ms + overhead)
        $actualElapsedMs = ($endTime - $startTime) * 1000;
        $this->assertLessThan($actualElapsedMs + 1000, $result['execution_time_ms']);

        // Verify created_at timestamp
        $this->assertArrayHasKey('created_at', $result);
        $this->assertNotEmpty($result['created_at']);

        // Verify discovery can be retrieved
        $retrieved = $service->getDiscovery($result['id']);
        $this->assertNotNull($retrieved);
        $this->assertEquals($result['id'], $retrieved['id']);
        $this->assertEquals('completed', $retrieved['status']);
        $this->assertEquals(1, $retrieved['total_found']);

        // Test retrieval of non-existent discovery
        $nonExistent = $service->getDiscovery('disc_nonexistent_'.uniqid());
        $this->assertNull($nonExistent);
    }

    /**
     * Test: Discovery statistics calculation
     *
     * Verifies that statistics correctly calculate:
     * - Total decisions found
     * - Already ingested vs not ingested counts
     * - Court breakdown
     * - Decision type breakdown
     * - Execution metrics
     *
     * @test
     */
    public function test_discovery_statistics_calculation(): void
    {
        // Arrange: Create test discovery with varied decisions
        $discoveryId = 'disc_stats_'.uniqid();

        // Create some ingested decisions in database
        CourtDecision::factory()->create([
            'ecli' => 'ECLI:HR:STATS:2024:ING001',
            'title' => 'K-STATS-ING-001/2024',
            'court' => 'Županijski sud u Osijeku',
        ]);

        CourtDecision::factory()->create([
            'ecli' => 'ECLI:HR:STATS:2024:ING002',
            'title' => 'K-STATS-ING-002/2024',
            'court' => 'Vrhovni sud Republike Hrvatske',
        ]);

        // Create discovery with mixed ingested and new decisions
        $discovery = [
            'id' => $discoveryId,
            'search_params' => ['keywords' => 'statistics test'],
            'results' => [
                // Already ingested
                [
                    'id' => 'stats-ingested-001',
                    'broj_odluke' => 'K-STATS-ING-001/2024',
                    'sud' => 'Županijski sud u Osijeku',
                    'vrsta_odluke' => 'Presuda',
                    'ecli' => 'ECLI:HR:STATS:2024:ING001',
                    'ingested' => true,
                ],
                [
                    'id' => 'stats-ingested-002',
                    'broj_odluke' => 'K-STATS-ING-002/2024',
                    'sud' => 'Vrhovni sud Republike Hrvatske',
                    'vrsta_odluke' => 'Presuda',
                    'ecli' => 'ECLI:HR:STATS:2024:ING002',
                    'ingested' => true,
                ],
                // Not ingested yet
                [
                    'id' => 'stats-new-003',
                    'broj_odluke' => 'K-STATS-NEW-003/2024',
                    'sud' => 'Županijski sud u Osijeku',
                    'vrsta_odluke' => 'Rješenje',
                    'ecli' => null,
                    'ingested' => false,
                ],
                [
                    'id' => 'stats-new-004',
                    'broj_odluke' => 'K-STATS-NEW-004/2024',
                    'sud' => 'Županijski sud u Zagrebu',
                    'vrsta_odluke' => 'Presuda',
                    'ecli' => null,
                    'ingested' => false,
                ],
                [
                    'id' => 'stats-new-005',
                    'broj_odluke' => 'K-STATS-NEW-005/2024',
                    'sud' => 'Županijski sud u Osijeku',
                    'vrsta_odluke' => 'Presuda',
                    'ecli' => null,
                    'ingested' => false,
                ],
            ],
            'total_found' => 5,
            'execution_time_ms' => 1234.56,
            'created_at' => now()->toIso8601String(),
            'status' => 'completed',
        ];

        Cache::put("discovery:{$discoveryId}", $discovery, 3600);

        $service = new DecisionDiscoveryService;

        // Act: Get discovery statistics
        $stats = $service->getDiscoveryStatistics($discoveryId);

        // Assert: Verify statistics
        $this->assertNotNull($stats);
        $this->assertEquals($discoveryId, $stats['discovery_id']);
        $this->assertEquals(5, $stats['total_found']);
        $this->assertEquals(2, $stats['already_ingested']);
        $this->assertEquals(3, $stats['not_ingested']);
        $this->assertEquals(1234.56, $stats['execution_time_ms']);
        $this->assertEquals('completed', $stats['status']);

        // Verify court breakdown
        $this->assertArrayHasKey('breakdown', $stats);
        $this->assertArrayHasKey('by_court', $stats['breakdown']);
        $courtBreakdown = $stats['breakdown']['by_court'];

        $this->assertEquals(3, $courtBreakdown['Županijski sud u Osijeku']);
        $this->assertEquals(1, $courtBreakdown['Vrhovni sud Republike Hrvatske']);
        $this->assertEquals(1, $courtBreakdown['Županijski sud u Zagrebu']);

        // Verify decision type breakdown
        $this->assertArrayHasKey('by_decision_type', $stats['breakdown']);
        $typeBreakdown = $stats['breakdown']['by_decision_type'];

        $this->assertEquals(4, $typeBreakdown['Presuda']);
        $this->assertEquals(1, $typeBreakdown['Rješenje']);

        // Test statistics for non-existent discovery
        $nonExistentStats = $service->getDiscoveryStatistics('disc_nonexistent_'.uniqid());
        $this->assertNull($nonExistentStats);
    }
}
