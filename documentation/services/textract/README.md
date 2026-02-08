# Textract OCR Pipeline Documentation

Comprehensive documentation for the Textract OCR processing pipeline in the AI Legal War Machine application.

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Pipeline Steps](#pipeline-steps)
- [Configuration](#configuration)
- [Usage](#usage)
- [Error Handling](#error-handling)
- [Performance Optimization](#performance-optimization)
- [Monitoring](#monitoring)
- [Troubleshooting](#troubleshooting)
- [API Reference](#api-reference)

## Overview

The Textract pipeline is a sophisticated OCR (Optical Character Recognition) system that processes PDF documents from Google Drive, extracts text using AWS Textract, and prepares the content for legal analysis and knowledge graph integration.

### Key Features

- **Automated PDF Processing**: Seamless integration with Google Drive
- **AWS Textract Integration**: High-quality OCR with confidence scoring
- **12-Step Pipeline**: Modular, maintainable architecture
- **Error Recovery**: Comprehensive error handling and retry logic
- **Quality Assurance**: OCR quality analysis and verification
- **Metadata Extraction**: Legal document metadata identification
- **PDF Reconstruction**: Searchable PDF generation with OCR overlay
- **Embedding Ready**: Automatic preparation for vector embeddings
- **Graph Sync Ready**: Integration with Neo4j knowledge graph

### Workflow Overview

```
Google Drive PDF → Download → S3 Upload → Textract Analysis
                                              ↓
Embeddings ← Content Extraction ← Quality Check ← OCR Results
    ↓
Neo4j Graph
```

## Architecture

### Design Pattern: Pipeline

The system uses Laravel's Pipeline pattern for processing, allowing each step to be:
- **Independent**: Each step is self-contained
- **Testable**: Easy to unit test individual steps
- **Maintainable**: Simple to add, remove, or modify steps
- **Traceable**: Clear flow of data through the system

### Action Pattern

Uses [Lorisleiva Actions](https://laravelactions.com/) for flexible invocation:
- Direct method call: `$action->handle()`
- Queued job: `ProcessDrivePdf::dispatch()`
- Command line: `php artisan process-drive-pdf`

### Models

#### TextractJob

**Location**: `app/Models/TextractJob.php`

Central model for tracking OCR processing jobs.

**Key Fields**:
```php
- drive_file_id: string      // Google Drive file ID
- drive_file_name: string    // Original file name
- case_id: ?int              // Associated legal case
- s3_key: ?string            // S3 input file location
- job_id: ?string            // AWS Textract job ID
- status: string             // pending, processing, succeeded, failed
- error: ?string             // Error message if failed
- metadata: array            // Processing metadata
- extracted_content: ?text   // OCR extracted text
- manual_content: ?text      // Manually edited content
- manually_edited: boolean   // Whether content was edited
- embedding_status: string   // pending, synced, failed
- graph_sync_status: string  // pending, synced, failed
```

**Statuses**:
- `pending`: Job created, awaiting processing
- `processing`: Currently being processed
- `succeeded`: Successfully processed
- `failed`: Processing failed with error

**Sync Statuses**:
- `pending`: Ready for sync
- `synced`: Successfully synced
- `failed`: Sync failed

## Pipeline Steps

The pipeline consists of 12 sequential steps, each handling a specific responsibility.

### 1. EnsureJobStep

**Purpose**: Create or retrieve TextractJob record

**Location**: `app/Pipelines/Textract/EnsureJobStep.php`

**Process**:
- Checks if job already exists for the file
- Creates new job record if needed
- Sets initial status to 'processing'
- Returns job instance in payload

**Payload**:
```php
Input:  ['driveFileId' => string, 'driveFileName' => string, ...]
Output: ['job' => TextractJob, ...]
```

**Error Handling**:
- Database connection failures
- Duplicate job creation (upsert logic)

### 2. DownloadDriveFileStep

**Purpose**: Download PDF from Google Drive

**Location**: `app/Pipelines/Textract/DownloadDriveFileStep.php`

**Process**:
- Uses GoogleDriveService to download file
- Stores temporarily in local filesystem
- Validates file is valid PDF
- Returns local file path

**Payload**:
```php
Input:  ['driveFileId' => string, ...]
Output: ['localFilePath' => string, ...]
```

**Error Handling**:
- File not found (404)
- Permission denied (403)
- Network timeout
- Invalid file format
- Disk space exhaustion

### 3. UploadInputToS3Step

**Purpose**: Upload PDF to S3 for Textract processing

**Location**: `app/Pipelines/Textract/UploadInputToS3Step.php`

**Process**:
- Uploads file to S3 textract-input bucket
- Generates unique S3 key
- Sets appropriate permissions
- Updates job with S3 key

**Payload**:
```php
Input:  ['localFilePath' => string, 'job' => TextractJob, ...]
Output: ['s3Key' => string, ...]
```

**Configuration**:
```env
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your_bucket
```

**Error Handling**:
- S3 bucket not found
- Insufficient permissions
- Network failures
- File size limits

### 4. StartAnalysisStep

**Purpose**: Start AWS Textract analysis job

**Location**: `app/Pipelines/Textract/StartAnalysisStep.php`

**Process**:
- Calls AWS Textract StartDocumentAnalysis API
- Specifies S3 location of input file
- Receives job ID for polling
- Updates TextractJob with job_id

**Payload**:
```php
Input:  ['s3Key' => string, 'job' => TextractJob, ...]
Output: ['jobId' => string, ...]
```

**AWS API Call**:
```php
$result = $textract->startDocumentAnalysis([
    'DocumentLocation' => [
        'S3Object' => [
            'Bucket' => $bucket,
            'Name' => $s3Key,
        ],
    ],
    'FeatureTypes' => ['TABLES', 'FORMS'],
]);
```

**Error Handling**:
- Invalid S3 object
- Textract service unavailable
- Rate limiting
- Invalid parameters

### 5. WaitAndFetchStep

**Purpose**: Poll Textract job until complete

**Location**: `app/Pipelines/Textract/WaitAndFetchStep.php`

**Process**:
- Polls GetDocumentAnalysis API
- Waits for job status to be SUCCEEDED
- Implements exponential backoff
- Downloads all result pages if paginated
- Handles FAILED status

**Payload**:
```php
Input:  ['jobId' => string, ...]
Output: ['textractResults' => array, ...]
```

**Polling Strategy**:
- Initial wait: 5 seconds
- Exponential backoff: 5s, 10s, 20s, 30s...
- Max wait per poll: 60 seconds
- Total timeout: 30 minutes

**Error Handling**:
- Job timeout
- Job failed status
- Network interruptions
- Malformed responses

### 6. SaveResultsStep

**Purpose**: Save raw Textract results to storage

**Location**: `app/Pipelines/Textract/SaveResultsStep.php`

**Process**:
- Serializes Textract results
- Saves to S3 or local storage
- Updates job metadata with result location
- Enables result replay for debugging

**Payload**:
```php
Input:  ['textractResults' => array, ...]
Output: ['resultsPath' => string, ...]
```

**Error Handling**:
- Storage failures
- Serialization errors

### 7. CollectLinesStep

**Purpose**: Parse Textract blocks into structured document

**Location**: `app/Pipelines/Textract/CollectLinesStep.php`

**Process**:
- Parses Textract blocks
- Organizes into pages and lines
- Extracts text, confidence, and geometry
- Builds OCR document structure

**Payload**:
```php
Input:  ['textractResults' => array, ...]
Output: ['ocrDocument' => object, ...]
```

**OCR Document Structure**:
```php
{
    "pages": [
        {
            "pageNumber": 1,
            "lines": [
                {
                    "text": "string",
                    "confidence": 98.5,
                    "geometry": {...},
                    "id": "line-1-1"
                }
            ],
            "width": 8.5,
            "height": 11.0
        }
    ],
    "documentMetadata": {
        "pageCount": 5,
        "format": "PDF"
    }
}
```

**Error Handling**:
- Malformed block data
- Missing required fields
- Invalid confidence scores

### 8. CheckOcrQualityStep

**Purpose**: Analyze OCR quality and confidence

**Location**: `app/Pipelines/Textract/CheckOcrQualityStep.php`

**Process**:
- Calculates average confidence score
- Identifies low-confidence sections
- Determines overall quality rating
- Flags documents needing manual review

**Payload**:
```php
Input:  ['ocrDocument' => object, ...]
Output: ['ocrQuality' => string, 'avgConfidence' => float, ...]
```

**Quality Ratings**:
- **high**: Average confidence > 90%
- **medium**: Average confidence 70-90%
- **low**: Average confidence < 70%

**Thresholds**:
```php
HIGH_CONFIDENCE = 90.0
MEDIUM_CONFIDENCE = 70.0
MIN_ACCEPTABLE = 50.0
```

**Error Handling**:
- Missing confidence data
- Invalid numeric values

### 9. CreateMetadataStep

**Purpose**: Extract legal document metadata

**Location**: `app/Pipelines/Textract/CreateMetadataStep.php`

**Process**:
- Analyzes document content for legal markers
- Extracts case numbers, dates, parties
- Identifies document type
- Stores structured metadata

**Payload**:
```php
Input:  ['ocrDocument' => object, ...]
Output: ['legalMetadata' => array, ...]
```

**Extracted Metadata**:
```php
[
    'documentType' => 'brief' | 'motion' | 'order' | 'complaint',
    'caseNumber' => '2023-CV-12345',
    'court' => 'Supreme Court',
    'parties' => ['Plaintiff', 'Defendant'],
    'filingDate' => '2023-01-15',
    'detectedLanguage' => 'en',
]
```

**Error Handling**:
- No metadata found (acceptable)
- Malformed content

### 10. ReconstructPdfStep

**Purpose**: Reconstruct searchable PDF with OCR overlay

**Location**: `app/Pipelines/Textract/ReconstructPdfStep.php`

**Process**:
- Overlays OCR text on original PDF
- Maintains original visual appearance
- Makes text selectable/searchable
- Preserves document formatting

**Payload**:
```php
Input:  ['localFilePath' => string, 'ocrDocument' => object, ...]
Output: ['reconstructedPdfPath' => string, ...]
```

**Technology**: FPDF or similar PDF manipulation library

**Error Handling**:
- PDF corruption
- Geometry mapping errors
- Font issues

### 11. UploadOutputStep

**Purpose**: Upload reconstructed PDF to S3

**Location**: `app/Pipelines/Textract/UploadOutputStep.php`

**Process**:
- Uploads reconstructed PDF to S3
- Stores in textract-output bucket
- Generates signed URL for access
- Updates job with output location

**Payload**:
```php
Input:  ['reconstructedPdfPath' => string, ...]
Output: ['outKey' => string, 'outUrl' => string, ...]
```

**Error Handling**:
- Upload failures
- Permission issues

### 12. PersistReconstructedStep

**Purpose**: Finalize job and prepare for downstream sync

**Location**: `app/Pipelines/Textract/PersistReconstructedStep.php`

**Process**:
- Updates job with final status
- Sets extracted_content
- Marks for embedding and graph sync
- Cleans up temporary files
- Triggers downstream jobs

**Payload**:
```php
Input:  ['job' => TextractJob, 'ocrDocument' => object, ...]
Output: Final payload with all data
```

**Job Updates**:
```php
$job->update([
    'status' => 'succeeded',
    'extracted_content' => $fullText,
    'embedding_status' => 'pending',
    'graph_sync_status' => 'pending',
]);
```

**Triggered Jobs**:
- `RegenerateTextractEmbeddings`: Create vector embeddings
- `SyncTextractToGraph`: Sync to Neo4j knowledge graph

**Error Handling**:
- Database failures
- Cleanup failures (logged, not fatal)

## Configuration

### Environment Variables

```env
# AWS Configuration
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=textract-bucket

# Google Drive Configuration
GOOGLE_DRIVE_CREDENTIALS_PATH=/path/to/credentials.json
GOOGLE_DRIVE_FOLDER_ID=folder_id

# Textract Configuration
TEXTRACT_POLL_INTERVAL=5
TEXTRACT_POLL_MAX_ATTEMPTS=360
TEXTRACT_TIMEOUT=1800

# Auto-sync Configuration
TEXTRACT_AUTO_SYNC=true
NEO4J_SYNC_ENABLED=false
```

### Configuration Files

**config/textract.php**:
```php
return [
    'auto_sync' => env('TEXTRACT_AUTO_SYNC', true),
    'poll_interval' => env('TEXTRACT_POLL_INTERVAL', 5),
    'max_attempts' => env('TEXTRACT_POLL_MAX_ATTEMPTS', 360),
    'timeout' => env('TEXTRACT_TIMEOUT', 1800),

    's3' => [
        'input_bucket' => env('AWS_BUCKET'),
        'output_bucket' => env('AWS_BUCKET'),
        'input_prefix' => 'textract-input/',
        'output_prefix' => 'textract-output/',
    ],

    'quality' => [
        'high_threshold' => 90.0,
        'medium_threshold' => 70.0,
        'min_acceptable' => 50.0,
    ],
];
```

## Usage

### Direct Invocation

```php
use App\Actions\Textract\ProcessDrivePdf;

$action = new ProcessDrivePdf();
$action->handle(
    driveFileId: '1abc123...',
    driveFileName: 'document.pdf',
    forceTextract: false
);
```

### Queued Job

```php
use App\Actions\Textract\ProcessDrivePdf;

ProcessDrivePdf::dispatch(
    '1abc123...',
    'document.pdf',
    false
);
```

### Artisan Command

```bash
php artisan textract:process {driveFileId} {driveFileName} --force
```

### Batch Processing

```php
use App\Actions\Textract\ListDrivePdfs;
use App\Actions\Textract\ProcessDrivePdf;

$listAction = new ListDrivePdfs($googleDriveService);
$files = $listAction->handle('folder_id');

foreach ($files as $file) {
    ProcessDrivePdf::dispatch(
        $file['id'],
        $file['name']
    );
}
```

### Retrieving Results

```php
use App\Models\TextractJob;

$job = TextractJob::where('drive_file_id', $fileId)->first();

// Get extracted content
$content = $job->extracted_content;

// Get manually edited content (if edited)
$content = $job->effective_content;

// Check status
if ($job->status === 'succeeded') {
    echo "Processing complete!";
}

// Get metadata
$metadata = $job->metadata;
$pageCount = $metadata['pageCount'];
$quality = $metadata['ocrQuality'];
```

## Error Handling

### Pipeline Error Flow

When any step throws an exception:

1. **Catch**: ProcessDrivePdf catches the exception
2. **Log**: Error logged with full stack trace
3. **Update**: Job status set to 'failed', error message stored
4. **Rethrow**: Exception re-thrown for job queue to handle

### Retry Logic

```php
// In job configuration
public $tries = 3;
public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min
public $timeout = 1800; // 30 minutes
```

### Common Errors

#### Google Drive Errors

```php
// File not found
catch (NotFoundException $e) {
    // Mark as failed, don't retry
}

// Permission denied
catch (ForbiddenException $e) {
    // Mark as failed, check permissions
}

// Rate limited
catch (RateLimitException $e) {
    // Retry with exponential backoff
}
```

#### AWS Textract Errors

```php
// Throttling
catch (ThrottlingException $e) {
    // Retry with backoff
}

// Invalid S3 object
catch (InvalidS3ObjectException $e) {
    // Check S3 upload, mark as failed
}

// Unsupported document
catch (UnsupportedDocumentException $e) {
    // Mark as failed, don't retry
}
```

#### Resource Errors

```php
// Memory exhaustion
ini_set('memory_limit', '512M');

// Disk space
if (disk_free_space('/') < 100 * 1024 * 1024) {
    throw new InsufficientStorageException();
}

// Timeouts
set_time_limit(1800); // 30 minutes
```

## Performance Optimization

### Batch Processing

Process multiple files concurrently using Laravel queues:

```php
// Queue configuration
'textract' => [
    'driver' => 'redis',
    'connection' => 'default',
    'queue' => 'textract',
    'retry_after' => 1800,
    'block_for' => null,
    'after_commit' => false,
],
```

```bash
# Run multiple workers
php artisan queue:work --queue=textract --tries=3 --timeout=1800 &
php artisan queue:work --queue=textract --tries=3 --timeout=1800 &
php artisan queue:work --queue=textract --tries=3 --timeout=1800 &
```

### Caching

Cache Google Drive file listings:

```php
$files = Cache::remember("drive_pdfs_{$folderId}", 300, function () use ($folderId) {
    return $listAction->handle($folderId);
});
```

### Database Optimization

Index critical columns:

```php
Schema::table('textract_jobs', function (Blueprint $table) {
    $table->index('drive_file_id');
    $table->index('status');
    $table->index('embedding_status');
    $table->index('graph_sync_status');
    $table->index(['status', 'created_at']);
});
```

### S3 Lifecycle Policies

Auto-delete temporary files:

```json
{
  "Rules": [
    {
      "Id": "DeleteOldTextractInput",
      "Filter": {
        "Prefix": "textract-input/"
      },
      "Status": "Enabled",
      "Expiration": {
        "Days": 7
      }
    }
  ]
}
```

## Monitoring

### Job Status Dashboard

```php
$stats = [
    'pending' => TextractJob::where('status', 'pending')->count(),
    'processing' => TextractJob::where('status', 'processing')->count(),
    'succeeded' => TextractJob::where('status', 'succeeded')->count(),
    'failed' => TextractJob::where('status', 'failed')->count(),

    'avg_processing_time' => TextractJob::where('status', 'succeeded')
        ->avg('performance_metrics->total_time'),
];
```

### Logging

All pipeline operations are logged:

```php
Log::info('ProcessDrivePdf: start', ['driveFileId' => $id]);
Log::error('ProcessDrivePdf: failed', ['error' => $e->getMessage()]);
Log::info('ProcessDrivePdf: succeeded', ['content_length' => $length]);
```

### Metrics

Track key performance indicators:

```php
// In performance_metrics field
[
    'download_time' => 15.2,
    'upload_time' => 8.5,
    'textract_time' => 120.3,
    'total_time' => 180.5,
    'file_size' => 2048576,
    'page_count' => 25,
]
```

### Alerts

Set up alerts for:
- High failure rate (> 10%)
- Long processing times (> 10 minutes)
- Queue backlog (> 100 jobs pending)
- Low OCR quality (avg confidence < 70%)

## Troubleshooting

### Job Stuck in Processing

**Symptom**: Job status remains 'processing' indefinitely

**Causes**:
- Worker crashed mid-processing
- Textract job timeout
- Network interruption

**Solution**:
```php
// Find stuck jobs
$stuckJobs = TextractJob::where('status', 'processing')
    ->where('processing_started_at', '<', now()->subHours(2))
    ->get();

// Reset to pending for retry
foreach ($stuckJobs as $job) {
    $job->update(['status' => 'pending']);
    ProcessDrivePdf::dispatch($job->drive_file_id, $job->drive_file_name);
}
```

### High Failure Rate

**Symptom**: Many jobs failing with similar errors

**Diagnosis**:
```php
$recentFailures = TextractJob::where('status', 'failed')
    ->where('updated_at', '>', now()->subHour())
    ->pluck('error');

// Group by error type
$errorCounts = $recentFailures->countBy(function ($error) {
    if (str_contains($error, 'S3')) return 'S3';
    if (str_contains($error, 'Drive')) return 'Drive';
    if (str_contains($error, 'Textract')) return 'Textract';
    return 'Other';
});
```

**Common Causes**:
- AWS credentials expired
- S3 bucket permissions changed
- Google Drive API quota exceeded
- Network issues

### Low OCR Quality

**Symptom**: OCR confidence scores consistently low

**Diagnosis**:
```php
$lowQualityJobs = TextractJob::where('metadata->avgConfidence', '<', 70)
    ->get();
```

**Causes**:
- Poor quality scans
- Handwritten text
- Complex layouts
- Non-English text

**Solutions**:
- Pre-process images (enhance contrast, deskew)
- Use manual review workflow
- Try alternative OCR engines for specific cases

### Memory Issues

**Symptom**: Jobs failing with memory errors

**Causes**:
- Large PDFs (100+ pages)
- High-resolution images
- Inefficient memory usage

**Solutions**:
```php
// Increase memory limit
ini_set('memory_limit', '512M');

// Process pages in chunks
foreach (array_chunk($pages, 10) as $chunk) {
    processChunk($chunk);
    gc_collect_cycles(); // Force garbage collection
}

// Use streaming for large files
```

## API Reference

### ProcessDrivePdf Action

```php
namespace App\Actions\Textract;

class ProcessDrivePdf
{
    /**
     * Process a single PDF from Google Drive
     *
     * @param string $driveFileId Google Drive file ID
     * @param string $driveFileName Original file name
     * @param bool $forceTextract Force Textract even if local OCR available
     * @return void
     * @throws \Exception on processing failure
     */
    public function handle(
        string $driveFileId,
        string $driveFileName,
        bool $forceTextract = false
    ): void
}
```

### ListDrivePdfs Action

```php
namespace App\Actions\Textract;

class ListDrivePdfs
{
    /**
     * List all PDFs in a Google Drive folder
     *
     * @param string $folderId Google Drive folder ID
     * @return array<array> List of file arrays
     */
    public function handle(string $folderId): array
}
```

### TextractJob Model

```php
namespace App\Models;

class TextractJob extends Model
{
    /**
     * Get effective content (manual if edited, otherwise extracted)
     *
     * @return string|null
     */
    public function getEffectiveContentAttribute(): ?string

    /**
     * Check if ready for embedding
     *
     * @return bool
     */
    public function isReadyForEmbedding(): bool

    /**
     * Check if ready for graph sync
     *
     * @return bool
     */
    public function isReadyForGraphSync(): bool

    /**
     * Mark content as manually edited
     *
     * @param int $userId User who edited
     * @return void
     */
    public function markAsEdited(int $userId): void
}
```

## Additional Resources

- [AWS Textract Documentation](https://docs.aws.amazon.com/textract/)
- [Google Drive API Documentation](https://developers.google.com/drive)
- [Laravel Pipeline Documentation](https://laravel.com/docs/helpers#pipeline)
- [Testing Guide](./TEXTRACT_TESTING.md)
- [Security Guide](./SECURITY.md)

## Contributing

When modifying the pipeline:

1. Follow the single responsibility principle for steps
2. Maintain backward compatibility with payload structure
3. Add comprehensive error handling
4. Update this documentation
5. Add tests for new functionality
6. Update performance benchmarks

## Questions?

For questions about the pipeline:

1. Review this documentation
2. Check existing pipeline steps for examples
3. Review test suites for usage patterns
4. Open an issue on GitHub
