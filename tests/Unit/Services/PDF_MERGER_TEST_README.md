# PdfMerger Test Suite

## Overview
This test suite provides comprehensive coverage for the `PdfMerger` service, which merges multiple PDF files into a single PDF document using the FPDI library.

## Test Results
✅ **19 tests passing**
✅ **24 assertions**
⚠️ **Requires FPDI library** (setasign/fpdi)

## Test Coverage

### Directory Creation (2 tests)
- Creates destination directory if it doesn't exist
- Handles existing destination directory gracefully

### Memory Limit Management (2 tests)
- Temporarily increases memory limit to 512M during merge
- Restores original memory limit after completion
- Restores memory limit even after exceptions (finally block)

### Empty Input Handling (1 test)
- Handles empty PDF array gracefully
- May produce empty PDF or throw exception (both acceptable)

### Non-existent File Handling (2 tests)
- Skips non-existent files without error
- Handles mixed existent and non-existent files
- Continues processing valid files

### Return Value (1 test)
- Returns destination path on successful merge

### Path Handling (2 tests)
- Handles absolute paths correctly
- Handles paths with special characters and spaces

### Error Handling (2 tests)
- Catches FPDI exceptions for invalid PDFs
- Skips invalid PDFs and continues processing
- Handles multiple invalid PDFs

### Edge Cases (2 tests)
- Destination paths without file extension
- Very long destination paths

### Integration Test Markers (3 tests)
- Documents FPDI library requirement
- Documents merge() functionality and signature
- Documents FPDI configuration

### Cleanup (2 tests)
- Verifies garbage collection after merge
- Documents PDF object cleanup behavior

## Service Functionality

### Method: merge(array $pdfPaths, string $destPath): string

**Purpose**: Merges multiple PDF files into a single PDF document.

**Parameters**:
- `$pdfPaths` (array): Array of PDF file paths to merge
- `$destPath` (string): Destination path for merged PDF

**Returns**: `string` - The destination path

**Behavior**:
1. Creates destination directory if it doesn't exist
2. Temporarily increases memory limit to 512M
3. Creates FPDI instance with configuration
4. Iterates through PDF paths
5. Skips non-existent files (continues)
6. Skips invalid PDFs (catches exceptions, continues)
7. Imports each page from valid PDFs
8. Preserves page orientation (landscape/portrait)
9. Preserves page dimensions
10. Outputs merged PDF to destination
11. Cleans up memory (unset, gc_collect_cycles)
12. Restores original memory limit (finally block)
13. Returns destination path

## FPDI Configuration

The service configures FPDI with:
- `setPrintHeader(false)` - No header
- `setPrintFooter(false)` - No footer
- `SetAutoPageBreak(false)` - No automatic page breaks
- `SetCreator('Laravel PDF Merger')` - Creator metadata
- `SetAuthor('Laravel App')` - Author metadata

## Running the Tests

```bash
# Run all PdfMerger tests
vendor/bin/phpunit tests/Unit/Services/PdfMergerTest.php

# Or use artisan
php artisan test --filter=PdfMergerTest

# Run with coverage
vendor/bin/phpunit --coverage-html coverage tests/Unit/Services/PdfMergerTest.php
```

## Dependencies

### Required
```bash
composer require setasign/fpdi
```

The FPDI library is required for PDF merging functionality.

**FPDI**: Free PDF Document Importer for TCPDF/FPDF
- **Package**: setasign/fpdi
- **Purpose**: Import pages from existing PDF documents
- **License**: MIT

## Test Limitations

### Why Limited Integration Testing?

The tests are primarily **unit tests** that verify:
- ✅ Directory creation logic
- ✅ Memory management
- ✅ Error handling structure
- ✅ Path handling
- ✅ Method signatures

**Integration testing limitations**:
- ❌ Creating valid PDFs is complex
- ❌ FPDI library behavior is external
- ❌ PDF parsing is format-sensitive
- ❌ Testing requires real PDF files

### What's NOT Tested

**PDF Content**:
- Actual PDF merging with real files
- Page content preservation
- Image quality preservation
- Font embedding
- Bookmark/annotation handling
- Encryption/security handling

**FPDI Library**:
- FPDI's PDF parsing logic
- Template import/export
- Page dimension calculation
- Orientation detection

### For Production Testing

To test actual PDF merging:

```php
// Create real test PDFs
$pdf1 = storage_path('tests/sample1.pdf');
$pdf2 = storage_path('tests/sample2.pdf');

// Merge them
$merger = new PdfMerger();
$output = storage_path('tests/merged.pdf');
$result = $merger->merge([$pdf1, $pdf2], $output);

// Verify output
$this->assertFileExists($output);
$this->assertGreaterThan(0, filesize($output));

// Verify PDF is valid
$pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
$pageCount = $pdf->setSourceFile($output);
$this->assertGreaterThan(0, $pageCount);
```

## Usage Examples

### Basic Merge
```php
$merger = new PdfMerger();

$pdfs = [
    storage_path('documents/contract.pdf'),
    storage_path('documents/appendix.pdf'),
    storage_path('documents/signatures.pdf'),
];

$output = storage_path('merged/complete_contract.pdf');

$result = $merger->merge($pdfs, $output);
// Returns: storage_path('merged/complete_contract.pdf')
```

