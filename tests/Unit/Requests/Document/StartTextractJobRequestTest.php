<?php

namespace Tests\Unit\Requests\Document;

use App\Http\Requests\Document\StartTextractJobRequest;
use App\Models\LegalCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class StartTextractJobRequestTest extends TestCase
{
    use UsesTestDatabase;

    private string $testCaseId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test case
        $case = LegalCase::create([
            'title' => 'Test Case for Textract',
            'client_name' => 'John Doe',
            'status' => 'active',
        ]);
        $this->testCaseId = $case->id;
    }

    /** @test */
    public function it_passes_validation_with_drive_file_id()
    {
        $request = new StartTextractJobRequest;
        $validator = Validator::make([
            'drive_file_id' => '1abcdefghijklmnopqrstuvwxyz',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_with_uploaded_file()
    {
        $request = new StartTextractJobRequest;
        $file = UploadedFile::fake()->create('document.pdf', 5120);

        $validator = Validator::make([
            'file' => $file,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_with_all_optional_fields()
    {
        $request = new StartTextractJobRequest;
        $file = UploadedFile::fake()->create('document.pdf', 5120);

        $validator = Validator::make([
            'file' => $file,
            'case_id' => $this->testCaseId,
            'force_reprocess' => true,
            'queue_name' => 'high_priority',
            'priority' => 80,
            'batch_id' => 'batch-2025-01',
            'metadata' => [
                'ocr_mode' => 'analyze',
                'language' => 'hr',
            ],
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_both_drive_file_id_and_file_are_missing()
    {
        $request = new StartTextractJobRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertTrue(
            $validator->errors()->has('drive_file_id') || $validator->errors()->has('file')
        );
    }

    /** @test */
    public function it_passes_validation_when_only_drive_file_id_is_provided()
    {
        $request = new StartTextractJobRequest;
        $validator = Validator::make([
            'drive_file_id' => '1abcdefghijklmnopqrstuvwxyz',
            'case_id' => $this->testCaseId,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_queue_name_is_invalid()
    {
        $request = new StartTextractJobRequest;
        $file = UploadedFile::fake()->create('document.pdf', 5120);

        $validator = Validator::make([
            'file' => $file,
            'queue_name' => 'invalid_queue',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('queue_name', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_priority_exceeds_maximum()
    {
        $request = new StartTextractJobRequest;
        $file = UploadedFile::fake()->create('document.pdf', 5120);

        $validator = Validator::make([
            'file' => $file,
            'priority' => 150,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('priority', $validator->errors()->toArray());
    }

    /** @test */
    public function it_passes_validation_with_all_valid_queue_names()
    {
        $request = new StartTextractJobRequest;
        $validQueues = ['textract', 'high_priority', 'low_priority'];

        foreach ($validQueues as $queue) {
            $file = UploadedFile::fake()->create('document.pdf', 5120);
            $validator = Validator::make([
                'file' => $file,
                'queue_name' => $queue,
            ], $request->rules());

            $this->assertFalse($validator->fails(), "Queue name {$queue} should be valid");
        }
    }

    /** @test */
    public function it_passes_validation_with_valid_image_file()
    {
        $request = new StartTextractJobRequest;
        $file = UploadedFile::fake()->image('scan.jpg');

        $validator = Validator::make([
            'file' => $file,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }
}
