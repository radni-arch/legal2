<?php

namespace Tests\Unit\Repositories;

use App\Models\EkomPredmet;
use App\Repositories\EkomPredmetRepository;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EkomPredmetRepositoryTest extends TestCase
{
    use UsesTestDatabase;

    private EkomPredmetRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EkomPredmetRepository;
    }

    /** @test */
    public function it_creates_new_predmet_from_api_data()
    {
        $apiData = [
            'id' => 12345,
            'oznaka' => 'K-123/2024',
            'status' => 'aktivan',
            'sud' => ['id' => 'SUD001'],
            'doNotDisturb' => false,
        ];

        $result = $this->repository->upsertFromApi($apiData);

        $this->assertInstanceOf(EkomPredmet::class, $result);
        $this->assertEquals(12345, $result->remote_id);
        $this->assertEquals('K-123/2024', $result->oznaka);
        $this->assertEquals('aktivan', $result->status);
        $this->assertEquals('SUD001', $result->sud_remote_id);
        $this->assertFalse($result->do_not_disturb);
        $this->assertEquals($apiData, $result->data);
        $this->assertNotNull($result->last_synced_at);
    }

    /** @test */
    public function it_updates_existing_predmet_from_api_data()
    {
        $existing = EkomPredmet::create([
            'remote_id' => 12345,
            'oznaka' => 'K-100/2024',
            'status' => 'otvoren',
        ]);

        $apiData = [
            'id' => 12345,
            'oznaka' => 'K-123/2024',
            'status' => 'zatvoren',
            'sud' => ['id' => 'SUD002'],
            'doNotDisturb' => true,
        ];

        $result = $this->repository->upsertFromApi($apiData);

        $this->assertEquals($existing->id, $result->id);
        $this->assertEquals('K-123/2024', $result->oznaka);
        $this->assertEquals('zatvoren', $result->status);
        $this->assertEquals('SUD002', $result->sud_remote_id);
        $this->assertTrue($result->do_not_disturb);
        $this->assertEquals(1, EkomPredmet::count());
    }

    /** @test */
    public function it_preserves_existing_values_when_api_data_is_null()
    {
        $existing = EkomPredmet::create([
            'remote_id' => 12345,
            'oznaka' => 'K-100/2024',
            'status' => 'aktivan',
        ]);

        $apiData = [
            'id' => 12345,
            'oznaka' => null,
            'status' => null,
        ];

        $result = $this->repository->upsertFromApi($apiData);

        $this->assertEquals('K-100/2024', $result->oznaka);
        $this->assertEquals('aktivan', $result->status);
    }

    /** @test */
    public function it_updates_last_synced_at_timestamp()
    {
        Carbon::setTestNow('2024-01-15 10:00:00');

        $apiData = [
            'id' => 12345,
            'oznaka' => 'K-123/2024',
        ];

        $result = $this->repository->upsertFromApi($apiData);

        $this->assertEquals('2024-01-15 10:00:00', $result->last_synced_at->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_handles_missing_sud_data()
    {
        $apiData = [
            'id' => 12345,
            'oznaka' => 'K-123/2024',
            'status' => 'aktivan',
        ];

        $result = $this->repository->upsertFromApi($apiData);

        $this->assertNull($result->sud_remote_id);
    }

    /** @test */
    public function it_defaults_do_not_disturb_to_false()
    {
        $apiData = [
            'id' => 12345,
            'oznaka' => 'K-123/2024',
        ];

        $result = $this->repository->upsertFromApi($apiData);

        $this->assertFalse($result->do_not_disturb);
    }

    /** @test */
    public function it_sets_do_not_disturb_to_true_when_provided()
    {
        $apiData = [
            'id' => 12345,
            'oznaka' => 'K-123/2024',
            'doNotDisturb' => true,
        ];

        $result = $this->repository->upsertFromApi($apiData);

        $this->assertTrue($result->do_not_disturb);
    }

    /** @test */
    public function it_converts_string_do_not_disturb_to_boolean()
    {
        $apiData = [
            'id' => 12345,
            'oznaka' => 'K-123/2024',
            'doNotDisturb' => '1',
        ];

        $result = $this->repository->upsertFromApi($apiData);

        $this->assertIsBool($result->do_not_disturb);
        $this->assertTrue($result->do_not_disturb);
    }

    /** @test */
    public function it_stores_complete_api_data_in_data_field()
    {
        $apiData = [
            'id' => 12345,
            'oznaka' => 'K-123/2024',
            'status' => 'aktivan',
            'sud' => [
                'id' => 'SUD001',
                'naziv' => 'Županijski sud u Zagrebu',
            ],
            'dodatni_podaci' => [
                'sudac' => 'Ivan Horvat',
                'datum_otvaranja' => '2024-01-01',
            ],
        ];

        $result = $this->repository->upsertFromApi($apiData);

        $this->assertEquals($apiData, $result->data);
        $this->assertEquals('Županijski sud u Zagrebu', $result->data['sud']['naziv']);
        $this->assertEquals('Ivan Horvat', $result->data['dodatni_podaci']['sudac']);
    }

    /** @test */
    public function it_extracts_nested_sud_id_correctly()
    {
        $apiData = [
            'id' => 12345,
            'oznaka' => 'K-123/2024',
            'sud' => [
                'id' => 'ZSZG',
                'naziv' => 'Županijski sud Zagreb',
                'nested' => ['data' => 'value'],
            ],
        ];

        $result = $this->repository->upsertFromApi($apiData);

        $this->assertEquals('ZSZG', $result->sud_remote_id);
    }

    /** @test */
    public function it_handles_multiple_upserts_for_same_remote_id()
    {
        $apiData1 = [
            'id' => 12345,
            'oznaka' => 'K-100/2024',
            'status' => 'otvoren',
        ];

        $apiData2 = [
            'id' => 12345,
            'oznaka' => 'K-100/2024',
            'status' => 'u_tijeku',
        ];

        $apiData3 = [
            'id' => 12345,
            'oznaka' => 'K-100/2024',
            'status' => 'zatvoren',
        ];

        $result1 = $this->repository->upsertFromApi($apiData1);
        $result2 = $this->repository->upsertFromApi($apiData2);
        $result3 = $this->repository->upsertFromApi($apiData3);

        $this->assertEquals($result1->id, $result2->id);
        $this->assertEquals($result2->id, $result3->id);
        $this->assertEquals('zatvoren', $result3->status);
        $this->assertEquals(1, EkomPredmet::count());
    }

    /** @test */
    public function it_handles_different_remote_ids_as_separate_records()
    {
        $apiData1 = ['id' => 100, 'oznaka' => 'K-100/2024'];
        $apiData2 = ['id' => 200, 'oznaka' => 'K-200/2024'];
        $apiData3 = ['id' => 300, 'oznaka' => 'K-300/2024'];

        $this->repository->upsertFromApi($apiData1);
        $this->repository->upsertFromApi($apiData2);
        $this->repository->upsertFromApi($apiData3);

        $this->assertEquals(3, EkomPredmet::count());
    }
}
