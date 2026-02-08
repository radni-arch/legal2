<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\EpredmetWidget;
use App\Models\Court;
use App\Models\SyncLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EpredmetWidgetSyncStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_has_active_tab_property(): void
    {
        Livewire::test(EpredmetWidget::class)
            ->assertSet('activeTab', 'lookup');
    }

    public function test_widget_can_switch_to_sync_status_tab(): void
    {
        Livewire::test(EpredmetWidget::class)
            ->set('activeTab', 'sync-status')
            ->assertSet('activeTab', 'sync-status');
    }

    public function test_widget_loads_sync_logs_when_switching_to_sync_status(): void
    {
        $court = Court::factory()->create(['name' => 'Test Court', 'external_id' => 1234]);
        SyncLog::create([
            'court_id' => $court->id,
            'register' => 'Pp Prz',
            'year' => 2025,
            'status' => 'completed',
            'total_fetched' => 100,
            'total_saved' => 95,
            'total_errors' => 5,
        ]);

        Livewire::test(EpredmetWidget::class)
            ->set('filterYear', 2025)
            ->call('loadSyncStatus')
            ->assertSet('syncLogs.0.total_fetched', 100)
            ->assertSet('syncLogs.0.total_saved', 95);
    }

    public function test_widget_calculates_sync_summary_totals(): void
    {
        $court1 = Court::factory()->create(['external_id' => 1001]);
        $court2 = Court::factory()->create(['external_id' => 1002]);

        SyncLog::create([
            'court_id' => $court1->id,
            'register' => 'Pp Prz',
            'year' => 2025,
            'status' => 'completed',
            'total_fetched' => 100,
            'total_saved' => 90,
            'total_errors' => 10,
        ]);
        SyncLog::create([
            'court_id' => $court2->id,
            'register' => 'Pp Prz',
            'year' => 2025,
            'status' => 'completed',
            'total_fetched' => 200,
            'total_saved' => 195,
            'total_errors' => 5,
        ]);

        Livewire::test(EpredmetWidget::class)
            ->set('filterYear', 2025)
            ->call('loadSyncStatus')
            ->assertSet('syncSummary.total_fetched', 300)
            ->assertSet('syncSummary.total_saved', 285)
            ->assertSet('syncSummary.total_errors', 15);
    }
}
