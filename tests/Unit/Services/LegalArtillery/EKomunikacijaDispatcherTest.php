<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Services\EKomunikacija\Client as EKomClient;
use App\Services\LegalArtillery\EKomunikacijaDispatcher;
use Mockery;
use Tests\TestCase;

class EKomunikacijaDispatcherTest extends TestCase
{
    private EKomunikacijaDispatcher $dispatcher;
    private $ekomClient;
    private DocumentProfile $profile;
    private CaseContext $context;
    private string $docxPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ekomClient = Mockery::mock(EKomClient::class);
        $this->dispatcher = new EKomunikacijaDispatcher($this->ekomClient);

        $this->profile = DocumentProfile::fromConfig('predsjednik_suda');
        $this->context = CaseContext::fromConfig();

        // Create a temporary file for testing
        $this->docxPath = tempnam(sys_get_temp_dir(), 'test_ekom_') . '.docx';
        file_put_contents($this->docxPath, 'test docx content');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->docxPath)) {
            unlink($this->docxPath);
        }

        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------
    // preparePayload() tests
    // -------------------------------------------------------

    public function test_prepares_submission_payload(): void
    {
        $payload = $this->dispatcher->preparePayload($this->profile, $this->context, $this->docxPath);

        $this->assertArrayHasKey('case_number', $payload);
        $this->assertArrayHasKey('court_id', $payload);
        $this->assertArrayHasKey('document_type', $payload);
        $this->assertArrayHasKey('attachments', $payload);
    }

    public function test_payload_contains_correct_case_number(): void
    {
        $payload = $this->dispatcher->preparePayload($this->profile, $this->context, $this->docxPath);

        $this->assertEquals('Pp Prz-74/2025', $payload['case_number']);
    }

    public function test_payload_contains_sender_info(): void
    {
        $payload = $this->dispatcher->preparePayload($this->profile, $this->context, $this->docxPath);

        $this->assertArrayHasKey('sender', $payload);
        $this->assertArrayHasKey('name', $payload['sender']);
        $this->assertArrayHasKey('oib', $payload['sender']);
        $this->assertArrayHasKey('address', $payload['sender']);
        $this->assertArrayHasKey('email', $payload['sender']);
    }

    public function test_payload_contains_subject_and_description(): void
    {
        $payload = $this->dispatcher->preparePayload($this->profile, $this->context, $this->docxPath);

        $this->assertArrayHasKey('subject', $payload);
        $this->assertEquals($this->profile->name, $payload['subject']);
        $this->assertArrayHasKey('description', $payload);
        $this->assertStringContainsString('Pp Prz-74/2025', $payload['description']);
    }

    public function test_payload_contains_metadata(): void
    {
        $payload = $this->dispatcher->preparePayload($this->profile, $this->context, $this->docxPath);

        $this->assertArrayHasKey('metadata', $payload);
        $this->assertEquals('legal_artillery', $payload['metadata']['generated_by']);
        $this->assertEquals('predsjednik_suda', $payload['metadata']['profile_key']);
        $this->assertArrayHasKey('generated_at', $payload['metadata']);
    }

    public function test_includes_file_attachment_in_payload(): void
    {
        $payload = $this->dispatcher->preparePayload($this->profile, $this->context, $this->docxPath);

        $this->assertNotEmpty($payload['attachments']);
        $mainAttachment = $payload['attachments'][0];
        $this->assertEquals('main_document', $mainAttachment['type']);
        $this->assertStringEndsWith('.docx', $mainAttachment['filename']);
        $this->assertEquals(
            base64_encode('test docx content'),
            $mainAttachment['content']
        );
        $this->assertEquals(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $mainAttachment['mime_type']
        );
    }

    public function test_includes_additional_attachments_in_payload(): void
    {
        $extraPath = tempnam(sys_get_temp_dir(), 'test_att_') . '.pdf';
        file_put_contents($extraPath, 'extra pdf content');

        $payload = $this->dispatcher->preparePayload(
            $this->profile,
            $this->context,
            $this->docxPath,
            [['path' => $extraPath, 'filename' => 'evidence.pdf', 'mime_type' => 'application/pdf']]
        );

        $this->assertCount(2, $payload['attachments']);
        $this->assertEquals('attachment', $payload['attachments'][1]['type']);
        $this->assertEquals('evidence.pdf', $payload['attachments'][1]['filename']);

        unlink($extraPath);
    }

    public function test_skips_nonexistent_attachment_file(): void
    {
        $payload = $this->dispatcher->preparePayload(
            $this->profile,
            $this->context,
            $this->docxPath,
            [['path' => '/tmp/nonexistent_file_xyz.pdf']]
        );

        // Only main document, nonexistent extra attachment is skipped
        $this->assertCount(1, $payload['attachments']);
    }

    // -------------------------------------------------------
    // Document type mapping tests
    // -------------------------------------------------------

    public function test_maps_document_type_for_predsjednik_suda(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $payload = $this->dispatcher->preparePayload($profile, $this->context, $this->docxPath);

        $this->assertEquals('ZAHTJEV', $payload['document_type']);
    }

    public function test_maps_document_type_for_dorh_production(): void
    {
        $profile = DocumentProfile::fromConfig('dorh_production');
        $payload = $this->dispatcher->preparePayload($profile, $this->context, $this->docxPath);

        $this->assertEquals('ZAHTJEV_DORH', $payload['document_type']);
    }

    public function test_maps_document_type_for_kazneni_sud_motion(): void
    {
        $profile = DocumentProfile::fromConfig('kazneni_sud_motion');
        $payload = $this->dispatcher->preparePayload($profile, $this->context, $this->docxPath);

        $this->assertEquals('PRIJEDLOG', $payload['document_type']);
    }

    public function test_maps_document_type_for_ustavni_sud(): void
    {
        $profile = DocumentProfile::fromConfig('ustavni_sud');
        $payload = $this->dispatcher->preparePayload($profile, $this->context, $this->docxPath);

        $this->assertEquals('USTAVNA_TUZBA', $payload['document_type']);
    }

    public function test_maps_document_type_for_ombudsman(): void
    {
        $profile = DocumentProfile::fromConfig('ombudsman');
        $payload = $this->dispatcher->preparePayload($profile, $this->context, $this->docxPath);

        $this->assertEquals('PRITUZBA', $payload['document_type']);
    }

    public function test_maps_document_type_for_echr_application(): void
    {
        $profile = DocumentProfile::fromConfig('echr_application');
        $payload = $this->dispatcher->preparePayload($profile, $this->context, $this->docxPath);

        $this->assertEquals('ECHR_APPLICATION', $payload['document_type']);
    }

    // -------------------------------------------------------
    // Court ID resolution tests
    // -------------------------------------------------------

    public function test_resolves_court_id_for_known_institution(): void
    {
        // predsjednik_suda has institution = 'Opcinski sud u Osijeku'
        $payload = $this->dispatcher->preparePayload($this->profile, $this->context, $this->docxPath);

        $this->assertEquals('OS_OSIJEK', $payload['court_id']);
    }

    public function test_resolves_court_id_as_unknown_for_unmapped_institution(): void
    {
        // ombudsman has institution = 'Ured Puckog pravobranitelja' which is not a court
        $profile = DocumentProfile::fromConfig('ombudsman');
        $payload = $this->dispatcher->preparePayload($profile, $this->context, $this->docxPath);

        $this->assertEquals('UNKNOWN', $payload['court_id']);
    }

    // -------------------------------------------------------
    // submit() tests
    // -------------------------------------------------------

    public function test_submits_to_e_komunikacija(): void
    {
        $this->ekomClient->shouldReceive('submitDocument')
            ->once()
            ->andReturn([
                'success' => true,
                'submission_id' => 'EKOM-12345',
                'timestamp' => '2026-02-05T10:00:00Z',
            ]);

        $result = $this->dispatcher->submit($this->profile, $this->context, $this->docxPath);

        $this->assertTrue($result['success']);
        $this->assertEquals('EKOM-12345', $result['submission_id']);
    }

    public function test_submit_passes_prepared_payload_to_client(): void
    {
        $this->ekomClient->shouldReceive('submitDocument')
            ->once()
            ->with(Mockery::on(function (array $payload) {
                return isset($payload['case_number'])
                    && isset($payload['court_id'])
                    && isset($payload['document_type'])
                    && isset($payload['attachments'])
                    && $payload['case_number'] === 'Pp Prz-74/2025';
            }))
            ->andReturn([
                'success' => true,
                'submission_id' => 'EKOM-99999',
                'timestamp' => '2026-02-05T10:00:00Z',
            ]);

        $result = $this->dispatcher->submit($this->profile, $this->context, $this->docxPath);

        $this->assertTrue($result['success']);
    }

    public function test_handles_submission_failure_gracefully(): void
    {
        $this->ekomClient->shouldReceive('submitDocument')
            ->once()
            ->andThrow(new \RuntimeException('Connection timeout'));

        $result = $this->dispatcher->submit($this->profile, $this->context, $this->docxPath);

        $this->assertFalse($result['success']);
        $this->assertEquals('Connection timeout', $result['error']);
    }

    // -------------------------------------------------------
    // checkStatus() and getHistory() delegation tests
    // -------------------------------------------------------

    public function test_check_status_delegates_to_client(): void
    {
        $this->ekomClient->shouldReceive('getSubmissionStatus')
            ->once()
            ->with('EKOM-12345')
            ->andReturn(['status' => 'delivered', 'submission_id' => 'EKOM-12345']);

        $result = $this->dispatcher->checkStatus('EKOM-12345');

        $this->assertEquals('delivered', $result['status']);
    }

    public function test_get_history_delegates_to_client(): void
    {
        $this->ekomClient->shouldReceive('getSubmissionHistory')
            ->once()
            ->with('Pp Prz-74/2025')
            ->andReturn([
                ['submission_id' => 'EKOM-001', 'status' => 'delivered'],
                ['submission_id' => 'EKOM-002', 'status' => 'pending'],
            ]);

        $result = $this->dispatcher->getHistory('Pp Prz-74/2025');

        $this->assertCount(2, $result);
    }
}
