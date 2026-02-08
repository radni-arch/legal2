<?php

namespace Tests\Unit;

use App\Models\TextractJob;
use App\Testing\TextractMockDataGenerator;
use Tests\TestCase;

/**
 * Tests for TextractMockDataGenerator
 *
 * Validates that the mock data generator produces valid, realistic test data
 */
class TextractMockDataGeneratorTest extends TestCase
{
    protected TextractMockDataGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new TextractMockDataGenerator;
    }

    /**
     * Test basic TextractJob creation
     *
     * @test
     */
    public function it_creates_basic_textract_job(): void
    {
        $job = $this->generator->createTextractJob();

        $this->assertInstanceOf(TextractJob::class, $job);
        $this->assertEquals('pending', $job->status);
        $this->assertNotEmpty($job->drive_file_id);
        $this->assertNotEmpty($job->drive_file_name);
        $this->assertStringEndsWith('.pdf', $job->drive_file_name);
    }

    /**
     * Test TextractJob creation with overrides
     *
     * @test
     */
    public function it_creates_job_with_overrides(): void
    {
        $job = $this->generator->createTextractJob([
            'status' => 'succeeded',
            'drive_file_name' => 'custom_file.pdf',
        ]);

        $this->assertEquals('succeeded', $job->status);
        $this->assertEquals('custom_file.pdf', $job->drive_file_name);
    }

    /**
     * Test succeeded job creation
     *
     * @test
     */
    public function it_creates_succeeded_job(): void
    {
        $job = $this->generator->createSucceededJob(10);

        $this->assertEquals('succeeded', $job->status);
        $this->assertNotEmpty($job->extracted_content);
        $this->assertNotEmpty($job->s3_key);
        $this->assertNotEmpty($job->job_id);
        $this->assertIsArray($job->metadata);
        $this->assertStringContainsString('textract-input/', $job->s3_key);
    }

    /**
     * Test failed job creation
     *
     * @test
     */
    public function it_creates_failed_job(): void
    {
        $errorMessage = 'Test error message';
        $job = $this->generator->createFailedJob($errorMessage);

        $this->assertEquals('failed', $job->status);
        $this->assertEquals($errorMessage, $job->error);
        $this->assertNotEmpty($job->s3_key);
    }

    /**
     * Test processing job creation
     *
     * @test
     */
    public function it_creates_processing_job(): void
    {
        $job = $this->generator->createProcessingJob();

        $this->assertEquals('processing', $job->status);
        $this->assertNotEmpty($job->s3_key);
        $this->assertNotEmpty($job->job_id);
        $this->assertNotNull($job->processing_started_at);
        $this->assertNotEmpty($job->worker_id);
    }

    /**
     * Test edited job creation
     *
     * @test
     */
    public function it_creates_edited_job(): void
    {
        $userId = 123;
        $job = $this->generator->createEditedJob($userId);

        $this->assertEquals('succeeded', $job->status);
        $this->assertTrue($job->manually_edited);
        $this->assertEquals($userId, $job->edited_by);
        $this->assertNotEmpty($job->extracted_content);
        $this->assertNotEmpty($job->manual_content);
        $this->assertNotNull($job->content_edited_at);
        $this->assertEquals('pending', $job->embedding_status);
        $this->assertEquals('pending', $job->graph_sync_status);
    }

    /**
     * Test OCR document creation
     *
     * @test
     */
    public function it_creates_ocr_document(): void
    {
        $pageCount = 5;
        $linesPerPage = 30;
        $doc = $this->generator->createOcrDocument($pageCount, $linesPerPage);

        $this->assertIsObject($doc);
        $this->assertIsArray($doc->pages);
        $this->assertCount($pageCount, $doc->pages);

        // Check first page structure
        $firstPage = $doc->pages[0];
        $this->assertEquals(1, $firstPage->pageNumber);
        $this->assertCount($linesPerPage, $firstPage->lines);
        $this->assertIsArray($firstPage->blocks);

        // Check first line structure
        $firstLine = $firstPage->lines[0];
        $this->assertIsString($firstLine->text);
        $this->assertIsFloat($firstLine->confidence);
        $this->assertGreaterThan(0, $firstLine->confidence);
        $this->assertLessThanOrEqual(100, $firstLine->confidence);
        $this->assertIsObject($firstLine->geometry);
    }

    /**
     * Test OCR document without blocks
     *
     * @test
     */
    public function it_creates_ocr_document_without_blocks(): void
    {
        $doc = $this->generator->createOcrDocument(3, 20, false);

        $this->assertCount(3, $doc->pages);
        foreach ($doc->pages as $page) {
            $this->assertObjectNotHasProperty('blocks', $page);
        }
    }

    /**
     * Test OCR page creation
     *
     * @test
     */
    public function it_creates_ocr_page(): void
    {
        $page = $this->generator->createOcrPage(1, 25, true);

        $this->assertEquals(1, $page->pageNumber);
        $this->assertCount(25, $page->lines);
        $this->assertIsArray($page->blocks);
        $this->assertCount(25, $page->blocks);
        $this->assertEquals(8.5, $page->width);
        $this->assertEquals(11.0, $page->height);
    }

    /**
     * Test Textract API response creation
     *
     * @test
     */
    public function it_creates_textract_api_response(): void
    {
        $response = $this->generator->createTextractApiResponse('SUCCEEDED', 5);

        $this->assertEquals('SUCCEEDED', $response['JobStatus']);
        $this->assertEquals(5, $response['DocumentMetadata']['Pages']);
        $this->assertArrayHasKey('Blocks', $response);
        $this->assertIsArray($response['Blocks']);
        $this->assertNotEmpty($response['Blocks']);
    }

    /**
     * Test failed Textract API response
     *
     * @test
     */
    public function it_creates_failed_textract_api_response(): void
    {
        $response = $this->generator->createTextractApiResponse('FAILED', 0);

        $this->assertEquals('FAILED', $response['JobStatus']);
        $this->assertArrayHasKey('StatusMessage', $response);
        $this->assertArrayNotHasKey('Blocks', $response);
    }

    /**
     * Test start job response creation
     *
     * @test
     */
    public function it_creates_start_job_response(): void
    {
        $response = $this->generator->createStartJobResponse();

        $this->assertArrayHasKey('JobId', $response);
        $this->assertNotEmpty($response['JobId']);
    }

    /**
     * Test get result response creation
     *
     * @test
     */
    public function it_creates_get_result_response(): void
    {
        $response = $this->generator->createGetResultResponse(10);

        $this->assertEquals('SUCCEEDED', $response['JobStatus']);
        $this->assertEquals(10, $response['DocumentMetadata']['Pages']);
        $this->assertArrayHasKey('Blocks', $response);
        $this->assertArrayHasKey('DetectDocumentTextModelVersion', $response);
    }

    /**
     * Test Google Drive file list creation
     *
     * @test
     */
    public function it_creates_drive_file_list(): void
    {
        $fileCount = 10;
        $files = $this->generator->createDriveFileList($fileCount);

        $this->assertIsArray($files);
        $this->assertCount($fileCount, $files);

        // Check first file structure
        $firstFile = $files[0];
        $this->assertArrayHasKey('id', $firstFile);
        $this->assertArrayHasKey('name', $firstFile);
        $this->assertArrayHasKey('mimeType', $firstFile);
        $this->assertArrayHasKey('size', $firstFile);
        $this->assertArrayHasKey('createdTime', $firstFile);
        $this->assertArrayHasKey('modifiedTime', $firstFile);
        $this->assertEquals('application/pdf', $firstFile['mimeType']);
    }

    /**
     * Test single Google Drive file creation
     *
     * @test
     */
    public function it_creates_single_drive_file(): void
    {
        $file = $this->generator->createDriveFile();

        $this->assertIsArray($file);
        $this->assertNotEmpty($file['id']);
        $this->assertStringEndsWith('.pdf', $file['name']);
        $this->assertEquals('application/pdf', $file['mimeType']);
        $this->assertGreaterThan(0, $file['size']);
    }

    /**
     * Test drive file creation with overrides
     *
     * @test
     */
    public function it_creates_drive_file_with_overrides(): void
    {
        $file = $this->generator->createDriveFile([
            'name' => 'custom.pdf',
            'size' => 123456,
        ]);

        $this->assertEquals('custom.pdf', $file['name']);
        $this->assertEquals(123456, $file['size']);
    }

    /**
     * Test pipeline payload creation
     *
     * @test
     */
    public function it_creates_pipeline_payload(): void
    {
        $payload = $this->generator->createPipelinePayload();

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('driveFileId', $payload);
        $this->assertArrayHasKey('driveFileName', $payload);
        $this->assertArrayHasKey('forceTextract', $payload);
        $this->assertFalse($payload['forceTextract']);
    }

    /**
     * Test pipeline payload with overrides
     *
     * @test
     */
    public function it_creates_pipeline_payload_with_overrides(): void
    {
        $payload = $this->generator->createPipelinePayload([
            'forceTextract' => true,
            'customField' => 'value',
        ]);

        $this->assertTrue($payload['forceTextract']);
        $this->assertEquals('value', $payload['customField']);
    }

    /**
     * Test complete pipeline result creation
     *
     * @test
     */
    public function it_creates_pipeline_result(): void
    {
        $result = $this->generator->createPipelineResult(5);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('job', $result);
        $this->assertArrayHasKey('ocrDocument', $result);
        $this->assertArrayHasKey('s3Key', $result);
        $this->assertArrayHasKey('outKey', $result);
        $this->assertArrayHasKey('jobId', $result);

        $this->assertInstanceOf(TextractJob::class, $result['job']);
        $this->assertIsObject($result['ocrDocument']);
        $this->assertCount(5, $result['ocrDocument']->pages);
    }

    /**
     * Test batch job creation
     *
     * @test
     */
    public function it_creates_job_batch(): void
    {
        $count = 10;
        $jobs = $this->generator->createJobBatch($count, 'succeeded');

        $this->assertIsArray($jobs);
        $this->assertCount($count, $jobs);

        foreach ($jobs as $job) {
            $this->assertInstanceOf(TextractJob::class, $job);
            $this->assertEquals('succeeded', $job->status);
        }
    }

    /**
     * Test large OCR document creation
     *
     * @test
     */
    public function it_creates_large_ocr_document(): void
    {
        $pageCount = 100;
        $linesPerPage = 50;
        $doc = $this->generator->createLargeOcrDocument($pageCount, $linesPerPage);

        $this->assertCount($pageCount, $doc->pages);
        $this->assertCount($linesPerPage, $doc->pages[0]->lines);

        // Large documents shouldn't have blocks for performance
        $this->assertObjectNotHasProperty('blocks', $doc->pages[0]);
    }

    /**
     * Test low confidence OCR document creation
     *
     * @test
     */
    public function it_creates_low_confidence_ocr_document(): void
    {
        $doc = $this->generator->createLowConfidenceOcrDocument(3);

        $this->assertCount(3, $doc->pages);

        // Check that confidence scores are low
        foreach ($doc->pages as $page) {
            foreach ($page->lines as $line) {
                $this->assertLessThan(80, $line->confidence);
                $this->assertGreaterThan(30, $line->confidence);
            }
        }
    }

    /**
     * Test OCR document with custom content
     *
     * @test
     */
    public function it_creates_ocr_document_with_custom_content(): void
    {
        $customText = [
            ['Line 1 of page 1', 'Line 2 of page 1'],
            ['Line 1 of page 2', 'Line 2 of page 2'],
        ];

        $doc = $this->generator->createOcrDocumentWithContent($customText);

        $this->assertCount(2, $doc->pages);
        $this->assertEquals('Line 1 of page 1', $doc->pages[0]->lines[0]->text);
        $this->assertEquals('Line 2 of page 1', $doc->pages[0]->lines[1]->text);
        $this->assertEquals('Line 1 of page 2', $doc->pages[1]->lines[0]->text);
        $this->assertEquals('Line 2 of page 2', $doc->pages[1]->lines[1]->text);
    }

    /**
     * Test Textract error response creation
     *
     * @test
     */
    public function it_creates_textract_error_response(): void
    {
        $response = $this->generator->createTextractErrorResponse(
            'ThrottlingException',
            'Rate limit exceeded'
        );

        $this->assertArrayHasKey('Error', $response);
        $this->assertEquals('ThrottlingException', $response['Error']['Code']);
        $this->assertEquals('Rate limit exceeded', $response['Error']['Message']);
        $this->assertEquals('FAILED', $response['JobStatus']);
        $this->assertEquals('Rate limit exceeded', $response['StatusMessage']);
    }

    /**
     * Test performance metrics creation
     *
     * @test
     */
    public function it_creates_performance_metrics(): void
    {
        $metrics = $this->generator->createPerformanceMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('download_time', $metrics);
        $this->assertArrayHasKey('upload_time', $metrics);
        $this->assertArrayHasKey('textract_time', $metrics);
        $this->assertArrayHasKey('total_time', $metrics);
        $this->assertArrayHasKey('file_size', $metrics);
        $this->assertArrayHasKey('page_count', $metrics);
        $this->assertArrayHasKey('memory_peak', $metrics);
        $this->assertArrayHasKey('cpu_time', $metrics);

        // All metrics should be positive numbers
        foreach ($metrics as $key => $value) {
            $this->assertGreaterThan(0, $value, "Metric {$key} should be positive");
        }
    }

    /**
     * Test that generated IDs are unique
     *
     * @test
     */
    public function it_generates_unique_identifiers(): void
    {
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $job = $this->generator->createTextractJob();
            $ids[] = $job->drive_file_id;
        }

        $uniqueIds = array_unique($ids);
        $this->assertCount(100, $uniqueIds, 'All generated IDs should be unique');
    }

    /**
     * Test that geometry data is valid
     *
     * @test
     */
    public function it_generates_valid_geometry_data(): void
    {
        $page = $this->generator->createOcrPage(1, 10);

        foreach ($page->lines as $line) {
            $box = $line->geometry->boundingBox;

            // All coordinates should be between 0 and 1
            $this->assertGreaterThanOrEqual(0, $box->width);
            $this->assertLessThanOrEqual(1, $box->width);
            $this->assertGreaterThanOrEqual(0, $box->height);
            $this->assertLessThanOrEqual(1, $box->height);
            $this->assertGreaterThanOrEqual(0, $box->left);
            $this->assertLessThanOrEqual(1, $box->left);
            $this->assertGreaterThanOrEqual(0, $box->top);
            $this->assertLessThanOrEqual(1, $box->top);

            // Polygon should have 4 points
            $this->assertCount(4, $line->geometry->polygon);
        }
    }

    /**
     * Test that confidence scores are realistic
     *
     * @test
     */
    public function it_generates_realistic_confidence_scores(): void
    {
        $doc = $this->generator->createOcrDocument(5, 30);

        foreach ($doc->pages as $page) {
            foreach ($page->lines as $line) {
                // High confidence OCR should be 85-99%
                $this->assertGreaterThanOrEqual(85, $line->confidence);
                $this->assertLessThanOrEqual(99, $line->confidence);
            }
        }
    }

    /**
     * Test that file names are realistic
     *
     * @test
     */
    public function it_generates_realistic_file_names(): void
    {
        $validPrefixes = ['Case', 'Document', 'Evidence', 'Exhibit', 'Motion', 'Brief', 'Complaint', 'Ruling'];

        for ($i = 0; $i < 20; $i++) {
            $job = $this->generator->createTextractJob();
            $fileName = $job->drive_file_name;

            $this->assertStringEndsWith('.pdf', $fileName);

            $hasValidPrefix = false;
            foreach ($validPrefixes as $prefix) {
                if (str_starts_with($fileName, $prefix)) {
                    $hasValidPrefix = true;
                    break;
                }
            }

            $this->assertTrue($hasValidPrefix, "File name '{$fileName}' should have valid legal document prefix");
        }
    }

    /**
     * Test that legal content contains legal terminology
     *
     * @test
     */
    public function it_generates_realistic_legal_content(): void
    {
        $job = $this->generator->createSucceededJob(5);
        $content = $job->extracted_content;

        $this->assertNotEmpty($content);

        // Should contain some legal terminology
        $legalTerms = ['COURT', 'pursuant', 'plaintiff', 'defendant', 'ORDERED', 'WHEREAS', 'Case', 'filed'];
        $foundTerms = 0;

        foreach ($legalTerms as $term) {
            if (str_contains($content, $term)) {
                $foundTerms++;
            }
        }

        $this->assertGreaterThan(0, $foundTerms, 'Content should contain legal terminology');
    }
}
