<?php

namespace App\HealthChecks;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OpenAIHealthCheck
{
    public function __invoke(): HealthCheckResult
    {
        try {
            $state = Cache::get('circuit_breaker_openai_state', 'closed');

            $data = [
                'circuit_state' => $state,
                'api_key_configured' => ! empty(config('openai.api_key')),
            ];

            if ($state === 'open') {
                return HealthCheckResult::unhealthy(
                    'OpenAI circuit breaker is OPEN - service unavailable',
                    $data
                );
            }

            if ($state === 'half_open') {
                return HealthCheckResult::degraded(
                    'OpenAI circuit breaker is HALF_OPEN - recovering',
                    $data
                );
            }

            if (empty(config('openai.api_key'))) {
                return HealthCheckResult::unhealthy(
                    'OpenAI API key not configured',
                    $data
                );
            }

            return HealthCheckResult::healthy($data, 'OpenAI service is available');
        } catch (\Exception $e) {
            Log::error('OpenAI health check failed', ['error' => $e->getMessage()]);

            return HealthCheckResult::unhealthy(
                'OpenAI health check error: ' . $e->getMessage(),
                ['error' => $e->getMessage()]
            );
        }
    }
}
