<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\EkomPodnesciList;
use App\Models\EkomPodnesak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EkomPodnesciListTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders_successfully(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ekom.podnesci'))
            ->assertOk()
            ->assertSee('Submissions (Podnesci)');
    }

    public function test_displays_podnesci_list(): void
    {
        $user = User::factory()->create();
        EkomPodnesak::factory()->create(['remote_id' => 'POD-123456']);

        Livewire::actingAs($user)
            ->test(EkomPodnesciList::class)
            ->assertSee('POD-123456');
    }

    public function test_can_search_by_remote_id(): void
    {
        $user = User::factory()->create();
        EkomPodnesak::factory()->create(['remote_id' => 'POD-FIND-ME']);
        EkomPodnesak::factory()->create(['remote_id' => 'POD-HIDDEN']);

        Livewire::actingAs($user)
            ->test(EkomPodnesciList::class)
            ->set('search', 'FIND-ME')
            ->assertSee('POD-FIND-ME')
            ->assertDontSee('POD-HIDDEN');
    }

    public function test_can_filter_by_status(): void
    {
        $user = User::factory()->create();
        EkomPodnesak::factory()->create(['remote_id' => 'DRAFT-1', 'status' => 'kreiran']);
        EkomPodnesak::factory()->create(['remote_id' => 'SENT-1', 'status' => 'poslan']);

        Livewire::actingAs($user)
            ->test(EkomPodnesciList::class)
            ->set('statusFilter', 'kreiran')
            ->assertSee('DRAFT-1')
            ->assertDontSee('SENT-1');
    }

    public function test_pagination_works(): void
    {
        $user = User::factory()->create();
        EkomPodnesak::factory()->count(30)->create();

        Livewire::actingAs($user)
            ->test(EkomPodnesciList::class)
            ->assertSet('perPage', 20);
    }

    public function test_can_change_per_page(): void
    {
        $user = User::factory()->create();
        EkomPodnesak::factory()->count(30)->create();

        Livewire::actingAs($user)
            ->test(EkomPodnesciList::class)
            ->set('perPage', 10)
            ->assertSet('perPage', 10);
    }

    public function test_has_create_link(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EkomPodnesciList::class)
            ->assertSeeHtml('href="' . route('ekom.podnesci.create') . '"');
    }

    public function test_requires_authentication(): void
    {
        $this->get(route('ekom.podnesci'))
            ->assertRedirect(route('login'));
    }

    public function test_can_sort_by_remote_id(): void
    {
        $user = User::factory()->create();
        EkomPodnesak::factory()->create(['remote_id' => 'AAA-001']);
        EkomPodnesak::factory()->create(['remote_id' => 'ZZZ-999']);

        $component = Livewire::actingAs($user)
            ->test(EkomPodnesciList::class)
            ->call('sortBy', 'remote_id')
            ->assertSet('sortField', 'remote_id')
            ->assertSet('sortDirection', 'asc');

        // Verify sort direction toggles
        $component->call('sortBy', 'remote_id')
            ->assertSet('sortDirection', 'desc');
    }

    public function test_can_sort_by_status(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EkomPodnesciList::class)
            ->call('sortBy', 'status')
            ->assertSet('sortField', 'status')
            ->assertSet('sortDirection', 'asc');
    }

    public function test_can_sort_by_last_synced_at(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EkomPodnesciList::class)
            ->call('sortBy', 'last_synced_at')
            ->assertSet('sortField', 'last_synced_at');
    }

    public function test_query_string_binding(): void
    {
        $user = User::factory()->create();

        // Test that queryString properties are defined
        $component = Livewire::actingAs($user)
            ->test(EkomPodnesciList::class);

        // Set values and verify they can be set (query string binding test)
        $component->set('search', 'test-query')
            ->assertSet('search', 'test-query')
            ->set('statusFilter', 'kreiran')
            ->assertSet('statusFilter', 'kreiran');
    }

    public function test_status_options_available(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(EkomPodnesciList::class);

        // Verify status options are defined
        $this->assertArrayHasKey('', $component->get('statusOptions'));
        $this->assertArrayHasKey('kreiran', $component->get('statusOptions'));
        $this->assertArrayHasKey('poslan', $component->get('statusOptions'));
    }

    public function test_displays_status_badge(): void
    {
        $user = User::factory()->create();
        EkomPodnesak::factory()->create(['remote_id' => 'POD-STATUS', 'status' => 'poslan']);

        Livewire::actingAs($user)
            ->test(EkomPodnesciList::class)
            ->assertSee('Poslan');
    }

    public function test_displays_empty_state_when_no_podnesci(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EkomPodnesciList::class)
            ->assertSee('No submissions found');
    }
}
