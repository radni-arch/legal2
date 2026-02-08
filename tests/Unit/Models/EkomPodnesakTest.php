<?php

namespace Tests\Unit\Models;

use App\Models\EkomPodnesak;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EkomPodnesakTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_has_correct_table_name()
    {
        $podnesak = new EkomPodnesak;
        $this->assertEquals('ekom_podnesci', $podnesak->getTable());
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $podnesak = EkomPodnesak::create([
            'remote_id' => 'RP123456',
            'status' => 'zaprimljen',
            'sud_remote_id' => 'SUD001',
            'vrsta_podneska_remote_id' => 'VP01',
            'vrijeme_kreiranja' => '2024-01-15 10:00:00',
            'vrijeme_slanja' => '2024-01-15 10:30:00',
            'vrijeme_zaprimanja' => '2024-01-15 11:00:00',
            'data' => ['some' => 'data'],
            'last_synced_at' => now(),
        ]);

        $this->assertEquals('RP123456', $podnesak->remote_id);
        $this->assertEquals('zaprimljen', $podnesak->status);
        $this->assertEquals('SUD001', $podnesak->sud_remote_id);
        $this->assertEquals('VP01', $podnesak->vrsta_podneska_remote_id);
    }

    /** @test */
    public function it_casts_data_as_array()
    {
        $testData = [
            'privitak' => 'file.pdf',
            'napomena' => 'Test napomena',
            'metadata' => ['key' => 'value'],
        ];

        $podnesak = EkomPodnesak::create([
            'remote_id' => 'RP123',
            'data' => $testData,
        ]);

        $this->assertIsArray($podnesak->data);
        $this->assertEquals($testData, $podnesak->data);
        $this->assertEquals('file.pdf', $podnesak->data['privitak']);
    }

    /** @test */
    public function it_casts_last_synced_at_as_datetime()
    {
        $podnesak = EkomPodnesak::create([
            'remote_id' => 'RP123',
            'last_synced_at' => '2024-01-15 14:30:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $podnesak->last_synced_at);
        $this->assertEquals('2024-01-15 14:30:00', $podnesak->last_synced_at->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_stores_croatian_court_submission_data()
    {
        $podnesak = EkomPodnesak::create([
            'remote_id' => 'RP2024001',
            'status' => 'u_obradi',
            'sud_remote_id' => 'ZSOSIJEK',
            'vrsta_podneska_remote_id' => 'TUZBA',
            'vrijeme_kreiranja' => '2024-01-15 09:00:00',
            'vrijeme_slanja' => '2024-01-15 09:15:00',
            'vrijeme_zaprimanja' => '2024-01-15 09:20:00',
            'data' => [
                'stranka' => 'Tužitelj d.o.o.',
                'predmet' => 'Naknada štete',
                'iznos' => 50000.00,
            ],
        ]);

        $this->assertEquals('u_obradi', $podnesak->status);
        $this->assertEquals('ZSOSIJEK', $podnesak->sud_remote_id);
        $this->assertEquals('Naknada štete', $podnesak->data['predmet']);
    }

    /** @test */
    public function it_handles_null_optional_fields()
    {
        $podnesak = EkomPodnesak::create([
            'remote_id' => 'RP123',
            'status' => 'kreiran',
            'vrijeme_slanja' => null,
            'vrijeme_zaprimanja' => null,
            'data' => null,
            'last_synced_at' => null,
        ]);

        $this->assertNull($podnesak->vrijeme_slanja);
        $this->assertNull($podnesak->vrijeme_zaprimanja);
        $this->assertNull($podnesak->data);
        $this->assertNull($podnesak->last_synced_at);
    }

    /** @test */
    public function it_can_update_sync_timestamp()
    {
        $podnesak = EkomPodnesak::create([
            'remote_id' => 'RP123',
            'status' => 'zaprimljen',
        ]);

        $this->assertNull($podnesak->last_synced_at);

        $syncTime = now();
        $podnesak->update(['last_synced_at' => $syncTime]);

        $this->assertNotNull($podnesak->fresh()->last_synced_at);
        $this->assertEquals(
            $syncTime->format('Y-m-d H:i:s'),
            $podnesak->fresh()->last_synced_at->format('Y-m-d H:i:s')
        );
    }

    /** @test */
    public function it_stores_timestamps_for_submission_workflow()
    {
        $kreiranje = '2024-01-15 08:00:00';
        $slanje = '2024-01-15 08:30:00';
        $zaprimanje = '2024-01-15 09:00:00';

        $podnesak = EkomPodnesak::create([
            'remote_id' => 'RP123',
            'status' => 'zaprimljen',
            'vrijeme_kreiranja' => $kreiranje,
            'vrijeme_slanja' => $slanje,
            'vrijeme_zaprimanja' => $zaprimanje,
        ]);

        $this->assertEquals($kreiranje, $podnesak->vrijeme_kreiranja);
        $this->assertEquals($slanje, $podnesak->vrijeme_slanja);
        $this->assertEquals($zaprimanje, $podnesak->vrijeme_zaprimanja);
    }
}
