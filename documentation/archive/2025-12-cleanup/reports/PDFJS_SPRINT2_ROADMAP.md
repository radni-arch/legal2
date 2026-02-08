# Sprint 2: PDF.js Preview - Implementation Roadmap

**Status:** Sprint Planning Phase
**Duration:** 2-3 days (8-12 developer-days)
**Start:** After Sprint 1 (Neo4j cleanup completion)
**Priority:** High (P1 - User-facing feature)

---

## Overview

Enable visual document preview in Textract Manager with PDF.js integration, signed URL security, and comprehensive UI controls.

### What Users Will Get

1. **View Original PDF** - See source document before OCR
2. **View Searchable PDF** - See processed document after OCR
3. **Navigation** - Prev/next page, jump to page number
4. **Zoom Controls** - In/out with keyboard shortcuts
5. **Download** - Save PDF to local machine
6. **Keyboard Shortcuts** - Full keyboard support for power users
7. **Mobile Support** - Touch gestures and responsive layout

### Dependencies

- **Sprint 1 Completion:** Neo4j cleanup must be done first
- **Infrastructure:** S3 bucket properly configured
- **Packages:** pdfjs-dist (npm install)

---

## Task Breakdown

### Day 1: Infrastructure Setup

#### Task 1.1: Install PDF.js (30 min)
**File:** `package.json`

```bash
npm install pdfjs-dist
npm run build
```

**Verification:**
```bash
npm list pdfjs-dist  # Should show ~4.0.379
```

#### Task 1.2: Create TextractPdfService (2 hours)
**File:** `app/Services/TextractPdfService.php`

**Key Methods:**
- `getS3Key(jobId, type)` - Return S3 path
- `fileExists(jobId, type)` - Check S3 file
- `getSignedUrl(jobId, type, minutes)` - Generate signed URL
- `getFileMetadata(jobId, type)` - Size, format, etc

**Tests:** `tests/Unit/Services/TextractPdfServiceTest.php`
- ✅ Get correct S3 paths
- ✅ Check file existence
- ✅ Validate file types
- ✅ Handle missing files

**Acceptance:** Service fully working, 100% test coverage

#### Task 1.3: Create TextractFileController (2 hours)
**File:** `app/Http/Controllers/TextractFileController.php`

**Methods:**
- `getSignedUrl(request, jobId, type)` - Return signed URL JSON
- `show(request, jobId, type)` - Stream file (protected by 'signed')
- `download(request, jobId, type)` - Download file

**Security:**
- ✅ Authenticate user (via middleware 'auth')
- ✅ Verify signature (via middleware 'signed')
- ✅ Check job exists
- ✅ Check file exists
- ✅ Prevent path traversal

**Tests:** `tests/Feature/TextractFileControllerTest.php`
- ✅ Authenticated user can get signed URL
- ✅ Unauthenticated user blocked
- ✅ Signature verification works
- ✅ Expiration enforced (1 hour)
- ✅ Missing files return 404
- ✅ Download works

**Acceptance:** Controller fully working with security tests passing

#### Task 1.4: Add Routes (30 min)
**File:** `routes/web.php`

```php
Route::middleware('auth')->group(function () {
    // Get signed URL
    Route::get('/api/textract/files/{jobId}/signed-url',
        [TextractFileController::class, 'getSignedUrl'])
        ->name('textract.signed-url');

    // Stream file (signature verified)
    Route::get('/textract/files/{jobId}/{type}',
        [TextractFileController::class, 'show'])
        ->middleware('signed')
        ->name('textract.file');

    // Download file
    Route::get('/textract/files/{jobId}/{type}/download',
        [TextractFileController::class, 'download'])
        ->middleware('signed')
        ->name('textract.file.download');
});
```

**Acceptance:** Routes registered and accessible

---

### Day 2: Frontend Components

#### Task 2.1: Create PDF.js Integration Script (2 hours)
**File:** `resources/js/pdf-viewer.js`

**Exports:**
- `PdfViewer` class for rendering
- Methods: `loadPdf()`, `renderPage()`, `nextPage()`, `prevPage()`, `zoomIn()`, `zoomOut()`, `fitToWidth()`, `fitToPage()`
- Keyboard support via `setupKeyboardShortcuts()`

**Features:**
- ✅ Render PDF pages to canvas
- ✅ Handle zoom levels (0.5x - 2.0x)
- ✅ Page navigation
- ✅ Keyboard shortcuts
- ✅ Event callbacks for UI updates
- ✅ Error handling

**Acceptance:** Script fully functional with test coverage

#### Task 2.2: Create PdfViewer Livewire Component (3 hours)
**File:** `app/Http/Livewire/PdfViewer.php`

**Properties:**
- `jobId`, `fileType`, `fileName`
- `signedUrl`, `isOpen`, `isLoading`, `error`
- `currentPage`, `totalPages`, `zoomLevel`

