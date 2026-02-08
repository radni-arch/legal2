<?php

namespace Tests\Feature\Livewire;

use App\Contracts\External\EkomServiceInterface;
use App\Http\Livewire\EkomPredmetDetail;
use App\Models\EkomPredmet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class EkomPredmetDetailTest extends TestCase
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
        $this->ekomServiceMock->shouldReceive('syncPredmeti')->andReturn(1)->byDefault();
        $this->ekomServiceMock->shouldReceive('download')->andReturn('/tmp/test.pdf')->byDefault();

        $this->app->instance(EkomServiceInterface::class, $this->ekomServiceMock);
    }

    /**
     * Test 1: Component renders successfully when authenticated
     */
    public function test_component_renders_successfully(): void
    {
        $user = User::factory()->create();
        $predmet = EkomPredmet::factory()->create(['oznaka' => 'Test-Case/2025']);

        $this->actingAs($user)
            ->get(route('ekom.predmeti.show', $predmet->remote_id))
            ->assertOk()
            ->assertSee('Test-Case/2025');
    }

    /**
     * Test 2: Component displays predmet details (oznaka, status, court info)
     */
    public function test_displays_predmet_details(): void
    {
        $user = User::factory()->create();
        $predmet = EkomPredmet::factory()->create([
            'oznaka' => 'Pp-999/2025',
            'status' => 'aktivan',
            'data' => [
                'sudNaziv' => 'Trgovacki sud u Zagrebu',
                'brojPredmeta' => 999,
                'stranka_tuzitelj' => 'Test Company d.o.o.',
            ],
        ]);

        Livewire::actingAs($user)
            ->test(EkomPredmetDetail::class, ['remoteId' => $predmet->remote_id])
            ->assertSee('Pp-999/2025')
            ->assertSee('aktivan')
            ->assertSee('Trgovacki sud u Zagrebu');
    }

    /**
     * Test 3: Shows 404 for invalid remote ID
     */
    public function test_shows_404_for_invalid_remote_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ekom.predmeti.show', 'invalid-id-99999999'))
            ->assertNotFound();
    }

    /**
     * Test 4: Can toggle DND on
     */
    public function test_can_toggle_dnd_on(): void
    {
        $user = User::factory()->create();
        $predmet = EkomPredmet::factory()->create([
            'do_not_disturb' => false,
        ]);

        $this->ekomServiceMock->shouldReceive('turnOnDndPredmet')
            ->once()
            ->with($predmet->id)
            ->andReturn(true);

        Livewire::actingAs($user)
            ->test(EkomPredmetDetail::class, ['remoteId' => $predmet->remote_id])
            ->call('toggleDnd')
            ->assertDispatched('success');

        $this->assertTrue($predmet->fresh()->do_not_disturb);
    }

    /**
     * Test 5: Can toggle DND off
     */
    public function test_can_toggle_dnd_off(): void
    {
        $user = User::factory()->create();
        $predmet = EkomPredmet::factory()->create([
            'do_not_disturb' => true,
        ]);

        $this->ekomServiceMock->shouldReceive('turnOffDndPredmet')
            ->once()
            ->with($predmet->id)
            ->andReturn(true);

        Livewire::actingAs($user)
            ->test(EkomPredmetDetail::class, ['remoteId' => $predmet->remote_id])
            ->call('toggleDnd')
            ->assertDispatched('success');

        $this->assertFalse($predmet->fresh()->do_not_disturb);
    }

    /**
     * Test 6: Requires authentication
     */
    public function test_requires_authentication(): void
    {
        $predmet = EkomPredmet::factory()->create();

        $this->get(route('ekom.predmeti.show', $predmet->remote_id))
            ->assertRedirect(route('login'));
    }

    /**
     * Test 7: Can refresh data from API
     */
    public function test_can_refresh_from_api(): void
    {
        $user = User::factory()->create();
        $predmet = EkomPredmet::factory()->create([
            'remote_id' => 'PRED12345',
        ]);

        $this->ekomServiceMock->shouldReceive('syncPredmeti')
            ->once()
            ->andReturn(1);

        Livewire::actingAs($user)
            ->test(EkomPredmetDetail::class, ['remoteId' => $predmet->remote_id])
            ->call('refreshFromApi')
            ->assertDispatched('success');
    }

    /**
     * Test 8: Can initiate document download
     */
    public function test_can_download_documents(): void
    {
        $user = User::factory()->create();
        $predmet = EkomPredmet::factory()->create([
            'remote_id' => 'PRED12345',
        ]);

        Livewire::actingAs($user)
            ->test(EkomPredmetDetail::class, ['remoteId' => $predmet->remote_id])
            ->call('downloadDocuments')
            ->assertDispatched('success');
    }

    /**
     * Test 9: Shows raw JSON data from API
     */
    public function test_shows_raw_json_data(): void
    {
        $user = User::factory()->create();
        $predmet = EkomPredmet::factory()->create([
            'data' => [
                'uniqueField' => 'UNIQUE_TEST_VALUE_12345',
                'nested' => ['key' => 'value'],
            ],
        ]);

        Livewire::actingAs($user)
            ->test(EkomPredmetDetail::class, ['remoteId' => $predmet->remote_id])
            ->assertSee('UNIQUE_TEST_VALUE_12345');
    }

    /**
     * Test 10: Displays last synced timestamp
     */
    public function test_displays_last_synced_timestamp(): void
    {
        $user = User::factory()->create();
        $predmet = EkomPredmet::factory()->create([
            'last_synced_at' => now()->subHours(2),
        ]);

        Livewire::actingAs($user)
            ->test(EkomPredmetDetail::class, ['remoteId' => $predmet->remote_id])
            ->assertSee('Last Synced');
    }

    /**
     * Test 11: Has link back to predmeti list
     */
    public function test_has_back_link(): void
    {
        $user = User::factory()->create();
        $predmet = EkomPredmet::factory()->create();

        Livewire::actingAs($user)
            ->test(EkomPredmetDetail::class, ['remoteId' => $predmet->remote_id])
            ->assertSee('Back to Cases');
    }

    /**
     * Test 12: DND toggle error handling
     */
    public function test_dnd_toggle_handles_errors(): void
    {
        $user = User::factory()->create();
        $predmet = EkomPredmet::factory()->create([
            'do_not_disturb' => false,
        ]);

        $this->ekomServiceMock->shouldReceive('turnOnDndPredmet')
            ->once()
            ->andThrow(new \Exception('API Error'));

        Livewire::actingAs($user)
            ->test(EkomPredmetDetail::class, ['remoteId' => $predmet->remote_id])
            ->call('toggleDnd')
            ->assertDispatched('error');
    }

    /**
     * Test 13: Refresh from API error handling
     */
    public function test_refresh_from_api_handles_errors(): void
    {
        $user = User::factory()->create();
        $predmet = EkomPredmet::factory()->create();

        $this->ekomServiceMock->shouldReceive('syncPredmeti')
            ->once()
            ->andThrow(new \Exception('Sync failed'));

        Livewire::actingAs($user)
            ->test(EkomPredmetDetail::class, ['remoteId' => $predmet->remote_id])
            ->call('refreshFromApi')
            ->assertDispatched('error');
    }

    /**
     * Test 14: Renders correct view
     */
    public function test_renders_correct_view(): void
    {
        $user = User::factory()->create();
        $predmet = EkomPredmet::factory()->create();

        Livewire::actingAs($user)
            ->test(EkomPredmetDetail::class, ['remoteId' => $predmet->remote_id])
            ->assertStatus(200)
            ->assertViewIs('livewire.ekom-predmet-detail');
    }
}
