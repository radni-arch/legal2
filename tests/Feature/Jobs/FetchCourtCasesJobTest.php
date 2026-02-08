<?php

namespace Tests\Feature\Jobs;

use App\Jobs\FetchCourtCasesJob;
use App\Models\Court;
use App\Models\SyncLog;
use App\Services\EPredmetService;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class FetchCourtCasesJobTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Create an empty generator for mocking fetchAllCases.
     */
    protected function emptyGenerator(): \Generator
    {
        return;
        yield;
    }

    /** @test */
    public function job_can_be_dispatched(): void
    {
        Queue::fake();

        FetchCourtCasesJob::dispatch(5107, 2025, 'Pp Prz');

        Queue::assertPushed(FetchCourtCasesJob::class, function ($job) {
            return $job->courtExternalId === 5107
                && $job->year === 2025
                && $job->register === 'Pp Prz';
        });
    }

    /** @test */
    public function job_creates_sync_log_when_running(): void
    {
        $court = Court::factory()->create(['external_id' => 5107]);

        $mockService = Mockery::mock(EPredmetService::class);
        $mockService->shouldReceive('fetchAllCases')
            ->once()
            ->with(5107, 'Pp Prz', 2025, null, 1, 0)
            ->andReturn($this->emptyGenerator());
        $this->app->instance(EPredmetService::class, $mockService);

        $job = new FetchCourtCasesJob(5107, 2025, 'Pp Prz');
        $job->handle(app(EPredmetService::class));

        $this->assertDatabaseHas('sync_logs', [
            'court_id' => $court->id,
            'year' => 2025,
            'register' => 'Pp Prz',
        ]);
    }

    /** @test */
    public function job_updates_sync_log_on_completion(): void
    {
        $court = Court::factory()->create(['external_id' => 5107]);

        $mockService = Mockery::mock(EPredmetService::class);
        $mockService->shouldReceive('fetchAllCases')
            ->once()
            ->andReturn($this->emptyGenerator());
        $this->app->instance(EPredmetService::class, $mockService);

        $job = new FetchCourtCasesJob(5107, 2025, 'Pp Prz');
        $job->handle(app(EPredmetService::class));

        $this->assertDatabaseHas('sync_logs', [
            'court_id' => $court->id,
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function job_handles_missing_court_gracefully(): void
    {
        // No court with external_id 9999 exists
        $mockService = Mockery::mock(EPredmetService::class);
        // fetchAllCases should NOT be called
        $mockService->shouldNotReceive('fetchAllCases');
        $this->app->instance(EPredmetService::class, $mockService);

        $job = new FetchCourtCasesJob(9999, 2025, 'Pp Prz');
        $job->handle(app(EPredmetService::class));

        // Should not create any sync log
        $this->assertDatabaseMissing('sync_logs', [
            'year' => 2025,
            'register' => 'Pp Prz',
        ]);
    }

    /** @test */
    public function job_marks_sync_log_as_failed_on_exception(): void
    {
        $court = Court::factory()->create(['external_id' => 5107]);

        $mockService = Mockery::mock(EPredmetService::class);
        $mockService->shouldReceive('fetchAllCases')
            ->once()
            ->andThrow(new \Exception('API connection failed'));
        $this->app->instance(EPredmetService::class, $mockService);

        $job = new FetchCourtCasesJob(5107, 2025, 'Pp Prz');

        try {
            $job->handle(app(EPredmetService::class));
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('API connection failed', $e->getMessage());
        }

        // NOW this assertion is reachable
        $this->assertDatabaseHas('sync_logs', [
            'court_id' => $court->id,
            'status' => 'failed',
        ]);
    }

    /** @test */
    public function job_accepts_max_cases_parameter(): void
    {
        Queue::fake();

        FetchCourtCasesJob::dispatch(5107, 2025, 'Pp Prz', 100);

        Queue::assertPushed(FetchCourtCasesJob::class, function ($job) {
            return $job->maxCases === 100;
        });
    }

    /** @test */
    public function job_processes_cases_from_generator(): void
    {
        $court = Court::factory()->create(['external_id' => 5107]);

        // Create a generator that yields test case data
        $generator = (function () {
            yield [
                'id' => 1,
                'oznakaBroj' => 'Pp Prz-1/2025',
                'sudac' => 'Test Judge',
                'vrstaPredmeta' => 'Test',
                'vrstaOdluke' => 'Naredba',
            ];
            yield [
                'id' => 2,
                'oznakaBroj' => 'Pp Prz-2/2025',
                'sudac' => 'Test Judge',
                'vrstaPredmeta' => 'Test',
                'vrstaOdluke' => 'Naredba',
            ];
        })();

        $mockService = Mockery::mock(EPredmetService::class);
        $mockService->shouldReceive('fetchAllCases')
            ->once()
            ->andReturn($generator);
        $this->app->instance(EPredmetService::class, $mockService);

        $job = new FetchCourtCasesJob(5107, 2025, 'Pp Prz');
        $job->handle(app(EPredmetService::class));

        // Verify sync log shows cases were processed
        $syncLog = SyncLog::where('court_id', $court->id)->first();
        $this->assertNotNull($syncLog);
        $this->assertEquals(2, $syncLog->total_fetched);
        $this->assertEquals(2, $syncLog->total_saved);
        $this->assertEquals('completed', $syncLog->status);
    }

    /** @test */
    public function job_marks_sync_log_as_running_before_processing(): void
    {
        $court = Court::factory()->create(['external_id' => 5107]);

        // Track whether status was 'running' when fetchAllCases was called
        $statusDuringFetch = null;
        $emptyGen = function (): \Generator {
            return;
            yield;
        };

        $mockService = Mockery::mock(EPredmetService::class);
        $mockService->shouldReceive('fetchAllCases')
            ->once()
            ->andReturnUsing(function () use ($court, &$statusDuringFetch, $emptyGen) {
                $syncLog = SyncLog::where('court_id', $court->id)->first();
                $statusDuringFetch = $syncLog ? $syncLog->status : null;
                return $emptyGen();
            });
        $this->app->instance(EPredmetService::class, $mockService);

        $job = new FetchCourtCasesJob(5107, 2025, 'Pp Prz');
        $job->handle(app(EPredmetService::class));

        $this->assertEquals('running', $statusDuringFetch);
    }

    /** @test */
    public function job_has_correct_timeout_and_tries(): void
    {
        $job = new FetchCourtCasesJob(5107, 2025, 'Pp Prz');

        $this->assertEquals(3600, $job->timeout);
        $this->assertEquals(1, $job->tries);
    }

    /** @test */
    public function job_reuses_existing_sync_log(): void
    {
        $court = Court::factory()->create(['external_id' => 5107]);

        // Create an existing sync log
        $existingSyncLog = SyncLog::create([
            'court_id' => $court->id,
            'register' => 'Pp Prz',
            'year' => 2025,
            'status' => 'pending',
            'last_case_number' => 50,
        ]);

        $mockService = Mockery::mock(EPredmetService::class);
        $mockService->shouldReceive('fetchAllCases')
            ->once()
            ->andReturn($this->emptyGenerator());
        $this->app->instance(EPredmetService::class, $mockService);

        $job = new FetchCourtCasesJob(5107, 2025, 'Pp Prz');
        $job->handle(app(EPredmetService::class));

        // Should still only have one sync log
        $count = SyncLog::where([
            'court_id' => $court->id,
            'register' => 'Pp Prz',
            'year' => 2025,
        ])->count();

        $this->assertEquals(1, $count);

        // Should update to completed
        $this->assertDatabaseHas('sync_logs', [
            'id' => $existingSyncLog->id,
            'status' => 'completed',
        ]);
    }
}
