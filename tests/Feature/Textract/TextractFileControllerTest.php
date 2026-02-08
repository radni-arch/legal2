<?php

declare(strict_types=1);

namespace Tests\Feature\Textract;

use App\Models\TextractDocument;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TextractFileControllerTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = new User;
        $this->user->id = 1;
        $this->user->name = 'Test User';
        $this->user->email = 'test@example.com';
        $this->user->password = bcrypt('password');
    }

    public function test_serves_pdf_file_with_valid_signed_url(): void
    {
        // Arrange
        Storage::fake('s3');
        $pdfContent = '%PDF-1.4 fake pdf content';
        Storage::disk('s3')->put('textract/outputs/test.pdf', $pdfContent);

        $document = new TextractDocument;
        $document->id = 1;
        $document->s3_output_path = 'textract/outputs/test.pdf';

        // Mock route model binding
        Route::bind('document', function ($value) use ($document) {
            return $value == $document->id ? $document : null;
        });

        $signedUrl = URL::temporarySignedRoute(
            'textract.file',
            now()->addHour(),
            ['document' => $document->id]
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'inline; filename="'.$document->id.'.pdf"');
        $this->assertEquals($pdfContent, $response->getContent());
    }

    public function test_rejects_request_with_invalid_signature(): void
    {
        // Arrange
        $document = new TextractDocument;
        $document->id = 2;

        Route::bind('document', function ($value) use ($document) {
            return $value == $document->id ? $document : null;
        });

        $invalidUrl = route('textract.file', ['document' => $document->id]);

        // Act
        $response = $this->actingAs($this->user)->get($invalidUrl);

        // Assert
        $response->assertStatus(403);
    }

    public function test_rejects_request_with_expired_signature(): void
    {
        // Arrange
        $document = new TextractDocument;
        $document->id = 3;

        Route::bind('document', function ($value) use ($document) {
            return $value == $document->id ? $document : null;
        });

        $expiredUrl = URL::temporarySignedRoute(
            'textract.file',
            now()->subMinute(), // Expired 1 minute ago
            ['document' => $document->id]
        );

        // Act
        $response = $this->actingAs($this->user)->get($expiredUrl);

        // Assert
        $response->assertStatus(403);
    }

    public function test_returns_404_for_nonexistent_document(): void
    {
        // Arrange
        Route::bind('document', function ($value) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException;
        });

        $signedUrl = URL::temporarySignedRoute(
            'textract.file',
            now()->addHour(),
            ['document' => 99999]
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertNotFound();
    }

    public function test_returns_404_when_document_has_no_s3_path(): void
    {
        // Arrange
        $document = new TextractDocument;
        $document->id = 5;
        $document->s3_output_path = null;

        Route::bind('document', function ($value) use ($document) {
            return $value == $document->id ? $document : null;
        });

        $signedUrl = URL::temporarySignedRoute(
            'textract.file',
            now()->addHour(),
            ['document' => $document->id]
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertNotFound();
    }

    public function test_requires_authentication(): void
    {
        // Arrange
        $document = new TextractDocument;
        $document->id = 6;

        Route::bind('document', function ($value) use ($document) {
            return $value == $document->id ? $document : null;
        });

        $signedUrl = URL::temporarySignedRoute(
            'textract.file',
            now()->addHour(),
            ['document' => $document->id]
        );

        // Act
        $response = $this->get($signedUrl);

        // Assert
        $response->assertRedirect(route('login'));
    }
}
