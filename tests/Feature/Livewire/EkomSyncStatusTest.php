<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\EkomSyncStatus;
use App\Jobs\EkomSyncAllJob;
use App\Models\EkomOtpravak;
use App\Models\EkomPodnesak;
use App\Models\EkomPredmet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * EkomSyncStatus Component Tests
 *
 * Tests for the E-Komunikacije sync status dashboard component.
 * This component displays sync statistics and allows manual sync triggering.
 */
class EkomSyncStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Test 1: Component renders successfully when authenticated
     */
    public function test_component_renders_successfully(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ekom.sync-status'))
            ->assertOk()
            ->assertSee('Sync Status');
    }

    /**
     * Test 2: Dashboard displays sync statistics correctly
     */
    public function test_displays_sync_statistics(): void
    {
        $user = User::factory()->create();

        // Create test data using factories
        EkomPredmet::factory()->count(5)->create();
        EkomPodnesak::factory()->count(3)->create();
        EkomOtpravak::factory()->count(7)->create();

        Livewire::actingAs($user)
            ->test(EkomSyncStatus::class)
            ->assertSet('stats.predmeti_count', 5)
            ->assertSet('stats.podnesci_count', 3)
            ->assertSet('stats.otpravci_count', 7);
    }

    /**
     * Test 3: Can trigger manual sync via button
     */
    public function test_can_trigger_manual_sync(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EkomSyncStatus::class)
            ->call('triggerSync')
            ->assertDispatched('success');

        Queue::assertPushed(EkomSyncAllJob::class);
    }

    /**
     * Test 4: Shows last sync time based on model timestamps
     */
    public function test_shows_last_sync_time(): void
    {
        $user = User::factory()->create();

        // Create a predmet synced an hour ago
        EkomPredmet::factory()->create(['last_synced_at' => now()->subHour()]);

        Livewire::actingAs($user)
            ->test(EkomSyncStatus::class)
            ->assertSee('hour ago');
    }

    /**
     * Test 5: Route requires authentication
     */
    public function test_requires_authentication(): void
    {
        $this->get(route('ekom.sync-status'))
            ->assertRedirect(route('login'));
    }

    /**
     * Test 6: Stats array contains all required keys
     */
    public function test_stats_has_all_required_keys(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(EkomSyncStatus::class);

        $stats = $component->get('stats');

        $this->assertArrayHasKey('predmeti_count', $stats);
        $this->assertArrayHasKey('podnesci_count', $stats);
        $this->assertArrayHasKey('otpravci_count', $stats);
        $this->assertArrayHasKey('predmeti_last_sync', $stats);
        $this->assertArrayHasKey('podnesci_last_sync', $stats);
        $this->assertArrayHasKey('otpravci_last_sync', $stats);
    }

    /**
     * Test 7: Refresh stats method reloads data
     */
    public function test_refresh_stats_reloads_data(): void
    {
        $user = User::factory()->create();

        // Create initial data
        EkomPredmet::factory()->count(2)->create();

        $component = Livewire::actingAs($user)
            ->test(EkomSyncStatus::class)
            ->assertSet('stats.predmeti_count', 2);

        // Add more data
        EkomPredmet::factory()->count(3)->create();

        // Refresh should reload from database with new count
        $component->call('refreshStats')
            ->assertSet('stats.predmeti_count', 5);
    }

    /**
     * Test 8: Component displays correct title
     */
    public function test_component_displays_correct_title(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ekom.sync-status'))
            ->assertOk()
            ->assertSee('Sync Status');
    }

    /**
     * Test 9: Status message is set after successful sync trigger
     */
    public function test_status_message_set_after_sync_trigger(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EkomSyncStatus::class)
            ->call('triggerSync')
            ->assertSet('statusMessage', 'Sync job dispatched successfully');
    }

    /**
     * Test 10: Component renders correct view
     */
    public function test_component_renders_correct_view(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EkomSyncStatus::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.ekom-sync-status');
    }
}
