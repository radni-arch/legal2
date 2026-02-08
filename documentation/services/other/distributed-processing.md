# Distributed Processing System for Large-Scale Document Ingestion

## Overview

The Distributed Processing System enables parallel, scalable processing of large volumes of documents using Redis queues, multiple workers, and intelligent prioritization. It transforms single-threaded Textract processing into a high-throughput, horizontally scalable system.

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        INPUT: Google Drive Folder                        │
│                           (1000+ PDF documents)                          │
└────────────────────────────────┬────────────────────────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                      TextractProcessDriveFolder                          │
│                           (Command/Orchestrator)                         │
│                                                                          │
│  • Scan folder                                                           │
│  • Create TextractBatch                                                  │
│  • Create TextractJob records                                            │
│  • Dispatch jobs to queues with priority                                │
└────────────────────────────────┬────────────────────────────────────────┘
                                 │
                  ┌──────────────┴──────────────┐
                  │                             │
                  ▼                             ▼
┌─────────────────────────────┐   ┌─────────────────────────────┐
│   REDIS QUEUES (Priority)   │   │    Batch Tracking (DB)      │
│                             │   │                             │
│  • textract-high (P:100)    │   │  • Total files: 1000        │
│  • textract (P:50)          │   │  • Processed: 0 → 1000      │
│  • textract-low (P:10)      │   │  • Failed: tracked          │
│  • textract-tables (P:30)   │   │  • Progress: 0% → 100%      │
│  • embeddings (P:20)        │   │  • Statistics: real-time    │
└──────────────┬──────────────┘   └─────────────────────────────┘
               │
               │  Multiple Workers (Horizontal Scaling)
               │
      ┌────────┼────────┬────────┬────────┐
      │        │        │        │        │
      ▼        ▼        ▼        ▼        ▼
┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐
│ Worker 1 │ │ Worker 2 │ │ Worker 3 │ │ Worker N │
│  PID:101 │ │  PID:102 │ │  PID:103 │ │  PID:N   │
└─────┬────┘ └─────┬────┘ └─────┬────┘ └─────┬────┘
      │            │            │            │
      └────────────┴────────────┴────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                      ProcessTextractJob                                  │
│                                                                          │
│  1. Mark job as processing                                               │
│  2. Download PDF from Google Drive                                       │
│  3. Upload to S3 (input bucket)                                          │
│  4. Start AWS Textract analysis                                          │
│  5. Poll for completion (async)                                          │
│  6. Download Textract JSON                                               │
│  7. Reconstruct searchable PDF                                           │
│  8. Upload to S3 (output bucket)                                         │
│  9. Store performance metrics                                            │
│  10. Update batch progress                                               │
└────────────────────────────────┬────────────────────────────────────────┘
                                 │
                    ┌────────────┴────────────┐
                    │                         │
                    ▼                         ▼
┌─────────────────────────────┐   ┌─────────────────────────────┐
│ ExtractTablesFromTextractJob│   │   GenerateEmbeddingsJob     │
│                             │   │                             │
│  • Parse TABLE blocks       │   │  • Extract text             │
│  • Organize cells → rows    │   │  • Generate embeddings      │
│  • Extract structured data  │   │  • Store in vector DB       │
│  • Export CSV/JSON          │   │  • Track tokens/cost        │
│  • Store in metadata        │   │  • Update embedding batch   │
└─────────────────────────────┘   └─────────────────────────────┘
                    │                         │
                    └────────────┬────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                            OUTPUTS                                       │
│                                                                          │
│  • Searchable PDFs (S3)                                                  │
│  • Textract JSON (S3)                                                    │
│  • Extracted tables (Database + S3)                                      │
│  • Vector embeddings (PostgreSQL pgvector)                               │
│  • Performance metrics (Database)                                        │
│  • Batch statistics (Database)                                           │
└─────────────────────────────────────────────────────────────────────────┘
```

## Key Features

### 1. **Parallel Processing with Redis Queues**

Multiple workers process jobs simultaneously:

```bash
# Start 4 workers on high-priority queue
php artisan queue:work redis --queue=textract-high --tries=3 --timeout=1800 &
php artisan queue:work redis --queue=textract-high --tries=3 --timeout=1800 &
php artisan queue:work redis --queue=textract-high --tries=3 --timeout=1800 &
php artisan queue:work redis --queue=textract-high --tries=3 --timeout=1800 &

# Start 8 workers on normal-priority queue
for i in {1..8}; do
    php artisan queue:work redis --queue=textract --tries=3 --timeout=1800 &
