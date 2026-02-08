# PDF.js Integration Research - Executive Summary
## Textract Manager Sprint 2 Preview

**Research Date:** 2025-11-15
**Status:** ✅ Complete - Ready for Implementation
**Estimated Effort:** 2-3 days (18-21 developer-hours)

---

## Quick Overview

The research confirms that **PDF.js integration with Laravel signed URLs is the optimal approach** for implementing document preview functionality in the Textract Manager. This design is:

- ✅ **Secure** - Uses Laravel's built-in signature verification with 1-hour expiration
- ✅ **Simple** - Minimal code, familiar patterns, no new dependencies beyond pdfjs-dist
- ✅ **Performant** - Client-side rendering, lazy loading, efficient caching
- ✅ **Scalable** - Works with any PDF size, any storage backend

---

## Research Findings

### 1. PDF.js Integration Methods Analyzed

#### Three Options Evaluated:

| Option | Pros | Cons | Recommendation |
|--------|------|------|-----------------|
| **CDN Only** | Simplest, smallest bundle | External dependency, no version control | ⚠️ Prototyping |
| **NPM + CDN Worker** | Versioned, controlled main lib | Build step required | ✅ **RECOMMENDED** |
| **Fully Local** | Complete offline support | Larger bundle, more complex | 🔒 High security |

#### Recommendation: Option 2 (NPM + CDN Worker)

**Why:**
- Aligns with existing Vite/webpack setup
- Version-controlled via package.json
- Main library (180 KB) from npm, worker (32 KB) from CDN
- Standard in modern Laravel projects
- Easy to switch to fully local if needed

```bash
npm install pdfjs-dist  # 4.0.379 recommended
```

---

### 2. Signed URL Strategy

#### Two Methods Evaluated:

| Method | Pros | Cons | Recommendation |
|--------|------|------|-----------------|
| **Laravel Signed URLs** | Native, simple, stateless | Token in URL | ✅ **PRIMARY** |
| **AWS S3 Pre-signed** | AWS handles verification | Exposes SDK details | ⚠️ Optional |

#### Recommendation: Hybrid Approach

**Primary:** Use Laravel's `URL::temporarySignedRoute()` with 1-hour expiration
- User clicks "View PDF" button
- Backend generates signed URL via `TextractFileController`
- URL includes: jobId, type, expiration, signature
- Frontend receives signed URL and opens in PDF.js
- Signature automatically verified by 'signed' middleware
- Cannot be forged without app key

**Optional:** AWS S3 pre-signed URLs for direct downloads (skip server)

**Security Guarantees:**
- ✅ User must be authenticated (middleware: 'auth')
- ✅ URL signature verified (middleware: 'signed')
- ✅ Timestamp checked (max 1 hour old)
- ✅ Cannot be reused or modified
- ✅ Revocable via key rotation

---

### 3. Architecture Design

#### Component Structure

```
TextractManager (existing)
├── Job List
├── Job Card with Actions
│   ├── [View Searchable PDF] ← NEW
│   ├── [View Original PDF] ← NEW
│   └── [Compare] ← Optional
└── PdfViewer Modal ← NEW Livewire Component
    ├── Header (title, close button)
    ├── Toolbar (zoom, pan, download, fullscreen)
    ├── Canvas Container
    │   └── PDF Canvas (rendered by PDF.js)
    └── Footer (page navigation)

TextractFileController ← NEW
├── Generate signed URLs
├── Stream files from S3
└── Handle authentication/authorization

TextractPdfService ← NEW
├── Get S3 key paths
├── Check file existence
├── Generate signed URLs
└── Get file metadata
```

#### Data Flow

```
1. User clicks [View Searchable PDF]
   ↓
2. TextractManager.showPdfPreview($jobId, 'output')
   ↓
3. Livewire dispatches 'openPdfModal' event
   ↓
4. PdfViewer component opens modal
   ↓
5. PdfViewer.loadSignedUrl()
   ↓
6. HTTP GET /api/textract/files/{jobId}/signed-url
   ↓
7. TextractFileController.getSignedUrl()
   ↓
8. Verifies job exists, generates signed URL
   ↓
9. Returns signed URL to frontend
   ↓
10. JavaScript calls PDF.js.getDocument(signedUrl)
   ↓
11. PDF.js fetches from signed URL (auto-verified)
   ↓
12. User sees PDF with controls
   ↓
13. Signed URL expires after 1 hour (automatic)
```

---

### 4. UI/UX Design

#### Design Principles
- Maintain dark theme consistency (matches Textract Manager)
- Responsive across all devices (desktop, tablet, mobile)
- Keyboard shortcuts for power users
- Touch gestures for mobile
- Progressive enhancement (works without JavaScript)

