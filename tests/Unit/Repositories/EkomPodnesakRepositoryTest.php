<?php

namespace Tests\Unit\Repositories;

use App\Models\EkomPodnesak;
use App\Repositories\EkomPodnesakRepository;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EkomPodnesakRepositoryTest extends TestCase
{
    use UsesTestDatabase;

    private EkomPodnesakRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EkomPodnesakRepository;
    }

    /** @test */
    public function it_creates_new_podnesak_from_paged_api_data()
    {
        $apiData = [
            'id' => 54321,
            'status' => 'zaprimljen',
            'sud' => ['id' => 'SUD001'],
            'vrstaPodneska' => ['id' => 'VP01'],
            'vrijemeKreiranja' => '2024-01-15 08:00:00',
            'vrijemeSlanja' => '2024-01-15 08:30:00',
            'vrijemeZaprimanja' => '2024-01-15 09:00:00',
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertInstanceOf(EkomPodnesak::class, $result);
        $this->assertEquals(54321, $result->remote_id);
        $this->assertEquals('zaprimljen', $result->status);
        $this->assertEquals('SUD001', $result->sud_remote_id);
        $this->assertEquals('VP01', $result->vrsta_podneska_remote_id);
        $this->assertEquals('2024-01-15 08:00:00', $result->vrijeme_kreiranja);
        $this->assertEquals('2024-01-15 08:30:00', $result->vrijeme_slanja);
        $this->assertEquals('2024-01-15 09:00:00', $result->vrijeme_zaprimanja);
        $this->assertNotNull($result->last_synced_at);
    }

    /** @test */
    public function it_updates_existing_podnesak_from_paged_api_data()
    {
        $existing = EkomPodnesak::create([
            'remote_id' => 54321,
            'status' => 'kreiran',
        ]);

        $apiData = [
            'id' => 54321,
            'status' => 'poslan',
            'sud' => ['id' => 'SUD002'],
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertEquals($existing->id, $result->id);
        $this->assertEquals('poslan', $result->status);
        $this->assertEquals('SUD002', $result->sud_remote_id);
        $this->assertEquals(1, EkomPodnesak::count());
    }

    /** @test */
    public function it_preserves_existing_status_when_not_provided()
    {
        $existing = EkomPodnesak::create([
            'remote_id' => 54321,
            'status' => 'original_status',
        ]);

        $apiData = [
            'id' => 54321,
            'status' => null,
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertEquals('original_status', $result->status);
    }

    /** @test */
    public function it_extracts_nested_sud_id()
    {
        $apiData = [
            'id' => 54321,
            'sud' => [
                'id' => 'ZSOSIJEK',
                'naziv' => 'Županijski sud u Osijeku',
            ],
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertEquals('ZSOSIJEK', $result->sud_remote_id);
    }

    /** @test */
    public function it_extracts_nested_vrsta_podneska_id()
    {
        $apiData = [
            'id' => 54321,
            'vrstaPodneska' => [
                'id' => 'TUZBA',
                'naziv' => 'Tužba',
            ],
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertEquals('TUZBA', $result->vrsta_podneska_remote_id);
    }

    /** @test */
    public function it_handles_missing_nested_fields()
    {
        $apiData = [
            'id' => 54321,
            'status' => 'zaprimljen',
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertNull($result->sud_remote_id);
        $this->assertNull($result->vrsta_podneska_remote_id);
        $this->assertNull($result->vrijeme_kreiranja);
        $this->assertNull($result->vrijeme_slanja);
        $this->assertNull($result->vrijeme_zaprimanja);
    }

    /** @test */
    public function it_stores_complete_api_data()
    {
        $apiData = [
            'id' => 54321,
            'status' => 'zaprimljen',
            'sud' => ['id' => 'SUD001', 'naziv' => 'Test Sud'],
            'vrstaPodneska' => ['id' => 'VP01', 'naziv' => 'Test Podnesak'],
            'dodatni_podaci' => [
                'stranka' => 'ABC d.o.o.',
                'predmet' => 'Naknada štete',
            ],
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertEquals($apiData, $result->data);
        $this->assertEquals('ABC d.o.o.', $result->data['dodatni_podaci']['stranka']);
    }

    /** @test */
    public function it_updates_last_synced_at_timestamp()
    {
        Carbon::setTestNow('2024-01-20 15:30:00');

        $apiData = [
            'id' => 54321,
            'status' => 'zaprimljen',
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertEquals('2024-01-20 15:30:00', $result->last_synced_at->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_handles_croatian_timestamp_formats()
    {
        $apiData = [
            'id' => 54321,
            'vrijemeKreiranja' => '2024-01-15T08:00:00',
            'vrijemeSlanja' => '2024-01-15T08:30:00',
            'vrijemeZaprimanja' => '2024-01-15T09:00:00',
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertStringContainsString('2024-01-15', $result->vrijeme_kreiranja);
        $this->assertStringContainsString('08:00:00', $result->vrijeme_kreiranja);
    }

    /** @test */
    public function upsert_from_detail_api_delegates_to_paged_api()
    {
        $apiData = [
            'id' => 54321,
            'status' => 'zaprimljen',
            'sud' => ['id' => 'SUD001'],
        ];

        $result = $this->repository->upsertFromDetailApi($apiData);

        $this->assertInstanceOf(EkomPodnesak::class, $result);
        $this->assertEquals(54321, $result->remote_id);
        $this->assertEquals('zaprimljen', $result->status);
    }

    /** @test */
    public function it_handles_multiple_upserts_for_same_remote_id()
    {
        $apiData1 = [
            'id' => 54321,
            'status' => 'kreiran',
        ];

        $apiData2 = [
            'id' => 54321,
            'status' => 'poslan',
        ];

        $apiData3 = [
            'id' => 54321,
            'status' => 'zaprimljen',
        ];

        $result1 = $this->repository->upsertFromPagedApi($apiData1);
        $result2 = $this->repository->upsertFromPagedApi($apiData2);
        $result3 = $this->repository->upsertFromPagedApi($apiData3);

        $this->assertEquals($result1->id, $result2->id);
        $this->assertEquals($result2->id, $result3->id);
        $this->assertEquals('zaprimljen', $result3->status);
        $this->assertEquals(1, EkomPodnesak::count());
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

        $this->assertEquals(3, EkomPodnesak::count());
    }

    /** @test */
    public function it_tracks_submission_workflow_timestamps()
    {
        $apiData = [
            'id' => 54321,
            'status' => 'zaprimljen',
            'vrijemeKreiranja' => '2024-01-15 08:00:00',
            'vrijemeSlanja' => '2024-01-15 08:30:00',
            'vrijemeZaprimanja' => '2024-01-15 09:00:00',
        ];

        $result = $this->repository->upsertFromPagedApi($apiData);

        $this->assertNotNull($result->vrijeme_kreiranja);
        $this->assertNotNull($result->vrijeme_slanja);
        $this->assertNotNull($result->vrijeme_zaprimanja);
        $this->assertEquals('2024-01-15 08:00:00', $result->vrijeme_kreiranja);
        $this->assertEquals('2024-01-15 08:30:00', $result->vrijeme_slanja);
        $this->assertEquals('2024-01-15 09:00:00', $result->vrijeme_zaprimanja);
    }
}
