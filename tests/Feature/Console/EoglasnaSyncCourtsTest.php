<?php

namespace Tests\Feature\Console;

use App\Services\EoglasnaService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EoglasnaSyncCourtsTest extends TestCase
{
    use UsesTestDatabase;

    protected $eoglasnaMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eoglasnaMock = Mockery::mock(EoglasnaService::class);
        $this->app->instance(EoglasnaService::class, $this->eoglasnaMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_syncs_courts_successfully()
    {
        $this->eoglasnaMock->shouldReceive('syncCourts')
            ->once()
            ->andReturn(50);

        $this->artisan('eoglasna:sync-courts')
            ->expectsOutput('Synchronized 50 courts.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_zero_courts_when_none_found()
    {
        $this->eoglasnaMock->shouldReceive('syncCourts')
            ->once()
            ->andReturn(0);

        $this->artisan('eoglasna:sync-courts')
            ->expectsOutput('Synchronized 0 courts.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_large_number_of_courts()
    {
        $this->eoglasnaMock->shouldReceive('syncCourts')
            ->once()
            ->andReturn(150);

        $this->artisan('eoglasna:sync-courts')
            ->expectsOutput('Synchronized 150 courts.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_service_exception()
    {
        $this->eoglasnaMock->shouldReceive('syncCourts')
            ->once()
            ->andThrow(new \Exception('API connection failed'));

        $this->artisan('eoglasna:sync-courts')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_handles_network_timeout()
    {
        $this->eoglasnaMock->shouldReceive('syncCourts')
            ->once()
            ->andThrow(new \Exception('Network timeout'));

        $this->artisan('eoglasna:sync-courts')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_calls_sync_courts_exactly_once()
    {
        $this->eoglasnaMock->shouldReceive('syncCourts')
            ->once()
            ->andReturn(25);

        $this->artisan('eoglasna:sync-courts')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_executes_without_arguments()
    {
        $this->eoglasnaMock->shouldReceive('syncCourts')
            ->once()
            ->andReturn(10);

        $this->artisan('eoglasna:sync-courts')
            ->expectsOutput('Synchronized 10 courts.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_correct_count_in_message()
    {
        $this->eoglasnaMock->shouldReceive('syncCourts')
            ->once()
            ->andReturn(75);

        $this->artisan('eoglasna:sync-courts')
            ->expectsOutputToContain('75 courts')
            ->assertExitCode(0);
    }
}
