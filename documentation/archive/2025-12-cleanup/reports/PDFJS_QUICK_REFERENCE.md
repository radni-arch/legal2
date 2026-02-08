# PDF.js Integration - Quick Reference Guide

**For:** Developers implementing Sprint 2
**Updated:** 2025-11-15
**Keep:** Handy during implementation

---

## The Design in 30 Seconds

**What:** Add PDF preview to Textract Manager
**How:** PDF.js + Laravel Signed URLs
**Where:** S3 buckets (input, output PDFs)
**Why:** Secure, simple, performant
**When:** Sprint 2 (2-3 days)

```
User clicks "View PDF" →
Signed URL generated →
PDF.js renders in modal →
User sees document with controls →
URL expires after 1 hour
```

---

## Core Technologies

| Technology | Version | Purpose | Cost |
|------------|---------|---------|------|
| PDF.js | 4.0.379 | PDF rendering | Free |
| Laravel | 11+ | Signed URLs | Already have |
| Livewire | 3.6+ | Modal component | Already have |
| S3 | N/A | PDF storage | Existing |

---

## File Structure

### Create (8 files)

```
app/Services/TextractPdfService.php
app/Http/Controllers/TextractFileController.php
app/Http/Livewire/PdfViewer.php
resources/js/pdf-viewer.js
resources/views/livewire/pdf-viewer.blade.php
tests/Unit/Services/TextractPdfServiceTest.php
tests/Feature/TextractFileControllerTest.php
tests/Feature/Livewire/PdfViewerTest.php
```

### Modify (4 files)

```
package.json                              # Add pdfjs-dist
routes/web.php                            # Add 3 new routes
app/Http/Livewire/TextractManager.php    # Add 2 methods
resources/views/livewire/textract-manager.blade.php  # Add buttons
```

---

## Three Key Components

### 1. TextractPdfService
**What:** S3 file logic
**Key methods:**
- `getS3Key(jobId, type)` → S3 path
- `fileExists(jobId, type)` → bool
- `getSignedUrl(jobId, type)` → URL
- `getFileMetadata(jobId, type)` → array

### 2. TextractFileController
**What:** Signed URL generation & file serving
**Key methods:**
- `getSignedUrl(request, jobId, type)` → JSON with URL
- `show(request, jobId, type)` → PDF stream
- `download(request, jobId, type)` → PDF download

### 3. PdfViewer
**What:** Livewire modal component
**Key methods:**
- `open(jobId, fileType)` → Open modal
- `close()` → Close modal
- `loadSignedUrl()` → Get signed URL
- `setPage(page)` → Jump to page
- `zoomIn()` / `zoomOut()` → Zoom controls

---

## Route Endpoints

```php
// Generate signed URL
GET /api/textract/files/{jobId}/signed-url
    → Returns: { signed_url: "...", expires_at: "..." }

// View PDF (protected by 'signed' middleware)
GET /textract/files/{jobId}/{type}
    Parameters: type = input|output|json
    Returns: PDF stream (inline display)

// Download PDF (protected by 'signed' middleware)
GET /textract/files/{jobId}/{type}/download
    Parameters: type = input|output|json
    Returns: PDF download
```

---

## Signed URL Flow

```
Frontend HTTP GET /api/textract/files/123/signed-url
  ↓
Controller verifies user is authenticated ✅
  ↓
Controller generates URL:
  /textract/files/123/output?signature=ABC&expires=TIMESTAMP
  ↓
Signature includes: jobId + type + expiration + app_key
  ↓
Returns to frontend
  ↓
Frontend passes to PDF.js
  ↓
PDF.js fetches from signed URL
  ↓
'signed' middleware verifies signature ✅
  ↓
Middleware checks expiration (max 1 hour old) ✅
  ↓
Controller streams file from S3 ✅
  ↓
Browser displays PDF
```

---

## Security Checklist

