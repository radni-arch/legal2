<?php

namespace Tests\Unit\Repositories;

use App\Models\EoglasnaNotice;
use App\Repositories\EoglasnaNoticeRepository;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EoglasnaNoticeRepositoryTest extends TestCase
{
    use UsesTestDatabase;

    private EoglasnaNoticeRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EoglasnaNoticeRepository;
    }

    /** @test */
    public function it_creates_new_notice_from_api_payload()
    {
        $payload = [
            'uuid' => 'notice-uuid-123',
            'publicUrl' => 'https://eoglasna.hr/notice/123',
            'noticeDocumentsDownloadUrl' => 'https://eoglasna.hr/download/123',
            'noticeType' => 'court_notice',
            'title' => 'Rješenje o otvaranju stečajnog postupka',
            'expirationDate' => '2024-12-31',
            'datePublished' => '2024-01-15 10:00:00',
            'noticeSourceType' => 'sud',
        ];

        $result = $this->repository->upsertFromApiPayload($payload);

        $this->assertInstanceOf(EoglasnaNotice::class, $result);
        $this->assertEquals('notice-uuid-123', $result->uuid);
        $this->assertEquals('https://eoglasna.hr/notice/123', $result->public_url);
        $this->assertEquals('court_notice', $result->notice_type);
        $this->assertEquals('Rješenje o otvaranju stečajnog postupka', $result->title);
    }

    /** @test */
    public function it_updates_existing_notice_by_uuid()
    {
        $existing = EoglasnaNotice::create([
            'uuid' => 'notice-123',
            'title' => 'Old Title',
        ]);

        $payload = [
            'uuid' => 'notice-123',
            'title' => 'New Title',
            'noticeType' => 'updated_type',
        ];

        $result = $this->repository->upsertFromApiPayload($payload);

        $this->assertEquals($existing->id, $result->id);
        $this->assertEquals('New Title', $result->title);
        $this->assertEquals('updated_type', $result->notice_type);
        $this->assertEquals(1, EoglasnaNotice::count());
    }

    /** @test */
    public function it_parses_expiration_date_as_date_string()
    {
        $payload = [
            'uuid' => 'notice-123',
            'expirationDate' => '2024-12-31',
        ];

        $result = $this->repository->upsertFromApiPayload($payload);

        $this->assertEquals('2024-12-31', $result->expiration_date->format('Y-m-d'));
    }

    /** @test */
    public function it_parses_date_published_as_datetime()
    {
        $payload = [
            'uuid' => 'notice-123',
            'datePublished' => '2024-01-15T10:30:00',
        ];

        $result = $this->repository->upsertFromApiPayload($payload);

        $this->assertInstanceOf(Carbon::class, $result->date_published);
        $this->assertEquals('2024-01-15 10:30:00', $result->date_published->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_handles_null_expiration_date()
    {
        $payload = [
            'uuid' => 'notice-123',
            'expirationDate' => null,
        ];

        $result = $this->repository->upsertFromApiPayload($payload);

        $this->assertNull($result->expiration_date);
    }

    /** @test */
    public function it_handles_null_date_published()
    {
        $payload = [
            'uuid' => 'notice-123',
            'datePublished' => null,
        ];

        $result = $this->repository->upsertFromApiPayload($payload);

        $this->assertNull($result->date_published);
    }

    /** @test */
    public function it_stores_participants_array()
    {
        $participants = [
            ['name' => 'Ivan Horvat', 'role' => 'tužitelj'],
            ['name' => 'Marko Marić', 'role' => 'tuženik'],
        ];

        $payload = [
            'uuid' => 'notice-123',
            'participants' => $participants,
        ];

        $result = $this->repository->upsertFromApiPayload($payload);

        $this->assertEquals($participants, $result->participants);
        $this->assertCount(2, $result->participants);
    }

    /** @test */
    public function it_stores_notice_documents_array()
    {
        $documents = [
            ['filename' => 'doc1.pdf', 'size' => 1024],
            ['filename' => 'doc2.pdf', 'size' => 2048],
        ];

        $payload = [
            'uuid' => 'notice-123',
            'noticeDocuments' => $documents,
        ];

        $result = $this->repository->upsertFromApiPayload($payload);

        $this->assertEquals($documents, $result->notice_documents);
        $this->assertCount(2, $result->notice_documents);
    }

    /** @test */
    public function it_extracts_court_fields_from_nested_court_object()
    {
        $payload = [
            'uuid' => 'notice-123',
            'court' => [
                'code' => 'ZSZG',
                'name' => 'Županijski sud u Zagrebu',
                'courtType' => 'zupanijski',
            ],
            'caseNumber' => 'K-123/2024',
            'caseType' => 'gradanski',
        ];

        $result = $this->repository->upsertFromApiPayload($payload);

        $this->assertEquals('ZSZG', $result->court_code);
        $this->assertEquals('Županijski sud u Zagrebu', $result->court_name);
        $this->assertEquals('zupanijski', $result->court_type);
        $this->assertEquals('K-123/2024', $result->case_number);
        $this->assertEquals('gradanski', $result->case_type);
    }

    /** @test */
    public function it_extracts_institution_fields_from_nested_institution_object()
    {
        $payload = [
            'uuid' => 'notice-123',
            'institution' => [
                'name' => 'Grad Zagreb',
                'type' => 'local_government',
            ],
            'institutionNoticeType' => 'javna_nabava',
        ];

        $result = $this->repository->upsertFromApiPayload($payload);

        $this->assertEquals('Grad Zagreb', $result->institution_name);
        $this->assertEquals('javna_nabava', $result->institution_notice_type);
    }

    /** @test */
    public function it_stores_court_notice_details()
    {
        $details = [
            'sudac' => 'Dr. Ana Kovač',
            'vijece' => 'Građansko vijeće',
        ];

        $payload = [
            'uuid' => 'notice-123',
            'courtNoticeDetails' => $details,
        ];

        $result = $this->repository->upsertFromApiPayload($payload);

        $this->assertEquals($details, $result->court_notice_details);
    }

    /** @test */
    public function it_stores_raw_payload()
    {
        $payload = [
            'uuid' => 'notice-123',
            'title' => 'Test Notice',
            'customField' => 'custom value',
            'nested' => [
                'data' => 'value',
            ],
        ];

        $result = $this->repository->upsertFromApiPayload($payload);

        $this->assertEquals($payload, $result->raw);
        $this->assertEquals('custom value', $result->raw['customField']);
    }

    /** @test */
    public function it_sets_first_seen_at_for_new_notice()
    {
        Carbon::setTestNow('2024-01-20 10:00:00');

        $payload = ['uuid' => 'notice-123'];

        $result = $this->repository->upsertFromApiPayload($payload);

        $this->assertEquals('2024-01-20 10:00:00', $result->first_seen_at->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_does_not_update_first_seen_at_for_existing_notice()
    {
        Carbon::setTestNow('2024-01-15 10:00:00');
        $existing = EoglasnaNotice::create([
            'uuid' => 'notice-123',
            'first_seen_at' => '2024-01-10 08:00:00',
        ]);

        Carbon::setTestNow('2024-01-20 15:00:00');
        $payload = ['uuid' => 'notice-123'];

        $result = $this->repository->upsertFromApiPayload($payload);

        $this->assertEquals('2024-01-10 08:00:00', $result->first_seen_at->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_updates_last_seen_at_on_every_upsert()
    {
        Carbon::setTestNow('2024-01-15 10:00:00');
        $this->repository->upsertFromApiPayload(['uuid' => 'notice-123']);

        Carbon::setTestNow('2024-01-20 15:00:00');
        $result = $this->repository->upsertFromApiPayload(['uuid' => 'notice-123']);

        $this->assertEquals('2024-01-20 15:00:00', $result->last_seen_at->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_handles_complete_court_notice()
    {
        $payload = [
            'uuid' => 'court-notice-001',
            'publicUrl' => 'https://eoglasna.hr/court/001',
            'noticeType' => 'sudska_obavijest',
            'title' => 'Poziv na ročište',
            'datePublished' => '2024-01-15 10:00:00',
            'expirationDate' => '2024-06-30',
            'noticeSourceType' => 'sud',
            'court' => [
                'code' => 'ZSOS',
                'name' => 'Županijski sud u Osijeku',
                'courtType' => 'zupanijski',
            ],
            'caseNumber' => 'Gž-456/2024',
            'caseType' => 'gradanski',
            'participants' => [
                ['name' => 'Ivan Horvat', 'role' => 'stranka'],
            ],
            'courtNoticeDetails' => [
                'sudac' => 'Mr.sc. Marko Jurić',
            ],
        ];

        $result = $this->repository->upsertFromApiPayload($payload);

        $this->assertEquals('court-notice-001', $result->uuid);
        $this->assertEquals('Županijski sud u Osijeku', $result->court_name);
        $this->assertEquals('ZSOS', $result->court_code);
        $this->assertEquals('Gž-456/2024', $result->case_number);
        $this->assertCount(1, $result->participants);
    }

    /** @test */
    public function it_handles_complete_institution_notice()
    {
        $payload = [
            'uuid' => 'inst-notice-001',
            'publicUrl' => 'https://eoglasna.hr/inst/001',
            'noticeType' => 'institucijska_obavijest',
            'title' => 'Javna nabava',
            'datePublished' => '2024-01-15 10:00:00',
            'noticeSourceType' => 'institucija',
            'institution' => [
                'name' => 'Grad Zagreb',
            ],
            'institutionNoticeType' => 'javna_nabava',
        ];

        $result = $this->repository->upsertFromApiPayload($payload);

        $this->assertEquals('inst-notice-001', $result->uuid);
        $this->assertEquals('Grad Zagreb', $result->institution_name);
        $this->assertEquals('javna_nabava', $result->institution_notice_type);
        $this->assertEquals('institucija', $result->notice_source_type);
    }

    /** @test */
    public function it_handles_empty_participants_array()
    {
        $payload = [
            'uuid' => 'notice-123',
            'participants' => [],
        ];

        $result = $this->repository->upsertFromApiPayload($payload);

        $this->assertEquals([], $result->participants);
    }

    /** @test */
    public function it_handles_missing_participants_field()
    {
        $payload = [
            'uuid' => 'notice-123',
            'title' => 'Test',
        ];

        $result = $this->repository->upsertFromApiPayload($payload);

        $this->assertEquals([], $result->participants);
    }

    /** @test */
    public function it_handles_missing_notice_documents_field()
    {
        $payload = [
            'uuid' => 'notice-123',
            'title' => 'Test',
        ];

        $result = $this->repository->upsertFromApiPayload($payload);

        $this->assertEquals([], $result->notice_documents);
    }

    /** @test */
    public function it_handles_missing_court_object()
    {
        $payload = [
            'uuid' => 'notice-123',
            'title' => 'Test',
        ];

        $result = $this->repository->upsertFromApiPayload($payload);

        $this->assertNull($result->court_code);
        $this->assertNull($result->court_name);
        $this->assertNull($result->court_type);
    }

    /** @test */
    public function it_handles_missing_institution_object()
    {
        $payload = [
            'uuid' => 'notice-123',
            'title' => 'Test',
        ];

        $result = $this->repository->upsertFromApiPayload($payload);

        $this->assertNull($result->institution_name);
    }

    /** @test */
    public function it_persists_notice_to_database()
    {
        $payload = [
            'uuid' => 'notice-123',
            'title' => 'Test Notice',
        ];

        $this->repository->upsertFromApiPayload($payload);

        $fromDb = EoglasnaNotice::where('uuid', 'notice-123')->first();

        $this->assertNotNull($fromDb);
        $this->assertEquals('Test Notice', $fromDb->title);
    }

    /** @test */
    public function it_handles_multiple_upserts_for_same_uuid()
    {
        $payload1 = ['uuid' => 'notice-123', 'title' => 'Title 1'];
        $payload2 = ['uuid' => 'notice-123', 'title' => 'Title 2'];
        $payload3 = ['uuid' => 'notice-123', 'title' => 'Title 3'];

        $result1 = $this->repository->upsertFromApiPayload($payload1);
        $result2 = $this->repository->upsertFromApiPayload($payload2);
        $result3 = $this->repository->upsertFromApiPayload($payload3);

        $this->assertEquals($result1->id, $result2->id);
        $this->assertEquals($result2->id, $result3->id);
        $this->assertEquals('Title 3', $result3->title);
        $this->assertEquals(1, EoglasnaNotice::count());
    }

    /** @test */
    public function it_handles_different_uuids_as_separate_records()
    {
        $payload1 = ['uuid' => 'notice-1', 'title' => 'Title 1'];
        $payload2 = ['uuid' => 'notice-2', 'title' => 'Title 2'];
        $payload3 = ['uuid' => 'notice-3', 'title' => 'Title 3'];

        $this->repository->upsertFromApiPayload($payload1);
        $this->repository->upsertFromApiPayload($payload2);
        $this->repository->upsertFromApiPayload($payload3);

        $this->assertEquals(3, EoglasnaNotice::count());
    }
}
