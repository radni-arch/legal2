<?php

namespace Tests\Unit\Jobs;

use App\Agents\OdlukeAgent;
use App\Jobs\ExecuteOdlukeAgentJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ExecuteOdlukeAgentJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_implements_should_queue_interface(): void
    {
        $job = new ExecuteOdlukeAgentJob('test query');

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    /** @test */
    public function it_stores_query_context_and_cache_key(): void
    {
        $context = ['case_id' => 123, 'court' => 'Zagreb'];
        $job = new ExecuteOdlukeAgentJob('find similar cases', $context, 'cache-key-123');

        $reflection = new \ReflectionClass($job);
        $query = $reflection->getProperty('query');
        $query->setAccessible(true);
        $contextProp = $reflection->getProperty('context');
        $contextProp->setAccessible(true);
        $cacheKey = $reflection->getProperty('cacheKey');
        $cacheKey->setAccessible(true);

        $this->assertEquals('find similar cases', $query->getValue($job));
        $this->assertEquals($context, $contextProp->getValue($job));
        $this->assertEquals('cache-key-123', $cacheKey->getValue($job));
    }

    /** @test */
    public function it_executes_agent_with_query_and_context(): void
    {
        Log::shouldReceive('info')->times(2);

        $mockAgent = Mockery::mock(OdlukeAgent::class);
        $mockAgent->shouldReceive('execute')
            ->once()
            ->with('test query', ['context' => 'value'])
            ->andReturn(['result' => 'success']);

        $job = new ExecuteOdlukeAgentJob('test query', ['context' => 'value']);
        $job->handle($mockAgent);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_caches_successful_result_when_cache_key_provided(): void
    {
        Log::shouldReceive('info')->times(2);

        Cache::shouldReceive('put')
            ->once()
            ->with('result-cache-key', Mockery::on(function ($value) {
                return $value['status'] === 'completed'
                    && isset($value['result'])
                    && isset($value['duration'])
                    && isset($value['completed_at']);
            }), 3600);

        $mockAgent = Mockery::mock(OdlukeAgent::class);
        $mockAgent->shouldReceive('execute')
            ->once()
            ->andReturn(['decisions' => [1, 2, 3]]);

        $job = new ExecuteOdlukeAgentJob('query', [], 'result-cache-key');
        $job->handle($mockAgent);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_does_not_cache_when_no_cache_key_provided(): void
    {
        Log::shouldReceive('info')->times(2);

        Cache::shouldReceive('put')->never();

        $mockAgent = Mockery::mock(OdlukeAgent::class);
        $mockAgent->shouldReceive('execute')
            ->once()
            ->andReturn(['result' => 'data']);

        $job = new ExecuteOdlukeAgentJob('query without cache');
        $job->handle($mockAgent);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_caches_error_on_exception(): void
    {
        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->once();

        Cache::shouldReceive('put')
            ->once()
            ->with('error-cache-key', Mockery::on(function ($value) {
                return $value['status'] === 'failed'
                    && isset($value['error'])
                    && isset($value['completed_at']);
            }), 3600);

        $mockAgent = Mockery::mock(OdlukeAgent::class);
        $mockAgent->shouldReceive('execute')
            ->once()
            ->andThrow(new \Exception('Agent failed'));

        $job = new ExecuteOdlukeAgentJob('query', [], 'error-cache-key');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Agent failed');

        $job->handle($mockAgent);
    }

    /** @test */
    public function it_logs_error_and_rethrows_on_exception(): void
    {
        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')
            ->once()
            ->with('OdlukeAgent execution failed', Mockery::type('array'));

        $mockAgent = Mockery::mock(OdlukeAgent::class);
        $mockAgent->shouldReceive('execute')
            ->once()
            ->andThrow(new \Exception('Execution error'));

        $job = new ExecuteOdlukeAgentJob('failing query');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Execution error');

        $job->handle($mockAgent);
    }

    /** @test */
    public function failed_method_logs_permanent_failure(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('ExecuteOdlukeAgentJob failed permanently', Mockery::type('array'));

        $job = new ExecuteOdlukeAgentJob('test query', [], 'failed-key');
        $exception = new \Exception('Permanent failure');

        $job->failed($exception);
    }

    /** @test */
    public function it_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        ExecuteOdlukeAgentJob::dispatch('search query', ['filter' => 'recent'], 'queue-test');

        Queue::assertPushed(ExecuteOdlukeAgentJob::class);
    }

    /** @test */
    public function it_has_1_retry_attempt(): void
    {
        $job = new ExecuteOdlukeAgentJob('query');

        $this->assertEquals(1, $job->tries);
    }

    /** @test */
    public function it_has_300_second_timeout(): void
    {
        $job = new ExecuteOdlukeAgentJob('query');

        $this->assertEquals(300, $job->timeout);
    }

    /** @test */
    public function it_logs_execution_duration(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Starting OdlukeAgent job', Mockery::type('array'));

        Log::shouldReceive('info')
            ->once()
            ->with('OdlukeAgent completed successfully', Mockery::on(function ($context) {
                return isset($context['duration']) && is_numeric($context['duration']);
            }));

        $mockAgent = Mockery::mock(OdlukeAgent::class);
        $mockAgent->shouldReceive('execute')
            ->once()
            ->andReturn(['result' => 'data']);

        $job = new ExecuteOdlukeAgentJob('query');
        $job->handle($mockAgent);

        $this->addToAssertionCount(1);
    }
}
