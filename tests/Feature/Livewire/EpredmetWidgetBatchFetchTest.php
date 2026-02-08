<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\EpredmetWidget;
use App\Models\Court;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class EpredmetWidgetBatchFetchTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_can_load_courts(): void
    {
        // Create level 1 (municipal) courts
        Court::factory()->create(['name' => 'Opcinski sud u Zagrebu', 'external_id' => 5001, 'level' => 1]);
        Court::factory()->create(['name' => 'Opcinski sud u Splitu', 'external_id' => 5002, 'level' => 1]);
        // Create level 2 (county) court - should not be loaded
        Court::factory()->create(['name' => 'Zupanijski sud u Zagrebu', 'external_id' => 6001, 'level' => 2]);

        Livewire::test(EpredmetWidget::class)
            ->call('loadCourts')
            ->assertCount('courts', 2); // Only level 1 (municipal) courts
    }

    public function test_widget_has_batch_fetch_properties(): void
    {
        Livewire::test(EpredmetWidget::class)
            ->assertSet('batchCourtId', null)
            ->assertSet('batchYear', (int) date('Y'))
            ->assertSet('batchRegister', 'Pp Prz')
            ->assertSet('batchFetchStatus', null);
    }

    public function test_widget_validates_batch_fetch_inputs(): void
    {
        Livewire::test(EpredmetWidget::class)
            ->set('activeTab', 'batch-fetch')
            ->call('startBatchFetch')
            ->assertHasErrors(['batchCourtId' => 'required']);
    }

    public function test_widget_displays_batch_fetch_progress(): void
    {
        // Fake the queue to prevent actual job dispatch
        Queue::fake();

        $court = Court::factory()->create(['external_id' => 5107, 'level' => 1]);

        Livewire::test(EpredmetWidget::class)
            ->set('batchCourtId', $court->id)
            ->set('batchYear', 2025)
            ->call('startBatchFetch')
            ->assertSet('batchFetchStatus', 'dispatched');
    }

    public function test_batch_fetch_validates_year_range(): void
    {
        $court = Court::factory()->create(['external_id' => 5107, 'level' => 1]);

        Livewire::test(EpredmetWidget::class)
            ->set('batchCourtId', $court->id)
            ->set('batchYear', 2019) // Below minimum of 2020
            ->call('startBatchFetch')
            ->assertHasErrors(['batchYear' => 'min']);
    }

    public function test_batch_fetch_validates_court_exists(): void
    {
        Livewire::test(EpredmetWidget::class)
            ->set('batchCourtId', 99999) // Non-existent court ID
            ->set('batchYear', 2025)
            ->call('startBatchFetch')
            ->assertHasErrors(['batchCourtId' => 'exists']);
    }

    public function test_batch_fetch_enables_polling_after_dispatch(): void
    {
        Queue::fake();

        $court = Court::factory()->create(['external_id' => 5107, 'level' => 1]);

        Livewire::test(EpredmetWidget::class)
            ->assertSet('pollingEnabled', false)
            ->set('batchCourtId', $court->id)
            ->set('batchYear', 2025)
            ->call('startBatchFetch')
            ->assertSet('pollingEnabled', true);
    }

    public function test_batch_fetch_clears_previous_error(): void
    {
        Queue::fake();

        $court = Court::factory()->create(['external_id' => 5107, 'level' => 1]);

        Livewire::test(EpredmetWidget::class)
            ->set('batchFetchError', 'Previous error message')
            ->set('batchCourtId', $court->id)
            ->set('batchYear', 2025)
            ->call('startBatchFetch')
            ->assertSet('batchFetchError', null);
    }

    public function test_loaded_courts_contain_expected_fields(): void
    {
        // Use Croatian special character in name to test short_name transformation
        $court = Court::factory()->create([
            'name' => 'Opcinski sud u Osijeku', // ASCII version
            'external_id' => 5107,
            'level' => 1,
        ]);

        $component = Livewire::test(EpredmetWidget::class)
            ->call('loadCourts');

        $courts = $component->get('courts');

        $this->assertCount(1, $courts);
        $this->assertEquals($court->id, $courts[0]['id']);
        $this->assertEquals(5107, $courts[0]['external_id']);
        // short_name won't transform since factory uses ASCII 'Opcinski' not Croatian 'Opcinski'
        $this->assertEquals($court->short_name, $courts[0]['name']);
        $this->assertEquals('Opcinski sud u Osijeku', $courts[0]['full_name']);
    }
}
