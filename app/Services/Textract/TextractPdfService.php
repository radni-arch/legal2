<?php

declare(strict_types=1);

namespace App\Services\Textract;

use App\Models\TextractDocument;
use Illuminate\Support\Facades\URL;

class TextractPdfService
{
    /**
     * Generate a signed URL for accessing a TextractDocument PDF.
     *
     * @param  TextractDocument  $document  The document to generate URL for
     * @param  int|null  $expiresIn  Expiration time in seconds (null = use config default)
     * @return string Signed URL
     *
     * @throws \InvalidArgumentException If document has no S3 output path
     */
    public function getSignedPdfUrl(TextractDocument $document, ?int $expiresIn = null): string
    {
        if (empty($document->s3_output_path)) {
            throw new \InvalidArgumentException('Document has no S3 output path');
        }

        $expiresIn = $expiresIn ?? config('textract.pdf_url_expiration', 3600);

        return URL::temporarySignedRoute(
            'textract.file',
            now()->addSeconds($expiresIn),
            ['document' => $document->id]
        );
    }

    /**
     * Verify a signed URL is still valid.
     *
     * @param  string  $url  The signed URL to verify
     * @return bool True if valid, false otherwise
     */
    public function verifySignedUrl(string $url): bool
    {
        return URL::hasValidSignature($url);
    }
}
