# Re-OCR Workflow Documentation

## Overview

The **ReprocessTextractJob** provides a scalable, production-ready solution for reprocessing previously extracted documents through AWS Textract. This workflow enables manual re-OCR operations with complete audit trails, case preservation, and robust error handling.

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Component Breakdown](#component-breakdown)
3. [Complete Workflow](#complete-workflow)
4. [Database Schema & Metadata](#database-schema--metadata)
5. [Usage Examples](#usage-examples)
6. [Error Handling & Edge Cases](#error-handling--edge-cases)
7. [Integration Points](#integration-points)
8. [Monitoring & Debugging](#monitoring--debugging)
9. [Future Enhancements](#future-enhancements)

---

## Architecture Overview

### High-Level Design

```
┌─────────────────────────────────────────────────────────────┐
│                   ReprocessTextractJob                      │
│  (Queue Job - Entry Point for Re-OCR Operations)           │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ├─► 1. Archive existing TextractJob
                     │      - Mark as 'superseded'
                     │      - Preserve case_id & metadata
                     │      - Delete for clean slate
                     │
                     ├─► 2. Create fresh TextractJob
                     │      - Preserve case_id
                     │      - Link to previous job
                     │      - Status: 'queued'
                     │
                     └─► 3. ProcessDrivePdf::run()
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│              ProcessDrivePdf Action                         │
│       (Pipeline Orchestrator - 11 Steps)                    │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ├─► Step 1: EnsureJobStep
                     │           - firstOrCreate() finds fresh job
                     │           - Validates case_id exists
                     │
                     ├─► Step 2: DownloadDriveFileStep
                     │           - Download PDF from Google Drive
                     │
                     ├─► Step 3: UploadInputToS3Step
                     │           - Upload to S3 bucket
                     │
                     ├─► Step 4: StartAnalysisStep
                     │           - Start AWS Textract job
                     │
                     ├─► Step 5: WaitAndFetchStep
                     │           - Poll Textract for completion
                     │
                     ├─► Step 6: SaveResultsStep
                     │           - Save raw Textract JSON
                     │
                     ├─► Step 7: CollectLinesStep
                     │           - Extract text lines
                     │
                     ├─► Step 8: CheckOcrQualityStep
                     │           - Analyze OCR quality
                     │
                     ├─► Step 9: CreateMetadataStep
                     │           - Extract legal metadata
                     │
                     ├─► Step 10: ReconstructPdfStep
                     │            - Rebuild searchable PDF
                     │
                     └─► Step 11: PersistReconstructedStep
                                  - Upload final PDF
                                  - Mark job as 'succeeded'
```

### Key Components

| Component | Location | Purpose |
|-----------|----------|---------|
| **ReprocessTextractJob** | `app/Jobs/ReprocessTextractJob.php` | Queue job for re-OCR operations |
| **ProcessDrivePdf** | `app/Actions/Textract/ProcessDrivePdf.php` | Pipeline orchestrator |
| **TextractJob Model** | `app/Models/TextractJob.php` | Database record for tracking |
| **Pipeline Steps** | `app/Pipelines/Textract/*.php` | Individual processing steps |

---

## Component Breakdown

### ReprocessTextractJob

**Location**: `app/Jobs/ReprocessTextractJob.php`

**Responsibilities**:
- Manage existing TextractJob records
- Preserve case associations across reprocessing
- Create audit trail metadata
- Delegate to ProcessDrivePdf for actual processing

**Configuration**:
```php
public int $tries = 3;           // Retry attempts
public int $backoff = 60;        // Seconds between retries
public int $timeout = 600;       // 10-minute timeout
```

**Parameters**:
```php
public function __construct(
    public string $driveFileId,      // Google Drive file ID
    public string $driveFileName,    // File name
    public bool $forceTextract = true // Force reprocessing flag
)
```

---

## Complete Workflow

### Step-by-Step Execution

#### Phase 1: Job Archival (Lines 92-148)

```php
DB::transaction(function () {
    // 1. Find existing non-superseded/non-failed job
    $existingJob = TextractJob::where('drive_file_id', $driveFileId)
        ->whereNotIn('status', ['superseded', 'failed'])
        ->lockForUpdate() // ← Prevent race conditions
        ->first();

    if ($existingJob) {
        // 2. Validate case_id exists
        if (!$caseId = $existingJob->case_id) {
            throw new RuntimeException("No case_id associated");
        }

        // 3. Create audit metadata
        $previousJobMetadata = [
            'previous_job_id' => $existingJob->id,
            'previous_status' => $existingJob->status,
            'previous_s3_key' => $existingJob->s3_key,
            'previous_case_id' => $existingJob->case_id,
            'superseded_at' => now()->toIso8601String(),
            'reason' => 'Manual re-OCR requested',
        ];

        // 4. Mark as superseded
        $existingJob->update([
            'status' => 'superseded',
            'metadata' => [...audit trail...]
        ]);

        // 5. Delete to allow EnsureJobStep::firstOrCreate()
        $existingJob->delete();
    }
});
```

**Why Delete After Superseding?**

The `EnsureJobStep::firstOrCreate()` method (line 21) uses:
```php
$job = TextractJob::firstOrCreate(
    ['drive_file_id' => $driveFileId],  // ← Finds by drive_file_id only
    ['drive_file_name' => $driveFileName, 'status' => 'queued']
);
```

If we don't delete the old job, `firstOrCreate()` will find and return the superseded job, causing:
1. Processing to use a superseded job record
2. Status updates to fail (superseded → succeeded doesn't make sense)
3. Audit trail confusion

**Solution**: Delete after marking as superseded (metadata preserved for audit).

#### Phase 2: Fresh Job Creation (Lines 150-167)

```php
if ($caseId) {
    $newJob = TextractJob::create([
        'drive_file_id' => $this->driveFileId,
        'drive_file_name' => $this->driveFileName,
        'case_id' => $caseId,              // ← Preserved from old job
        'status' => 'queued',
        'metadata' => [
            'reprocessed_from' => $previousJobMetadata  // ← Audit trail
        ]
    ]);
}
```

**Why Create Before ProcessDrivePdf?**

The `EnsureJobStep` (line 27-29) validates case_id:
```php
if (!$job->case_id) {
    throw new RuntimeException('No case selected');
}
```

By creating the job here with preserved `case_id`, we ensure:
1. Validation passes in EnsureJobStep
2. Case association is maintained
3. No data loss during reprocessing

#### Phase 3: Pipeline Execution (Lines 169-171)

```php
ProcessDrivePdfAction::run($this->driveFileId, $this->driveFileName);
```

Executes the full 11-step pipeline:

1. **EnsureJobStep** - Finds fresh job via `firstOrCreate()`
2. **DownloadDriveFileStep** - Downloads PDF from Google Drive
3. **UploadInputToS3Step** - Uploads to S3 for Textract
4. **StartAnalysisStep** - Initiates AWS Textract analysis
5. **WaitAndFetchStep** - Polls Textract until completion
6. **SaveResultsStep** - Stores raw Textract JSON
7. **CollectLinesStep** - Extracts text lines from results
8. **CheckOcrQualityStep** - Analyzes OCR quality metrics
9. **CreateMetadataStep** - Extracts legal metadata
10. **ReconstructPdfStep** - Rebuilds searchable PDF
11. **PersistReconstructedStep** - Uploads final PDF, marks succeeded

---

## Database Schema & Metadata

### TextractJob Table Structure

```sql
CREATE TABLE textract_jobs (
    id BIGINT PRIMARY KEY,
    drive_file_id VARCHAR(255),      -- Google Drive file ID
    drive_file_name VARCHAR(255),    -- File name
    case_id BIGINT,                  -- Associated case (required)
    s3_key VARCHAR(255),             -- S3 object key
    job_id VARCHAR(255),             -- AWS Textract job ID
    status VARCHAR(50),              -- Status tracking
    error TEXT,                      -- Error messages
    metadata JSON,                   -- Flexible metadata
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Status Values

| Status | Description | Next Transition |
|--------|-------------|-----------------|
| `queued` | Job created, awaiting processing | → `processing` |
| `processing` | Currently being processed | → `succeeded` or `failed` |
| `succeeded` | Successfully completed | → `superseded` (if reprocessed) |
| `failed` | Processing failed | Manual intervention needed |
| `superseded` | Replaced by reprocessing | Terminal state |

### Metadata Structure

#### For Superseded Jobs

```json
{
  "superseded_at": "2025-10-25T18:30:00.000000Z",
  "superseded_by": "ReprocessTextractJob",
  "superseded_reason": "Manual re-OCR requested",
  "original_metadata": { /* preserved original metadata */ }
}
```

#### For Reprocessed Jobs

```json
{
  "reprocessed_from": {
    "previous_job_id": 123,
    "previous_status": "succeeded",
    "previous_s3_key": "textract/input/abc123.pdf",
    "previous_case_id": 456,
    "superseded_at": "2025-10-25T18:30:00.000000Z",
    "reason": "Manual re-OCR requested"
  },
  "ocr_quality": {
    "confidence_score": 0.95,
    "total_words": 1500
  }
}
```

---

## Usage Examples

### Basic Reprocessing

```php
use App\Jobs\ReprocessTextractJob;

// Dispatch for immediate reprocessing
ReprocessTextractJob::dispatch(
    driveFileId: '1abc...xyz',
    driveFileName: 'contract.pdf',
    forceTextract: true
);
```

### Conditional Reprocessing Based on Quality

```php
use App\Jobs\ReprocessTextractJob;
use App\Models\TextractJob;

$job = TextractJob::where('drive_file_id', $fileId)->first();
$ocrQuality = $job->metadata['ocr_quality']['confidence_score'] ?? 0;

if ($ocrQuality < 0.8) {
    ReprocessTextractJob::dispatch(
        $job->drive_file_id,
        $job->drive_file_name,
        true
    );

    Log::info("Reprocessing low-quality document", [
        'job_id' => $job->id,
        'quality' => $ocrQuality
    ]);
}
```

### Batch Reprocessing via Console Command

```php
// app/Console/Commands/ReprocessLowQualityDocs.php
use App\Jobs\ReprocessTextractJob;
use App\Models\TextractJob;

class ReprocessLowQualityDocs extends Command
{
    protected $signature = 'textract:reprocess-low-quality {threshold=0.8}';

    public function handle()
    {
        $threshold = (float) $this->argument('threshold');

        $jobs = TextractJob::where('status', 'succeeded')
            ->whereRaw("(metadata->>'ocr_quality.confidence_score')::float < ?", [$threshold])
            ->get();

        $this->info("Found {$jobs->count()} low-quality documents");

        foreach ($jobs as $job) {
            ReprocessTextractJob::dispatch(
                $job->drive_file_id,
                $job->drive_file_name,
                true
            );
        }

        $this->info("Dispatched {$jobs->count()} reprocessing jobs");
    }
}
```

### Integration with Livewire Component

```php
// app/Http/Livewire/TextractManager.php
use App\Jobs\ReprocessTextractJob;

public function reprocessJob(int $jobId): void
{
    $job = TextractJob::findOrFail($jobId);

    // Dispatch reprocessing job
    ReprocessTextractJob::dispatch(
        $job->drive_file_id,
        $job->drive_file_name,
        true
    );

    session()->flash('message', "Re-OCR job queued for: {$job->drive_file_name}");
    $this->refreshJobs();
}
```

---

## Error Handling & Edge Cases

### Edge Cases Handled

#### 1. Missing case_id Validation

```php
if (!$caseId) {
    throw new RuntimeException(
        "Cannot reprocess job {$existingJob->id}: no case_id associated. " .
        "Please assign a case to this document before reprocessing."
    );
}
```

**Why**: EnsureJobStep requires case_id, preventing silent failures.

#### 2. Race Condition Prevention

```php
$existingJob = TextractJob::where('drive_file_id', $driveFileId)
    ->whereNotIn('status', ['superseded', 'failed'])
    ->lockForUpdate() // ← Pessimistic locking
    ->first();
```

**Why**: Prevents multiple concurrent reprocess jobs from conflicting.

#### 3. Transaction Atomicity

```php
DB::transaction(function () {
    // Archive, update metadata, delete
});
```

**Why**: Ensures all-or-nothing execution if database operations fail.

#### 4. Graceful Failure Handling

```php
public function failed(Throwable $e): void
{
    $failedJob = TextractJob::where('drive_file_id', $this->driveFileId)
        ->whereNotIn('status', ['superseded', 'succeeded', 'failed'])
        ->latest()
        ->first();

    if ($failedJob) {
        $failedJob->update([
            'status' => 'failed',
            'error' => $e->getMessage(),
            'metadata' => [
                'failed_at' => now()->toIso8601String(),
                'failed_in' => 'ReprocessTextractJob',
                'exception_class' => get_class($e),
            ]
        ]);
    } else {
        Log::warning('No active job found to mark as failed');
    }
}
```

**Why**: Handles edge cases where job state is unexpected.

### Common Error Scenarios

| Error | Cause | Resolution |
|-------|-------|------------|
| `RuntimeException: no case_id` | Job has no associated case | Assign case before reprocessing |
| `Queue timeout` | Processing took > 10 minutes | Increase timeout or investigate bottleneck |
| `firstOrCreate conflict` | Multiple dispatches | Use queue locking or debouncing |
| `S3 upload failure` | Network/permissions issue | Check AWS credentials and retry |

---

## Integration Points

### Current Usage Locations

1. **TextractManager Livewire Component**
   - Location: `app/Http/Livewire/TextractManager.php:150`
   - Usage: Manual processing via UI
   ```php
   ProcessDrivePdf::run($job->drive_file_id, $job->drive_file_name);
   ```

2. **Console Command**
   - Location: `app/Console/Commands/TextractProcessDriveFolder.php:78`
   - Usage: Batch processing from Drive folder
   ```php
   ProcessDrivePdf::run($driveId, $name);
   ```

### Future Integration Points

1. **Automated Quality Checks**
   - Trigger reprocessing when OCR quality falls below threshold
   - Schedule periodic quality audits

2. **User-Initiated Reprocessing**
   - Add "Re-OCR" button to document viewer
   - Bulk reprocessing from document list

3. **Event-Driven Reprocessing**
   - Listen for document updates in Google Drive
   - Trigger on case assignment changes

---

## Monitoring & Debugging

### Logging Strategy

All operations are logged with structured context:

```php
Log::info('ReprocessTextractJob: Starting re-OCR', [
    'driveFileId' => $this->driveFileId,
    'driveFileName' => $this->driveFileName,
    'forceTextract' => $this->forceTextract,
]);
```

### Key Log Events

| Event | Log Level | Context Included |
|-------|-----------|------------------|
| Job started | `INFO` | driveFileId, driveFileName, forceTextract |
| Existing job found | `INFO` | existing_job_id, existing_status, case_id |
| Job archived | `INFO` | previous_job_id |
| Fresh job created | `INFO` | new_job_id, case_id, previous_job_id |
| Processing completed | `INFO` | driveFileId, previous_job_id, reprocessed |
| Job failed | `ERROR` | driveFileId, error, trace |
| No job to fail | `WARNING` | driveFileId |

### Database Queries for Monitoring

#### Find All Reprocessed Jobs

```sql
SELECT * FROM textract_jobs
WHERE JSON_CONTAINS_PATH(metadata, 'one', '$.reprocessed_from')
ORDER BY created_at DESC;
```

#### Find Superseded Jobs

```sql
SELECT * FROM textract_jobs
WHERE status = 'superseded'
ORDER BY updated_at DESC;
```

#### Track Reprocessing Chain

```sql
SELECT
    id,
    drive_file_id,
    status,
    metadata->'reprocessed_from'->>'previous_job_id' as previous_job_id,
    created_at
FROM textract_jobs
WHERE drive_file_id = '1abc...xyz'
ORDER BY created_at ASC;
```

#### OCR Quality Metrics

```sql
SELECT
    drive_file_name,
    (metadata->'ocr_quality'->>'confidence_score')::float as quality_score,
    status,
    created_at
FROM textract_jobs
WHERE status = 'succeeded'
ORDER BY quality_score ASC
LIMIT 20;
```

---

## Future Enhancements

### Short-Term (Next Sprint)

1. **UI Integration**
   - Add "Re-OCR" button to TextractManager
   - Show reprocessing history in job details
   - Display quality metrics

2. **Batch Operations**
   - Console command: `textract:reprocess-batch {case_id}`
   - API endpoint for bulk reprocessing

3. **Quality Dashboard**
   - Track reprocessing success rates
   - Show quality improvements over time
   - Alert on repeated failures

### Medium-Term (Next Quarter)

1. **Automated Quality Triggers**
   - Auto-reprocess when confidence < threshold
   - Schedule periodic quality audits
   - Smart retry logic based on error type

2. **Enhanced Metadata Tracking**
   - Track processing time per step
   - Cost tracking (AWS Textract usage)
   - Quality improvement metrics

3. **Soft Deletes Support**
   - Use Laravel soft deletes instead of hard delete
   - Enable recovery of superseded jobs
   - Compliance with data retention policies

### Long-Term (Future Roadmap)

1. **ML-Based Quality Prediction**
   - Predict which documents need reprocessing
   - Optimize Textract parameters per document type
   - A/B testing for OCR strategies

2. **Multi-Provider Support**
   - Fallback to Google Cloud Vision
   - Compare results across providers
   - Cost optimization

3. **Real-Time Processing**
   - WebSocket notifications for progress
   - Streaming results as they become available
   - Progressive enhancement of results

---

## Appendix A: Complete Code Reference

### File: app/Jobs/ReprocessTextractJob.php

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Actions\Textract\ProcessDrivePdf as ProcessDrivePdfAction;
use App\Models\TextractJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * ReprocessTextractJob
 *
 * Purpose: Queue job for re-OCR operations that forces reprocessing
 * of previously processed documents.
 */
class ReprocessTextractJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 600;

    public function __construct(
        public string $driveFileId,
        public string $driveFileName,
        public bool $forceTextract = true
    ) {}

    public function handle(): void { /* See implementation above */ }
    public function failed(Throwable $e): void { /* See implementation above */ }
}
```

---

## Appendix B: Related Documentation

- [Textract Legal Metadata](./TEXTRACT_LEGAL_METADATA.md)
- [Milestone B Improvements](./MILESTONE_B_IMPROVEMENTS.md)
- [Metadata Generation Optimization](./METADATA_GENERATION_OPTIMIZATION.md)

---

## Contributing

When modifying the re-OCR workflow:

1. **Maintain Audit Trails**: Always preserve metadata for compliance
2. **Test Edge Cases**: Especially around race conditions and missing data
3. **Update Documentation**: Keep this file in sync with code changes
4. **Monitor Performance**: Track processing times and costs
5. **Log Comprehensively**: Include context in all log statements

---

## License

Internal documentation for AI Legal War Machine project.

**Last Updated**: 2025-10-25
**Author**: Claude Code
**Version**: 1.0.0
