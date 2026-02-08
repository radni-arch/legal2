<?php

namespace Tests\Feature;

use App\Actions\Textract\AnalyzeTextractLayout;
use App\Actions\Textract\DownloadDriveFile;
use App\Actions\Textract\ProcessDrivePdf;
use App\Actions\Textract\ReconstructPdfV2;
use App\Actions\Textract\SaveAnalysisResults;
use App\Actions\Textract\StartTextractAnalysis;
use App\Actions\Textract\UploadInputToS3;
use App\Actions\Textract\UploadOutputToS3;
use App\Models\LegalCase;
use App\Models\TextractJob;
use App\Services\Ocr\LegalDocumentMetadata;
use App\Services\Ocr\LegalMetadataExtractor;
use App\Services\Ocr\OcrDocument;
use App\Services\Ocr\OcrLine;
use App\Services\Ocr\OcrPage;
use App\Services\Ocr\OcrQualityAnalyzer;
use App\Services\TextractService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Facades\Actions;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Feature Test: TextractPipelineIntegration - End-to-End Pipeline Testing
 *
 * Tests the complete Textract OCR pipeline from Google Drive download
 * through AWS Textract processing to final searchable PDF output:
 *
 * Pipeline Flow:
 * 1. EnsureJobStep - Create/find TextractJob record
 * 2. DownloadDriveFileStep - Download PDF from Google Drive
 * 3. UploadInputToS3Step - Upload original PDF to S3 input bucket
 * 4. StartAnalysisStep - Start AWS Textract DocumentAnalysis
 * 5. WaitAndFetchStep - Poll AWS for results, handle pagination
 * 6. SaveResultsStep - Save raw JSON blocks to S3 and local storage
 * 7. CollectLinesStep - Parse blocks into structured OcrDocument
 * 8. CheckOcrQualityStep - Analyze OCR quality and flag low confidence
 * 9. CreateMetadataStep - Extract Croatian legal document metadata
 * 10. ReconstructPdfStep - Generate searchable PDF with TCPDF
 * 11. UploadOutputStep - Upload searchable PDF to S3 output bucket
 * 12. PersistReconstructedStep - Final cleanup and status updates
 *
 * Test Scenarios:
 * 1. Happy path - Complete pipeline execution with high-quality document
 * 2. Google Drive failure - Download failure at step 2
 * 3. Textract failure - AWS API error at step 4 or 5
 * 4. Croatian document - Legal metadata extraction with diacritics
 * 5. Pipeline resume - Checkpoint and resume from failure point
 *
 * Coverage: 5 comprehensive feature test methods
 */
class TextractPipelineIntegrationTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock logging to reduce noise
        Log::shouldReceive('info')->byDefault();
        Log::shouldReceive('error')->byDefault();
        Log::shouldReceive('warning')->byDefault();
        Log::shouldReceive('debug')->byDefault();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test 1: End-to-end pipeline test (happy path)
     *
     * Tests complete pipeline execution from Google Drive to searchable PDF:
     * - All 12 pipeline steps execute successfully
     * - Document downloaded, processed, and uploaded
     * - Metadata extracted and stored
     * - Job status progresses through all stages
     * - Final status is 'succeeded'
     */
    /** @test */
    public function it_completes_full_pipeline_end_to_end_happy_path()
    {
        // Arrange: Create case and job
        $case = LegalCase::factory()->create();

        Storage::fake('s3');
        Storage::fake('local');

        $driveFileId = 'e2e-test-drive-file-001';
        $driveFileName = 'legal-contract.pdf';

        // Mock 1: Download from Google Drive
        Actions::shouldReceive('run')
            ->once()
            ->with(DownloadDriveFile::class, [
                'driveFileId' => $driveFileId,
                'driveFileName' => $driveFileName,
            ])
            ->andReturnUsing(function () {
                $tempPath = sys_get_temp_dir().'/downloaded-legal-contract.pdf';
                file_put_contents($tempPath, '%PDF-1.4 Legal Contract Content');

                return $tempPath;
            });

        // Mock 2: Upload to S3 input
        Actions::shouldReceive('run')
            ->once()
            ->with(UploadInputToS3::class, Mockery::any())
            ->andReturnUsing(function ($action, $params) {
                $s3Key = 'textract/input/'.$params['driveFileId'].'.pdf';
                Storage::disk('s3')->put($s3Key, '%PDF-1.4 Content');

                return $s3Key;
            });

        // Mock 3: Start Textract analysis
        Actions::shouldReceive('run')
            ->once()
            ->with(StartTextractAnalysis::class, Mockery::any())
            ->andReturn('aws-textract-job-abc123');

        // Mock 4: Wait and fetch results (Textract blocks)
        $textractBlocks = [
            ['BlockType' => 'PAGE', 'Id' => 'page-1', 'Page' => 1, 'Confidence' => 99.0],
            ['BlockType' => 'LINE', 'Id' => 'line-1', 'Text' => 'Contract Agreement', 'Page' => 1, 'Confidence' => 98.5,
                'Geometry' => ['BoundingBox' => ['Left' => 0.1, 'Top' => 0.1, 'Width' => 0.3, 'Height' => 0.02]]],
            ['BlockType' => 'LINE', 'Id' => 'line-2', 'Text' => 'Party A and Party B agree to the following terms.', 'Page' => 1, 'Confidence' => 97.8,
                'Geometry' => ['BoundingBox' => ['Left' => 0.1, 'Top' => 0.15, 'Width' => 0.7, 'Height' => 0.015]]],
            ['BlockType' => 'WORD', 'Id' => 'word-1', 'Text' => 'Contract', 'Page' => 1, 'Confidence' => 99.0],
            ['BlockType' => 'WORD', 'Id' => 'word-2', 'Text' => 'Agreement', 'Page' => 1, 'Confidence' => 98.0],
        ];

        $textractServiceMock = Mockery::mock(TextractService::class);
        $textractServiceMock->shouldReceive('waitAndFetchDocumentAnalysis')
            ->once()
            ->andReturn($textractBlocks);
        $this->app->instance(TextractService::class, $textractServiceMock);

        // Mock 5: Save analysis results
        Actions::shouldReceive('run')
            ->once()
            ->with(SaveAnalysisResults::class, Mockery::any())
            ->andReturnUsing(function ($action, $params) {
                $fileId = $params[0];
                $blocks = $params[1];

                $s3Key = 'textract/json/'.$fileId.'.json';
                $localRel = 'textract/json/'.$fileId.'.json';

                Storage::disk('s3')->put($s3Key, json_encode($blocks));
                Storage::disk('local')->put($localRel, json_encode($blocks));

                return [
                    's3JsonKey' => $s3Key,
                    'localJsonRel' => $localRel,
                    'localJsonAbs' => Storage::disk('local')->path($localRel),
                ];
            });

        // Mock 6: Analyze layout (blocks → OcrDocument)
        Actions::shouldReceive('run')
            ->once()
            ->with(AnalyzeTextractLayout::class, Mockery::any())
            ->andReturnUsing(function () {
                $doc = new OcrDocument;
                $page = new OcrPage(1);
                $page->lines = [
                    new OcrLine('Contract Agreement', 0.1, 0.1, 0.3, 0.02, 98.5),
                    new OcrLine('Party A and Party B agree to the following terms.', 0.1, 0.15, 0.7, 0.015, 97.8),
                ];
                $doc->pages = [$page];

                return $doc;
            });

        // Mock 7: Reconstruct PDF
        Actions::shouldReceive('run')
            ->once()
            ->with(ReconstructPdfV2::class, Mockery::any())
            ->andReturnUsing(function ($action, $params) {
                $tempPath = sys_get_temp_dir().'/reconstructed-'.$params['driveFileId'].'.pdf';
                file_put_contents($tempPath, '%PDF-1.4 Searchable PDF Content');

                return $tempPath;
            });

        // Mock 8: Upload output to S3
        Actions::shouldReceive('run')
            ->once()
            ->with(UploadOutputToS3::class, Mockery::any())
            ->andReturnUsing(function ($action, $params) {
                $outKey = 'textract/output/'.$params[0].'-searchable.pdf';
                Storage::disk('s3')->put($outKey, '%PDF-1.4 Searchable');

                return $outKey;
            });

        // Mock OCR quality analyzer
        $qualityAnalyzerMock = Mockery::mock(OcrQualityAnalyzer::class);
        $qualityAnalyzerMock->shouldReceive('analyzeFromBlocks')
            ->once()
            ->andReturn([
                'confidence' => 98.1,
                'coverage' => 95.0,
                'low_confidence_pages' => 0,
            ]);
        $this->app->instance(OcrQualityAnalyzer::class, $qualityAnalyzerMock);

        // Mock legal metadata extractor
        $metadataExtractorMock = Mockery::mock(LegalMetadataExtractor::class);
        $metadataExtractorMock->shouldReceive('extract')
            ->once()
            ->andReturn(new LegalDocumentMetadata(
                documentType: 'contract',
                totalCitations: 0,
            ));
        $this->app->instance(LegalMetadataExtractor::class, $metadataExtractorMock);

        // Act: Execute full pipeline
        $action = new ProcessDrivePdf;
        $action->handle($driveFileId, $driveFileName, true);

        // Assert: Verify complete pipeline execution
        $job = TextractJob::where('drive_file_id', $driveFileId)->first();

        $this->assertNotNull($job);
        $this->assertEquals('succeeded', $job->status);
        $this->assertEquals($driveFileName, $job->drive_file_name);
        $this->assertNotNull($job->extracted_content);
        $this->assertStringContainsString('Contract Agreement', $job->extracted_content);
        $this->assertStringContainsString('Party A and Party B', $job->extracted_content);

        // Verify S3 files were created
        Storage::disk('s3')->assertExists('textract/input/'.$driveFileId.'.pdf');
        Storage::disk('s3')->assertExists('textract/json/'.$driveFileId.'.json');
        Storage::disk('s3')->assertExists('textract/output/'.$driveFileId.'-searchable.pdf');
    }

    /**
     * Test 2: Pipeline with Google Drive download failure
     *
     * Tests pipeline behavior when Google Drive download fails:
     * - DownloadDriveFileStep throws exception
     * - Pipeline execution halts at step 2
     * - Job status set to 'failed'
     * - Error message captured in job.error field
     * - No subsequent steps execute
     */
    /** @test */
    public function it_handles_google_drive_download_failure()
    {
        // Arrange
        Storage::fake('s3');
        Storage::fake('local');

        $driveFileId = 'download-failure-test-002';
        $driveFileName = 'missing-file.pdf';

        // Mock: Download fails with Google Drive API error
        Actions::shouldReceive('run')
            ->once()
            ->with(DownloadDriveFile::class, Mockery::any())
            ->andThrow(new \RuntimeException('Google Drive API error: File not found (404)'));

        // No other actions should be called
        Actions::shouldReceive('run')
            ->with(UploadInputToS3::class, Mockery::any())
            ->never();

        // Act & Assert: Expect exception
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Google Drive API error: File not found');

        try {
            $action = new ProcessDrivePdf;
            $action->handle($driveFileId, $driveFileName, true);
        } finally {
            // Verify job was marked as failed
            $job = TextractJob::where('drive_file_id', $driveFileId)->first();

            if ($job) {
                $this->assertEquals('failed', $job->status);
                $this->assertNotNull($job->error);
                $this->assertStringContainsString('Google Drive API error', $job->error);
            }

            // Verify no S3 files were created
            Storage::disk('s3')->assertMissing('textract/input/'.$driveFileId.'.pdf');
            Storage::disk('s3')->assertMissing('textract/json/'.$driveFileId.'.json');
        }
    }

    /**
     * Test 3: Pipeline with Textract processing failure
     *
     * Tests pipeline behavior when AWS Textract processing fails:
     * - StartAnalysisStep or WaitAndFetchStep fails with AWS error
     * - Pipeline halts after S3 upload but before results retrieval
     * - Job status set to 'failed'
     * - AWS error code and message captured
     * - Supports retry mechanisms (documented in error)
     */
    /** @test */
    public function it_handles_textract_processing_failure()
    {
        // Arrange
        Storage::fake('s3');
        Storage::fake('local');

        $driveFileId = 'textract-failure-test-003';
        $driveFileName = 'corrupt-scan.pdf';

        // Mock 1: Download succeeds
        Actions::shouldReceive('run')
            ->once()
            ->with(DownloadDriveFile::class, Mockery::any())
            ->andReturnUsing(function () {
                $tempPath = sys_get_temp_dir().'/corrupt-scan.pdf';
                file_put_contents($tempPath, '%PDF-1.4 Corrupt');

                return $tempPath;
            });

        // Mock 2: Upload to S3 succeeds
        Actions::shouldReceive('run')
            ->once()
            ->with(UploadInputToS3::class, Mockery::any())
            ->andReturnUsing(function ($action, $params) {
                $s3Key = 'textract/input/'.$params['driveFileId'].'.pdf';
                Storage::disk('s3')->put($s3Key, '%PDF-1.4');

                return $s3Key;
            });

        // Mock 3: Start Textract analysis succeeds
        Actions::shouldReceive('run')
            ->once()
            ->with(StartTextractAnalysis::class, Mockery::any())
            ->andReturn('aws-job-failure-456');

        // Mock 4: Wait and fetch fails with AWS Textract error
        $textractServiceMock = Mockery::mock(TextractService::class);
        $textractServiceMock->shouldReceive('waitAndFetchDocumentAnalysis')
            ->once()
            ->andThrow(new \RuntimeException('Textract job aws-job-failure-456 status: FAILED. Reason: InvalidImageException - Document image is too dark or corrupted.'));
        $this->app->instance(TextractService::class, $textractServiceMock);

        // No subsequent actions should be called
        Actions::shouldReceive('run')
            ->with(SaveAnalysisResults::class, Mockery::any())
            ->never();

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Textract job aws-job-failure-456 status: FAILED');

        try {
            $action = new ProcessDrivePdf;
            $action->handle($driveFileId, $driveFileName, true);
        } finally {
            // Verify job failure status
            $job = TextractJob::where('drive_file_id', $driveFileId)->first();

            if ($job) {
                $this->assertEquals('failed', $job->status);
                $this->assertNotNull($job->error);
                $this->assertStringContainsString('FAILED', $job->error);
                $this->assertStringContainsString('InvalidImageException', $job->error);

                // Verify job has Textract job ID stored
                $this->assertEquals('aws-job-failure-456', $job->job_id);
            }

            // Verify input file was uploaded (before failure)
            Storage::disk('s3')->assertExists('textract/input/'.$driveFileId.'.pdf');

            // Verify no output files created (after failure)
            Storage::disk('s3')->assertMissing('textract/json/'.$driveFileId.'.json');
            Storage::disk('s3')->assertMissing('textract/output/'.$driveFileId.'-searchable.pdf');
        }
    }

    /**
     * Test 4: Pipeline with Croatian document metadata extraction
     *
     * Tests complete pipeline with Croatian legal document:
     * - Full pipeline execution with Croatian text (č, ć, š, ž, đ)
     * - Legal metadata extraction: case numbers, courts, dates
     * - Croatian diacritics preserved through all steps
     * - Searchable PDF generated with dejavusans font
     * - Metadata stored in job.metadata field
     */
    /** @test */
    public function it_processes_croatian_legal_document_with_metadata_extraction()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        Storage::fake('s3');
        Storage::fake('local');

        $driveFileId = 'croatian-doc-test-004';
        $driveFileName = 'presuda-vsrh-gž-1234-23.pdf';

        // Mock 1: Download Croatian document
        Actions::shouldReceive('run')
            ->once()
            ->with(DownloadDriveFile::class, Mockery::any())
            ->andReturnUsing(function () {
                $tempPath = sys_get_temp_dir().'/presuda-croatian.pdf';
                file_put_contents($tempPath, '%PDF-1.4 Croatian Legal Document');

                return $tempPath;
            });

        // Mock 2: Upload to S3
        Actions::shouldReceive('run')
            ->once()
            ->with(UploadInputToS3::class, Mockery::any())
            ->andReturnUsing(function ($action, $params) {
                $s3Key = 'textract/input/'.$params['driveFileId'].'.pdf';
                Storage::disk('s3')->put($s3Key, '%PDF-1.4');

                return $s3Key;
            });

        // Mock 3: Start Textract
        Actions::shouldReceive('run')
            ->once()
            ->with(StartTextractAnalysis::class, Mockery::any())
            ->andReturn('aws-textract-croatian-789');

        // Mock 4: Textract results with Croatian text
        $croatianBlocks = [
            ['BlockType' => 'PAGE', 'Id' => 'page-1', 'Page' => 1, 'Confidence' => 99.5],
            ['BlockType' => 'LINE', 'Id' => 'line-1', 'Text' => 'VRHOVNI SUD REPUBLIKE HRVATSKE', 'Page' => 1, 'Confidence' => 99.8,
                'Geometry' => ['BoundingBox' => ['Left' => 0.1, 'Top' => 0.05, 'Width' => 0.6, 'Height' => 0.03]]],
            ['BlockType' => 'LINE', 'Id' => 'line-2', 'Text' => 'PRESUDA', 'Page' => 1, 'Confidence' => 99.9,
                'Geometry' => ['BoundingBox' => ['Left' => 0.1, 'Top' => 0.1, 'Width' => 0.2, 'Height' => 0.025]]],
            ['BlockType' => 'LINE', 'Id' => 'line-3', 'Text' => 'Poslovni broj: Gž 1234/23', 'Page' => 1, 'Confidence' => 98.5,
                'Geometry' => ['BoundingBox' => ['Left' => 0.1, 'Top' => 0.15, 'Width' => 0.4, 'Height' => 0.02]]],
            ['BlockType' => 'LINE', 'Id' => 'line-4', 'Text' => 'Tužitelj: Marko Matić, Zagreb', 'Page' => 1, 'Confidence' => 97.5,
                'Geometry' => ['BoundingBox' => ['Left' => 0.1, 'Top' => 0.2, 'Width' => 0.5, 'Height' => 0.018]]],
            ['BlockType' => 'LINE', 'Id' => 'line-5', 'Text' => 'Tuženik: Petar Perić, Čakovec', 'Page' => 1, 'Confidence' => 97.0,
                'Geometry' => ['BoundingBox' => ['Left' => 0.1, 'Top' => 0.25, 'Width' => 0.5, 'Height' => 0.018]]],
            ['BlockType' => 'LINE', 'Id' => 'line-6', 'Text' => 'Primjenom članka 29. Zakona o kaznenom postupku (NN 152/08)', 'Page' => 1, 'Confidence' => 96.0,
                'Geometry' => ['BoundingBox' => ['Left' => 0.1, 'Top' => 0.3, 'Width' => 0.7, 'Height' => 0.015]]],
        ];

        $textractServiceMock = Mockery::mock(TextractService::class);
        $textractServiceMock->shouldReceive('waitAndFetchDocumentAnalysis')
            ->once()
            ->andReturn($croatianBlocks);
        $this->app->instance(TextractService::class, $textractServiceMock);

        // Mock 5: Save results
        Actions::shouldReceive('run')
            ->once()
            ->with(SaveAnalysisResults::class, Mockery::any())
            ->andReturnUsing(function ($action, $params) {
                $fileId = $params[0];
                $s3Key = 'textract/json/'.$fileId.'.json';
                Storage::disk('s3')->put($s3Key, json_encode($params[1], JSON_UNESCAPED_UNICODE));

                return ['s3JsonKey' => $s3Key, 'localJsonRel' => 'test.json', 'localJsonAbs' => '/tmp/test.json'];
            });

        // Mock 6: Analyze layout
        Actions::shouldReceive('run')
            ->once()
            ->with(AnalyzeTextractLayout::class, Mockery::any())
            ->andReturnUsing(function () {
                $doc = new OcrDocument;
                $page = new OcrPage(1);
                $page->lines = [
                    new OcrLine('VRHOVNI SUD REPUBLIKE HRVATSKE', 0.1, 0.05, 0.6, 0.03, 99.8),
                    new OcrLine('PRESUDA', 0.1, 0.1, 0.2, 0.025, 99.9),
                    new OcrLine('Poslovni broj: Gž 1234/23', 0.1, 0.15, 0.4, 0.02, 98.5),
                    new OcrLine('Tužitelj: Marko Matić, Zagreb', 0.1, 0.2, 0.5, 0.018, 97.5),
                    new OcrLine('Tuženik: Petar Perić, Čakovec', 0.1, 0.25, 0.5, 0.018, 97.0),
                    new OcrLine('Primjenom članka 29. Zakona o kaznenom postupku (NN 152/08)', 0.1, 0.3, 0.7, 0.015, 96.0),
                ];
                $doc->pages = [$page];

                return $doc;
            });

        // Mock 7: Reconstruct PDF (with Croatian text)
        Actions::shouldReceive('run')
            ->once()
            ->with(ReconstructPdfV2::class, Mockery::any())
            ->andReturnUsing(function () {
                $tempPath = sys_get_temp_dir().'/croatian-searchable.pdf';
                // Simulate PDF with Croatian diacritics
                file_put_contents($tempPath, '%PDF-1.4 Croatian: Čakovec Matić Perić');

                return $tempPath;
            });

        // Mock 8: Upload output
        Actions::shouldReceive('run')
            ->once()
            ->with(UploadOutputToS3::class, Mockery::any())
            ->andReturnUsing(function ($action, $params) {
                $outKey = 'textract/output/'.$params[0].'-searchable.pdf';
                Storage::disk('s3')->put($outKey, '%PDF-1.4');

                return $outKey;
            });

        // Mock quality analyzer
        $qualityAnalyzerMock = Mockery::mock(OcrQualityAnalyzer::class);
        $qualityAnalyzerMock->shouldReceive('analyzeFromBlocks')
            ->once()
            ->andReturn(['confidence' => 98.0, 'coverage' => 96.0, 'low_confidence_pages' => 0]);
        $this->app->instance(OcrQualityAnalyzer::class, $qualityAnalyzerMock);

        // Mock Croatian legal metadata extractor
        $metadataExtractorMock = Mockery::mock(LegalMetadataExtractor::class);
        $metadataExtractorMock->shouldReceive('extract')
            ->once()
            ->andReturn(new LegalDocumentMetadata(
                documentType: 'presuda',
                caseNumberCitations: [
                    ['canonical' => 'Gž 1234/23', 'prefix' => 'Gž', 'number' => '1234', 'year' => '23'],
                ],
                courts: ['Vrhovni sud Republike Hrvatske'],
                parties: ['Marko Matić', 'Petar Perić'],
                legalCitations: [
                    ['text' => 'Zakon o kaznenom postupku (NN 152/08)', 'type' => 'statute'],
                ],
                totalCitations: 2,
            ));
        $this->app->instance(LegalMetadataExtractor::class, $metadataExtractorMock);

        // Act: Execute pipeline with Croatian document
        $action = new ProcessDrivePdf;
        $action->handle($driveFileId, $driveFileName, true);

        // Assert: Verify Croatian text processing
        $job = TextractJob::where('drive_file_id', $driveFileId)->first();

        $this->assertNotNull($job);
        $this->assertEquals('succeeded', $job->status);

        // Verify Croatian diacritics preserved in extracted content
        $this->assertStringContainsString('Matić', $job->extracted_content);
        $this->assertStringContainsString('Perić', $job->extracted_content);
        $this->assertStringContainsString('Čakovec', $job->extracted_content);

        // Verify Croatian legal metadata
        $this->assertNotNull($job->metadata);
        $this->assertEquals('presuda', $job->metadata['documentType'] ?? null);
        $this->assertArrayHasKey('caseNumberCitations', $job->metadata);
        $this->assertCount(1, $job->metadata['caseNumberCitations']);
        $this->assertEquals('Gž 1234/23', $job->metadata['caseNumberCitations'][0]['canonical']);

        // Verify courts
        $this->assertArrayHasKey('courts', $job->metadata);
        $this->assertContains('Vrhovni sud Republike Hrvatske', $job->metadata['courts']);

        // Verify parties
        $this->assertArrayHasKey('parties', $job->metadata);
        $this->assertContains('Marko Matić', $job->metadata['parties']);
        $this->assertContains('Petar Perić', $job->metadata['parties']);

        // Verify searchable PDF created with Croatian text
        $pdfContent = Storage::disk('s3')->get('textract/output/'.$driveFileId.'-searchable.pdf');
        $this->assertStringContainsString('%PDF-1.4', $pdfContent);
    }

    /**
     * Test 5: Pipeline resume after failure (checkpoint/resume)
     *
     * Tests pipeline resume functionality after mid-execution failure:
     * - Initial run fails at WaitAndFetchStep (timeout)
     * - Job status saved as 'analyzing' with job_id stored
     * - Resume from checkpoint (skip download, skip upload, skip start)
     * - Resume execution picks up at WaitAndFetchStep
     * - Complete remaining steps successfully
     * - Final status is 'succeeded'
     */
    /** @test */
    public function it_resumes_pipeline_after_failure_from_checkpoint()
    {
        // Arrange: Simulate failed job at WaitAndFetch stage
        $case = LegalCase::factory()->create();

        Storage::fake('s3');
        Storage::fake('local');

        $driveFileId = 'resume-test-005';
        $driveFileName = 'resume-document.pdf';
        $awsJobId = 'aws-resume-job-xyz789';

        // Pre-create job in 'analyzing' state (simulating previous failed run)
        $existingJob = TextractJob::create([
            'drive_file_id' => $driveFileId,
            'drive_file_name' => $driveFileName,
            'case_id' => $case->id,
            'status' => 'analyzing',
            's3_key' => 'textract/input/'.$driveFileId.'.pdf',
            'job_id' => $awsJobId,
            'metadata' => [
                'checkpoint' => 'wait_and_fetch',
                'attempts' => 1,
                'last_error' => 'Timeout waiting for Textract job completion',
            ],
        ]);

        // Pre-upload S3 input file (from previous run)
        Storage::disk('s3')->put('textract/input/'.$driveFileId.'.pdf', '%PDF-1.4 Content');

        // Mock: Download should NOT be called (checkpoint skip)
        Actions::shouldReceive('run')
            ->with(DownloadDriveFile::class, Mockery::any())
            ->never();

        // Mock: Upload should NOT be called (checkpoint skip)
        Actions::shouldReceive('run')
            ->with(UploadInputToS3::class, Mockery::any())
            ->never();

        // Mock: Start analysis should NOT be called (checkpoint skip)
        Actions::shouldReceive('run')
            ->with(StartTextractAnalysis::class, Mockery::any())
            ->never();

        // Mock: Wait and fetch NOW succeeds (resume point)
        $resumeBlocks = [
            ['BlockType' => 'PAGE', 'Id' => 'page-1', 'Page' => 1, 'Confidence' => 99.0],
            ['BlockType' => 'LINE', 'Id' => 'line-1', 'Text' => 'Resumed Document Content', 'Page' => 1, 'Confidence' => 98.0,
                'Geometry' => ['BoundingBox' => ['Left' => 0.1, 'Top' => 0.1, 'Width' => 0.5, 'Height' => 0.02]]],
        ];

        $textractServiceMock = Mockery::mock(TextractService::class);
        $textractServiceMock->shouldReceive('waitAndFetchDocumentAnalysis')
            ->once()
            ->with($awsJobId, Mockery::any(), Mockery::any())
            ->andReturn($resumeBlocks);
        $this->app->instance(TextractService::class, $textractServiceMock);

        // Mock: All subsequent steps execute normally
        Actions::shouldReceive('run')
            ->once()
            ->with(SaveAnalysisResults::class, Mockery::any())
            ->andReturnUsing(function ($action, $params) {
                $s3Key = 'textract/json/'.$params[0].'.json';
                Storage::disk('s3')->put($s3Key, json_encode($params[1]));

                return ['s3JsonKey' => $s3Key, 'localJsonRel' => 'test.json', 'localJsonAbs' => '/tmp/test.json'];
            });

        Actions::shouldReceive('run')
            ->once()
            ->with(AnalyzeTextractLayout::class, Mockery::any())
            ->andReturnUsing(function () {
                $doc = new OcrDocument;
                $page = new OcrPage(1);
                $page->lines = [new OcrLine('Resumed Document Content', 0.1, 0.1, 0.5, 0.02, 98.0)];
                $doc->pages = [$page];

                return $doc;
            });

        Actions::shouldReceive('run')
            ->once()
            ->with(ReconstructPdfV2::class, Mockery::any())
            ->andReturnUsing(function () {
                $tempPath = sys_get_temp_dir().'/resumed.pdf';
                file_put_contents($tempPath, '%PDF-1.4 Resumed');

                return $tempPath;
            });

        Actions::shouldReceive('run')
            ->once()
            ->with(UploadOutputToS3::class, Mockery::any())
            ->andReturnUsing(function ($action, $params) {
                $outKey = 'textract/output/'.$params[0].'-searchable.pdf';
                Storage::disk('s3')->put($outKey, '%PDF-1.4');

                return $outKey;
            });

        $qualityAnalyzerMock = Mockery::mock(OcrQualityAnalyzer::class);
        $qualityAnalyzerMock->shouldReceive('analyzeFromBlocks')
            ->once()
            ->andReturn(['confidence' => 98.0, 'coverage' => 95.0, 'low_confidence_pages' => 0]);
        $this->app->instance(OcrQualityAnalyzer::class, $qualityAnalyzerMock);

        $metadataExtractorMock = Mockery::mock(LegalMetadataExtractor::class);
        $metadataExtractorMock->shouldReceive('extract')
            ->once()
            ->andReturn(new LegalDocumentMetadata(documentType: 'document', totalCitations: 0));
        $this->app->instance(LegalMetadataExtractor::class, $metadataExtractorMock);

        // Act: Resume pipeline execution
        $action = new ProcessDrivePdf;
        $action->handle($driveFileId, $driveFileName, true);

        // Assert: Verify resume completed successfully
        $existingJob->refresh();

        $this->assertEquals('succeeded', $existingJob->status);
        $this->assertNotNull($existingJob->extracted_content);
        $this->assertStringContainsString('Resumed Document Content', $existingJob->extracted_content);

        // Verify checkpoint metadata updated
        $this->assertArrayHasKey('attempts', $existingJob->metadata);

        // Verify output files created (resume completed all steps)
        Storage::disk('s3')->assertExists('textract/json/'.$driveFileId.'.json');
        Storage::disk('s3')->assertExists('textract/output/'.$driveFileId.'-searchable.pdf');

        // Verify input file still exists (from previous run)
        Storage::disk('s3')->assertExists('textract/input/'.$driveFileId.'.pdf');
    }
}
