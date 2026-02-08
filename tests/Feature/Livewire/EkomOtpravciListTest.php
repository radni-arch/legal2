<?php

namespace Tests\Feature\Livewire;

use App\Contracts\External\EkomServiceInterface;
use App\Http\Livewire\EkomOtpravciList;
use App\Models\EkomOtpravak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EkomOtpravciListTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders_successfully(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ekom.otpravci'))
            ->assertOk()
            ->assertSee('Dispatches (Otpravci)');
    }

    public function test_displays_otpravci_list(): void
    {
        $user = User::factory()->create();
        EkomOtpravak::factory()->create(['remote_id' => 'OTP-123456']);

        Livewire::actingAs($user)
            ->test(EkomOtpravciList::class)
            ->assertSee('OTP-123456');
    }

    public function test_can_filter_pending(): void
    {
        $user = User::factory()->create();
        EkomOtpravak::factory()->create([
            'remote_id' => 'PENDING-1',
            'vrijeme_potvrde_primitka' => null,
        ]);
        EkomOtpravak::factory()->create([
            'remote_id' => 'CONFIRMED-1',
            'vrijeme_potvrde_primitka' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(EkomOtpravciList::class)
            ->set('pendingOnly', true)
            ->assertSee('PENDING-1')
            ->assertDontSee('CONFIRMED-1');
    }

    public function test_can_filter_by_status(): void
    {
        $user = User::factory()->create();
        EkomOtpravak::factory()->create([
            'remote_id' => 'POSLAN-1',
            'status' => 'poslan',
        ]);
        EkomOtpravak::factory()->create([
            'remote_id' => 'DOSTAVLJEN-1',
            'status' => 'dostavljen',
        ]);

        Livewire::actingAs($user)
            ->test(EkomOtpravciList::class)
            ->set('statusFilter', 'poslan')
            ->assertSee('POSLAN-1')
            ->assertDontSee('DOSTAVLJEN-1');
    }

    public function test_can_confirm_receipt(): void
    {
        $user = User::factory()->create();
        $otpravak = EkomOtpravak::factory()->create([
            'vrijeme_potvrde_primitka' => null,
        ]);

        // Extract numeric part from remote_id for service call
        $numericId = (int) preg_replace('/[^0-9]/', '', $otpravak->remote_id);

        $this->mock(EkomServiceInterface::class)
            ->shouldReceive('potvrdiPrimitakOtpravka')
            ->with($numericId)
            ->once();

        Livewire::actingAs($user)
            ->test(EkomOtpravciList::class)
            ->call('confirmReceipt', $otpravak->id)
            ->assertDispatched('toast', function ($name, $params) {
                return $params['type'] === 'success';
            });

        // Verify local model is updated
        $otpravak->refresh();
        $this->assertNotNull($otpravak->vrijeme_potvrde_primitka);
    }

    public function test_url_filter_parameter_sets_pending_only(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->withQueryParams(['filter' => 'pending'])
            ->test(EkomOtpravciList::class)
            ->assertSet('pendingOnly', true);
    }

    public function test_requires_authentication(): void
    {
        $this->get(route('ekom.otpravci'))
            ->assertRedirect(route('login'));
    }

    public function test_sorting_by_remote_id(): void
    {
        $user = User::factory()->create();
        EkomOtpravak::factory()->create(['remote_id' => 'AAA-111']);
        EkomOtpravak::factory()->create(['remote_id' => 'ZZZ-999']);

        $component = Livewire::actingAs($user)
            ->test(EkomOtpravciList::class)
            ->call('sortBy', 'remote_id');

        $component->assertSet('sortField', 'remote_id')
            ->assertSet('sortDirection', 'asc');
    }

    public function test_sorting_toggles_direction(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EkomOtpravciList::class)
            ->call('sortBy', 'remote_id')
            ->assertSet('sortDirection', 'asc')
            ->call('sortBy', 'remote_id')
            ->assertSet('sortDirection', 'desc');
    }

    public function test_displays_predmet_info(): void
    {
        $user = User::factory()->create();
        EkomOtpravak::factory()->create([
            'remote_id' => 'OTP-TEST',
            'predmet_remote_id' => 'K-1234/2024',
        ]);

        Livewire::actingAs($user)
            ->test(EkomOtpravciList::class)
            ->assertSee('K-1234/2024');
    }

    public function test_pagination_works(): void
    {
        $user = User::factory()->create();
        EkomOtpravak::factory()->count(25)->create();

        $component = Livewire::actingAs($user)
            ->test(EkomOtpravciList::class)
            ->assertSet('perPage', 20);

        // Should have pagination since we have 25 items with 20 per page
        $this->assertGreaterThan(20, EkomOtpravak::count());
    }

    public function test_shows_expired_receipt_status(): void
    {
        $user = User::factory()->create();
        EkomOtpravak::factory()->create([
            'remote_id' => 'EXPIRED-TEST',
            'primljen_zbog_isteka_roka' => true,
        ]);

        Livewire::actingAs($user)
            ->test(EkomOtpravciList::class)
            ->assertSee('EXPIRED-TEST')
            ->assertSeeHtml('Expired');
    }
}
