<?php

namespace Tests\Unit\Benchmarks;

use App\Benchmarks\BaseBenchmark;
use App\Models\BenchmarkRun;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class BaseBenchmarkTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_runs_benchmark_and_stores_results()
    {
        $benchmark = new class extends BaseBenchmark
        {
            public function getName(): string
            {
                return 'Test Benchmark';
            }

            public function getDescription(): string
            {
                return 'A test benchmark';
            }

            protected function execute(): array
            {
                return [
                    'score' => 0.95,
                    'items_processed' => 100,
                ];
            }
        };

        $run = $benchmark->run();

        $this->assertInstanceOf(BenchmarkRun::class, $run);
        $this->assertEquals('Test Benchmark', $run->benchmark_name);
        $this->assertEquals('completed', $run->status);
        $this->assertArrayHasKey('score', $run->metrics);
        $this->assertEquals(0.95, $run->metrics['score']);
        $this->assertNotNull($run->duration_ms);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->completed_at);
    }

    /** @test */
    public function it_handles_benchmark_failures()
    {
        $benchmark = new class extends BaseBenchmark
        {
            public function getName(): string
            {
                return 'Failing Benchmark';
            }

            public function getDescription(): string
            {
                return 'A benchmark that fails';
            }

            protected function execute(): array
            {
                throw new \RuntimeException('Benchmark execution failed');
            }
        };

        try {
            $run = $benchmark->run();
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            // Expected exception
            $this->assertEquals('Benchmark execution failed', $e->getMessage());

            // Check that the run was marked as failed
            $failedRun = BenchmarkRun::where('benchmark_name', 'Failing Benchmark')->first();
            $this->assertNotNull($failedRun);
            $this->assertEquals('failed', $failedRun->status);
            $this->assertNotNull($failedRun->error_message);
            $this->assertStringContainsString('Benchmark execution failed', $failedRun->error_message);
        }
    }

    /** @test */
    public function it_stores_configuration()
    {
        $benchmark = new class extends BaseBenchmark
        {
            public function getName(): string
            {
                return 'Config Test';
            }

            public function getDescription(): string
            {
                return 'Test config storage';
            }

            protected function execute(): array
            {
                return ['result' => $this->config['param'] ?? 'default'];
            }

            protected function getDefaultConfig(): array
            {
                return ['param' => 'default_value'];
            }
        };

        $benchmark->setConfig(['param' => 'custom_value']);
        $run = $benchmark->run();

        $this->assertArrayHasKey('param', $run->config);
        $this->assertEquals('custom_value', $run->config['param']);
        $this->assertEquals('custom_value', $run->metrics['result']);
    }

    /** @test */
    public function it_stores_git_information()
    {
        $benchmark = new class extends BaseBenchmark
        {
            public function getName(): string
            {
                return 'Git Test';
            }

            public function getDescription(): string
            {
                return 'Test git info';
            }

            protected function execute(): array
            {
                return [];
            }
        };

        $run = $benchmark->run();

        $this->assertNotNull($run->git_commit_hash);
        $this->assertIsString($run->git_commit_hash);
    }

    /** @test */
    public function it_stores_system_information()
    {
        $benchmark = new class extends BaseBenchmark
        {
            public function getName(): string
            {
                return 'System Test';
            }

            public function getDescription(): string
            {
                return 'Test system info';
            }

            protected function execute(): array
            {
                return [];
            }
        };

        $run = $benchmark->run();

        $this->assertNotNull($run->system_info);
        $this->assertIsArray($run->system_info);
        $this->assertArrayHasKey('os', $run->system_info);
        $this->assertArrayHasKey('php_version', $run->system_info);
        $this->assertEquals(PHP_VERSION, $run->php_version);
    }

    /** @test */
    public function it_gets_recent_runs()
    {
        // Create a benchmark
        $benchmark = new class extends BaseBenchmark
        {
            public function getName(): string
            {
                return 'Recent Runs Test';
            }

            public function getDescription(): string
            {
                return 'Test recent runs';
            }

            protected function execute(): array
            {
                return ['score' => rand(1, 100)];
            }
        };

        // Run it multiple times
        $benchmark->run();
        $benchmark->run();
        $benchmark->run();

        // Get recent runs
        $recentRuns = $benchmark->getRecentRuns(2);

        $this->assertCount(2, $recentRuns);
        $this->assertInstanceOf(BenchmarkRun::class, $recentRuns->first());
    }

    /** @test */
    public function it_compares_two_runs()
    {
        $benchmark = new class extends BaseBenchmark
        {
            public function getName(): string
            {
                return 'Comparison Test';
            }

            public function getDescription(): string
            {
                return 'Test comparison';
            }

            protected function execute(): array
            {
                return [
                    'accuracy' => 0.85,
                    'latency_ms' => 100,
                ];
            }

            public function isImprovement(string $metricKey, float $diff): bool
            {
                if ($metricKey === 'latency_ms') {
                    return $diff < 0; // Lower is better for latency
                }

                return $diff > 0; // Higher is better for accuracy
            }
        };

        // Create baseline run
        $baselineRun = BenchmarkRun::create([
            'benchmark_class' => get_class($benchmark),
            'benchmark_name' => 'Comparison Test',
            'git_commit_hash' => 'baseline123',
            'started_at' => now()->subDay(),
            'status' => 'completed',
            'metrics' => [
                'accuracy' => 0.80,
                'latency_ms' => 120,
            ],
        ]);

        // Create current run
        $currentRun = BenchmarkRun::create([
            'benchmark_class' => get_class($benchmark),
            'benchmark_name' => 'Comparison Test',
            'git_commit_hash' => 'current456',
            'started_at' => now(),
            'status' => 'completed',
            'metrics' => [
                'accuracy' => 0.85,
                'latency_ms' => 100,
            ],
        ]);

        // Use reflection to call protected method
        $reflection = new \ReflectionClass($benchmark);
        $method = $reflection->getMethod('compareTwoRuns');
        $method->setAccessible(true);

        $comparison = $method->invoke($benchmark, $baselineRun, $currentRun);

        $this->assertArrayHasKey('baseline', $comparison);
        $this->assertArrayHasKey('current', $comparison);
        $this->assertArrayHasKey('changes', $comparison);

        // Check accuracy improved (use delta for floating point comparison)
        $this->assertEqualsWithDelta(0.05, $comparison['changes']['accuracy']['diff'], 0.0001);
        $this->assertTrue($comparison['changes']['accuracy']['improved']);

        // Check latency improved (decreased)
        $this->assertEquals(-20, $comparison['changes']['latency_ms']['diff']);
        $this->assertTrue($comparison['changes']['latency_ms']['improved']);
    }
}
