<?php

namespace Tests\Feature\Console;

use App\Services\EkomService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EkomToggleDndGeneralCommandTest extends TestCase
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
    public function it_turns_on_general_dnd()
    {
        $this->ekomMock->shouldReceive('turnOnGeneralDnd')
            ->once()
            ->andReturn(true);

        $this->artisan('ekom:dnd:general', ['action' => 'on'])
            ->expectsOutput('General DND ON: true')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_turns_off_general_dnd()
    {
        $this->ekomMock->shouldReceive('turnOffGeneralDnd')
            ->once()
            ->andReturn(true);

        $this->artisan('ekom:dnd:general', ['action' => 'off'])
            ->expectsOutput('General DND OFF: true')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_false_when_dnd_on_fails()
    {
        $this->ekomMock->shouldReceive('turnOnGeneralDnd')
            ->once()
            ->andReturn(false);

        $this->artisan('ekom:dnd:general', ['action' => 'on'])
            ->expectsOutput('General DND ON: false')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_false_when_dnd_off_fails()
    {
        $this->ekomMock->shouldReceive('turnOffGeneralDnd')
            ->once()
            ->andReturn(false);

        $this->artisan('ekom:dnd:general', ['action' => 'off'])
            ->expectsOutput('General DND OFF: false')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_invalid_action()
    {
        $this->ekomMock->shouldNotReceive('turnOnGeneralDnd');
        $this->ekomMock->shouldNotReceive('turnOffGeneralDnd');

        $this->artisan('ekom:dnd:general', ['action' => 'toggle'])
            ->expectsOutput('Action must be on|off')
            ->assertExitCode(2);
    }

    /** @test */
    public function it_handles_empty_action()
    {
        $this->ekomMock->shouldNotReceive('turnOnGeneralDnd');
        $this->ekomMock->shouldNotReceive('turnOffGeneralDnd');

        $this->artisan('ekom:dnd:general', ['action' => ''])
            ->expectsOutput('Action must be on|off')
            ->assertExitCode(2);
    }

    /** @test */
    public function it_handles_service_exception_on_turn_on()
    {
        $this->ekomMock->shouldReceive('turnOnGeneralDnd')
            ->once()
            ->andThrow(new \Exception('API unavailable'));

        $this->artisan('ekom:dnd:general', ['action' => 'on'])
            ->assertExitCode(1);
    }

    /** @test */
    public function it_handles_service_exception_on_turn_off()
    {
        $this->ekomMock->shouldReceive('turnOffGeneralDnd')
            ->once()
            ->andThrow(new \Exception('Connection timeout'));

        $this->artisan('ekom:dnd:general', ['action' => 'off'])
            ->assertExitCode(1);
    }

    /** @test */
    public function it_rejects_case_variant_on()
    {
        $this->ekomMock->shouldNotReceive('turnOnGeneralDnd');
        $this->ekomMock->shouldNotReceive('turnOffGeneralDnd');

        $this->artisan('ekom:dnd:general', ['action' => 'ON'])
            ->expectsOutput('Action must be on|off')
            ->assertExitCode(2);
    }

    /** @test */
    public function it_rejects_case_variant_off()
    {
        $this->ekomMock->shouldNotReceive('turnOnGeneralDnd');
        $this->ekomMock->shouldNotReceive('turnOffGeneralDnd');

        $this->artisan('ekom:dnd:general', ['action' => 'OFF'])
            ->expectsOutput('Action must be on|off')
            ->assertExitCode(2);
    }

    /** @test */
    public function it_rejects_numeric_action()
    {
        $this->ekomMock->shouldNotReceive('turnOnGeneralDnd');
        $this->ekomMock->shouldNotReceive('turnOffGeneralDnd');

        $this->artisan('ekom:dnd:general', ['action' => '1'])
            ->expectsOutput('Action must be on|off')
            ->assertExitCode(2);
    }
}
