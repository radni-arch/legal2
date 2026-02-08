<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Textract;

use App\Models\TextractDocument;
use App\Services\Textract\TextractPdfService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TextractPdfServiceTest extends TestCase
{
    private TextractPdfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TextractPdfService::class);
    }

    public function test_generates_signed_url_for_pdf_document(): void
    {
        // Arrange
        Storage::fake('s3');
        $document = new TextractDocument;
        $document->id = 1;
        $document->s3_output_path = 'textract/outputs/test-document.pdf';

        // Act
        $signedUrl = $this->service->getSignedPdfUrl($document);

        // Assert
        $this->assertNotNull($signedUrl);
        $this->assertStringContainsString('textract/file', $signedUrl);
        $this->assertStringContainsString('signature=', $signedUrl);
        $this->assertStringContainsString('expires=', $signedUrl);
    }

    public function test_throws_exception_when_document_has_no_s3_path(): void
    {
        // Arrange
        $document = new TextractDocument;
        $document->id = 2;
        $document->s3_output_path = null;

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Document has no S3 output path');
        $this->service->getSignedPdfUrl($document);
    }

    public function test_respects_custom_expiration_time(): void
    {
        // Arrange
        Storage::fake('s3');
        $document = new TextractDocument;
        $document->id = 3;
        $document->s3_output_path = 'textract/outputs/test.pdf';

        // Act
        $signedUrl = $this->service->getSignedPdfUrl($document, expiresIn: 300); // 5 minutes

        // Assert
        $this->assertNotNull($signedUrl);
        // URL should expire in approximately 5 minutes (within 10 second tolerance)
        $parsedUrl = parse_url($signedUrl);
        parse_str($parsedUrl['query'] ?? '', $queryParams);
        $expires = (int) ($queryParams['expires'] ?? 0);
        $expectedExpiry = now()->addSeconds(300)->timestamp;
        $this->assertEqualsWithDelta($expectedExpiry, $expires, 10);
    }

    public function test_uses_default_expiration_from_config(): void
    {
        // Arrange
        Storage::fake('s3');
        config(['textract.pdf_url_expiration' => 1800]); // 30 minutes
        $document = new TextractDocument;
        $document->id = 4;
        $document->s3_output_path = 'textract/outputs/test.pdf';

        // Act
        $signedUrl = $this->service->getSignedPdfUrl($document);

        // Assert
        $parsedUrl = parse_url($signedUrl);
        parse_str($parsedUrl['query'] ?? '', $queryParams);
        $expires = (int) ($queryParams['expires'] ?? 0);
        $expectedExpiry = now()->addSeconds(1800)->timestamp;
        $this->assertEqualsWithDelta($expectedExpiry, $expires, 10);
    }
}
