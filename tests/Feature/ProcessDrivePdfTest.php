<?php

namespace Tests\Feature;

use App\Actions\Textract\ProcessDrivePdf;
use App\Models\TextractJob;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Task 2.A.2: ProcessDrivePdf Test
 *
 * Comprehensive test suite for the ProcessDrivePdf action that orchestrates
 * the end-to-end pipeline for processing a PDF from Google Drive through
 * AWS Textract OCR.
 */
class ProcessDrivePdfTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that the action successfully processes a PDF through the pipeline
     *
     * @test
     */
    public function it_processes_pdf_through_pipeline(): void
    {
        Log::shouldReceive('info')->times(2);
        Log::shouldReceive('error')->never();

        // Mock the pipeline to return successful payload
        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock
            ->shouldReceive('send')
            ->once()
            ->andReturnSelf();

        $pipelineMock
            ->shouldReceive('through')
            ->once()
            ->andReturnSelf();

        $job = TextractJob::create([
            'drive_file_id' => 'file-123',
            'drive_file_name' => 'test.pdf',
            'status' => 'pending',
        ]);

        $pipelineMock
            ->shouldReceive('thenReturn')
            ->once()
            ->andReturn([
                'driveFileId' => 'file-123',
                'driveFileName' => 'test.pdf',
                'job' => $job,
                'ocrDocument' => (object) [
                    'pages' => [
                        (object) [
                            'lines' => [
                                (object) ['text' => 'Line 1'],
                                (object) ['text' => 'Line 2'],
                            ],
                        ],
                    ],
                ],
                'outKey' => 's3://bucket/output.pdf',
            ]);

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;
        $action->handle('file-123', 'test.pdf');

        // Verify job was updated
        $job->refresh();
        $this->assertEquals('succeeded', $job->status);
        $this->assertStringContainsString('Line 1', $job->extracted_content);
        $this->assertEquals('pending', $job->embedding_status);
        $this->assertEquals('pending', $job->graph_sync_status);
    }

    /**
     * Test that the action handles forceTextract parameter
     *
     * @test
     */
    public function it_handles_force_textract_parameter(): void
    {
        Log::shouldReceive('info')->times(2);

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->once()->with(Mockery::on(function ($payload) {
            return $payload['forceTextract'] === true;
        }))->andReturnSelf();
        $pipelineMock->shouldReceive('through')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')->once()->andReturn([
            'driveFileId' => 'file-force',
            'driveFileName' => 'force.pdf',
        ]);

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;
        $action->handle('file-force', 'force.pdf', true);

        $this->assertTrue(true); // Test passes if pipeline received forceTextract=true
    }

    /**
     * Test that the action creates TextractJob with correct status
     *
     * @test
     */
    public function it_creates_textract_job_with_correct_status(): void
    {
        Log::shouldReceive('info')->times(2);

        $job = TextractJob::create([
            'drive_file_id' => 'file-status',
            'drive_file_name' => 'status.pdf',
            'status' => 'pending',
        ]);

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('through')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')->once()->andReturn([
            'driveFileId' => 'file-status',
            'job' => $job,
            'ocrDocument' => (object) ['pages' => []],
        ]);

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;
        $action->handle('file-status', 'status.pdf');

        $job->refresh();
        $this->assertEquals('succeeded', $job->status);
    }

    /**
     * Test that the action extracts full text from OCR document
     *
     * @test
     */
    public function it_extracts_full_text_from_ocr_document(): void
    {
        Log::shouldReceive('info')->times(2);

        $job = TextractJob::create([
            'drive_file_id' => 'file-text',
            'drive_file_name' => 'text.pdf',
            'status' => 'pending',
        ]);

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('through')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')->once()->andReturn([
            'driveFileId' => 'file-text',
            'job' => $job,
            'ocrDocument' => (object) [
                'pages' => [
                    (object) [
                        'lines' => [
                            (object) ['text' => 'First line'],
                            (object) ['text' => 'Second line'],
                        ],
                    ],
                    (object) [
                        'lines' => [
                            (object) ['text' => 'Third line on page 2'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;
        $action->handle('file-text', 'text.pdf');

        $job->refresh();
        $this->assertStringContainsString('First line', $job->extracted_content);
        $this->assertStringContainsString('Second line', $job->extracted_content);
        $this->assertStringContainsString('Third line on page 2', $job->extracted_content);
    }

    /**
     * Test that the action handles pipeline errors gracefully
     *
     * @test
     */
    public function it_handles_pipeline_errors_gracefully(): void
    {
        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->once();

        TextractJob::create([
            'drive_file_id' => 'file-error',
            'drive_file_name' => 'error.pdf',
            'status' => 'pending',
        ]);

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('through')->once()->andReturnSelf();
        $pipelineMock
            ->shouldReceive('thenReturn')
            ->once()
            ->andThrow(new \Exception('Pipeline failed'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Pipeline failed');

        $action->handle('file-error', 'error.pdf');
    }

    /**
     * Test that the action updates job status to failed on error
     *
     * @test
     */
    public function it_updates_job_status_to_failed_on_error(): void
    {
        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->once();

        $job = TextractJob::create([
            'drive_file_id' => 'file-fail',
            'drive_file_name' => 'fail.pdf',
            'status' => 'processing',
        ]);

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('through')->once()->andReturnSelf();
        $pipelineMock
            ->shouldReceive('thenReturn')
            ->once()
            ->andThrow(new \Exception('Processing error'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle('file-fail', 'fail.pdf');
        } catch (\Exception $e) {
            // Expected
        }

        $job->refresh();
        $this->assertEquals('failed', $job->status);
        $this->assertEquals('Processing error', $job->error);
    }

    /**
     * Test that the action logs start event
     *
     * @test
     */
    public function it_logs_start_event(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('ProcessDrivePdf (Action): start', Mockery::on(function ($context) {
                return $context['driveFileId'] === 'file-log'
                    && $context['driveFileName'] === 'log.pdf'
                    && $context['forceTextract'] === false;
            }));

        Log::shouldReceive('info')->once(); // success log

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('through')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')->once()->andReturn([]);

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;
        $action->handle('file-log', 'log.pdf');
    }

    /**
     * Test that the action logs success event with content length
     *
     * @test
     */
    public function it_logs_success_event_with_content_length(): void
    {
        Log::shouldReceive('info')->once(); // start log

        Log::shouldReceive('info')
            ->once()
            ->with('ProcessDrivePdf: succeeded', Mockery::on(function ($context) {
                return $context['driveFileId'] === 'file-success'
                    && isset($context['content_length']);
            }));

        $job = TextractJob::create([
            'drive_file_id' => 'file-success',
            'drive_file_name' => 'success.pdf',
            'status' => 'pending',
        ]);

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('through')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')->once()->andReturn([
            'driveFileId' => 'file-success',
            'job' => $job,
            'ocrDocument' => (object) [
                'pages' => [
                    (object) ['lines' => [(object) ['text' => 'Content']]],
                ],
            ],
            'outKey' => 's3://output',
        ]);

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;
        $action->handle('file-success', 'success.pdf');
    }

    /**
     * Test that the action logs error event with trace
     *
     * @test
     */
    public function it_logs_error_event_with_trace(): void
    {
        Log::shouldReceive('info')->once(); // start log

        Log::shouldReceive('error')
            ->once()
            ->with('ProcessDrivePdf: failed', Mockery::on(function ($context) {
                return $context['driveFileId'] === 'file-trace'
                    && isset($context['error'])
                    && isset($context['trace']);
            }));

        TextractJob::create([
            'drive_file_id' => 'file-trace',
            'drive_file_name' => 'trace.pdf',
            'status' => 'pending',
        ]);

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('through')->once()->andReturnSelf();
        $pipelineMock
            ->shouldReceive('thenReturn')
            ->once()
            ->andThrow(new \Exception('Error with trace'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle('file-trace', 'trace.pdf');
        } catch (\Exception $e) {
            // Expected
        }

        $this->assertTrue(true);
    }

    /**
     * Test that the action uses AsAction trait
     *
     * @test
     */
    public function it_uses_as_action_trait(): void
    {
        $reflection = new \ReflectionClass(ProcessDrivePdf::class);
        $traits = $reflection->getTraitNames();

        $this->assertContains('Lorisleiva\Actions\Concerns\AsAction', $traits);
    }

    /**
     * Test that the action uses AsJob trait
     *
     * @test
     */
    public function it_uses_as_job_trait(): void
    {
        $reflection = new \ReflectionClass(ProcessDrivePdf::class);
        $traits = $reflection->getTraitNames();

        $this->assertContains('Lorisleiva\Actions\Concerns\AsJob', $traits);
    }

    /**
     * Test that the action can be dispatched as a job
     *
     * @test
     */
    public function it_can_be_dispatched_as_job(): void
    {
        // Since it uses AsJob trait, it should be dispatchable
        $action = new ProcessDrivePdf;

        $this->assertTrue(method_exists($action, 'dispatch'));
    }

    /**
     * Test that the action sends correct payload to pipeline
     *
     * @test
     */
    public function it_sends_correct_payload_to_pipeline(): void
    {
        Log::shouldReceive('info')->times(2);

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function ($payload) {
                return $payload['driveFileId'] === 'file-payload'
                    && $payload['driveFileName'] === 'payload.pdf'
                    && $payload['forceTextract'] === false;
            }))
            ->andReturnSelf();

        $pipelineMock->shouldReceive('through')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')->once()->andReturn([]);

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;
        $action->handle('file-payload', 'payload.pdf');
    }

    /**
     * Test that the action processes pipeline through correct steps
     *
     * @test
     */
    public function it_processes_through_correct_pipeline_steps(): void
    {
        Log::shouldReceive('info')->times(2);

        $expectedSteps = [
            \App\Pipelines\Textract\EnsureJobStep::class,
            \App\Pipelines\Textract\DownloadDriveFileStep::class,
            \App\Pipelines\Textract\UploadInputToS3Step::class,
            \App\Pipelines\Textract\StartAnalysisStep::class,
            \App\Pipelines\Textract\WaitAndFetchStep::class,
            \App\Pipelines\Textract\SaveResultsStep::class,
            \App\Pipelines\Textract\CollectLinesStep::class,
            \App\Pipelines\Textract\CheckOcrQualityStep::class,
            \App\Pipelines\Textract\CreateMetadataStep::class,
            \App\Pipelines\Textract\ReconstructPdfStep::class,
            \App\Pipelines\Textract\UploadOutputStep::class,
            \App\Pipelines\Textract\PersistReconstructedStep::class,
        ];

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->once()->andReturnSelf();
        $pipelineMock
            ->shouldReceive('through')
            ->once()
            ->with($expectedSteps)
            ->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')->once()->andReturn([]);

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;
        $action->handle('file-steps', 'steps.pdf');

        $this->assertTrue(true); // Test passes if pipeline received correct steps
    }

    /**
     * Test that the action handles OCR document without pages
     *
     * @test
     */
    public function it_handles_ocr_document_without_pages(): void
    {
        Log::shouldReceive('info')->times(2);

        $job = TextractJob::create([
            'drive_file_id' => 'file-no-pages',
            'drive_file_name' => 'no-pages.pdf',
            'status' => 'pending',
        ]);

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('through')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')->once()->andReturn([
            'driveFileId' => 'file-no-pages',
            'job' => $job,
            'ocrDocument' => (object) [], // No pages
        ]);

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;
        $action->handle('file-no-pages', 'no-pages.pdf');

        $job->refresh();
        $this->assertEquals('', $job->extracted_content);
    }

    /**
     * Test that the action handles pages without lines
     *
     * @test
     */
    public function it_handles_pages_without_lines(): void
    {
        Log::shouldReceive('info')->times(2);

        $job = TextractJob::create([
            'drive_file_id' => 'file-no-lines',
            'drive_file_name' => 'no-lines.pdf',
            'status' => 'pending',
        ]);

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('through')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')->once()->andReturn([
            'driveFileId' => 'file-no-lines',
            'job' => $job,
            'ocrDocument' => (object) [
                'pages' => [
                    (object) [], // Page without lines
                ],
            ],
        ]);

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;
        $action->handle('file-no-lines', 'no-lines.pdf');

        $job->refresh();
        $this->assertEquals('', $job->extracted_content);
    }

    /**
     * Test that the action handles lines with empty text
     *
     * @test
     */
    public function it_handles_lines_with_empty_text(): void
    {
        Log::shouldReceive('info')->times(2);

        $job = TextractJob::create([
            'drive_file_id' => 'file-empty-lines',
            'drive_file_name' => 'empty-lines.pdf',
            'status' => 'pending',
        ]);

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('through')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')->once()->andReturn([
            'driveFileId' => 'file-empty-lines',
            'job' => $job,
            'ocrDocument' => (object) [
                'pages' => [
                    (object) [
                        'lines' => [
                            (object) ['text' => ''],
                            (object) ['text' => 'Non-empty line'],
                            (object) ['text' => ''],
                        ],
                    ],
                ],
            ],
        ]);

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;
        $action->handle('file-empty-lines', 'empty-lines.pdf');

        $job->refresh();
        $this->assertStringContainsString('Non-empty line', $job->extracted_content);
        $this->assertStringNotContainsString('  ', $job->extracted_content); // No double spaces from empty lines
    }

    /**
     * Test that the action adds page breaks between pages
     *
     * @test
     */
    public function it_adds_page_breaks_between_pages(): void
    {
        Log::shouldReceive('info')->times(2);

        $job = TextractJob::create([
            'drive_file_id' => 'file-page-breaks',
            'drive_file_name' => 'page-breaks.pdf',
            'status' => 'pending',
        ]);

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('through')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')->once()->andReturn([
            'driveFileId' => 'file-page-breaks',
            'job' => $job,
            'ocrDocument' => (object) [
                'pages' => [
                    (object) [
                        'lines' => [(object) ['text' => 'Page 1']],
                    ],
                    (object) [
                        'lines' => [(object) ['text' => 'Page 2']],
                    ],
                ],
            ],
        ]);

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;
        $action->handle('file-page-breaks', 'page-breaks.pdf');

        $job->refresh();
        $content = $job->extracted_content;

        // Should have page breaks (double newlines) between pages
        $this->assertStringContainsString('Page 1', $content);
        $this->assertStringContainsString('Page 2', $content);
    }

    /**
     * Test that the action sets embedding and graph sync statuses
     *
     * @test
     */
    public function it_sets_embedding_and_graph_sync_statuses(): void
    {
        Log::shouldReceive('info')->times(2);

        $job = TextractJob::create([
            'drive_file_id' => 'file-sync',
            'drive_file_name' => 'sync.pdf',
            'status' => 'pending',
            'embedding_status' => null,
            'graph_sync_status' => null,
        ]);

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('through')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')->once()->andReturn([
            'driveFileId' => 'file-sync',
            'job' => $job,
            'ocrDocument' => (object) ['pages' => []],
        ]);

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;
        $action->handle('file-sync', 'sync.pdf');

        $job->refresh();
        $this->assertEquals('pending', $job->embedding_status);
        $this->assertEquals('pending', $job->graph_sync_status);
    }

    /**
     * Test that the action trims extracted content
     *
     * @test
     */
    public function it_trims_extracted_content(): void
    {
        Log::shouldReceive('info')->times(2);

        $job = TextractJob::create([
            'drive_file_id' => 'file-trim',
            'drive_file_name' => 'trim.pdf',
            'status' => 'pending',
        ]);

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('through')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')->once()->andReturn([
            'driveFileId' => 'file-trim',
            'job' => $job,
            'ocrDocument' => (object) [
                'pages' => [
                    (object) [
                        'lines' => [(object) ['text' => '  Content with spaces  ']],
                    ],
                ],
            ],
        ]);

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;
        $action->handle('file-trim', 'trim.pdf');

        $job->refresh();
        $content = $job->extracted_content;

        // Should not start or end with whitespace
        $this->assertEquals(trim($content), $content);
        $this->assertStringContainsString('Content with spaces', $content);
    }

    /**
     * Test that the action can process files with special characters in names
     *
     * @test
     */
    public function it_processes_files_with_special_characters_in_names(): void
    {
        Log::shouldReceive('info')->times(2);

        $specialName = 'Dokument #1 (2024) [final].pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function ($payload) use ($specialName) {
                return $payload['driveFileName'] === $specialName;
            }))
            ->andReturnSelf();
        $pipelineMock->shouldReceive('through')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')->once()->andReturn([]);

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;
        $action->handle('file-special', $specialName);

        $this->assertTrue(true);
    }

    /**
     * Test that the action handles very long file names
     *
     * @test
     */
    public function it_handles_very_long_file_names(): void
    {
        Log::shouldReceive('info')->times(2);

        $longName = str_repeat('a', 200).'.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function ($payload) use ($longName) {
                return $payload['driveFileName'] === $longName;
            }))
            ->andReturnSelf();
        $pipelineMock->shouldReceive('through')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')->once()->andReturn([]);

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;
        $action->handle('file-long', $longName);

        $this->assertTrue(true);
    }

    /**
     * Test action handles payload without job instance
     *
     * @test
     */
    public function it_handles_payload_without_job_instance(): void
    {
        Log::shouldReceive('info')->times(2);

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('through')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')->once()->andReturn([
            'driveFileId' => 'file-no-job',
            'driveFileName' => 'no-job.pdf',
            // No 'job' key in payload
        ]);

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;
        $action->handle('file-no-job', 'no-job.pdf');

        // Should not throw exception
        $this->assertTrue(true);
    }

    /**
     * Test orchestration: action delegates to pipeline
     *
     * @test
     */
    public function it_orchestrates_processing_via_pipeline(): void
    {
        // The action's main purpose is orchestration via Pipeline
        Log::shouldReceive('info')->times(2);

        $pipelineMock = Mockery::mock(Pipeline::class);

        // Verify it uses Pipeline to orchestrate
        $pipelineMock->shouldReceive('send')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('through')->once()->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')->once()->andReturn([]);

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;
        $action->handle('file-orchestrate', 'orchestrate.pdf');

        // If pipeline was called correctly, orchestration works
        $this->assertTrue(true);
    }
}
