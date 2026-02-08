<?php

namespace Tests\Feature\Api;

use App\Jobs\EkomSyncAllJob;
use App\Models\EkomOtpravak;
use App\Models\EkomPodnesak;
use App\Models\EkomPredmet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Feature tests for the EKOM Internal API
 *
 * Tests all endpoints:
 * - GET /api/ekom/predmeti - List predmeti with pagination and search
 * - GET /api/ekom/predmeti/{remoteId} - Get single predmet by remote_id
 * - GET /api/ekom/podnesci - List podnesci with pagination and status filter
 * - GET /api/ekom/otpravci - List otpravci with pagination and pending filter
 * - POST /api/ekom/sync - Trigger sync job
 */
class EkomApiTest extends TestCase
{
    use RefreshDatabase;

    // ========================================
    // Authentication Tests
    // ========================================

    public function test_list_predmeti_requires_auth(): void
    {
        $this->getJson('/api/ekom/predmeti')
            ->assertUnauthorized();
    }

    public function test_get_predmet_requires_auth(): void
    {
        $this->getJson('/api/ekom/predmeti/some-id')
            ->assertUnauthorized();
    }

    public function test_list_podnesci_requires_auth(): void
    {
        $this->getJson('/api/ekom/podnesci')
            ->assertUnauthorized();
    }

    public function test_list_otpravci_requires_auth(): void
    {
        $this->getJson('/api/ekom/otpravci')
            ->assertUnauthorized();
    }

    public function test_trigger_sync_requires_auth(): void
    {
        $this->postJson('/api/ekom/sync')
            ->assertUnauthorized();
    }

    // ========================================
    // Predmeti Endpoints
    // ========================================

