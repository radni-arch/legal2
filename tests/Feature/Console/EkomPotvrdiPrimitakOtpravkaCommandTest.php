<?php

namespace Tests\Feature\Console;

use App\Services\EkomService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EkomPotvrdiPrimitakOtpravkaCommandTest extends TestCase
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
    public function it_confirms_receipt_of_otpravak()
    {
        $this->ekomMock->shouldReceive('potvrdiPrimitakOtpravka')
            ->once()
            ->with(123)
            ->andReturnNull();

        $this->artisan('ekom:otpravci:potvrdi', ['id' => '123'])
            ->expectsOutput('Confirmed receipt for otpravak 123.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_confirms_receipt_with_different_id()
    {
        $this->ekomMock->shouldReceive('potvrdiPrimitakOtpravka')
            ->once()
            ->with(456)
            ->andReturnNull();

        $this->artisan('ekom:otpravci:potvrdi', ['id' => '456'])
            ->expectsOutput('Confirmed receipt for otpravak 456.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_converts_id_to_integer()
    {
        $this->ekomMock->shouldReceive('potvrdiPrimitakOtpravka')
            ->once()
            ->with(789)
            ->andReturnNull();

        $this->artisan('ekom:otpravci:potvrdi', ['id' => '789'])
            ->expectsOutput('Confirmed receipt for otpravak 789.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_service_exception()
    {
        $this->ekomMock->shouldReceive('potvrdiPrimitakOtpravka')
            ->once()
            ->with(999)
            ->andThrow(new \Exception('API error'));

        $this->artisan('ekom:otpravci:potvrdi', ['id' => '999'])
            ->assertExitCode(1);
    }

    /** @test */
    public function it_handles_network_timeout()
    {
        $this->ekomMock->shouldReceive('potvrdiPrimitakOtpravka')
            ->once()
            ->with(111)
            ->andThrow(new \Exception('Connection timeout'));

        $this->artisan('ekom:otpravci:potvrdi', ['id' => '111'])
            ->assertExitCode(1);
    }

    /** @test */
    public function it_handles_zero_id()
    {
        $this->ekomMock->shouldReceive('potvrdiPrimitakOtpravka')
            ->once()
            ->with(0)
            ->andReturnNull();

        $this->artisan('ekom:otpravci:potvrdi', ['id' => '0'])
            ->expectsOutput('Confirmed receipt for otpravak 0.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_negative_id()
    {
        $this->ekomMock->shouldReceive('potvrdiPrimitakOtpravka')
            ->once()
            ->with(-10)
            ->andReturnNull();

        $this->artisan('ekom:otpravci:potvrdi', ['id' => '-10'])
            ->expectsOutput('Confirmed receipt for otpravak -10.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_calls_service_method_exactly_once()
    {
        $this->ekomMock->shouldReceive('potvrdiPrimitakOtpravka')
            ->once()
            ->with(555)
            ->andReturnNull();

        $this->artisan('ekom:otpravci:potvrdi', ['id' => '555'])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_success_message_with_correct_id()
    {
        $this->ekomMock->shouldReceive('potvrdiPrimitakOtpravka')
            ->once()
            ->with(777)
            ->andReturnNull();

        $this->artisan('ekom:otpravci:potvrdi', ['id' => '777'])
            ->expectsOutputToContain('otpravak 777')
            ->assertExitCode(0);
    }
}
