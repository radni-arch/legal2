# TextractManager Setup & Quick Start Guide

## Overview
This guide helps you set up and test the TextractManager content editing and synchronization features.

## Prerequisites

### 1. System Requirements
- PHP 8.1+
- PostgreSQL with pgvector extension (optional, using JSON for now)
- Redis or database queue driver
- Neo4j 4.4+ (optional, for graph sync)
- OpenAI API key

### 2. Required Services
- Google Drive API access
- AWS Textract access
- AWS S3 bucket

---

## Initial Setup

### Step 1: Database Migration

Run the new migrations to add content management fields:

```bash
# Run migrations
php artisan migrate

# Verify tables
php artisan tinker
>>> \App\Models\TextractJob::first()
>>> \App\Models\TextractDocument::count()
```

Expected output:
- `textract_jobs` table has new columns: `extracted_content`, `manual_content`, `manually_edited`, `content_edited_at`, `edited_by`, `embedding_status`, `graph_sync_status`, `embedding_synced_at`, `graph_synced_at`
- `textract_documents` table exists

### Step 2: Environment Configuration

Update your `.env` file:

```env
# Required
OPENAI_API_KEY=sk-...
TEXTRACT_AUTO_SYNC=true

# Optional (defaults shown)
TEXTRACT_EMBEDDING_MODEL=text-embedding-3-small
TEXTRACT_CHUNK_SIZE=1000
TEXTRACT_CHUNK_OVERLAP=200
TEXTRACT_EMBEDDING_BATCH_SIZE=100

# Neo4j (optional)
NEO4J_SYNC_ENABLED=false
# or true if you want graph sync
```

Clear config cache:

```bash
php artisan config:clear
php artisan config:cache
```

### Step 3: Queue Worker

Start a queue worker in a separate terminal:

```bash
php artisan queue:work --queue=default --tries=3 --timeout=600 --verbose
```

Keep this running throughout testing.

### Step 4: Web Server

Start the Laravel development server:

```bash
php artisan serve
```

Or use Laravel Herd/Valet if preferred.

---

## Quick Testing Workflow

### Test 1: Process a Document

1. Navigate to TextractManager page:
   ```
   http://localhost:8000/textract
   ```

2. **Sync from Google Drive:**
   - Enter your Google Drive folder ID
   - Click "📥 Sync from Drive"
   - Wait for files to appear in the job list

3. **Process a Job:**
   - Find a job with status "queued"
   - Select a case from the dropdown
   - Click "💾 Assign"
   - Click "🚀 Process (Async)"
   - Monitor the queue worker terminal for progress

4. **Wait for Completion:**
   - Job status will change: queued → uploading → started → analyzing → reconstructing → succeeded
   - This typically takes 2-5 minutes depending on document size

### Test 2: View Content

1. Once job status is "succeeded":
   - Click "👁️ View Content" button

2. **Explore Content Tab:**
   - Check document statistics (words, chars, lines, chunks)
   - Scroll through extracted content
   - Note that content is OCR'd text from AWS Textract

3. **Check Metadata Tab:**
   - Click "📊 Metadata" tab
   - View the JSON metadata extracted from the document
   - Check case assignment

4. **Check Sync Status Tab:**
   - Click "🔄 Sync Status" tab
   - Should show:
     - Embedding Status: ⏳ Pending
     - Graph Sync Status: ⏳ Pending
   - *Note: If auto_sync is enabled, these should change to "synced" within a minute as queue processes*

### Test 3: Edit Content

1. **Open Edit Modal:**
   - Click "✏️ Edit Content" button
   - Read the warning banner about sync triggers

2. **Make Changes:**
   - Modify some text in the textarea
   - Watch word/char count update
   - Example: Fix OCR errors, add clarifications, etc.

3. **Save Changes:**
   - Click "💾 Save Changes"
   - Watch for success toast: "Content saved successfully. Embeddings and graph sync will be regenerated."
   - Modal should close
   - Job list should refresh
   - "✏️ Edited" badge should appear on job card

