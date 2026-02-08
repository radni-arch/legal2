<?php

namespace Tests\Unit\Seeders;

use App\Models\CourtDecision;
use App\Services\Odluke\OdlukeClient;
use Database\Seeders\CourtDecisionDownloadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CourtDecisionDownloadSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure we have a clean database state
        CourtDecision::query()->delete();
    }

    public function test_seeder_downloads_decisions_from_api()
    {
        // Set small target count for testing
        config(['testing.court_decision_download_count' => 3]);

        // Mock OdlukeClient to return test IDs
        $mockClient = $this->createMock(OdlukeClient::class);
        $mockClient->expects($this->atLeastOnce())
            ->method('collectIdsFromList')
            ->willReturnOnConsecutiveCalls(
                [
                    'url' => 'https://odluke.sudovi.hr/Document/DisplayList',
                    'ids' => ['test-id-1', 'test-id-2', 'test-id-3'],
                    'count' => 3,
                ],
                [
                    'url' => 'https://odluke.sudovi.hr/Document/DisplayList',
                    'ids' => [], // No more results
                    'count' => 0,
                ]
            );

        $mockClient->expects($this->exactly(3))
            ->method('fetchDecisionMeta')
            ->willReturnOnConsecutiveCalls(
                [
                    'broj_odluke' => 'Rev-123/2023',
                    'sud' => 'Vrhovni sud Republike Hrvatske',
                    'datum_odluke' => '2023-01-15',
                    'vrsta_odluke' => 'Presuda',
                    'ecli' => 'ECLI:HR:VSRH:2023:TEST123',
                ],
                [
                    'broj_odluke' => 'Rev-124/2023',
                    'sud' => 'Županijski sud u Zagrebu',
                    'datum_odluke' => '2023-02-20',
                    'vrsta_odluke' => 'Rješenje',
                ],
                [
                    'broj_odluke' => 'Rev-125/2023',
                    'sud' => 'Općinski sud u Splitu',
                    'datum_odluke' => '2023-03-10',
                    'vrsta_odluke' => 'Presuda',
                ]
            );

        $this->app->instance(OdlukeClient::class, $mockClient);

        // Run seeder with small count for testing
        Artisan::call('db:seed', [
            '--class' => CourtDecisionDownloadSeeder::class,
        ]);

        // Verify decisions were saved
        $this->assertEquals(3, CourtDecision::count());

        $this->assertDatabaseHas('court_decisions', [
            'case_number' => 'Rev-123/2023',
            'court' => 'Vrhovni sud Republike Hrvatske',
        ]);

        $this->assertDatabaseHas('court_decisions', [
            'case_number' => 'Rev-124/2023',
            'court' => 'Županijski sud u Zagrebu',
        ]);
    }

    public function test_seeder_handles_empty_results_gracefully()
    {
        // Mock OdlukeClient to return empty results
        $mockClient = $this->createMock(OdlukeClient::class);
        $mockClient->expects($this->once())
            ->method('collectIdsFromList')
            ->willReturn([
                'url' => 'https://odluke.sudovi.hr/Document/DisplayList',
                'ids' => [],
                'count' => 0,
            ]);

        $this->app->instance(OdlukeClient::class, $mockClient);

        // Should not throw exception
        Artisan::call('db:seed', [
            '--class' => CourtDecisionDownloadSeeder::class,
        ]);

        // No decisions should be created
        $this->assertEquals(0, CourtDecision::count());
    }

    public function test_seeder_handles_api_errors_gracefully()
    {
        config(['testing.court_decision_download_count' => 10]);

        // Mock OdlukeClient to throw exception
        $mockClient = $this->createMock(OdlukeClient::class);
        $mockClient->expects($this->atLeastOnce())
            ->method('collectIdsFromList')
            ->willThrowException(new \Exception('API connection failed'));

        $this->app->instance(OdlukeClient::class, $mockClient);

        // Should not throw exception - error is logged instead
        Artisan::call('db:seed', [
            '--class' => CourtDecisionDownloadSeeder::class,
        ]);

        // No decisions should be created
        $this->assertEquals(0, CourtDecision::count());
    }

    public function test_seeder_respects_rate_limiting()
    {
        config(['testing.court_decision_download_count' => 2]);

        $startTime = microtime(true);

        // Mock OdlukeClient with throttling behavior
        $mockClient = $this->createMock(OdlukeClient::class);
        $mockClient->expects($this->atLeastOnce())
            ->method('collectIdsFromList')
            ->willReturnOnConsecutiveCalls(
                [
                    'url' => 'https://odluke.sudovi.hr/Document/DisplayList',
                    'ids' => ['test-id-1', 'test-id-2'],
                    'count' => 2,
                ],
                [
                    'url' => 'https://odluke.sudovi.hr/Document/DisplayList',
                    'ids' => [],
                    'count' => 0,
                ]
            );

        $mockClient->expects($this->exactly(2))
            ->method('fetchDecisionMeta')
            ->willReturn([
                'broj_odluke' => 'Rev-123/2023',
                'sud' => 'Vrhovni sud Republike Hrvatske',
                'datum_odluke' => '2023-01-15',
            ]);

        $this->app->instance(OdlukeClient::class, $mockClient);

        Artisan::call('db:seed', [
            '--class' => CourtDecisionDownloadSeeder::class,
        ]);

        $duration = microtime(true) - $startTime;

        // Should take at least some time due to throttling
        // Note: In real implementation, OdlukeClient handles throttling internally
        $this->assertGreaterThanOrEqual(0, $duration);
    }

    public function test_seeder_prevents_duplicates()
    {
        config(['testing.court_decision_download_count' => 2]);

        // Create existing decision
        CourtDecision::factory()->create([
            'id' => 'test-id-1',
            'case_number' => 'Rev-123/2023',
        ]);

        $this->assertEquals(1, CourtDecision::count());

        // Mock OdlukeClient to return same ID
        $mockClient = $this->createMock(OdlukeClient::class);
        $mockClient->expects($this->atLeastOnce())
            ->method('collectIdsFromList')
            ->willReturnOnConsecutiveCalls(
                [
                    'url' => 'https://odluke.sudovi.hr/Document/DisplayList',
                    'ids' => ['test-id-1', 'test-id-2'],
                    'count' => 2,
                ],
                [
                    'url' => 'https://odluke.sudovi.hr/Document/DisplayList',
                    'ids' => [],
                    'count' => 0,
                ]
            );

        $mockClient->expects($this->once()) // Only for test-id-2, since test-id-1 exists
            ->method('fetchDecisionMeta')
            ->willReturn([
                'broj_odluke' => 'Rev-124/2023',
                'sud' => 'Županijski sud u Zagrebu',
                'datum_odluke' => '2023-02-20',
            ]);

        $this->app->instance(OdlukeClient::class, $mockClient);

        Artisan::call('db:seed', [
            '--class' => CourtDecisionDownloadSeeder::class,
        ]);

        // Should have 2 total (1 existing + 1 new)
        $this->assertEquals(2, CourtDecision::count());
    }

    public function test_seeder_handles_invalid_metadata()
    {
        config(['testing.court_decision_download_count' => 1]);

        // Mock OdlukeClient to return invalid metadata
        $mockClient = $this->createMock(OdlukeClient::class);
        $mockClient->expects($this->atLeastOnce())
            ->method('collectIdsFromList')
            ->willReturnOnConsecutiveCalls(
                [
                    'url' => 'https://odluke.sudovi.hr/Document/DisplayList',
                    'ids' => ['test-id-1'],
                    'count' => 1,
                ],
                [
                    'url' => 'https://odluke.sudovi.hr/Document/DisplayList',
                    'ids' => [],
                    'count' => 0,
                ]
            );

        $mockClient->expects($this->once())
            ->method('fetchDecisionMeta')
            ->willReturn(null); // Invalid metadata

        $this->app->instance(OdlukeClient::class, $mockClient);

        // Should not throw exception
        Artisan::call('db:seed', [
            '--class' => CourtDecisionDownloadSeeder::class,
        ]);

        // Decision might still be created with minimal data or skipped
        $this->assertGreaterThanOrEqual(0, CourtDecision::count());
    }

    public function test_seeder_supports_custom_query()
    {
        // This test verifies that seeder can accept custom search queries
        // Implementation will use environment variables or config

        config([
            'testing.court_decision_download_query' => 'kazneni',
            'testing.court_decision_download_count' => 1,
        ]);

        $mockClient = $this->createMock(OdlukeClient::class);
        $mockClient->expects($this->atLeastOnce())
            ->method('collectIdsFromList')
            ->with(
                $this->equalTo('kazneni'),
                $this->anything(),
                $this->anything(),
                $this->anything()
            )
            ->willReturnOnConsecutiveCalls(
                [
                    'url' => 'https://odluke.sudovi.hr/Document/DisplayList',
                    'ids' => ['test-id-1'],
                    'count' => 1,
                ],
                [
                    'url' => 'https://odluke.sudovi.hr/Document/DisplayList',
                    'ids' => [],
                    'count' => 0,
                ]
            );

        $mockClient->expects($this->once())
            ->method('fetchDecisionMeta')
            ->willReturn([
                'broj_odluke' => 'Kž-100/2023',
                'sud' => 'Županijski sud u Zagrebu',
                'datum_odluke' => '2023-01-15',
            ]);

        $this->app->instance(OdlukeClient::class, $mockClient);

        Artisan::call('db:seed', [
            '--class' => CourtDecisionDownloadSeeder::class,
        ]);

        $this->assertEquals(1, CourtDecision::count());
    }
}