**Methods:**
- `open(jobId, fileType)` - Load PDF
- `close()` - Close modal
- `loadSignedUrl()` - Get signed URL
- `setPage(page)` - Jump to page
- `zoomIn()`, `zoomOut()` - Zoom controls

**Listeners:**
- `openPdfModal` - Open with job ID
- `closePdfModal` - Close
- `updatePage` - Change page

**Acceptance:** Component fully working, tests passing

#### Task 2.3: Create PdfViewer Blade Template (2 hours)
**File:** `resources/views/livewire/pdf-viewer.blade.php`

**Structure:**
```
Modal Backdrop
├── Modal Container
├── Header (title + close)
├── Toolbar (zoom, pan, download)
├── Canvas Container
│   └── PDF Canvas
├── Footer (page nav)
└── Loading / Error States
```

**Features:**
- ✅ Dark theme (matches app)
- ✅ Responsive (desktop + mobile)
- ✅ Accessible (keyboard shortcuts)
- ✅ Smooth animations
- ✅ Loading indicator
- ✅ Error messages

**Acceptance:** Template renders correctly, responsive on all devices

#### Task 2.4: Update TextractManager Component (1 hour)
**File:** `app/Http/Livewire/TextractManager.php`

**Add Methods:**
```php
public function showPdfPreview(int $jobId, string $type = 'output'): void
public function showComparison(int $jobId): void
```

**Fire Events:**
- `openPdfModal` with jobId and type
- `openComparisonModal` with jobId

**Acceptance:** Events fire correctly, component integration working

#### Task 2.5: Add Preview Buttons to Job Cards (1 hour)
**File:** `resources/views/livewire/textract-manager.blade.php`

**Add to job actions:**
```blade
@if($job->status === 'succeeded')
    <button wire:click="showPdfPreview({{ $job->id }}, 'output')">
        📄 View Searchable
    </button>
    <button wire:click="showPdfPreview({{ $job->id }}, 'input')">
        📋 View Original
    </button>
@endif
```

**Acceptance:** Buttons visible and clickable

---

### Day 3: Testing & Documentation

#### Task 3.1: Livewire Component Tests (2 hours)
**File:** `tests/Feature/Livewire/PdfViewerTest.php`

**Tests:**
- ✅ Component renders when isOpen = true
- ✅ Modal closes on button click
- ✅ Loads signed URL on open
- ✅ Handles missing files gracefully
- ✅ Page navigation works
- ✅ Zoom controls dispatch events

**Acceptance:** All tests passing, >90% coverage

#### Task 3.2: JavaScript Unit Tests (1 hour)
**File:** `resources/js/__tests__/pdf-viewer.test.js`

**Tests (using Jest/Vitest):**
- ✅ PdfViewer loads PDF from URL
- ✅ Page rendering works
- ✅ Zoom calculations correct
- ✅ Page navigation bounds checking
- ✅ Keyboard shortcuts fire correctly

**Acceptance:** All tests passing

#### Task 3.3: Browser/Dusk Tests (2 hours)
**File:** `tests/Browser/TextractPdfViewerTest.php`

**Tests:**
- ✅ Can open PDF modal from job card
- ✅ PDF renders in canvas
- ✅ Page navigation works end-to-end
- ✅ Zoom controls work
- ✅ Keyboard shortcuts work
- ✅ Download button works
- ✅ Close button closes modal

**Acceptance:** All browser tests passing on desktop + mobile

#### Task 3.4: Documentation (1 hour)
**File:** `docs/TEXTRACT_PDF_VIEWER.md`

**Sections:**
- User Guide (how to use)
- Keyboard Shortcuts (reference)
- Troubleshooting (common issues)
- Performance Tips (optimization)
- FAQ (frequently asked questions)

**Acceptance:** Documentation complete and reviewed

#### Task 3.5: Code Review & Polish (1 hour)
**Tasks:**
- [ ] Code style (pint, eslint)
- [ ] Performance optimization
- [ ] Error handling edge cases
- [ ] Accessibility review (a11y)
- [ ] Security audit

**Acceptance:** All code reviewed, merged to main

---

## Acceptance Criteria

### Functional Requirements

- [ ] Can view original PDF from S3
- [ ] Can view searchable PDF from S3
- [ ] Signed URLs expire after 1 hour
- [ ] Cannot access files without signature
- [ ] Page navigation works (prev/next/jump)
- [ ] Zoom controls work (in/out/fit)
- [ ] Keyboard shortcuts work
- [ ] Mobile responsive
- [ ] Download button works

### Non-Functional Requirements

- [ ] PDF load time < 1 second
- [ ] Page render < 300ms
- [ ] Zero console errors
- [ ] No memory leaks
- [ ] Works on Chrome, Firefox, Safari, Edge
- [ ] Works on mobile (iOS, Android)
- [ ] 90%+ test coverage for new code

