# PDF.js Integration Research & Design Document
## Textract Manager - Sprint 2 Preparation

**Date:** 2025-11-15
**Scope:** Document preview functionality for Textract Manager
**Status:** ✅ RESEARCH PHASE - Ready for Implementation Planning

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [PDF.js Integration Methods](#pdfjs-integration-methods)
3. [Signed URL Generation Strategy](#signed-url-generation-strategy)
4. [Architecture Design](#architecture-design)
5. [UI/UX Design](#uiux-design)
6. [Security Considerations](#security-considerations)
7. [Performance Optimization](#performance-optimization)
8. [Implementation Plan](#implementation-plan)
9. [Code Snippets](#code-snippets)
10. [Testing Strategy](#testing-strategy)

---

## Executive Summary

### Recommendation Summary

**Best Approach:** CDN + Local NPM + Livewire Component

- **PDF.js Distribution:** Use official Mozilla CDN + local worker file
- **Signed URLs:** Laravel-native signed URLs with custom 1-hour expiration
- **Component:** Dedicated Livewire component (`PdfViewer`) with modal display
- **Storage:** S3 for PDFs + local fallback for local files
- **Security:** Middleware-protected routes with signature verification
- **Performance:** Lazy-load PDFs, client-side caching, efficient pagination

**Estimated Effort:** 2-3 days (research: complete, design: this document, implementation: next phase)

### Key Features
- ✅ View original PDF from S3
- ✅ View searchable PDF from S3 output
- ✅ Zoom/pan controls
- ✅ Page navigation
- ✅ Side-by-side comparison (optional)
- ✅ Mobile responsive
- ✅ Keyboard shortcuts

---

## PDF.js Integration Methods

### Option 1: CDN-only (Simplest)

**Pros:**
- No NPM installation
- Smallest bundle size
- No build step required
- Works immediately

**Cons:**
- External dependency on Mozilla CDN
- Internet required for worker file
- No version control in package.json
- Potential latency from CDN

**Implementation:**
```html
<!-- In blade template -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.0.379/pdf.min.js"></script>
<script>
    pdfjsLib.GlobalWorkerOptions.workerSrc =
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.0.379/pdf.worker.min.js';
</script>
```

**Best For:** Quick prototypes, offline not critical

---

### Option 2: NPM Package + CDN Worker (Balanced)

**Pros:**
- Main library from NPM (versioned, controlled)
- Worker file can stay on CDN (lighter)
- Good balance of control + simplicity
- Standard in modern Laravel projects
- Webpack/Vite integration works cleanly

**Cons:**
- Requires build step (already have Vite)
- Worker file still from external source
- Slight complexity

**Implementation:**
```bash
npm install pdfjs-dist
```

```javascript
// resources/js/app.js
import * as pdfjsLib from 'pdfjs-dist/legacy/build/pdf.js';

// Load worker from CDN (Monaco can host locally too)
pdfjsLib.GlobalWorkerOptions.workerSrc =
    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.0.379/pdf.worker.min.js';

window.pdfjsLib = pdfjsLib; // Make available globally
```

**Best For:** Production applications with control requirements

---

### Option 3: Fully Local NPM (Maximum Control)

**Pros:**
- Complete offline capability
- Full version control
- No external dependencies
- Fastest loading

**Cons:**
- Larger bundle size (worker + main)
- More complex configuration
- Vite needs proper import handling

**Implementation:**
```bash
npm install pdfjs-dist
```

```javascript
// resources/js/pdf-viewer.js
import * as pdfjsLib from 'pdfjs-dist/legacy/build/pdf.js';
import worker from 'pdfjs-dist/legacy/build/pdf.worker.js?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = worker;
export default pdfjsLib;
```

**Best For:** Offline-first applications, high security requirements

---

### **RECOMMENDATION: Option 2 (NPM + CDN Worker)**

**Why this approach:**
1. ✅ Aligns with existing Vite setup
2. ✅ Version-controlled through package.json
3. ✅ Minimal build overhead
4. ✅ Standard in Laravel ecosystem
5. ✅ Easy to switch to fully local if needed
6. ✅ No external dependency for main library

```bash
# Installation
npm install pdfjs-dist

# Version: ^4.0.379 (latest as of research date)
```

---

## Signed URL Generation Strategy

### Current State Analysis

**Existing Implementation:**
```php
// app/Http/Controllers/EvidenceAssetController.php
// Uses Laravel's 'signed' middleware for URL verification
// Decrypts path and serves local file
```

**Why Adapt for S3:**
- Textract Manager uses S3 for storage (configured in `.env`)
- PDFs stored at:
  - **Input:** `s3://bucket/textract/input/{jobId}/input.pdf`
  - **Output:** `s3://bucket/textract/output/{jobId}/output.pdf`
  - **JSON:** `s3://bucket/textract/json/{jobId}/data.json`

---

### Signed URL Implementation

#### Method 1: Laravel Built-in Signed URLs (Recommended)

**Pros:**
- Native Laravel support
- Uses existing middleware
- Familiar pattern
- Easy token rotation

**Cons:**
- Token attached to URL (query string)
- Need custom expiration logic

```php
// Generate signed URL
$url = URL::temporarySignedRoute(
    'textract.file.view',
    now()->addHour(),
    ['jobId' => $jobId, 'type' => 'output'] // input|output|json
);
```

#### Method 2: AWS S3 Pre-signed URLs (Native)

**Pros:**
- Built into AWS SDK
- S3 handles all verification
- No server overhead for validation
- Standard AWS pattern
- Can set any expiration

**Cons:**
- Exposes AWS SDK details
- Credentials must be safe
- More complex setup

```php
// Generate AWS pre-signed URL
$s3Client = Storage::disk('s3')->getClient();
$cmd = $s3Client->getCommand(
    'GetObject',
    ['Bucket' => env('AWS_BUCKET'), 'Key' => $s3Key]
);
$request = $s3Client->createPresignedRequest($cmd, '+20 minutes');
$presignedUrl = (string)$request->getUri();
```

#### Method 3: Custom JWT Token (Advanced)

**Pros:**
- Full control over claims
- Stateless verification
- Can encode metadata
- Good for API usage

**Cons:**
- Need JWT library
- More complexity
- Overkill for file serving

---

### **RECOMMENDATION: Hybrid Approach**

**Use Laravel Signed URLs as primary layer:**

```php
// Controller: Generate signed URL
Route::get('/api/textract/files/{jobId}', [TextractFileController::class, 'show'])
    ->middleware(['auth:api', 'signed'])
    ->name('textract.file.view');

// Expires in 1 hour from generation
// Signature validates user + timestamp + jobId
```

**Falls back to S3 pre-signed URL if serving from S3:**

```php
// For direct S3 downloads (skip server)
// Use AWS pre-signed URLs (optional feature)
```

**Benefits:**
- Simple, secure, standard
- Single signature verification per request
- No database lookups needed
- 1-hour expiration covers typical user session
- Easy to implement logging/audit trail

---

## Architecture Design

### Component Structure

```
resources/
├── js/
│   ├── app.js                          (import pdfjs-dist)
│   └── pdf-viewer.js                   (PDF.js integration)
├── views/
│   └── livewire/
│       ├── textract-manager.blade.php  (existing, add buttons)
│       └── pdf-viewer/
│           ├── modal.blade.php         (modal container)
│           ├── viewer.blade.php        (PDF canvas + controls)
│           └── comparison.blade.php    (side-by-side optional)

app/
├── Http/
│   ├── Controllers/
│   │   └── TextractFileController.php  (NEW - signed URLs)
│   └── Livewire/
│       ├── TextractManager.php         (MODIFY - add buttons)
│       └── PdfViewer.php               (NEW - modal component)
├── Services/
│   └── TextractPdfService.php          (NEW - S3 file logic)
└── Models/
    └── TextractJob.php                 (existing, unchanged)
```

### Data Flow

```
User clicks "View PDF" button
    ↓
TextractManager fires: viewPdf($jobId, 'output')
    ↓
Livewire component loads PdfViewer modal
    ↓
PdfViewer requests signed URL from TextractFileController
    ↓
Controller generates 1-hour signed URL
    ↓
Frontend JavaScript receives signed URL
    ↓
PDF.js loads PDF from signed URL
    ↓
User sees PDF with controls (zoom, pan, page nav)
    ↓
Signed URL expires after 1 hour (automatic)
```

### Component Relationships

```
TextractManager (parent)
├── Job List
├── Job Details
└── PdfViewer Modal (child)
    ├── PDF Canvas
    ├── Controls (zoom, pan, navigation)
    └── Metadata Display

TextractFileController
├── Authentication check
├── Signature verification
├── File existence check
├── Signed URL generation
└── File streaming (S3)
```

---

## UI/UX Design

### Design System

**Existing Design:**
- Dark theme: `--bg: #0f172a`
- Card background: `--card: #111827`
- Accent color: `--accent: #22d3ee` (cyan)
- Border: `--border: #1f2937`

**Maintain consistency** in PDF viewer design.

---

### View PDF Button (in Job Card)

**Location:** `textract-manager.blade.php` - Job actions section

**Current:**
```blade
<button class="btn btn-sm btn-info" wire:click="viewContent({{ $job->id }})">
    👁️ View Content
</button>
```

**Add:**
```blade
{{-- New buttons for PDF viewing --}}
@if($job->status === 'succeeded' && $job->extracted_content)
    <button class="btn btn-sm btn-info"
            wire:click="showPdfPreview({{ $job->id }}, 'output')"
            title="View searchable PDF">
        📄 View Searchable PDF
    </button>

    <button class="btn btn-sm"
            wire:click="showPdfPreview({{ $job->id }}, 'input')"
            title="View original PDF">
        📋 View Original
    </button>
@endif

{{-- Optional: Comparison button --}}
@if($job->status === 'succeeded')
    <button class="btn btn-sm btn-warn"
            wire:click="showComparison({{ $job->id }})"
            title="Compare original vs searchable">
        🔄 Compare
    </button>
@endif
```

---

### PDF Viewer Modal Design

**Modal Structure:**
```
┌─────────────────────────────────────────────────────────┐
│ 📄 Document Name | Page 5 of 12                    [×]  │ ← Header
├─────────────────────────────────────────────────────────┤
│ [–] [+] Fit | Pan | Download | Full Screen              │ ← Toolbar
├─────────────────────────────────────────────────────────┤
│                                                           │
│                      PDF Canvas                          │
│                  (rendered by PDF.js)                    │
│                   [responsive height]                    │
│                                                           │
├─────────────────────────────────────────────────────────┤
│ [< Prev] Page: [5] of [12]  [Next >]                    │ ← Footer
└─────────────────────────────────────────────────────────┘
```

**Features:**
- ✅ Full modal width on desktop (90vw, max 1000px)
- ✅ Responsive on mobile (95vw)
- ✅ Dark theme matching app design
- ✅ Sticky toolbar for easy access
- ✅ Keyboard shortcuts support

---

### Controls & Interactions

#### **Toolbar Controls:**

| Control | Icon | Action | Shortcut |
|---------|------|--------|----------|
| Zoom Out | `–` | Decrease zoom (min 50%) | `-` or `Ctrl+-` |
| Zoom In | `+` | Increase zoom (max 200%) | `+` or `Ctrl++` |
| Fit Width | `↔` | Fit page to window width | `w` |
| Fit Page | `⊡` | Fit entire page | `p` |
| Pan/Hand | ✋ | Toggle pan mode | `h` |
| Download | ⬇ | Download PDF file | `d` |
| Full Screen | ⛶ | Toggle fullscreen | `f` |

#### **Page Navigation:**

| Control | Keyboard | Mouse Wheel |
|---------|----------|-------------|
| Next Page | Right arrow, PageDown | Scroll down |
| Prev Page | Left arrow, PageUp | Scroll up |
| First Page | Home | - |
| Last Page | End | - |
| Go to Page | `g` then type | - |

#### **Additional Features:**

- **Text Selection:** Copy text from PDF
- **Search:** Find text in PDF (Ctrl+F)
- **Zoom Memory:** Remember zoom level per session
- **Loading State:** Progress bar while PDF loads
- **Error Handling:** User-friendly error messages

---

### Modal CSS Styling

```css
/* Maintain existing design variables */
.pdf-viewer-modal {
    max-width: 1000px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    display: flex;
    flex-direction: column;
    max-height: 90vh;
}

.pdf-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pdf-toolbar {
    padding: 12px 16px;
    background: #0b1220;
    border-bottom: 1px solid var(--border);
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.pdf-canvas-container {
    flex: 1;
    overflow: auto;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 20px;
}

.pdf-canvas {
    max-width: 100%;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

.pdf-footer {
    padding: 12px 16px;
    border-top: 1px solid var(--border);
    background: #0b1220;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Responsive */
@media (max-width: 768px) {
    .pdf-viewer-modal {
        max-width: 95vw;
        max-height: 95vh;
    }

    .pdf-toolbar {
        flex-direction: column;
    }
}
```

---

### Mobile Responsive Design

**Desktop (> 768px):**
- Full modal with all controls
- Side-by-side comparison available
- Normal font sizes

**Tablet (768px - 1024px):**
- Stacked toolbar controls
- Full PDF viewer
- Touch-friendly buttons (larger)

**Mobile (< 768px):**
- Toolbar in horizontal scroll if needed
- Full-screen optimized
- Simplified controls (hide less-used features)
- Touch gestures for zoom/pan

**Touch Gestures:**
- Pinch: Zoom in/out
- Two-finger pan: Move around page
- Tap: Show/hide toolbar
- Swipe left/right: Next/prev page

---

## Security Considerations

### URL Signature Verification

**Implementation in TextractFileController:**

```php
// 1. Route protected by 'signed' middleware
// 2. Signature includes: user ID, timestamp, jobId
// 3. Expires after 1 hour
// 4. Cannot be forged without app key
// 5. Cannot be reused on different files
```

**Request Flow:**
```
Incoming request with signed URL
    ↓
Middleware verifies signature
    ↓
Middleware verifies expiration (not older than 1 hour)
    ↓
Controller checks: does user have access to this job?
    ↓
Controller serves file from S3
```

### Access Control

**Who can view PDFs:**
1. ✅ Authenticated users (middleware: 'auth')
2. ✅ With valid signed URL signature
3. ✅ Within 1-hour expiration window
4. ✅ Only for jobs they have access to (future: case-level permissions)

**Implementation:**
```php
// TextractFileController.php
public function show(Request $request, int $jobId, string $type)
{
    $request->validate([/* signature validated by middleware */]);

    // Check: does this job exist?
    $job = TextractJob::findOrFail($jobId);

    // Check: does user have access? (can add case-level check)
    // $this->authorize('view', $job);

    // Check: does S3 file exist?
    $s3Key = $this->getS3Key($jobId, $type);
    if (!Storage::disk('s3')->exists($s3Key)) {
        abort(404, 'File not found');
    }

    // Return file (streaming or redirect)
    return $this->serveFile($s3Key);
}
```

### CORS Configuration

**If frontend and backend on different domains:**

```php
// config/cors.php - may need update
'allowed_origins' => ['https://yourdomain.com'],
'allowed_methods' => ['GET'],
'allowed_headers' => ['Content-Type', 'Authorization'],
```

**For same-origin (typical), no CORS needed.**

### S3 Bucket Configuration

**Security best practices:**
1. ✅ Bucket is private (not public)
2. ✅ Files accessible only via signed URLs or server
3. ✅ Encryption enabled (AES-256 recommended)
4. ✅ Versioning enabled (optional, for audit trail)
5. ✅ Server-side logging enabled
6. ✅ Lifecycle policies to delete old versions

**In application:**
- ✅ Never expose S3 credentials in URLs
- ✅ Never make buckets public
- ✅ Always use signed URLs with expiration
- ✅ Rotate AWS keys regularly
- ✅ Monitor S3 access logs

---

## Performance Optimization

### PDF.js Performance Tips

#### 1. **Lazy Loading**
```javascript
// Load PDF only when modal opened
// Don't preload all PDFs at page load
document.addEventListener('openPdfModal', async function(jobId) {
    const pdf = await pdfjsLib.getDocument(signedUrl).promise;
    // Render first page only
    renderPage(pdf, 1);
});
```

#### 2. **Partial Rendering**
```javascript
// Render only visible pages (not all pages)
// Destroy off-screen pages to save memory
const renderPage = async (pdf, pageNum) => {
    const page = await pdf.getPage(pageNum);
    const canvas = document.getElementById('pdf-canvas');
    const context = canvas.getContext('2d');

    const viewport = page.getViewport({ scale: 1.5 });
    canvas.width = viewport.width;
    canvas.height = viewport.height;

    await page.render({ canvasContext: context, viewport }).promise;
};
```

#### 3. **Debounced Zoom**
```javascript
// Debounce zoom events to avoid excessive rendering
let zoomTimeout;
document.addEventListener('wheel', (e) => {
    if (e.ctrlKey) {
        e.preventDefault();
        clearTimeout(zoomTimeout);
        zoomTimeout = setTimeout(() => {
            updateZoom(e.deltaY);
            renderCurrentPage();
        }, 100);
    }
});
```

#### 4. **Browser Caching**
```javascript
// Cache rendered pages in memory (with size limit)
class PageCache {
    constructor(maxSize = 10) {
        this.cache = new Map();
        this.maxSize = maxSize;
    }

    get(pageNum) {
        return this.cache.get(pageNum);
    }

    set(pageNum, canvas) {
        if (this.cache.size >= this.maxSize) {
            const firstKey = this.cache.keys().next().value;
            this.cache.delete(firstKey);
        }
        this.cache.set(pageNum, canvas);
    }
}
```

#### 5. **Signed URL Caching**
```javascript
// Cache signed URLs (still expire after 1 hour)
const signedUrlCache = {
    set(jobId, type, url) {
        sessionStorage.setItem(`pdf_${jobId}_${type}`, JSON.stringify({
            url,
            timestamp: Date.now()
        }));
    },

    get(jobId, type) {
        const cached = JSON.parse(sessionStorage.getItem(`pdf_${jobId}_${type}`));
        if (cached && Date.now() - cached.timestamp < 3600000) { // < 1 hour
            return cached.url;
        }
        return null;
    }
};
```

### Bundle Size Optimization

**PDF.js sizes:**
- Main library: ~180 KB (gzipped)
- Worker: ~32 KB (gzipped)
- **Total:** ~212 KB added to bundle

**Not bad for comprehensive PDF viewing capability.**

### S3 Performance

**Options for serving PDFs:**

| Option | Speed | Cost | Complexity |
|--------|-------|------|-----------|
| Stream from S3 | Fast | Low | Low |
| Redirect to S3 signed URL | Very fast | Very low | Very low |
| CloudFront CDN | Extremely fast | Medium | Medium |
| Download then serve | Slow | High | Medium |

**Recommendation:**
- **Direct S3 streaming** (no extra cost, simple)
- **CloudFront CDN** (optional, if serving many PDFs)

---

## Implementation Plan

### Phase 1: Setup & Infrastructure (4-6 hours)

#### Task 1.1: Install PDF.js
```bash
npm install pdfjs-dist
npm run build  # Vite will bundle it
```

**Files to create:**
- None (NPM package only)

**Files to modify:**
- `package.json` (add dependency)

#### Task 1.2: Create TextractFileController
**File:** `app/Http/Controllers/TextractFileController.php`

**Responsibilities:**
- Generate signed URLs with 1-hour expiration
- Verify access to TextractJob
- Stream files from S3
- Handle errors gracefully

**Methods:**
- `show(Request $request, int $jobId, string $type)` - Get signed URL and serve
- `download(Request $request, int $jobId, string $type)` - Download file
- `getSignedUrl(int $jobId, string $type)` - Generate signed URL for frontend

#### Task 1.3: Add Routes
**File:** `routes/web.php`

**Routes to add:**
```php
Route::middleware('auth')->group(function () {
    // Signed URL generation (for frontend)
    Route::get('/api/textract/files/{jobId}/signed-url',
        [TextractFileController::class, 'getSignedUrl'])
        ->name('textract.signed-url');

    // Actual file serving (protected by 'signed')
    Route::get('/textract/files/{jobId}/{type}',
        [TextractFileController::class, 'show'])
        ->middleware('signed')
        ->name('textract.file');

    // File download
    Route::get('/textract/files/{jobId}/{type}/download',
        [TextractFileController::class, 'download'])
        ->middleware('signed')
        ->name('textract.file.download');
});
```

#### Task 1.4: Create TextractPdfService
**File:** `app/Services/TextractPdfService.php`

**Responsibilities:**
- Get S3 key paths
- Check file existence
- Generate signed URLs
- Stream files

**Methods:**
- `getS3Key(int $jobId, string $type): string`
- `fileExists(int $jobId, string $type): bool`
- `getSignedUrl(int $jobId, string $type, int $minutes = 60): string`
- `streamFile(int $jobId, string $type): StreamResponse`

---

### Phase 2: Frontend Components (1-2 days)

#### Task 2.1: Create PdfViewer Livewire Component
**File:** `app/Http/Livewire/PdfViewer.php`

**Public properties:**
```php
public ?int $jobId = null;
public string $fileType = 'output'; // input|output|json
public string $fileName = '';
public ?string $signedUrl = null;
public bool $isOpen = false;
public bool $isLoading = false;
public string $error = '';
```

**Methods:**
```php
public function open(int $jobId, string $fileType = 'output'): void
public function close(): void
public function loadSignedUrl(): void
public function render(): View
```

#### Task 2.2: Create PdfViewer Blade Template
**File:** `resources/views/livewire/pdf-viewer.blade.php`

**Structure:**
- Modal container (backdrop + modal)
- Header (title + close button)
- Toolbar (zoom, pan, controls)
- Canvas container
- Footer (page navigation)
- Loading state
- Error state

#### Task 2.3: Create PDF.js Integration Script
**File:** `resources/js/pdf-viewer.js`

**Exports:**
- `PdfViewer` class for handling PDF rendering
- Methods for zoom, pan, page navigation
- Event handlers for controls
- Error handling

#### Task 2.4: Update TextractManager Component
**File:** `app/Http/Livewire/TextractManager.php`

**Add methods:**
```php
public function showPdfPreview(int $jobId, string $type = 'output'): void
{
    $this->dispatch('openPdfModal', jobId: $jobId, type: $type);
}

public function showComparison(int $jobId): void
{
    $this->dispatch('openComparisonModal', jobId: $jobId);
}
```

**Add buttons to modal:**
```blade
@if($isExpanded && $job->status === 'succeeded')
    <button wire:click="showPdfPreview({{ $job->id }}, 'output')">
        📄 View Searchable
    </button>
    <button wire:click="showPdfPreview({{ $job->id }}, 'input')">
        📋 View Original
    </button>
@endif
```

#### Task 2.5: Create Comparison Component (Optional)
**File:** `app/Http/Livewire/PdfComparison.php`

**Responsibilities:**
- Display two PDFs side-by-side
- Synchronized scrolling
- Toggle between PDFs
- Highlight differences (if possible)

---

### Phase 3: Testing (4-6 hours)

#### Task 3.1: Unit Tests - TextractPdfService
**File:** `tests/Unit/Services/TextractPdfServiceTest.php`

**Tests:**
- `test_get_s3_key_returns_correct_path()`
- `test_file_exists_checks_s3_correctly()`
- `test_signed_url_includes_expiration()`
- `test_signed_url_includes_job_id()`

#### Task 3.2: Feature Tests - TextractFileController
**File:** `tests/Feature/TextractFileControllerTest.php`

**Tests:**
- `test_authenticated_user_can_get_signed_url()`
- `test_unauthenticated_user_cannot_access_files()`
- `test_signed_url_expires_after_one_hour()`
- `test_invalid_signature_rejected()`
- `test_file_not_found_returns_404()`
- `test_can_download_pdf()`

#### Task 3.3: Component Tests - PdfViewer
**File:** `tests/Feature/Livewire/PdfViewerTest.php`

**Tests:**
- `test_component_renders_modal()`
- `test_modal_opens_on_dispatch()`
- `test_modal_closes_on_button_click()`
- `test_loads_signed_url_on_open()`
- `test_handles_missing_file_gracefully()`

#### Task 3.4: Browser Tests - UI Interactions
**File:** `tests/Browser/TextractPdfViewerTest.php`

**Tests:**
- `test_can_view_pdf_in_modal()`
- `test_zoom_controls_work()`
- `test_page_navigation_works()`
- `test_keyboard_shortcuts_work()`
- `test_download_button_works()`

---

### Phase 4: Documentation & Refinement (2-4 hours)

#### Task 4.1: User Documentation
**File:** `docs/TEXTRACT_PDF_VIEWER.md`

- How to view PDFs
- Keyboard shortcuts
- Troubleshooting
- Performance tips

#### Task 4.2: Developer Documentation
**File:** `docs/TEXTRACT_PDF_VIEWER_DEVELOPMENT.md`

- Architecture overview
- Component lifecycle
- PDF.js API reference
- Security considerations

#### Task 4.3: Code Review & Polish
- Address feedback
- Optimize performance
- Add error handling
- Improve accessibility

---

## Code Snippets

### TextractFileController

```php
<?php

namespace App\Http\Controllers;

use App\Models\TextractJob;
use App\Services\TextractPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class TextractFileController extends Controller
{
    protected TextractPdfService $pdfService;

    public function __construct(TextractPdfService $pdfService)
    {
        $this->pdfService = $pdfService;
    }

    /**
     * Get signed URL for PDF (expires in 1 hour)
     */
    public function getSignedUrl(Request $request, int $jobId, string $type = 'output')
    {
        $request->validate([
            'type' => 'in:input,output,json',
        ]);

        // Verify job exists
        $job = TextractJob::findOrFail($jobId);

        // TODO: Add case-level permission check if needed
        // $this->authorize('view', $job);

        try {
            // Generate signed URL (1 hour expiration)
            $signedUrl = URL::temporarySignedRoute(
                'textract.file',
                now()->addHour(),
                ['jobId' => $jobId, 'type' => $type]
            );

            return response()->json([
                'signed_url' => $signedUrl,
                'expires_at' => now()->addHour(),
                'type' => $type,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to generate signed URL', [
                'job_id' => $jobId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to generate preview URL',
            ], 500);
        }
    }

    /**
     * Serve file with signature verification (via 'signed' middleware)
     */
    public function show(Request $request, int $jobId, string $type = 'output')
    {
        // Signature already verified by middleware
        try {
            $job = TextractJob::findOrFail($jobId);

            // Verify file exists
            if (!$this->pdfService->fileExists($jobId, $type)) {
                abort(404, "File not found: {$type}");
            }

            // Get S3 key
            $s3Key = $this->pdfService->getS3Key($jobId, $type);

            // Stream file from S3
            $file = Storage::disk('s3')->get($s3Key);

            return response($file, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . basename($s3Key) . '"')
                ->header('Cache-Control', 'public, max-age=3600')
                ->header('X-Content-Type-Options', 'nosniff');
        } catch (\Exception $e) {
            \Log::error('Failed to serve PDF', [
                'job_id' => $jobId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            abort(500, 'Failed to load PDF');
        }
    }

    /**
     * Download file
     */
    public function download(Request $request, int $jobId, string $type = 'output')
    {
        try {
            $job = TextractJob::findOrFail($jobId);

            if (!$this->pdfService->fileExists($jobId, $type)) {
                abort(404, "File not found: {$type}");
            }

            $s3Key = $this->pdfService->getS3Key($jobId, $type);
            $fileName = $this->generateFileName($job, $type);

            return Storage::disk('s3')->download($s3Key, $fileName);
        } catch (\Exception $e) {
            \Log::error('Failed to download PDF', [
                'job_id' => $jobId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            abort(500, 'Failed to download PDF');
        }
    }

    private function generateFileName(TextractJob $job, string $type): string
    {
        $baseName = str_replace(' ', '-', $job->drive_file_name);
        $baseName = str_replace('.pdf', '', $baseName);
        $suffix = match($type) {
            'input' => 'original',
            'output' => 'searchable',
            'json' => 'data',
            default => 'file',
        };

        return "{$baseName}_{$suffix}.pdf";
    }
}
```

### TextractPdfService

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class TextractPdfService
{
    protected string $bucket;
    protected string $inputPrefix;
    protected string $outputPrefix;
    protected string $jsonPrefix;

    public function __construct()
    {
        $this->bucket = (string) env('AWS_BUCKET');
        $this->inputPrefix = trim((string) env('S3_INPUT_PREFIX', 'textract/input'), '/');
        $this->outputPrefix = trim((string) env('S3_OUTPUT_PREFIX', 'textract/output'), '/');
        $this->jsonPrefix = trim((string) env('S3_JSON_PREFIX', 'textract/json'), '/');
    }

    /**
     * Get S3 key for file
     */
    public function getS3Key(int $jobId, string $type): string
    {
        return match($type) {
            'input' => "{$this->inputPrefix}/{$jobId}/input.pdf",
            'output' => "{$this->outputPrefix}/{$jobId}/output.pdf",
            'json' => "{$this->jsonPrefix}/{$jobId}/data.json",
            default => throw new \InvalidArgumentException("Invalid file type: {$type}"),
        };
    }

    /**
     * Check if file exists in S3
     */
    public function fileExists(int $jobId, string $type): bool
    {
        try {
            $s3Key = $this->getS3Key($jobId, $type);
            return Storage::disk('s3')->exists($s3Key);
        } catch (\Exception $e) {
            \Log::warning('Error checking file existence', [
                'job_id' => $jobId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get signed URL for file (1 hour expiration)
     */
    public function getSignedUrl(int $jobId, string $type, int $minutes = 60): string
    {
        $s3Key = $this->getS3Key($jobId, $type);
        $s3Client = Storage::disk('s3')->getClient();

        $cmd = $s3Client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key' => $s3Key,
        ]);

        $request = $s3Client->createPresignedRequest($cmd, "+{$minutes} minutes");
        return (string) $request->getUri();
    }

    /**
     * Get file size from S3
     */
    public function getFileSize(int $jobId, string $type): ?int
    {
        try {
            $s3Key = $this->getS3Key($jobId, $type);
            return Storage::disk('s3')->size($s3Key);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get file metadata
     */
    public function getFileMetadata(int $jobId, string $type): array
    {
        try {
            $s3Key = $this->getS3Key($jobId, $type);
            $exists = $this->fileExists($jobId, $type);
            $size = $exists ? $this->getFileSize($jobId, $type) : null;

            return [
                'exists' => $exists,
                'size' => $size,
                'formatted_size' => $size ? $this->formatBytes($size) : null,
                's3_key' => $s3Key,
                'bucket' => $this->bucket,
            ];
        } catch (\Exception $e) {
            return [
                'exists' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
```

### PdfViewer Livewire Component

```php
<?php

namespace App\Http\Livewire;

use App\Models\TextractJob;
use Illuminate\Support\Facades\URL;
use Livewire\Component;

class PdfViewer extends Component
{
    // State
    public ?int $jobId = null;
    public string $fileType = 'output';
    public string $fileName = '';
    public ?string $signedUrl = null;
    public bool $isOpen = false;
    public bool $isLoading = false;
    public string $error = '';
    public int $currentPage = 1;
    public int $totalPages = 0;
    public float $zoomLevel = 1.0;

    protected $listeners = [
        'openPdfModal' => 'open',
        'closePdfModal' => 'close',
        'updatePage' => 'setPage',
    ];

    public function open(int $jobId, string $fileType = 'output'): void
    {
        try {
            $this->isLoading = true;
            $this->error = '';

            // Load job
            $job = TextractJob::findOrFail($jobId);
            if ($job->status !== 'succeeded') {
                throw new \Exception('Document is not ready for preview');
            }

            $this->jobId = $jobId;
            $this->fileType = $fileType;
            $this->fileName = $job->drive_file_name;
            $this->currentPage = 1;
            $this->zoomLevel = 1.0;
            $this->isOpen = true;

            // Request signed URL from controller
            $this->loadSignedUrl();
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            $this->dispatch('notification', ['type' => 'error', 'message' => $this->error]);
        } finally {
            $this->isLoading = false;
        }
    }

    public function loadSignedUrl(): void
    {
        try {
            // Generate signed URL via route
            $this->signedUrl = URL::temporarySignedRoute(
                'textract.file',
                now()->addHour(),
                ['jobId' => $this->jobId, 'type' => $this->fileType]
            );
        } catch (\Throwable $e) {
            $this->error = 'Failed to load signed URL: ' . $e->getMessage();
            \Log::error('PDF signed URL generation failed', [
                'job_id' => $this->jobId,
                'type' => $this->fileType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function close(): void
    {
        $this->reset(['jobId', 'fileType', 'fileName', 'signedUrl', 'error']);
        $this->isOpen = false;
    }

    public function setPage(int $page): void
    {
        $this->currentPage = max(1, min($page, $this->totalPages));
    }

    public function zoomIn(): void
    {
        $this->zoomLevel = min($this->zoomLevel + 0.1, 2.0);
        $this->dispatch('updateZoom', $this->zoomLevel);
    }

    public function zoomOut(): void
    {
        $this->zoomLevel = max($this->zoomLevel - 0.1, 0.5);
        $this->dispatch('updateZoom', $this->zoomLevel);
    }

    public function render()
    {
        return view('livewire.pdf-viewer', [
            'isOpen' => $this->isOpen,
            'isLoading' => $this->isLoading,
            'error' => $this->error,
            'fileName' => $this->fileName,
            'fileType' => $this->fileType,
            'signedUrl' => $this->signedUrl,
            'currentPage' => $this->currentPage,
            'totalPages' => $this->totalPages,
            'zoomLevel' => $this->zoomLevel,
        ]);
    }
}
```

### PDF.js Integration Script

```javascript
// resources/js/pdf-viewer.js

import * as pdfjsLib from 'pdfjs-dist/legacy/build/pdf.js';

// Load worker from CDN
pdfjsLib.GlobalWorkerOptions.workerSrc =
    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.0.379/pdf.worker.min.js';

export class PdfViewer {
    constructor(options = {}) {
        this.canvas = options.canvas;
        this.container = options.container;
        this.onPageChange = options.onPageChange || (() => {});
        this.onLoadComplete = options.onLoadComplete || (() => {});

        this.pdf = null;
        this.currentPage = 1;
        this.zoomLevel = options.zoomLevel || 1.0;
        this.pageCache = new Map();
    }

    /**
     * Load PDF from signed URL
     */
    async loadPdf(signedUrl) {
        try {
            this.pdf = await pdfjsLib.getDocument(signedUrl).promise;
            this.onLoadComplete(this.pdf.numPages);
            await this.renderPage(1);
        } catch (error) {
            console.error('Failed to load PDF:', error);
            throw error;
        }
    }

    /**
     * Render specific page
     */
    async renderPage(pageNum) {
        if (!this.pdf || pageNum < 1 || pageNum > this.pdf.numPages) {
            return;
        }

        try {
            const page = await this.pdf.getPage(pageNum);
            const viewport = page.getViewport({
                scale: window.devicePixelRatio * this.zoomLevel
            });

            // Set canvas dimensions
            this.canvas.width = viewport.width;
            this.canvas.height = viewport.height;

            // Render page to canvas
            const context = this.canvas.getContext('2d');
            await page.render({
                canvasContext: context,
                viewport: viewport
            }).promise;

            this.currentPage = pageNum;
            this.onPageChange(pageNum, this.pdf.numPages);
        } catch (error) {
            console.error(`Failed to render page ${pageNum}:`, error);
        }
    }

    /**
     * Navigate to next page
     */
    async nextPage() {
        if (this.currentPage < this.pdf.numPages) {
            await this.renderPage(this.currentPage + 1);
        }
    }

    /**
     * Navigate to previous page
     */
    async prevPage() {
        if (this.currentPage > 1) {
            await this.renderPage(this.currentPage - 1);
        }
    }

    /**
     * Jump to specific page
     */
    async goToPage(pageNum) {
        await this.renderPage(pageNum);
    }

    /**
     * Zoom in
     */
    async zoomIn() {
        this.zoomLevel = Math.min(this.zoomLevel + 0.1, 2.0);
        await this.renderPage(this.currentPage);
    }

    /**
     * Zoom out
     */
    async zoomOut() {
        this.zoomLevel = Math.max(this.zoomLevel - 0.1, 0.5);
        await this.renderPage(this.currentPage);
    }

    /**
     * Fit to page width
     */
    async fitToWidth() {
        const page = await this.pdf.getPage(this.currentPage);
        const viewport = page.getViewport({ scale: 1 });
        const containerWidth = this.container.clientWidth;
        this.zoomLevel = containerWidth / viewport.width;
        await this.renderPage(this.currentPage);
    }

    /**
     * Fit entire page
     */
    async fitToPage() {
        const page = await this.pdf.getPage(this.currentPage);
        const viewport = page.getViewport({ scale: 1 });
        const maxWidth = this.container.clientWidth;
        const maxHeight = this.container.clientHeight;
        this.zoomLevel = Math.min(
            maxWidth / viewport.width,
            maxHeight / viewport.height
        );
        await this.renderPage(this.currentPage);
    }

    /**
     * Handle keyboard shortcuts
     */
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            if (!this.pdf) return;

            switch(e.key) {
                case 'ArrowRight':
                case 'PageDown':
                    e.preventDefault();
                    this.nextPage();
                    break;
                case 'ArrowLeft':
                case 'PageUp':
                    e.preventDefault();
                    this.prevPage();
                    break;
                case 'Home':
                    e.preventDefault();
                    this.goToPage(1);
                    break;
                case 'End':
                    e.preventDefault();
                    this.goToPage(this.pdf.numPages);
                    break;
                case '+':
                case '=':
                    e.preventDefault();
                    this.zoomIn();
                    break;
                case '-':
                    e.preventDefault();
                    this.zoomOut();
                    break;
                case 'f':
                    if (e.ctrlKey || e.metaKey) return;
                    e.preventDefault();
                    document.documentElement.requestFullscreen?.();
                    break;
            }
        });
    }
}

// Export for global use
window.PdfViewer = PdfViewer;
export default PdfViewer;
```

---

## Testing Strategy

### Unit Tests

```php
// tests/Unit/Services/TextractPdfServiceTest.php

class TextractPdfServiceTest extends TestCase
{
    protected TextractPdfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TextractPdfService::class);
    }

    public function test_get_s3_key_returns_correct_input_path()
    {
        $key = $this->service->getS3Key(123, 'input');
        $this->assertStringContainsString('textract/input/123/input.pdf', $key);
    }

    public function test_get_s3_key_returns_correct_output_path()
    {
        $key = $this->service->getS3Key(123, 'output');
        $this->assertStringContainsString('textract/output/123/output.pdf', $key);
    }

    public function test_file_exists_returns_false_for_missing_file()
    {
        $exists = $this->service->fileExists(999, 'output');
        $this->assertFalse($exists);
    }

    public function test_invalid_file_type_throws_exception()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->getS3Key(123, 'invalid');
    }
}
```

### Feature Tests

```php
// tests/Feature/TextractFileControllerTest.php

class TextractFileControllerTest extends TestCase
{
    use UsesTestDatabase;

    public function test_authenticated_user_can_get_signed_url()
    {
        $user = User::factory()->create();
        $job = TextractJob::factory()->succeeded()->create();

        $response = $this->actingAs($user)
            ->get("/api/textract/files/{$job->id}/signed-url");

        $response->assertOk();
        $response->assertJsonStructure(['signed_url', 'expires_at']);
    }

    public function test_unauthenticated_user_cannot_get_signed_url()
    {
        $job = TextractJob::factory()->create();

        $response = $this->get("/api/textract/files/{$job->id}/signed-url");

        $response->assertUnauthorized();
    }

    public function test_signed_url_is_valid_within_expiration()
    {
        $user = User::factory()->create();
        $job = TextractJob::factory()->succeeded()->create();

        // Get signed URL
        $signedResponse = $this->actingAs($user)
            ->get("/api/textract/files/{$job->id}/signed-url");

        $signedUrl = $signedResponse->json('signed_url');

        // Use signed URL immediately
        $fileResponse = $this->get($signedUrl);

        $fileResponse->assertOk();
        $fileResponse->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_invalid_signature_returns_403()
    {
        $user = User::factory()->create();
        $job = TextractJob::factory()->create();

        $response = $this->actingAs($user)
            ->get("/textract/files/{$job->id}/output?signature=invalid");

        $response->assertForbidden();
    }

    public function test_expired_signature_returns_403()
    {
        $user = User::factory()->create();
        $job = TextractJob::factory()->create();

        $expiredUrl = URL::temporarySignedRoute(
            'textract.file',
            now()->subDay(), // Already expired
            ['jobId' => $job->id, 'type' => 'output']
        );

        $response = $this->actingAs($user)->get($expiredUrl);

        $response->assertForbidden();
    }

    public function test_missing_file_returns_404()
    {
        $user = User::factory()->create();
        $job = TextractJob::factory()->create();

        $signedUrl = URL::temporarySignedRoute(
            'textract.file',
            now()->addHour(),
            ['jobId' => $job->id, 'type' => 'output']
        );

        $response = $this->actingAs($user)->get($signedUrl);

        $response->assertNotFound();
    }

    public function test_can_download_pdf()
    {
        $user = User::factory()->create();
        $job = TextractJob::factory()->succeeded()->create();

        // Assuming file exists in test S3
        $signedUrl = URL::temporarySignedRoute(
            'textract.file.download',
            now()->addHour(),
            ['jobId' => $job->id, 'type' => 'output']
        );

        $response = $this->actingAs($user)->get($signedUrl);

        $response->assertOk();
        $response->assertHeader('Content-Disposition');
    }
}
```

### Browser Tests (Dusk)

```php
// tests/Browser/TextractPdfViewerTest.php

class TextractPdfViewerTest extends DuskTestCase
{
    public function test_can_open_pdf_preview_modal()
    {
        $user = User::factory()->create();
        $job = TextractJob::factory()->succeeded()->create();

        $this->browse(function (Browser $browser) use ($user, $job) {
            $browser->loginAs($user)
                ->visit('/textract')
                ->waitFor('[dusk="job-' . $job->id . '"]')
                ->click('@view-pdf-button-' . $job->id)
                ->waitFor('.pdf-viewer-modal')
                ->assertVisible('.pdf-canvas');
        });
    }

    public function test_pdf_zoom_controls_work()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::factory()->create())
                ->visit('/textract')
                ->click('@view-pdf-button')
                ->waitFor('.pdf-viewer-modal')
                ->click('@zoom-in-button')
                ->pause(200)
                ->assertVisible('.pdf-canvas')
                ->click('@zoom-out-button')
                ->pause(200)
                ->assertVisible('.pdf-canvas');
        });
    }

    public function test_page_navigation_works()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::factory()->create())
                ->visit('/textract')
                ->click('@view-pdf-button')
                ->waitFor('.pdf-viewer-modal')
                ->assertSeeIn('@current-page', '1')
                ->click('@next-page-button')
                ->pause(200)
                ->assertSeeIn('@current-page', '2')
                ->click('@prev-page-button')
                ->pause(200)
                ->assertSeeIn('@current-page', '1');
        });
    }

    public function test_keyboard_shortcuts_work()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::factory()->create())
                ->visit('/textract')
                ->click('@view-pdf-button')
                ->waitFor('.pdf-viewer-modal')
                ->keys('@pdf-canvas', '{RIGHTARROW}')
                ->pause(200)
                ->assertSeeIn('@current-page', '2')
                ->keys('@pdf-canvas', '{LEFTARROW}')
                ->pause(200)
                ->assertSeeIn('@current-page', '1');
        });
    }

    public function test_close_button_closes_modal()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::factory()->create())
                ->visit('/textract')
                ->click('@view-pdf-button')
                ->waitFor('.pdf-viewer-modal')
                ->click('@close-modal-button')
                ->waitForMissing('.pdf-viewer-modal')
                ->assertMissing('.pdf-viewer-modal');
        });
    }
}
```

---

## Migration Path from Current State

### Pre-implementation Checklist

- [ ] Verify S3 bucket configuration (input/output prefixes)
- [ ] Confirm Textract pipeline is generating output PDFs
- [ ] Test S3 file access from application
- [ ] Verify Livewire version compatibility (current: 3.6)
- [ ] Set up PDF.js worker file hosting plan

### Database Schema Changes

**No database migrations needed.** All PDF viewing state is:
- In Livewire component (volatile)
- In browser session storage (temporary)
- No persistent storage required

### Backward Compatibility

- ✅ Existing TextractManager component unaffected
- ✅ No breaking changes to models or routes
- ✅ Optional feature (doesn't block existing workflows)
- ✅ Graceful degradation if S3 unavailable

---

## Performance Benchmarks & Targets

### Load Time Targets

| Scenario | Target | Notes |
|----------|--------|-------|
| Open modal | < 200ms | Signed URL generation |
| PDF load | < 1s | Network + parsing |
| Page render | < 300ms | Canvas rendering |
| Zoom/pan | < 100ms | Re-render |
| Page nav | < 200ms | Network (if cached) |

### Bundle Size Impact

| Component | Size | Notes |
|-----------|------|-------|
| PDF.js main | 180 KB (gzipped) | From CDN or npm |
| Worker | 32 KB | From CDN |
| Integration JS | 5 KB | Custom code |
| **Total** | **217 KB** | One-time download |

### Memory Usage

- Single PDF in memory: 50-200 MB (depends on page count/resolution)
- Page cache (10 pages): 5-50 MB
- Modal with all assets: ~250 MB typical

---

## Recommendations & Best Practices

### For Implementation

1. **Start with MVP:** Full PDF viewer (no comparison initially)
2. **Use CDN for worker:** Reduce bundle size
3. **Implement caching:** Session storage for signed URLs
4. **Add error boundaries:** Graceful failure handling
5. **Log all errors:** For debugging and monitoring

### For Operations

1. **Monitor S3 latency:** Track PDF load times
2. **Set up CloudWatch:** Alert on errors
3. **Enable S3 versioning:** Audit trail of files
4. **Rotate AWS credentials:** Regular security updates
5. **Document signed URL flow:** For troubleshooting

### For Security

1. **Always use HTTPS:** For signed URLs
2. **Set correct CORS:** If needed for cross-domain
3. **Validate file types:** Accept only PDFs
4. **Check file sizes:** Prevent DoS attacks
5. **Audit access logs:** Monitor who viewed what

---

## Conclusion

**PDF.js integration with Laravel Signed URLs is the recommended approach** for implementing document preview in the Textract Manager. This design provides:

- ✅ **Security:** Signature verification + 1-hour expiration
- ✅ **Simplicity:** Minimal code, familiar patterns
- ✅ **Performance:** Lazy loading, caching, streaming
- ✅ **Scalability:** Works for any document size
- ✅ **Maintainability:** Standard Laravel conventions

**Estimated Timeline:** 2-3 days for full implementation including tests and documentation.

**Next Steps:**
1. Review this design with the team
2. Begin Phase 1 (Setup & Infrastructure)
3. Create tickets for each task
4. Assign developers
5. Start implementation

---

**Document Version:** 1.0
**Last Updated:** 2025-11-15
**Author:** Claude AI Research Agent
**Status:** ✅ Ready for Implementation Planning
