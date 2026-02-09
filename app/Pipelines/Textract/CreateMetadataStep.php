<?php

namespace App\Pipelines\Textract;

use App\Services\Ocr\LegalDocumentMetadata;
use App\Services\Ocr\LegalMetadataExtractor;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Step: CreateMetadataStep
 * Extract and store comprehensive legal metadata from the OCR document.
 * This step is detachable and can be run independently.
 *
 * Extracts:
 * - Legal citations (statutes, case numbers, ECLI, Narodne Novine)
 * - Legal entities (courts, parties, judges)
 * - Document classification (type, jurisdiction)
 * - Dates and key legal phrases
 *
 * Supports two extraction paths:
 * 1. Primary: ocrDocument is present (Textract flow via CollectLinesStep)
 * 2. Fallback: textractText is present (local OCR flow via LocalOcrRouteStep)
 * 3. Skip: neither available - logs warning and continues pipeline
 *
 * Input payload keys: ocrDocument OR textractText, driveFileId, driveFileName, job (optional)
 * Output payload adds: legalMetadata (when extraction is possible)
 */
class CreateMetadataStep
{
    public function __construct(
        private LegalMetadataExtractor $extractor
    ) {}

    public function handle(array $payload, Closure $next): mixed
    {
        $driveFileId = $payload['driveFileId'] ?? null;
        $driveFileName = $payload['driveFileName'] ?? null;

        // Primary path: ocrDocument is available (Textract flow)
        if (isset($payload['ocrDocument'])) {
            $metadata = $this->extractor->extract(
                document: $payload['ocrDocument'],
                driveFileId: $driveFileId,
                driveFileName: $driveFileName
            );

            return $this->applyMetadata($payload, $metadata, $next);
        }

        // Fallback path: textractText is available (local OCR / skip_textract flow)
        $textractText = $payload['textractText'] ?? '';
        if ($textractText !== '') {
            $metadata = $this->extractor->extractFromText(
                text: $textractText,
                driveFileId: $driveFileId,
                driveFileName: $driveFileName
            );

            return $this->applyMetadata($payload, $metadata, $next);
        }

        // Neither available - log warning and skip gracefully
        Log::warning('CreateMetadataStep: no ocrDocument or textractText available, skipping metadata extraction', [
            'driveFileId' => $driveFileId,
            'driveFileName' => $driveFileName,
        ]);

        return $next($payload);
    }

    /**
     * Apply extracted metadata to payload, update job, and continue pipeline.
     */
    private function applyMetadata(array $payload, LegalDocumentMetadata $metadata, Closure $next): mixed
    {
        $payload['legalMetadata'] = $metadata;

        // Optionally update job status
        if (isset($payload['job'])) {
            $payload['job']->update([
                'status' => 'metadata_extracted',
                'metadata' => $metadata->toArray(),
            ]);
        }

        return $next($payload);
    }
}
