<?php

namespace Tests\Unit\Models;

use App\Models\EoglasnaOsijekMonitoring;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EoglasnaOsijekMonitoringTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_has_correct_table_name()
    {
        $monitoring = new EoglasnaOsijekMonitoring;
        $this->assertEquals('eoglasna_osijek_monitoring', $monitoring->getTable());
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $monitoring = EoglasnaOsijekMonitoring::create([

            'name' => 'Ivan',
            'last_name' => 'Horvat',
            'oib' => '12345678901',
            'street' => 'Glavna ulica',
            'street_number' => 42,
            'city' => 'Osijek',
            'zip' => 31000,
            'public_url' => 'https://eoglasna.hr/osijek/123',
            'notice_documents_download_url' => 'https://eoglasna.hr/osijek/download/123',
            'notice_type' => 'court_notice',
            'title' => 'Stečajni postupak',
            'expiration_date' => '2024-12-31',
            'date_published' => '2024-01-15 10:00:00',
            'notice_source_type' => 'sud',
            'court_code' => 'ZSOS',
            'court_name' => 'Županijski sud u Osijeku',
            'court_type' => 'zupanijski',
            'case_number' => 'St-123/2024',
            'case_type' => 'stecaj',
            'institution_name' => null,
            'institution_notice_type' => null,
            'participants' => [['name' => 'ABC d.o.o.']],
            'notice_documents' => [['file' => 'doc.pdf']],
            'court_notice_details' => ['sudac' => 'Marko Marić'],
            'raw' => ['data' => 'original'],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->assertNotEmpty($monitoring->uuid);
        $this->assertIsString($monitoring->uuid);
        $this->assertEquals('Ivan', $monitoring->name);
        $this->assertEquals('Horvat', $monitoring->last_name);
        $this->assertEquals('12345678901', $monitoring->oib);
        $this->assertEquals('Osijek', $monitoring->city);
    }

    /** @test */
    public function it_casts_numeric_fields()
    {
        $monitoring = EoglasnaOsijekMonitoring::create([

            'street_number' => '42',
            'zip' => '31000',
        ]);

        $this->assertIsInt($monitoring->street_number);
        $this->assertIsInt($monitoring->zip);
        $this->assertEquals(42, $monitoring->street_number);
        $this->assertEquals(31000, $monitoring->zip);
    }

    /** @test */
    public function it_casts_array_fields()
    {
        $participants = [
            ['name' => 'Ivan Horvat', 'oib' => '11111111111'],
            ['name' => 'Marko Marić', 'oib' => '22222222222'],
        ];

        $documents = [
            ['filename' => 'doc1.pdf', 'size' => 1024],
            ['filename' => 'doc2.pdf', 'size' => 2048],
        ];

        $details = [
            'sudac' => 'Dr. Ana Kovač',
            'rociste' => '2024-02-15',
        ];

        $raw = [
            'source' => 'eoglasna',
            'metadata' => ['version' => '1.0'],
        ];

        $monitoring = EoglasnaOsijekMonitoring::create([

            'participants' => $participants,
            'notice_documents' => $documents,
            'court_notice_details' => $details,
            'raw' => $raw,
        ]);

        $this->assertIsArray($monitoring->participants);
        $this->assertIsArray($monitoring->notice_documents);
        $this->assertIsArray($monitoring->court_notice_details);
        $this->assertIsArray($monitoring->raw);

        $this->assertCount(2, $monitoring->participants);
        $this->assertEquals('Ivan Horvat', $monitoring->participants[0]['name']);
        $this->assertEquals('doc1.pdf', $monitoring->notice_documents[0]['filename']);
    }

    /** @test */
    public function it_casts_datetime_fields()
    {
        $monitoring = EoglasnaOsijekMonitoring::create([

            'date_published' => '2024-01-15 10:00:00',
            'expiration_date' => '2024-12-31',
            'first_seen_at' => '2024-01-10 08:00:00',
            'last_seen_at' => '2024-01-20 16:30:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $monitoring->date_published);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $monitoring->expiration_date);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $monitoring->first_seen_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $monitoring->last_seen_at);

        $this->assertEquals('2024-01-15 10:00:00', $monitoring->date_published->format('Y-m-d H:i:s'));
        $this->assertEquals('2024-12-31', $monitoring->expiration_date->format('Y-m-d'));
    }

    /** @test */
    public function it_stores_osijek_court_monitoring_data()
    {
        $monitoring = EoglasnaOsijekMonitoring::create([

            'name' => 'Marko',
            'last_name' => 'Jurić',
            'oib' => '98765432101',
            'street' => 'Trg Ante Starčevića',
            'street_number' => 1,
            'city' => 'Osijek',
            'zip' => 31000,
            'public_url' => 'https://eoglasna.hr/osijek/stecaj-001',
            'notice_type' => 'stecajni_postupak',
            'title' => 'Rješenje o otvaranju stečajnog postupka',
            'date_published' => now(),
            'court_code' => 'TSOS',
            'court_name' => 'Trgovački sud u Osijeku',
            'court_type' => 'trgovacki',
            'case_number' => 'St-456/2024',
            'case_type' => 'stecaj',
            'participants' => [
                [
                    'ime' => 'Marko Jurić',
                    'oib' => '98765432101',
                    'uloga' => 'dužnik',
                ],
            ],
        ]);

        $this->assertEquals('Osijek', $monitoring->city);
        $this->assertEquals(31000, $monitoring->zip);
        $this->assertEquals('TSOS', $monitoring->court_code);
        $this->assertEquals('Trgovački sud u Osijeku', $monitoring->court_name);
        $this->assertEquals('St-456/2024', $monitoring->case_number);
    }

    /** @test */
    public function it_stores_participant_address_data()
    {
        $monitoring = EoglasnaOsijekMonitoring::create([

            'name' => 'Ana',
            'last_name' => 'Kovač',
            'street' => 'Kapucinska ulica',
            'street_number' => 29,
            'city' => 'Osijek',
            'zip' => 31000,
        ]);

        $this->assertEquals('Kapucinska ulica', $monitoring->street);
        $this->assertEquals(29, $monitoring->street_number);
        $this->assertEquals('Osijek', $monitoring->city);
        $this->assertEquals(31000, $monitoring->zip);
    }

    /** @test */
    public function it_handles_null_optional_fields()
    {
        $monitoring = EoglasnaOsijekMonitoring::create([

            'title' => 'Minimal Notice',
            'name' => null,
            'last_name' => null,
            'oib' => null,
            'street' => null,
            'street_number' => null,
            'city' => null,
            'zip' => null,
            'participants' => null,
            'notice_documents' => null,
            'court_notice_details' => null,
            'raw' => null,
        ]);

        $this->assertNull($monitoring->name);
        $this->assertNull($monitoring->oib);
        $this->assertNull($monitoring->street_number);
        $this->assertNull($monitoring->zip);
        // participants has JsonUnescaped cast which converts null to empty array
        $this->assertEquals([], $monitoring->participants);
    }

    /** @test */
    public function it_updates_last_seen_timestamp()
    {
        $monitoring = EoglasnaOsijekMonitoring::create([

            'title' => 'Test',
            'first_seen_at' => now()->subDays(10),
            'last_seen_at' => now()->subDays(10),
        ]);

        $newTimestamp = now();
        $monitoring->update(['last_seen_at' => $newTimestamp]);

        $this->assertEquals(
            $newTimestamp->format('Y-m-d H:i:s'),
            $monitoring->fresh()->last_seen_at->format('Y-m-d H:i:s')
        );
    }

    /** @test */
    public function it_stores_multiple_participants_with_addresses()
    {
        $monitoring = EoglasnaOsijekMonitoring::create([

            'title' => 'Multi-party case',
            'participants' => [
                [
                    'ime' => 'Ivan Horvat',
                    'oib' => '11111111111',
                    'adresa' => 'Ulica 1, Osijek',
                ],
                [
                    'ime' => 'Marko Marić',
                    'oib' => '22222222222',
                    'adresa' => 'Ulica 2, Osijek',
                ],
                [
                    'tvrtka' => 'ABC d.o.o.',
                    'oib' => '33333333333',
                    'adresa' => 'Poslovni park, Osijek',
                ],
            ],
        ]);

        $this->assertCount(3, $monitoring->participants);
        $this->assertEquals('Ivan Horvat', $monitoring->participants[0]['ime']);
        $this->assertEquals('ABC d.o.o.', $monitoring->participants[2]['tvrtka']);
    }

    /** @test */
    public function it_can_query_by_oib()
    {
        EoglasnaOsijekMonitoring::create([

            'name' => 'Ivan',
            'last_name' => 'Horvat',
            'oib' => '12345678901',
        ]);

        EoglasnaOsijekMonitoring::create([

            'name' => 'Marko',
            'last_name' => 'Marić',
            'oib' => '98765432101',
        ]);

        $result = EoglasnaOsijekMonitoring::where('oib', '12345678901')->first();

        $this->assertNotNull($result);
        $this->assertEquals('Ivan', $result->name);
        $this->assertEquals('Horvat', $result->last_name);
    }

    /** @test */
    public function it_can_query_by_city()
    {
        EoglasnaOsijekMonitoring::create(['city' => 'Osijek']);
        EoglasnaOsijekMonitoring::create(['city' => 'Osijek']);
        EoglasnaOsijekMonitoring::create(['city' => 'Zagreb']);

        $osijekRecords = EoglasnaOsijekMonitoring::where('city', 'Osijek')->get();

        $this->assertCount(2, $osijekRecords);
    }

    /** @test */
    public function it_can_query_by_court_code()
    {
        EoglasnaOsijekMonitoring::create([

            'court_code' => 'TSOS',
            'title' => 'Case 1',
        ]);

        EoglasnaOsijekMonitoring::create([

            'court_code' => 'TSOS',
            'title' => 'Case 2',
        ]);

        EoglasnaOsijekMonitoring::create([

            'court_code' => 'ZSOS',
            'title' => 'Case 3',
        ]);

        $tsosRecords = EoglasnaOsijekMonitoring::where('court_code', 'TSOS')->get();

        $this->assertCount(2, $tsosRecords);
    }

    /** @test */
    public function it_stores_institution_notices()
    {
        $monitoring = EoglasnaOsijekMonitoring::create([

            'title' => 'Javna nabava',
            'notice_source_type' => 'institucija',
            'institution_name' => 'Grad Osijek',
            'institution_notice_type' => 'javna_nabava',
            'court_code' => null,
            'court_name' => null,
            'court_type' => null,
        ]);

        $this->assertEquals('institucija', $monitoring->notice_source_type);
        $this->assertEquals('Grad Osijek', $monitoring->institution_name);
        $this->assertEquals('javna_nabava', $monitoring->institution_notice_type);
        $this->assertNull($monitoring->court_code);
    }

    /** @test */
    public function it_stores_complete_osijek_bankruptcy_case()
    {
        $monitoring = EoglasnaOsijekMonitoring::create([

            'name' => 'Pero',
            'last_name' => 'Perić',
            'oib' => '11223344556',
            'street' => 'Europska avenija',
            'street_number' => 24,
            'city' => 'Osijek',
            'zip' => 31000,
            'public_url' => 'https://eoglasna.hr/osijek/stecaj/001',
            'notice_documents_download_url' => 'https://eoglasna.hr/osijek/stecaj/001/download',
            'notice_type' => 'stečaj',
            'title' => 'Rješenje o otvaranju stečajnog postupka - Perić d.o.o.',
            'expiration_date' => '2024-06-30',
            'date_published' => '2024-01-15 09:00:00',
            'notice_source_type' => 'sud',
            'court_code' => 'TSOS',
            'court_name' => 'Trgovački sud u Osijeku',
            'court_type' => 'trgovacki',
            'case_number' => 'St-12/2024',
            'case_type' => 'stečajni_postupak',
            'participants' => [
                [
                    'naziv' => 'Perić d.o.o.',
                    'oib' => '11223344556',
                    'uloga' => 'stečajni_dužnik',
                    'adresa' => 'Europska avenija 24, 31000 Osijek',
                ],
            ],
            'notice_documents' => [
                ['naziv' => 'Rješenje o otvaranju stečaja.pdf', 'url' => 'https://...'],
                ['naziv' => 'Odluka o imenovanju upravitelja.pdf', 'url' => 'https://...'],
            ],
            'court_notice_details' => [
                'sudac' => 'Mr.sc. Ana Jurić',
                'stecajni_upravitelj' => 'Ivan Kovač',
                'datum_rocista_vjerovnika' => '2024-03-15',
            ],
            'raw' => [
                'izvorni_podaci' => 'original court data',
            ],
            'first_seen_at' => '2024-01-15 09:00:00',
            'last_seen_at' => '2024-01-15 09:00:00',
        ]);

        $this->assertEquals('Pero', $monitoring->name);
        $this->assertEquals('Perić', $monitoring->last_name);
        $this->assertEquals('Osijek', $monitoring->city);
        $this->assertEquals(31000, $monitoring->zip);
        $this->assertEquals('St-12/2024', $monitoring->case_number);
        $this->assertEquals('stečajni_postupak', $monitoring->case_type);
        $this->assertCount(1, $monitoring->participants);
        $this->assertCount(2, $monitoring->notice_documents);
        $this->assertEquals('Mr.sc. Ana Jurić', $monitoring->court_notice_details['sudac']);
    }
}
