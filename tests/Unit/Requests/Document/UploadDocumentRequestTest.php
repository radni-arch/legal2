<?php

namespace Tests\Unit\Requests\Document;

use App\Http\Requests\Document\UploadDocumentRequest;
use App\Models\LegalCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class UploadDocumentRequestTest extends TestCase
{
    use UsesTestDatabase;

    private string $testCaseId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test case
        $case = LegalCase::create([
            'title' => 'Test Case for Document Upload',
            'client_name' => 'John Doe',
            'status' => 'active',
        ]);
        $this->testCaseId = $case->id;
    }

    /** @test */
    public function it_passes_validation_with_valid_required_fields()
    {
        $request = new UploadDocumentRequest;
        $file = UploadedFile::fake()->create('document.pdf', 1024);

        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'file' => $file,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_with_all_optional_fields()
    {
        $request = new UploadDocumentRequest;
        $file = UploadedFile::fake()->create('evidence.pdf', 2048);

        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'file' => $file,
            'title' => 'Police Report - Evidence Collection',
            'category' => 'evidence',
            'author' => 'Officer John Smith',
            'document_date' => '2025-01-15',
            'tags' => ['police-report', 'evidence'],
            'language' => 'hr',
            'metadata' => ['source' => 'police_department'],
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_case_id_is_missing()
    {
        $request = new UploadDocumentRequest;
        $file = UploadedFile::fake()->create('document.pdf', 1024);

        $validator = Validator::make([
            'file' => $file,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('case_id', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_file_is_missing()
    {
        $request = new UploadDocumentRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('file', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_category_is_invalid()
    {
        $request = new UploadDocumentRequest;
        $file = UploadedFile::fake()->create('document.pdf', 1024);

        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'file' => $file,
            'category' => 'invalid_category',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('category', $validator->errors()->toArray());
    }

    /** @test */
    public function it_passes_validation_with_all_valid_category_values()
    {
        $request = new UploadDocumentRequest;
        $validCategories = ['evidence', 'pleading', 'motion', 'order', 'correspondence', 'discovery', 'other'];

        foreach ($validCategories as $category) {
            $file = UploadedFile::fake()->create('document.pdf', 1024);
            $validator = Validator::make([
                'case_id' => $this->testCaseId,
                'file' => $file,
                'category' => $category,
            ], $request->rules());

            $this->assertFalse($validator->fails(), "Category {$category} should be valid");
        }
    }

    /** @test */
    public function it_passes_validation_with_valid_file_types()
    {
        $request = new UploadDocumentRequest;
        $validFiles = [
            UploadedFile::fake()->create('document.pdf', 1024),
            UploadedFile::fake()->create('document.doc', 1024),
            UploadedFile::fake()->create('document.docx', 1024),
            UploadedFile::fake()->create('document.txt', 1024),
            UploadedFile::fake()->image('image.jpg'),
            UploadedFile::fake()->image('image.png'),
        ];

        foreach ($validFiles as $file) {
            $validator = Validator::make([
                'case_id' => $this->testCaseId,
                'file' => $file,
            ], $request->rules());

            $this->assertFalse($validator->fails(), "File type {$file->getClientOriginalExtension()} should be valid");
        }
    }

    /** @test */
    public function it_fails_validation_when_language_is_invalid()
    {
        $request = new UploadDocumentRequest;
        $file = UploadedFile::fake()->create('document.pdf', 1024);

        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'file' => $file,
            'language' => 'invalid',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('language', $validator->errors()->toArray());
    }
}
