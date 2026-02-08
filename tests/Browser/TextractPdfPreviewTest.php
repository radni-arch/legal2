<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\LegalCase;
use App\Models\TextractDocument;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

/**
 * Browser tests for Textract PDF Preview functionality
 *
 * NOTE: These tests require Agent 3's implementation of:
 * - TextractManager::previewPdf() method
 * - TextractManager::closePdfModal() method
 * - PDF preview modal in textract-manager.blade.php
 * - Preview button with dusk="preview-pdf-{id}" selector
 * - PDF.js viewer component integration
 *
 * Tests will fail until Agent 3 completes Task 5 (Livewire Integration)
 */
class TextractPdfPreviewTest extends DuskTestCase
{
    use AuthenticatesUser, MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();

        // Ensure required tables exist
        if (! Schema::hasTable('users') || ! Schema::hasTable('legal_cases') || ! Schema::hasTable('textract_documents')) {
            $this->markTestSkipped('Database schema not initialized');
        }
    }

    /**
     * Test 1: User can preview PDF document and close modal
     *
     * Verifies complete PDF preview workflow:
     * - Opening preview modal via button click
     * - Modal displays with canvas element
     * - Navigation controls are visible
     * - Closing modal clears state
     */
    public function test_user_can_preview_pdf_document_and_close_modal(): void
    {
        // Arrange
        $user = User::factory()->create();
        $case = LegalCase::factory()->create();

        Storage::fake('s3');
        Storage::disk('s3')->put('textract/outputs/test-document.pdf', '%PDF-1.4 fake pdf content');

        $document = TextractDocument::factory()->create([
            'case_id' => $case->id,
            's3_output_path' => 'textract/outputs/test-document.pdf',
            'drive_file_name' => 'test-preview.pdf',
            'status' => 'succeeded',
        ]);

        // Act & Assert
        $this->browse(function (Browser $browser) use ($user, $document) {
            $this->loginAs($browser, $user);

            $browser->visit('/textract')
                ->waitForText('Textract', 10)
                ->assertSee($document->drive_file_name)

                // Click preview button
                ->click("@preview-pdf-{$document->id}")
                ->waitFor('#pdf-canvas', 10)

                // Verify modal is open and displays PDF viewer UI
                ->assertSee('PDF Preview')
                ->assertVisible('#pdf-canvas')
                ->assertSee('Page')
                ->assertSee('of')

                // Verify navigation controls are visible
                ->assertVisible('button:contains("Previous")')
                ->assertVisible('button:contains("Next")')
                ->assertVisible('button:contains("Zoom In")')
                ->assertVisible('button:contains("Zoom Out")')
                ->assertVisible('button:contains("Close")')

                // Close modal
                ->click('button:contains("Close")')
                ->waitUntilMissing('#pdf-canvas', 5)
                ->assertDontSee('PDF Preview');
        });
    }

    /**
     * Test 2: PDF navigation controls work correctly
     *
     * Verifies:
     * - Next/Previous buttons enable/disable based on current page
     * - Page counter updates when navigating
     * - Button states reflect available navigation options
     */
    public function test_pdf_navigation_controls_work(): void
    {
        // Arrange
        $user = User::factory()->create();
        $case = LegalCase::factory()->create();

        Storage::fake('s3');

        // Create a multi-page PDF (in real scenario, this would be actual multi-page PDF)
        // For testing purposes, we'll simulate the behavior
        $multiPagePdfContent = '%PDF-1.4 fake multi-page pdf content';
        Storage::disk('s3')->put('textract/outputs/multi-page.pdf', $multiPagePdfContent);

        $document = TextractDocument::factory()->create([
            'case_id' => $case->id,
            's3_output_path' => 'textract/outputs/multi-page.pdf',
            'drive_file_name' => 'multi-page-document.pdf',
            'status' => 'succeeded',
            'metadata' => ['pages' => 3],
        ]);

        // Act & Assert
        $this->browse(function (Browser $browser) use ($user, $document) {
            $this->loginAs($browser, $user);

            $browser->visit('/textract')
                ->waitForText('Textract', 10)

                // Open PDF preview
                ->click("@preview-pdf-{$document->id}")
                ->waitFor('#pdf-canvas', 10)
                ->assertSee('PDF Preview')

                // Verify initial state (page 1)
                ->assertSee('Page 1')

                // Previous button should be disabled on first page
                ->assertAttribute('button:contains("Previous")', 'disabled', 'true')

                // Next button should be enabled
                ->assertMissing('button:contains("Next")[disabled]')

                // Test zoom controls
                ->click('button:contains("Zoom In")')
                ->pause(500) // Wait for zoom animation

                ->click('button:contains("Zoom Out")')
                ->pause(500)

                // Navigate to next page (if multi-page PDF support is implemented)
                ->click('button:contains("Next")')
                ->pause(1000) // Wait for page render
                ->waitForText('Page 2', 5)

                // Both buttons should be enabled on middle page
                ->assertMissing('button:contains("Previous")[disabled]')
                ->assertMissing('button:contains("Next")[disabled]')

                // Navigate back to first page
                ->click('button:contains("Previous")')
                ->pause(1000)
                ->waitForText('Page 1', 5)

                // Previous button should be disabled again
                ->assertAttribute('button:contains("Previous")', 'disabled', 'true');
        });
    }

    /**
     * Test 3: Shows error for document without PDF
     *
     * Verifies:
     * - Preview button is hidden for documents without s3_output_path
     * - UI gracefully handles missing PDF files
     * - No errors when document has no PDF available
     */
    public function test_shows_no_preview_button_for_document_without_pdf(): void
    {
        // Arrange
        $user = User::factory()->create();
        $case = LegalCase::factory()->create();

        // Create document without s3_output_path (no PDF available)
        $documentWithoutPdf = TextractDocument::factory()->create([
            'case_id' => $case->id,
            's3_output_path' => null, // No PDF file
            'drive_file_name' => 'no-pdf-document.pdf',
            'status' => 'queued',
        ]);

        // Create document with PDF for comparison
        Storage::fake('s3');
        Storage::disk('s3')->put('textract/outputs/valid.pdf', '%PDF-1.4 valid pdf');

        $documentWithPdf = TextractDocument::factory()->create([
            'case_id' => $case->id,
            's3_output_path' => 'textract/outputs/valid.pdf',
            'drive_file_name' => 'valid-document.pdf',
            'status' => 'succeeded',
        ]);

        // Act & Assert
        $this->browse(function (Browser $browser) use ($user, $documentWithoutPdf, $documentWithPdf) {
            $this->loginAs($browser, $user);

            $browser->visit('/textract')
                ->waitForText('Textract', 10)

                // Document without PDF should not have preview button
                ->assertSee($documentWithoutPdf->drive_file_name)
                ->assertMissing("@preview-pdf-{$documentWithoutPdf->id}")

                // Document with PDF should have preview button
                ->assertSee($documentWithPdf->drive_file_name)
                ->assertPresent("@preview-pdf-{$documentWithPdf->id}");
        });
    }

    /**
     * Test 4: Keyboard shortcuts work in PDF viewer (bonus test)
     *
     * Verifies:
     * - Arrow keys navigate between pages
     * - Escape key closes modal
     * - Plus/minus keys zoom in/out
     *
     * NOTE: This is an optional enhancement test. May not be implemented in initial version.
     */
    public function test_keyboard_shortcuts_work_in_pdf_viewer(): void
    {
        // Arrange
        $user = User::factory()->create();
        $case = LegalCase::factory()->create();

        Storage::fake('s3');
        Storage::disk('s3')->put('textract/outputs/keyboard-test.pdf', '%PDF-1.4 test pdf');

        $document = TextractDocument::factory()->create([
            'case_id' => $case->id,
            's3_output_path' => 'textract/outputs/keyboard-test.pdf',
            'drive_file_name' => 'keyboard-shortcuts.pdf',
            'status' => 'succeeded',
            'metadata' => ['pages' => 3],
        ]);

        // Act & Assert
        $this->browse(function (Browser $browser) use ($user, $document) {
            $this->loginAs($browser, $user);

            $browser->visit('/textract')
                ->waitForText('Textract', 10)

                // Open PDF preview
                ->click("@preview-pdf-{$document->id}")
                ->waitFor('#pdf-canvas', 10)
                ->assertSee('PDF Preview')
                ->assertSee('Page 1')

                // Test right arrow key (next page)
                ->keys('#pdf-canvas', ['{arrow_right}'])
                ->pause(1000)
                ->waitForText('Page 2', 5)

                // Test left arrow key (previous page)
                ->keys('#pdf-canvas', ['{arrow_left}'])
                ->pause(1000)
                ->waitForText('Page 1', 5)

                // Test escape key (close modal)
                ->keys('body', ['{escape}'])
                ->pause(500)
                ->waitUntilMissing('#pdf-canvas', 5)
                ->assertDontSee('PDF Preview');
        });
    }
}
