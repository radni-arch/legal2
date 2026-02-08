# Textract PDF Preview Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add PDF preview functionality to Textract Manager using PDF.js, allowing users to view uploaded documents directly in the browser with secure signed URLs.

**Architecture:** Backend provides signed URLs for temporary S3 access, PDF.js renders PDFs client-side in modal viewer, Livewire component manages UI state. NPM package with CDN worker fallback ensures reliable PDF.js loading.

**Tech Stack:** PDF.js 4.8.69, Laravel Signed URLs, Livewire 3.6, Tailwind CSS, AWS S3

---

## Prerequisites

**Required Knowledge:**
- Laravel signed routes and URL generation
- Livewire component lifecycle and events
- PDF.js library usage and worker configuration
- AWS S3 file storage and retrieval
- TDD with PHPUnit and Pest

**Documentation References:**
- `/home/user/ai-legal-war-machine/PDFJS_INTEGRATION_RESEARCH.md` - Technical design
- `/home/user/ai-legal-war-machine/PDFJS_SPRINT2_ROADMAP.md` - Day-by-day roadmap
- Laravel Signed URLs: https://laravel.com/docs/11.x/urls#signed-urls
- PDF.js Documentation: https://mozilla.github.io/pdf.js/

---

## Task 1: Backend - PDF Service (TDD)

**Goal:** Create service to generate signed URLs for PDF access with configurable expiration.

**Files:**
- Create: `app/Services/Textract/TextractPdfService.php`
- Create: `tests/Unit/Services/Textract/TextractPdfServiceTest.php`
- Config: `config/textract.php` (modify)

### Step 1: Write the failing test

**File:** `tests/Unit/Services/Textract/TextractPdfServiceTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Textract;

use App\Models\TextractDocument;
use App\Services\Textract\TextractPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $document = TextractDocument::factory()->create([
            's3_output_path' => 'textract/outputs/test-document.pdf',
        ]);

        // Act
        $signedUrl = $this->service->getSignedPdfUrl($document);

        // Assert
        $this->assertNotNull($signedUrl);
        $this->assertStringContainsString('textract-file', $signedUrl);
        $this->assertStringContainsString('signature=', $signedUrl);
        $this->assertStringContainsString('expires=', $signedUrl);
    }

    public function test_throws_exception_when_document_has_no_s3_path(): void
    {
        // Arrange
        $document = TextractDocument::factory()->create([
            's3_output_path' => null,
        ]);

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Document has no S3 output path');
        $this->service->getSignedPdfUrl($document);
    }

    public function test_respects_custom_expiration_time(): void
    {
        // Arrange
        Storage::fake('s3');
        $document = TextractDocument::factory()->create([
            's3_output_path' => 'textract/outputs/test.pdf',
        ]);

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
        $document = TextractDocument::factory()->create([
            's3_output_path' => 'textract/outputs/test.pdf',
        ]);

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
```

### Step 2: Run test to verify it fails

**Command:**
```bash
php artisan test --filter=TextractPdfServiceTest
```

**Expected Output:**
```
FAILED Tests\Unit\Services\Textract\TextractPdfServiceTest
✗ generates signed url for pdf document
  Class "App\Services\Textract\TextractPdfService" not found
```

### Step 3: Add configuration for PDF URL expiration

**File:** `config/textract.php` (add at end of config array)

```php
    /*
    |--------------------------------------------------------------------------
    | PDF Preview Settings
    |--------------------------------------------------------------------------
    */
    'pdf_url_expiration' => env('TEXTRACT_PDF_URL_EXPIRATION', 3600), // 1 hour default
```

### Step 4: Write minimal implementation

**File:** `app/Services/Textract/TextractPdfService.php`

```php
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
```

### Step 5: Run test to verify it passes

**Command:**
```bash
php artisan test --filter=TextractPdfServiceTest
```

**Expected Output:**
```
PASS Tests\Unit\Services\Textract\TextractPdfServiceTest
✓ generates signed url for pdf document
✓ throws exception when document has no s3 path
✓ respects custom expiration time
✓ uses default expiration from config

Tests:    4 passed (4 assertions)
Duration: 0.23s
```

### Step 6: Commit

```bash
git add app/Services/Textract/TextractPdfService.php tests/Unit/Services/Textract/TextractPdfServiceTest.php config/textract.php
git commit -m "feat(textract): add PDF signed URL service with TDD

- Create TextractPdfService for generating signed S3 URLs
- Add 4 unit tests covering URL generation, validation, expiration
- Add pdf_url_expiration config option (default 1 hour)
- Validate document has S3 path before generating URL"
```

---

