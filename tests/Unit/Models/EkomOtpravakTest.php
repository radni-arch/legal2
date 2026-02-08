<?php

namespace Tests\Unit\Models;

use App\Models\EkomOtpravak;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EkomOtpravakTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_has_correct_table_name()
    {
        $otpravak = new EkomOtpravak;
        $this->assertEquals('ekom_otpravci', $otpravak->getTable());
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $otpravak = EkomOtpravak::create([
            'remote_id' => 123456,
            'status' => 'poslan',
            'predmet_remote_id' => 1001,
            'vrijeme_slanja_sa_suda' => '2024-01-15 10:00:00',
            'vrijeme_potvrde_primitka' => '2024-01-15 11:00:00',
            'primljen_zbog_isteka_roka' => false,
            'data' => ['some' => 'data'],
            'last_synced_at' => now(),
        ]);

        $this->assertEquals(123456, $otpravak->remote_id);
        $this->assertEquals('poslan', $otpravak->status);
        $this->assertEquals(1001, $otpravak->predmet_remote_id);
        $this->assertFalse($otpravak->primljen_zbog_isteka_roka);
    }

    /** @test */
    public function it_casts_data_as_array()
    {
        $testData = [
            'dokument' => 'presuda.pdf',
            'napomena' => 'Dostava presude',
            'metadata' => ['tip' => 'presuda'],
        ];

        $otpravak = EkomOtpravak::create([
            'remote_id' => 123,
            'data' => $testData,
        ]);

        $this->assertIsArray($otpravak->data);
        $this->assertEquals($testData, $otpravak->data);
        $this->assertEquals('presuda.pdf', $otpravak->data['dokument']);
    }

    /** @test */
    public function it_casts_primljen_zbog_isteka_roka_as_boolean()
    {
        $expired = EkomOtpravak::create([
            'remote_id' => 1,
            'primljen_zbog_isteka_roka' => true,
        ]);

        $normal = EkomOtpravak::create([
            'remote_id' => 2,
            'primljen_zbog_isteka_roka' => false,
        ]);

        $this->assertIsBool($expired->primljen_zbog_isteka_roka);
        $this->assertTrue($expired->primljen_zbog_isteka_roka);
        $this->assertFalse($normal->primljen_zbog_isteka_roka);
    }

    /** @test */
    public function it_casts_last_synced_at_as_datetime()
    {
        $otpravak = EkomOtpravak::create([
            'remote_id' => 12300,
            'last_synced_at' => '2024-01-15 14:30:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $otpravak->last_synced_at);
        $this->assertEquals('2024-01-15 14:30:00', $otpravak->last_synced_at->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_stores_croatian_court_dispatch_data()
    {
        $otpravak = EkomOtpravak::create([
            'remote_id' => 2024001,
            'status' => 'dostavljen',
            'predmet_remote_id' => 1232024,
            'vrijeme_slanja_sa_suda' => '2024-01-15 09:00:00',
            'vrijeme_potvrde_primitka' => '2024-01-15 10:00:00',
            'primljen_zbog_isteka_roka' => false,
            'data' => [
                'tip_dostavnice' => 'sudska_odluka',
                'stranka' => 'Branitelj d.o.o.',
                'sadrzaj' => 'Dostava presude prvostupanjskog suda',
                'broj_predmeta' => 'K-123/2024', // Store the string ID in data
            ],
        ]);

        $this->assertEquals('dostavljen', $otpravak->status);
        $this->assertEquals(1232024, $otpravak->predmet_remote_id);
        $this->assertEquals('sudska_odluka', $otpravak->data['tip_dostavnice']);
        $this->assertEquals('K-123/2024', $otpravak->data['broj_predmeta']);
        $this->assertFalse($otpravak->primljen_zbog_isteka_roka);
    }

    /** @test */
    public function it_handles_null_optional_fields()
    {
        $otpravak = EkomOtpravak::create([
            'remote_id' => 12301,
            'status' => 'kreiran',
            'vrijeme_potvrde_primitka' => null,
            'data' => null,
            'last_synced_at' => null,
        ]);

        $this->assertNull($otpravak->vrijeme_potvrde_primitka);
        $this->assertNull($otpravak->data);
        $this->assertNull($otpravak->last_synced_at);
    }

    /** @test */
    public function it_can_update_sync_timestamp()
    {
        $otpravak = EkomOtpravak::create([
            'remote_id' => 12302,
            'status' => 'poslan',
        ]);

        $this->assertNull($otpravak->last_synced_at);

        $syncTime = now();
        $otpravak->update(['last_synced_at' => $syncTime]);

        $this->assertNotNull($otpravak->fresh()->last_synced_at);
        $this->assertEquals(
            $syncTime->format('Y-m-d H:i:s'),
            $otpravak->fresh()->last_synced_at->format('Y-m-d H:i:s')
        );
    }

    /** @test */
    public function it_tracks_dispatch_delivery_workflow()
    {
        $slanje = '2024-01-15 08:00:00';
        $primitak = '2024-01-15 09:30:00';

        $otpravak = EkomOtpravak::create([
            'remote_id' => 12303,
            'status' => 'dostavljen',
            'vrijeme_slanja_sa_suda' => $slanje,
            'vrijeme_potvrde_primitka' => $primitak,
            'primljen_zbog_isteka_roka' => false,
        ]);

        $this->assertEquals($slanje, $otpravak->vrijeme_slanja_sa_suda);
        $this->assertEquals($primitak, $otpravak->vrijeme_potvrde_primitka);
        $this->assertEquals('dostavljen', $otpravak->status);
    }

    /** @test */
    public function it_marks_documents_received_due_to_deadline_expiration()
    {
        $otpravak = EkomOtpravak::create([
            'remote_id' => 999,
            'status' => 'istekao_rok',
            'vrijeme_slanja_sa_suda' => '2024-01-01 10:00:00',
            'primljen_zbog_isteka_roka' => true,
            'data' => [
                'razlog' => 'Stranka nije potvrdila primitak u roku od 15 dana',
            ],
        ]);

        $this->assertTrue($otpravak->primljen_zbog_isteka_roka);
        $this->assertEquals('istekao_rok', $otpravak->status);
    }
}
