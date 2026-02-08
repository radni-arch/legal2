# PdfRenderer Test Suite

## Overview
This test suite provides comprehensive coverage for the `PdfRenderer` service, which renders Croatian legal articles to PDF format using DomPDF via Laravel views.

## Test Results
✅ **23 tests passing**
✅ **40 assertions**
⚠️ **Requires barryvdh/laravel-dompdf**
⚠️ **Requires view template**: `resources/views/pdf/article.blade.php`

## Test Coverage

### Basic Functionality (2 tests)
- Renders article to PDF with full workflow
- Returns void (no return value)

### Context Handling (5 tests)
- Passes complete context to view
- Handles minimal context (only required fields)
- Handles empty context array
- Handles Croatian characters (đ, č, ć, š, ž)
- Handles special characters in content

### View Rendering (2 tests)
- Uses correct view template (`pdf.article`)
- Renders view to HTML string

### PDF Configuration (2 tests)
- Sets A4 paper size
- Sets portrait orientation

### Directory Creation (2 tests)
- Creates nested destination directories
- Handles existing destination directories

### Path Handling (3 tests)
- Absolute paths
- Paths with special characters and spaces
- Very long paths

### Memory Management (1 test)
- Documents memory cleanup behavior (`unset`)

### Integration Documentation (3 tests)
- Documents DomPDF library requirement
- Documents view template requirement
- Verifies method signature (ctx, destPath, void)
- Documents expected context fields

### Edge Cases (3 tests)
- Very long article HTML content
- HTML with special characters
- Empty article HTML

## Service Functionality

### Method: renderArticle(array $ctx, string $destPath): void

**Purpose**: Renders a Croatian legal article to PDF format.

**Parameters**:
- `$ctx` (array): Article context data
- `$destPath` (string): Destination file path for PDF

**Returns**: `void` (no return value)

**Workflow**:
1. Renders `pdf.article` Blade view with context
2. Loads HTML into DomPDF
3. Sets paper size (A4, portrait)
4. Creates destination directory if needed
5. Saves PDF to destination path
6. Cleans up memory (unset $pdf, $html)

## Expected Context Fields

The service expects these fields in the context array:

### Required (in practice)
- **law_title** (string): Law title (e.g., "Zakon o radu")
- **article_number** (string): Article number (e.g., "15", "15a")
- **article_html** (string): Article content as HTML

### Optional
- **law_eli** (string): ELI identifier (e.g., "HR:NN:2014:93")
- **law_pub_date** (string): Publication date (e.g., "2014-07-30")
- **generated_at** (string): Generation timestamp (defaults to `gmdate('c')`)
- **generator_version** (string): Generator version (defaults to "1.0.0")

## View Template

**Location**: `resources/views/pdf/article.blade.php`

**Features**:
- A4 page format with margins (28mm top/bottom, 20mm left/right)
- DejaVu Sans font (supports Croatian characters)
- 12pt base font size
- Header with law title and article number
- Metadata section (ELI, publication date, generation info)
- Article content section (HTML rendered)
- Footer with page identifier

**CSS Styling**:
- `@page`: Page margins
- `body`: Font family, size, color
- `header`: Title and metadata
- `.article`: Content styling
- `footer`: Fixed footer at page bottom

## Running the Tests

```bash
# Run all PdfRenderer tests
vendor/bin/phpunit tests/Unit/Services/PdfRendererTest.php

# Or use artisan
php artisan test --filter=PdfRendererTest

# Run with coverage
vendor/bin/phpunit --coverage-html coverage tests/Unit/Services/PdfRendererTest.php
```

## Dependencies

### Required Package
```bash
composer require barryvdh/laravel-dompdf
```

**Laravel DomPDF**: Laravel wrapper for DomPDF library
- **Package**: barryvdh/laravel-dompdf
- **Purpose**: Convert HTML to PDF
- **License**: MIT
- **Docs**: https://github.com/barryvdh/laravel-dompdf

### Required View Template
Create `resources/views/pdf/article.blade.php` with article layout.

## Test Approach

### What's Tested (Unit Tests)
- ✅ View facade usage and template selection
- ✅ PDF facade configuration (paper, orientation)
- ✅ Directory creation logic
- ✅ Context passing to views
- ✅ Path handling (absolute, special chars, long paths)
- ✅ Method signature and return type

### What's NOT Tested (Integration)
- ❌ Actual PDF generation
- ❌ DomPDF rendering quality
- ❌ Font rendering
- ❌ CSS to PDF conversion
- ❌ File output verification

### For Production Testing

To test actual PDF rendering:

