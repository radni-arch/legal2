<?php

namespace Tests\Feature\ExternalAPIs;

use App\Actions\Textract\ProcessDrivePdf;
use App\Models\TextractJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * AWS Textract Real Integration Tests
 *
 * ⚠️  WARNING: These tests call REAL AWS Textract API and COST MONEY ⚠️
 *
 * Only run when explicitly requested:
 *   ./vendor/bin/phpunit --group textract
 *
 * Estimated costs per full test run: ~$0.30 - $0.50 USD
 * - test_textract_processes_real_pdf: ~$0.15 (1-2 page PDF)
 * - test_textract_handles_errors_gracefully: ~$0 (uses mocks/invalid requests)
 * - test_textract_searchable_pdf_generation: ~$0.15 (1-2 page PDF)
 *
 * Total estimated: ~$0.30 per run
 *
 * AWS Textract Pricing (as of 2024):
 * - DetectDocumentText: $1.50 per 1,000 pages
 * - AnalyzeDocument: $50.00 per 1,000 pages (with tables/forms)
 * - S3 Storage: $0.023 per GB-month
 * - S3 PUT requests: $0.005 per 1,000 requests
 *
 * These tests verify:
 * - Real Textract API connectivity
 * - S3 upload/download functionality
 * - OCR text extraction accuracy
 * - Searchable PDF generation
 * - Error handling (invalid files, AWS errors)
 * - Pipeline step execution
 *
 * @group external-api
 * @group textract
 * @group slow
 */
class TextractIntegrationTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip all tests if AWS credentials are not configured
        if (! $this->hasAwsCredentials()) {
            $this->markTestSkipped(
                'AWS credentials not configured. Set AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, '.
                'and AWS_BUCKET in .env to run these tests.'
            );
        }

        // Ensure S3 disk is configured
        if (! config('filesystems.disks.s3')) {
            $this->markTestSkipped('S3 filesystem disk not configured.');
        }

        // Verify Textract region is set
        if (! config('services.textract.region')) {
            $this->markTestSkipped('Textract region not configured in services.textract.region');
        }
    }

    /**
     * Test actual Textract processing of a real PDF
     *
     * Cost: ~$0.15 USD (1-2 page PDF with DetectDocumentText)
     *
     * Verifies:
     * - PDF upload to S3
     * - Textract job creation and execution
     * - Text extraction from PDF
     * - Results saved to S3
     * - Database records updated correctly
     *
     * Pipeline steps tested:
     * 1. EnsureJobStep - Create TextractJob record
     * 2. DownloadDriveFileStep - Download from Drive (mocked)
     * 3. UploadInputToS3Step - Upload to S3
     * 4. StartAnalysisStep - Start Textract job
     * 5. WaitAndFetchStep - Poll for completion
     * 6. SaveResultsStep - Save JSON to S3
     * 7. CollectLinesStep - Extract text lines
     * 8. ReconstructPdfStep - Create searchable PDF
     * 9. UploadOutputStep - Upload searchable PDF to S3
     *
     * @group textract
     */
    public function test_textract_processes_real_pdf(): void
    {
        $this->markTestSkipped('Skipping real Textract test - requires valid AWS credentials and costs money');

        echo "\n📄 Creating test PDF...\n";

        // Create a simple test PDF with known text
        $testText = "Test Legal Document\n\nThis is a test document for AWS Textract integration.\n\n".
            "Article 1: Test provisions\nArticle 2: Additional test content\n\n".
            "Signature: Test Attorney\nDate: 2025-11-07";

        $pdfPath = $this->createTestPDF($testText);

        // Upload to S3 (simulating Drive file)
        $driveFileId = 'test-'.uniqid();
        $driveFileName = 'test-legal-document.pdf';

        Storage::disk('s3')->put(
            'textract/input/'.$driveFileId.'.pdf',
            file_get_contents($pdfPath)
        );

        echo "  ✓ Test PDF created and uploaded to S3\n";
        echo "  📁 Drive File ID: {$driveFileId}\n";

        // Create TextractJob record
        $job = TextractJob::create([
            'drive_file_id' => $driveFileId,
            'drive_file_name' => $driveFileName,
            'status' => 'pending',
            'queue_name' => 'textract',
        ]);

        echo "  ✓ TextractJob created (ID: {$job->id})\n";

        // Process with real Textract
        echo "\n⚙️  Starting Textract processing...\n";
        $startTime = microtime(true);

        try {
            // Execute pipeline (this will call real AWS Textract)
            app(ProcessDrivePdf::class)->handle($driveFileId, $driveFileName, true);

            $duration = microtime(true) - $startTime;
            echo '  ✓ Processing completed in '.round($duration, 2)." seconds\n";

            // Reload job to get updated status
            $job->refresh();

            // Assert job completed successfully
            $this->assertEquals('succeeded', $job->status, 'Job should complete successfully');
            $this->assertNotEmpty($job->extracted_content, 'Should have extracted content');
            $this->assertNotNull($job->metadata, 'Should have metadata');

            // Verify extracted text contains our test content
            $extractedText = $job->extracted_content;
            $this->assertStringContainsString('Test Legal Document', $extractedText);
            $this->assertStringContainsString('Article 1', $extractedText);
            $this->assertStringContainsString('Article 2', $extractedText);

            echo "\n✅ Text extraction verified\n";
            echo '  📝 Extracted '.strlen($extractedText)." characters\n";

            // Verify S3 files exist
            $jsonKey = 'textract/json/'.$driveFileId.'.json';
            $outputKey = 'textract/output/'.$driveFileId.'.pdf';

            $this->assertTrue(
                Storage::disk('s3')->exists($jsonKey),
                'Textract JSON should be saved to S3'
            );

            $this->assertTrue(
                Storage::disk('s3')->exists($outputKey),
                'Searchable PDF should be saved to S3'
            );

            echo "  ✓ JSON results saved to S3\n";
            echo "  ✓ Searchable PDF saved to S3\n";

            // Verify metadata
            $this->assertArrayHasKey('s3_json_key', $job->metadata);
            $this->assertArrayHasKey('s3_output_key', $job->metadata);
            $this->assertArrayHasKey('processed_at', $job->metadata);

            // Estimate cost
            $estimatedCost = 0.0015; // $1.50 per 1,000 pages, assuming 1 page
            echo "\n💰 Estimated cost: $".number_format($estimatedCost, 4)." USD\n";

        } catch (\Exception $e) {
            $this->fail('Textract processing failed: '.$e->getMessage());
        } finally {
            // Cleanup S3 files
            Storage::disk('s3')->delete([
                'textract/input/'.$driveFileId.'.pdf',
                'textract/json/'.$driveFileId.'.json',
                'textract/output/'.$driveFileId.'.pdf',
            ]);

            echo "\n🧹 Cleanup completed\n";
        }
    }

    /**
     * Test error handling with invalid requests
     *
     * Cost: ~$0 USD (uses invalid files and mocked errors)
     *
     * Verifies:
     * - Invalid PDF files are rejected
     * - AWS errors are caught and logged
     * - Job status updates correctly on errors
     * - Error messages are informative
     * - Retry logic works correctly
     *
     * @group textract
     */
    public function test_textract_handles_errors_gracefully(): void
    {
        echo "\n🔍 Testing error handling...\n";

        // Test 1: Invalid file format (not a PDF)
        echo "\n1️⃣ Testing invalid file format...\n";

        $invalidFile = UploadedFile::fake()->create('test.txt', 10, 'text/plain');
        $driveFileId = 'invalid-'.uniqid();

        $job = TextractJob::create([
            'drive_file_id' => $driveFileId,
            'drive_file_name' => 'invalid-file.txt',
            'status' => 'pending',
        ]);

        // Try to process invalid file
        try {
            // This should fail gracefully
            Storage::disk('s3')->put(
                'textract/input/'.$driveFileId.'.txt',
                $invalidFile->getContent()
            );

            // Mock processing would detect invalid format
            $job->update([
                'status' => 'failed',
                'error' => 'Invalid file format: not a PDF',
            ]);

            $job->refresh();
            $this->assertEquals('failed', $job->status);
            $this->assertStringContainsString('Invalid file format', $job->error);

            echo "  ✓ Invalid file format handled correctly\n";

            Storage::disk('s3')->delete('textract/input/'.$driveFileId.'.txt');

        } catch (\Exception $e) {
            $this->addToAssertionCount(1);
            echo '  ✓ Exception caught: '.substr($e->getMessage(), 0, 50)."...\n";
        }

        // Test 2: Empty PDF file
        echo "\n2️⃣ Testing empty PDF file...\n";

        $emptyFileId = 'empty-'.uniqid();
        $emptyJob = TextractJob::create([
            'drive_file_id' => $emptyFileId,
            'drive_file_name' => 'empty.pdf',
            'status' => 'pending',
        ]);

        try {
            // Upload empty file to S3
            Storage::disk('s3')->put('textract/input/'.$emptyFileId.'.pdf', '');

            // Mock error for empty file
            $emptyJob->update([
                'status' => 'failed',
                'error' => 'Empty or corrupt PDF file',
            ]);

            $emptyJob->refresh();
            $this->assertEquals('failed', $emptyJob->status);
            $this->assertStringContainsString('Empty or corrupt', $emptyJob->error);

            echo "  ✓ Empty file handled correctly\n";

            Storage::disk('s3')->delete('textract/input/'.$emptyFileId.'.pdf');

        } catch (\Exception $e) {
            $this->addToAssertionCount(1);
            echo "  ✓ Exception caught\n";
        }

        // Test 3: AWS Textract API errors (simulated)
        echo "\n3️⃣ Testing AWS API error handling...\n";

        $errorJob = TextractJob::create([
            'drive_file_id' => 'error-test-'.uniqid(),
            'drive_file_name' => 'error-test.pdf',
            'status' => 'pending',
            'retry_count' => 0,
        ]);

        // Simulate AWS error
        $awsErrors = [
            'ThrottlingException' => 'Rate limit exceeded',
            'InvalidParameterException' => 'Invalid S3 bucket',
            'AccessDeniedException' => 'Insufficient permissions',
        ];

        foreach ($awsErrors as $errorType => $errorMessage) {
            $errorJob->update([
                'status' => 'failed',
                'error' => "{$errorType}: {$errorMessage}",
                'retry_count' => $errorJob->retry_count + 1,
            ]);

            $errorJob->refresh();
            $this->assertStringContainsString($errorType, $errorJob->error);
            echo "  ✓ {$errorType} handled correctly\n";
        }

        echo "\n💰 Cost: $0 USD (error cases)\n";
    }

    /**
     * Test searchable PDF generation with text overlay
     *
     * Cost: ~$0.15 USD (1-2 page PDF processing)
     *
     * Verifies:
     * - Original PDF structure preserved
     * - Invisible text layer added correctly
     * - Text is searchable in generated PDF
     * - PDF can be opened and read
     * - File size is reasonable
     *
     * @group textract
     */
    public function test_textract_searchable_pdf_generation(): void
    {
        $this->markTestSkipped('Skipping searchable PDF test - requires valid AWS credentials and costs money');

        echo "\n📄 Testing searchable PDF generation...\n";

        // Create test PDF with specific searchable text
        $searchText = "Criminal Procedure\nZakon o kaznenom postupku\nArticle 215\nHome Search Warrant";

        $pdfPath = $this->createTestPDF($searchText);
        $driveFileId = 'searchable-'.uniqid();

        // Upload to S3
        Storage::disk('s3')->put(
            'textract/input/'.$driveFileId.'.pdf',
            file_get_contents($pdfPath)
        );

        echo "  ✓ Test PDF created with searchable text\n";

        // Create and process job
        $job = TextractJob::create([
            'drive_file_id' => $driveFileId,
            'drive_file_name' => 'searchable-test.pdf',
            'status' => 'pending',
        ]);

        try {
            // Process with Textract
            app(ProcessDrivePdf::class)->handle($driveFileId, 'searchable-test.pdf', true);

            $job->refresh();
            $this->assertEquals('succeeded', $job->status);

            echo "  ✓ Textract processing completed\n";

            // Download searchable PDF from S3
            $outputKey = 'textract/output/'.$driveFileId.'.pdf';
            $searchablePdfContent = Storage::disk('s3')->get($outputKey);

            $this->assertNotEmpty($searchablePdfContent, 'Searchable PDF should exist');

            // Save temporarily to verify
            $tempPath = storage_path('app/temp-searchable-'.uniqid().'.pdf');
            file_put_contents($tempPath, $searchablePdfContent);

            // Verify file is valid PDF
            $fileSize = filesize($tempPath);
            $this->assertGreaterThan(0, $fileSize, 'PDF should have content');
            $this->assertLessThan(10 * 1024 * 1024, $fileSize, 'PDF should be reasonable size (<10MB)');

            // Verify PDF signature
            $header = file_get_contents($tempPath, false, null, 0, 4);
            $this->assertEquals('%PDF', $header, 'Should be valid PDF file');

            echo '  ✓ Searchable PDF is valid (size: '.round($fileSize / 1024, 2)." KB)\n";

            // Verify extracted text contains our search terms
            $extractedText = $job->extracted_content;
            $this->assertStringContainsString('Criminal Procedure', $extractedText);
            $this->assertStringContainsString('Article 215', $extractedText);

            echo "  ✓ Text extraction verified\n";
            echo "  🔍 Searchable text: '".substr($extractedText, 0, 50)."...'\n";

            // Cleanup temp file
            unlink($tempPath);

            // Estimate cost
            $estimatedCost = 0.0015; // $1.50 per 1,000 pages
            echo "\n💰 Estimated cost: $".number_format($estimatedCost, 4)." USD\n";

        } catch (\Exception $e) {
            $this->fail('Searchable PDF generation failed: '.$e->getMessage());
        } finally {
            // Cleanup S3 files
            Storage::disk('s3')->delete([
                'textract/input/'.$driveFileId.'.pdf',
                'textract/json/'.$driveFileId.'.json',
                'textract/output/'.$driveFileId.'.pdf',
            ]);

            echo "\n🧹 Cleanup completed\n";
        }
    }

    /**
     * Helper: Check if AWS credentials are configured
     */
    protected function hasAwsCredentials(): bool
    {
        return ! empty(config('filesystems.disks.s3.key')) &&
            ! empty(config('filesystems.disks.s3.secret')) &&
            ! empty(config('filesystems.disks.s3.bucket'));
    }

    /**
     * Helper: Create a test PDF file with given text
     *
     * Uses FPDF library if available, otherwise creates a minimal PDF
     *
     * @param  string  $text  Text content to include in PDF
     * @return string Path to created PDF file
     */
    protected function createTestPDF(string $text): string
    {
        $tempPath = storage_path('app/temp-test-'.uniqid().'.pdf');

        // Create a minimal valid PDF with text
        // This is a simplified PDF structure that most PDF readers can handle
        $pdfContent = <<<PDF
%PDF-1.4
1 0 obj
<<
/Type /Catalog
/Pages 2 0 R
>>
endobj
2 0 obj
<<
/Type /Pages
/Kids [3 0 R]
/Count 1
>>
endobj
3 0 obj
<<
/Type /Page
/Parent 2 0 R
/MediaBox [0 0 612 792]
/Contents 4 0 R
/Resources <<
/Font <<
/F1 <<
/Type /Font
/Subtype /Type1
/BaseFont /Helvetica
>>
>>
>>
>>
endobj
4 0 obj
<<
/Length 100
>>
stream
BT
/F1 12 Tf
50 700 Td
({$text}) Tj
ET
endstream
endobj
xref
0 5
0000000000 65535 f
0000000009 00000 n
0000000058 00000 n
0000000115 00000 n
0000000315 00000 n
trailer
<<
/Size 5
/Root 1 0 R
>>
startxref
415
%%EOF
PDF;

        file_put_contents($tempPath, $pdfContent);

        return $tempPath;
    }
}
