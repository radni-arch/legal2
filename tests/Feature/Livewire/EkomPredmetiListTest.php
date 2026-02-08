<?php

namespace Tests\Feature\Livewire;

use App\Contracts\External\EkomServiceInterface;
use App\Http\Livewire\EkomPredmetiList;
use App\Models\EkomPredmet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class EkomPredmetiListTest extends TestCase
{
    use RefreshDatabase;

    protected MockInterface $ekomServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the EkomServiceInterface for all tests
        $this->ekomServiceMock = Mockery::mock(EkomServiceInterface::class);

        // Allow any DND calls by default (tests can override)
        $this->ekomServiceMock->shouldReceive('turnOnDndPredmet')->andReturn(true)->byDefault();
        $this->ekomServiceMock->shouldReceive('turnOffDndPredmet')->andReturn(true)->byDefault();

        $this->app->instance(EkomServiceInterface::class, $this->ekomServiceMock);
    }

    /**
     * Test 1: Component renders successfully when authenticated
     */
    public function test_component_renders_successfully(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ekom.predmeti'))
            ->assertOk()
            ->assertSee('Cases (Predmeti)');
    }

    /**
     * Test 2: Component displays predmeti list with oznaka
     */
    public function test_displays_predmeti_list(): void
    {
        $user = User::factory()->create();
        EkomPredmet::factory()->create(['oznaka' => 'Pp-123/2025']);

        Livewire::actingAs($user)
            ->test(EkomPredmetiList::class)
            ->assertSee('Pp-123/2025');
    }

    /**
     * Test 3: Component requires authentication
     */
    public function test_requires_authentication(): void
    {
        $this->get(route('ekom.predmeti'))
            ->assertRedirect(route('login'));
    }

    /**
     * Test 4: Can search predmeti by oznaka
     */
    public function test_can_search_predmeti(): void
    {
        $user = User::factory()->create();
        EkomPredmet::factory()->create(['oznaka' => 'Pp-123/2025']);
        EkomPredmet::factory()->create(['oznaka' => 'K-456/2025']);

        Livewire::actingAs($user)
            ->test(EkomPredmetiList::class)
            ->set('search', 'Pp-123')
            ->assertSee('Pp-123/2025')
            ->assertDontSee('K-456/2025');
    }

    /**
     * Test 5: Can filter by status
     */
    public function test_can_filter_by_status(): void
    {
        $user = User::factory()->create();
        EkomPredmet::factory()->create(['oznaka' => 'Active-1/2025', 'status' => 'aktivan']);
        EkomPredmet::factory()->create(['oznaka' => 'Closed-1/2025', 'status' => 'zatvoren']);

        Livewire::actingAs($user)
            ->test(EkomPredmetiList::class)
            ->set('statusFilter', 'aktivan')
            ->assertSee('Active-1/2025')
            ->assertDontSee('Closed-1/2025');
    }

    /**
     * Test 6: Can filter by DND state
     */
    public function test_can_filter_by_dnd_state(): void
    {
        $user = User::factory()->create();
        EkomPredmet::factory()->create(['oznaka' => 'DND-On/2025', 'do_not_disturb' => true]);
        EkomPredmet::factory()->create(['oznaka' => 'DND-Off/2025', 'do_not_disturb' => false]);

        Livewire::actingAs($user)
            ->test(EkomPredmetiList::class)
            ->set('dndFilter', 'enabled')
            ->assertSee('DND-On/2025')
            ->assertDontSee('DND-Off/2025');
    }

    /**
     * Test 7: Pagination has configurable page size
     */
    public function test_pagination_works(): void
    {
        $user = User::factory()->create();
        EkomPredmet::factory()->count(30)->create();

        Livewire::actingAs($user)
            ->test(EkomPredmetiList::class)
            ->assertSet('perPage', 20);
    }

    /**
     * Test 8: Can change pagination page size
     */
    public function test_can_change_page_size(): void
    {
        $user = User::factory()->create();
        EkomPredmet::factory()->count(30)->create();

        Livewire::actingAs($user)
            ->test(EkomPredmetiList::class)
            ->set('perPage', 10)
            ->assertSet('perPage', 10);
    }

    /**
     * Test 9: Can sort by column
     */
    public function test_can_sort_by_column(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EkomPredmetiList::class)
            ->assertSet('sortField', 'last_synced_at')
            ->call('sortBy', 'oznaka')
            ->assertSet('sortField', 'oznaka')
            ->assertSet('sortDirection', 'asc');
    }

    /**
     * Test 10: Sorting toggles direction on same column
     */
    public function test_sort_toggles_direction(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EkomPredmetiList::class)
            ->call('sortBy', 'oznaka')
            ->assertSet('sortDirection', 'asc')
            ->call('sortBy', 'oznaka')
            ->assertSet('sortDirection', 'desc');
    }

    /**
     * Test 11: Toggle DND on calls service correctly
     */
    public function test_toggle_dnd_on_calls_service(): void
    {
        $user = User::factory()->create();
        $predmet = EkomPredmet::factory()->create([
            'remote_id' => 'PRED123',
            'do_not_disturb' => false,
        ]);

        // Set specific expectations for this test
        $this->ekomServiceMock->shouldReceive('turnOnDndPredmet')
            ->once()
            ->with($predmet->id)
            ->andReturn(true);

        Livewire::actingAs($user)
            ->test(EkomPredmetiList::class)
            ->call('toggleDnd', $predmet->id);

        $this->assertTrue($predmet->fresh()->do_not_disturb);
    }

    /**
     * Test 12: Toggle DND off calls service correctly
     */
    public function test_toggle_dnd_off_calls_service(): void
    {
        $user = User::factory()->create();
        $predmet = EkomPredmet::factory()->create([
            'remote_id' => 'PRED456',
            'do_not_disturb' => true,
        ]);

        // Set specific expectations for this test
        $this->ekomServiceMock->shouldReceive('turnOffDndPredmet')
            ->once()
            ->with($predmet->id)
            ->andReturn(true);

        Livewire::actingAs($user)
            ->test(EkomPredmetiList::class)
            ->call('toggleDnd', $predmet->id);

        $this->assertFalse($predmet->fresh()->do_not_disturb);
    }

    /**
     * Test 13: Query string binding for shareable links
     */
    public function test_query_string_binding(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(EkomPredmetiList::class)
            ->set('search', 'test-search')
            ->set('statusFilter', 'aktivan');

        // Query string should include search and status filter
        $this->assertEquals('test-search', $component->get('search'));
        $this->assertEquals('aktivan', $component->get('statusFilter'));
    }

    /**
     * Test 14: Empty search returns all predmeti
     */
    public function test_empty_search_returns_all_predmeti(): void
    {
        $user = User::factory()->create();
        EkomPredmet::factory()->create(['oznaka' => 'First/2025']);
        EkomPredmet::factory()->create(['oznaka' => 'Second/2025']);

        Livewire::actingAs($user)
            ->test(EkomPredmetiList::class)
            ->set('search', '')
            ->assertSee('First/2025')
            ->assertSee('Second/2025');
    }

    /**
     * Test 15: Renders correct view
     */
    public function test_renders_correct_view(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EkomPredmetiList::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.ekom-predmeti-list');
    }

    /**
     * Test 16: Toggle DND handles service error gracefully
     */
    public function test_toggle_dnd_handles_service_error(): void
    {
        $user = User::factory()->create();
        $predmet = EkomPredmet::factory()->create(['do_not_disturb' => false]);

        $this->mock(\App\Contracts\External\EkomServiceInterface::class)
            ->shouldReceive('turnOnDndPredmet')
            ->andThrow(new \Exception('API Error'));

        Livewire::actingAs($user)
            ->test(EkomPredmetiList::class)
            ->call('toggleDnd', $predmet->id)
            ->assertDispatched('error');
    }
}