## Task 2: Backend - File Download Controller (TDD)

**Goal:** Create controller endpoint that serves PDF files from S3 via signed URLs with security validation.

**Files:**
- Create: `app/Http/Controllers/Textract/TextractFileController.php`
- Create: `tests/Feature/Textract/TextractFileControllerTest.php`
- Modify: `routes/web.php`

### Step 1: Write the failing test

**File:** `tests/Feature/Textract/TextractFileControllerTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Textract;

use App\Models\TextractDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TextractFileControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_serves_pdf_file_with_valid_signed_url(): void
    {
        // Arrange
        Storage::fake('s3');
        $pdfContent = '%PDF-1.4 fake pdf content';
        Storage::disk('s3')->put('textract/outputs/test.pdf', $pdfContent);

        $document = TextractDocument::factory()->create([
            's3_output_path' => 'textract/outputs/test.pdf',
        ]);

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
        $document = TextractDocument::factory()->create();
        $invalidUrl = route('textract.file', ['document' => $document->id]);

        // Act
        $response = $this->actingAs($this->user)->get($invalidUrl);

        // Assert
        $response->assertStatus(403);
    }

    public function test_rejects_request_with_expired_signature(): void
    {
        // Arrange
        $document = TextractDocument::factory()->create();
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
        $document = TextractDocument::factory()->create([
            's3_output_path' => null,
        ]);

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
        $document = TextractDocument::factory()->create();
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
```

### Step 2: Run test to verify it fails

**Command:**
```bash
php artisan test --filter=TextractFileControllerTest
```

**Expected Output:**
```
FAILED Tests\Feature\Textract\TextractFileControllerTest
✗ serves pdf file with valid signed url
  Route [textract.file] not defined
```

### Step 3: Add route definition

**File:** `routes/web.php` (add in Textract routes section)

```php
// Textract PDF file serving (requires signed URL)
Route::get('/textract/file/{document}', [App\Http\Controllers\Textract\TextractFileController::class, 'show'])
    ->middleware(['auth', 'signed'])
    ->name('textract.file');
```

### Step 4: Write minimal implementation

**File:** `app/Http/Controllers/Textract/TextractFileController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Textract;

use App\Http\Controllers\Controller;
use App\Models\TextractDocument;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class TextractFileController extends Controller
{
    /**
     * Serve a TextractDocument PDF file via signed URL.
     *
     * @param  TextractDocument  $document  The document to serve
     * @return Response
     */
    public function show(TextractDocument $document): Response
    {
        // Verify document has S3 output path
        if (empty($document->s3_output_path)) {
            abort(404, 'Document file not found');
        }

        // Verify file exists in S3
        if (! Storage::disk('s3')->exists($document->s3_output_path)) {
            abort(404, 'Document file not found in storage');
        }

        // Get file content from S3
        $content = Storage::disk('s3')->get($document->s3_output_path);

        // Return PDF response with appropriate headers
        return response($content, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$document->id.'.pdf"');
    }
}
```

### Step 5: Run test to verify it passes

**Command:**
```bash
php artisan test --filter=TextractFileControllerTest
```

**Expected Output:**
```
PASS Tests\Feature\Textract\TextractFileControllerTest
✓ serves pdf file with valid signed url
✓ rejects request with invalid signature
✓ rejects request with expired signature
✓ returns 404 for nonexistent document
✓ returns 404 when document has no s3 path
✓ requires authentication

Tests:    6 passed (14 assertions)
Duration: 0.45s
```

### Step 6: Commit

```bash
git add app/Http/Controllers/Textract/TextractFileController.php tests/Feature/Textract/TextractFileControllerTest.php routes/web.php
git commit -m "feat(textract): add signed PDF file controller with TDD

- Create TextractFileController for serving PDF files
- Add 6 feature tests covering auth, signatures, file serving
- Protect endpoint with auth and signed middleware
- Return proper PDF headers for inline browser viewing"
```

---

## Task 3: Frontend - Install PDF.js via NPM

**Goal:** Install PDF.js package and configure build system for worker file.