#### Key Features

**Controls:**
- Zoom In/Out (keyboard: `+`/`-`)
- Fit to Width (keyboard: `w`)
- Fit to Page (keyboard: `p`)
- Pan/Hand Mode (keyboard: `h`)
- Download PDF (keyboard: `d`)
- Fullscreen (keyboard: `f`)

**Navigation:**
- Next/Previous Page (keyboard: arrow keys, page down/up)
- Jump to Page (keyboard: `g` then type number)
- First/Last Page (keyboard: Home/End)
- Mouse wheel support

**Display:**
- Dark theme matching app design
- Responsive layout (90vw desktop, 95vw mobile)
- Loading indicator while PDF loads
- Error messages if file missing
- Page counter (Page 5 of 12)

#### Mobile Support

- Touch gestures: pinch zoom, two-finger pan
- Simplified toolbar on small screens
- Full-screen optimized
- Touch-friendly button sizes (≥44px)

---

### 5. Security Analysis

#### Authentication & Authorization
- ✅ Users must be authenticated (via 'auth' middleware)
- ✅ Signed URL signature verified (via 'signed' middleware)
- ✅ Expiration enforced (max 1 hour old)
- ✅ Can add case-level permissions (future enhancement)

#### URL Signature Structure
```
URL Format:
/textract/files/{jobId}/{type}?signature=XYZ&expires=TIMESTAMP

Signature includes:
- User ID
- URL path
- Timestamp
- App key (secret)

Cannot forge without:
- App key (never exposed)
- Correct timestamp (verified server-side)
- Correct jobId (specific to each file)
```

#### S3 Bucket Security
- Bucket is private (not public-accessible)
- Files only accessible via signed URLs or app server
- Encryption enabled (AES-256 recommended)
- Versioning enabled (optional, for audit trail)
- Access logging enabled
- Lifecycle policies for old versions

#### Additional Recommendations
- ✅ Rotate AWS keys regularly (monthly minimum)
- ✅ Monitor S3 access logs for anomalies
- ✅ Use HTTPS only (enforced by middleware)
- ✅ Set proper CORS headers if cross-domain
- ✅ Validate file types (reject non-PDFs)

---

### 6. Performance Optimization

#### Optimization Strategies

1. **Lazy Loading**
   - Load PDF only when modal opens
   - Don't preload all PDFs at page load
   - Reduces initial page weight

2. **Partial Rendering**
   - Render only visible pages
   - Destroy off-screen pages to free memory
   - Handles large documents efficiently

3. **Page Caching**
   - Cache rendered pages in memory (max 10 pages)
   - Avoid re-rendering on navigation
   - ~50MB max per document

4. **Signed URL Caching**
   - Cache in sessionStorage (expires with session)
   - Reuse for multiple views of same file
   - Reduces controller calls

5. **Debounced Interactions**
   - Debounce zoom events (100ms)
   - Debounce page navigation
   - Prevents excessive rendering

#### Performance Targets

| Metric | Target | Current Estimate |
|--------|--------|-------------------|
| Modal open time | < 200ms | ~150ms (Livewire + JS) |
| PDF load | < 1s | ~500ms (S3 + parsing) |
| Page render | < 300ms | ~200ms (canvas draw) |
| Zoom/pan | < 100ms | ~50ms (debounced) |
| **Bundle size** | **< 250KB** | **217KB** (PDF.js) |

#### Bundle Impact
- PDF.js main: 180 KB (gzipped)
- Worker: 32 KB (gzipped)
- Integration code: 5 KB
- **Total:** 217 KB one-time download

---

## Implementation Plan

### High-Level Phases

#### **Phase 1: Infrastructure (4-5 hours)**
- Install pdfjs-dist via npm
- Create TextractPdfService (S3 key logic, file checks)
- Create TextractFileController (signed URL generation, file serving)
- Add routes for signed URLs and file access

#### **Phase 2: Frontend (8-9 hours)**
- Create pdf-viewer.js integration script (PDF.js wrapper)
- Create PdfViewer Livewire component (modal logic)
- Create Blade templates (modal UI)
- Update TextractManager component (buttons)
- Add preview buttons to job cards

#### **Phase 3: Testing (6-7 hours)**
- Unit tests for TextractPdfService
- Feature tests for TextractFileController
- Livewire component tests
- Browser/Dusk tests for UI interactions
- Documentation and code review

---

## Recommended Approach

### Installation & Setup

