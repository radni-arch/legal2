<?php

namespace Tests\Unit\HealthChecks;

use App\HealthChecks\HealthCheckResult;
use App\HealthChecks\QueueHealthCheck;
use Tests\TestCase;

class QueueHealthCheckTest extends TestCase
{
    public function test_returns_healthy_for_sync_driver(): void
    {
        $check = new QueueHealthCheck();
        $result = $check();

        $this->assertInstanceOf(HealthCheckResult::class, $result);
        $this->assertTrue($result->isHealthy());
    }
}