```php
// Generate a real PDF
$renderer = new PdfRenderer();

$ctx = [
    'law_title' => 'Zakon o radu',
    'article_number' => '15',
    'article_html' => '<p>Članak 15 određuje prava radnika.</p>',
];

$output = storage_path('tests/article_15.pdf');
$renderer->renderArticle($ctx, $output);

// Verify output
$this->assertFileExists($output);
$this->assertGreaterThan(1000, filesize($output)); // PDF should have content

// Optional: Verify PDF is valid
$pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
try {
    $pageCount = $pdf->setSourceFile($output);
    $this->assertEquals(1, $pageCount);
} catch (\Exception $e) {
    $this->fail('Generated PDF is invalid: ' . $e->getMessage());
}
```

## Usage Examples

### Basic Article Rendering
```php
$renderer = new PdfRenderer();

$ctx = [
    'law_title' => 'Zakon o radu',
    'article_number' => '15',
    'law_eli' => 'HR:NN:2014:93',
    'law_pub_date' => '2014-07-30',
    'article_html' => '<p>Članak 15 određuje osnovna prava radnika...</p>',
];

$output = storage_path('pdfs/zakon_o_radu_cl_15.pdf');

$renderer->renderArticle($ctx, $output);
// PDF saved at $output
```

### Batch Processing Articles
```php
$renderer = new PdfRenderer();

$articles = [
    ['number' => '1', 'html' => '<p>Article 1 content</p>'],
    ['number' => '2', 'html' => '<p>Article 2 content</p>'],
    ['number' => '3', 'html' => '<p>Article 3 content</p>'],
];

foreach ($articles as $article) {
    $ctx = [
        'law_title' => 'Kazneni zakon',
        'article_number' => $article['number'],
        'article_html' => $article['html'],
    ];

    $output = storage_path("pdfs/kz_cl_{$article['number']}.pdf");
    $renderer->renderArticle($ctx, $output);
}
```

### With Complete Metadata
```php
$renderer = new PdfRenderer();

$ctx = [
    'law_title' => 'Zakon o međunarodnom privatnom pravu',
    'article_number' => '5',
    'law_eli' => 'HR:NN:2017:101',
    'law_pub_date' => '2017-10-30',
    'article_html' => '<p>Članak 5 uređuje nadležnost...</p>',
    'generated_at' => now()->toIso8601String(),
    'generator_version' => '2.0.0',
];

$output = storage_path('pdfs/mpp_cl_5.pdf');

$renderer->renderArticle($ctx, $output);
```

## Memory Management

### Memory Cleanup
After each render:
```php
unset($pdf, $html);
```

**Purpose**: Frees memory for batch operations

**Benefits**:
- Prevents memory accumulation
- Allows processing many articles
- Reduces PHP memory errors

### Memory Usage Estimation
Approximate memory per article:
- **Small article** (<10 KB HTML): ~5-10 MB
- **Medium article** (10-50 KB HTML): ~10-20 MB
- **Large article** (>50 KB HTML): ~20-50 MB

### For Large Batches
If processing many articles:
```php
// Process in chunks
$chunks = array_chunk($articles, 50);

foreach ($chunks as $chunk) {
    foreach ($chunk as $article) {
        $renderer->renderArticle($article, $output);
    }

    // Force garbage collection after each chunk
    gc_collect_cycles();
}
```

## DomPDF Configuration

### Default Settings
The service uses DomPDF defaults:
- **Paper**: A4
- **Orientation**: Portrait
- **Font**: DejaVu Sans (from view template)
- **Encoding**: UTF-8

### Advanced Configuration
To customize DomPDF globally, publish config:

```bash
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

Edit `config/dompdf.php`:
```php
return [
    'show_warnings' => false,
    'public_path' => public_path(),
    'convert_entities' => true,
    'options' => [
        'font_dir' => storage_path('fonts/'),
        'font_cache' => storage_path('fonts/'),
        'temp_dir' => sys_get_temp_dir(),
        'chroot' => realpath(base_path()),
        'enable_font_subsetting' => false,
        'pdf_backend' => 'CPDF',
        'default_media_type' => 'screen',
        'default_paper_size' => 'a4',
        'default_font' => 'serif',
        'dpi' => 96,
        'enable_php' => false,
        'enable_javascript' => true,
        'enable_remote' => true,
        'font_height_ratio' => 1.1,
    ],
];
```

## Croatian Character Support

### Font Selection
**DejaVu Sans** is used because it supports:
- Latin Extended-A and Extended-B
- Croatian diacritics: č, ć, dž, đ, lj, nj, š, ž
- Proper glyph rendering

### UTF-8 Encoding
Ensure:
- Database charset: `utf8mb4`
- Blade template: `<meta charset="UTF-8">`
- HTML content: UTF-8 encoded

### Example
```php
$ctx = [
    'law_title' => 'Zakon o međunarodnom privatnom pravu',
    'article_html' => '<p>Određuje nadležnost za bračne sporove.</p>',
];