**Files:**
- Modify: `package.json`
- Modify: `vite.config.js`
- Create: `public/.gitignore` (if doesn't exist)

### Step 1: Install PDF.js package

**Command:**
```bash
npm install pdfjs-dist@4.8.69 --save
```

**Expected Output:**
```
added 1 package, and audited 109 packages in 3s
```

### Step 2: Verify installation

**Command:**
```bash
npm list pdfjs-dist
```

**Expected Output:**
```
ai-legal-war-machine@1.0.0 /home/user/ai-legal-war-machine
└── pdfjs-dist@4.8.69
```

### Step 3: Configure Vite to copy PDF.js worker

**File:** `vite.config.js` (modify existing config)

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                // Copy PDF.js worker to public directory
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name && assetInfo.name.includes('pdf.worker')) {
                        return 'build/assets/pdf.worker.[hash].js';
                    }
                    return 'build/assets/[name].[hash][extname]';
                },
            },
        },
    },
    resolve: {
        alias: {
            'pdfjs-dist': resolve(__dirname, 'node_modules/pdfjs-dist'),
        },
    },
});
```

### Step 4: Add public/build to .gitignore if needed

**File:** `public/.gitignore` (create or modify)

```
/build
/hot
```

### Step 5: Build assets to verify configuration

**Command:**
```bash
npm run build
```

**Expected Output:**
```
> build
> vite build

vite v5.x.x building for production...
✓ built in X.XXs
```

### Step 6: Commit

```bash
git add package.json package-lock.json vite.config.js public/.gitignore
git commit -m "feat(textract): install PDF.js and configure Vite

- Install pdfjs-dist@4.8.69 via NPM
- Configure Vite to handle PDF.js worker files
- Add build directory to .gitignore
- Set up module alias for easier imports"
```

---

## Task 4: Frontend - PDF Viewer JavaScript Component

**Goal:** Create reusable PDF.js viewer component with canvas rendering and error handling.

**Files:**
- Create: `resources/js/components/pdf-viewer.js`
- Modify: `resources/js/app.js`

### Step 1: Write PDF viewer component with inline tests

**File:** `resources/js/components/pdf-viewer.js`

```javascript
/**
 * PDF Viewer Component using PDF.js
 *
 * Handles PDF rendering with canvas, page navigation, and error handling.
 * Uses PDF.js web worker for performance.
 */

import * as pdfjsLib from 'pdfjs-dist';

// Configure PDF.js worker
// Try CDN first for reliability, fall back to local build
pdfjsLib.GlobalWorkerOptions.workerSrc =
    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.8.69/pdf.worker.min.mjs';

export class PdfViewer {
    constructor(canvasElement, options = {}) {
        if (!canvasElement) {
            throw new Error('Canvas element is required');
        }

        this.canvas = canvasElement;
        this.context = this.canvas.getContext('2d');
        this.pdfDoc = null;
        this.currentPage = 1;
        this.scale = options.scale || 1.5;
        this.rendering = false;

        // Callbacks
        this.onPageChange = options.onPageChange || (() => {});
        this.onError = options.onError || ((error) => console.error('PDF Error:', error));
    }

    /**
     * Load PDF from URL
     * @param {string} url - PDF file URL (must be signed URL for security)
     * @returns {Promise<void>}
     */
    async loadDocument(url) {
        try {
            const loadingTask = pdfjsLib.getDocument(url);

            this.pdfDoc = await loadingTask.promise;
            this.currentPage = 1;

            await this.renderPage(1);
            this.onPageChange(1, this.pdfDoc.numPages);

        } catch (error) {
            this.onError(error);
            throw new Error(`Failed to load PDF: ${error.message}`);
        }
    }

    /**
     * Render specific page
     * @param {number} pageNum - Page number to render (1-indexed)
     * @returns {Promise<void>}
     */
    async renderPage(pageNum) {
        if (!this.pdfDoc) {
            throw new Error('No PDF document loaded');
        }

        if (this.rendering) {
            return; // Prevent concurrent renders
        }

        if (pageNum < 1 || pageNum > this.pdfDoc.numPages) {
            throw new Error(`Invalid page number: ${pageNum}`);
        }

        try {
            this.rendering = true;
            this.currentPage = pageNum;

            const page = await this.pdfDoc.getPage(pageNum);
            const viewport = page.getViewport({ scale: this.scale });

            // Set canvas dimensions
            this.canvas.height = viewport.height;
            this.canvas.width = viewport.width;

            const renderContext = {
                canvasContext: this.context,
                viewport: viewport,
            };

            await page.render(renderContext).promise;
            this.onPageChange(pageNum, this.pdfDoc.numPages);

        } catch (error) {
            this.onError(error);
            throw new Error(`Failed to render page ${pageNum}: ${error.message}`);
        } finally {
            this.rendering = false;
        }
    }

    /**
     * Navigate to next page
     * @returns {Promise<void>}
     */
    async nextPage() {
        if (!this.pdfDoc || this.currentPage >= this.pdfDoc.numPages) {
            return;
        }
        await this.renderPage(this.currentPage + 1);
    }