done
```

### 2. **Queue Prioritization**

Jobs are processed based on priority:

| Queue | Priority | Use Case | Workers |
|-------|----------|----------|---------|
| `textract-high` | 100 | Urgent documents | 4 |
| `textract` | 50 | Normal processing | 8 |
| `textract-low` | 10 | Batch operations | 2 |
| `textract-tables` | 30 | Table extraction | 4 |
| `embeddings` | 20 | Vector generation | 6 |

### 3. **Batch Tracking**

Monitor progress in real-time:

```bash
# Monitor batch progress
php artisan textract:batch-status {batch-id} --watch

# Output:
# Batch: 9c8f3b2a-1234-5678-90ab-cdef12345678
# Status: processing
# Progress: [========================      ] 60%
# Total Files: 100
# Processed: 60
# Failed: 2
# Success Rate: 96.7%
# Estimated remaining: 180 seconds
```

### 4. **Automatic Retry with Exponential Backoff**

Failed jobs retry automatically:

```php
public $tries = 3;
public $backoff = [60, 300, 900]; // 1min, 5min, 15min
```

### 5. **Table Extraction from PDF**

Enhanced Textract processing includes table parsing:

```php
// Tables automatically extracted with structure
[
    'id' => 'table_1',
    'confidence' => 95.3,
    'rows' => [
        [
            ['text' => 'Header 1', 'is_header' => true],
            ['text' => 'Header 2', 'is_header' => true],
        ],
        [
            ['text' => 'Value 1'],
            ['text' => 'Value 2'],
        ],
    ],
    'structured_data' => [
        ['Header 1' => 'Value 1', 'Header 2' => 'Value 2'],
    ],
]
```

### 6. **Batch Embedding Generation**

Process embeddings in batches:

```php
// Automatically dispatched after Textract completion
GenerateEmbeddingsJob::dispatch($jobId)
    ->onQueue('embeddings')
    ->delay(now()->addSeconds(5));
```

## Setup

The distributed processing system supports **two queue drivers**:
- **Database queue** (default) - Perfect for localhost, no Redis needed
- **Redis queue** - Recommended for production, better performance at scale

### Localhost Setup (Database Queue)

**No Redis required!** The system works out of the box with PostgreSQL.

#### 1. Run the migration

```bash
php artisan migrate
```

This creates the necessary queue tables: `jobs`, `failed_jobs`, `job_batches`

#### 2. Configure environment

Your `.env` should have:

```env
QUEUE_CONNECTION=database
```

#### 3. Start queue workers

```bash
# Start a single worker (foreground)
php artisan queue:work --queue=textract --tries=3 --timeout=1800

# Start multiple workers (separate terminals)
php artisan queue:work --queue=textract --tries=3 --timeout=1800
php artisan queue:work --queue=textract --tries=3 --timeout=1800
php artisan queue:work --queue=textract --tries=3 --timeout=1800

# Or use background jobs (Linux/Mac)
php artisan queue:work --queue=textract --tries=3 --timeout=1800 &
```

#### 4. Process documents

```bash
# Process with batch tracking
php artisan textract:process-drive-folder {FOLDER_ID} \
    --case={CASE_ID} \
    --batch \
    --parallel \
    --limit=10

# Monitor progress
php artisan textract:batch-status {batch-id} --watch
```

**That's it!** No Redis installation or configuration needed for local development.

### Production Setup (Redis Queue)

For production workloads, Redis provides better performance and reliability.

#### 1. Install and configure Redis

```bash
# Ubuntu/Debian
sudo apt-get install redis-server
sudo systemctl start redis

# macOS
brew install redis
brew services start redis
```

#### 2. Install PHP Redis extension

```bash
# Ubuntu/Debian
sudo apt-get install php-redis

# macOS
pecl install redis
```

#### 3. Configure environment

Update your `.env`:

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
QUEUE_PREFIX=queues
```

#### 4. Start queue workers

```bash
# High-priority queue (4 workers)
for i in {1..4}; do
    php artisan queue:work redis --queue=textract-high --tries=3 --timeout=1800 &
done

# Normal priority queue (8 workers)
for i in {1..8}; do
    php artisan queue:work redis --queue=textract --tries=3 --timeout=1800 &
done

# Low priority queue (2 workers)
for i in {1..2}; do
    php artisan queue:work redis --queue=textract-low --tries=2 --timeout=3600 &
done

# Table extraction queue (4 workers)
for i in {1..4}; do
    php artisan queue:work redis --queue=textract-tables --tries=2 --timeout=600 &
done

# Embeddings queue (6 workers)
for i in {1..6}; do
    php artisan queue:work redis --queue=embeddings --tries=2 --timeout=900 &
done
```

