<?php

namespace Tests\Feature\Console;

use App\Services\Ekom\SifarniciSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class EkomSyncSifarniciCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_command_runs_sync_all_by_default(): void
    {
        $mockService = Mockery::mock(SifarniciSyncService::class);
        $mockService->shouldReceive('syncAll')
            ->once()
            ->with(false)
            ->andReturn([
                'courts' => 5,
                'procedure_types' => 10,
                'submission_types' => 20,
                'participant_roles' => 15,
                'fee_options' => 8,
                'non_payment_reasons' => 3,
                'fee_exemptions' => 2,
                'settlements' => 100,
                'countries' => 50,
            ]);

        $this->app->instance(SifarniciSyncService::class, $mockService);

        $this->artisan('ekom:sync-sifrarnici')
            ->assertExitCode(0);
    }

    public function test_command_with_force_option(): void
    {
        $mockService = Mockery::mock(SifarniciSyncService::class);
        $mockService->shouldReceive('syncAll')
            ->once()
            ->with(true)
            ->andReturn([
                'courts' => 5,
                'procedure_types' => 10,
                'submission_types' => 20,
                'participant_roles' => 15,
                'fee_options' => 8,
                'non_payment_reasons' => 3,
                'fee_exemptions' => 2,
                'settlements' => 100,
                'countries' => 50,
            ]);

        $this->app->instance(SifarniciSyncService::class, $mockService);

        $this->artisan('ekom:sync-sifrarnici --force')
            ->assertExitCode(0);
    }

    public function test_command_with_only_option(): void
    {
        $mockService = Mockery::mock(SifarniciSyncService::class);
        $mockService->shouldReceive('syncCourts')
            ->once()
            ->andReturn(5);

        $this->app->instance(SifarniciSyncService::class, $mockService);

        $this->artisan('ekom:sync-sifrarnici --only=courts')
            ->assertExitCode(0);
    }

    public function test_command_with_only_procedure_types_and_court_id(): void
    {
        $mockService = Mockery::mock(SifarniciSyncService::class);
        $mockService->shouldReceive('syncProcedureTypes')
            ->once()
            ->with(42)
            ->andReturn(3);

        $this->app->instance(SifarniciSyncService::class, $mockService);

        $this->artisan('ekom:sync-sifrarnici --only=procedure-types --court-id=42')
            ->assertExitCode(0);
    }

    public function test_command_with_invalid_only_option(): void
    {
        $this->artisan('ekom:sync-sifrarnici --only=invalid-table')
            ->assertExitCode(1);
    }
}
