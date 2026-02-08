<?php

namespace App\Pipelines\Textract;

use App\Actions\Textract\UploadOutputToS3;
use App\Pipelines\Concerns\RetryableStep;
use Closure;

/**
 * Step: UploadOutputStep
 * Upload the reconstructed PDF to S3 output prefix.
 * Adds: outKey
 */
class UploadOutputStep
{
    use RetryableStep;

    public function handle(array $payload, Closure $next): mixed
    {
        $payload['outKey'] = $this->withRetry(
            fn () => UploadOutputToS3::run($payload['driveFileId'], $payload['targetLocalPath']),
            's3_upload_output'
        );

        return $next($payload);
    }
}
