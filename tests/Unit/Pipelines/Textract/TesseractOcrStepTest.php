<?php

namespace Tests\Unit\Pipelines\Textract;

use App\Pipelines\Textract\TesseractOcrStep;
use App\Services\Ocr\DocumentOcrRouter;
use App\Services\Ocr\OcrQualityComparator;
use App\Services\Ocr\TesseractOcrService;
use Mockery;
use Tests\TestCase;

class TesseractOcrStepTest extends TestCase
{
    private function mockGuardAvailable(): void
    {
        $guard = Mockery::mock(\App\Services\Ocr\OcrAvailabilityGuard::class);
        $guard->shouldReceive('canUseLocalOcr')->andReturn(true);
        $this->app->instance(\App\Services\Ocr\OcrAvailabilityGuard::class, $guard);
    }

    private function mockGuardUnavailable(): void
    {
        $guard = Mockery::mock(\App\Services\Ocr\OcrAvailabilityGuard::class);
        $guard->shouldReceive('canUseLocalOcr')->andReturn(false);
        $this->app->instance(\App\Services\Ocr\OcrAvailabilityGuard::class, $guard);
    }

    /** @test */
    public function it_skips_tesseract_when_router_recommends_textract(): void
    {
        $this->mockGuardAvailable();

        $router = Mockery::mock(DocumentOcrRouter::class);
        $router->shouldReceive('recommend')
            ->once()
            ->andReturn(['engine' => 'textract', 'reasons' => ['high_confidence'], 'confidence' => 0.95, 'analysis' => []]);

        $tesseract = Mockery::mock(TesseractOcrService::class);
        $tesseract->shouldNotReceive('extractText');

        $comparator = Mockery::mock(OcrQualityComparator::class);

        $step = new TesseractOcrStep($router, $tesseract, $comparator);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'blocks' => [['BlockType' => 'WORD', 'Confidence' => 98]],
            'textractText' => 'Existing good quality text.',
        ];

        $nextCalled = false;
        $step->handle($payload, function ($p) use (&$nextCalled) {
            $nextCalled = true;
            $this->assertEquals('textract', $p['ocr_engine_used']);
            return $p;
        });

