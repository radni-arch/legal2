<?php

namespace Tests\Unit\Models;

use App\Models\EkomPredmet;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EkomPredmetTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_has_correct_table_name()
    {
        $predmet = new EkomPredmet;
        $this->assertEquals('ekom_predmeti', $predmet->getTable());
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $predmet = EkomPredmet::create([
            'remote_id' => 'RPRED123456',
            'oznaka' => 'K-123/2024',
            'status' => 'aktivan',
            'sud_remote_id' => 'SUD001',
            'do_not_disturb' => false,
            'data' => ['some' => 'data'],
            'last_synced_at' => now(),
        ]);

        $this->assertEquals('RPRED123456', $predmet->remote_id);
        $this->assertEquals('K-123/2024', $predmet->oznaka);
        $this->assertEquals('aktivan', $predmet->status);
        $this->assertEquals('SUD001', $predmet->sud_remote_id);
        $this->assertFalse($predmet->do_not_disturb);
    }

    /** @test */
    public function it_casts_data_as_array()
    {
        $testData = [
            'stranka_tuzitelj' => 'ABC d.o.o.',
            'stranka_tuzenik' => 'XYZ d.o.o.',
            'vrijednost_spora' => 100000.00,
            'metadata' => ['tip' => 'gradanski'],
        ];

        $predmet = EkomPredmet::create([
            'remote_id' => 'RPRED123',
            'oznaka' => 'P-456/2024',
            'data' => $testData,
        ]);

        $this->assertIsArray($predmet->data);
        $this->assertEquals($testData, $predmet->data);
        $this->assertEquals('ABC d.o.o.', $predmet->data['stranka_tuzitelj']);
        $this->assertEquals(100000.00, $predmet->data['vrijednost_spora']);
    }

    /** @test */
    public function it_casts_do_not_disturb_as_boolean()
    {
        $disturbed = EkomPredmet::create([
            'remote_id' => 'RPRED001',
            'oznaka' => 'K-100/2024',
            'do_not_disturb' => false,
        ]);

        $notDisturbed = EkomPredmet::create([
            'remote_id' => 'RPRED002',
            'oznaka' => 'K-101/2024',
            'do_not_disturb' => true,
        ]);

        $this->assertIsBool($disturbed->do_not_disturb);
        $this->assertFalse($disturbed->do_not_disturb);
        $this->assertTrue($notDisturbed->do_not_disturb);
    }

    /** @test */
    public function it_casts_last_synced_at_as_datetime()
    {
        $predmet = EkomPredmet::create([
            'remote_id' => 'RPRED123',
            'oznaka' => 'K-123/2024',
            'last_synced_at' => '2024-01-15 14:30:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $predmet->last_synced_at);
        $this->assertEquals('2024-01-15 14:30:00', $predmet->last_synced_at->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_stores_croatian_court_case_data()
    {
        $predmet = EkomPredmet::create([
            'remote_id' => 'RPRED2024001',
            'oznaka' => 'K-1234/2024',
            'status' => 'u_tijeku',
            'sud_remote_id' => 'ZSOSIJEK',
            'do_not_disturb' => false,
            'data' => [
                'naziv_predmeta' => 'Naknada štete',
                'sudac' => 'Dr. Ivan Horvat',
                'datum_otvaranja' => '2024-01-15',
                'stranke' => [
                    'tuzitelj' => 'Tužitelj d.o.o.',
                    'tuzenik' => 'Tuženik d.o.o.',
                ],
            ],
        ]);

        $this->assertEquals('K-1234/2024', $predmet->oznaka);
        $this->assertEquals('u_tijeku', $predmet->status);
        $this->assertEquals('ZSOSIJEK', $predmet->sud_remote_id);
        $this->assertEquals('Naknada štete', $predmet->data['naziv_predmeta']);
    }

    /** @test */
    public function it_handles_null_optional_fields()
    {
        $predmet = EkomPredmet::create([
            'remote_id' => 'RPRED123',
            'oznaka' => 'K-123/2024',
            'status' => null,
            'sud_remote_id' => null,
            'data' => null,
            'last_synced_at' => null,
        ]);

        $this->assertNull($predmet->status);
        $this->assertNull($predmet->sud_remote_id);
        $this->assertNull($predmet->data);
        $this->assertNull($predmet->last_synced_at);
    }

    /** @test */
    public function it_can_update_sync_timestamp()
    {
        $predmet = EkomPredmet::create([
            'remote_id' => 'RPRED123',
            'oznaka' => 'K-123/2024',
        ]);

        $this->assertNull($predmet->last_synced_at);

        $syncTime = now();
        $predmet->update(['last_synced_at' => $syncTime]);

        $this->assertNotNull($predmet->fresh()->last_synced_at);
        $this->assertEquals(
            $syncTime->format('Y-m-d H:i:s'),
            $predmet->fresh()->last_synced_at->format('Y-m-d H:i:s')
        );
    }

    /** @test */
    public function it_can_mark_case_as_do_not_disturb()
    {
        $predmet = EkomPredmet::create([
            'remote_id' => 'RPRED123',
            'oznaka' => 'K-999/2024',
            'status' => 'aktivan',
            'do_not_disturb' => false,
        ]);

        $this->assertFalse($predmet->do_not_disturb);

        $predmet->update(['do_not_disturb' => true]);

        $this->assertTrue($predmet->fresh()->do_not_disturb);
    }

    /** @test */
    public function it_stores_case_number_with_croatian_format()
    {
        $predmet = EkomPredmet::create([
            'remote_id' => 'RPRED001',
            'oznaka' => 'K-1234/2024-56',
        ]);

        $this->assertEquals('K-1234/2024-56', $predmet->oznaka);
    }

    /** @test */
    public function it_can_update_case_status()
    {
        $predmet = EkomPredmet::create([
            'remote_id' => 'RPRED123',
            'oznaka' => 'K-123/2024',
            'status' => 'otvoren',
        ]);

        $this->assertEquals('otvoren', $predmet->status);

        $predmet->update(['status' => 'zatvoren']);

        $this->assertEquals('zatvoren', $predmet->fresh()->status);
    }

    /** @test */
    public function test_has_statuses_constant(): void
    {
        $this->assertIsArray(EkomPredmet::STATUSES);
        $this->assertArrayHasKey('otvoren', EkomPredmet::STATUSES);
        $this->assertArrayHasKey('aktivan', EkomPredmet::STATUSES);
        $this->assertArrayHasKey('u_tijeku', EkomPredmet::STATUSES);
        $this->assertArrayHasKey('zatvoren', EkomPredmet::STATUSES);
    }
}
