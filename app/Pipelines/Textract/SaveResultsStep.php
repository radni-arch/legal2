<?php

namespace App\Pipelines\Textract;

use App\Actions\Textract\SaveAnalysisResults;
use Closure;

/**
 * Step: SaveResultsStep
 * Save JSON blocks to S3 and local; payload not modified except for optional meta.
 */
class SaveResultsStep
{
    public function handle(array $payload, Closure $next): mixed
    {
        // Skip saving Textract results when using local OCR route (skip_textract)
        if ($payload['skip_textract'] ?? false) {
            return $next($payload);
        }

        $meta = SaveAnalysisResults::run($payload['driveFileId'], $payload['blocks']);
        $payload['resultsMeta'] = $meta;

        return $next($payload);
    }
}
