<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentGenerationRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $apiToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiToken = Str::random(64);
        $this->user = User::factory()->create([
            'api_token' => $this->apiToken,
        ]);
    }

    public function test_document_runs_endpoint_includes_rate_limit_headers(): void
    {
        $response = $this->withToken($this->apiToken)
            ->getJson('/api/documents/runs');

        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    public function test_document_generation_route_is_rate_limited(): void
    {
        $route = collect(app('router')->getRoutes())
            ->first(fn ($route) => $route->uri() === 'api/documents/generate'
                && in_array('POST', $route->methods(), true));

        $this->assertNotNull($route, 'Expected documents/generate route to be registered.');
        $this->assertContains('api.token', $route->middleware());
        $this->assertContains('throttle:60,1', $route->middleware());
    }
}
