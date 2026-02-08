<?php

namespace Tests\Feature\Console;

use App\Services\EkomService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EkomToggleDndPredmetCommandTest extends TestCase
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
    public function it_turns_on_dnd_for_predmet()
    {
        $this->ekomMock->shouldReceive('turnOnDndPredmet')
            ->once()
            ->with(123)
            ->andReturn(true);

        $this->artisan('ekom:dnd:predmet', ['predmetId' => '123', 'action' => 'on'])
            ->expectsOutput('DND ON for predmet 123: true')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_turns_off_dnd_for_predmet()
    {
        $this->ekomMock->shouldReceive('turnOffDndPredmet')
            ->once()
            ->with(456)
            ->andReturn(true);

        $this->artisan('ekom:dnd:predmet', ['predmetId' => '456', 'action' => 'off'])
            ->expectsOutput('DND OFF for predmet 456: true')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_false_when_dnd_on_fails()
    {
        $this->ekomMock->shouldReceive('turnOnDndPredmet')
            ->once()
            ->with(789)
            ->andReturn(false);

        $this->artisan('ekom:dnd:predmet', ['predmetId' => '789', 'action' => 'on'])
            ->expectsOutput('DND ON for predmet 789: false')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_false_when_dnd_off_fails()
    {
        $this->ekomMock->shouldReceive('turnOffDndPredmet')
            ->once()
            ->with(321)
            ->andReturn(false);

        $this->artisan('ekom:dnd:predmet', ['predmetId' => '321', 'action' => 'off'])
            ->expectsOutput('DND OFF for predmet 321: false')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_invalid_action()
    {
        $this->ekomMock->shouldNotReceive('turnOnDndPredmet');
        $this->ekomMock->shouldNotReceive('turnOffDndPredmet');

        $this->artisan('ekom:dnd:predmet', ['predmetId' => '100', 'action' => 'invalid'])
            ->expectsOutput('Action must be on|off')
            ->assertExitCode(2);
    }

    /** @test */
    public function it_handles_empty_action()
    {
        $this->ekomMock->shouldNotReceive('turnOnDndPredmet');
        $this->ekomMock->shouldNotReceive('turnOffDndPredmet');

        $this->artisan('ekom:dnd:predmet', ['predmetId' => '200', 'action' => ''])
            ->expectsOutput('Action must be on|off')
            ->assertExitCode(2);
    }

    /** @test */
    public function it_converts_predmet_id_to_integer()
    {
        $this->ekomMock->shouldReceive('turnOnDndPredmet')
            ->once()
            ->with(999)
            ->andReturn(true);

        $this->artisan('ekom:dnd:predmet', ['predmetId' => '999', 'action' => 'on'])
            ->expectsOutput('DND ON for predmet 999: true')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_service_exception_on_turn_on()
    {
        $this->ekomMock->shouldReceive('turnOnDndPredmet')
            ->once()
            ->with(555)
            ->andThrow(new \Exception('API error'));

        $this->artisan('ekom:dnd:predmet', ['predmetId' => '555', 'action' => 'on'])
            ->assertExitCode(1);
    }

    /** @test */
    public function it_handles_service_exception_on_turn_off()
    {
        $this->ekomMock->shouldReceive('turnOffDndPredmet')
            ->once()
            ->with(666)
            ->andThrow(new \Exception('Network timeout'));

        $this->artisan('ekom:dnd:predmet', ['predmetId' => '666', 'action' => 'off'])
            ->assertExitCode(1);
    }

    /** @test */
    public function it_accepts_case_sensitive_on_action()
    {
        $this->ekomMock->shouldReceive('turnOnDndPredmet')
            ->once()
            ->with(111)
            ->andReturn(true);

        $this->artisan('ekom:dnd:predmet', ['predmetId' => '111', 'action' => 'on'])
            ->expectsOutput('DND ON for predmet 111: true')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_accepts_case_sensitive_off_action()
    {
        $this->ekomMock->shouldReceive('turnOffDndPredmet')
            ->once()
            ->with(222)
            ->andReturn(true);

        $this->artisan('ekom:dnd:predmet', ['predmetId' => '222', 'action' => 'off'])
            ->expectsOutput('DND OFF for predmet 222: true')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_zero_predmet_id()
    {
        $this->ekomMock->shouldReceive('turnOnDndPredmet')
            ->once()
            ->with(0)
            ->andReturn(false);

        $this->artisan('ekom:dnd:predmet', ['predmetId' => '0', 'action' => 'on'])
            ->expectsOutput('DND ON for predmet 0: false')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_negative_predmet_id()
    {
        $this->ekomMock->shouldReceive('turnOffDndPredmet')
            ->once()
            ->with(-5)
            ->andReturn(false);

        $this->artisan('ekom:dnd:predmet', ['predmetId' => '-5', 'action' => 'off'])
            ->expectsOutput('DND OFF for predmet -5: false')
            ->assertExitCode(0);
    }
}
