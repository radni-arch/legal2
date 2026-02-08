<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Http\Livewire\TextractManager;
use App\Models\TextractDocument;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class TextractManagerPdfTest extends TestCase
{
    use UsesTestDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_can_open_pdf_preview_modal(): void
    {
        // Arrange
        $document = TextractDocument::factory()->create([
            's3_output_path' => 'textract/outputs/test.pdf',
        ]);

        // Act & Assert
        Livewire::test(TextractManager::class)
            ->call('previewPdf', $document->id)
            ->assertSet('showPdfModal', true)
            ->assertSet('previewDocumentId', $document->id)
            ->assertSet('pdfSignedUrl', function ($url) {
                return str_contains($url, 'textract-file') && str_contains($url, 'signature=');
            });
    }

    public function test_can_close_pdf_preview_modal(): void
    {
        // Arrange
        $document = TextractDocument::factory()->create();

        // Act & Assert
        Livewire::test(TextractManager::class)
            ->set('showPdfModal', true)
            ->set('previewDocumentId', $document->id)
            ->call('closePdfModal')
            ->assertSet('showPdfModal', false)
            ->assertSet('previewDocumentId', null)
            ->assertSet('pdfSignedUrl', null);
    }

    public function test_prevents_preview_for_document_without_s3_path(): void
    {
        // Arrange
        $document = TextractDocument::factory()->create([
            's3_output_path' => null,
        ]);

        // Act & Assert
        Livewire::test(TextractManager::class)
            ->call('previewPdf', $document->id)
            ->assertSet('showPdfModal', false)
            ->assertDispatched('notify', function ($event) {
                return $event['type'] === 'error' &&
                       str_contains($event['message'], 'preview');
            });
    }

    public function test_modal_displays_pdf_viewer_ui(): void
    {
        // Arrange
        $document = TextractDocument::factory()->create([
            's3_output_path' => 'textract/outputs/test.pdf',
        ]);

        // Act
        $component = Livewire::test(TextractManager::class)
            ->call('previewPdf', $document->id);

        // Assert
        $component
            ->assertSee('PDF Preview')
            ->assertSee('Close')
            ->assertSeeHtml('id="pdf-canvas"')
            ->assertSeeHtml('wire:click="closePdfModal"');
    }
}
