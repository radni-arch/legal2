# TextractManager Enhancement - Implementation Tasks

> **Goal**: Enable manual editing of OCR content with automatic embedding & graph sync

---

## 📋 High-Level Tasks

### **Task 1: Database Schema** (3-4 hours)
**Create migrations for content storage and sync tracking**

```bash
php artisan make:migration add_content_editing_to_textract_jobs
php artisan make:migration create_textract_documents_table
```

**Fields to add to `textract_jobs`**:
- `extracted_content` (longText) - Raw OCR text
- `manual_content` (longText) - User-edited text
- `manually_edited` (boolean) - Edit flag
- `content_edited_at`, `edited_by` - Audit trail
- `embedding_status`, `graph_sync_status` - Sync tracking
- `embedding_synced_at`, `graph_synced_at` - Timestamps

**New table `textract_documents`**:
- Stores chunked content with embeddings
- Links to `textract_job_id` and `case_id`
- Vector column (pgvector or JSON)
- Metadata: provider, model, dimensions, token_count

**Models**:
- Update `TextractJob` model with new fields, casts, and observers
- Create `TextractDocument` model

---

### **Task 2: Vector & Graph Services** (6-8 hours)
**Create services for embedding generation and graph synchronization**

**A. Create `TextractVectorStoreService`**
- `ingestTextractJob()` - Main entry point
- `chunkText()` - Split content into ~1000 char chunks
- `generateEmbedding()` - Call OpenAI with retry logic
- Delete old chunks, create new `TextractDocument` records

**B. Enhance `GraphRagService`**
- Add `syncTextractJob()` method
- Create TextractDocument node in Neo4j
- Extract keywords → create Keyword nodes
- Link to CaseDocument if assigned
- Find similar documents → create SIMILAR_TO relationships

**C. Create Queue Jobs**
- `RegenerateTextractEmbeddings` - Async embedding job
- `SyncTextractToGraph` - Async graph sync job
- Both with 3 retries, status updates, error logging

---

### **Task 3: Livewire Component Logic** (4-6 hours)
**Add content management methods to `TextractManager.php`**

**New Properties**:
```php
public bool $showContentViewModal = false;
public ?array $viewingContent = null;
public bool $showContentEditModal = false;
public array $editingContent = [];
public string $contentTab = 'content';
```

**New Methods**:
- `viewContent($jobId)` - Load job data, show preview modal
- `editContent($jobId)` - Load job data, show edit modal
- `saveContent()` - Validate & update content, trigger syncs
- `resetToOriginal($jobId)` - Discard edits, restore OCR
- `regenerateEmbeddings($jobId)` - Manual sync trigger
- `syncToGraph($jobId)` - Manual graph sync trigger

**Model Observer** (in `TextractJob::booted()`):
- Detect content changes with `isDirty(['manual_content', 'extracted_content'])`
- Dispatch `RegenerateTextractEmbeddings` job
- Dispatch `SyncTextractToGraph` job

---

### **Task 4: UI Components** (6-8 hours)
**Create modals and update views**

**A. Content View Modal** (Read-Only)
- Three tabs: Content | Metadata | OCR Quality
- Show document stats (words, chars, lines)
- Display active content (manual or extracted)
- Show "Edited by X at Y" if manually edited
- Metadata table with JSON formatting
- OCR quality metrics visualization
- "Edit Content" button

**B. Content Edit Modal**
- Warning banner about triggering syncs
- Case selection dropdown (from `$caseOptions`)
- Large textarea for content (20 rows, monospace)
- Word count display
- Metadata JSON textarea with validation
- "Reset to Original" button (with confirmation)
- Save/Cancel buttons

**C. Job Card Updates**
- Add "👁️ View" button
- Add "✏️ Edit" button (disabled if no content)
- Show "✏️ Edited" badge on manually edited jobs

**D. Job Details Updates**
- Add sync status section with:
  - Embedding status (pending/processing/completed/failed)
  - Graph sync status with timestamps
  - "Sync Now" / "Retry" buttons

---

### **Task 5: Integration & Testing** (4-6 hours)
**Wire everything together and test**

**A. Configuration**
- Create `config/embeddings.php` with auto_sync, model, chunk settings
- Update `.env.example` with new vars

**B. Update OCR Pipeline**
- Modify `SaveResultsStep` to populate `extracted_content`
- Set initial `embedding_status = 'pending'`

**C. Testing Checklist**
- [ ] View modal displays content correctly
- [ ] Edit modal loads with current data
- [ ] Content saves successfully
- [ ] Validation works (required content, valid JSON metadata)
- [ ] Model observer triggers both sync jobs
- [ ] Queue jobs execute and update status
- [ ] Embeddings stored in `textract_documents`
- [ ] Graph nodes/relationships created
- [ ] Manual sync buttons work
- [ ] Reset to original works
- [ ] Status indicators update correctly
- [ ] Toast notifications appear

