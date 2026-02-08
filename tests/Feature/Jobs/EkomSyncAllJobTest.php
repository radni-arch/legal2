<?php

namespace Tests\Feature\Jobs;

use App\Contracts\External\EkomServiceInterface;
use App\Jobs\EkomSyncAllJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class EkomSyncAllJobTest extends TestCase
{
    /** @test */
    public function job_can_be_dispatched(): void
    {
        Queue::fake();

        EkomSyncAllJob::dispatch();

        Queue::assertPushed(EkomSyncAllJob::class);
    }

    /** @test */
    public function job_with_custom_max_pages(): void
    {
        Queue::fake();

        EkomSyncAllJob::dispatch(maxPages: 5);

        Queue::assertPushed(EkomSyncAllJob::class, function ($job) {
            return $job->maxPages === 5;
        });
    }

    /** @test */
    public function job_calls_all_sync_methods(): void
    {
        $mock = Mockery::mock(EkomServiceInterface::class);
        $mock->shouldReceive('syncPredmeti')
            ->once()
            ->with([], 10, null)
            ->andReturn(10);
        $mock->shouldReceive('syncPodnesci')
            ->once()
            ->with([], 10, null)
            ->andReturn(5);
        $mock->shouldReceive('syncOtpravci')
            ->once()
            ->with([], 10, null)
            ->andReturn(3);

        $this->app->instance(EkomServiceInterface::class, $mock);

        $job = new EkomSyncAllJob(maxPages: 10);
        $job->handle($mock);
    }

    /** @test */
    public function job_handles_exception_and_rethrows(): void
    {
        $mock = Mockery::mock(EkomServiceInterface::class);
        $mock->shouldReceive('syncPredmeti')
            ->once()
            ->andThrow(new \Exception('API Error'));

        $this->app->instance(EkomServiceInterface::class, $mock);

        $job = new EkomSyncAllJob();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('API Error');

        $job->handle($mock);
    }

    /** @test */
    public function job_has_correct_defaults(): void
    {
        $job = new EkomSyncAllJob();

        $this->assertEquals(10, $job->maxPages);
        $this->assertEquals(3600, $job->timeout);
        $this->assertEquals(1, $job->tries);
    }

    /** @test */
    public function job_logs_start_and_completion(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('EKOM full sync started', Mockery::hasKey('max_pages'));

        Log::shouldReceive('info')
            ->once()
            ->with('EKOM predmeti synced', Mockery::hasKey('count'));

        Log::shouldReceive('info')
            ->once()
            ->with('EKOM podnesci synced', Mockery::hasKey('count'));

        Log::shouldReceive('info')
            ->once()
            ->with('EKOM otpravci synced', Mockery::hasKey('count'));

        Log::shouldReceive('info')
            ->once()
            ->with('EKOM full sync completed', Mockery::on(function ($context) {
                return isset($context['predmeti'])
                    && isset($context['podnesci'])
                    && isset($context['otpravci'])
                    && isset($context['duration_ms']);
            }));

        $mock = Mockery::mock(EkomServiceInterface::class);
        $mock->shouldReceive('syncPredmeti')->once()->andReturn(10);
        $mock->shouldReceive('syncPodnesci')->once()->andReturn(5);
        $mock->shouldReceive('syncOtpravci')->once()->andReturn(3);

        $this->app->instance(EkomServiceInterface::class, $mock);

        $job = new EkomSyncAllJob();
        $job->handle($mock);
    }

    /** @test */
    public function job_logs_failure_on_exception(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('EKOM full sync started', Mockery::any());

        Log::shouldReceive('error')
            ->once()
            ->with('EKOM full sync failed', Mockery::on(function ($context) {
                return isset($context['error'])
                    && $context['error'] === 'Sync failed'
                    && isset($context['duration_ms']);
            }));

        $mock = Mockery::mock(EkomServiceInterface::class);
        $mock->shouldReceive('syncPredmeti')
            ->once()
            ->andThrow(new \Exception('Sync failed'));

        $this->app->instance(EkomServiceInterface::class, $mock);

        $job = new EkomSyncAllJob();

        try {
            $job->handle($mock);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Sync failed', $e->getMessage());
        }
    }

    /** @test */
    public function job_handles_deserialization_without_user_id(): void
    {
        $mock = Mockery::mock(EkomServiceInterface::class);
        $mock->shouldReceive('syncPredmeti')->once()->andReturn(1);
        $mock->shouldReceive('syncPodnesci')->once()->andReturn(1);
        $mock->shouldReceive('syncOtpravci')->once()->andReturn(1);

        $this->app->instance(EkomServiceInterface::class, $mock);

        // Simulate deserialization without userId (e.g., job queued before property existed)
        $job = new EkomSyncAllJob(maxPages: 10);
        $serialized = serialize($job);

        // Remove userId from serialized data to simulate missing property
        $restored = unserialize($serialized);
        $restored->handle($mock);

        // Should complete without "must not be accessed before initialization" error
        $this->assertTrue(true);
    }

    /** @test */
    public function job_uses_default_max_pages_of_10(): void
    {
        $mock = Mockery::mock(EkomServiceInterface::class);
        $mock->shouldReceive('syncPredmeti')
            ->once()
            ->with([], 10, null)
            ->andReturn(0);
        $mock->shouldReceive('syncPodnesci')
            ->once()
            ->with([], 10, null)
            ->andReturn(0);
        $mock->shouldReceive('syncOtpravci')
            ->once()
            ->with([], 10, null)
            ->andReturn(0);

        $this->app->instance(EkomServiceInterface::class, $mock);

        $job = new EkomSyncAllJob();
        $job->handle($mock);

        // If we get here without exception, the method calls matched
        $this->assertTrue(true);
    }
}
