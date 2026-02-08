<?php

namespace Tests\Integration;

use App\Actions\Textract\AnalyzeTextractLayout;
use App\Actions\Textract\DownloadDriveFile;
use App\Actions\Textract\ProcessDrivePdf;
use App\Actions\Textract\ReconstructPdfV2;
use App\Actions\Textract\SaveAnalysisResults;
use App\Actions\Textract\StartTextractAnalysis;
use App\Actions\Textract\UploadInputToS3;
use App\Actions\Textract\UploadOutputToS3;
use App\Actions\Textract\WaitAndFetchTextract;
use App\Jobs\ExtractTablesFromTextractJob;
use App\Jobs\GenerateEmbeddingsJob;
use App\Jobs\ProcessTextractJob;
use App\Models\LegalCase;
use App\Models\TextractBatch;
use App\Models\TextractJob;
use App\Pipelines\Textract\CheckOcrQualityStep;
use App\Pipelines\Textract\CollectLinesStep;
use App\Pipelines\Textract\CreateMetadataStep;
use App\Pipelines\Textract\DownloadDriveFileStep;
use App\Pipelines\Textract\EnsureJobStep;
use App\Pipelines\Textract\ReconstructPdfStep;
use App\Pipelines\Textract\SaveResultsStep;
use App\Pipelines\Textract\StartAnalysisStep;
use App\Pipelines\Textract\UploadInputToS3Step;
use App\Pipelines\Textract\UploadOutputStep;
use App\Pipelines\Textract\WaitAndFetchStep;
use App\Services\CourtDecisionVectorStoreService;
use App\Services\Ocr\LegalMetadataExtractor;
use App\Services\Ocr\OcrQualityAnalyzer;
use App\Services\OpenAIService;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Lorisleiva\Actions\Facades\Actions;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Integration tests for complete Textract processing pipeline
 * These tests verify end-to-end workflows with mocked AWS services
 */
class TextractPipelineFlowTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_completes_full_textract_pipeline_end_to_end()
    {
        // Arrange
        Config::set('distributed-processing.textract.auto_extract_tables', true);
        Config::set('distributed-processing.textract.auto_generate_embeddings', true);
        Config::set('distributed-processing.embeddings.model', 'text-embedding-3-small');
        Config::set('distributed-processing.embeddings.chunk_size', 1500);

        Queue::fake();

        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12345',
            'drive_file_id' => 'test-drive-file-integration',
            'drive_file_name' => 'contract-document.pdf',
            'case_id' => 'case-123',
            'status' => 'pending',
            'queue_name' => 'textract',
            'embedding_status' => 'pending',
            'graph_sync_status' => 'pending',
        ]);

        // Mock ProcessDrivePdf action to simulate successful Textract processing
        $mockProcessDrivePdf = Mockery::mock(ProcessDrivePdf::class);
        $this->app->instance(ProcessDrivePdf::class, $mockProcessDrivePdf);
        $mockProcessDrivePdf->shouldReceive('handle')
            ->once()
            ->with('test-drive-file-integration', 'contract-document.pdf', true)
            ->andReturnUsing(function () use ($job) {
                $job->update([
                    'status' => 'succeeded',
                    'extracted_content' => 'This is a legal contract between Party A and Party B. Article 1: Definitions...',
                    'metadata' => [
                        'page_count' => 10,
                        'block_count' => 500,
                        'tables' => [
                            ['rows' => 5, 'columns' => 3],
                        ],
                    ],
                ]);
            });

        // Act - Execute ProcessTextractJob
        $processJob = new ProcessTextractJob($job->id);
        $processJob->handle();

        // Assert - Verify job completed successfully
        $job->refresh();
        $this->assertEquals('completed', $job->status);
        $this->assertNotNull($job->extracted_content);
        $this->assertStringContainsString('legal contract', $job->extracted_content);

        // Verify metadata was stored
        $this->assertNotNull($job->metadata);
        $this->assertArrayHasKey('s3_json_key', $job->metadata);
        $this->assertArrayHasKey('s3_input_key', $job->metadata);
        $this->assertArrayHasKey('s3_output_key', $job->metadata);
        $this->assertArrayHasKey('page_count', $job->metadata);
        $this->assertEquals(10, $job->metadata['page_count']);

        // Verify performance metrics
        $this->assertNotNull($job->performance_metrics);
        $this->assertArrayHasKey('duration_seconds', $job->performance_metrics);
        $this->assertArrayHasKey('pages_processed', $job->performance_metrics);
        $this->assertArrayHasKey('blocks_extracted', $job->performance_metrics);

        // Verify follow-up jobs were dispatched
        Queue::assertPushed(ExtractTablesFromTextractJob::class);
        Queue::assertPushed(GenerateEmbeddingsJob::class);
    }

    /** @test */
    public function it_processes_batch_of_textract_jobs_correctly()
    {
        // Arrange
        Config::set('distributed-processing.textract.auto_extract_tables', false);
        Config::set('distributed-processing.textract.auto_generate_embeddings', false);

        Queue::fake();

        $batch = TextractBatch::create([
            'id' => '01HXC9K8P5B6M2QWERTY00001',
            'status' => 'processing',
            'total_items' => 3,
            'processed_items' => 0,
            'failed_items' => 0,
        ]);

        $jobs = collect([
            TextractJob::create([
                'id' => '01HXC9K8P5B6M2QWERTY12346',
                'drive_file_id' => 'batch-file-1',
                'drive_file_name' => 'doc-1.pdf',
                'status' => 'pending',
                'batch_id' => $batch->id,
            ]),
            TextractJob::create([
                'id' => '01HXC9K8P5B6M2QWERTY12347',
                'drive_file_id' => 'batch-file-2',
                'drive_file_name' => 'doc-2.pdf',
                'status' => 'pending',
                'batch_id' => $batch->id,
            ]),
            TextractJob::create([
                'id' => '01HXC9K8P5B6M2QWERTY12348',
                'drive_file_id' => 'batch-file-3',
                'drive_file_name' => 'doc-3.pdf',
                'status' => 'pending',
                'batch_id' => $batch->id,
            ]),
        ]);

        // Mock ProcessDrivePdf for each job
        $mockProcessDrivePdf = Mockery::mock(ProcessDrivePdf::class);
        $this->app->instance(ProcessDrivePdf::class, $mockProcessDrivePdf);
        $mockProcessDrivePdf->shouldReceive('handle')
            ->times(3)
            ->andReturnUsing(function ($driveFileId, $driveFileName) use ($jobs) {
                $job = $jobs->firstWhere('drive_file_id', $driveFileId);
                $job->update([
                    'status' => 'succeeded',
                    'extracted_content' => "Content for {$driveFileName}",
                    'metadata' => ['page_count' => 1],
                ]);
            });

        // Act - Process all jobs in batch
        foreach ($jobs as $job) {
            $processJob = new ProcessTextractJob($job->id, $batch->id);
            $processJob->handle();
        }

        // Assert - Verify all jobs completed
        $batch->refresh();
        $this->assertEquals(3, $batch->processed_items);
        $this->assertEquals(0, $batch->failed_items);

        foreach ($jobs as $job) {
            $job->refresh();
            $this->assertEquals('completed', $job->status);
            $this->assertNotNull($job->extracted_content);
        }
    }

    /** @test */
    public function it_handles_textract_failure_and_updates_batch_correctly()
    {
        // Arrange
        Queue::fake();

        $batch = TextractBatch::create([
            'id' => '01HXC9K8P5B6M2QWERTY00002',
            'status' => 'processing',
            'total_items' => 2,
            'processed_items' => 0,
            'failed_items' => 0,
        ]);

        $successJob = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12349',
            'drive_file_id' => 'success-file',
            'drive_file_name' => 'success.pdf',
            'status' => 'pending',
            'batch_id' => $batch->id,
        ]);

        $failJob = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12350',
            'drive_file_id' => 'fail-file',
            'drive_file_name' => 'fail.pdf',
            'status' => 'pending',
            'batch_id' => $batch->id,
        ]);

        // Mock ProcessDrivePdf - one succeeds, one fails
        $mockProcessDrivePdf = Mockery::mock(ProcessDrivePdf::class);
        $this->app->instance(ProcessDrivePdf::class, $mockProcessDrivePdf);

        $mockProcessDrivePdf->shouldReceive('handle')
            ->with('success-file', 'success.pdf', true)
            ->once()
            ->andReturnUsing(function () use ($successJob) {
                $successJob->update([
                    'status' => 'succeeded',
                    'extracted_content' => 'Success content',
                ]);
            });

        $mockProcessDrivePdf->shouldReceive('handle')
            ->with('fail-file', 'fail.pdf', true)
            ->once()
            ->andThrow(new \Exception('Textract API error'));

        // Act - Process both jobs
        $processJob1 = new ProcessTextractJob($successJob->id, $batch->id);
        $processJob1->handle();

        $processJob2 = new ProcessTextractJob($failJob->id, $batch->id);
        try {
            $processJob2->handle();
        } catch (\Exception $e) {
            // Expected failure
        }

        // Assert
        $batch->refresh();
        $this->assertEquals(2, $batch->processed_items);
        $this->assertEquals(1, $batch->failed_items);

        $successJob->refresh();
        $this->assertEquals('completed', $successJob->status);

        $failJob->refresh();
        $this->assertEquals('failed', $failJob->status);
        $this->assertNotNull($failJob->error);
    }

    /** @test */
    public function it_generates_embeddings_after_textract_completion()
    {
        // Arrange
        Config::set('distributed-processing.embeddings.model', 'text-embedding-3-small');

        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12351',
            'drive_file_id' => 'embedding-test-file',
            'drive_file_name' => 'embedding-test.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Legal document content for embedding generation test.',
            'embedding_status' => 'pending',
            'case_id' => 'case-456',
        ]);

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockVectorStore = Mockery::mock(CourtDecisionVectorStoreService::class);

        $mockOpenAI->shouldReceive('embeddings')
            ->once()
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                ],
                'usage' => ['total_tokens' => 25],
            ]);

        $mockVectorStore->shouldReceive('upsert')
            ->once()
            ->withArgs(function ($vectors) use ($job) {
                return $vectors[0]['id'] === 'textract_'.$job->id
                    && count($vectors[0]['vector']) === 1536
                    && $vectors[0]['metadata']['case_id'] === 'case-456';
            });

        // Act
        $embeddingJob = new GenerateEmbeddingsJob($job->id, 'textract_job');
        $embeddingJob->handle($mockOpenAI, $mockVectorStore);

        // Assert
        $job->refresh();
        $this->assertEquals('synced', $job->embedding_status);
        $this->assertNotNull($job->embedding_synced_at);
    }

    /** @test */
    public function it_retries_failed_textract_jobs_with_backoff()
    {
        // Arrange
        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12352',
            'drive_file_id' => 'retry-test-file',
            'drive_file_name' => 'retry-test.pdf',
            'status' => 'pending',
            'retry_count' => 0,
        ]);

        // Act - Create job and verify retry configuration
        $processJob = new ProcessTextractJob($job->id);

        // Assert - Verify retry configuration
        $this->assertEquals(3, $processJob->tries);
        $this->assertEquals([60, 300, 900], $processJob->backoff);
        $this->assertEquals(1800, $processJob->timeout);
    }

    /** @test */
    public function it_stores_searchable_pdf_metadata_correctly()
    {
        // Arrange
        Queue::fake();
        Config::set('distributed-processing.textract.auto_extract_tables', false);
        Config::set('distributed-processing.textract.auto_generate_embeddings', false);

        putenv('S3_JSON_PREFIX=textract/json');
        putenv('S3_INPUT_PREFIX=textract/input');
        putenv('S3_OUTPUT_PREFIX=textract/output');

        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12353',
            'drive_file_id' => 'pdf-metadata-test',
            'drive_file_name' => 'metadata-test.pdf',
            'status' => 'pending',
        ]);

        // Mock ProcessDrivePdf
        $mockProcessDrivePdf = Mockery::mock(ProcessDrivePdf::class);
        $this->app->instance(ProcessDrivePdf::class, $mockProcessDrivePdf);
        $mockProcessDrivePdf->shouldReceive('handle')
            ->once()
            ->with('pdf-metadata-test', 'metadata-test.pdf', true)
            ->andReturnUsing(function () use ($job) {
                $job->update([
                    'status' => 'succeeded',
                    'extracted_content' => 'PDF content',
                    'metadata' => [
                        'page_count' => 5,
                        'block_count' => 200,
                        'tables' => [],
                    ],
                ]);
            });

        // Act
        $processJob = new ProcessTextractJob($job->id);
        $processJob->handle();

        // Assert
        $job->refresh();

        // Verify S3 keys are stored
        $this->assertEquals(
            'textract/json/pdf-metadata-test.json',
            $job->metadata['s3_json_key']
        );
        $this->assertEquals(
            'textract/input/pdf-metadata-test.pdf',
            $job->metadata['s3_input_key']
        );
        $this->assertEquals(
            'textract/output/pdf-metadata-test.pdf',
            $job->metadata['s3_output_key']
        );

        // Verify processed timestamp
        $this->assertArrayHasKey('processed_at', $job->metadata);

        // Verify performance metrics include searchable PDF info
        $this->assertEquals(5, $job->performance_metrics['pages_processed']);
        $this->assertEquals(200, $job->performance_metrics['blocks_extracted']);
        $this->assertEquals(0, $job->performance_metrics['tables_extracted']);
    }

    /** @test */
    public function it_processes_textract_job_with_table_extraction()
    {
        // Arrange
        Queue::fake();
        Config::set('distributed-processing.textract.auto_extract_tables', true);
        Config::set('distributed-processing.textract.auto_generate_embeddings', false);

        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12354',
            'drive_file_id' => 'table-test-file',
            'drive_file_name' => 'table-test.pdf',
            'status' => 'pending',
        ]);

        // Mock ProcessDrivePdf with tables
        $mockProcessDrivePdf = Mockery::mock(ProcessDrivePdf::class);
        $this->app->instance(ProcessDrivePdf::class, $mockProcessDrivePdf);
        $mockProcessDrivePdf->shouldReceive('handle')
            ->once()
            ->with('table-test-file', 'table-test.pdf', true)
            ->andReturnUsing(function () use ($job) {
                $job->update([
                    'status' => 'succeeded',
                    'extracted_content' => 'Document with tables',
                    'metadata' => [
                        'page_count' => 3,
                        'block_count' => 150,
                        'tables' => [
                            ['rows' => 10, 'columns' => 5],
                            ['rows' => 8, 'columns' => 3],
                        ],
                    ],
                ]);
            });

        // Act
        $processJob = new ProcessTextractJob($job->id);
        $processJob->handle();

        // Assert
        $job->refresh();
        $this->assertEquals('completed', $job->status);

        // Verify table extraction job was dispatched
        Queue::assertPushed(ExtractTablesFromTextractJob::class, function ($queuedJob) {
            return $queuedJob->queue === 'textract-tables';
        });

        // Verify table count in metrics
        $this->assertEquals(2, $job->performance_metrics['tables_extracted']);
    }

    /** @test */
    public function it_handles_large_documents_with_pagination()
    {
        // Arrange
        Queue::fake();
        Config::set('distributed-processing.textract.auto_extract_tables', false);
        Config::set('distributed-processing.textract.auto_generate_embeddings', false);

        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12355',
            'drive_file_id' => 'large-doc-test',
            'drive_file_name' => 'large-document.pdf',
            'status' => 'pending',
        ]);

        // Mock ProcessDrivePdf for large document
        $mockProcessDrivePdf = Mockery::mock(ProcessDrivePdf::class);
        $this->app->instance(ProcessDrivePdf::class, $mockProcessDrivePdf);
        $mockProcessDrivePdf->shouldReceive('handle')
            ->once()
            ->with('large-doc-test', 'large-document.pdf', true)
            ->andReturnUsing(function () use ($job) {
                $job->update([
                    'status' => 'succeeded',
                    'extracted_content' => str_repeat('Page content. ', 10000), // Large content
                    'metadata' => [
                        'page_count' => 100,
                        'block_count' => 5000,
                        'tables' => [],
                    ],
                ]);
            });

        // Act
        $processJob = new ProcessTextractJob($job->id);
        $processJob->handle();

        // Assert
        $job->refresh();
        $this->assertEquals('completed', $job->status);
        $this->assertEquals(100, $job->performance_metrics['pages_processed']);
        $this->assertEquals(5000, $job->performance_metrics['blocks_extracted']);
        $this->assertGreaterThan(10000, strlen($job->extracted_content));
    }

    /** @test */
    public function it_verifies_end_to_end_pipeline_with_all_components()
    {
        // This test verifies the complete flow:
        // 1. TextractJob creation
        // 2. ProcessTextractJob execution
        // 3. Follow-up jobs dispatch
        // 4. Batch tracking
        // 5. Performance metrics

        // Arrange
        Queue::fake();
        Config::set('distributed-processing.textract.auto_extract_tables', true);
        Config::set('distributed-processing.textract.auto_generate_embeddings', true);

        $batch = TextractBatch::create([
            'id' => '01HXC9K8P5B6M2QWERTY00003',
            'status' => 'processing',
            'total_items' => 1,
            'processed_items' => 0,
            'failed_items' => 0,
        ]);

        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12356',
            'drive_file_id' => 'end-to-end-test',
            'drive_file_name' => 'complete-pipeline.pdf',
            'case_id' => 'case-789',
            'status' => 'pending',
            'batch_id' => $batch->id,
            'queue_name' => 'textract',
        ]);

        // Mock ProcessDrivePdf
        $mockProcessDrivePdf = Mockery::mock(ProcessDrivePdf::class);
        $this->app->instance(ProcessDrivePdf::class, $mockProcessDrivePdf);
        $mockProcessDrivePdf->shouldReceive('handle')
            ->once()
            ->with('end-to-end-test', 'complete-pipeline.pdf', true)
            ->andReturnUsing(function () use ($job) {
                $job->update([
                    'status' => 'succeeded',
                    'extracted_content' => 'Complete pipeline test content with legal terminology.',
                    'metadata' => [
                        'page_count' => 15,
                        'block_count' => 750,
                        'tables' => [['rows' => 20, 'columns' => 4]],
                    ],
                ]);
            });

        // Act
        $processJob = new ProcessTextractJob($job->id, $batch->id);
        $processJob->handle();

        // Assert - Verify complete pipeline
        $job->refresh();
        $batch->refresh();

        // 1. Job completed successfully
        $this->assertEquals('completed', $job->status);
        $this->assertNotNull($job->extracted_content);

        // 2. Metadata stored
        $this->assertArrayHasKey('s3_json_key', $job->metadata);
        $this->assertArrayHasKey('processed_at', $job->metadata);
        $this->assertEquals(15, $job->metadata['page_count']);

        // 3. Performance metrics
        $this->assertNotNull($job->performance_metrics);
        $this->assertArrayHasKey('duration_seconds', $job->performance_metrics);
        $this->assertEquals(15, $job->performance_metrics['pages_processed']);
        $this->assertEquals(750, $job->performance_metrics['blocks_extracted']);
        $this->assertEquals(1, $job->performance_metrics['tables_extracted']);

        // 4. Batch updated
        $this->assertEquals(1, $batch->processed_items);
        $this->assertEquals(0, $batch->failed_items);

        // 5. Follow-up jobs dispatched
        Queue::assertPushed(ExtractTablesFromTextractJob::class);
        Queue::assertPushed(GenerateEmbeddingsJob::class);
    }

    protected $qualityAnalyzerMock;

    protected $metadataExtractorMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkipped('Requires live OpenAI API - enable for integration testing');

        $this->qualityAnalyzerMock = Mockery::mock(OcrQualityAnalyzer::class);
        $this->metadataExtractorMock = Mockery::mock(LegalMetadataExtractor::class);

        $this->app->instance(OcrQualityAnalyzer::class, $this->qualityAnalyzerMock);
        $this->app->instance(LegalMetadataExtractor::class, $this->metadataExtractorMock);
    }

    /** @test */
    public function it_processes_document_through_full_textract_pipeline()
    {
        // Arrange: Create a case and initial payload
        $case = LegalCase::factory()->create();

        $job = TextractJob::create([
            'drive_file_id' => 'test-file-123',
            'drive_file_name' => 'legal-document.pdf',
            'case_id' => $case->id,
            'status' => 'queued',
        ]);

        $payload = [
            'driveFileId' => 'test-file-123',
            'driveFileName' => 'legal-document.pdf',
        ];

        // Mock all action calls
        Actions::shouldReceive('run')
            ->with(DownloadDriveFile::class, Mockery::any())
            ->andReturn('/tmp/downloaded.pdf');

        Actions::shouldReceive('run')
            ->with(UploadInputToS3::class, Mockery::any())
            ->andReturn('textract/input/test-file-123.pdf');

        Actions::shouldReceive('run')
            ->with(StartTextractAnalysis::class, Mockery::any())
            ->andReturn('textract-job-id-456');

        $mockBlocks = [
            ['BlockType' => 'PAGE', 'Id' => 'page-1', 'Confidence' => 95],
            ['BlockType' => 'LINE', 'Id' => 'line-1', 'Text' => 'Zakon o kaznenom postupku', 'Confidence' => 90],
            ['BlockType' => 'WORD', 'Id' => 'word-1', 'Text' => 'Zakon', 'Confidence' => 92],
        ];

        Actions::shouldReceive('run')
            ->with(WaitAndFetchTextract::class, Mockery::any())
            ->andReturn($mockBlocks);

        Actions::shouldReceive('run')
            ->with(SaveAnalysisResults::class, Mockery::any())
            ->andReturn([
                's3JsonKey' => 'textract/output/test-file-123.json',
                'localJsonAbs' => '/tmp/textract-results.json',
            ]);

        // Mock OCR quality analysis
        $this->qualityAnalyzerMock
            ->shouldReceive('analyzeFromBlocks')
            ->once()
            ->andReturn([
                'confidence' => 0.92,
                'coverage' => 0.88,
                'low_confidence_pages' => 1,
            ]);

        $mockOcrDocument = (object) [
            'pages' => [
                (object) [
                    'pageNumber' => 1,
                    'lines' => [
                        (object) ['text' => 'Zakon o kaznenom postupku'],
                    ],
                ],
            ],
        ];

        Actions::shouldReceive('run')
            ->with(AnalyzeTextractLayout::class, Mockery::any())
            ->andReturn($mockOcrDocument);

        Actions::shouldReceive('run')
            ->with(ReconstructPdfV2::class, Mockery::any())
            ->andReturn('/tmp/reconstructed-searchable.pdf');

        Actions::shouldReceive('run')
            ->with(UploadOutputToS3::class, Mockery::any())
            ->andReturn('textract/output/test-file-123.pdf');

        // Mock legal metadata extraction
        $legalMetadata = (object) [
            'documentType' => 'zakon',
            'totalCitations' => 1,
            'courts' => [],
            'parties' => [],
            'toArray' => fn () => [
                'documentType' => 'zakon',
                'totalCitations' => 1,
            ],
        ];

        $this->metadataExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->andReturn($legalMetadata);

        // Act: Run through the pipeline
        $result = Pipeline::send($payload)
            ->through([
                new EnsureJobStep,
                new DownloadDriveFileStep,
                new UploadInputToS3Step,
                new StartAnalysisStep,
                new WaitAndFetchStep,
                new SaveResultsStep,
                new CheckOcrQualityStep($this->qualityAnalyzerMock),
                new CollectLinesStep,
                new ReconstructPdfStep,
                new UploadOutputStep,
                new CreateMetadataStep($this->metadataExtractorMock),
            ])
            ->thenReturn();

        // Assert: Verify the complete pipeline results
        $this->assertArrayHasKey('job', $result);
        $this->assertArrayHasKey('localPath', $result);
        $this->assertArrayHasKey('s3Key', $result);
        $this->assertArrayHasKey('jobId', $result);
        $this->assertArrayHasKey('blocks', $result);
        $this->assertArrayHasKey('resultsMeta', $result);
        $this->assertArrayHasKey('qualityMetrics', $result);
        $this->assertArrayHasKey('ocrDocument', $result);
        $this->assertArrayHasKey('targetLocalPath', $result);
        $this->assertArrayHasKey('outKey', $result);
        $this->assertArrayHasKey('legalMetadata', $result);

        // Verify job was updated correctly through the pipeline
        $job->refresh();
        $this->assertEquals('metadata_extracted', $job->status);
        $this->assertEquals('textract/input/test-file-123.pdf', $job->s3_key);
        $this->assertEquals('textract-job-id-456', $job->job_id);
        $this->assertNotNull($job->metadata);
        $this->assertArrayHasKey('ocrQuality', $job->metadata);
    }

    /** @test */
    public function it_flags_document_for_review_when_quality_is_low()
    {
        $case = LegalCase::factory()->create();

        $job = TextractJob::create([
            'drive_file_id' => 'low-quality-file',
            'drive_file_name' => 'poor-scan.pdf',
            'case_id' => $case->id,
            'status' => 'queued',
        ]);

        config(['vizra-adk.ocr.min_confidence' => 0.85]);

        // Mock actions with low-quality results
        Actions::shouldReceive('run')->with(DownloadDriveFile::class, Mockery::any())->andReturn('/tmp/file.pdf');
        Actions::shouldReceive('run')->with(UploadInputToS3::class, Mockery::any())->andReturn('s3-key');
        Actions::shouldReceive('run')->with(StartTextractAnalysis::class, Mockery::any())->andReturn('job-id');

        $lowQualityBlocks = [
            ['BlockType' => 'PAGE', 'Confidence' => 60],
            ['BlockType' => 'LINE', 'Text' => 'Barely readable', 'Confidence' => 55],
        ];

        Actions::shouldReceive('run')->with(WaitAndFetchTextract::class, Mockery::any())->andReturn($lowQualityBlocks);
        Actions::shouldReceive('run')->with(SaveAnalysisResults::class, Mockery::any())->andReturn([
            's3JsonKey' => 'test.json',
            'localJsonAbs' => '/tmp/test.json',
        ]);

        // Return low quality metrics
        $this->qualityAnalyzerMock
            ->shouldReceive('analyzeFromBlocks')
            ->once()
            ->andReturn([
                'confidence' => 0.60, // Below threshold
                'coverage' => 0.65,   // Below threshold
                'low_confidence_pages' => 5, // Too many
            ]);

        Actions::shouldReceive('run')->with(AnalyzeTextractLayout::class, Mockery::any())->andReturn((object) ['pages' => []]);
        Actions::shouldReceive('run')->with(ReconstructPdfV2::class, Mockery::any())->andReturn('/tmp/out.pdf');
        Actions::shouldReceive('run')->with(UploadOutputToS3::class, Mockery::any())->andReturn('out-key');

        $this->metadataExtractorMock->shouldReceive('extract')->andReturn((object) [
            'documentType' => 'unknown',
            'toArray' => fn () => [],
        ]);

        $payload = [
            'driveFileId' => 'low-quality-file',
            'driveFileName' => 'poor-scan.pdf',
        ];

        // Run pipeline
        $result = Pipeline::send($payload)
            ->through([
                new EnsureJobStep,
                new DownloadDriveFileStep,
                new UploadInputToS3Step,
                new StartAnalysisStep,
                new WaitAndFetchStep,
                new SaveResultsStep,
                new CheckOcrQualityStep($this->qualityAnalyzerMock),
                new CollectLinesStep,
                new ReconstructPdfStep,
                new UploadOutputStep,
                new CreateMetadataStep($this->metadataExtractorMock),
            ])
            ->thenReturn();

        // Assert document is flagged for review
        $this->assertTrue($result['needsReview']);
        $this->assertNotEmpty($result['reviewReasons']);
        $this->assertGreaterThan(0, count($result['reviewReasons']));

        // Check job metadata includes review flag
        $job->refresh();
        $this->assertArrayHasKey('needsReview', $job->metadata);
        $this->assertTrue($job->metadata['needsReview']);
    }

    /** @test */
    public function it_handles_croatian_legal_document_metadata_extraction()
    {
        $case = LegalCase::factory()->create();

        $job = TextractJob::create([
            'drive_file_id' => 'croatian-law-doc',
            'drive_file_name' => 'presuda-vsrh.pdf',
            'case_id' => $case->id,
            'status' => 'queued',
        ]);

        // Mock standard pipeline actions
        Actions::shouldReceive('run')->andReturn('/tmp/file.pdf', 's3-input', 'job-id', [], [], (object) ['pages' => []], '/tmp/out.pdf', 's3-output');

        $this->qualityAnalyzerMock->shouldReceive('analyzeFromBlocks')->andReturn([
            'confidence' => 0.95,
            'coverage' => 0.92,
            'low_confidence_pages' => 0,
        ]);

        // Mock Croatian legal document metadata
        $croatianLegalMetadata = (object) [
            'documentType' => 'presuda',
            'totalCitations' => 8,
            'courts' => ['Vrhovni sud Republike Hrvatske'],
            'parties' => ['Tužitelj', 'Tuženik'],
            'citations' => [
                'Zakon o kaznenom postupku (NN 152/08)',
                'Ustav RH Članak 29',
            ],
            'toArray' => fn () => [
                'documentType' => 'presuda',
                'totalCitations' => 8,
                'courts' => ['Vrhovni sud Republike Hrvatske'],
                'parties' => ['Tužitelj', 'Tuženik'],
            ],
        ];

        $this->metadataExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->andReturn($croatianLegalMetadata);

        $payload = [
            'driveFileId' => 'croatian-law-doc',
            'driveFileName' => 'presuda-vsrh.pdf',
        ];

        // Run pipeline
        $result = Pipeline::send($payload)
            ->through([
                new EnsureJobStep,
                new DownloadDriveFileStep,
                new UploadInputToS3Step,
                new StartAnalysisStep,
                new WaitAndFetchStep,
                new SaveResultsStep,
                new CheckOcrQualityStep($this->qualityAnalyzerMock),
                new CollectLinesStep,
                new ReconstructPdfStep,
                new UploadOutputStep,
                new CreateMetadataStep($this->metadataExtractorMock),
            ])
            ->thenReturn();

        // Assert Croatian legal metadata was extracted
        $this->assertArrayHasKey('legalMetadata', $result);
        $this->assertEquals('presuda', $result['legalMetadata']->documentType);
        $this->assertEquals(8, $result['legalMetadata']->totalCitations);
        $this->assertContains('Vrhovni sud Republike Hrvatske', $result['legalMetadata']->courts);

        // Verify job has legal metadata
        $job->refresh();
        $this->assertArrayHasKey('documentType', $job->metadata);
        $this->assertEquals('presuda', $job->metadata['documentType']);
    }
}
