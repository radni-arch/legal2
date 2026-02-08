<?php

namespace App\HealthChecks;

use Illuminate\Support\Facades\Log;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;

class Neo4jHealthCheck
{
    public function __invoke(): HealthCheckResult
    {
        $enabled = (bool) config('neo4j.sync.enabled', true);
        if (! $enabled) {
            return HealthCheckResult::degraded('Neo4j sync is disabled by configuration');
        }

        try {
            $uri = (string) config('neo4j.uri', 'bolt://localhost:7687');
            $user = (string) config('neo4j.user', 'neo4j');
            $password = (string) config('neo4j.password');

            $client = ClientBuilder::create()
                ->withDriver('bolt', $uri, Authenticate::basic($user, $password))
                ->withDefaultDriver('bolt')
                ->build();

            $client->run('RETURN 1 AS ping');

            return HealthCheckResult::healthy(['connection' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Neo4j health check failed', ['error' => $e->getMessage()]);

            return HealthCheckResult::unhealthy(
                'Neo4j connection failed: ' . $e->getMessage(),
                ['error' => $e->getMessage()]
            );
        }
    }
}
