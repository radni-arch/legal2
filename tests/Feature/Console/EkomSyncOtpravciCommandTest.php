<?php

namespace Tests\Feature\Console;

use App\Services\EkomService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EkomSyncOtpravciCommandTest extends TestCase
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
    public function it_syncs_otpravci_without_filters()
    {
        $this->ekomMock->shouldReceive('syncOtpravci')
            ->once()
            ->with([], 1, 20)
            ->andReturn(42);

        $this->artisan('ekom:otpravci:sync')
            ->expectsOutput('Synced 42 otpravak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_otpravci_with_status_filter_primljen()
    {
        $this->ekomMock->shouldReceive('syncOtpravci')
            ->once()
            ->with(['status' => ['PRIMLJEN']], 1, 20)
            ->andReturn(15);

        $this->artisan('ekom:otpravci:sync', ['--status' => ['PRIMLJEN']])
            ->expectsOutput('Synced 15 otpravak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_otpravci_with_status_filter_u_dostavi()
    {
        $this->ekomMock->shouldReceive('syncOtpravci')
            ->once()
            ->with(['status' => ['U_DOSTAVI']], 1, 20)
            ->andReturn(28);

        $this->artisan('ekom:otpravci:sync', ['--status' => ['U_DOSTAVI']])
            ->expectsOutput('Synced 28 otpravak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_otpravci_with_multiple_statuses()
    {
        $this->ekomMock->shouldReceive('syncOtpravci')
            ->once()
            ->with(['status' => ['PRIMLJEN', 'U_DOSTAVI']], 1, 20)
            ->andReturn(60);

        $this->artisan('ekom:otpravci:sync', ['--status' => ['PRIMLJEN', 'U_DOSTAVI']])
            ->expectsOutput('Synced 60 otpravak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_otpravci_with_sud_id_filter()
    {
        $this->ekomMock->shouldReceive('syncOtpravci')
            ->once()
            ->with(['sudId' => [4]], 1, 20)
            ->andReturn(9);

        $this->artisan('ekom:otpravci:sync', ['--sudId' => ['4']])
            ->expectsOutput('Synced 9 otpravak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_otpravci_with_combined_filters()
    {
        $this->ekomMock->shouldReceive('syncOtpravci')
            ->once()
            ->with(['status' => ['PRIMLJEN'], 'sudId' => [1, 2]], 1, 20)
            ->andReturn(25);

        $this->artisan('ekom:otpravci:sync', [
            '--status' => ['PRIMLJEN'],
            '--sudId' => ['1', '2'],
        ])
            ->expectsOutput('Synced 25 otpravak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_otpravci_with_custom_page_size()
    {
        $this->ekomMock->shouldReceive('syncOtpravci')
            ->once()
            ->with([], 1, 40)
            ->andReturn(40);

        $this->artisan('ekom:otpravci:sync', ['--size' => '40'])
            ->expectsOutput('Synced 40 otpravak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_clamps_size_to_minimum_of_1()
    {
        $this->ekomMock->shouldReceive('syncOtpravci')
            ->once()
            ->with([], 1, 1)
            ->andReturn(1);

        $this->artisan('ekom:otpravci:sync', ['--size' => '0'])
            ->expectsOutput('Synced 1 otpravak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_clamps_size_to_maximum_of_100()
    {
        $this->ekomMock->shouldReceive('syncOtpravci')
            ->once()
            ->with([], 1, 100)
            ->andReturn(100);

        $this->artisan('ekom:otpravci:sync', ['--size' => '250'])
            ->expectsOutput('Synced 100 otpravak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_multiple_pages()
    {
        $this->ekomMock->shouldReceive('syncOtpravci')
            ->once()
            ->with([], 6, 20)
            ->andReturn(120);

        $this->artisan('ekom:otpravci:sync', ['--pages' => '6'])
            ->expectsOutput('Synced 120 otpravak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_ensures_minimum_of_1_page()
    {
        $this->ekomMock->shouldReceive('syncOtpravci')
            ->once()
            ->with([], 1, 20)
            ->andReturn(20);

        $this->artisan('ekom:otpravci:sync', ['--pages' => '0'])
            ->expectsOutput('Synced 20 otpravak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_otpravci_with_custom_pages_and_size()
    {
        $this->ekomMock->shouldReceive('syncOtpravci')
            ->once()
            ->with([], 8, 15)
            ->andReturn(120);

        $this->artisan('ekom:otpravci:sync', [
            '--pages' => '8',
            '--size' => '15',
        ])
            ->expectsOutput('Synced 120 otpravak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_zero_otpravci_when_none_found()
    {
        $this->ekomMock->shouldReceive('syncOtpravci')
            ->once()
            ->with(['status' => ['PRIMLJEN']], 1, 20)
            ->andReturn(0);

        $this->artisan('ekom:otpravci:sync', ['--status' => ['PRIMLJEN']])
            ->expectsOutput('Synced 0 otpravak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_service_exceptions()
    {
        $this->ekomMock->shouldReceive('syncOtpravci')
            ->once()
            ->andThrow(new \Exception('Network error'));

        $this->artisan('ekom:otpravci:sync')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_converts_sud_ids_to_integers()
    {
        $this->ekomMock->shouldReceive('syncOtpravci')
            ->once()
            ->with(['sudId' => [11, 12, 13]], 1, 20)
            ->andReturn(33);

        $this->artisan('ekom:otpravci:sync', ['--sudId' => ['11', '12', '13']])
            ->expectsOutput('Synced 33 otpravak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_all_options_together()
    {
        $this->ekomMock->shouldReceive('syncOtpravci')
            ->once()
            ->with(['status' => ['U_DOSTAVI'], 'sudId' => [5, 6]], 12, 80)
            ->andReturn(960);

        $this->artisan('ekom:otpravci:sync', [
            '--status' => ['U_DOSTAVI'],
            '--sudId' => ['5', '6'],
            '--pages' => '12',
            '--size' => '80',
        ])
            ->expectsOutput('Synced 960 otpravak(a).')
            ->assertExitCode(0);
    }
}