#### 5. Use Supervisor for production

Create `/etc/supervisor/conf.d/laravel-queue.conf`:

```ini
[program:laravel-queue-textract]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis --queue=textract --tries=3 --timeout=1800
autostart=true
autorestart=true
user=www-data
numprocs=8
redirect_stderr=true
stdout_logfile=/var/log/laravel-queue-textract.log

[program:laravel-queue-textract-high]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis --queue=textract-high --tries=3 --timeout=1800
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/laravel-queue-textract-high.log
```

Then:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

### Queue Driver Comparison

| Feature | Database Queue | Redis Queue |
|---------|---------------|-------------|
| **Setup Complexity** | Simple (built-in) | Moderate (requires Redis) |
| **Performance** | Good for <100 jobs/min | Excellent for high volume |
| **Localhost Development** | ✅ Perfect | Overkill |
| **Production** | ⚠️ Works, but limited | ✅ Recommended |
| **Horizontal Scaling** | ✅ Supported | ✅ Supported |
| **Priority Queues** | ✅ Supported | ✅ Supported |
| **Job Retries** | ✅ Supported | ✅ Supported |
| **Monitoring** | Database queries | Redis commands |
| **Memory Usage** | Database storage | In-memory (fast) |

**Recommendation**: Use **database** queue for localhost development and **Redis** queue for production deployments.

## Usage

### Basic Usage: Process Folder with Parallel Workers

```bash
# Start workers (in separate terminals or as background jobs)
# Note: Works with both 'database' and 'redis' queue drivers
php artisan queue:work --queue=textract --tries=3 --timeout=1800

# Process folder with batch tracking
php artisan textract:process-drive-folder {FOLDER_ID} \
    --case={CASE_ID} \
    --batch \
    --parallel \
    --limit=100

# Output:
# Listing PDFs in Drive folder: abc123def456
# Mode: PARALLEL QUEUE
# Batch tracking: ENABLED
# Created batch: 9c8f3b2a-...
# QUEUE [textract] Priority:0: document001.pdf (file-id-1)
# QUEUE [textract] Priority:0: document002.pdf (file-id-2)
# ...
# Ukupno obrađeno/poslano u red: 100
# Batch ID: 9c8f3b2a-...
# Monitor with: php artisan textract:batch-status 9c8f3b2a-...
# Parallel mode: All jobs dispatched simultaneously
# Start workers with: php artisan queue:work --queue=textract
```

### Advanced Usage: Priority Queues

```bash
# High-priority urgent documents
php artisan textract:process-drive-folder {FOLDER_ID} \
    --case={CASE_ID} \
    --queue=textract-high \
    --priority=100 \
    --parallel \
    --limit=10

# Low-priority batch processing
php artisan textract:process-drive-folder {FOLDER_ID} \
    --case={CASE_ID} \
    --queue=textract-low \
    --priority=10 \
    --limit=1000
```

### Table Extraction

```bash
# Enable automatic table extraction
php artisan textract:process-drive-folder {FOLDER_ID} \
    --case={CASE_ID} \
    --extract-tables \
    --parallel

# Tables will be extracted and stored in job metadata
```

### Monitor Batch Progress

```bash
# Show batch status
php artisan textract:batch-status {batch-id}

# Watch in real-time (updates every 2 seconds)
php artisan textract:batch-status {batch-id} --watch

# Show all batches
php artisan textract:batch-status --all
```

## Configuration

### Queue Configuration

Edit `config/distributed-processing.php`:

```php
'queues' => [
    'textract-high' => [
        'priority' => 100,
        'workers' => 4,
        'timeout' => 1800,
        'tries' => 3,
    ],
    'textract' => [
        'priority' => 50,
        'workers' => 8,
        'timeout' => 1800,
        'tries' => 3,
    ],
    // ... more queues
],
```

### Worker Scaling

```php
'scaling' => [
    'enabled' => true,
    'min_workers' => 2,
    'max_workers' => 20,
    'scale_up_threshold' => 100,
    'scale_down_threshold' => 10,
],
```

### Environment Variables

Add to `.env`:

```env
# Queue Configuration
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis

# Distributed Processing
DISTRIBUTED_SCALING_ENABLED=false

# Textract Configuration
TEXTRACT_AUTO_EMBEDDINGS=true
TEXTRACT_AUTO_TABLES=true

# Embedding Configuration
EMBEDDING_MODEL=text-embedding-3-small
```