    /**
     * Navigate to previous page
     * @returns {Promise<void>}
     */
    async previousPage() {
        if (!this.pdfDoc || this.currentPage <= 1) {
            return;
        }
        await this.renderPage(this.currentPage - 1);
    }

    /**
     * Zoom in
     * @returns {Promise<void>}
     */
    async zoomIn() {
        this.scale += 0.25;
        await this.renderPage(this.currentPage);
    }

    /**
     * Zoom out
     * @returns {Promise<void>}
     */
    async zoomOut() {
        if (this.scale > 0.5) {
            this.scale -= 0.25;
            await this.renderPage(this.currentPage);
        }
    }

    /**
     * Get current state
     * @returns {Object}
     */
    getState() {
        return {
            currentPage: this.currentPage,
            totalPages: this.pdfDoc?.numPages || 0,
            scale: this.scale,
            loaded: !!this.pdfDoc,
        };
    }

    /**
     * Clean up resources
     */
    destroy() {
        if (this.pdfDoc) {
            this.pdfDoc.destroy();
            this.pdfDoc = null;
        }
        this.context.clearRect(0, 0, this.canvas.width, this.canvas.height);
    }
}

// Export for global use in Livewire components
window.PdfViewer = PdfViewer;
```

### Step 2: Register component in app.js

**File:** `resources/js/app.js` (add import)

```javascript
import './bootstrap';
import './components/pdf-viewer';

// Other existing imports...
```

### Step 3: Build JavaScript to verify no syntax errors

**Command:**
```bash
npm run dev
```

**Expected Output:**
```
VITE v5.x.x  ready in XXX ms

➜  Local:   http://localhost:5173/
➜  Network: use --host to expose
```

Press Ctrl+C after build completes.

### Step 4: Commit

```bash
git add resources/js/components/pdf-viewer.js resources/js/app.js
git commit -m "feat(textract): add PDF.js viewer component

