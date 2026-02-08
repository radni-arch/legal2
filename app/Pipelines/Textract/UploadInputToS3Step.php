<?php

namespace App\Pipelines\Textract;

use App\Actions\Textract\UploadInputToS3;
use App\Pipelines\Concerns\RetryableStep;
use Closure;

/**
 * Step: UploadInputToS3Step
 * Upload original PDF to S3 input prefix.
 * Adds: s3Key
 */
class UploadInputToS3Step
{
    use RetryableStep;

    public function handle(array $payload, Closure $next): mixed
    {
        // Skip S3 upload when using local OCR route (skip_textract)
        if ($payload['skip_textract'] ?? false) {
            return $next($payload);
        }

        $payload['s3Key'] = $this->withRetry(
            fn () => UploadInputToS3::run($payload['localPath'], $payload['driveFileId']),
            's3_upload_input'
        );
        $payload['job']->update(['s3_key' => $payload['s3Key'], 'status' => 'started']);

        return $next($payload);
    }
}
