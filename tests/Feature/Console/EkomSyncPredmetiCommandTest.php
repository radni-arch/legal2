<?php

namespace Tests\Feature\Console;

use App\Services\EkomService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EkomSyncPredmetiCommandTest extends TestCase
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
    public function it_syncs_predmeti_without_filters()
    {
        $this->ekomMock->shouldReceive('syncPredmeti')
            ->once()
            ->with([], 1, 20)
            ->andReturn(50);

        $this->artisan('ekom:predmeti:sync')
            ->expectsOutput('Synced 50 predmet(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_predmeti_with_status_filter()
    {
        $this->ekomMock->shouldReceive('syncPredmeti')
            ->once()
            ->with(['status' => ['U_RADU']], 1, 20)
            ->andReturn(25);

        $this->artisan('ekom:predmeti:sync', ['--status' => ['U_RADU']])
            ->expectsOutput('Synced 25 predmet(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_predmeti_with_multiple_statuses()
    {
        $this->ekomMock->shouldReceive('syncPredmeti')
            ->once()
            ->with(['status' => ['U_RADU', 'ARHIVIRAN']], 1, 20)
            ->andReturn(100);

        $this->artisan('ekom:predmeti:sync', ['--status' => ['U_RADU', 'ARHIVIRAN']])
            ->expectsOutput('Synced 100 predmet(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_predmeti_with_sud_id_filter()
    {
        $this->ekomMock->shouldReceive('syncPredmeti')
            ->once()
            ->with(['sudId' => [1, 2]], 1, 20)
            ->andReturn(15);

        $this->artisan('ekom:predmeti:sync', ['--sudId' => ['1', '2']])
            ->expectsOutput('Synced 15 predmet(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_predmeti_with_combined_filters()
    {
        $this->ekomMock->shouldReceive('syncPredmeti')
            ->once()
            ->with(['status' => ['U_RADU'], 'sudId' => [3]], 1, 20)
            ->andReturn(8);

        $this->artisan('ekom:predmeti:sync', [
            '--status' => ['U_RADU'],
            '--sudId' => ['3'],
        ])
            ->expectsOutput('Synced 8 predmet(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_predmeti_with_custom_page_size()
    {
        $this->ekomMock->shouldReceive('syncPredmeti')
            ->once()
            ->with([], 1, 50)
            ->andReturn(50);

        $this->artisan('ekom:predmeti:sync', ['--size' => '50'])
            ->expectsOutput('Synced 50 predmet(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_clamps_size_to_minimum_of_1()
    {
        $this->ekomMock->shouldReceive('syncPredmeti')
            ->once()
            ->with([], 1, 1)
            ->andReturn(1);

        $this->artisan('ekom:predmeti:sync', ['--size' => '0'])
            ->expectsOutput('Synced 1 predmet(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_clamps_size_to_maximum_of_100()
    {
        $this->ekomMock->shouldReceive('syncPredmeti')
            ->once()
            ->with([], 1, 100)
            ->andReturn(100);

        $this->artisan('ekom:predmeti:sync', ['--size' => '200'])
            ->expectsOutput('Synced 100 predmet(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_multiple_pages()
    {
        $this->ekomMock->shouldReceive('syncPredmeti')
            ->once()
            ->with([], 5, 20)
            ->andReturn(100);

        $this->artisan('ekom:predmeti:sync', ['--pages' => '5'])
            ->expectsOutput('Synced 100 predmet(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_ensures_minimum_of_1_page()
    {
        $this->ekomMock->shouldReceive('syncPredmeti')
            ->once()
            ->with([], 1, 20)
            ->andReturn(20);

        $this->artisan('ekom:predmeti:sync', ['--pages' => '0'])
            ->expectsOutput('Synced 20 predmet(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_predmeti_with_custom_pages_and_size()
    {
        $this->ekomMock->shouldReceive('syncPredmeti')
            ->once()
            ->with([], 3, 30)
            ->andReturn(90);

        $this->artisan('ekom:predmeti:sync', [
            '--pages' => '3',
            '--size' => '30',
        ])
            ->expectsOutput('Synced 90 predmet(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_zero_predmeti_when_none_found()
    {
        $this->ekomMock->shouldReceive('syncPredmeti')
            ->once()
            ->with(['status' => ['ARHIVIRAN']], 1, 20)
            ->andReturn(0);

        $this->artisan('ekom:predmeti:sync', ['--status' => ['ARHIVIRAN']])
            ->expectsOutput('Synced 0 predmet(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_service_exceptions()
    {
        $this->ekomMock->shouldReceive('syncPredmeti')
            ->once()
            ->andThrow(new \Exception('API connection failed'));

        $this->artisan('ekom:predmeti:sync')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_converts_sud_ids_to_integers()
    {
        $this->ekomMock->shouldReceive('syncPredmeti')
            ->once()
            ->with(['sudId' => [10, 20, 30]], 1, 20)
            ->andReturn(15);

        $this->artisan('ekom:predmeti:sync', ['--sudId' => ['10', '20', '30']])
            ->expectsOutput('Synced 15 predmet(a).')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_all_options_together()
    {
        $this->ekomMock->shouldReceive('syncPredmeti')
            ->once()
            ->with(['status' => ['U_RADU'], 'sudId' => [1, 2]], 10, 75)
            ->andReturn(750);

        $this->artisan('ekom:predmeti:sync', [
            '--status' => ['U_RADU'],
            '--sudId' => ['1', '2'],
            '--pages' => '10',
            '--size' => '75',
        ])
            ->expectsOutput('Synced 750 predmet(a).')
            ->assertExitCode(0);
    }
}