**D. Manual Testing**
- Test with real OCR document
- Edit content with errors
- Verify embeddings regenerate
- Check Neo4j graph browser for nodes
- Test edge cases (empty content, invalid JSON, very large docs)

---

## 🎯 Quick Implementation Guide

### **Step-by-Step**

1. **Database**
   ```bash
   # Create migrations
   php artisan make:migration add_content_editing_to_textract_jobs
   php artisan make:migration create_textract_documents_table

   # Run migrations
   php artisan migrate
   ```

2. **Models**
   - Add fields to `TextractJob::$fillable` and `$casts`
   - Add `getActiveContentAttribute()` accessor
   - Add model observer in `booted()` method
   - Create `TextractDocument` model

3. **Services**
   ```bash
   # Create new service
   touch app/Services/TextractVectorStoreService.php

   # Create jobs
   php artisan make:job RegenerateTextractEmbeddings
   php artisan make:job SyncTextractToGraph
   ```

   - Implement `TextractVectorStoreService` with chunking & OpenAI
   - Add `syncTextractJob()` to `GraphRagService`
   - Implement both queue jobs with error handling

4. **Component**
   - Add properties to `TextractManager`
   - Implement 6 new methods
   - Add validation rules

5. **Views**
   - Add Content View Modal to `textract-manager.blade.php`
   - Add Content Edit Modal
   - Add action buttons to job cards
   - Add sync status to job details

6. **Config**
   ```bash
   # Create config
   touch config/embeddings.php

   # Update .env
   echo "EMBEDDINGS_AUTO_SYNC=true" >> .env
   echo "EMBEDDING_MODEL=text-embedding-3-small" >> .env
   ```

7. **Test**
   - Create test job with OCR content
   - View content
   - Edit content
   - Verify jobs dispatched
   - Check database for embeddings
   - Check Neo4j for graph nodes

---

## ⚡ Critical Code Snippets

### Model Observer (TextractJob.php)
```php
protected static function booted(): void
{
    static::updated(function (TextractJob $job) {
        if ($job->isDirty(['manual_content', 'extracted_content'])) {
            if (config('embeddings.auto_sync', true)) {
                dispatch(new RegenerateTextractEmbeddings($job->id));
            }
            if (config('neo4j.sync.auto_sync', true)) {
                dispatch(new SyncTextractToGraph($job->id));
            }
        }
    });
}
```

### Save Content (TextractManager.php)
```php
public function saveContent(): void
{
    // Convert JSON string to array
    if (isset($this->editingContent['metadata']) && is_string($this->editingContent['metadata'])) {
        $this->editingContent['metadata'] = json_decode($this->editingContent['metadata'], true);
    }

    $this->validate([
        'editingContent.manual_content' => ['required', 'string'],
        'editingContent.metadata' => ['nullable', 'array'],
    ]);

    $job = TextractJob::findOrFail($this->editingContent['id']);
    $job->update([
        'manual_content' => $this->editingContent['manual_content'],
        'manually_edited' => true,
        'content_edited_at' => now(),
        'edited_by' => auth()->user()->name ?? 'system',
        'metadata' => $this->editingContent['metadata'],
    ]);

    // Observer triggers jobs automatically

    $this->showContentEditModal = false;
    $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Content updated']);
}
```

---

## 📊 Effort Summary

| Task | Time | Priority |
|------|------|----------|
| 1. Database Schema | 3-4 hours | 🔴 High |
| 2. Vector & Graph Services | 6-8 hours | 🔴 High |
| 3. Livewire Component | 4-6 hours | 🟡 Medium |
| 4. UI Components | 6-8 hours | 🟡 Medium |
| 5. Integration & Testing | 4-6 hours | 🟡 Medium |
| **Total** | **23-32 hours** | |

---

## ✅ Definition of Done

- ✅ User can view OCR content in read-only modal
- ✅ User can edit content and metadata
- ✅ Edits trigger automatic embedding regeneration
- ✅ Edits trigger automatic graph synchronization
- ✅ Sync status visible in UI with timestamps
- ✅ Manual sync buttons work for failed/pending states
- ✅ "Reset to Original" discards manual edits
- ✅ All tests pass
- ✅ No console errors
- ✅ Similar UX to LawManager component

---

## 🔗 References

- **Full Plan**: `/docs/TEXTRACT_MANAGER_IMPLEMENTATION_PLAN.md`
- **Pattern Source**: `app/Http/Livewire/IngestedLawsManager.php`
- **Vector Service Pattern**: `app/Services/LawVectorStoreService.php`
- **Graph Service Pattern**: `app/Services/GraphRagService.php`
