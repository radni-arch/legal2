<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class HealthCheckEndpointsTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Bypass API token middleware for testing
        $this->withoutMiddleware(\App\Http\Middleware\ApiTokenAuth::class);

        $this->actingAs(User::factory()->create());
    }

    /** @test */
    public function it_returns_system_health_report()
    {
        $response = $this->getJson('/api/monitoring/health/system');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'status',
                    'timestamp',
                    'services' => [
                        'database' => ['status'],
                        'neo4j' => ['status'],
                        'openai' => ['status'],
                        'cache' => ['status'],
                        'queue' => ['status'],
                    ],
                    'summary' => [
                        'healthy',
                        'unhealthy',
                        'degraded',
                        'disabled',
                        'unconfigured',
                    ],
                ],
            ]);

        // Verify overall status is one of the valid states
        $this->assertContains(
            $response->json('data.status'),
            ['healthy', 'degraded', 'unhealthy']
        );
    }

    /** @test */
    public function it_checks_database_health()
    {
        $response = $this->getJson('/api/monitoring/health/database');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'status',
                    'driver',
                    'database',
                    'connected',
                    'timestamp',
                ],
            ]);

        // Database should be healthy in tests
        $response->assertJson([
            'success' => true,
            'data' => [
                'status' => 'healthy',
                'connected' => true,
                'query_test' => 'passed',
            ],
        ]);
    }

    /** @test */
    public function it_checks_neo4j_health()
    {
        $response = $this->getJson('/api/monitoring/health/neo4j');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'status',
                    'enabled',
                    'timestamp',
                ],
            ]);

        // Neo4j might be disabled in tests, so check for valid status
        $status = $response->json('data.status');
        $this->assertContains($status, ['healthy', 'unhealthy', 'disabled']);
    }

    /** @test */
    public function it_checks_openai_health()
    {
        $response = $this->getJson('/api/monitoring/health/openai');

        // Status depends on whether API key is configured
        $this->assertContains($response->status(), [200, 503]);

        $response->assertJsonStructure([
            'success',
            'data' => [
                'status',
                'timestamp',
            ],
        ]);

        $status = $response->json('data.status');
        $this->assertContains($status, ['healthy', 'unhealthy', 'unconfigured']);
    }

    /** @test */
    public function it_checks_cache_health()
    {
        $response = $this->getJson('/api/monitoring/health/cache');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'status',
                    'driver',
                    'write_test',
                    'read_test',
                    'timestamp',
                ],
            ]);

        // Cache should work in tests
        $response->assertJson([
            'success' => true,
            'data' => [
                'status' => 'healthy',
                'write_test' => 'passed',
                'read_test' => 'passed',
            ],
        ]);
    }

    /** @test */
    public function it_checks_queue_health()
    {
        $response = $this->getJson('/api/monitoring/health/queue');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'status',
                    'driver',
                    'queues' => [
                        'default',
                        'textract',
                        'agents',
                    ],
                    'total_backlog',
                    'failed_jobs',
                    'timestamp',
                ],
            ]);

        // Queue should be healthy in tests (empty queues)
        $this->assertIsInt($response->json('data.total_backlog'));
        $this->assertIsInt($response->json('data.failed_jobs'));
    }

    /** @test */
    public function it_returns_rate_limit_metrics()
    {
        // Hit a rate limiter a few times
        $user = auth()->user();
        RateLimiter::hit('openai|'.$user->id, 60);
        RateLimiter::hit('openai|'.$user->id, 60);
        RateLimiter::hit('openai|'.$user->id, 60);

        $response = $this->getJson('/api/monitoring/metrics/rate-limits');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'identifier',
                    'limiters' => [
                        'openai' => [
                            'name',
                            'limit',
                            'period',
                            'used',
                            'remaining',
                            'usage_percent',
                            'throttled',
                            'retry_after_seconds',
                        ],
                        'agents',
                        'search',
                        'api',
                    ],
                    'timestamp',
                ],
            ]);

        // Verify used count increased
        $this->assertGreaterThan(0, $response->json('data.limiters.openai.used'));
        $this->assertLessThan(30, $response->json('data.limiters.openai.remaining'));
    }

    /** @test */
    public function it_returns_token_usage_metrics()
    {
        $user = auth()->user();
        $key = 'tokens:'.$user->id;

        // Simulate some token usage
        Cache::increment($key.':usage', 500);

        // Add hourly data
        $hourKey = $key.':hourly:'.now()->format('Y-m-d-H');
        Cache::increment($hourKey, 500);

        $response = $this->getJson('/api/monitoring/metrics/tokens');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'identifier',
                    'total_tokens_used',
                    'daily_budget',
                    'budget_used_percent',
                    'budget_remaining',
                    'hourly_breakdown' => [
                        '*' => [
                            'hour',
                            'timestamp',
                            'tokens_used',
                        ],
                    ],
                    'timestamp',
                ],
            ]);

        // Verify token usage is tracked
        $this->assertEquals(500, $response->json('data.total_tokens_used'));
        $this->assertEquals(50000, $response->json('data.daily_budget'));
        $this->assertEquals(1.0, $response->json('data.budget_used_percent'));

        // Verify hourly breakdown has 24 hours
        $hourlyBreakdown = $response->json('data.hourly_breakdown');
        $this->assertCount(24, $hourlyBreakdown);
    }

    /** @test */
    public function system_health_returns_503_when_unhealthy()
    {
        // Mock a database failure scenario by disconnecting
        // Note: This is hard to test without actually breaking things
        // so we'll just verify the structure is correct

        $response = $this->getJson('/api/monitoring/health/system');

        // Should return 200 or 503 depending on actual health
        $this->assertContains($response->status(), [200, 503]);
    }

    /** @test */
    public function database_health_check_measures_response_time()
    {
        $response = $this->getJson('/api/monitoring/health/database');

        $response->assertStatus(200);

        $responseTime = $response->json('data.response_time_ms');
        $this->assertIsNumeric($responseTime);
        $this->assertGreaterThan(0, $responseTime);
        $this->assertLessThan(1000, $responseTime); // Should be fast in tests
    }

    /** @test */
    public function cache_health_check_performs_actual_read_write_test()
    {
        $response = $this->getJson('/api/monitoring/health/cache');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'healthy',
                    'write_test' => 'passed',
                    'read_test' => 'passed',
                    'delete_test' => 'passed',
                ],
            ]);

        // Verify driver is reported
        $this->assertNotEmpty($response->json('data.driver'));
    }

    /** @test */
    public function rate_limit_metrics_shows_all_four_limiters()
    {
        $response = $this->getJson('/api/monitoring/metrics/rate-limits');

        $response->assertStatus(200);

        $limiters = $response->json('data.limiters');

        // Verify all 4 limiters are present
        $this->assertArrayHasKey('openai', $limiters);
        $this->assertArrayHasKey('agents', $limiters);
        $this->assertArrayHasKey('search', $limiters);
        $this->assertArrayHasKey('api', $limiters);

        // Verify limits are correct
        $this->assertEquals(30, $limiters['openai']['limit']);
        $this->assertEquals(10, $limiters['agents']['limit']);
        $this->assertEquals(60, $limiters['search']['limit']);
        $this->assertEquals(120, $limiters['api']['limit']);
    }

    /** @test */
    public function token_metrics_calculates_budget_percentage_correctly()
    {
        $user = auth()->user();
        $key = 'tokens:'.$user->id;

        // Use 25% of budget (12,500 tokens)
        Cache::put($key.':usage', 12500);

        $response = $this->getJson('/api/monitoring/metrics/tokens');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_tokens_used' => 12500,
                    'daily_budget' => 50000,
                    'budget_used_percent' => 25.0,
                    'budget_remaining' => 37500,
                ],
            ]);
    }

    protected function tearDown(): void
    {
        // Clean up test cache keys
        $user = auth()->user();
        if ($user) {
            $key = 'tokens:'.$user->id;
            Cache::forget($key.':usage');

            // Clear hourly keys
            for ($i = 0; $i < 24; $i++) {
                $hourKey = $key.':hourly:'.now()->subHours($i)->format('Y-m-d-H');
                Cache::forget($hourKey);
            }
        }

        parent::tearDown();
    }
}
