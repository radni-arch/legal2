<?php

namespace Tests\Unit\Pipelines\Textract;

use App\Models\TextractJob;
use App\Pipelines\Textract\CreateMetadataStep;
use App\Services\Ocr\LegalDocumentMetadata;
use App\Services\Ocr\LegalMetadataExtractor;
use App\Services\Ocr\OcrDocument;
use App\Services\Ocr\OcrPage;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CreateMetadataStepTest extends TestCase
{
    use UsesTestDatabase;

    protected CreateMetadataStep $step;

    protected $metadataExtractorMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->metadataExtractorMock = Mockery::mock(LegalMetadataExtractor::class);
        $this->step = new CreateMetadataStep($this->metadataExtractorMock);
    }

    protected function tearDown(): void
    {
        try {
            Mockery::close();
        } finally {
            parent::tearDown();
        }
    }

    protected function createOcrDocument(array $pages = []): OcrDocument
    {
        $doc = new OcrDocument;
        $doc->pages = $pages;

        return $doc;
    }

    /** @test */
    public function it_extracts_legal_metadata_from_ocr_document()
    {
        $job = TextractJob::factory()->create(['status' => 'reconstructing']);

        $page = new OcrPage(number: 1, lines: []);
        $ocrDocument = $this->createOcrDocument([$page]);

        $legalMetadata = new LegalDocumentMetadata(
            documentType: 'presuda',
            totalCitations: 5,
            courts: ['VSRH'],
            parties: ['Tužitelj', 'Tuženik'],
        );

        $this->metadataExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->with(
                Mockery::type(OcrDocument::class),
                'file-123',
                'document.pdf'
            )
            ->andReturn($legalMetadata);

        $payload = [
            'job' => $job,
            'ocrDocument' => $ocrDocument,
            'driveFileId' => 'file-123',
            'driveFileName' => 'document.pdf',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('legalMetadata', $result);
        $this->assertEquals($legalMetadata, $result['legalMetadata']);
    }

    /** @test */
    public function it_gracefully_skips_when_ocr_document_not_in_payload()
    {
        $job = TextractJob::factory()->create();

        Log::spy();

        $payload = [
            'job' => $job,
            'driveFileId' => 'file-123',
            'driveFileName' => 'document.pdf',
            // No ocrDocument, no textractText
        ];

        // Should NOT throw - gracefully skip metadata extraction
        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('legalMetadata', $result);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'CreateMetadataStep')
                    && str_contains($message, 'skipping metadata extraction');
            });
    }

    /** @test */
    public function it_updates_job_metadata_when_job_is_present()
    {
        $job = TextractJob::factory()->create(['status' => 'reconstructing']);

        $ocrDocument = $this->createOcrDocument([]);

        $legalMetadata = new LegalDocumentMetadata(
            documentType: 'rješenje',
            totalCitations: 3,
        );

        $this->metadataExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->andReturn($legalMetadata);

        $payload = [
            'job' => $job,
            'ocrDocument' => $ocrDocument,
            'driveFileId' => 'file-123',
            'driveFileName' => 'document.pdf',
        ];

        $this->step->handle($payload, fn ($p) => $p);

        $job->refresh();
        $this->assertEquals('metadata_extracted', $job->status);
        $this->assertNotNull($job->metadata);
    }

    /** @test */
    public function it_works_without_job_in_payload()
    {
        $ocrDocument = $this->createOcrDocument([]);

        $legalMetadata = new LegalDocumentMetadata(
            documentType: 'nalog',
            totalCitations: 1,
        );

        $this->metadataExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->andReturn($legalMetadata);

        $payload = [
            'ocrDocument' => $ocrDocument,
            'driveFileId' => 'file-123',
            'driveFileName' => 'document.pdf',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('legalMetadata', $result);
        $this->assertEquals($legalMetadata, $result['legalMetadata']);
    }

    /** @test */
    public function it_handles_missing_drive_file_info_gracefully()
    {
        $ocrDocument = $this->createOcrDocument([]);

        $legalMetadata = new LegalDocumentMetadata(
            documentType: 'unknown',
        );

        $this->metadataExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->with(
                Mockery::type(OcrDocument::class),
                null,
                null
            )
            ->andReturn($legalMetadata);

        $payload = [
            'ocrDocument' => $ocrDocument,
            // No driveFileId or driveFileName
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('legalMetadata', $result);
    }

    /** @test */
    public function it_passes_all_payload_to_next_step()
    {
        $job = TextractJob::factory()->create();

        $legalMetadata = new LegalDocumentMetadata(
            documentType: 'test',
        );

        $this->metadataExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->andReturn($legalMetadata);

        $payload = [
            'job' => $job,
            'ocrDocument' => $this->createOcrDocument([]),
            'driveFileId' => 'file-id',
            'driveFileName' => 'test.pdf',
            'blocks' => [],
            'qualityMetrics' => ['confidence' => 0.95],
            'customData' => 'preserved',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('customData', $result);
        $this->assertEquals('preserved', $result['customData']);
        $this->assertArrayHasKey('blocks', $result);
        $this->assertArrayHasKey('qualityMetrics', $result);
        $this->assertArrayHasKey('legalMetadata', $result);
    }
}
