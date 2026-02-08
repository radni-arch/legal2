# Sprint 12 Worker B: Complete Case Analysis Workflows - Test Specification

## Overview

This document describes the E2E browser tests created for complete case analysis workflows in the Legal Defense Playground. These tests follow **Test-Driven Development (TDD)** principles - they represent the **specification** for how complete workflows should function from start to finish.

## Test-Driven Development Approach

**Status**: ✅ RED Phase Complete

These tests were written **before** implementation (TDD RED phase). They currently fail because the complete workflow features don't exist yet - **this is correct and expected in TDD**.

### TDD Cycle Progress

- ✅ **RED**: Tests written and verified to fail
- ⏳ **GREEN**: Implementation pending (requires feature integration)
- ⏳ **REFACTOR**: Will occur after implementation

## Test Suite: CompleteCaseWorkflowTest.php

**Location**: `tests/Browser/CompleteCaseWorkflowTest.php`

**Total Tests**: 8 comprehensive end-to-end workflow scenarios

---

### Test 1: Complete Case Analysis Workflow

**Test Method**: `test_complete_case_analysis_workflow()`

**Specification**: Full journey from case creation through evidence analysis to motion generation and export.

**Workflow Steps**:
1. Login as lawyer
2. Navigate to Legal Defense Playground
3. Select case from dropdown
4. Click "Evidence Analysis" module
5. Enter evidence description
6. Select evidence type
7. Click "Analyze Evidence" button
8. Wait for analysis completion (30s timeout)
9. Review constitutional violations with ZKP citations
10. Click "Generate Suppression Motion"
11. Review generated motion in Croatian
12. Click "Export to PDF"
13. Verify PDF download initiated

**UI Elements Required**:
- Case selection dropdown (`selectedCaseId`)
- Module navigation buttons
- Evidence input form (`evidenceDescription`, `evidenceType`)
- "🔍 Analyze Evidence" button
- Results display showing "Constitutional Violations" and "ZKP"
- "Generate Suppression Motion" button
- Motion display with "PRIJEDLOG ZA ISKLJUČENJE DOKAZA" and "Čl"
- "Export to PDF" button
- Download handling

**Success Criteria**:
- User can complete entire workflow without errors
- Analysis completes within 30 seconds
- Motion is generated in Croatian with proper legal citations
- PDF export is triggered

---

### Test 2: Misconduct Detection Workflow

**Test Method**: `test_misconduct_detection_workflow()`

**Specification**: Detect prosecutorial misconduct and generate legal remedies.

**Workflow Steps**:
1. Navigate to Misconduct Detection module
2. Select misconduct type (e.g., EVIDENCE_SUPPRESSION)
3. Enter detailed misconduct description
4. Click "Detect Misconduct" button
5. Review analysis with severity score
6. Generate dismissal motion
7. Generate ethics complaint

**UI Elements Required**:
- Misconduct module navigation
- Misconduct type dropdown (`misconductType`)
- Details textarea (`misconductDetails`)
- "⚠️ Detect Misconduct" button
- Results showing "Brady Violation" and "Severity Score"
- "Generate Dismissal Motion" button
- "Generate Ethics Complaint" button
- Documents showing Croatian legal references

**Expected Outputs**:
- Misconduct analysis with severity scoring
- "PRIJEDLOG ZA OBUSTAVU" (Dismissal Motion)
- "PRIJAVA" (Ethics Complaint)
- References to "Zakon o Državnom odvjetništvu" and "Kodeks"

---

### Test 3: Topic Analysis Workflow

**Test Method**: `test_topic_analysis_workflow()`

**Specification**: Analyze specific legal topics (Drug Charges, Home Searches) with abuse detection.

**Workflow Steps**:
1. Navigate to Topic Framework module
2. Select topic: "Drug Charges"
3. Enter drug type, amount, and charge details
4. Run analysis
5. Review overcharging detection and regional statistics
6. Switch to "Home Search" topic
7. Run home search analysis
8. Review disproportionality score

**UI Elements Required**:
- Topic selection dropdown (`selectedTopic`)
- Drug charge inputs:
  - Drug type (`drugType`)
  - Amount (`amount`)
  - Charged as (`chargedAs`)
- "🎯 Analyze Drug Charge" button
- Home search analysis interface
- "🏠 Analyze Home Search" button
- Regional statistics display
- Recommendations section

