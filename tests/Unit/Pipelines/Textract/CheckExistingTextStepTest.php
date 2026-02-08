<?php

namespace Tests\Unit\Pipelines\Textract;

use App\Contracts\Ocr\PdfTextExtractorInterface;
use App\Models\TextractJob;
use App\Pipelines\Textract\CheckExistingTextStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CheckExistingTextStepTest extends TestCase
{
    use RefreshDatabase;

    private string $testPdfPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a temporary test PDF path
        $this->testPdfPath = sys_get_temp_dir() . '/test_' . uniqid() . '.pdf';
        // Create an empty file so is_file() returns true
        touch($this->testPdfPath);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testPdfPath)) {
            @unlink($this->testPdfPath);
        }
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_skips_textract_for_high_text_coverage(): void
    {
        // 800 words over 2 pages = 400 words/page
        // Coverage = 400/50 = 8.0, capped at 1.0 = 100%
        $extractor = $this->createMockExtractor(
            text: str_repeat("This is a test sentence with many words. ", 100), // ~800 words
            pageCount: 2
        );

        $step = new CheckExistingTextStep($extractor);
        $job = TextractJob::factory()->create(['status' => 'pending']);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'localPath' => $this->testPdfPath,
            'job' => $job,
            'forceTextract' => false,
        ];

        $resultPayload = null;
        $step->handle($payload, function ($p) use (&$resultPayload) {
            $resultPayload = $p;
            return $p;
        });

        $this->assertTrue($resultPayload['skip_textract'] ?? false);
        $this->assertEquals('ocrmypdf_skip_text', $resultPayload['ocr_route']);
        $this->assertArrayHasKey('existing_text_coverage', $resultPayload);
        $this->assertGreaterThanOrEqual(0.8, $resultPayload['existing_text_coverage']);
    }

    /** @test */
    public function it_proceeds_to_textract_for_low_text_coverage(): void
    {
        // 4 words over 10 pages = 0.4 words/page
        // Coverage = 0.4/50 = 0.008 = 0.8%
        $extractor = $this->createMockExtractor(
            text: "Page header\nPage footer", // Very few words (4 words)
            pageCount: 10
        );

        $step = new CheckExistingTextStep($extractor);
        $job = TextractJob::factory()->create(['status' => 'pending']);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'localPath' => $this->testPdfPath,
            'job' => $job,
            'forceTextract' => false,
        ];

        $resultPayload = null;
        $step->handle($payload, function ($p) use (&$resultPayload) {
            $resultPayload = $p;
            return $p;
        });

        $this->assertFalse($resultPayload['skip_textract'] ?? true);
        $this->assertEquals('textract', $resultPayload['ocr_route']);
        $this->assertArrayHasKey('existing_text_coverage', $resultPayload);
        $this->assertLessThan(0.8, $resultPayload['existing_text_coverage']);
    }

    /** @test */
    public function it_uses_configurable_min_words_per_page_skip_threshold(): void
    {
        // Set min_words_per_page_skip to 25 (lower threshold)
        config(['ocr.min_words_per_page_skip' => 25]);

        // 300 words over 10 pages = 30 words/page
        // 30 >= 25, so should skip Textract
        $extractor = $this->createMockExtractor(
            text: str_repeat("Word ", 300), // 300 words
            pageCount: 10
        );

        $step = new CheckExistingTextStep($extractor);
        $job = TextractJob::factory()->create(['status' => 'pending']);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'localPath' => $this->testPdfPath,
            'job' => $job,
            'forceTextract' => false,
        ];

        $resultPayload = null;
        $step->handle($payload, function ($p) use (&$resultPayload) {
            $resultPayload = $p;
            return $p;
        });

        // With 30 words per page and min_words_per_page_skip=25, 30 >= 25 so skip
        $this->assertTrue($resultPayload['skip_textract'] ?? false);
        $this->assertEquals('ocrmypdf_skip_text', $resultPayload['ocr_route']);
    }

    /** @test */
    public function it_does_not_skip_when_words_per_page_below_min_threshold(): void
    {
        // Set min_words_per_page_skip to 100 (high threshold)
        config(['ocr.min_words_per_page_skip' => 100]);

        // 300 words over 10 pages = 30 words/page
        // 30 < 100, so should NOT skip Textract
        $extractor = $this->createMockExtractor(
            text: str_repeat("Word ", 300), // 300 words
            pageCount: 10
        );

        $step = new CheckExistingTextStep($extractor);
        $job = TextractJob::factory()->create(['status' => 'pending']);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'localPath' => $this->testPdfPath,
            'job' => $job,
            'forceTextract' => false,
        ];

        $resultPayload = null;
        $step->handle($payload, function ($p) use (&$resultPayload) {
            $resultPayload = $p;
            return $p;
        });

        // With 30 words per page and min_words_per_page_skip=100, 30 < 100 so don't skip
        $this->assertFalse($resultPayload['skip_textract'] ?? true);
        $this->assertEquals('textract', $resultPayload['ocr_route']);
    }

    /** @test */
    public function it_uses_default_50_words_per_page_threshold_from_ocr_config(): void
    {
        // Do NOT set any custom threshold - default from config/ocr.php should be 50
        // 250 words over 10 pages = 25 words/page
        // 25 < 50, so should NOT skip Textract
        $extractor = $this->createMockExtractor(
            text: str_repeat("Word ", 250), // 250 words
            pageCount: 10
        );

        $step = new CheckExistingTextStep($extractor);
        $job = TextractJob::factory()->create(['status' => 'pending']);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'localPath' => $this->testPdfPath,
            'job' => $job,
            'forceTextract' => false,
        ];

        $resultPayload = null;
        $step->handle($payload, function ($p) use (&$resultPayload) {
            $resultPayload = $p;
            return $p;
        });

        // 25 words/page < 50 default threshold, so should NOT skip
        $this->assertFalse($resultPayload['skip_textract'] ?? true);
        $this->assertEquals('textract', $resultPayload['ocr_route']);
    }

    /** @test */
    public function it_sets_routing_metadata_when_skipping_textract(): void
    {
        // 400 words over 2 pages = 200 words/page (well above threshold)
        $extractor = $this->createMockExtractor(
            text: str_repeat("Legal document text content. ", 100), // ~400 words
            pageCount: 2
        );

        $step = new CheckExistingTextStep($extractor);
        $job = TextractJob::factory()->create(['status' => 'pending', 'metadata' => []]);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'localPath' => $this->testPdfPath,
            'job' => $job,
            'forceTextract' => false,
        ];

        $resultPayload = null;
        $step->handle($payload, function ($p) use (&$resultPayload) {
            $resultPayload = $p;
            return $p;
        });

        // Check payload metadata
        $this->assertArrayHasKey('ocr_routing_metadata', $resultPayload);
        $this->assertTrue($resultPayload['ocr_routing_metadata']['skipped_textract']);
        $this->assertArrayHasKey('existing_text_coverage', $resultPayload['ocr_routing_metadata']);
        $this->assertArrayHasKey('words_per_page', $resultPayload['ocr_routing_metadata']);
        $this->assertArrayHasKey('page_count', $resultPayload['ocr_routing_metadata']);
        $this->assertArrayHasKey('threshold_used', $resultPayload['ocr_routing_metadata']);

        // Check that job metadata was updated
        $job->refresh();
        $this->assertArrayHasKey('ocr_routing_metadata', $job->metadata);
        $this->assertTrue($job->metadata['ocr_routing_metadata']['skipped_textract']);
    }

    /** @test */
    public function it_handles_pdftotext_failure_gracefully(): void
    {
        $extractor = $this->createFailingExtractor('pdftotext command failed');

        $step = new CheckExistingTextStep($extractor);
        $job = TextractJob::factory()->create(['status' => 'pending']);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'localPath' => $this->testPdfPath,
            'job' => $job,
            'forceTextract' => false,
        ];

        $resultPayload = null;
        $step->handle($payload, function ($p) use (&$resultPayload) {
            $resultPayload = $p;
            return $p;
        });

        // Should proceed to Textract on failure
        $this->assertFalse($resultPayload['skip_textract'] ?? true);
        $this->assertEquals('textract', $resultPayload['ocr_route']);
        $this->assertArrayHasKey('pdftotext_error', $resultPayload);
        $this->assertEquals('pdftotext command failed', $resultPayload['pdftotext_error']);
    }

    /** @test */
    public function it_respects_force_textract_flag(): void
    {
        // Even with high text coverage, forceTextract should bypass the check
        // This mock should NOT be called since forceTextract bypasses extraction
        $extractor = Mockery::mock(PdfTextExtractorInterface::class);
        $extractor->shouldNotReceive('extract');

        $step = new CheckExistingTextStep($extractor);
        $job = TextractJob::factory()->create(['status' => 'pending']);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'localPath' => $this->testPdfPath,
            'job' => $job,
            'forceTextract' => true, // Force Textract
        ];

        $resultPayload = null;
        $step->handle($payload, function ($p) use (&$resultPayload) {
            $resultPayload = $p;
            return $p;
        });

        // Should NOT skip Textract even with high coverage
        $this->assertFalse($resultPayload['skip_textract'] ?? true);
        $this->assertEquals('textract', $resultPayload['ocr_route']);
    }

    /** @test */
    public function it_skips_check_when_no_local_path(): void
    {
        // This mock should NOT be called since no local path
        $extractor = Mockery::mock(PdfTextExtractorInterface::class);
        $extractor->shouldNotReceive('extract');

        $step = new CheckExistingTextStep($extractor);
        $job = TextractJob::factory()->create(['status' => 'pending']);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            // No localPath
            'job' => $job,
            'forceTextract' => false,
        ];

        $resultPayload = null;
        $step->handle($payload, function ($p) use (&$resultPayload) {
            $resultPayload = $p;
            return $p;
        });

        // Should proceed to Textract when no local file
        $this->assertFalse($resultPayload['skip_textract'] ?? true);
        $this->assertEquals('textract', $resultPayload['ocr_route']);
    }

    /** @test */
    public function it_stores_pdftotext_text_when_skipping_textract(): void
    {
        $extractedText = str_repeat("Legal document with substantial text content. ", 100);
        $extractor = $this->createMockExtractor(
            text: $extractedText,
            pageCount: 2
        );

        $step = new CheckExistingTextStep($extractor);
        $job = TextractJob::factory()->create(['status' => 'pending']);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'localPath' => $this->testPdfPath,
            'job' => $job,
            'forceTextract' => false,
        ];

        $resultPayload = null;
        $step->handle($payload, function ($p) use (&$resultPayload) {
            $resultPayload = $p;
            return $p;
        });

        $this->assertTrue($resultPayload['skip_textract']);
        $this->assertArrayHasKey('pdftotext_text', $resultPayload);
        $this->assertEquals($extractedText, $resultPayload['pdftotext_text']);
    }

    /**
     * Create a mock extractor that returns successful extraction.
     */
    private function createMockExtractor(string $text, int $pageCount): PdfTextExtractorInterface
    {
        $extractor = Mockery::mock(PdfTextExtractorInterface::class);
        $extractor->shouldReceive('extract')
            ->once()
            ->andReturn([
                'success' => true,
                'text' => $text,
                'page_count' => $pageCount,
                'error' => null,
            ]);

        return $extractor;
    }

    /**
     * Create a mock extractor that returns failure.
     */
    private function createFailingExtractor(string $error): PdfTextExtractorInterface
    {
        $extractor = Mockery::mock(PdfTextExtractorInterface::class);
        $extractor->shouldReceive('extract')
            ->once()
            ->andReturn([
                'success' => false,
                'text' => '',
                'page_count' => 0,
                'error' => $error,
            ]);

        return $extractor;
    }
}
