<?php

namespace Tests\Unit\Benchmarks;

use App\Models\BenchmarkRun;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class BenchmarkRunModelTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_creates_benchmark_run_with_ulid()
    {
        $run = BenchmarkRun::create([
            'benchmark_class' => 'App\\Benchmarks\\CitationAccuracyBenchmark',
            'benchmark_name' => 'Test Benchmark',
            'git_commit_hash' => 'abc123',
            'started_at' => now(),
            'metrics' => ['accuracy' => 0.95],
        ]);

        $this->assertNotNull($run->id);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $run->id);
    }

    /** @test */
    public function it_casts_timestamps_correctly()
    {
        $now = now();

        $run = BenchmarkRun::create([
            'benchmark_class' => 'TestBenchmark',
            'benchmark_name' => 'Test',
            'git_commit_hash' => 'abc123',
            'started_at' => $now,
            'completed_at' => $now->addSeconds(10),
            'metrics' => [],
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $run->started_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $run->completed_at);
    }

    /** @test */
    public function it_casts_json_fields_to_arrays()
    {
        $run = BenchmarkRun::create([
            'benchmark_class' => 'TestBenchmark',
            'benchmark_name' => 'Test',
            'git_commit_hash' => 'abc123',
            'started_at' => now(),
            'config' => ['option' => 'value'],
            'metrics' => ['score' => 0.8],
            'details' => ['info' => 'test'],
        ]);

        $this->assertIsArray($run->config);
        $this->assertIsArray($run->metrics);
        $this->assertIsArray($run->details);
        $this->assertEquals('value', $run->config['option']);
    }

    /** @test */
    public function scope_for_benchmark_filters_by_class()
    {
        BenchmarkRun::create([
            'benchmark_class' => 'Benchmark1',
            'benchmark_name' => 'Test 1',
            'git_commit_hash' => 'abc123',
            'started_at' => now(),
            'metrics' => [],
        ]);

        BenchmarkRun::create([
            'benchmark_class' => 'Benchmark2',
            'benchmark_name' => 'Test 2',
            'git_commit_hash' => 'abc123',
            'started_at' => now(),
            'metrics' => [],
        ]);

        $results = BenchmarkRun::forBenchmark('Benchmark1')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Benchmark1', $results->first()->benchmark_class);
    }

    /** @test */
    public function scope_completed_filters_completed_runs()
    {
        BenchmarkRun::create([
            'benchmark_class' => 'Test',
            'benchmark_name' => 'Test',
            'git_commit_hash' => 'abc123',
            'started_at' => now(),
            'status' => 'completed',
            'metrics' => [],
        ]);

        BenchmarkRun::create([
            'benchmark_class' => 'Test',
            'benchmark_name' => 'Test',
            'git_commit_hash' => 'abc123',
            'started_at' => now(),
            'status' => 'running',
            'metrics' => [],
        ]);

        $results = BenchmarkRun::completed()->get();

        $this->assertCount(1, $results);
        $this->assertEquals('completed', $results->first()->status);
    }

    /** @test */
    public function it_gets_metric_by_key()
    {
        $run = BenchmarkRun::create([
            'benchmark_class' => 'Test',
            'benchmark_name' => 'Test',
            'git_commit_hash' => 'abc123',
            'started_at' => now(),
            'metrics' => ['accuracy' => 0.95, 'precision' => 0.90],
        ]);

        $this->assertEquals(0.95, $run->getMetric('accuracy'));
        $this->assertEquals(0.90, $run->getMetric('precision'));
        $this->assertNull($run->getMetric('nonexistent'));
        $this->assertEquals('default', $run->getMetric('nonexistent', 'default'));
    }

    /** @test */
    public function it_checks_status_correctly()
    {
        $completed = BenchmarkRun::create([
            'benchmark_class' => 'Test',
            'benchmark_name' => 'Test',
            'git_commit_hash' => 'abc123',
            'started_at' => now(),
            'status' => 'completed',
            'metrics' => [],
        ]);

        $failed = BenchmarkRun::create([
            'benchmark_class' => 'Test',
            'benchmark_name' => 'Test',
            'git_commit_hash' => 'abc123',
            'started_at' => now(),
            'status' => 'failed',
            'metrics' => [],
        ]);

        $running = BenchmarkRun::create([
            'benchmark_class' => 'Test',
            'benchmark_name' => 'Test',
            'git_commit_hash' => 'abc123',
            'started_at' => now(),
            'status' => 'running',
            'metrics' => [],
        ]);

        $this->assertTrue($completed->isSuccessful());
        $this->assertFalse($completed->isFailed());
        $this->assertFalse($completed->isRunning());

        $this->assertFalse($failed->isSuccessful());
        $this->assertTrue($failed->isFailed());
        $this->assertFalse($failed->isRunning());

        $this->assertFalse($running->isSuccessful());
        $this->assertFalse($running->isFailed());
        $this->assertTrue($running->isRunning());
    }

    /** @test */
    public function it_formats_duration_correctly()
    {
        $run1 = BenchmarkRun::create([
            'benchmark_class' => 'Test',
            'benchmark_name' => 'Test',
            'git_commit_hash' => 'abc123',
            'started_at' => now(),
            'duration_ms' => 500,
            'metrics' => [],
        ]);

        $run2 = BenchmarkRun::create([
            'benchmark_class' => 'Test',
            'benchmark_name' => 'Test',
            'git_commit_hash' => 'abc123',
            'started_at' => now(),
            'duration_ms' => 2500,
            'metrics' => [],
        ]);

        $run3 = BenchmarkRun::create([
            'benchmark_class' => 'Test',
            'benchmark_name' => 'Test',
            'git_commit_hash' => 'abc123',
            'started_at' => now(),
            'duration_ms' => null,
            'metrics' => [],
        ]);

        $this->assertEquals('500ms', $run1->getFormattedDuration());
        $this->assertEquals('2.50s', $run2->getFormattedDuration());
        $this->assertEquals('N/A', $run3->getFormattedDuration());
    }
}