**Analysis Outputs**:
- "Overcharging Detected" flag
- "Regional Statistics" comparison
- "Recommendation" for defense strategy
- "Disproportionality Score" for home searches
- "Judicial District" context

---

### Test 4: Complete Defense Strategy Workflow

**Test Method**: `test_complete_defense_strategy_workflow()`

**Specification**: Generate unified defense strategy combining all analyses.

**Workflow Steps**:
1. Navigate to Defense Strategy module
2. Click "Generate Defense Strategy"
3. Wait for AI generation (60s timeout)
4. Review comprehensive strategy
5. Click "Export Complete Defense Package"
6. Download ZIP file with all documents

**UI Elements Required**:
- "Defense Strategy" module button
- "Generate Defense Strategy" button
- Strategy display showing:
  - Constitutional Arguments
  - Procedural Objections
  - Recommended Motions
- "Export Complete Defense Package" button
- Package contents list:
  - Suppression Motions
  - Dismissal Motions
  - Legal Memoranda
- "Download ZIP" button

**Success Criteria**:
- Strategy generation completes within 60 seconds
- All major defense components included
- ZIP package downloads successfully

---

### Test 5: Textract Document Processing Workflow

**Test Method**: `test_textract_document_processing_workflow()`

**Specification**: OCR processing workflow using AWS Textract.

**Workflow Steps**:
1. Navigate to `/textract` page
2. Upload PDF document
3. Click "Process Document"
4. Wait for job queue confirmation
5. Pause for background processing (5s)
6. Refresh page
7. Verify "Completed" status (30s timeout)
8. Download searchable PDF
9. View extracted text

**UI Elements Required**:
- File upload input (`file`)
- "Process Document" button
- Status messages:
  - "Processing started"
  - "Job queued"
  - "Completed"
- "Download Searchable PDF" button
- "View Extracted Text" link
- Extracted text display showing "LINE blocks:"

**Technical Requirements**:
- Background job processing (Laravel queues)
- S3 upload/download
- AWS Textract integration
- PDF reconstruction with searchable text layer

---

### Test 6: Case Document Upload Workflow

**Test Method**: `test_case_document_upload_workflow()`

**Specification**: Upload and manage case documents with categorization and linking.

**Workflow Steps**:
1. Navigate to case documents page
2. Upload first document (Police Report)
3. Categorize as POLICE_REPORT
4. Upload second document (Witness Statement)
5. Categorize as WITNESS_STATEMENT
6. Link first document to evidence
7. Search within documents

**UI Elements Required**:
- Document upload input (`document`)
- Document type dropdown (`documentType`)
- "Upload" button
- Document list/grid
- "Link to Evidence" button
- Evidence linking modal with `evidenceId` dropdown
- Search interface (`searchQuery`)
- "Search Documents" button
- Search results display

**Document Types**:
- POLICE_REPORT
- WITNESS_STATEMENT
- COURT_ORDER
- EVIDENCE_PHOTO
- EXPERT_REPORT
- etc.

---

### Test 7: Evidence File Upload Workflow

**Test Method**: `test_evidence_file_upload_workflow()`

**Specification**: Upload evidence files with automatic text extraction and auto-population.

**Workflow Steps**:
1. Navigate to Evidence Analysis module
2. Upload evidence file (PDF)
3. Wait for "File uploaded" confirmation
4. Watch "Extracting text..." status
5. Verify evidence description auto-populated
6. Run analysis on extracted content
7. Review results

**UI Elements Required**:
- Evidence file upload input (`evidenceFile`)
- Upload status messages
- Auto-populated evidence description field
- Analysis functionality working with extracted text

**Expected Behavior**:
- Text extraction from PDF
- Auto-fill of `evidenceDescription` field
- User can edit extracted text before analysis
- Analysis works seamlessly with uploaded content

---

### Test 8: Bulk Document Processing Workflow

**Test Method**: `test_bulk_document_processing_workflow()`

**Specification**: Process multiple documents simultaneously with batch monitoring.

**Workflow Steps**:
1. Navigate to Textract Manager
2. Select multiple files (3 files)
3. Click "Process Batch"
4. Monitor batch progress
5. Wait for completion (10s for 3 files)
6. Verify all completed successfully
7. Download all processed files

**UI Elements Required**:
- Multiple file upload (`files[]`)
- "Process Batch" button
- Batch status:
  - "Batch processing started"
  - "3 files queued"
  - "Processing..."
  - "Batch complete"
  - "3 / 3 completed"
- "Download All" button
- Error handling (should NOT show "Failed" or "Error")

