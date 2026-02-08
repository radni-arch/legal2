<?php

namespace Tests\Feature\Console;

use App\Services\EoglasnaService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EoglasnaWatchOsijekTest extends TestCase
{
    use UsesTestDatabase;

    protected $eoglasnaMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eoglasnaMock = Mockery::mock(EoglasnaService::class);
        $this->app->instance(EoglasnaService::class, $this->eoglasnaMock);

        Log::spy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_monitors_osijek_court_successfully()
    {
        $this->eoglasnaMock->shouldReceive('monitorOsijekCourtAll')
            ->once()
            ->andReturn(75);

        $this->artisan('eoglasna:watch-osijek')
            ->expectsOutput('Fetching ALL e-Oglasna items from Općinski sud u Osijeku...')
            ->expectsOutput('Osijek court monitoring done. Items processed: 75')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_processes_zero_items_when_none_found()
    {
        $this->eoglasnaMock->shouldReceive('monitorOsijekCourtAll')
            ->once()
            ->andReturn(0);

        $this->artisan('eoglasna:watch-osijek')
            ->expectsOutput('Osijek court monitoring done. Items processed: 0')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_processes_large_number_of_items()
    {
        $this->eoglasnaMock->shouldReceive('monitorOsijekCourtAll')
            ->once()
            ->andReturn(500);

        $this->artisan('eoglasna:watch-osijek')
            ->expectsOutput('Osijek court monitoring done. Items processed: 500')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_service_exception_with_logging()
    {
        $this->eoglasnaMock->shouldReceive('monitorOsijekCourtAll')
            ->once()
            ->andThrow(new \Exception('API connection failed'));

        $this->artisan('eoglasna:watch-osijek')
            ->expectsOutput('Fetching ALL e-Oglasna items from Općinski sud u Osijeku...')
            ->expectsOutput('Osijek monitoring failed: API connection failed')
            ->assertExitCode(1);

        Log::shouldHaveReceived('error')
            ->once()
            ->with('eoglasna:watch-osijek failed', Mockery::on(function ($context) {
                return $context['error'] === 'API connection failed' && isset($context['trace']);
            }));
    }

    /** @test */
    public function it_handles_network_timeout()
    {
        $this->eoglasnaMock->shouldReceive('monitorOsijekCourtAll')
            ->once()
            ->andThrow(new \Exception('Connection timeout'));

        $this->artisan('eoglasna:watch-osijek')
            ->expectsOutput('Osijek monitoring failed: Connection timeout')
            ->assertExitCode(1);

        Log::shouldHaveReceived('error')
            ->once()
            ->with('eoglasna:watch-osijek failed', Mockery::type('array'));
    }

    /** @test */
    public function it_handles_runtime_exception()
    {
        $this->eoglasnaMock->shouldReceive('monitorOsijekCourtAll')
            ->once()
            ->andThrow(new \RuntimeException('Database error'));

        $this->artisan('eoglasna:watch-osijek')
            ->expectsOutput('Osijek monitoring failed: Database error')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_calls_monitor_osijek_court_all_exactly_once()
    {
        $this->eoglasnaMock->shouldReceive('monitorOsijekCourtAll')
            ->once()
            ->andReturn(100);

        $this->artisan('eoglasna:watch-osijek')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_executes_without_arguments()
    {
        $this->eoglasnaMock->shouldReceive('monitorOsijekCourtAll')
            ->once()
            ->andReturn(25);

        $this->artisan('eoglasna:watch-osijek')
            ->expectsOutput('Osijek court monitoring done. Items processed: 25')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_correct_count_in_message()
    {
        $this->eoglasnaMock->shouldReceive('monitorOsijekCourtAll')
            ->once()
            ->andReturn(42);

        $this->artisan('eoglasna:watch-osijek')
            ->expectsOutputToContain('42')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_logs_exception_with_trace()
    {
        $exception = new \Exception('Service failure');

        $this->eoglasnaMock->shouldReceive('monitorOsijekCourtAll')
            ->once()
            ->andThrow($exception);

        $this->artisan('eoglasna:watch-osijek')
            ->assertExitCode(1);

        Log::shouldHaveReceived('error')
            ->once()
            ->with('eoglasna:watch-osijek failed', Mockery::on(function ($context) {
                return isset($context['error']) && isset($context['trace']);
            }));
    }

    /** @test */
    public function it_displays_fetching_message()
    {
        $this->eoglasnaMock->shouldReceive('monitorOsijekCourtAll')
            ->once()
            ->andReturn(10);

        $this->artisan('eoglasna:watch-osijek')
            ->expectsOutputToContain('Općinski sud u Osijeku')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_partial_processing()
    {
        $this->eoglasnaMock->shouldReceive('monitorOsijekCourtAll')
            ->once()
            ->andReturn(15);

        $this->artisan('eoglasna:watch-osijek')
            ->expectsOutput('Osijek court monitoring done. Items processed: 15')
            ->assertExitCode(0);
    }
}