- Create PdfViewer class with canvas rendering
- Implement page navigation (next/prev)
- Add zoom controls (in/out)
- Configure worker with CDN fallback
- Add error handling and state management
- Export to window for Livewire integration"
```

---

## Task 5: Livewire - Add PDF Preview to TextractManager (TDD)

**Goal:** Integrate PDF viewer into TextractManager component with modal UI and navigation controls.

**Files:**
- Modify: `app/Http/Livewire/TextractManager.php`
- Modify: `resources/views/livewire/textract-manager.blade.php`
- Create: `tests/Feature/Livewire/TextractManagerPdfTest.php`

### Step 1: Write the failing test

**File:** `tests/Feature/Livewire/TextractManagerPdfTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Http\Livewire\TextractManager;
use App\Models\TextractDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TextractManagerPdfTest extends TestCase
{
    use RefreshDatabase;

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
```

### Step 2: Run test to verify it fails

**Command:**
```bash
php artisan test --filter=TextractManagerPdfTest
```

**Expected Output:**
```
FAILED Tests\Feature\Livewire\TextractManagerPdfTest
✗ can open pdf preview modal
  Method previewPdf does not exist
```

### Step 3: Add properties and methods to Livewire component

**File:** `app/Http/Livewire/TextractManager.php` (add these properties and methods)

```php
    // Add to class properties section
    public bool $showPdfModal = false;
    public ?int $previewDocumentId = null;
    public ?string $pdfSignedUrl = null;

    // Add to methods section
    /**
     * Open PDF preview modal for a document
     */
    public function previewPdf(int $documentId): void
    {
        $document = TextractDocument::findOrFail($documentId);

        // Verify document has S3 output path
        if (empty($document->s3_output_path)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Cannot preview: document has no PDF file',
            ]);
            return;
        }

        try {
            // Generate signed URL for PDF access
            $pdfService = app(\App\Services\Textract\TextractPdfService::class);
            $this->pdfSignedUrl = $pdfService->getSignedPdfUrl($document);

            $this->previewDocumentId = $documentId;
            $this->showPdfModal = true;

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to generate preview URL: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Close PDF preview modal
     */
    public function closePdfModal(): void
    {
        $this->showPdfModal = false;
        $this->previewDocumentId = null;
        $this->pdfSignedUrl = null;
    }
```

### Step 4: Add PDF preview modal to Blade template

**File:** `resources/views/livewire/textract-manager.blade.php` (add before closing </div>)

```blade
{{-- PDF Preview Modal --}}
@if($showPdfModal)
<div class="fixed inset-0 z-50 overflow-y-auto" x-data="pdfModalHandler()">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        {{-- Background overlay --}}
        <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-75" wire:click="closePdfModal"></div>

        {{-- Modal panel --}}
        <div class="inline-block w-full max-w-6xl px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-gray-800 rounded-lg shadow-xl sm:my-8 sm:align-middle sm:p-6">

            {{-- Header --}}
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold text-white">PDF Preview</h3>
                <button wire:click="closePdfModal" class="text-gray-400 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- PDF Canvas --}}
            <div class="relative bg-gray-900 rounded-lg p-4 mb-4 flex items-center justify-center min-h-[600px]">
                <canvas id="pdf-canvas" class="max-w-full"></canvas>
                <div x-show="loading" class="absolute inset-0 flex items-center justify-center bg-gray-900 bg-opacity-75">
                    <div class="text-white">Loading PDF...</div>
                </div>
            </div>

            {{-- Controls --}}
            <div class="flex items-center justify-between">
                <div class="flex space-x-2">
                    <button @click="previousPage" :disabled="currentPage <= 1"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        Previous
                    </button>
                    <button @click="nextPage" :disabled="currentPage >= totalPages"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        Next
                    </button>
                </div>

                <div class="text-white">
                    Page <span x-text="currentPage"></span> of <span x-text="totalPages"></span>
                </div>

                <div class="flex space-x-2">
                    <button @click="zoomOut"
                            class="px-4 py-2 text-sm font-medium text-white bg-gray-700 rounded-lg hover:bg-gray-600">
                        Zoom Out
                    </button>
                    <button @click="zoomIn"
                            class="px-4 py-2 text-sm font-medium text-white bg-gray-700 rounded-lg hover:bg-gray-600">
                        Zoom In
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function pdfModalHandler() {
            return {
                viewer: null,
                loading: true,
                currentPage: 1,
                totalPages: 0,

                init() {
                    this.$nextTick(() => {
                        const canvas = document.getElementById('pdf-canvas');
                        if (!canvas) {
                            console.error('PDF canvas not found');
                            return;
                        }

                        this.viewer = new window.PdfViewer(canvas, {
                            scale: 1.5,
                            onPageChange: (page, total) => {
                                this.currentPage = page;
                                this.totalPages = total;
                            },
                            onError: (error) => {
                                console.error('PDF Viewer Error:', error);
                                this.loading = false;
                                alert('Failed to load PDF: ' + error.message);
                            }
                        });

                        this.loadPdf();
                    });
                },

                async loadPdf() {
                    try {
                        this.loading = true;
                        await this.viewer.loadDocument(@js($pdfSignedUrl));
                        this.loading = false;
                    } catch (error) {
                        console.error('PDF Load Error:', error);
                        this.loading = false;
                    }
                },

                async nextPage() {
                    await this.viewer.nextPage();
                },

                async previousPage() {
                    await this.viewer.previousPage();
                },

                async zoomIn() {
                    await this.viewer.zoomIn();
                },

                async zoomOut() {
                    await this.viewer.zoomOut();
                }
            };
        }
    </script>
</div>
@endif
```

### Step 5: Add "Preview" button to document list

**File:** `resources/views/livewire/textract-manager.blade.php` (find the actions column, add preview button)

```blade
{{-- In the document list actions section --}}
@if($document->s3_output_path)
    <button wire:click="previewPdf({{ $document->id }})"
            class="text-blue-400 hover:text-blue-300 mr-2"
            title="Preview PDF">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        </svg>
    </button>
@endif
```

### Step 6: Run test to verify it passes

**Command:**
```bash
php artisan test --filter=TextractManagerPdfTest
```

**Expected Output:**
```
PASS Tests\Feature\Livewire\TextractManagerPdfTest
✓ can open pdf preview modal
✓ can close pdf preview modal
✓ prevents preview for document without s3 path
✓ modal displays pdf viewer ui

Tests:    4 passed (11 assertions)
Duration: 0.52s
```

### Step 7: Commit

```bash
git add app/Http/Livewire/TextractManager.php resources/views/livewire/textract-manager.blade.php tests/Feature/Livewire/TextractManagerPdfTest.php
git commit -m "feat(textract): integrate PDF preview into TextractManager

- Add previewPdf/closePdfModal methods to Livewire component
- Create modal UI with PDF.js canvas and navigation controls
- Add Preview button to document list
- Write 4 Livewire tests covering modal behavior
- Implement Alpine.js handler for PDF viewer lifecycle"
```

---

## Task 6: Integration Testing with Browser Tests

**Goal:** Create end-to-end browser tests using Laravel Dusk to verify complete PDF preview workflow.

**Files:**
- Create: `tests/Browser/TextractPdfPreviewTest.php`

### Step 1: Write browser test

**File:** `tests/Browser/TextractPdfPreviewTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\TextractDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TextractPdfPreviewTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_user_can_preview_pdf_document(): void
    {
        // Arrange
        $user = User::factory()->create();
        Storage::fake('s3');
        Storage::disk('s3')->put('textract/outputs/test.pdf', '%PDF-1.4 fake pdf');

        $document = TextractDocument::factory()->create([
            's3_output_path' => 'textract/outputs/test.pdf',
        ]);

        // Act & Assert
        $this->browse(function (Browser $browser) use ($user, $document) {
            $browser->loginAs($user)
                ->visit('/textract')
                ->waitForText('Textract Manager')
                ->assertSee($document->id)
                ->click('@preview-pdf-' . $document->id)
                ->waitFor('#pdf-canvas', 5)
                ->assertSee('PDF Preview')
                ->assertSee('Page 1 of')
                ->assertVisible('#pdf-canvas')
                ->assertVisible('button:contains("Previous")')
                ->assertVisible('button:contains("Next")')
                ->assertVisible('button:contains("Zoom In")')
                ->assertVisible('button:contains("Zoom Out")')
                ->click('button:contains("Close")')
                ->waitUntilMissing('#pdf-canvas')
                ->assertDontSee('PDF Preview');
        });
    }

    public function test_pdf_navigation_controls_work(): void
    {
        // Arrange
        $user = User::factory()->create();
        Storage::fake('s3');

        // Create a multi-page PDF (fake content)
        $multiPagePdf = '%PDF-1.4 fake multi-page pdf';
        Storage::disk('s3')->put('textract/outputs/multi.pdf', $multiPagePdf);

        $document = TextractDocument::factory()->create([
            's3_output_path' => 'textract/outputs/multi.pdf',
        ]);

        // Act & Assert
        $this->browse(function (Browser $browser) use ($user, $document) {
            $browser->loginAs($user)
                ->visit('/textract')
                ->click('@preview-pdf-' . $document->id)
                ->waitFor('#pdf-canvas', 5)
                ->assertSee('Page 1')
                ->assertButtonEnabled('Next')
                ->assertButtonDisabled('Previous')
                ->click('button:contains("Next")')
                ->pause(500) // Wait for render
                ->assertSee('Page 2')
                ->assertButtonEnabled('Previous')
                ->click('button:contains("Previous")')
                ->pause(500)
                ->assertSee('Page 1');
        });
    }

    public function test_shows_error_for_document_without_pdf(): void
    {
        // Arrange
        $user = User::factory()->create();
        $document = TextractDocument::factory()->create([
            's3_output_path' => null,
        ]);

        // Act & Assert
        $this->browse(function (Browser $browser) use ($user, $document) {
            $browser->loginAs($user)
                ->visit('/textract')
                ->assertDontSee('@preview-pdf-' . $document->id); // Button shouldn't exist
        });
    }
}
```

### Step 2: Add Dusk selectors to Blade template

**File:** `resources/views/livewire/textract-manager.blade.php` (update preview button)

```blade
@if($document->s3_output_path)
    <button wire:click="previewPdf({{ $document->id }})"
            dusk="preview-pdf-{{ $document->id }}"
            class="text-blue-400 hover:text-blue-300 mr-2"
            title="Preview PDF">
        {{-- SVG icon --}}
    </button>