    public function test_can_list_predmeti(): void
    {
        $this->withApiAuth();
        EkomPredmet::factory()->count(3)->create();

        $response = $this->getJson('/api/ekom/predmeti');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['remote_id', 'oznaka', 'status'],
                ],
            ]);
    }

    public function test_can_search_predmeti_by_oznaka(): void
    {
        $this->withApiAuth();
        EkomPredmet::factory()->create(['oznaka' => 'Pp-123/2025']);
        EkomPredmet::factory()->create(['oznaka' => 'K-456/2025']);

        $response = $this->getJson('/api/ekom/predmeti?search=Pp-123');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.oznaka', 'Pp-123/2025');
    }

    public function test_predmeti_pagination(): void
    {
        $this->withApiAuth();
        EkomPredmet::factory()->count(25)->create();

        $response = $this->getJson('/api/ekom/predmeti?per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('total', 25);
    }

    public function test_can_filter_predmeti_by_status(): void
    {
        $this->withApiAuth();
        EkomPredmet::factory()->create(['status' => 'otvoren']);
        EkomPredmet::factory()->create(['status' => 'zatvoren']);
        EkomPredmet::factory()->create(['status' => 'otvoren']);

        $response = $this->getJson('/api/ekom/predmeti?status=otvoren');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_get_single_predmet(): void
    {
        $this->withApiAuth();
        $predmet = EkomPredmet::factory()->create([
            'remote_id' => 'RPRED123456',
            'oznaka' => 'Pp-999/2025',
        ]);

        $response = $this->getJson("/api/ekom/predmeti/{$predmet->remote_id}");

        $response->assertOk()
            ->assertJsonPath('data.remote_id', 'RPRED123456')
            ->assertJsonPath('data.oznaka', 'Pp-999/2025');
    }

    public function test_returns_404_for_missing_predmet(): void
    {
        $this->withApiAuth();

        $this->getJson('/api/ekom/predmeti/nonexistent-id-99999')
            ->assertNotFound();
    }

    // ========================================
    // Podnesci Endpoints
    // ========================================

    public function test_can_list_podnesci(): void
    {
        $this->withApiAuth();
        EkomPodnesak::factory()->count(2)->create();

        $response = $this->getJson('/api/ekom/podnesci');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['remote_id', 'status'],
                ],
            ]);
    }

    public function test_can_filter_podnesci_by_status(): void
    {
        $this->withApiAuth();
        EkomPodnesak::factory()->create(['status' => 'kreiran']);
        EkomPodnesak::factory()->create(['status' => 'poslan']);
        EkomPodnesak::factory()->create(['status' => 'kreiran']);

        $response = $this->getJson('/api/ekom/podnesci?status=kreiran');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_podnesci_pagination(): void
    {
        $this->withApiAuth();
        EkomPodnesak::factory()->count(30)->create();

        $response = $this->getJson('/api/ekom/podnesci?per_page=15');

        $response->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('per_page', 15)
            ->assertJsonPath('total', 30);
    }

    // ========================================
    // Otpravci Endpoints
    // ========================================

    public function test_can_list_otpravci(): void
    {
        $this->withApiAuth();
        EkomOtpravak::factory()->count(2)->create();

        $response = $this->getJson('/api/ekom/otpravci');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['remote_id', 'status'],
                ],
            ]);
    }

    public function test_can_filter_pending_otpravci(): void
    {
        $this->withApiAuth();

        // Pending: vrijeme_potvrde_primitka is null
        EkomOtpravak::factory()->create(['vrijeme_potvrde_primitka' => null]);
        EkomOtpravak::factory()->create(['vrijeme_potvrde_primitka' => null]);

        // Confirmed: vrijeme_potvrde_primitka is set
        EkomOtpravak::factory()->create(['vrijeme_potvrde_primitka' => now()]);

        $response = $this->getJson('/api/ekom/otpravci?pending=1');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_otpravci_by_status(): void
    {
        $this->withApiAuth();
        EkomOtpravak::factory()->create(['status' => 'poslan']);
        EkomOtpravak::factory()->create(['status' => 'dostavljen']);
        EkomOtpravak::factory()->create(['status' => 'poslan']);

        $response = $this->getJson('/api/ekom/otpravci?status=poslan');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_otpravci_pagination(): void
    {
        $this->withApiAuth();
        EkomOtpravak::factory()->count(40)->create();

        $response = $this->getJson('/api/ekom/otpravci?per_page=20');

        $response->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('per_page', 20)
            ->assertJsonPath('total', 40);
    }

    // ========================================
    // Sync Endpoint
    // ========================================

    public function test_can_trigger_sync(): void
    {
        Queue::fake();
        $this->withApiAuth();

        $response = $this->postJson('/api/ekom/sync');

        $response->assertAccepted()
            ->assertJsonPath('message', 'Sync job dispatched');

        Queue::assertPushed(EkomSyncAllJob::class);
    }

    public function test_sync_returns_proper_response_structure(): void
    {
        Queue::fake();
        $this->withApiAuth();

        $response = $this->postJson('/api/ekom/sync');

        $response->assertAccepted()
            ->assertJsonStructure(['message']);
    }

    // ========================================
    // Rate Limiting Tests
    // ========================================

    public function test_api_endpoints_have_rate_limiting_headers(): void
    {
        $this->withApiAuth();

        $response = $this->getJson('/api/ekom/predmeti');

        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    // ========================================
    // Input Validation Tests
    // ========================================

    public function test_predmeti_validates_per_page_max(): void
    {
        $this->withApiAuth()
            ->getJson('/api/ekom/predmeti?per_page=500')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_predmeti_validates_status_values(): void
    {
        $this->withApiAuth()
            ->getJson('/api/ekom/predmeti?status=invalid_status')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_predmeti_validates_search_max_length(): void
    {
        $longSearch = str_repeat('a', 300);

        $this->withApiAuth()
            ->getJson('/api/ekom/predmeti?search=' . $longSearch)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['search']);
    }

    public function test_otpravci_validates_pending_is_boolean(): void
    {
        $this->withApiAuth()
            ->getJson('/api/ekom/otpravci?pending=invalid')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pending']);
    }

    public function test_podnesci_validates_per_page_max(): void
    {
        $this->withApiAuth()
            ->getJson('/api/ekom/podnesci?per_page=200')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_podnesci_validates_status_values(): void
    {
        $this->withApiAuth()
            ->getJson('/api/ekom/podnesci?status=nonexistent_status')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_otpravci_validates_per_page_max(): void
    {
        $this->withApiAuth()
            ->getJson('/api/ekom/otpravci?per_page=150')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_otpravci_validates_status_values(): void
    {
        $this->withApiAuth()
            ->getJson('/api/ekom/otpravci?status=bad_status')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }
}
