<?php

namespace Tests\Feature\Console;

use App\Services\EkomService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EkomDndAllOffCommandTest extends TestCase
{
    use UsesTestDatabase;

    protected $ekomMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ekomMock = Mockery::mock(EkomService::class);
        $this->app->instance(EkomService::class, $this->ekomMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_turns_off_dnd_for_all_predmeti()
    {
        $this->ekomMock->shouldReceive('dndAllOff')
            ->once()
            ->andReturnNull();

        $this->artisan('ekom:dnd:all-off')
            ->expectsOutput('DND OFF for all Predmeti executed.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_executes_without_arguments()
    {
        $this->ekomMock->shouldReceive('dndAllOff')
            ->once()
            ->andReturnNull();

        $this->artisan('ekom:dnd:all-off')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_service_exception()
    {
        $this->ekomMock->shouldReceive('dndAllOff')
            ->once()
            ->andThrow(new \Exception('Service error'));

        $this->artisan('ekom:dnd:all-off')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_calls_service_method_exactly_once()
    {
        $this->ekomMock->shouldReceive('dndAllOff')
            ->once()
            ->andReturnNull();

        $this->artisan('ekom:dnd:all-off')
            ->expectsOutput('DND OFF for all Predmeti executed.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_network_timeout_exception()
    {
        $this->ekomMock->shouldReceive('dndAllOff')
            ->once()
            ->andThrow(new \Exception('Network timeout'));

        $this->artisan('ekom:dnd:all-off')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_displays_success_message_on_completion()
    {
        $this->ekomMock->shouldReceive('dndAllOff')
            ->once()
            ->andReturnNull();

        $this->artisan('ekom:dnd:all-off')
            ->expectsOutputToContain('DND OFF for all Predmeti')
            ->assertExitCode(0);
    }
}
