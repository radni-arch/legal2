<?php

namespace Tests\Unit\Models;

use App\Models\CaseDocumentUpload;
use App\Models\LegalCase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CaseDocumentUploadTest extends TestCase
{
    use UsesTestDatabase;

    private function createTestCase(): LegalCase
    {
        return LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'TEST-'.rand(1000, 9999),
            'title' => 'Test Case',
            'status' => 'active',
        ]);
    }

    /** @test */
    public function it_uses_string_primary_key()
    {
        $upload = new CaseDocumentUpload(['id' => 'test-upload-id']);

        $this->assertEquals('test-upload-id', $upload->id);
        $this->assertFalse($upload->incrementing);
        $this->assertEquals('string', $upload->getKeyType());
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $testCase = $this->createTestCase();
        $data = [
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'doc_id' => 'doc-123',
            'disk' => 'local',
            'local_path' => '/uploads/case-docs/file.pdf',
            'original_filename' => 'contract.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1048576,
            'sha256' => hash('sha256', 'content'),
            'source_url' => 'https://example.com/file.pdf',
            'uploaded_at' => now(),
            'status' => 'completed',
            'error' => null,
        ];

        $upload = CaseDocumentUpload::create($data);

        $this->assertEquals($data['doc_id'], $upload->doc_id);
        $this->assertEquals($data['disk'], $upload->disk);
        $this->assertEquals($data['local_path'], $upload->local_path);
        $this->assertEquals($data['original_filename'], $upload->original_filename);
        $this->assertEquals($data['mime_type'], $upload->mime_type);
        $this->assertEquals($data['file_size'], $upload->file_size);
        $this->assertEquals($data['sha256'], $upload->sha256);
    }

    /** @test */
    public function it_belongs_to_legal_case()
    {
        $case = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-2024-001',
            'title' => 'Test Case',
            'status' => 'active',
        ]);

        $upload = CaseDocumentUpload::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $case->id,
            'doc_id' => 'doc-'.rand(1000, 9999),
            'local_path' => '/tmp/file.pdf',
            'original_filename' => 'file.pdf',
        ]);

        $this->assertInstanceOf(LegalCase::class, $upload->case);
        $this->assertEquals($case->id, $upload->case->id);
    }

    /** @test */
    public function it_casts_uploaded_at_as_datetime()
    {
        $testCase = $this->createTestCase();
        $upload = CaseDocumentUpload::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'doc_id' => 'doc-'.rand(1000, 9999),
            'local_path' => '/tmp/test.pdf',
            'uploaded_at' => '2024-01-15 10:30:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $upload->uploaded_at);
        $this->assertEquals('2024-01-15', $upload->uploaded_at->toDateString());
    }

    /** @test */
    public function it_stores_file_metadata()
    {
        $testCase = $this->createTestCase();
        $upload = CaseDocumentUpload::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'doc_id' => 'doc-'.rand(1000, 9999),
            'local_path' => '/tmp/evidence.pdf',
            'original_filename' => 'evidence.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048576,
            'sha256' => hash('sha256', 'test content'),
        ]);

        $this->assertEquals('evidence.pdf', $upload->original_filename);
        $this->assertEquals('application/pdf', $upload->mime_type);
        $this->assertEquals(2048576, $upload->file_size);
        $this->assertEquals(64, strlen($upload->sha256)); // SHA-256 is 64 hex chars
    }

    /** @test */
    public function it_stores_upload_status()
    {
        $testCase = $this->createTestCase();

        $pending = CaseDocumentUpload::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'doc_id' => 'doc-'.rand(1000, 9999),
            'local_path' => '/tmp/pending.pdf',
            'status' => 'pending',
        ]);

        $completed = CaseDocumentUpload::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'doc_id' => 'doc-'.rand(1000, 9999),
            'local_path' => '/tmp/completed.pdf',
            'status' => 'completed',
        ]);

        $failed = CaseDocumentUpload::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'doc_id' => 'doc-'.rand(1000, 9999),
            'local_path' => '/tmp/failed.pdf',
            'status' => 'failed',
            'error' => 'Upload timeout',
        ]);

        $this->assertEquals('pending', $pending->status);
        $this->assertEquals('completed', $completed->status);
        $this->assertEquals('failed', $failed->status);
        $this->assertEquals('Upload timeout', $failed->error);
    }

    /** @test */
    public function it_uses_configurable_table_name()
    {
        config(['vizra-adk.tables.cases_documents_uploads' => 'custom_uploads']);

        $upload = new CaseDocumentUpload;

        $this->assertEquals('custom_uploads', $upload->getTable());
    }
}
