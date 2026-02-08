<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Cache;

class OcrAvailabilityGuard
{
    private const CACHE_KEY = 'ocr_availability_status';
    private const CACHE_TTL = 300; // 5 minutes

    public function check(bool $fresh = false): array
    {
        if (!$fresh) {
            $cached = Cache::get(self::CACHE_KEY);
            if ($cached !== null) {
                return $cached;
            }
        }

        $ocrmypdf = app(OcrmypdfService::class);
        $tesseract = app(TesseractOcrService::class);

        $status = [
            'ocrmypdf_available' => $ocrmypdf->isAvailable(),
            'ocrmypdf_version' => $ocrmypdf->getVersion(),
            'tesseract_available' => $tesseract->isAvailable(),
            'croatian_language' => $ocrmypdf->hasCroatianLanguage(),
            'checked_at' => now()->toIso8601String(),
        ];

        Cache::put(self::CACHE_KEY, $status, self::CACHE_TTL);

        return $status;
    }

    /**
     * Can we use the local OCR path (ocrmypdf or tesseract)?
     */
    public function canUseLocalOcr(): bool
    {
        $status = $this->check();
        return ($status['ocrmypdf_available'] || $status['tesseract_available'])
            && $status['croatian_language'];
    }
}
