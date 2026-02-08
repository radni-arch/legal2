<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected ?User $apiUser = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure app key exists for encryption/cookies in HTTP tests
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        // Provide a dummy OpenAI API key so the service doesn't throw
        config(['openai.api_key' => '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']);
        // Make @vite a no-op in tests to prevent missing manifest/dev-server issues
        Blade::directive('vite', fn ($expression) => '');
    }

    /**
     * Create an authenticated user with API token for testing API endpoints.
     */
    protected function createAuthenticatedApiUser(): User
    {
        $user = User::factory()->create([
            'api_token' => Str::random(60),
        ]);

        return $user;
    }

    /**
     * Set up API authentication for subsequent requests.
     *
     * @return $this
     */
    protected function withApiAuth(?User $user = null): static
    {
        $this->apiUser = $user ?? $this->createAuthenticatedApiUser();

        return $this;
    }

    /**
     * Override json to include API auth if set
     */
    public function json($method, $uri, array $data = [], array $headers = [], $options = 0)
    {
        if ($this->apiUser) {
            $headers['Authorization'] = 'Bearer '.$this->apiUser->api_token;
        }

        return parent::json($method, $uri, $data, $headers, $options);
    }

    /**
     * Override postJson to include API auth if set
     */
    public function postJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        if ($this->apiUser) {
            $headers['Authorization'] = 'Bearer '.$this->apiUser->api_token;
        }

        return parent::postJson($uri, $data, $headers, $options);
    }

    /**
     * Override getJson to include API auth if set
     */
    public function getJson($uri, array $headers = [], $options = 0)
    {
        if ($this->apiUser) {
            $headers['Authorization'] = 'Bearer '.$this->apiUser->api_token;
        }

        return parent::getJson($uri, $headers, $options);
    }
}
