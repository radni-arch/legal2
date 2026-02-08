<?php

namespace Tests\Unit\Models;

use App\Models\EoglasnaNotice;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EoglasnaNoticeTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_has_correct_table_name()
    {
        $notice = new EoglasnaNotice;
        $this->assertEquals('eoglasna_notices', $notice->getTable());
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $notice = EoglasnaNotice::create([
            'uuid' => 'notice-uuid-123',
            'public_url' => 'https://eoglasna.hr/notice/123',
            'notice_documents_download_url' => 'https://eoglasna.hr/download/123',
            'notice_type' => 'court_notice',
            'title' => 'Rješenje o otvaranju stečajnog postupka',
            'expiration_date' => '2024-12-31',
            'date_published' => '2024-01-15 10:00:00',
            'notice_source_type' => 'sud',
            'court_code' => 'TS001',
            'court_name' => 'Trgovački sud u Zagrebu',
            'court_type' => 'trgovacki',
            'case_number' => 'St-123/2024',
            'case_type' => 'stecaj',
            'institution_name' => null,
            'institution_notice_type' => null,
            'participants' => [['name' => 'ABC d.o.o.', 'role' => 'dužnik']],
            'notice_documents' => [['filename' => 'rjesenje.pdf']],
            'court_notice_details' => ['sud' => 'TS Zagreb'],
            'raw' => ['original' => 'data'],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->assertEquals('notice-uuid-123', $notice->uuid);
        $this->assertEquals('Rješenje o otvaranju stečajnog postupka', $notice->title);
        $this->assertEquals('St-123/2024', $notice->case_number);
    }

    /** @test */
    public function it_casts_array_fields()
    {
        $participants = [
            ['name' => 'Ivan Horvat', 'role' => 'tužitelj'],
            ['name' => 'Marko Marić', 'role' => 'tuženik'],
        ];

        $documents = [
            ['filename' => 'tuzba.pdf', 'size' => 1024],
            ['filename' => 'odgovor.pdf', 'size' => 2048],
        ];

        $details = [
            'sudac' => 'Dr. Ana Kovač',
            'datum_rocista' => '2024-02-15',
        ];

        $raw = [
            'source_id' => 'SRC123',
            'metadata' => ['version' => '1.0'],
        ];

        $notice = EoglasnaNotice::create([
            'uuid' => 'test-uuid',
            'participants' => $participants,
            'notice_documents' => $documents,
            'court_notice_details' => $details,
            'raw' => $raw,
        ]);

        $this->assertIsArray($notice->participants);
        $this->assertIsArray($notice->notice_documents);
        $this->assertIsArray($notice->court_notice_details);
        $this->assertIsArray($notice->raw);

        $this->assertCount(2, $notice->participants);
        $this->assertEquals('Ivan Horvat', $notice->participants[0]['name']);
        $this->assertEquals('tuzba.pdf', $notice->notice_documents[0]['filename']);
        $this->assertEquals('Dr. Ana Kovač', $notice->court_notice_details['sudac']);
    }

    /** @test */
    public function it_casts_date_and_datetime_fields()
    {
        $notice = EoglasnaNotice::create([
            'uuid' => 'test-uuid',
            'expiration_date' => '2024-12-31',
            'date_published' => '2024-01-15 10:00:00',
            'first_seen_at' => '2024-01-10 08:00:00',
            'last_seen_at' => '2024-01-20 16:30:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $notice->expiration_date);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $notice->date_published);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $notice->first_seen_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $notice->last_seen_at);

        $this->assertEquals('2024-12-31', $notice->expiration_date->format('Y-m-d'));
        $this->assertEquals('2024-01-15 10:00:00', $notice->date_published->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_stores_court_notice_data()
    {
        $notice = EoglasnaNotice::create([
            'uuid' => 'court-notice-001',
            'public_url' => 'https://eoglasna.hr/court/001',
            'notice_type' => 'sudska_obavijest',
            'title' => 'Poziv na ročište',
            'date_published' => now(),
            'notice_source_type' => 'sud',
            'court_code' => 'ZSOS',
            'court_name' => 'Županijski sud u Osijeku',
            'court_type' => 'zupanijski',
            'case_number' => 'Gž-456/2024',
            'case_type' => 'gradanski',
            'participants' => [
                ['ime' => 'Ivan Horvat', 'uloga' => 'stranka'],
            ],
            'court_notice_details' => [
                'sudac' => 'Mr.sc. Marko Jurić',
                'vijece' => 'Građansko vijeće',
            ],
        ]);

        $this->assertEquals('court-notice-001', $notice->uuid);
        $this->assertEquals('Županijski sud u Osijeku', $notice->court_name);
        $this->assertEquals('ZSOS', $notice->court_code);
        $this->assertEquals('Gž-456/2024', $notice->case_number);
        $this->assertCount(1, $notice->participants);
    }

    /** @test */
    public function it_stores_institution_notice_data()
    {
        $notice = EoglasnaNotice::create([
            'uuid' => 'inst-notice-001',
            'public_url' => 'https://eoglasna.hr/inst/001',
            'notice_type' => 'institucijska_obavijest',
            'title' => 'Javna nabava - poziv na natječaj',
            'date_published' => now(),
            'notice_source_type' => 'institucija',
            'institution_name' => 'Grad Zagreb',
            'institution_notice_type' => 'javna_nabava',
            'court_code' => null,
            'court_name' => null,
            'participants' => [],
            'raw' => [
                'iznos' => 1000000,
                'rok_prijave' => '2024-02-28',
            ],
        ]);

        $this->assertEquals('inst-notice-001', $notice->uuid);
        $this->assertEquals('Grad Zagreb', $notice->institution_name);
        $this->assertEquals('javna_nabava', $notice->institution_notice_type);
        $this->assertEquals('institucija', $notice->notice_source_type);
        $this->assertNull($notice->court_code);
    }

    /** @test */
    public function it_handles_null_optional_fields()
    {
        $notice = EoglasnaNotice::create([
            'uuid' => 'minimal-notice',
            'title' => 'Minimal Notice',
            'notice_documents_download_url' => null,
            'expiration_date' => null,
            'case_number' => null,
            'participants' => null,
            'notice_documents' => null,
            'court_notice_details' => null,
            'raw' => null,
        ]);

        $this->assertNull($notice->notice_documents_download_url);
        $this->assertNull($notice->expiration_date);
        $this->assertNull($notice->case_number);
        $this->assertNull($notice->participants);
        $this->assertNull($notice->notice_documents);
        $this->assertNull($notice->court_notice_details);
        $this->assertNull($notice->raw);
    }

    /** @test */
    public function it_updates_last_seen_timestamp()
    {
        $notice = EoglasnaNotice::create([
            'uuid' => 'test-uuid',
            'title' => 'Test Notice',
            'first_seen_at' => now()->subDays(10),
            'last_seen_at' => now()->subDays(10),
        ]);

        $newTimestamp = now();
        $notice->update(['last_seen_at' => $newTimestamp]);

        $this->assertEquals(
            $newTimestamp->format('Y-m-d H:i:s'),
            $notice->fresh()->last_seen_at->format('Y-m-d H:i:s')
        );
    }

    /** @test */
    public function it_stores_multiple_participants()
    {
        $notice = EoglasnaNotice::create([
            'uuid' => 'multi-party-case',
            'title' => 'Višestranka parnica',
            'participants' => [
                ['ime' => 'ABC d.o.o.', 'uloga' => 'tužitelj', 'oib' => '12345678901'],
                ['ime' => 'XYZ d.o.o.', 'uloga' => 'tuženik', 'oib' => '98765432109'],
                ['ime' => 'DEF d.o.o.', 'uloga' => 'umješač', 'oib' => '11111111111'],
            ],
        ]);

        $this->assertCount(3, $notice->participants);
        $this->assertEquals('tužitelj', $notice->participants[0]['uloga']);
        $this->assertEquals('tuženik', $notice->participants[1]['uloga']);
        $this->assertEquals('umješač', $notice->participants[2]['uloga']);
    }

    /** @test */
    public function it_stores_multiple_documents()
    {
        $notice = EoglasnaNotice::create([
            'uuid' => 'multi-doc-notice',
            'title' => 'Notice with multiple documents',
            'notice_documents' => [
                ['naziv' => 'tuzba.pdf', 'velicina' => 1024, 'url' => 'https://example.com/1'],
                ['naziv' => 'odluka.pdf', 'velicina' => 2048, 'url' => 'https://example.com/2'],
                ['naziv' => 'privitak.pdf', 'velicina' => 512, 'url' => 'https://example.com/3'],
            ],
        ]);

        $this->assertCount(3, $notice->notice_documents);
        $this->assertEquals('tuzba.pdf', $notice->notice_documents[0]['naziv']);
        $this->assertEquals(2048, $notice->notice_documents[1]['velicina']);
    }
}