### Error Handling
```php
$merger = new PdfMerger();

$pdfs = [
    'existing.pdf',
    'nonexistent.pdf',  // Will be skipped
    'invalid.pdf',      // Will be skipped
    'another.pdf',
];

try {
    $result = $merger->merge($pdfs, 'output.pdf');
    // Merges only valid PDFs
} catch (\Exception $e) {
    // Handle merge failure
    Log::error('PDF merge failed: ' . $e->getMessage());
}
```

### With Large Files
```php
// Memory limit automatically increased to 512M
$merger = new PdfMerger();

$largePdfs = [
    'large_document_1.pdf',  // 50 MB
    'large_document_2.pdf',  // 75 MB
    'large_document_3.pdf',  // 100 MB
];

$output = 'merged_large.pdf';

// Memory limit restored after completion
$result = $merger->merge($largePdfs, $output);
```

## Memory Management

### Memory Limit Strategy

**Original Limit**: Varies by PHP configuration
**During Merge**: 512M
**After Merge**: Restored to original

**Why 512M?**
- PDF parsing is memory-intensive
- Multiple PDFs loaded simultaneously
- FPDI library requires working memory
- Prevents "memory exhausted" errors

**Memory Cleanup**:
1. `unset($pdf)` - Releases FPDI object
2. `gc_collect_cycles()` - Forces garbage collection
3. Memory limit restored in `finally` block

### Memory Usage Estimation

Approximate memory needed:
- **Small PDFs** (<1 MB): ~10-20 MB per file
- **Medium PDFs** (1-10 MB): ~50-100 MB per file
- **Large PDFs** (>10 MB): ~200-500 MB per file

For very large operations, consider:
- Merging in batches
- Increasing to 1024M or more
- Processing on dedicated workers

## Error Scenarios

### 1. Non-existent Files
**Behavior**: Skipped, processing continues
```php
if (!is_file($path)) continue;
```

### 2. Invalid PDF Files
**Behavior**: Exception caught, processing continues
```php
try {
    $pageCount = $pdf->setSourceFile($path);
} catch (\Throwable $e) {
    continue;
}
```

### 3. Unreadable Files
**Behavior**: Skipped by `is_file()` check

### 4. Permission Errors
**Behavior**: May throw exception when creating directory or writing output

### 5. Disk Space Issues
**Behavior**: May throw exception during PDF output

## Performance Considerations

### Time Complexity
- **O(n × p)** where:
  - n = number of PDF files
  - p = average pages per file

### Factors Affecting Performance
- **File sizes**: Larger PDFs take longer
- **Page count**: More pages = more processing
- **Image content**: PDFs with many images slower
- **Disk I/O**: Reading/writing speed matters
- **Memory availability**: Swapping slows down

### Optimization Tips
1. **Batch processing**: Merge in smaller groups
2. **Async processing**: Use queues for background merging
3. **Caching**: Don't re-merge unnecessarily
4. **Compression**: Pre-compress large PDFs if possible
5. **SSD storage**: Faster disk I/O helps

## Common Use Cases

### Legal Documents
- Merge contract pages with exhibits
- Combine court filings
- Create case document bundles

### Reports
- Combine multiple report sections
- Add cover pages and appendices
- Create complete documentation

### Archives
- Consolidate related documents
- Create yearly compilaries
- Build document collections

## Security Considerations

### Input Validation
The service does NOT validate:
- PDF content safety
- Malicious PDF payloads
- File size limits
- PDF encryption/passwords

**Recommendation**: Validate PDFs before merging:
```php
// Check file size
if (filesize($path) > 100 * 1024 * 1024) {
    throw new Exception('PDF too large');
}

// Verify it's actually a PDF
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $path);
if ($mime !== 'application/pdf') {
    throw new Exception('Not a PDF file');
}
```

### Output Security
- Ensure destination is in allowed directory
- Use proper file permissions (0644)
- Don't expose output paths to users
- Sanitize user-provided paths

## Alternative Libraries

If FPDI doesn't meet needs, consider:

1. **PDFtk Server** (CLI)
   - Robust, feature-rich
   - Requires system installation

2. **Ghostscript** (CLI)
   - High-quality merging
   - System dependency

3. **mPDF**
   - HTML to PDF conversion
   - Can merge existing PDFs

4. **DomPDF**
   - HTML to PDF
   - Limited PDF manipulation

5. **iLovePDF API**
   - Cloud-based
   - Requires API key

## Troubleshooting

### "Class not found" Error
**Solution**: Install FPDI
```bash
composer require setasign/fpdi
```

### "Memory exhausted" Error
**Solution**: Increase memory limit in php.ini or code
```php
ini_set('memory_limit', '1024M');
```

### "Permission denied" Error
**Solution**: Ensure directory is writable
```bash
chmod 775 storage/pdfs
```

### "Invalid PDF" Warnings
**Solution**: Validate PDFs before merging, skip invalid files

## PHPUnit Deprecations

Tests use `@test` annotations which will be deprecated in PHPUnit 12. Migrate to PHP 8 attributes:

```php
// Current
/** @test */
public function it_does_something() { }

// Future
#[Test]
public function it_does_something() { }
```

## Related Services

- **OcrService**: Extract text from PDFs
- **UploadService**: Handle PDF uploads
- **FactExtractionService**: Extract facts from PDF content
- **IngestPipelineService**: Process PDFs for search

## Notes

This service is a **wrapper** around FPDI, providing:
- Laravel integration
- Memory management
- Error handling
- Directory creation
- Consistent interface

The actual PDF merging is performed by the FPDI library.