```bash
# 1. Install PDF.js
npm install pdfjs-dist

# 2. Create TextractPdfService
touch app/Services/TextractPdfService.php

# 3. Create TextractFileController
touch app/Http/Controllers/TextractFileController.php

# 4. Create PdfViewer component
php artisan make:livewire PdfViewer

# 5. Create JavaScript integration
touch resources/js/pdf-viewer.js

# 6. Update routes
# (edit routes/web.php manually)

# 7. Add tests
mkdir -p tests/Unit/Services tests/Feature tests/Browser
```

### Key Implementation Details

**TextractPdfService Methods:**
```php
getS3Key(int $jobId, string $type): string
fileExists(int $jobId, string $type): bool
getSignedUrl(int $jobId, string $type, int $minutes = 60): string
getFileMetadata(int $jobId, string $type): array
```

**TextractFileController Routes:**
```
GET  /api/textract/files/{jobId}/signed-url        (getSignedUrl)
GET  /textract/files/{jobId}/{type}                (show, protected by 'signed')
GET  /textract/files/{jobId}/{type}/download       (download, protected by 'signed')
```

**PdfViewer Component:**
```php
open(int $jobId, string $fileType = 'output')
close()
loadSignedUrl()
setPage(int $page)
zoomIn() / zoomOut()
```

**JavaScript Features:**
```javascript
PdfViewer.loadPdf(signedUrl)
PdfViewer.renderPage(pageNum)
PdfViewer.nextPage()
PdfViewer.prevPage()
PdfViewer.zoomIn() / zoomOut()
PdfViewer.fitToWidth() / fitToPage()
PdfViewer.setupKeyboardShortcuts()
```

---

## Files to Create/Modify

### New Files (8 total)

**Backend:**
1. `app/Services/TextractPdfService.php` - S3 file logic
2. `app/Http/Controllers/TextractFileController.php` - Signed URLs & file serving
3. `app/Http/Livewire/PdfViewer.php` - Modal component
4. `tests/Unit/Services/TextractPdfServiceTest.php` - Service tests
5. `tests/Feature/TextractFileControllerTest.php` - Controller tests
6. `tests/Feature/Livewire/PdfViewerTest.php` - Component tests

**Frontend:**
7. `resources/js/pdf-viewer.js` - PDF.js integration script
8. `resources/views/livewire/pdf-viewer.blade.php` - Modal UI

### Modified Files (4 total)

1. `package.json` - Add pdfjs-dist dependency
2. `routes/web.php` - Add new routes
3. `app/Http/Livewire/TextractManager.php` - Add preview methods
4. `resources/views/livewire/textract-manager.blade.php` - Add buttons

---

## Risk Assessment & Mitigation

