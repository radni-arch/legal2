<?php

namespace Tests\Unit\Models\Ekom;

use App\Models\Ekom\EkomCourt;
use App\Models\Ekom\EkomProcedureType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EkomCourtTest extends TestCase
{
    use RefreshDatabase;

    public function test_court_has_correct_table_name(): void
    {
        $court = new EkomCourt();
        $this->assertEquals('ekom_courts', $court->getTable());
    }

    public function test_court_has_fillable_attributes(): void
    {
        $court = new EkomCourt();
        $expected = ['remote_id', 'naziv', 'oznaka', 'vrsta_suda_id', 'vrsta_suda_naziv', 'data', 'synced_at'];
        $this->assertEquals($expected, $court->getFillable());
    }

    public function test_court_casts_data_as_array(): void
    {
        $court = EkomCourt::create([
            'remote_id' => 1,
            'naziv' => 'Opcinski sud u Zagrebu',
            'data' => ['key' => 'value'],
            'synced_at' => now(),
        ]);

        $court->refresh();
        $this->assertIsArray($court->data);
        $this->assertEquals(['key' => 'value'], $court->data);
    }

    public function test_court_casts_synced_at_as_datetime(): void
    {
        $court = EkomCourt::create([
            'remote_id' => 2,
            'naziv' => 'Test sud',
            'synced_at' => '2026-01-15 10:00:00',
        ]);

        $court->refresh();
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $court->synced_at);
    }

    public function test_court_has_many_procedure_types(): void
    {
        $court = EkomCourt::create([
            'remote_id' => 10,
            'naziv' => 'Test sud',
            'synced_at' => now(),
        ]);

        EkomProcedureType::create([
            'remote_id' => 100,
            'court_remote_id' => 10,
            'naziv' => 'Parnični postupak',
            'synced_at' => now(),
        ]);

        EkomProcedureType::create([
            'remote_id' => 101,
            'court_remote_id' => 10,
            'naziv' => 'Ovršni postupak',
            'synced_at' => now(),
        ]);

        $this->assertCount(2, $court->procedureTypes);
        $this->assertInstanceOf(EkomProcedureType::class, $court->procedureTypes->first());
    }

    public function test_scope_by_remote_id(): void
    {
        EkomCourt::create([
            'remote_id' => 20,
            'naziv' => 'Sud A',
            'synced_at' => now(),
        ]);

        EkomCourt::create([
            'remote_id' => 21,
            'naziv' => 'Sud B',
            'synced_at' => now(),
        ]);

        $result = EkomCourt::byRemoteId(20)->first();
        $this->assertNotNull($result);
        $this->assertEquals('Sud A', $result->naziv);
    }

    public function test_remote_id_is_unique(): void
    {
        EkomCourt::create([
            'remote_id' => 30,
            'naziv' => 'Sud Original',
            'synced_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        EkomCourt::create([
            'remote_id' => 30,
            'naziv' => 'Sud Duplicate',
            'synced_at' => now(),
        ]);
    }
}