        $this->assertTrue($nextCalled);
    }

    /** @test */
    public function it_runs_tesseract_when_router_recommends_it(): void
    {
        $this->mockGuardAvailable();

        $router = Mockery::mock(DocumentOcrRouter::class);
        $router->shouldReceive('recommend')
            ->once()
            ->andReturn(['engine' => 'tesseract', 'reasons' => ['croatian_content:0.8'], 'confidence' => 0.85, 'analysis' => []]);

        $tesseract = Mockery::mock(TesseractOcrService::class);
        $tesseract->shouldReceive('extractText')
            ->once()
            ->andReturn([
                'text' => 'Općinski sud u Zagrebu donosi rješenje.',
                'status' => 'success',
                'pages' => [['page' => 1, 'char_count' => 40, 'word_count' => 6]],
                'page_count' => 1,
                'engine' => 'tesseract',
            ]);

        $comparator = Mockery::mock(OcrQualityComparator::class);
        $comparator->shouldReceive('compare')
            ->once()
            ->andReturn([
                'winner' => 'tesseract',
                'improvement_percent' => 15.0,
                'textract_score' => 0.6,
                'tesseract_score' => 0.85,
                'reasons' => ['better_diacritics'],
            ]);

        $step = new TesseractOcrStep($router, $tesseract, $comparator);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'localPath' => '/tmp/test.pdf',
            'blocks' => [['BlockType' => 'WORD', 'Confidence' => 65]],
            'textractText' => 'Opcinski sud u Zagrebu donosi rjesenje.',
        ];

        $step->handle($payload, function ($p) {
            $this->assertEquals('tesseract', $p['ocr_engine_used']);
            $this->assertStringContainsString('Općinski', $p['finalText']);
            return $p;
        });
    }

    /** @test */
    public function it_falls_back_to_textract_when_tesseract_fails(): void
    {
        $this->mockGuardAvailable();

        $router = Mockery::mock(DocumentOcrRouter::class);
        $router->shouldReceive('recommend')
            ->andReturn(['engine' => 'tesseract', 'reasons' => ['croatian_content'], 'confidence' => 0.8, 'analysis' => []]);

        $tesseract = Mockery::mock(TesseractOcrService::class);
        $tesseract->shouldReceive('extractText')
            ->once()
            ->andReturn([
                'text' => '',
                'status' => 'error',
                'pages' => [],
                'page_count' => 0,
                'engine' => 'tesseract',
                'error' => 'Tesseract not available',
            ]);

        $comparator = Mockery::mock(OcrQualityComparator::class);

        $step = new TesseractOcrStep($router, $tesseract, $comparator);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'localPath' => '/tmp/test.pdf',
            'blocks' => [],
            'textractText' => 'Fallback text from Textract.',
        ];

        $step->handle($payload, function ($p) {
            $this->assertEquals('textract', $p['ocr_engine_used']);
            $this->assertStringContainsString('Fallback text', $p['finalText']);
            return $p;
        });
    }

    /** @test */
    public function it_stores_ocr_metadata_in_payload(): void
    {
        $this->mockGuardAvailable();

        $router = Mockery::mock(DocumentOcrRouter::class);
        $router->shouldReceive('recommend')
            ->andReturn(['engine' => 'textract', 'reasons' => ['default'], 'confidence' => 0.9, 'analysis' => []]);

        $tesseract = Mockery::mock(TesseractOcrService::class);
        $comparator = Mockery::mock(OcrQualityComparator::class);

        $step = new TesseractOcrStep($router, $tesseract, $comparator);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'blocks' => [],
            'textractText' => 'Some text.',
        ];

        $step->handle($payload, function ($p) {
            $this->assertArrayHasKey('ocr_routing', $p);
            $this->assertArrayHasKey('ocr_engine_used', $p);
            return $p;
        });
    }

    /** @test */
    public function it_uses_ocrmypdf_when_routing_recommends_tesseract(): void
    {
        $this->mockGuardAvailable();

        $router = Mockery::mock(DocumentOcrRouter::class);
        $router->shouldReceive('recommend')->andReturn([
            'engine' => 'tesseract',
            'reasons' => ['croatian_content:0.8'],
            'confidence' => 0.8,
            'analysis' => [],
            'routing_score' => 2.0,
        ]);

        $ocrmypdf = Mockery::mock(\App\Services\Ocr\OcrmypdfService::class);
        $ocrmypdf->shouldReceive('isAvailable')->andReturn(true);
        $ocrmypdf->shouldReceive('process')
            ->once()
            ->andReturn([
                'status' => 'success',
                'text' => 'Presuda broj Kž-123/2024',
                'output_pdf' => '/tmp/output.pdf',
                'page_count' => 3,
                'engine' => 'ocrmypdf',
                'error' => null,
                'exit_code' => 0,
            ]);

        $tesseract = Mockery::mock(TesseractOcrService::class);
        $tesseract->shouldNotReceive('extractText');

        $comparator = Mockery::mock(OcrQualityComparator::class);
        $comparator->shouldReceive('compare')->andReturn([
            'winner' => 'tesseract',
            'improvement_percent' => 15.0,
            'textract_score' => 0.6,
            'tesseract_score' => 0.75,
            'reasons' => ['better_diacritics'],
        ]);

        $step = new TesseractOcrStep($router, $tesseract, $comparator, $ocrmypdf);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'localPath' => '/tmp/test.pdf',
            'blocks' => [['BlockType' => 'WORD', 'Confidence' => 65]],
            'textractText' => 'Opcinski sud',
        ];

        $step->handle($payload, function ($p) {
            $this->assertEquals('ocrmypdf', $p['ocr_engine_used']);
            return $p;
        });
    }

    /** @test */
    public function it_skips_local_ocr_when_guard_says_unavailable(): void
    {
        $this->mockGuardUnavailable();

        $router = Mockery::mock(DocumentOcrRouter::class);
        $router->shouldNotReceive('recommend');

        $tesseract = Mockery::mock(TesseractOcrService::class);
        $tesseract->shouldNotReceive('extractText');

        $comparator = Mockery::mock(OcrQualityComparator::class);

        $step = new TesseractOcrStep($router, $tesseract, $comparator);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'blocks' => [],
            'textractText' => 'Some text.',
        ];

        $step->handle($payload, function ($p) {
            $this->assertEquals('textract', $p['ocr_engine_used']);
            $this->assertEquals('local_ocr_unavailable', $p['ocr_skipped_reason']);
            return $p;
        });
    }

    /** @test */
    public function it_forces_reocr_when_quality_flagged_for_review(): void
    {
        $this->mockGuardAvailable();

        $router = Mockery::mock(DocumentOcrRouter::class);
        $router->shouldReceive('recommend')->andReturn([
            'engine' => 'textract',
            'reasons' => ['high_textract_confidence:0.90'],
            'confidence' => 0.9,
            'analysis' => [],
            'routing_score' => -2.0,
        ]);

        $tesseract = Mockery::mock(TesseractOcrService::class);
        $tesseract->shouldReceive('extractText')
            ->once()
            ->andReturn([
                'text' => 'Better text from Tesseract.',
                'status' => 'success',
                'pages' => [['page' => 1, 'char_count' => 30, 'word_count' => 5]],
                'page_count' => 1,
                'engine' => 'tesseract',
            ]);

        $comparator = Mockery::mock(OcrQualityComparator::class);
        $comparator->shouldReceive('compare')->andReturn([
            'winner' => 'tesseract',
            'improvement_percent' => 10.0,
            'textract_score' => 0.5,
            'tesseract_score' => 0.7,
            'reasons' => ['better_diacritics'],
        ]);

        config(['ocr.force_reocr_on_review' => true]);

        $step = new TesseractOcrStep($router, $tesseract, $comparator);

        $payload = [
            'driveFileId' => 'test-123',
            'driveFileName' => 'test.pdf',
            'localPath' => '/tmp/test.pdf',
            'blocks' => [],
            'textractText' => 'Bad text from Textract.',
            'needsReview' => true,
            'reviewReasons' => ['Low overall confidence'],
        ];

        $step->handle($payload, function ($p) {
            $this->assertContains('quality_gated_override', $p['ocr_routing']['reasons']);
            return $p;
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