## Database Schema

### textract_jobs (Enhanced)

```sql
-- New columns added for distributed processing
queue_name VARCHAR(255) DEFAULT 'textract'
priority INT DEFAULT 0
retry_count INT DEFAULT 0
worker_id INT NULL
queued_at TIMESTAMP NULL
processing_started_at TIMESTAMP NULL
performance_metrics JSON NULL

-- Indexes for performance
INDEX(queue_name)
INDEX(priority)
INDEX(status)
INDEX(status, priority)
```

### textract_batches (New)

```sql
id UUID PRIMARY KEY
batch_type VARCHAR(255)  -- 'drive_folder', 'manual', 'scheduled'
source_identifier VARCHAR(255)
total_files INT DEFAULT 0
processed_files INT DEFAULT 0
failed_files INT DEFAULT 0
status VARCHAR(50) DEFAULT 'pending'
configuration JSON
statistics JSON
started_at TIMESTAMP
completed_at TIMESTAMP
```

### embedding_batches (New)

```sql
id UUID PRIMARY KEY
source_type VARCHAR(255)  -- 'textract_job', 'law', 'decision'
total_items INT DEFAULT 0
processed_items INT DEFAULT 0
failed_items INT DEFAULT 0
status VARCHAR(50) DEFAULT 'pending'
embedding_model VARCHAR(255) DEFAULT 'text-embedding-3-small'
item_ids JSON
configuration JSON
tokens_used INT DEFAULT 0
cost DECIMAL(10,4) DEFAULT 0
started_at TIMESTAMP
completed_at TIMESTAMP
```

## Performance Metrics

### Single Worker vs Multiple Workers

| Workers | Documents | Time (Old) | Time (New) | Speedup |
|---------|-----------|------------|------------|---------|
| 1 | 100 | 8.3 hours | 8.3 hours | 1x |
| 4 | 100 | 8.3 hours | 2.1 hours | 4x |
| 8 | 100 | 8.3 hours | 1.0 hour | 8x |
| 16 | 100 | 8.3 hours | 0.5 hours | 16x |

### Cost Analysis

Assuming average document: 10 pages, 5 minutes processing time

| Documents | Workers | Time | AWS Cost | Total Cost |
|-----------|---------|------|----------|------------|
| 1,000 | 1 | 83 hours | ~$150 | ~$150 |
| 1,000 | 8 | 10.4 hours | ~$150 | ~$150 |
| 10,000 | 16 | 52 hours | ~$1,500 | ~$1,500 |

**Key Insight**: Processing time scales linearly with workers, but cost remains the same. More workers = faster results, same cost.

## Table Extraction Details

### Extraction Process

```
PDF Document
    ↓
AWS Textract (TABLE blocks)
    ↓
TableExtractorService
    ├→ Find TABLE blocks
    ├→ Get CELL blocks
    ├→ Organize into rows/columns
    ├→ Extract text from each cell
    ├→ Detect headers
    ├→ Build structured data
    ↓
Output Formats:
    • Structured JSON (with headers)
    • CSV export
    • Stored in job metadata
```

### Table Structure Example

**Input PDF Table**:
```
| Name    | Age | City   |
|---------|-----|--------|
| John    | 30  | Zagreb |
| Maria   | 25  | Split  |
```

**Extracted Structure**:
```json
{
  "tables": [
    {
      "id": "table_1",
      "confidence": 95.3,
      "metadata": {
        "row_count": 3,
        "column_count": 3,
        "page": 1
      },
      "structured_data": [
        {
          "Name": "John",
          "Age": "30",
          "City": "Zagreb"
        },
        {
          "Name": "Maria",
          "Age": "25",
          "City": "Split"
        }
      ]
    }
  ]
}
```

### Accessing Extracted Tables

```php
$job = TextractJob::find($jobId);
$tables = $job->metadata['tables'] ?? [];

foreach ($tables as $table) {
    echo "Table found on page {$table['metadata']['page']}\n";
    echo "Rows: {$table['metadata']['row_count']}\n";
    echo "Columns: {$table['metadata']['column_count']}\n";

    // Export to CSV
    $csv = app(TableExtractorService::class)->exportTableToCsv($table);
    file_put_contents("table_{$table['id']}.csv", $csv);
}
```

## Monitoring & Troubleshooting

### Monitor Queue Length

```bash
# Check Redis queue length
redis-cli LLEN queues:textract
redis-cli LLEN queues:textract-high
redis-cli LLEN queues:embeddings
```

