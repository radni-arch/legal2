<?php

namespace Tests\Unit\Pipelines\Concerns;

use App\Pipelines\Concerns\RetryableStep;
use Tests\TestCase;

class RetryableStepTest extends TestCase
{
    private function makeStep(int $maxRetries = 3, int $retryDelayMs = 0): object
    {
        $step = new class {
            use RetryableStep;

            public function executeWithRetry(callable $operation, string $operationName): mixed
            {
                return $this->withRetry($operation, $operationName);
            }
        };
        $step->maxRetries = $maxRetries;
        $step->retryDelayMs = $retryDelayMs;

        return $step;
    }

    /** @test */
    public function it_retries_on_transient_failure_and_succeeds(): void
    {
        $attempts = 0;
        $step = $this->makeStep(maxRetries: 3);

        $result = $step->executeWithRetry(function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new \RuntimeException('Transient S3 error');
            }
            return 'success';
        }, 'test_operation');

        $this->assertEquals('success', $result);
        $this->assertEquals(3, $attempts);
    }

    /** @test */
    public function it_throws_after_max_retries_exceeded(): void
    {
        $step = $this->makeStep(maxRetries: 2);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Persistent failure');

        $step->executeWithRetry(function () {
            throw new \RuntimeException('Persistent failure');
        }, 'test_operation');
    }

    /** @test */
    public function it_succeeds_on_first_try_without_retry(): void
    {
        $attempts = 0;
        $step = $this->makeStep(maxRetries: 3);

        $result = $step->executeWithRetry(function () use (&$attempts) {
            $attempts++;
            return 'immediate_success';
        }, 'test_operation');

        $this->assertEquals('immediate_success', $result);
        $this->assertEquals(1, $attempts);
    }
}
