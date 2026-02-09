<?php

namespace Tests\Unit\Models;

use App\Models\IngestRun;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TDD Tests for IngestRun model
 *
 * SOT-001: Verifies the IngestRun model has correct attributes,
 * casts, relationships, and auto-generated fields.
 */
class IngestRunTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_be_created_with_required_attributes(): void
    {
        $user = User::factory()->create();

        $ingestRun = IngestRun::create([
            'user_id' => $user->id,
            'source' => 'uploader',
            'original_filename' => 'test-document.pdf',
            'stored_path' => 'uploads/abc123-test-document.pdf',
            'stored_disk' => 'public',
            'status' => 'pending',
        ]);

        $this->assertNotNull($ingestRun->id);
        $this->assertEquals($user->id, $ingestRun->user_id);
        $this->assertEquals('uploader', $ingestRun->source);
        $this->assertEquals('test-document.pdf', $ingestRun->original_filename);
        $this->assertEquals('uploads/abc123-test-document.pdf', $ingestRun->stored_path);
        $this->assertEquals('public', $ingestRun->stored_disk);
        $this->assertEquals('pending', $ingestRun->status);
    }

    /** @test */
    public function it_auto_generates_ulid_as_primary_key(): void
    {
        $user = User::factory()->create();

        $ingestRun = IngestRun::create([
            'user_id' => $user->id,
            'source' => 'uploader',
            'original_filename' => 'test.pdf',
            'stored_path' => 'uploads/test.pdf',
            'stored_disk' => 'public',
            'status' => 'pending',
        ]);

        $this->assertNotNull($ingestRun->id);
        // ULID is 26 chars
        $this->assertEquals(26, strlen($ingestRun->id));
    }

    /** @test */
    public function it_auto_generates_correlation_id(): void
    {
        $user = User::factory()->create();

        $ingestRun = IngestRun::create([
            'user_id' => $user->id,
            'source' => 'uploader',
            'original_filename' => 'test.pdf',
            'stored_path' => 'uploads/test.pdf',
            'stored_disk' => 'public',
            'status' => 'pending',
        ]);

        $this->assertNotNull($ingestRun->correlation_id);
        // UUID format: 8-4-4-4-12 hex chars
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $ingestRun->correlation_id
        );
    }

    /** @test */
    public function it_has_nullable_case_id(): void
    {
        $user = User::factory()->create();

        $ingestRun = IngestRun::create([
            'user_id' => $user->id,
            'source' => 'uploader',
            'original_filename' => 'test.pdf',
            'stored_path' => 'uploads/test.pdf',
            'stored_disk' => 'public',
            'status' => 'pending',
            'case_id' => null,
        ]);

        $this->assertNull($ingestRun->case_id);
    }

    /** @test */
    public function it_belongs_to_a_user(): void
    {
        $user = User::factory()->create();

        $ingestRun = IngestRun::create([
            'user_id' => $user->id,
            'source' => 'uploader',
            'original_filename' => 'test.pdf',
            'stored_path' => 'uploads/test.pdf',
            'stored_disk' => 'public',
            'status' => 'pending',
        ]);

        $this->assertInstanceOf(User::class, $ingestRun->user);
        $this->assertEquals($user->id, $ingestRun->user->id);
    }

    /** @test */
    public function it_optionally_belongs_to_a_case(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::create([
            'title' => 'Test Case',
            'case_number' => 'TC-001',
            'status' => 'active',
            'user_id' => $user->id,
        ]);

        $ingestRun = IngestRun::create([
            'user_id' => $user->id,
            'case_id' => $case->id,
            'source' => 'uploader',
            'original_filename' => 'test.pdf',
            'stored_path' => 'uploads/test.pdf',
            'stored_disk' => 'public',
            'status' => 'pending',
        ]);

        $this->assertInstanceOf(LegalCase::class, $ingestRun->legalCase);
        $this->assertEquals($case->id, $ingestRun->legalCase->id);
    }

    /** @test */
    public function it_casts_datetime_fields(): void
    {
        $user = User::factory()->create();

        $ingestRun = IngestRun::create([
            'user_id' => $user->id,
            'source' => 'uploader',
            'original_filename' => 'test.pdf',
            'stored_path' => 'uploads/test.pdf',
            'stored_disk' => 'public',
            'status' => 'processing',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $ingestRun->refresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $ingestRun->started_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $ingestRun->completed_at);
    }

    /** @test */
    public function it_supports_all_valid_status_values(): void
    {
        $user = User::factory()->create();
        $validStatuses = ['pending', 'processing', 'ocr', 'embedding', 'analysis', 'completed', 'failed'];

        foreach ($validStatuses as $status) {
            $ingestRun = IngestRun::create([
                'user_id' => $user->id,
                'source' => 'uploader',
                'original_filename' => 'test.pdf',
                'stored_path' => 'uploads/test.pdf',
                'stored_disk' => 'public',
                'status' => $status,
            ]);

            $this->assertEquals($status, $ingestRun->status, "Status '$status' should be valid");
        }
    }

    /** @test */
    public function it_supports_all_valid_source_values(): void
    {
        $user = User::factory()->create();
        $validSources = ['uploader', 'drive', 'api'];

        foreach ($validSources as $source) {
            $ingestRun = IngestRun::create([
                'user_id' => $user->id,
                'source' => $source,
                'original_filename' => 'test.pdf',
                'stored_path' => 'uploads/test.pdf',
                'stored_disk' => 'public',
                'status' => 'pending',
            ]);

            $this->assertEquals($source, $ingestRun->source, "Source '$source' should be valid");
        }
    }

    /** @test */
    public function it_stores_error_message_on_failure(): void
    {
        $user = User::factory()->create();

        $ingestRun = IngestRun::create([
            'user_id' => $user->id,
            'source' => 'uploader',
            'original_filename' => 'test.pdf',
            'stored_path' => 'uploads/test.pdf',
            'stored_disk' => 'public',
            'status' => 'failed',
            'error_message' => 'OCR processing failed: timeout',
        ]);

        $this->assertEquals('OCR processing failed: timeout', $ingestRun->error_message);
    }
}
