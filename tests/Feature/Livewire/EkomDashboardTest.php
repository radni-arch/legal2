<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\EkomDashboard;
use App\Models\EkomOtpravak;
use App\Models\EkomPodnesak;
use App\Models\EkomPredmet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class EkomDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Test 1: Dashboard renders successfully when authenticated
     */
    public function test_dashboard_renders_successfully(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ekom.dashboard'))
            ->assertOk()
            ->assertSee('E-Komunikacije Dashboard');
    }

    /**
     * Test 2: Dashboard displays statistics correctly
     */
    public function test_dashboard_displays_statistics(): void
    {
        $user = User::factory()->create();

        // Create test data using factories
        EkomPredmet::factory()->count(3)->create();
        EkomPodnesak::factory()->count(2)->create();
        EkomOtpravak::factory()->count(4)->create();

        Livewire::actingAs($user)
            ->test(EkomDashboard::class)
            ->assertSet('stats.predmeti_count', 3)
            ->assertSet('stats.podnesci_count', 2)
            ->assertSet('stats.otpravci_count', 4);
    }

    /**
     * Test 3: Dashboard requires authentication
     */
    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('ekom.dashboard'))
            ->assertRedirect(route('login'));
    }

    /**
     * Test 4: Dashboard loads recent activity
     */
    public function test_dashboard_loads_recent_activity(): void
    {
        $user = User::factory()->create();

        // Create a predmet with a sync timestamp
        $predmet = EkomPredmet::factory()->create([
            'last_synced_at' => now(),
        ]);

        $component = Livewire::actingAs($user)
            ->test(EkomDashboard::class);

        $recentActivity = $component->get('recentActivity');
        $this->assertIsArray($recentActivity);
    }

    /**
     * Test 5: Dashboard refresh stats method reloads data from database
     */
    public function test_refresh_stats_reloads_data(): void
    {
        $user = User::factory()->create();

        // Create initial data
        EkomPredmet::factory()->count(2)->create();

        // Test component with initial data
        $component = Livewire::actingAs($user)
            ->test(EkomDashboard::class)
            ->assertSet('stats.predmeti_count', 2);

        // Add more data
        EkomPredmet::factory()->count(3)->create();

        // Refresh should reload from database with new count
        $component->call('refreshStats')
            ->assertSet('stats.predmeti_count', 5);
    }

    /**
     * Test 6: Component renders correct view
     */
    public function test_component_renders_correct_view(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EkomDashboard::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.ekom-dashboard');
    }

    /**
     * Test 7: Dashboard tracks pending otpravci (without confirmation receipt)
     */
    public function test_dashboard_tracks_pending_otpravci(): void
    {
        $user = User::factory()->create();

        // Create otpravci - some with confirmation, some without
        EkomOtpravak::factory()->count(2)->create([
            'vrijeme_potvrde_primitka' => null, // Pending
        ]);
        EkomOtpravak::factory()->count(3)->create([
            'vrijeme_potvrde_primitka' => now(), // Confirmed
        ]);

        Livewire::actingAs($user)
            ->test(EkomDashboard::class)
            ->assertSet('stats.pending_otpravci', 2);
    }

    /**
     * Test 8: Dashboard tracks draft podnesci
     */
    public function test_dashboard_tracks_draft_podnesci(): void
    {
        $user = User::factory()->create();

        // Create podnesci - some draft, some not
        EkomPodnesak::factory()->count(2)->create([
            'status' => 'kreiran', // Draft status
        ]);
        EkomPodnesak::factory()->count(3)->create([
            'status' => 'poslan', // Sent status
        ]);

        Livewire::actingAs($user)
            ->test(EkomDashboard::class)
            ->assertSet('stats.draft_podnesci', 2);
    }

    /**
     * Test 9: Dashboard has stats array with required keys
     */
    public function test_dashboard_has_stats_array_with_required_keys(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(EkomDashboard::class);

        $stats = $component->get('stats');

        $this->assertArrayHasKey('predmeti_count', $stats);
        $this->assertArrayHasKey('podnesci_count', $stats);
        $this->assertArrayHasKey('otpravci_count', $stats);
        $this->assertArrayHasKey('pending_otpravci', $stats);
        $this->assertArrayHasKey('draft_podnesci', $stats);
        $this->assertArrayHasKey('last_sync', $stats);
    }

    /**
     * Test 10: Dashboard displays action status messages
     */
    public function test_dashboard_displays_action_status_messages(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(EkomDashboard::class);

        // Initially null
        $this->assertNull($component->get('actionStatus'));
        $this->assertNull($component->get('actionMessage'));
    }
}