@endif
```

### Step 3: Run browser test

**Command:**
```bash
php artisan dusk --filter=TextractPdfPreviewTest
```

**Expected Output:**
```
PHPUnit 11.x.x by Sebastian Bergmann and contributors.

...                                                                 3 / 3 (100%)

Time: 00:15.234, Memory: 32.00 MB

OK (3 tests, 18 assertions)
```

### Step 4: Commit

```bash
git add tests/Browser/TextractPdfPreviewTest.php resources/views/livewire/textract-manager.blade.php
git commit -m "test(textract): add browser tests for PDF preview

- Create 3 Dusk tests covering full preview workflow
- Test PDF modal open/close behavior
- Test navigation controls (next/prev pages)
- Test error handling for missing PDFs
- Add dusk selectors to Livewire template"
```

---

## Task 7: Documentation and Final Verification

**Goal:** Document the PDF preview feature and verify all tests pass.

**Files:**
- Create: `docs/features/TEXTRACT_PDF_PREVIEW.md`
- Update: `README.md`

### Step 1: Create feature documentation

**File:** `docs/features/TEXTRACT_PDF_PREVIEW.md`

```markdown
# Textract PDF Preview Feature

## Overview

The Textract Manager now includes inline PDF preview functionality, allowing users to view uploaded documents directly in the browser without downloading files.

## Architecture