### Monitor Workers

```bash
# Check running workers
ps aux | grep "queue:work"

# Monitor worker output
tail -f storage/logs/laravel.log | grep ProcessTextractJob
```

### Performance Metrics

```php
// Check job performance metrics
$job = TextractJob::find($jobId);
$metrics = $job->performance_metrics;

echo "Duration: {$metrics['duration_seconds']}s\n";
echo "Pages: {$metrics['pages_processed']}\n";
echo "Tables: {$metrics['tables_extracted']}\n";
echo "Worker: {$metrics['worker_id']}\n";
echo "Memory: {$metrics['memory_peak_mb']}MB\n";
```

### Common Issues

**Issue**: Jobs stuck in pending
- **Solution**: Start queue workers
  ```bash
  php artisan queue:work redis --queue=textract
  ```

**Issue**: High failure rate
- **Solution**: Check AWS credentials, S3 permissions, Textract limits

**Issue**: Slow processing
- **Solution**: Add more workers or increase worker timeout

**Issue**: Redis connection errors
- **Solution**: Check Redis is running, verify `REDIS_HOST` in `.env`

## Horizontal Scaling

### Single Server

```bash
# Run multiple workers on one server
for i in {1..16}; do
    php artisan queue:work redis --queue=textract --tries=3 --timeout=1800 &
done
```

### Multiple Servers

**Server 1** (High Priority):
```bash
for i in {1..8}; do
    php artisan queue:work redis --queue=textract-high --tries=3 --timeout=1800 &
done
```

**Server 2** (Normal Priority):
```bash
for i in {1..16}; do
    php artisan queue:work redis --queue=textract --tries=3 --timeout=1800 &
done
```

**Server 3** (Embeddings):
```bash
for i in {1..8}; do
    php artisan queue:work redis --queue=embeddings --tries=2 --timeout=900 &
done
```

### Docker Scaling

```yaml
services:
  worker:
    image: your-app:latest
    command: php artisan queue:work redis --queue=textract --tries=3
    deploy:
      replicas: 16
    environment:
      - QUEUE_CONNECTION=redis
      - REDIS_HOST=redis
```

## Best Practices

### 1. **Use Batch Tracking**
Always use `--batch` flag for visibility:
```bash
php artisan textract:process-drive-folder {FOLDER_ID} --case={CASE_ID} --batch
```

### 2. **Monitor Progress**
Watch batches in real-time:
```bash
php artisan textract:batch-status {batch-id} --watch
```

### 3. **Prioritize Appropriately**
- Use `textract-high` for urgent documents (< 10 docs)
- Use `textract` for normal processing (10-100 docs)
- Use `textract-low` for large batches (> 100 docs)

### 4. **Scale Workers Appropriately**
- Start with 4-8 workers
- Monitor queue length
- Add workers if queue backs up
- Reduce workers if queue is empty

### 5. **Enable Table Extraction Selectively**
Only use `--extract-tables` for documents likely to contain tables (contracts, financial docs)

### 6. **Monitor Costs**
Track AWS Textract costs:
```php
$batch = TextractBatch::find($batchId);
$estimatedCost = $batch->total_files * 0.015; // ~$0.015 per page (10 pages avg)
```

## Future Enhancements

- [ ] Auto-scaling workers based on queue length
- [ ] Dead letter queue for permanently failed jobs
- [ ] Webhook notifications on batch completion
- [ ] Integration with CloudWatch for AWS metrics
- [ ] Support for other document types (DOCX, XLSX)
- [ ] Intelligent retry with different strategies
- [ ] Cost optimization with AWS Batch

## Support

For issues:
- Check logs: `storage/logs/laravel.log`
- Monitor batch: `php artisan textract:batch-status`
- View queue: `redis-cli LLEN queues:textract`

## Summary

The Distributed Processing System transforms Textract from a single-threaded bottleneck into a high-throughput, scalable document processing pipeline. With Redis queues, parallel workers, and intelligent prioritization, you can process thousands of documents in hours instead of days.

**Key Achievements**:
- ✅ Parallel processing with multiple workers
- ✅ Queue prioritization for urgent documents
- ✅ Batch tracking with real-time progress
- ✅ Automatic retry with exponential backoff
- ✅ Table extraction from PDFs
- ✅ Batch embedding generation
- ✅ Horizontal scaling ready
- ✅ Performance metrics tracking
- ✅ Cost-efficient processing

**Performance**: 8-16x faster than single-threaded processing, same AWS cost.
