<?php

namespace Tests\Unit\Pipelines\Textract;

use App\Models\TextractJob;
use App\Pipelines\Textract\CreateMetadataStep;
use App\Services\Ocr\LegalDocumentMetadata;
use App\Services\Ocr\LegalMetadataExtractor;
use App\Services\Ocr\OcrDocument;
use App\Services\Ocr\OcrLine;
use App\Services\Ocr\OcrPage;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Tests for CreateMetadataStep resilience when ocrDocument is missing.
 * Covers the skip_textract flow where LocalOcrRouteStep provides textractText
 * but no ocrDocument.
 */
class CreateMetadataStepResilienceTest extends TestCase
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

    // -------------------------------------------------------------------------
    // Regression: ocrDocument IS present (existing Textract flow)
    // -------------------------------------------------------------------------

    /** @test */
    public function it_works_when_ocr_document_is_present(): void
    {
        $job = TextractJob::factory()->create(['status' => 'reconstructing']);

        $ocrDocument = new OcrDocument;
        $ocrDocument->pages = [new OcrPage(number: 1, lines: [])];

        $expectedMetadata = new LegalDocumentMetadata(
            documentType: 'presuda',
            totalCitations: 3,
        );

        $this->metadataExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->with(
                Mockery::type(OcrDocument::class),
                'file-123',
                'document.pdf'
            )
            ->andReturn($expectedMetadata);

        $payload = [
            'job' => $job,
            'ocrDocument' => $ocrDocument,
            'driveFileId' => 'file-123',
            'driveFileName' => 'document.pdf',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('legalMetadata', $result);
        $this->assertEquals($expectedMetadata, $result['legalMetadata']);
        $this->assertEquals('presuda', $result['legalMetadata']->documentType);
    }

    // -------------------------------------------------------------------------
    // Resilience: ocrDocument MISSING, textractText IS present (skip_textract flow)
    // -------------------------------------------------------------------------

    /** @test */
    public function it_falls_back_to_textract_text_when_ocr_document_missing(): void
    {
        $job = TextractJob::factory()->create(['status' => 'processing_local_ocr']);

        $sampleText = "REPUBLIKA HRVATSKA\nVRHOVNI SUD\nPresuda br. Rev-123/2024";

        $expectedMetadata = new LegalDocumentMetadata(
            documentType: 'presuda',
            totalCitations: 1,
            courts: ['VSRH'],
        );

        // The extractor should be called with extractFromText when ocrDocument is missing
        $this->metadataExtractorMock
            ->shouldReceive('extractFromText')
            ->once()
            ->with(
                $sampleText,
                'file-456',
                'local-ocr.pdf'
            )
            ->andReturn($expectedMetadata);

        $payload = [
            'job' => $job,
            'textractText' => $sampleText,
            'driveFileId' => 'file-456',
            'driveFileName' => 'local-ocr.pdf',
            'skip_textract' => true,
            // No ocrDocument key
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('legalMetadata', $result);
        $this->assertEquals($expectedMetadata, $result['legalMetadata']);
        $this->assertEquals('presuda', $result['legalMetadata']->documentType);
    }

    /** @test */
    public function it_updates_job_status_when_using_text_fallback(): void
    {
        $job = TextractJob::factory()->create(['status' => 'processing_local_ocr']);

        $expectedMetadata = new LegalDocumentMetadata(
            documentType: 'rjesenje',
            totalCitations: 2,
        );

        $this->metadataExtractorMock
            ->shouldReceive('extractFromText')
            ->once()
            ->andReturn($expectedMetadata);

        $payload = [
            'job' => $job,
            'textractText' => 'Some legal text content here.',
            'driveFileId' => 'file-789',
            'driveFileName' => 'test.pdf',
        ];

        $this->step->handle($payload, fn ($p) => $p);

        $job->refresh();
        $this->assertEquals('metadata_extracted', $job->status);
        $this->assertNotNull($job->metadata);
    }

    // -------------------------------------------------------------------------
    // Resilience: BOTH ocrDocument AND textractText missing
    // -------------------------------------------------------------------------

    /** @test */
    public function it_gracefully_skips_when_both_missing_and_logs_warning(): void
    {
        $job = TextractJob::factory()->create(['status' => 'processing']);

        Log::spy();

        // Extractor should NOT be called at all
        $this->metadataExtractorMock
            ->shouldNotReceive('extract');
        $this->metadataExtractorMock
            ->shouldNotReceive('extractFromText');

        $payload = [
            'job' => $job,
            'driveFileId' => 'file-000',
            'driveFileName' => 'empty.pdf',
            // No ocrDocument, no textractText
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        // Pipeline should continue without crashing
        $this->assertIsArray($result);
        $this->assertArrayHasKey('driveFileId', $result);

        // Verify warning was logged
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'CreateMetadataStep')
                    && str_contains($message, 'skipping metadata extraction');
            });
    }

    /** @test */
    public function it_does_not_set_legal_metadata_when_both_missing(): void
    {
        $job = TextractJob::factory()->create(['status' => 'processing']);

        Log::spy();

        $this->metadataExtractorMock->shouldNotReceive('extract');
        $this->metadataExtractorMock->shouldNotReceive('extractFromText');

        $payload = [
            'job' => $job,
            'driveFileId' => 'file-000',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayNotHasKey('legalMetadata', $result);
    }

    /** @test */
    public function it_skips_when_textract_text_is_empty_string(): void
    {
        $job = TextractJob::factory()->create(['status' => 'processing']);

        Log::spy();

        $this->metadataExtractorMock->shouldNotReceive('extract');
        $this->metadataExtractorMock->shouldNotReceive('extractFromText');

        $payload = [
            'job' => $job,
            'textractText' => '',
            'driveFileId' => 'file-000',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayNotHasKey('legalMetadata', $result);
    }

    // -------------------------------------------------------------------------
    // Pipeline continuation: next step is always called
    // -------------------------------------------------------------------------

    /** @test */
    public function it_always_passes_payload_to_next_step(): void
    {
        Log::spy();

        $payload = [
            'driveFileId' => 'file-xyz',
            'driveFileName' => 'test.pdf',
            'customData' => 'preserved',
            'blocks' => [],
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('customData', $result);
        $this->assertEquals('preserved', $result['customData']);
        $this->assertArrayHasKey('blocks', $result);
    }

    /** @test */
    public function it_does_not_throw_runtime_exception_when_ocr_document_missing(): void
    {
        Log::spy();

        $payload = [
            'driveFileId' => 'file-no-throw',
        ];

        // Should NOT throw - this is the whole point of this story
        $result = $this->step->handle($payload, fn ($p) => $p);
        $this->assertIsArray($result);
    }

    // -------------------------------------------------------------------------
    // OcrDocument::fromPlainText() factory method tests
    // -------------------------------------------------------------------------

    /** @test */
    public function it_creates_ocr_document_from_plain_text(): void
    {
        $text = "Line one\nLine two\nLine three";

        $document = OcrDocument::fromPlainText($text);

        $this->assertInstanceOf(OcrDocument::class, $document);
        $this->assertCount(1, $document->pages);
        $this->assertEquals(1, $document->pages[0]->number);
        $this->assertCount(3, $document->pages[0]->lines);
        $this->assertEquals('Line one', $document->pages[0]->lines[0]->text);
        $this->assertEquals('Line two', $document->pages[0]->lines[1]->text);
        $this->assertEquals('Line three', $document->pages[0]->lines[2]->text);
    }

    /** @test */
    public function it_creates_multi_page_document_from_text_with_form_feeds(): void
    {
        $text = "Page 1 line 1\nPage 1 line 2\fPage 2 line 1\nPage 2 line 2\fPage 3 line 1";

        $document = OcrDocument::fromPlainText($text);

        $this->assertInstanceOf(OcrDocument::class, $document);
        $this->assertCount(3, $document->pages);

        $this->assertEquals(1, $document->pages[0]->number);
        $this->assertCount(2, $document->pages[0]->lines);
        $this->assertEquals('Page 1 line 1', $document->pages[0]->lines[0]->text);

        $this->assertEquals(2, $document->pages[1]->number);
        $this->assertCount(2, $document->pages[1]->lines);
        $this->assertEquals('Page 2 line 1', $document->pages[1]->lines[0]->text);

        $this->assertEquals(3, $document->pages[2]->number);
        $this->assertCount(1, $document->pages[2]->lines);
        $this->assertEquals('Page 3 line 1', $document->pages[2]->lines[0]->text);
    }

    /** @test */
    public function it_creates_empty_document_from_empty_text(): void
    {
        $document = OcrDocument::fromPlainText('');

        $this->assertInstanceOf(OcrDocument::class, $document);
        $this->assertCount(0, $document->pages);
    }

    /** @test */
    public function it_creates_ocr_lines_with_zero_confidence(): void
    {
        $text = 'Test line';
        $document = OcrDocument::fromPlainText($text);

        $line = $document->pages[0]->lines[0];
        $this->assertInstanceOf(OcrLine::class, $line);
        $this->assertEquals(0.0, $line->confidence);
        $this->assertEquals(0.0, $line->left);
        $this->assertEquals('Test line', $line->text);
    }

    /** @test */
    public function it_filters_empty_lines_in_from_plain_text(): void
    {
        $text = "Line one\n\n\nLine two\n\nLine three";

        $document = OcrDocument::fromPlainText($text);

        // Empty lines should be filtered out
        $this->assertCount(3, $document->pages[0]->lines);
        $this->assertEquals('Line one', $document->pages[0]->lines[0]->text);
        $this->assertEquals('Line two', $document->pages[0]->lines[1]->text);
        $this->assertEquals('Line three', $document->pages[0]->lines[2]->text);
    }

    // -------------------------------------------------------------------------
    // LegalMetadataExtractor::extractFromText() tests
    // -------------------------------------------------------------------------

    /** @test */
    public function it_extracts_metadata_from_plain_text_via_extractor(): void
    {
        // This tests the real extractFromText method (not mocked)
        // We need to verify it creates an OcrDocument and calls extract()
        $realExtractorMock = Mockery::mock(LegalMetadataExtractor::class)->makePartial();

        $expectedMetadata = new LegalDocumentMetadata(
            documentType: 'presuda',
            totalCitations: 2,
        );

        $realExtractorMock
            ->shouldReceive('extract')
            ->once()
            ->with(
                Mockery::on(function ($doc) {
                    return $doc instanceof OcrDocument
                        && count($doc->pages) === 1
                        && count($doc->pages[0]->lines) === 2;
                }),
                'file-abc',
                'test.pdf'
            )
            ->andReturn($expectedMetadata);

        $result = $realExtractorMock->extractFromText(
            "Line one content\nLine two content",
            'file-abc',
            'test.pdf'
        );

        $this->assertEquals($expectedMetadata, $result);
    }
}