### Low Risk Areas ✅
- PDF.js is mature and widely used
- Laravel signed URLs are battle-tested
- No database schema changes needed
- Optional feature (doesn't break existing workflows)

### Medium Risk Areas ⚠️
- Large PDF handling (100+ pages, 50+ MB)
  - **Mitigation:** Implement streaming, lazy loading, memory limits

- S3 latency impacting user experience
  - **Mitigation:** Add CloudFront CDN, caching strategies

- Browser compatibility
  - **Mitigation:** Test on major browsers, provide fallbacks

### Mitigation Strategies
1. **Comprehensive testing** - Unit, integration, browser tests
2. **Monitoring & logging** - Track errors, performance metrics
3. **Graceful degradation** - Feature works without breaking existing UI
4. **Rollback plan** - Can disable quickly if issues arise

---

## Rollback Plan

If issues occur:

```
Level 1: Disable buttons (1 minute)
  ├─ Hide [View PDF] buttons from UI
  └─ Users can still edit content, re-process jobs

Level 2: 503 Error (5 minutes)
  ├─ Routes return service unavailable
  └─ Clear error message to users

Level 3: Revert changes (15 minutes)
  ├─ `git revert` changes
  ├─ Deploy previous version
  └─ No data loss, no side effects
```

---

## Success Criteria

### Functional ✅
- [ ] Can view original PDF from S3
- [ ] Can view searchable PDF from S3
- [ ] Page navigation works
- [ ] Zoom controls work
- [ ] Keyboard shortcuts work
- [ ] Mobile responsive
- [ ] Download button works

### Non-Functional ✅
- [ ] PDF load time < 1 second
- [ ] Page render < 300ms
- [ ] Zero console errors
- [ ] No memory leaks
- [ ] Cross-browser support
- [ ] Mobile touch support

### Security ✅
- [ ] Authentication enforced
- [ ] Signature verification works
- [ ] Expiration enforced (1 hour)
- [ ] No path traversal possible
- [ ] 90%+ test coverage

---

## Timeline & Effort

| Phase | Duration | Developer-Hours | Resources |
|-------|----------|-----------------|-----------|
| Phase 1: Infrastructure | ~5h | 4-5h | 1 backend dev |
| Phase 2: Frontend | ~8-9h | 8-9h | 1 frontend dev |
| Phase 3: Testing | ~6-7h | 6-7h | 1 QA engineer |
| **Total** | **~2.5-3 days** | **18-21h** | **1-3 developers** |

---

## Documents Generated

Two comprehensive documents have been created:

### 1. **PDFJS_INTEGRATION_RESEARCH.md** (Primary)
- Complete technical design document
- 10 sections covering all aspects
- Code snippets and examples
- Testing strategy
- Performance benchmarks
- **Pages:** ~15 pages
- **Use for:** Architecture understanding, detailed implementation guidance

### 2. **PDFJS_SPRINT2_ROADMAP.md** (Practical)
- Day-by-day task breakdown
- Acceptance criteria
- Risk mitigation
- Timeline and dependencies
- **Pages:** ~8 pages
- **Use for:** Sprint planning, task assignment, progress tracking

---

## Recommendations

### ✅ Do This
1. **Start with Phase 1 after Sprint 1 completion** - Infrastructure needs to be solid
2. **Use NPM + CDN Worker approach** - Best balance of control and simplicity
3. **Implement comprehensive tests** - Especially for security
4. **Set up monitoring** - Track PDF loads and errors
5. **Document keyboard shortcuts** - Power users will appreciate

### ⚠️ Don't Do This
1. ❌ Don't use CDN-only approach in production - Hard to version control
2. ❌ Don't skip security testing - Signature verification is critical
3. ❌ Don't preload all PDFs - Hurts performance
4. ❌ Don't forget mobile testing - Touch interactions are different
5. ❌ Don't expose AWS credentials - Use signed URLs only

### 🎯 Consider for Future
1. **Side-by-side comparison** - Compare original vs searchable (Sprint 3)
2. **Thumbnail generation** - Visual job identification (Sprint 3)
3. **Batch operations** - Select and download multiple PDFs (Sprint 3)
4. **OCR quality overlay** - Highlight low-confidence areas (Sprint 4)
5. **Export to different formats** - PDF, PNG, DOCX (Sprint 4)

---

## Conclusion

**PDF.js + Laravel Signed URLs is the recommended solution** for implementing document preview in the Textract Manager because it:

✅ **Security** - Uses proven Laravel authentication patterns
✅ **Simplicity** - Minimal new code, no complex infrastructure
✅ **Performance** - Client-side rendering, efficient caching
✅ **Maintainability** - Standard conventions, easy to understand
✅ **Scalability** - Works with any PDF size, any storage backend

**Ready to implement:** All research complete, design documented, tasks defined.

---

## Next Steps

1. **Review** - Share this summary with the team
2. **Prioritize** - Decide if Sprint 2 should proceed
3. **Plan** - Create sprint tickets from PDFJS_SPRINT2_ROADMAP.md
4. **Assign** - Allocate developers to each phase
5. **Execute** - Follow the day-by-day breakdown
6. **Test** - Run comprehensive test suite
7. **Deploy** - Use rollback plan if needed
8. **Monitor** - Track errors and performance
9. **Iterate** - Gather feedback for improvements

---

## Document References

**Location:** `/home/user/ai-legal-war-machine/`

| Document | Purpose | Pages | Read Time |
|----------|---------|-------|-----------|
| **PDFJS_INTEGRATION_RESEARCH.md** | Complete technical design | ~15 | 30-40 min |
| **PDFJS_SPRINT2_ROADMAP.md** | Sprint planning & tasks | ~8 | 15-20 min |
| **PDFJS_RESEARCH_SUMMARY.md** | This executive summary | ~5 | 10-15 min |
| **TEXTRACT_MANAGER_ASSESSMENT.md** | Original assessment | ~30 | 45-60 min |

---

**Research Completed:** 2025-11-15
**Status:** ✅ **READY FOR IMPLEMENTATION**
**Next Review:** After Sprint 2 planning meeting

---

## Contact & Questions

For clarification on any aspect of this research:
- Review the detailed PDFJS_INTEGRATION_RESEARCH.md document
- Check the task breakdown in PDFJS_SPRINT2_ROADMAP.md
- Refer to code snippets for implementation patterns
- Contact technical team for specific questions

---

*This research document is part of the AI Legal War Machine documentation suite and follows the project's architectural standards and best practices.*