4. **Monitor Queue:**
   - In queue worker terminal, watch for:
     ```
     Processing: App\Jobs\RegenerateTextractEmbeddings
     Processing: App\Jobs\SyncTextractToGraph
     ```
   - These run automatically due to the model observer

5. **Verify Sync Completed:**
   - Open "View Content" modal again
   - Go to "Sync Status" tab
   - Should now show:
     - Embedding Status: ✅ Synced (with timestamp)
     - Graph Sync Status: ✅ Synced (with timestamp)

### Test 4: Check Database

Verify data is stored correctly:

```bash
php artisan tinker
```

```php
// Check the job
$job = \App\Models\TextractJob::latest()->first();
echo "Status: {$job->status}\n";
echo "Manually Edited: " . ($job->manually_edited ? 'Yes' : 'No') . "\n";
echo "Edited By: {$job->edited_by}\n";
echo "Content Length: " . mb_strlen($job->manual_content) . "\n";
echo "Embedding Status: {$job->embedding_status}\n";
echo "Graph Sync Status: {$job->graph_sync_status}\n";

// Check documents
$docs = \App\Models\TextractDocument::where('textract_job_id', $job->id)->get();
echo "Total Chunks: " . $docs->count() . "\n";
echo "Completed: " . $docs->where('processing_status', 'completed')->count() . "\n";

// Check first document
$first = $docs->first();
if ($first) {
    echo "Embedding Dimensions: " . count($first->embedding) . "\n";
    echo "Token Count: {$first->token_count}\n";
    echo "Model: {$first->embedding_model}\n";
}
```

### Test 5: Check Neo4j (If Enabled)

