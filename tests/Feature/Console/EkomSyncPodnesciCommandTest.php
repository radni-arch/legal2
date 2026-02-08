<?php

namespace Tests\Feature\Console;

use App\Services\EkomService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EkomSyncPodnesciCommandTest extends TestCase
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
    public function it_syncs_podnesci_without_filters()
    {
        $this->ekomMock->shouldReceive('syncPodnesci')
            ->once()
            ->with([], 1, 20)
            ->andReturn(30);

        $this->artisan('ekom:podnesci:sync')
            ->expectsOutput('Synced 30 podnesak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_podnesci_with_status_filter_nacrt()
    {
        $this->ekomMock->shouldReceive('syncPodnesci')
            ->once()
            ->with(['status' => ['NACRT']], 1, 20)
            ->andReturn(10);

        $this->artisan('ekom:podnesci:sync', ['--status' => ['NACRT']])
            ->expectsOutput('Synced 10 podnesak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_podnesci_with_status_filter_poslan()
    {
        $this->ekomMock->shouldReceive('syncPodnesci')
            ->once()
            ->with(['status' => ['POSLAN']], 1, 20)
            ->andReturn(20);

        $this->artisan('ekom:podnesci:sync', ['--status' => ['POSLAN']])
            ->expectsOutput('Synced 20 podnesak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_podnesci_with_multiple_statuses()
    {
        $this->ekomMock->shouldReceive('syncPodnesci')
            ->once()
            ->with(['status' => ['NACRT', 'POSLAN']], 1, 20)
            ->andReturn(45);

        $this->artisan('ekom:podnesci:sync', ['--status' => ['NACRT', 'POSLAN']])
            ->expectsOutput('Synced 45 podnesak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_podnesci_with_sud_id_filter()
    {
        $this->ekomMock->shouldReceive('syncPodnesci')
            ->once()
            ->with(['sudId' => [5]], 1, 20)
            ->andReturn(12);

        $this->artisan('ekom:podnesci:sync', ['--sudId' => ['5']])
            ->expectsOutput('Synced 12 podnesak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_podnesci_with_combined_filters()
    {
        $this->ekomMock->shouldReceive('syncPodnesci')
            ->once()
            ->with(['status' => ['POSLAN'], 'sudId' => [2, 3]], 1, 20)
            ->andReturn(18);

        $this->artisan('ekom:podnesci:sync', [
            '--status' => ['POSLAN'],
            '--sudId' => ['2', '3'],
        ])
            ->expectsOutput('Synced 18 podnesak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_podnesci_with_custom_page_size()
    {
        $this->ekomMock->shouldReceive('syncPodnesci')
            ->once()
            ->with([], 1, 60)
            ->andReturn(60);

        $this->artisan('ekom:podnesci:sync', ['--size' => '60'])
            ->expectsOutput('Synced 60 podnesak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_clamps_size_to_minimum_of_1()
    {
        $this->ekomMock->shouldReceive('syncPodnesci')
            ->once()
            ->with([], 1, 1)
            ->andReturn(1);

        $this->artisan('ekom:podnesci:sync', ['--size' => '-5'])
            ->expectsOutput('Synced 1 podnesak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_clamps_size_to_maximum_of_100()
    {
        $this->ekomMock->shouldReceive('syncPodnesci')
            ->once()
            ->with([], 1, 100)
            ->andReturn(100);

        $this->artisan('ekom:podnesci:sync', ['--size' => '150'])
            ->expectsOutput('Synced 100 podnesak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_multiple_pages()
    {
        $this->ekomMock->shouldReceive('syncPodnesci')
            ->once()
            ->with([], 7, 20)
            ->andReturn(140);

        $this->artisan('ekom:podnesci:sync', ['--pages' => '7'])
            ->expectsOutput('Synced 140 podnesak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_ensures_minimum_of_1_page()
    {
        $this->ekomMock->shouldReceive('syncPodnesci')
            ->once()
            ->with([], 1, 20)
            ->andReturn(20);

        $this->artisan('ekom:podnesci:sync', ['--pages' => '-2'])
            ->expectsOutput('Synced 20 podnesak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_podnesci_with_custom_pages_and_size()
    {
        $this->ekomMock->shouldReceive('syncPodnesci')
            ->once()
            ->with([], 4, 25)
            ->andReturn(100);

        $this->artisan('ekom:podnesci:sync', [
            '--pages' => '4',
            '--size' => '25',
        ])
            ->expectsOutput('Synced 100 podnesak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_zero_podnesci_when_none_found()
    {
        $this->ekomMock->shouldReceive('syncPodnesci')
            ->once()
            ->with(['status' => ['NACRT']], 1, 20)
            ->andReturn(0);

        $this->artisan('ekom:podnesci:sync', ['--status' => ['NACRT']])
            ->expectsOutput('Synced 0 podnesak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_service_exceptions()
    {
        $this->ekomMock->shouldReceive('syncPodnesci')
            ->once()
            ->andThrow(new \Exception('API timeout'));

        $this->artisan('ekom:podnesci:sync')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_converts_sud_ids_to_integers()
    {
        $this->ekomMock->shouldReceive('syncPodnesci')
            ->once()
            ->with(['sudId' => [7, 8]], 1, 20)
            ->andReturn(22);

        $this->artisan('ekom:podnesci:sync', ['--sudId' => ['7', '8']])
            ->expectsOutput('Synced 22 podnesak(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_all_options_together()
    {
        $this->ekomMock->shouldReceive('syncPodnesci')
            ->once()
            ->with(['status' => ['NACRT', 'POSLAN'], 'sudId' => [1]], 5, 50)
            ->andReturn(250);

        $this->artisan('ekom:podnesci:sync', [
            '--status' => ['NACRT', 'POSLAN'],
            '--sudId' => ['1'],
            '--pages' => '5',
            '--size' => '50',
        ])
            ->expectsOutput('Synced 250 podnesak(a).')
            ->assertExitCode(0);
    }
}
