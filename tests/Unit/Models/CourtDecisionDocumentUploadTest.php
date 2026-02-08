<?php

namespace Tests\Unit\Models;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocumentUpload;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CourtDecisionDocumentUploadTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_uses_string_primary_key()
    {
        $upload = new CourtDecisionDocumentUpload(['id' => 'test-id']);

        $this->assertFalse($upload->incrementing);
        $this->assertEquals('string', $upload->getKeyType());
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        // Create parent decision first
        $decision = CourtDecision::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'Upload-Fill-456/2024',
            'title' => 'Upload Fillable Test Decision',
        ]);

        $data = [
            'id' => Str::ulid()->toString(),
            'decision_id' => $decision->id,
            'doc_id' => 'doc-456',
            'disk' => 'local',
            'local_path' => '/uploads/decisions/file.pdf',
            'original_filename' => 'decision.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 512000,
            'sha256' => hash('sha256', 'content'),
            'source_url' => 'https://example.com/decision.pdf',
            'uploaded_at' => now(),
            'status' => 'completed',
        ];

        $upload = CourtDecisionDocumentUpload::create($data);

        $this->assertEquals($data['doc_id'], $upload->doc_id);
        $this->assertEquals($data['original_filename'], $upload->original_filename);
        $this->assertEquals($data['status'], $upload->status);
    }

    /** @test */
    public function it_belongs_to_court_decision()
    {
        $decision = CourtDecision::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'Rev-123/2024',
            'title' => 'Test Decision',
        ]);

        $upload = CourtDecisionDocumentUpload::create([
            'id' => Str::ulid()->toString(),
            'decision_id' => $decision->id,
            'doc_id' => 'doc-rel-test',
            'local_path' => '/uploads/test.pdf',
        ]);

        $this->assertInstanceOf(CourtDecision::class, $upload->decision);
        $this->assertEquals($decision->id, $upload->decision->id);
    }

    /** @test */
    public function it_casts_uploaded_at_as_datetime()
    {
        $decision = CourtDecision::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'Test-999/2024',
            'title' => 'Datetime Test Decision',
        ]);

        $upload = CourtDecisionDocumentUpload::create([
            'id' => Str::ulid()->toString(),
            'decision_id' => $decision->id,
            'doc_id' => 'doc-datetime-test',
            'local_path' => '/uploads/datetime-test.pdf',
            'uploaded_at' => '2024-10-15 14:30:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $upload->uploaded_at);
    }

    /** @test */
    public function it_uses_configurable_table_name()
    {
        config(['vizra-adk.tables.court_decision_document_uploads' => 'custom_decision_uploads']);

        $upload = new CourtDecisionDocumentUpload;

        $this->assertEquals('custom_decision_uploads', $upload->getTable());
    }
}