- [ ] Authentication middleware: `auth`
- [ ] Signature middleware: `signed`
- [ ] Expiration: 1 hour max
- [ ] Signature includes: jobId, type, timestamp
- [ ] Cannot forge: requires app_key
- [ ] Cannot reuse: signature is unique per request
- [ ] Cannot transfer: signature tied to URL
- [ ] HTTPS: Enforced in production
- [ ] S3 bucket: Private (not public)
- [ ] Error handling: Don't expose S3 internals

---

## UI Controls Reference

### Buttons
```
📄 View Searchable PDF
📋 View Original
🔄 Compare (optional)
```

### Toolbar
```
[–] [+]        Zoom out/in
Fit ↔          Fit to width
⊡              Fit page
✋             Pan mode
⬇             Download
⛶             Fullscreen
```

### Keyboard Shortcuts
```
→ / PageDown   Next page
← / PageUp     Previous page
Home / End     First / Last page
- / +          Zoom out / in
w              Fit width
p              Fit page
h              Pan mode
d              Download
f              Fullscreen
g              Go to page (then type #)
```

---

## Performance Targets

| Metric | Target | Measurement |
|--------|--------|-------------|
| Modal open | < 200ms | Click to modal visible |
| PDF load | < 1s | Signed URL to first page rendered |
| Page render | < 300ms | Click next to page displayed |
| Zoom action | < 100ms | Debounced re-render |
| **Bundle** | < 250KB | PDF.js + code |

---

## Testing Checklist

### Unit Tests
- [ ] TextractPdfService: 4 tests
- [ ] S3 key generation: correct paths
- [ ] File existence checks
- [ ] Signed URL generation

### Feature Tests
- [ ] TextractFileController: 6 tests
- [ ] Authenticated user gets signed URL
- [ ] Unauthenticated user blocked
- [ ] Invalid signature rejected
- [ ] Expiration enforced
- [ ] Missing file returns 404
- [ ] Download works

### Component Tests
- [ ] PdfViewer: 5 tests
- [ ] Modal renders when open
- [ ] Modal closes on button
- [ ] Signed URL loads
- [ ] Error handling works
- [ ] Page navigation works

### Browser Tests
- [ ] Dusk: 5 tests
- [ ] Can view PDF
- [ ] Zoom controls work
- [ ] Page navigation works
- [ ] Keyboard shortcuts work
- [ ] Download works

**Total:** ~20 tests, >90% coverage

---

## Troubleshooting Guide

### PDF doesn't load
**Check:**
- [ ] Is S3 file actually present?
- [ ] Is signed URL valid?
- [ ] Check browser console for errors
- [ ] Verify CORS headers (if cross-domain)

### Zoom/pan doesn't work
**Check:**
- [ ] Is JavaScript loaded? (pdf-viewer.js)
- [ ] Is PDF.js worker loaded? (check console)
- [ ] Is viewport calculated correctly?
- [ ] Check for JavaScript errors

### Signature invalid
**Check:**
- [ ] Is user authenticated?
- [ ] Has 1 hour passed since generation?
- [ ] Is URL unchanged (no extra params)?
- [ ] Check server logs for details

### File takes too long to load
**Check:**
- [ ] Is S3 responsive? (check AWS metrics)
- [ ] Is PDF large? (>50MB)
- [ ] Is connection slow? (mobile?)
- [ ] Use CloudFront CDN to cache

### Mobile doesn't work
**Check:**
- [ ] Is view responsive? (use mobile viewport)
- [ ] Do touch gestures work? (test pinch zoom)
- [ ] Is toolbar visible? (might be hidden)
- [ ] Check mobile browser console

---

## Quick Implementation Checklist

### Day 1: Infrastructure
- [ ] `npm install pdfjs-dist`
- [ ] Create TextractPdfService
- [ ] Create TextractFileController
- [ ] Add routes to web.php
- [ ] Write service tests
- [ ] Write controller tests

### Day 2: Frontend
- [ ] Create pdf-viewer.js script
- [ ] Create PdfViewer component
- [ ] Create blade templates
- [ ] Update TextractManager
- [ ] Add preview buttons
- [ ] Write component tests

