<?php

namespace Tests\Unit\HealthChecks;

use App\HealthChecks\HealthCheckResult;
use App\HealthChecks\OpenAIHealthCheck;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OpenAIHealthCheckTest extends TestCase
{
    public function test_returns_healthy_when_circuit_closed(): void
    {
        Cache::put('circuit_breaker_openai_state', 'closed');
        config(['openai.api_key' => 'test-key']);

        $check = new OpenAIHealthCheck();
        $result = $check();

        $this->assertTrue($result->isHealthy());
    }

    public function test_returns_unhealthy_when_circuit_open(): void
    {
        Cache::put('circuit_breaker_openai_state', 'open');

        $check = new OpenAIHealthCheck();
        $result = $check();

        $this->assertTrue($result->isUnhealthy());
    }

    public function test_returns_degraded_when_half_open(): void
    {
        Cache::put('circuit_breaker_openai_state', 'half_open');

        $check = new OpenAIHealthCheck();
        $result = $check();

        $this->assertTrue($result->isDegraded());
    }
}