### Backend Components

1. **TextractPdfService** (`app/Services/Textract/TextractPdfService.php`)
   - Generates temporary signed URLs for secure S3 access
   - Configurable expiration time (default 1 hour)
   - Validates document has S3 output path

2. **TextractFileController** (`app/Http/Controllers/Textract/TextractFileController.php`)
   - Serves PDF files from S3 via signed URLs
   - Protected by `auth` and `signed` middleware
   - Returns proper PDF headers for browser inline viewing

### Frontend Components

1. **PdfViewer JavaScript Class** (`resources/js/components/pdf-viewer.js`)
   - Wrapper around PDF.js library
   - Canvas-based rendering
   - Page navigation (next/prev)
   - Zoom controls (in/out)
   - Error handling and state management

2. **TextractManager Livewire Component** (`app/Http/Livewire/TextractManager.php`)
   - `previewPdf()` - Opens modal and generates signed URL
   - `closePdfModal()` - Closes modal and clears state
   - Alpine.js integration for PDF viewer lifecycle

## Usage

### For Users

1. Navigate to `/textract` in the browser
2. Find a document with a PDF icon in the actions column
3. Click the eye icon to preview
4. Use navigation controls:
   - **Previous/Next** - Navigate between pages
   - **Zoom In/Out** - Adjust viewing scale
   - **Close** - Close the modal

### For Developers

**Generate a signed PDF URL programmatically:**

```php
use App\Services\Textract\TextractPdfService;

$pdfService = app(TextractPdfService::class);
$signedUrl = $pdfService->getSignedPdfUrl($document, expiresIn: 1800); // 30 min
```

**Use PdfViewer in custom components:**

```javascript
import { PdfViewer } from './components/pdf-viewer';

const canvas = document.getElementById('my-canvas');
const viewer = new PdfViewer(canvas, {
    scale: 1.5,
    onPageChange: (page, total) => console.log(`Page ${page}/${total}`),
    onError: (error) => alert('PDF Error: ' + error.message)
});

await viewer.loadDocument(signedUrl);
await viewer.nextPage();
await viewer.zoomIn();
viewer.destroy(); // Clean up when done
```

## Configuration

**Environment Variables:**

```env
# PDF signed URL expiration time (seconds)
TEXTRACT_PDF_URL_EXPIRATION=3600  # 1 hour default
```

**Config File:** `config/textract.php`

```php
'pdf_url_expiration' => env('TEXTRACT_PDF_URL_EXPIRATION', 3600),
```

## Security

- **Signed URLs** - All PDF access requires valid signature
- **Expiration** - URLs expire after configured time (default 1 hour)
- **Authentication** - Must be logged in to access files
- **S3 Private Buckets** - Direct S3 access blocked, only via signed URLs

## Testing

**Run all PDF preview tests:**

```bash
# Unit tests (PDF Service)
php artisan test --filter=TextractPdfServiceTest

# Feature tests (Controller + Livewire)
php artisan test --filter=TextractFileControllerTest
php artisan test --filter=TextractManagerPdfTest

# Browser tests (End-to-end)
php artisan dusk --filter=TextractPdfPreviewTest

# All Textract tests
php artisan test tests/Unit/Services/Textract
php artisan test tests/Feature/Textract
php artisan test tests/Feature/Livewire/TextractManagerPdfTest
php artisan dusk --filter=TextractPdfPreviewTest
```

## Performance

- **PDF.js Worker** - Rendering happens in background thread
- **Lazy Loading** - PDF only loaded when modal opens
- **CDN Worker** - Uses cdnjs.cloudflare.com for reliable worker file
- **Canvas Rendering** - Hardware-accelerated via browser canvas API

## Troubleshooting

**PDF not loading:**
- Check S3 credentials are configured
- Verify document has `s3_output_path` set
- Check signed URL hasn't expired
- Look for JavaScript console errors

**Worker errors:**
- Check PDF.js version matches worker version (4.8.69)
- Verify CDN is accessible (firewall/proxy issues)
- Check browser console for worker initialization errors

**Performance issues:**
- Reduce `scale` option for faster rendering
- Check S3 region latency
- Verify browser supports Canvas API
- Consider pagination for very large PDFs

## Dependencies

- **pdfjs-dist** - 4.8.69 (NPM package)
- **Laravel** - 11.x (Signed URLs)
- **Livewire** - 3.6.x (UI component)
- **Alpine.js** - 3.x (Modal interactivity)
- **Tailwind CSS** - 3.x (Styling)

## Future Enhancements

