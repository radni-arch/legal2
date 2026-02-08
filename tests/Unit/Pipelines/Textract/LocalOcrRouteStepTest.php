<?php

namespace Tests\Unit\Pipelines\Textract;

use App\Models\TextractJob;
use App\Pipelines\Textract\LocalOcrRouteStep;
use App\Services\Ocr\OcrmypdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LocalOcrRouteStepTest extends TestCase
{
    use RefreshDatabase;

    private string $testPdfPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testPdfPath = sys_get_temp_dir() . '/test_' . uniqid() . '.pdf';
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
    public function it_skips_when_skip_textract_is_false(): void
    {
        $ocrmypdf = Mockery::mock(OcrmypdfService::class);
        $ocrmypdf->shouldNotReceive('process');
        $ocrmypdf->shouldNotReceive('isAvailable');

        $step = new LocalOcrRouteStep($ocrmypdf);
        $job = TextractJob::factory()->create(['status' => 'pending']);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'localPath' => $this->testPdfPath,
            'job' => $job,
            'skip_textract' => false, // Not skipping
        ];

        $resultPayload = null;
        $step->handle($payload, function ($p) use (&$resultPayload) {
            $resultPayload = $p;
            return $p;
        });

        // Should pass through unchanged
        $this->assertFalse($resultPayload['skip_textract']);
        $this->assertArrayNotHasKey('ocr_engine_used', $resultPayload);
    }

    /** @test */
    public function it_processes_with_ocrmypdf_when_skip_textract_is_true(): void
    {
        $extractedText = "This is pre-extracted text from pdftotext.";

        $ocrmypdf = Mockery::mock(OcrmypdfService::class);
        $ocrmypdf->shouldReceive('isAvailable')->andReturn(true);
        $ocrmypdf->shouldReceive('process')
            ->once()
            ->with($this->testPdfPath, Mockery::on(function ($options) {
                return $options['skip_text'] === true;
            }))
            ->andReturn([
                'status' => 'success',
                'text' => $extractedText . ' Enhanced with more text.',
                'output_pdf' => '/tmp/output.pdf',
                'page_count' => 3,
                'engine' => 'ocrmypdf',
                'error' => null,
                'exit_code' => 0,
            ]);

        $step = new LocalOcrRouteStep($ocrmypdf);
        $job = TextractJob::factory()->create(['status' => 'pending']);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'localPath' => $this->testPdfPath,
            'job' => $job,
            'skip_textract' => true,
            'pdftotext_text' => $extractedText,
            'ocr_routing_metadata' => [
                'page_count' => 3,
            ],
        ];

        $resultPayload = null;
        $step->handle($payload, function ($p) use (&$resultPayload) {
            $resultPayload = $p;
            return $p;
        });

        $this->assertEquals('ocrmypdf_skip_text', $resultPayload['ocr_engine_used']);
        $this->assertArrayHasKey('textractText', $resultPayload);
        $this->assertArrayHasKey('finalText', $resultPayload);
        $this->assertEquals([], $resultPayload['blocks']);
        $this->assertArrayHasKey('linesByPage', $resultPayload);
    }

    /** @test */
    public function it_uses_pdftotext_text_when_ocrmypdf_unavailable(): void
    {
        $extractedText = "This is pre-extracted text from pdftotext.";

        $ocrmypdf = Mockery::mock(OcrmypdfService::class);
        $ocrmypdf->shouldReceive('isAvailable')->andReturn(false);
        $ocrmypdf->shouldNotReceive('process');

        $step = new LocalOcrRouteStep($ocrmypdf);
        $job = TextractJob::factory()->create(['status' => 'pending']);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'localPath' => $this->testPdfPath,
            'job' => $job,
            'skip_textract' => true,
            'pdftotext_text' => $extractedText,
            'ocr_routing_metadata' => [
                'page_count' => 2,
            ],
        ];

        $resultPayload = null;
        $step->handle($payload, function ($p) use (&$resultPayload) {
            $resultPayload = $p;
            return $p;
        });

        $this->assertEquals('ocrmypdf_skip_text', $resultPayload['ocr_engine_used']);
        $this->assertEquals($extractedText, $resultPayload['textractText']);
        $this->assertEquals($extractedText, $resultPayload['finalText']);
    }

    /** @test */
    public function it_falls_back_to_pdftotext_when_ocrmypdf_fails(): void
    {
        $extractedText = "This is pre-extracted text from pdftotext.";

        $ocrmypdf = Mockery::mock(OcrmypdfService::class);
        $ocrmypdf->shouldReceive('isAvailable')->andReturn(true);
        $ocrmypdf->shouldReceive('process')
            ->once()
            ->andReturn([
                'status' => 'error',
                'text' => '',
                'output_pdf' => '',
                'page_count' => 0,
                'engine' => 'ocrmypdf',
                'error' => 'Processing failed',
                'exit_code' => 1,
            ]);

        $step = new LocalOcrRouteStep($ocrmypdf);
        $job = TextractJob::factory()->create(['status' => 'pending']);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'localPath' => $this->testPdfPath,
            'job' => $job,
            'skip_textract' => true,
            'pdftotext_text' => $extractedText,
            'ocr_routing_metadata' => [
                'page_count' => 2,
            ],
        ];

        $resultPayload = null;
        $step->handle($payload, function ($p) use (&$resultPayload) {
            $resultPayload = $p;
            return $p;
        });

        // Should still work with pdftotext text
        $this->assertEquals('ocrmypdf_skip_text', $resultPayload['ocr_engine_used']);
        $this->assertEquals($extractedText, $resultPayload['finalText']);
    }

    /** @test */
    public function it_updates_job_metadata_with_routing_info(): void
    {
        $extractedText = "Document text content.";

        $ocrmypdf = Mockery::mock(OcrmypdfService::class);
        $ocrmypdf->shouldReceive('isAvailable')->andReturn(false);

        $step = new LocalOcrRouteStep($ocrmypdf);
        $job = TextractJob::factory()->create(['status' => 'pending', 'metadata' => []]);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'localPath' => $this->testPdfPath,
            'job' => $job,
            'skip_textract' => true,
            'pdftotext_text' => $extractedText,
            'ocr_routing_metadata' => [
                'page_count' => 1,
            ],
        ];

        $step->handle($payload, function ($p) {
            return $p;
        });

        $job->refresh();
        $this->assertEquals('processing_local_ocr', $job->status);
        $this->assertArrayHasKey('ocr_routing_metadata', $job->metadata);
        $this->assertTrue($job->metadata['ocr_routing_metadata']['skipped_textract']);
        $this->assertEquals('ocrmypdf_skip_text', $job->metadata['ocr_routing_metadata']['ocr_engine_used']);
    }

    /** @test */
    public function it_creates_lines_by_page_structure(): void
    {
        $extractedText = "Line 1 page 1\nLine 2 page 1\fLine 1 page 2\nLine 2 page 2";

        $ocrmypdf = Mockery::mock(OcrmypdfService::class);
        $ocrmypdf->shouldReceive('isAvailable')->andReturn(false);

        $step = new LocalOcrRouteStep($ocrmypdf);
        $job = TextractJob::factory()->create(['status' => 'pending']);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'localPath' => $this->testPdfPath,
            'job' => $job,
            'skip_textract' => true,
            'pdftotext_text' => $extractedText,
            'ocr_routing_metadata' => [
                'page_count' => 2,
            ],
        ];

        $resultPayload = null;
        $step->handle($payload, function ($p) use (&$resultPayload) {
            $resultPayload = $p;
            return $p;
        });

        $this->assertArrayHasKey('linesByPage', $resultPayload);
        $this->assertCount(2, $resultPayload['linesByPage']);
        $this->assertCount(2, $resultPayload['linesByPage'][1]); // 2 lines on page 1
        $this->assertCount(2, $resultPayload['linesByPage'][2]); // 2 lines on page 2
    }
}