Open Neo4j Browser (http://localhost:7474) and run:

```cypher
// Find TextractDocument nodes
MATCH (d:TextractDocument)
RETURN d
LIMIT 10

// Find relationships
MATCH (d:TextractDocument)-[r]->(n)
RETURN d, r, n
LIMIT 20

// Find keywords extracted
MATCH (d:TextractDocument)-[:HAS_KEYWORD]->(k:Keyword)
RETURN d.drive_file_name, k.term
LIMIT 50
```

### Test 6: Reset Content

1. **Open edited job:**
   - Click "✏️ Edit Content" on a job with "Edited" badge

2. **Reset to Original:**
   - Click "🔄 Reset to Original" button
   - Confirm in dialog
   - Success toast should appear
   - Content should revert to original OCR extraction

3. **Verify Reset:**
   - "Edited" badge should disappear
   - Sync jobs should trigger again
   - Database should show `manually_edited = false`

### Test 7: Manual Sync

1. **Simulate Failed Sync:**
   - In database, manually set:
     ```sql
     UPDATE textract_jobs SET embedding_status = 'failed' WHERE id = X;
     ```

2. **Trigger Manual Sync:**
   - Open job details modal (👁️ Details button)
   - Scroll to "Synchronization Status" section
   - Click 🔄 button next to embedding status
   - Success toast should appear
   - Status should change to "pending"
   - Queue worker should process it

3. **Verify Success:**
   - Status should update to "synced"
   - TextractDocuments should be created

---

## Common Issues & Solutions

### Issue 1: Queue Jobs Not Processing

**Symptom:** Status stuck at "pending"

**Solution:**
```bash
# Check if queue worker is running
ps aux | grep "queue:work"

# Restart queue worker
php artisan queue:restart
php artisan queue:work --queue=default --tries=3 --timeout=600 --verbose
```

### Issue 2: OpenAI API Errors

**Symptom:** Embedding status = "failed"

**Solution:**
```bash
# Check API key
php artisan tinker
>>> config('openai.api_key')

# Check API quota
# Visit: https://platform.openai.com/usage

# Check error logs
tail -f storage/logs/laravel.log | grep "OpenAI"
```

### Issue 3: Neo4j Connection Failed

**Symptom:** Graph sync fails, but no error shown

**Solution:**
```bash
# Check Neo4j is running
docker ps | grep neo4j
# or
sudo systemctl status neo4j

# Check connection
php artisan tinker
>>> app(\App\Services\Neo4jService::class)->ping()

# Disable Neo4j if not needed
# .env: NEO4J_SYNC_ENABLED=false
```

### Issue 4: Modal Not Opening

**Symptom:** Clicking buttons does nothing

**Solution:**
- Check browser console for JavaScript errors
- Clear cache: `php artisan view:clear`
- Check Livewire is working: `php artisan livewire:publish --config`
- Verify Alpine.js loaded (used by Livewire)

### Issue 5: Content Not Saving

**Symptom:** No error, but content doesn't change

**Solution:**
```bash
# Check validation rules
php artisan tinker
>>> $job = \App\Models\TextractJob::find(X);
>>> $job->update(['manual_content' => 'test']);

# Check database permissions
# Check model fillable array includes 'manual_content'

# Enable query log
DB::enableQueryLog();
// perform action
dd(DB::getQueryLog());
```

---

## Performance Benchmarks

Expected performance for typical documents:

| Operation | Document Size | Expected Time |
|-----------|--------------|---------------|
| OCR Processing | 10 pages | 2-3 minutes |
| Text Extraction | 10 pages | <1 second |
| Chunking | 10,000 chars | <1 second |
| Embedding Generation | 10 chunks | 5-10 seconds |
| Graph Sync | 10 chunks | 10-20 seconds |
| UI Modal Open | Any | <500ms |
| Content Save | Any | <1 second |

If performance is significantly slower:
- Check queue worker is running
- Check network latency to OpenAI
- Check database indexes
- Monitor server resources

---

## Development Tips

### Viewing Queue Jobs

```bash
# List jobs
php artisan queue:monitor

# Failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry [id]

# Retry all failed
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

### Database Queries

```bash
# Count jobs by status
php artisan tinker
>>> \App\Models\TextractJob::select('status', DB::raw('count(*) as count'))
       ->groupBy('status')->get();

# Find jobs needing sync
>>> \App\Models\TextractJob::where('embedding_status', 'pending')
       ->where('status', 'succeeded')->count();

# Check latest activity
>>> \App\Models\TextractJob::latest('updated_at')->take(5)->get(['id', 'drive_file_name', 'status', 'embedding_status']);
```

### Debugging Livewire

```php
// In TextractManager.php, add:
protected $listeners = ['refresh' => '$refresh'];

// Enable Livewire debugging
// resources/views/livewire/textract-manager.blade.php
<div x-data="{ debug: false }">
    <button @click="debug = !debug">Toggle Debug</button>
    <pre x-show="debug">{{ json_encode($viewingContent, JSON_PRETTY_PRINT) }}</pre>
</div>
```

### Checking Logs

```bash
# Tail application logs
tail -f storage/logs/laravel.log

# Filter for textract
tail -f storage/logs/laravel.log | grep -i textract

# Filter for embeddings
tail -f storage/logs/laravel.log | grep -i embedding

# Filter for queue
tail -f storage/logs/laravel.log | grep -i "Processing:"
```

---

## Next Steps

After completing the quick tests:

1. **Review full testing checklist:** See `TEXTRACT_MANAGER_TESTING.md`
2. **Test edge cases:** Empty content, very large documents, special characters
3. **Performance testing:** Benchmark with real document volumes
4. **Security review:** Check authorization, input sanitization
5. **Documentation:** Update user guides, API docs

---

## Support

If you encounter issues:

1. Check this guide's "Common Issues" section
2. Review logs: `storage/logs/laravel.log`
3. Check queue worker output
4. Run `php artisan tinker` for debugging
5. Review test checklist for similar scenarios

For bugs or feature requests:
- Create GitHub issue with detailed reproduction steps
- Include relevant logs and screenshots
- Tag with `textract-manager` label
