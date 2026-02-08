<?php

namespace Tests\Unit\Services\Ocr;

use App\Services\Ocr\OcrAvailabilityGuard;
use App\Services\Ocr\OcrmypdfService;
use App\Services\Ocr\TesseractOcrService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class OcrAvailabilityGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function guard_returns_availability_status(): void
    {
        $ocrmypdf = Mockery::mock(OcrmypdfService::class);
        $ocrmypdf->shouldReceive('isAvailable')->andReturn(true);
        $ocrmypdf->shouldReceive('getVersion')->andReturn('16.0.0');
        $ocrmypdf->shouldReceive('hasCroatianLanguage')->andReturn(true);

        $tesseract = Mockery::mock(TesseractOcrService::class);
        $tesseract->shouldReceive('isAvailable')->andReturn(true);

        $this->app->instance(OcrmypdfService::class, $ocrmypdf);
        $this->app->instance(TesseractOcrService::class, $tesseract);

        Cache::flush();
        $guard = new OcrAvailabilityGuard();
        $status = $guard->check(fresh: true);

        $this->assertTrue($status['ocrmypdf_available']);
        $this->assertTrue($status['tesseract_available']);
        $this->assertTrue($status['croatian_language']);
        $this->assertArrayHasKey('checked_at', $status);
    }

    /** @test */
    public function guard_returns_unavailable_when_binary_missing(): void
    {
        $ocrmypdf = Mockery::mock(OcrmypdfService::class);
        $ocrmypdf->shouldReceive('isAvailable')->andReturn(false);
        $ocrmypdf->shouldReceive('getVersion')->andReturn(null);
        $ocrmypdf->shouldReceive('hasCroatianLanguage')->andReturn(false);

        $tesseract = Mockery::mock(TesseractOcrService::class);
        $tesseract->shouldReceive('isAvailable')->andReturn(false);

        $this->app->instance(OcrmypdfService::class, $ocrmypdf);
        $this->app->instance(TesseractOcrService::class, $tesseract);

        Cache::flush();
        $guard = new OcrAvailabilityGuard();
        $status = $guard->check(fresh: true);

        $this->assertFalse($status['ocrmypdf_available']);
        $this->assertFalse($status['tesseract_available']);
    }

    /** @test */
    public function can_use_local_ocr_requires_binary_and_language(): void
    {
        $ocrmypdf = Mockery::mock(OcrmypdfService::class);
        $ocrmypdf->shouldReceive('isAvailable')->andReturn(true);
        $ocrmypdf->shouldReceive('getVersion')->andReturn('16.0.0');
        $ocrmypdf->shouldReceive('hasCroatianLanguage')->andReturn(true);

        $tesseract = Mockery::mock(TesseractOcrService::class);
        $tesseract->shouldReceive('isAvailable')->andReturn(true);

        $this->app->instance(OcrmypdfService::class, $ocrmypdf);
        $this->app->instance(TesseractOcrService::class, $tesseract);

        Cache::flush();
        $guard = new OcrAvailabilityGuard();

        $this->assertTrue($guard->canUseLocalOcr());
    }

    /** @test */
    public function cannot_use_local_ocr_without_croatian_language(): void
    {
        $ocrmypdf = Mockery::mock(OcrmypdfService::class);
        $ocrmypdf->shouldReceive('isAvailable')->andReturn(true);
        $ocrmypdf->shouldReceive('getVersion')->andReturn('16.0.0');
        $ocrmypdf->shouldReceive('hasCroatianLanguage')->andReturn(false);

        $tesseract = Mockery::mock(TesseractOcrService::class);
        $tesseract->shouldReceive('isAvailable')->andReturn(true);

        $this->app->instance(OcrmypdfService::class, $ocrmypdf);
        $this->app->instance(TesseractOcrService::class, $tesseract);

        Cache::flush();
        $guard = new OcrAvailabilityGuard();

        $this->assertFalse($guard->canUseLocalOcr());
    }

    /** @test */
    public function check_uses_cache_by_default(): void
    {
        Cache::put('ocr_availability_status', [
            'ocrmypdf_available' => true,
            'ocrmypdf_version' => '16.0.0',
            'tesseract_available' => true,
            'croatian_language' => true,
            'checked_at' => now()->toIso8601String(),
        ], 300);

        $guard = new OcrAvailabilityGuard();
        $status = $guard->check();

        $this->assertTrue($status['ocrmypdf_available']);
    }
}