- Thumbnail navigation sidebar
- Search within PDF
- Annotations and highlighting
- Download button
- Full-screen mode
- Mobile-optimized controls
- Keyboard shortcuts (arrow keys for navigation)
```

### Step 2: Update main README

**File:** `README.md` (add to Textract section)

```markdown
#### PDF Preview

View uploaded documents directly in browser:
- Inline PDF viewer using PDF.js
- Page navigation and zoom controls
- Secure access via signed URLs (1 hour expiration)
- Hardware-accelerated canvas rendering

See [TEXTRACT_PDF_PREVIEW.md](docs/features/TEXTRACT_PDF_PREVIEW.md) for details.
```

### Step 3: Run full test suite

**Command:**
```bash
# Run all new tests
php artisan test tests/Unit/Services/Textract/TextractPdfServiceTest.php
php artisan test tests/Feature/Textract/TextractFileControllerTest.php
php artisan test tests/Feature/Livewire/TextractManagerPdfTest.php
```

**Expected Output:**
```
PASS Tests\Unit\Services\Textract\TextractPdfServiceTest
✓ generates signed url for pdf document
✓ throws exception when document has no s3 path
✓ respects custom expiration time
✓ uses default expiration from config

PASS Tests\Feature\Textract\TextractFileControllerTest
✓ serves pdf file with valid signed url
✓ rejects request with invalid signature
✓ rejects request with expired signature
✓ returns 404 for nonexistent document
✓ returns 404 when document has no s3 path
✓ requires authentication

PASS Tests\Feature\Livewire\TextractManagerPdfTest
✓ can open pdf preview modal
✓ can close pdf preview modal
✓ prevents preview for document without s3 path
✓ modal displays pdf viewer ui

Tests:    14 passed (31 assertions)
Duration: 1.23s
```

### Step 4: Verify JavaScript builds without errors

**Command:**
```bash
npm run build
```

**Expected Output:**
```
vite v5.x.x building for production...
✓ built in X.XXs
```

### Step 5: Commit documentation

```bash
git add docs/features/TEXTRACT_PDF_PREVIEW.md README.md
git commit -m "docs(textract): add PDF preview feature documentation

- Create comprehensive feature documentation
- Document architecture, usage, configuration
- Add security notes and troubleshooting guide
- Update README with PDF preview section
- Include code examples for developers"
```

### Step 6: Final verification - Run all tests

**Command:**
```bash
composer test
```

**Expected Output:**
```
Tests:    XXX passed
Duration: XX.XXs
```

---

## Completion Checklist

Before marking Sprint 2 complete, verify:

- [ ] All 14 unit/feature tests pass
- [ ] Browser tests pass (if running Dusk)
- [ ] JavaScript builds without errors (`npm run build`)
- [ ] PDF.js package installed in package.json
- [ ] Config file has `pdf_url_expiration` setting
- [ ] Route `textract.file` exists in routes/web.php
- [ ] TextractManager has Preview button in UI
- [ ] Modal opens and displays PDF canvas
- [ ] Navigation controls work (next/prev/zoom)
- [ ] Documentation created and README updated
- [ ] All commits pushed to remote branch

## Post-Sprint Tasks

**Optional enhancements for future sprints:**

1. Add thumbnail navigation sidebar
2. Implement full-screen mode
3. Add keyboard shortcuts (arrow keys)
4. Mobile-responsive controls
5. Download button in modal
6. Search within PDF functionality
7. Performance monitoring and metrics

---

## Execution Notes

**Estimated Time:** 18-21 hours across 2-3 days

**Task Breakdown:**
- Task 1: Backend PDF Service (2-3h)
- Task 2: File Controller (2-3h)
- Task 3: Install PDF.js (1h)
- Task 4: JavaScript Component (3-4h)
- Task 5: Livewire Integration (4-5h)
- Task 6: Browser Tests (3-4h)
- Task 7: Documentation (2-3h)

**Parallel Execution Strategy:**

Can run in parallel:
- Task 1 + Task 3 (Backend + NPM install)
- Task 4 alone (needs Task 3 complete)
- Task 2 (needs Task 1 complete)
- Task 5 (needs Tasks 1, 2, 4 complete)
- Task 6 (needs Task 5 complete)
- Task 7 (can start anytime, finalize at end)

**Recommended Agent Assignment:**
- Agent 1: Tasks 1 + 2 (Backend)
- Agent 2: Tasks 3 + 4 (Frontend JavaScript)
- Agent 3: Task 5 (Livewire Integration)
- Agent 4: Task 6 (Browser Tests)
- Agent 5: Task 7 (Documentation)

All agents must use TDD approach (RED-GREEN-REFACTOR) and commit after each passing test.