**Performance Requirements**:
- Handle at least 3 files concurrently
- Queue-based processing (Laravel jobs)
- Progress tracking per file
- Batch completion detection
- Bulk download capability

---

## Implementation Requirements

### Backend (Laravel/Livewire)

**Files to Implement/Modify**:

1. **LegalPlayground.php** - Complete workflow orchestration
2. **EvidenceAnalysisService.php** - Evidence analysis
3. **MisconductDetector.php** - Misconduct detection
4. **Topic Analyzers** - Drug charges, home searches
5. **Defense Strategy Generator** - Unified strategy creation
6. **MotionGenerators** - Suppression, dismissal motions
7. **TextractService.php** - OCR processing
8. **DocumentManager.php** - Document uploads and linking

**Required Services**:
- Evidence analysis with constitutional violation detection
- Motion generation (suppression, dismissal)
- Ethics complaint generation
- Topic-specific analyzers
- Defense strategy compilation
- PDF export functionality
- Textract job management
- Document categorization and search
- File text extraction

### Frontend (Blade/Livewire/Alpine.js)

**Files to Implement/Modify**:

1. **legal-playground.blade.php** - Module navigation and UI
2. **textract-manager.blade.php** - Batch processing UI
3. **Case documents views** - Upload and linking UI

**UI Components Needed**:
- Case selection dropdown
- Module navigation (tabs or buttons)
- Evidence analysis form
- Misconduct detection form
- Topic analysis interfaces
- Defense strategy viewer
- Document upload with drag-and-drop
- Batch processing progress bars
- PDF download handlers
- Search functionality

### Database

**Potential Schema Changes**:
- Ensure `cases` table has all required fields
- Document uploads table
- Evidence files table
- Generated motions storage
- Textract job tracking
- Document-evidence linking table

---

## Test Helper Methods

### createSamplePDF()

**Purpose**: Generate test PDF files for upload testing.

**Implementation**:
```php
protected function createSamplePDF(string $content = 'Sample document content'): string
{
    $filename = sys_get_temp_dir() . '/test_' . uniqid() . '.pdf';

    // Create minimal valid PDF structure
    $pdf = "%PDF-1.4\n";
    $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $pdf .= "2 0 obj\n<< /Type /Pages /Count 1 /Kids [3 0 R] >>\nendobj\n";
    $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n";
    $pdf .= "4 0 obj\n<< /Length 44 >>\nstream\nBT /F1 12 Tf 100 700 Td ($content) Tj ET\nendstream\nendobj\n";
    $pdf .= "xref\n0 5\n0000000000 65535 f\n0000000009 00000 n\n0000000056 00000 n\n0000000115 00000 n\n0000000317 00000 n\n";
    $pdf .= "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n406\n%%EOF";

    file_put_contents($filename, $pdf);

    return $filename;
}
```

**Usage**: Automatically creates and cleans up test PDFs in all upload tests.

---

## Running the Tests

### Prerequisites

```bash
# Start infrastructure
su - claude -c "/usr/lib/postgresql/16/bin/pg_ctl -D /var/lib/postgresql/16/main start"
/tmp/chromedriver-linux64/chromedriver --port=9515 &

# Ensure assets built
npm run build
```

### Run All Workflow Tests

```bash
php artisan dusk tests/Browser/CompleteCaseWorkflowTest.php
```

### Run Individual Tests

```bash
# Complete case analysis
php artisan dusk tests/Browser/CompleteCaseWorkflowTest.php --filter=test_complete_case_analysis_workflow

# Misconduct detection
php artisan dusk tests/Browser/CompleteCaseWorkflowTest.php --filter=test_misconduct_detection_workflow

# Topic analysis
php artisan dusk tests/Browser/CompleteCaseWorkflowTest.php --filter=test_topic_analysis_workflow

# Defense strategy
php artisan dusk tests/Browser/CompleteCaseWorkflowTest.php --filter=test_complete_defense_strategy_workflow

# Textract processing
php artisan dusk tests/Browser/CompleteCaseWorkflowTest.php --filter=test_textract_document_processing_workflow

# Case documents
php artisan dusk tests/Browser/CompleteCaseWorkflowTest.php --filter=test_case_document_upload_workflow

# Evidence files
php artisan dusk tests/Browser/CompleteCaseWorkflowTest.php --filter=test_evidence_file_upload_workflow

# Bulk processing
php artisan dusk tests/Browser/CompleteCaseWorkflowTest.php --filter=test_bulk_document_processing_workflow
```

