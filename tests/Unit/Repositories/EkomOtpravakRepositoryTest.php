<?php

namespace Tests\Unit\Repositories;

use App\Models\EkomOtpravak;
use App\Repositories\EkomOtpravakRepository;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EkomOtpravakRepositoryTest extends TestCase
{
    use UsesTestDatabase;

    private EkomOtpravakRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EkomOtpravakRepository;
    }

    /** @test */
    public function it_creates_new_otpravak_from_paged_api_data()
    {
        $apiData = [
            'id' => 98765,
            'status' => 'poslan',
            'predmet' => ['id' => 'PRED001'],
            'vrijemeSlanjaSaSuda' => '2024-01-15 10:00:00',
            'vrijemePotvrdePrimitka' => '2024-01-15 11:00:00',
            'primljenZbogIstekaRoka' => false,
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertInstanceOf(EkomOtpravak::class, $result);
        $this->assertEquals(98765, $result->remote_id);
        $this->assertEquals('poslan', $result->status);
        $this->assertEquals('PRED001', $result->predmet_remote_id);
        $this->assertEquals('2024-01-15 10:00:00', $result->vrijeme_slanja_sa_suda);
        $this->assertEquals('2024-01-15 11:00:00', $result->vrijeme_potvrde_primitka);
        $this->assertFalse($result->primljen_zbog_isteka_roka);
        $this->assertNotNull($result->last_synced_at);
    }

    /** @test */
    public function it_updates_existing_otpravak_from_paged_api_data()
    {
        $existing = EkomOtpravak::create([
            'remote_id' => 98765,
            'status' => 'kreiran',
        ]);

        $apiData = [
            'id' => 98765,
            'status' => 'dostavljen',
            'predmet' => ['id' => 'PRED002'],
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertEquals($existing->id, $result->id);
        $this->assertEquals('dostavljen', $result->status);
        $this->assertEquals('PRED002', $result->predmet_remote_id);
        $this->assertEquals(1, EkomOtpravak::count());
    }

    /** @test */
    public function it_preserves_existing_status_when_not_provided()
    {
        $existing = EkomOtpravak::create([
            'remote_id' => 98765,
            'status' => 'original_status',
        ]);

        $apiData = [
            'id' => 98765,
            'status' => null,
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertEquals('original_status', $result->status);
    }

    /** @test */
    public function it_extracts_nested_predmet_id()
    {
        $apiData = [
            'id' => 98765,
            'predmet' => [
                'id' => 'K-123/2024',
                'oznaka' => 'K-123/2024',
                'sud' => ['naziv' => 'Županijski sud'],
            ],
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertEquals('K-123/2024', $result->predmet_remote_id);
    }

    /** @test */
    public function it_handles_primljen_zbog_isteka_roka_as_true()
    {
        $apiData = [
            'id' => 98765,
            'status' => 'istekao_rok',
            'primljenZbogIstekaRoka' => true,
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertTrue($result->primljen_zbog_isteka_roka);
        $this->assertIsBool($result->primljen_zbog_isteka_roka);
    }

    /** @test */
    public function it_defaults_primljen_zbog_isteka_roka_to_false()
    {
        $apiData = [
            'id' => 98765,
            'status' => 'poslan',
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertFalse($result->primljen_zbog_isteka_roka);
    }

    /** @test */
    public function it_converts_string_primljen_zbog_isteka_roka_to_boolean()
    {
        $apiData = [
            'id' => 98765,
            'primljenZbogIstekaRoka' => '1',
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertIsBool($result->primljen_zbog_isteka_roka);
        $this->assertTrue($result->primljen_zbog_isteka_roka);
    }

    /** @test */
    public function it_handles_missing_nested_fields()
    {
        $apiData = [
            'id' => 98765,
            'status' => 'poslan',
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertNull($result->predmet_remote_id);
        $this->assertNull($result->vrijeme_slanja_sa_suda);
        $this->assertNull($result->vrijeme_potvrde_primitka);
    }

    /** @test */
    public function it_stores_complete_api_data()
    {
        $apiData = [
            'id' => 98765,
            'status' => 'dostavljen',
            'predmet' => [
                'id' => 'PRED001',
                'oznaka' => 'K-123/2024',
            ],
            'vrijemeSlanjaSaSuda' => '2024-01-15 10:00:00',
            'dodatni_podaci' => [
                'dokument' => 'presuda.pdf',
                'tip' => 'presuda',
            ],
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertEquals($apiData, $result->data);
        $this->assertEquals('presuda.pdf', $result->data['dodatni_podaci']['dokument']);
    }

    /** @test */
    public function it_updates_last_synced_at_timestamp()
    {
        Carbon::setTestNow('2024-01-25 14:00:00');

        $apiData = [
            'id' => 98765,
            'status' => 'poslan',
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertEquals('2024-01-25 14:00:00', $result->last_synced_at->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_tracks_dispatch_delivery_workflow()
    {
        $apiData = [
            'id' => 98765,
            'status' => 'dostavljen',
            'vrijemeSlanjaSaSuda' => '2024-01-15 09:00:00',
            'vrijemePotvrdePrimitka' => '2024-01-15 10:30:00',
            'primljenZbogIstekaRoka' => false,
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertNotNull($result->vrijeme_slanja_sa_suda);
        $this->assertNotNull($result->vrijeme_potvrde_primitka);
        $this->assertEquals('2024-01-15 09:00:00', $result->vrijeme_slanja_sa_suda);
        $this->assertEquals('2024-01-15 10:30:00', $result->vrijeme_potvrde_primitka);
        $this->assertFalse($result->primljen_zbog_isteka_roka);
    }

    /** @test */
    public function it_handles_deadline_expiration_scenario()
    {
        $apiData = [
            'id' => 98765,
            'status' => 'istekao_rok',
            'vrijemeSlanjaSaSuda' => '2024-01-01 10:00:00',
            'primljenZbogIstekaRoka' => true,
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertTrue($result->primljen_zbog_isteka_roka);
        $this->assertEquals('istekao_rok', $result->status);
        $this->assertNull($result->vrijeme_potvrde_primitka);
    }

    /** @test */
    public function it_handles_multiple_upserts_for_same_remote_id()
    {
        $apiData1 = [
            'id' => 98765,
            'status' => 'kreiran',
        ];

        $apiData2 = [
            'id' => 98765,
            'status' => 'poslan',
        ];

        $apiData3 = [
            'id' => 98765,
            'status' => 'dostavljen',
        ];

        $result1 = $this->repository->upsertFromPagedApi($apiData1);
        $result2 = $this->repository->upsertFromPagedApi($apiData2);
        $result3 = $this->repository->upsertFromPagedApi($apiData3);

        $this->assertEquals($result1->id, $result2->id);
        $this->assertEquals($result2->id, $result3->id);
        $this->assertEquals('dostavljen', $result3->status);
        $this->assertEquals(1, EkomOtpravak::count());
    }

    /** @test */
    public function it_handles_different_remote_ids_as_separate_records()
    {
        $apiData1 = ['id' => 100, 'status' => 'status1'];
        $apiData2 = ['id' => 200, 'status' => 'status2'];
        $apiData3 = ['id' => 300, 'status' => 'status3'];

        $this->repository->upsertFromPagedApi($apiData1);
        $this->repository->upsertFromPagedApi($apiData2);
        $this->repository->upsertFromPagedApi($apiData3);

        $this->assertEquals(3, EkomOtpravak::count());
    }
}
