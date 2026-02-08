<?php

namespace Tests\Unit\Pipelines\Textract;

use App\Models\LegalCase;
use App\Models\TextractJob;
use App\Pipelines\Textract\PersistReconstructedStep;
use App\Services\CaseIngestPipeline;
use App\Services\Ocr\LegalMetadataExtractor;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class PersistReconstructedStepTest extends TestCase
{
    use UsesTestDatabase;

    protected PersistReconstructedStep $step;

    protected $metadataExtractorMock;

    protected $ingestPipelineMock;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->metadataExtractorMock = Mockery::mock(LegalMetadataExtractor::class);
        $this->ingestPipelineMock = Mockery::mock(CaseIngestPipeline::class);

        $this->step = new PersistReconstructedStep(
            $this->metadataExtractorMock,
            $this->ingestPipelineMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_throws_exception_if_case_id_missing()
    {
        $payload = [
            'driveFileId' => 'file-123',
            'driveFileName' => 'document.pdf',
            'targetLocalPath' => '/tmp/file.pdf',
            // No caseId
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing case selection');

        $this->step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_throws_exception_if_case_not_found()
    {
        $payload = [
            'driveFileId' => 'file-123',
            'driveFileName' => 'document.pdf',
            'targetLocalPath' => '/tmp/file.pdf',
            'caseId' => '99999', // Non-existent case
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Selected case not found');

        $this->step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_creates_case_document_upload()
    {
        $case = LegalCase::factory()->create();

        // Create a temporary file
        Storage::disk('local')->put('temp/test.pdf', 'test content');
        $pdfPath = Storage::disk('local')->path('temp/test.pdf');

        $ocrDocument = (object) [
            'pages' => [
                (object) [
                    'pageNumber' => 1,
                    'lines' => [
                        (object) ['text' => 'Test line 1'],
                        (object) ['text' => 'Test line 2'],
                    ],
                ],
            ],
        ];

        $legalMetadata = Mockery::mock();
        $legalMetadata->shouldReceive('toArray')
            ->andReturn([
                'documentType' => 'presuda',
                'totalCitations' => 5,
            ]);

        $this->ingestPipelineMock
            ->shouldReceive('ingest')
            ->once()
            ->andReturn([
                'status' => 'success',
                'chunk_count' => 3,
                'needs_review' => false,
            ]);

        $payload = [
            'caseId' => (string) $case->id,
            'driveFileId' => 'file-123',
            'driveFileName' => 'document.pdf',
            'targetLocalPath' => $pdfPath,
            'ocrDocument' => $ocrDocument,
            'legalMetadata' => $legalMetadata,
            'blocks' => [],
            'qualityMetrics' => ['confidence' => 0.95],
            'resultsMeta' => ['localJsonAbs' => '/tmp/results.json'],
            's3Key' => 'textract/input/file.pdf',
            'outKey' => 'textract/output/file.pdf',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertDatabaseHas('cases_documents_uploads', [
            'case_id' => $case->id,
            'doc_id' => 'doc-file-123',
            'original_filename' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'status' => 'stored',
        ]);

        $this->assertArrayHasKey('ingestResult', $result);
        $this->assertEquals('success', $result['ingestResult']['status']);
    }

    /** @test */
    public function it_ingests_document_with_correct_parameters()
    {
        $case = LegalCase::factory()->create();

        Storage::disk('local')->put('temp/test.pdf', 'test content');
        $pdfPath = Storage::disk('local')->path('temp/test.pdf');

        $ocrDocument = (object) [
            'pages' => [
                (object) [
                    'pageNumber' => 1,
                    'lines' => [(object) ['text' => 'Content']],
                ],
            ],
        ];

        $blocks = [['BlockType' => 'PAGE']];
        $qualityMetrics = ['confidence' => 0.92];

        $this->ingestPipelineMock
            ->shouldReceive('ingest')
            ->once()
            ->with(
                Mockery::on(fn ($id) => $id == $case->id),
                Mockery::on(fn ($docId) => $docId === 'doc-file-123'),
                Mockery::on(fn ($text) => str_contains($text, 'Content')),
                Mockery::on(fn ($b) => $b === $blocks),
                Mockery::on(function ($options) use ($qualityMetrics) {
                    return isset($options['metadata']['ocr_quality'])
                        && $options['metadata']['ocr_quality'] === $qualityMetrics;
                })
            )
            ->andReturn([
                'status' => 'success',
                'chunk_count' => 2,
                'needs_review' => false,
            ]);

        $payload = [
            'caseId' => (string) $case->id,
            'driveFileId' => 'file-123',
            'driveFileName' => 'document.pdf',
            'targetLocalPath' => $pdfPath,
            'ocrDocument' => $ocrDocument,
            'blocks' => $blocks,
            'qualityMetrics' => $qualityMetrics,
            'resultsMeta' => ['localJsonAbs' => '/tmp/results.json'],
            's3Key' => 'input.pdf',
            'outKey' => 'output.pdf',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);
        // Verify that the ingest method was called with expected parameters
        $this->assertNotNull($result);
    }

    /** @test */
    public function it_passes_all_payload_to_next_step()
    {
        $case = LegalCase::factory()->create();

        Storage::disk('local')->put('temp/test.pdf', 'test content');
        $pdfPath = Storage::disk('local')->path('temp/test.pdf');

        $this->ingestPipelineMock
            ->shouldReceive('ingest')
            ->once()
            ->andReturn(['status' => 'success', 'chunk_count' => 1, 'needs_review' => false]);

        $payload = [
            'caseId' => (string) $case->id,
            'driveFileId' => 'file-123',
            'driveFileName' => 'document.pdf',
            'targetLocalPath' => $pdfPath,
            'ocrDocument' => (object) ['pages' => []],
            'blocks' => [],
            'qualityMetrics' => [],
            'resultsMeta' => ['localJsonAbs' => '/tmp/results.json'],
            's3Key' => 'input.pdf',
            'outKey' => 'output.pdf',
            'customData' => 'preserved',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('customData', $result);
        $this->assertEquals('preserved', $result['customData']);
        $this->assertArrayHasKey('ingestResult', $result);
    }

    /** @test */
    public function it_prefers_final_text_over_ocr_document_extraction()
    {
        $case = LegalCase::factory()->create();

        Storage::disk('local')->put('temp/test.pdf', 'test content');
        $pdfPath = Storage::disk('local')->path('temp/test.pdf');

        $ocrDocument = (object) [
            'pages' => [
                (object) [
                    'pageNumber' => 1,
                    'lines' => [(object) ['text' => 'OCR extracted text']],
                ],
            ],
        ];

        $capturedText = null;
        $this->ingestPipelineMock
            ->shouldReceive('ingest')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::on(function ($text) use (&$capturedText) {
                    $capturedText = $text;

                    return true;
                }),
                Mockery::any(),
                Mockery::any()
            )
            ->andReturn(['status' => 'success', 'chunk_count' => 1, 'needs_review' => false]);

        $payload = [
            'caseId' => (string) $case->id,
            'driveFileId' => 'file-123',
            'driveFileName' => 'document.pdf',
            'targetLocalPath' => $pdfPath,
            'ocrDocument' => $ocrDocument,
            'finalText' => 'Final text from upstream step',
            'blocks' => [],
            'qualityMetrics' => [],
            'resultsMeta' => ['localJsonAbs' => '/tmp/results.json'],
            's3Key' => 'input.pdf',
            'outKey' => 'output.pdf',
        ];

        $this->step->handle($payload, fn ($p) => $p);

        $this->assertEquals('Final text from upstream step', $capturedText);
    }

    /** @test */
    public function it_falls_back_to_ocr_document_when_final_text_missing()
    {
        $case = LegalCase::factory()->create();

        Storage::disk('local')->put('temp/test.pdf', 'test content');
        $pdfPath = Storage::disk('local')->path('temp/test.pdf');

        $ocrDocument = (object) [
            'pages' => [
                (object) [
                    'pageNumber' => 1,
                    'lines' => [(object) ['text' => 'OCR extracted text']],
                ],
            ],
        ];

        $capturedText = null;
        $this->ingestPipelineMock
            ->shouldReceive('ingest')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::on(function ($text) use (&$capturedText) {
                    $capturedText = $text;

                    return true;
                }),
                Mockery::any(),
                Mockery::any()
            )
            ->andReturn(['status' => 'success', 'chunk_count' => 1, 'needs_review' => false]);

        $payload = [
            'caseId' => (string) $case->id,
            'driveFileId' => 'file-123',
            'driveFileName' => 'document.pdf',
            'targetLocalPath' => $pdfPath,
            'ocrDocument' => $ocrDocument,
            // No finalText
            'blocks' => [],
            'qualityMetrics' => [],
            'resultsMeta' => ['localJsonAbs' => '/tmp/results.json'],
            's3Key' => 'input.pdf',
            'outKey' => 'output.pdf',
        ];

        $this->step->handle($payload, fn ($p) => $p);

        $this->assertStringContainsString('OCR extracted text', $capturedText);
    }

    /** @test */
    public function it_updates_textract_job_with_ocr_engine_metadata()
    {
        $case = LegalCase::factory()->create();

        Storage::disk('local')->put('temp/test.pdf', 'test content');
        $pdfPath = Storage::disk('local')->path('temp/test.pdf');

        $job = TextractJob::factory()->create([
            'drive_file_id' => 'file-123',
            'case_id' => $case->id,
            'status' => 'processing',
        ]);

        $this->ingestPipelineMock
            ->shouldReceive('ingest')
            ->once()
            ->andReturn(['status' => 'success', 'chunk_count' => 1, 'needs_review' => false]);

        $ocrRouting = ['strategy' => 'confidence_compare', 'threshold' => 0.85];

        $payload = [
            'caseId' => (string) $case->id,
            'driveFileId' => 'file-123',
            'driveFileName' => 'document.pdf',
            'targetLocalPath' => $pdfPath,
            'ocrDocument' => (object) ['pages' => []],
            'blocks' => [],
            'qualityMetrics' => [],
            'resultsMeta' => ['localJsonAbs' => '/tmp/results.json'],
            's3Key' => 'input.pdf',
            'outKey' => 'output.pdf',
            'job' => $job,
            'ocr_engine_used' => 'tesseract',
            'ocr_routing' => $ocrRouting,
        ];

        $this->step->handle($payload, fn ($p) => $p);

        $job->refresh();
        $this->assertEquals('tesseract', $job->ocr_engine);
        $this->assertEquals($ocrRouting, $job->ocr_routing_metadata);
    }

    /** @test */
    public function it_defaults_ocr_engine_to_textract_when_not_specified()
    {
        $case = LegalCase::factory()->create();

        Storage::disk('local')->put('temp/test.pdf', 'test content');
        $pdfPath = Storage::disk('local')->path('temp/test.pdf');

        $job = TextractJob::factory()->create([
            'drive_file_id' => 'file-123',
            'case_id' => $case->id,
            'status' => 'processing',
        ]);

        $this->ingestPipelineMock
            ->shouldReceive('ingest')
            ->once()
            ->andReturn(['status' => 'success', 'chunk_count' => 1, 'needs_review' => false]);

        $payload = [
            'caseId' => (string) $case->id,
            'driveFileId' => 'file-123',
            'driveFileName' => 'document.pdf',
            'targetLocalPath' => $pdfPath,
            'ocrDocument' => (object) ['pages' => []],
            'blocks' => [],
            'qualityMetrics' => [],
            'resultsMeta' => ['localJsonAbs' => '/tmp/results.json'],
            's3Key' => 'input.pdf',
            'outKey' => 'output.pdf',
            'job' => $job,
            // No ocr_engine_used or ocr_routing
        ];

        $this->step->handle($payload, fn ($p) => $p);

        $job->refresh();
        $this->assertEquals('textract', $job->ocr_engine);
        $this->assertNull($job->ocr_routing_metadata);
    }
}