### Expected Status (TDD RED Phase)

**Current**: ❌ All tests fail (expected - workflows not fully implemented)

**After Implementation**: ✅ All tests pass

---

## Success Criteria

### Definition of Done

- ✅ All 8 workflow tests pass
- ✅ End-to-end journeys complete without errors
- ✅ Documents generate correctly in Croatian
- ✅ File uploads and downloads work
- ✅ Batch processing handles multiple files
- ✅ No console errors during workflows
- ✅ Performance within acceptable timeouts

### Quality Metrics

- **Workflow Completion Rate**: 100%
- **Analysis Timeout**: < 30 seconds
- **Motion Generation**: < 20 seconds
- **Defense Strategy**: < 60 seconds
- **Textract Processing**: < 30 seconds per file
- **Batch Processing**: 3 files in < 10 seconds (queue time)
- **File Upload Success**: 100%

---

## Common Issues and Solutions

### Issue 1: Case Selection Not Working

**Symptom**: Cannot select case from dropdown

**Solution**: Ensure LegalPlayground component loads cases properly:
```php
public function mount()
{
    $this->cases = LegalCase::all()->map(fn($case) => [
        'id' => $case->id,
        'label' => "{$case->case_number} - {$case->title}"
    ])->toArray();
}
```

### Issue 2: Module Navigation Not Switching

**Symptom**: Clicking module buttons doesn't change view

**Solution**: Implement Livewire method:
```php
public function setModule($module)
{
    $this->activeModule = $module;
    $this->reset(['results', 'errorMessage']);
}
```

### Issue 3: File Upload Not Processing

**Symptom**: Files upload but don't process

**Solution**:
1. Check queue worker is running
2. Verify AWS credentials configured
3. Ensure S3 bucket permissions correct
4. Check Textract job status in AWS console

### Issue 4: PDF Download Not Working

**Symptom**: Click download but nothing happens

**Solution**: Implement proper download response:
```php
public function exportToPDF()
{
    $pdf = $this->generatePDF();
    return response()->streamDownload(function() use ($pdf) {
        echo $pdf;
    }, 'suppression-motion.pdf');
}
```

---

## Future Enhancements

### Beyond Current Specification

1. **Real-time Collaboration**: Multiple users working on same case
2. **Version History**: Track changes to motions and strategies
3. **Template Library**: Pre-built motion templates
4. **AI Learning**: System learns from successful strategies
5. **Court Filing Integration**: Direct e-filing to Croatian courts
6. **Client Portal**: Share documents securely with clients
7. **Mobile App**: iOS/Android apps for case management
8. **Voice Dictation**: Dictate evidence descriptions
9. **Translation**: Auto-translate Croatian ↔ English
10. **Analytics Dashboard**: Track success rates and patterns

---

## Notes for Implementers

### TDD Philosophy

These tests define the **complete user journey**. Implementation should:
1. Make tests pass with minimal code
2. Focus on user experience
3. Integrate existing services
4. Handle errors gracefully

### Integration Points

This workflow integrates:
- Evidence analysis module
- Misconduct detection module
- Topic framework (drug charges, home searches)
- Motion generators
- Textract service
- Document management
- PDF generation

All these components should work together seamlessly.

### Performance Considerations

- Use **queues** for long-running tasks (Textract, AI generation)
- Implement **caching** for repeated analyses
- Use **lazy loading** for large document lists
- Optimize **database queries** (eager loading)
- Add **progress indicators** for user feedback

### Security Considerations

- Validate all file uploads (size, type, content)
- Sanitize user inputs before AI processing
- Implement rate limiting on expensive operations
- Ensure proper authorization (user can only access their cases)
- Encrypt sensitive documents at rest
- Audit log all document access

---

## Sprint 12 Worker B Deliverable

**Status**: ✅ **TDD RED Phase Complete**

**Delivered**:
- 8 comprehensive end-to-end workflow tests
- Complete specification documentation
- Test helper methods
- Integration requirements

**Next Steps** (GREEN Phase):
1. Integrate existing services into unified workflows
2. Implement missing UI components
3. Add file upload/download handlers
4. Implement batch processing
5. Run tests to verify implementation

**Timeline**: Tests created in Sprint 12 Worker B, implementation to follow.

---

**Created**: 2025-11-10
**Author**: Sprint 12 Worker B
**TDD Phase**: RED (Specification Complete)
**Status**: Ready for Implementation