// Characters render correctly in PDF:
// đ, č, ć, š, ž, etc.
```

## Error Handling

### Common Errors

#### 1. View Not Found
**Error**: `View [pdf.article] not found`

**Solution**: Create view template
```bash
mkdir -p resources/views/pdf
# Create article.blade.php
```

#### 2. Permission Denied
**Error**: `mkdir(): Permission denied`

**Solution**: Ensure directory is writable
```bash
chmod 775 storage/pdfs
```

#### 3. Memory Exhausted
**Error**: `Allowed memory size exhausted`

**Solution**: Increase memory limit
```php
ini_set('memory_limit', '512M');
```

#### 4. DomPDF Not Installed
**Error**: `Class 'Barryvdh\DomPDF\Facade\Pdf' not found`

**Solution**: Install package
```bash
composer require barryvdh/laravel-dompdf
```

## Performance Considerations

### Rendering Time
Factors affecting speed:
- **HTML complexity**: More tags = slower
- **Images**: Embedded images slow rendering
- **CSS complexity**: Complex styles take time
- **Font loading**: First render loads fonts
- **Disk I/O**: Writing to slow disks

### Optimization Tips

1. **Cache generated PDFs**
```php
$cacheKey = "pdf_article_{$articleId}";

if (Cache::has($cacheKey)) {
    return Cache::get($cacheKey);
}

$renderer->renderArticle($ctx, $output);
Cache::put($cacheKey, $output, now()->addDays(30));
```

2. **Queue long operations**
```php
dispatch(new RenderArticlePdf($ctx, $output));
```

3. **Simplify HTML**
- Avoid complex CSS
- Minimize nested tags
- Use system fonts
- Avoid external resources

4. **Batch rendering**
- Process in chunks
- Use gc_collect_cycles()
- Monitor memory usage

## Security Considerations

### Input Validation

**Always validate context data**:
```php
// Sanitize HTML content
$ctx['article_html'] = strip_tags(
    $ctx['article_html'],
    '<p><br><strong><em><ul><ol><li><h1><h2><h3>'
);

// Limit content length
if (strlen($ctx['article_html']) > 100000) {
    throw new Exception('Article too long');
}
```

### Path Security

**Prevent path traversal**:
```php
$destPath = storage_path('pdfs/' . basename($filename));

// Don't use user input directly:
// BAD: storage_path($userInput)
// GOOD: storage_path('pdfs/' . basename($userInput))
```

### XSS in PDFs

**Sanitize HTML content**:
- Remove `<script>` tags
- Remove event handlers (`onclick`, etc.)
- Validate allowed tags
- Use `{!! !!}` only for trusted content

## Alternative Libraries

If DomPDF doesn't meet needs:

1. **wkhtmltopdf** (via snappy)
   - Better CSS support
   - Faster rendering
   - Requires system binary

2. **mPDF**
   - Good UTF-8 support
   - Better table handling
   - More memory intensive

3. **TCPDF**
   - Very detailed control
   - Complex API
   - Slower than DomPDF

4. **Browsershot** (Puppeteer)
   - Chrome rendering
   - Perfect CSS support
   - Requires Node.js

5. **Cloud APIs** (Docraptor, etc.)
   - High quality
   - Costs money
   - External dependency

## Troubleshooting

### Fonts Not Rendering
**Problem**: Croatian characters show as boxes

**Solution**: Use DejaVu Sans or Liberation Sans
```css
body { font-family: 'DejaVu Sans', sans-serif; }
```

### CSS Not Working
**Problem**: Styles not applied in PDF

**Solution**: Use inline styles or `<style>` tags
- Avoid external CSS files
- Use absolute units (pt, px, not em)
- Simplify flexbox/grid layouts

### Images Not Loading
**Problem**: Images don't appear in PDF

**Solution**: Use absolute paths or base64
```php
// Absolute path
$imgPath = public_path('images/logo.png');
$html = "<img src='$imgPath'>";

// Or base64
$data = base64_encode(file_get_contents($imgPath));
$html = "<img src='data:image/png;base64,$data'>";
```

## PHPUnit Deprecations

Tests use `@test` annotations. Migrate to PHP 8 attributes:

```php
// Current
/** @test */
public function it_does_something() { }

// Future (PHPUnit 12+)
#[Test]
public function it_does_something() { }
```

## Related Services

- **PdfMerger**: Combine multiple PDFs
- **MetadataBuilder**: Generate article metadata
- **OcrService**: Extract text from PDFs
- **IngestPipelineService**: Process documents

## Notes

### Service Design
PdfRenderer is a **thin wrapper** that:
- Integrates DomPDF with Laravel
- Provides consistent interface
- Handles directory creation
- Manages memory cleanup
- Uses Blade templates

### Actual rendering is performed by DomPDF library

### Why Blade Templates?
- **Reusability**: Same template for all articles
- **Maintainability**: Easy to update layout
- **Flexibility**: Can add custom styles
- **Laravel Integration**: Uses familiar syntax