### Day 3: Testing & Polish
- [ ] Write Dusk tests
- [ ] Code review & cleanup
- [ ] Performance optimization
- [ ] Write documentation
- [ ] Deploy to staging
- [ ] Test on real devices

---

## Key Code Patterns

### Getting Signed URL (Frontend)
```javascript
const response = await fetch(`/api/textract/files/${jobId}/signed-url`);
const data = await response.json();
const signedUrl = data.signed_url;
```

### Loading PDF with PDF.js (Frontend)
```javascript
const pdf = await pdfjsLib.getDocument(signedUrl).promise;
const page = await pdf.getPage(1);
const viewport = page.getViewport({ scale: 1.5 });
await page.render({ canvasContext: context, viewport }).promise;
```

### Generating Signed URL (Backend)
```php
$signedUrl = URL::temporarySignedRoute(
    'textract.file',
    now()->addHour(),
    ['jobId' => $jobId, 'type' => $type]
);
```

### Opening Modal from TextractManager (Livewire)
```php
public function showPdfPreview(int $jobId, string $type = 'output'): void
{
    $this->dispatch('openPdfModal', jobId: $jobId, type: $type);
}
```

---

## Common Mistakes to Avoid

❌ **Don't:**
- Store signed URLs in database (they expire!)
- Use CDN-only in production (hard to update)
- Forget the 'signed' middleware (security issue!)
- Render all pages at once (memory leak)
- Expose AWS credentials in URLs (security!)
- Skip mobile testing (different interactions)

✅ **Do:**
- Generate signed URL per request
- Use NPM + CDN worker approach
- Always verify signatures
- Render pages on-demand
- Use signed URLs only
- Test on mobile devices

---

## Performance Tips

### For Users
- Zoom before downloading (smaller file)
- Use download button (faster than print)
- Mobile: rotate to landscape for reading
- Large PDFs: use fullscreen mode

### For Developers
- Lazy-load PDFs (don't preload all)
- Cache rendered pages (10-page limit)
- Debounce zoom events (100ms)
- Use CloudFront for S3 (optional)
- Monitor S3 metrics in CloudWatch

---

## Dependencies & Versions

```json
{
  "devDependencies": {
    "pdfjs-dist": "^4.0.379"
  }
}
```

**Compatibility:**
- Laravel 11+ ✅
- Livewire 3.6+ ✅
- PHP 8.2+ ✅
- Node 16+ ✅

---

## Documentation References

| Document | When to Read |
|----------|--------------|
| PDFJS_INTEGRATION_RESEARCH.md | Full details, design decisions |
| PDFJS_SPRINT2_ROADMAP.md | Day-by-day tasks, sprint planning |
| PDFJS_RESEARCH_SUMMARY.md | Executive overview, quick insights |
| PDFJS_QUICK_REFERENCE.md | **This file!** Keep handy |

---

## Getting Help

### Problem: Need design details?
→ Read **PDFJS_INTEGRATION_RESEARCH.md** (Section: Architecture Design)

### Problem: Need task list?
→ Read **PDFJS_SPRINT2_ROADMAP.md** (Section: Task Breakdown)

### Problem: Need code example?
→ Read **PDFJS_INTEGRATION_RESEARCH.md** (Section: Code Snippets)

### Problem: Need quick decision?
→ Read **PDFJS_RESEARCH_SUMMARY.md** (Section: Recommendations)

### Problem: Stuck on implementation?
→ Check this file (Section: Troubleshooting Guide)

---

## Success Indicators

**After implementation, you should see:**

✅ "View Searchable PDF" button in job cards
✅ Modal opens with PDF displayed
✅ Zoom, pan, page navigation work
✅ Keyboard shortcuts work
✅ Mobile support works
✅ Download button saves PDF
✅ All tests passing
✅ No console errors
✅ < 1s load time
✅ Users happy!

---

**Keep this guide handy during implementation!**

Last updated: 2025-11-15
