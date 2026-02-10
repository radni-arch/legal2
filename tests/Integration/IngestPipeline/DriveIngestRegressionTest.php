<?php

namespace Tests\Integration\IngestPipeline;

use App\Events\CaseDocumentIngested;
use App\Jobs\Ingest\ProcessIngestRunJob;
use App\Jobs\ProcessTextractJob;
use App\Models\IngestRun;
use App\Models\LegalCase;
use App\Models\TextractJob;
use App\Models\User;
use App\Services\Ingest\IngestOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression tests for the Google Drive ingestion path.
 *
 * Verifies that files synced from Google Drive enter the same canonical
 * ingest pipeline: IngestRun creation → ProcessIngestRunJob → OCR → analysis.
 *
 * SOT-014 acceptance criteria:
 * CI proves Drive path produces expected OCR/embedding/analysis artifacts.
 */
class DriveIngestRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    /**
     * Drive source creates IngestRun with 'drive' source type.
     */
    public function test_drive_source_creates_ingest_run_with_drive_source(): void
    {
        Queue::fake([ProcessIngestRunJob::class]);

        $user = User::factory()->create();
        $case = LegalCase::factory()->create();
        $orchestrator = app(IngestOrchestrator::class);

        $storedPath = 'textract/source/drive-file-123.pdf';
        Storage::disk('public')->put($storedPath, '%PDF-1.4 drive content');

        $ingestRun = $orchestrator->ingest(
            storedPath: $storedPath,
            disk: 'public',
            originalFilename: 'Presuda-Pp-Prz-74-2025.pdf',
            userId: $user->id,
            caseId: $case->id,
            source: 'drive',
        );

        $this->assertNotNull($ingestRun);
        $this->assertEquals('drive', $ingestRun->source);
        $this->assertEquals('pending', $ingestRun->status);
        $this->assertEquals($user->id, $ingestRun->user_id);
        $this->assertEquals($case->id, $ingestRun->case_id);
        $this->assertNotEmpty($ingestRun->correlation_id);

        Queue::assertPushed(ProcessIngestRunJob::class, function ($job) use ($ingestRun) {
            return $job->ingestRunId === $ingestRun->id;
        });
    }

    /**
     * Drive PDF file triggers Textract OCR pipeline.
     */
    public function test_drive_pdf_triggers_textract_pipeline(): void
    {
        Queue::fake([ProcessTextractJob::class]);

        $user = User::factory()->create();
        $case = LegalCase::factory()->create();

        $storedPath = 'textract/source/drive-pdf.pdf';
        Storage::disk('public')->put($storedPath, '%PDF-1.4 drive pdf');

        $ingestRun = IngestRun::create([
            'user_id' => $user->id,
            'case_id' => $case->id,
            'source' => 'drive',
            'original_filename' => 'Odluka-Kz-123-2024.pdf',
            'stored_path' => $storedPath,
            'stored_disk' => 'public',
            'status' => 'pending',
        ]);

        $job = new ProcessIngestRunJob($ingestRun->id);
        $job->handle();

        $ingestRun->refresh();
        $this->assertEquals('ocr', $ingestRun->status);

        // Verify TextractJob was created with drive metadata
        $textractJob = TextractJob::where('drive_file_id', $ingestRun->id)->first();
        $this->assertNotNull($textractJob);
        $this->assertEquals('drive', $textractJob->metadata['source']);

        Queue::assertPushed(ProcessTextractJob::class);
    }

    /**
     * Drive and uploader paths use the same ProcessIngestRunJob.
     * This regression test ensures pipeline behavior is identical.
     */
    public function test_drive_and_uploader_use_same_pipeline(): void
    {
        Event::fake([CaseDocumentIngested::class]);

        $user = User::factory()->create();
        $case = LegalCase::factory()->create();

        // Create two IngestRuns: one drive, one uploader
        $content = 'Same legal document content for both paths';

        $uploaderPath = 'uploads/uploader-doc.txt';
        $drivePath = 'textract/source/drive-doc.txt';
        Storage::disk('public')->put($uploaderPath, $content);
        Storage::disk('public')->put($drivePath, $content);

        $uploaderRun = IngestRun::create([
            'user_id' => $user->id,
            'case_id' => $case->id,
            'source' => 'uploader',
            'original_filename' => 'doc.txt',
            'stored_path' => $uploaderPath,
            'stored_disk' => 'public',
            'status' => 'pending',
        ]);

        $driveRun = IngestRun::create([
            'user_id' => $user->id,
            'case_id' => $case->id,
            'source' => 'drive',
            'original_filename' => 'doc.txt',
            'stored_path' => $drivePath,
            'stored_disk' => 'public',
            'status' => 'pending',
        ]);

        // Process both
        (new ProcessIngestRunJob($uploaderRun->id))->handle();
        (new ProcessIngestRunJob($driveRun->id))->handle();

        $uploaderRun->refresh();
        $driveRun->refresh();

        // Both should complete with the same status
        $this->assertEquals('completed', $uploaderRun->status);
        $this->assertEquals('completed', $driveRun->status);

        // Both should fire CaseDocumentIngested
        Event::assertDispatched(CaseDocumentIngested::class, 2);
    }

    /**
     * API source path also creates valid IngestRun.
     */
    public function test_api_source_creates_valid_ingest_run(): void
    {
        Queue::fake([ProcessIngestRunJob::class]);

        $user = User::factory()->create();
        $orchestrator = app(IngestOrchestrator::class);

        $storedPath = 'uploads/api-file.txt';
        Storage::disk('public')->put($storedPath, 'API uploaded content');

        $ingestRun = $orchestrator->ingest(
            storedPath: $storedPath,
            disk: 'public',
            originalFilename: 'api-document.txt',
            userId: $user->id,
            caseId: null,
            source: 'api',
        );

        $this->assertEquals('api', $ingestRun->source);
        $this->assertNull($ingestRun->case_id);
        $this->assertNotEmpty($ingestRun->correlation_id);

        Queue::assertPushed(ProcessIngestRunJob::class);
    }

    /**
     * Text file without case_id creates no CaseDocument but still completes.
     */
    public function test_text_without_case_id_completes_without_case_document(): void
    {
        Event::fake([CaseDocumentIngested::class]);

        $user = User::factory()->create();

        $storedPath = 'uploads/no-case.txt';
        Storage::disk('public')->put($storedPath, 'Standalone document');

        $ingestRun = IngestRun::create([
            'user_id' => $user->id,
            'case_id' => null,
            'source' => 'drive',
            'original_filename' => 'standalone.txt',
            'stored_path' => $storedPath,
            'stored_disk' => 'public',
            'status' => 'pending',
        ]);

        (new ProcessIngestRunJob($ingestRun->id))->handle();

        $ingestRun->refresh();
        $this->assertEquals('completed', $ingestRun->status);

        // No CaseDocumentIngested should be dispatched without case_id
        Event::assertNotDispatched(CaseDocumentIngested::class);
    }
}
