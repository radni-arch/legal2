<?php

namespace Tests\Unit\HealthChecks;

use App\HealthChecks\HealthCheckResult;
use App\HealthChecks\Neo4jHealthCheck;
use Tests\TestCase;

class Neo4jHealthCheckTest extends TestCase
{
    public function test_returns_degraded_when_neo4j_disabled(): void
    {
        config(['neo4j.sync.enabled' => false]);
        $check = new Neo4jHealthCheck();
        $result = $check();

        $this->assertInstanceOf(HealthCheckResult::class, $result);
        $this->assertTrue($result->isDegraded());
    }
}