### Security Requirements

- [ ] Authentication enforced
- [ ] Signature validation works
- [ ] Expiration enforced
- [ ] No path traversal possible
- [ ] No sensitive data in URLs
- [ ] HTTPS enforced in production

---

## Risk Mitigation

### Risk: Large PDFs (> 50MB) slow down UI

**Mitigation:**
- Implement streaming/chunked loading
- Add progress indicator
- Lazy-load pages
- Memory management

### Risk: S3 latency impacts experience

**Mitigation:**
- CloudFront CDN (optional)
- Caching strategy
- Async loading
- Fallback messages

### Risk: Browser compatibility issues

**Mitigation:**
- Test on all major browsers
- Polyfills for older browsers
- Progressive enhancement
- Graceful degradation

### Risk: Mobile touch interactions don't work smoothly

**Mitigation:**
- Hammer.js for touch gestures
- Test extensively on devices
- Optimize for touch targets
- Fast response times

---

## Rollback Plan

If issues arise:

1. **Disable PDF preview buttons** - Hide in TextractManager
2. **Keep routes but 503 error** - Graceful degradation
3. **Remove feature from S3** - Easy cleanup
4. **Revert component** - Git revert changes
5. **Users unaffected** - Feature is optional

---

## Success Metrics

### Adoption
- [ ] > 50% of users view at least one PDF
- [ ] Average session time increases

### Quality
- [ ] < 1% error rate on PDF loads
- [ ] < 500ms average load time
- [ ] < 0.5% security incidents

### Feedback
- [ ] Positive user feedback
- [ ] No critical bugs reported
- [ ] Feature request for next phase?

---

## Dependencies & Prerequisites

### External Dependencies
- ✅ pdfjs-dist (npm)
- ✅ S3 bucket configured
- ✅ Textract pipeline working

### Internal Dependencies
- ✅ Sprint 1 (Neo4j cleanup) must be complete
- ✅ TextractJob model stable
- ✅ Livewire 3.6+ working

### Infrastructure
- ✅ S3 bucket with proper permissions
- ✅ Input/output PDFs being generated
- ✅ HTTPS configured
- ✅ CloudFront (optional but recommended)

---

## Timeline

```
Day 1 (Infrastructure):    4-5 hours
├─ PDF.js install         0.5h
├─ PdfService             2h
├─ FileController         2h
└─ Routes                 0.5h

Day 2 (Frontend):         8-9 hours
├─ pdf-viewer.js          2h
├─ PdfViewer component    3h
├─ Blade template         2h
├─ Manager integration    1h
└─ Buttons in cards       1h

Day 3 (Testing):          6-7 hours
├─ Component tests        2h
├─ JS unit tests          1h
├─ Browser/Dusk tests     2h
├─ Documentation          1h
└─ Code review/polish     1h

Total: 18-21 hours (2.5-3 developer-days)
```

---

## Resource Requirements

### Team Members
- 1 Backend Developer (Phase 1: Infrastructure)
- 1 Frontend Developer (Phase 2: Components)
- 1 QA Engineer (Phase 3: Testing)

### Infrastructure
- S3 bucket with PDFs
- PostgreSQL database
- Laravel 11 environment
- Node.js environment

### Tools
- GitHub (version control)
- IDE (VS Code, PhpStorm)
- Browser dev tools
- PDF test files

---

## Handoff Checklist

- [ ] Code merged to main
- [ ] Tests passing on CI/CD
- [ ] Documentation complete
- [ ] Staging environment verified
- [ ] Performance benchmarks met
- [ ] Security audit passed
- [ ] Product team notified
- [ ] Monitoring configured
- [ ] Alerts set up
- [ ] Team trained on new feature

---

## Next Phase: Sprint 3 (Optional)

After Sprint 2 is complete, consider:

1. **Side-by-Side Comparison** (3-4 days)
   - Display original + searchable PDFs together
   - Synchronized scrolling
   - Highlight differences

2. **Thumbnail Generation** (2-3 days)
   - Generate first page thumbnails
   - Display in job list
   - Lazy load

3. **Batch Operations** (1-2 days)
   - Select multiple jobs
   - Batch download
   - Batch open in new tabs

---

## References

- **Research Document:** `/PDFJS_INTEGRATION_RESEARCH.md`
- **Assessment Document:** `/TEXTRACT_MANAGER_ASSESSMENT.md`
- **Existing Implementation:** `app/Http/Livewire/TextractManager.php`
- **S3 Config:** `config/filesystems.php`
- **Routes:** `routes/web.php`

---

**Document Version:** 1.0
**Created:** 2025-11-15
**Status:** ✅ Ready for Sprint Planning
