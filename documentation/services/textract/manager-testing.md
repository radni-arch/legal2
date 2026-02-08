# TextractManager Testing Checklist

## Overview
This document provides a comprehensive testing checklist for the TextractManager content editing and synchronization features.

## Prerequisites

### Database Setup
1. Run migrations to create new schema:
   ```bash
   php artisan migrate
   ```

2. Verify tables exist:
   - `textract_jobs` has new columns: `extracted_content`, `manual_content`, `manually_edited`, etc.
   - `textract_documents` table exists with all fields

### Configuration
1. Update `.env` with required variables:
   ```env
   TEXTRACT_AUTO_SYNC=true
   OPENAI_API_KEY=your_key_here
   # Optional: Override defaults
   # TEXTRACT_CHUNK_SIZE=1000
   # TEXTRACT_CHUNK_OVERLAP=200
   ```

2. Verify config files loaded:
   ```bash
   php artisan config:cache
   php artisan config:clear
   ```

### Queue Setup
1. Start queue worker:
   ```bash
   php artisan queue:work --queue=default --tries=3 --timeout=600
   ```

2. Monitor queue in separate terminal:
   ```bash
   php artisan queue:monitor
   ```

---

## A. UI Component Testing

### 1. View Modal Tests

**Test 1.1: View Content Modal Opens**
- [ ] Navigate to TextractManager page
- [ ] Find a succeeded job
- [ ] Click "👁️ View Content" button
- [ ] Verify modal opens with correct title
- [ ] Verify Content tab is selected by default

**Test 1.2: Content Tab Display**
- [ ] Verify document statistics show:
  - [ ] Word count
  - [ ] Character count
  - [ ] Line count
  - [ ] Chunk count
- [ ] Verify content displays in monospace font
- [ ] Verify scrolling works for long content
- [ ] Verify no "Edited" banner for unedited content

**Test 1.3: Metadata Tab Display**
- [ ] Click "📊 Metadata" tab
- [ ] Verify JSON metadata displays with proper formatting
- [ ] Verify case information shows if assigned
- [ ] Verify metadata is read-only

**Test 1.4: Sync Status Tab Display**
- [ ] Click "🔄 Sync Status" tab
- [ ] Verify Embedding Status shows with correct icon
- [ ] Verify Graph Sync Status shows with correct icon
- [ ] Verify timestamps display if synced
- [ ] Verify "Regenerate Embeddings" button exists
- [ ] Verify "Sync to Graph" button exists

**Test 1.5: Modal Navigation**
- [ ] Verify clicking outside modal closes it
- [ ] Verify "✕ Close" button closes modal
- [ ] Verify "✏️ Edit Content" button switches to edit modal
- [ ] Verify modal state resets on close

### 2. Edit Modal Tests

**Test 2.1: Edit Content Modal Opens**
- [ ] Click "✏️ Edit Content" button on job card
- [ ] Verify edit modal opens
- [ ] Verify warning banner displays
- [ ] Verify case information shows
- [ ] Verify textarea contains current content

**Test 2.2: Content Editor Functionality**
- [ ] Verify word count updates when typing
- [ ] Verify character count updates when typing
- [ ] Verify textarea is resizable vertically
- [ ] Verify monospace font in textarea
- [ ] Verify placeholder text shows when empty

**Test 2.3: Save Validation**
- [ ] Clear all content in textarea
- [ ] Click "💾 Save Changes"
- [ ] Verify error toast: "Content cannot be empty"
- [ ] Restore content but don't change it
- [ ] Click "💾 Save Changes"
- [ ] Verify info toast: "No changes detected"

**Test 2.4: Successful Save**
- [ ] Make changes to content
- [ ] Click "💾 Save Changes"
- [ ] Verify success toast appears
- [ ] Verify modal closes
- [ ] Verify job list refreshes
- [ ] Verify "✏️ Edited" badge appears on job card

**Test 2.5: Reset to Original**
- [ ] Open edit modal on edited job
- [ ] Click "🔄 Reset to Original" button
- [ ] Verify confirmation dialog appears
- [ ] Click "OK"
- [ ] Verify success toast appears
- [ ] Verify content reverts to original
- [ ] Verify "✏️ Edited" badge disappears

**Test 2.6: Cancel Button**
- [ ] Make changes to content
- [ ] Click "Cancel" button
- [ ] Verify modal closes
- [ ] Verify changes are not saved
- [ ] Re-open modal
- [ ] Verify original content is still there

### 3. Job Card Tests

**Test 3.1: Button Visibility**
- [ ] Find job with status "queued"
- [ ] Verify "View Content" button is NOT visible
- [ ] Verify "Edit Content" button is NOT visible
- [ ] Find job with status "succeeded"
- [ ] Verify "View Content" button IS visible
- [ ] Verify "Edit Content" button IS visible

**Test 3.2: Edited Badge**
- [ ] Edit content on a job
- [ ] Verify "✏️ Edited" badge appears in job header
- [ ] Verify badge has info styling (blue)
- [ ] Hover over badge
- [ ] Verify tooltip shows "Content manually edited"

**Test 3.3: Job Card Integration**
- [ ] Verify new buttons align with existing buttons
- [ ] Verify buttons have consistent styling
- [ ] Verify loading states work with wire:loading
- [ ] Verify buttons are disabled when appropriate

### 4. Job Details Modal Tests

**Test 4.1: Sync Status Section Visibility**
- [ ] Open job details for failed job
- [ ] Verify sync status section does NOT appear
- [ ] Open job details for succeeded job
- [ ] Verify "Synchronization Status" section appears

**Test 4.2: Embedding Status Display**
- [ ] Verify embedding status shows correct icon:
  - [ ] ⏳ for pending
  - [ ] ⚙️ for processing
  - [ ] ✅ for synced
  - [ ] ❌ for failed
- [ ] Verify timestamp shows if synced
- [ ] Verify retry button (🔄) shows for pending/failed

**Test 4.3: Graph Sync Status Display**
- [ ] Verify graph status shows correct icon
- [ ] Verify timestamp shows if synced
- [ ] Verify retry button only shows if embeddings are synced

**Test 4.4: Manual Sync Triggers**
- [ ] Click embedding retry button
- [ ] Verify success toast appears
- [ ] Verify status changes to "pending"
- [ ] Wait for job to process
- [ ] Verify status updates to "synced" or "failed"

---

## B. Backend Logic Testing

### 5. Model Tests

**Test 5.1: TextractJob Model**
- [ ] Create test job with extracted_content
- [ ] Verify `effective_content` returns extracted_content
- [ ] Update with manual_content
- [ ] Verify `effective_content` returns manual_content
- [ ] Call `markAsEdited(userId)`
- [ ] Verify manually_edited = true
- [ ] Verify content_edited_at is set
- [ ] Verify edited_by = userId

**Test 5.2: TextractDocument Model**
- [ ] Create test document with embedding
- [ ] Call `hasEmbedding()`
- [ ] Verify returns true
- [ ] Call `cosineSimilarity([1,2,3,...])`
- [ ] Verify returns float between -1 and 1
- [ ] Test with same vector
- [ ] Verify returns ~1.0

**Test 5.3: Model Observer**
- [ ] Update manual_content on TextractJob
- [ ] Verify observer detects change
- [ ] Wait for queue processing
- [ ] Verify RegenerateTextractEmbeddings job dispatched
- [ ] Verify SyncTextractToGraph job dispatched (if Neo4j enabled)
- [ ] Check logs for auto-dispatch messages

### 6. Service Tests

**Test 6.1: TextractVectorStoreService - Chunking**
- [ ] Create service instance
- [ ] Call `chunkText()` with 5000 char string
- [ ] Verify chunks array returned
- [ ] Verify chunk_size ~1000 chars
- [ ] Verify overlap ~200 chars
- [ ] Verify chunk_index increments
- [ ] Verify chunks preserve sentence boundaries

**Test 6.2: TextractVectorStoreService - Embedding**
- [ ] Call `ingestTextractJob($jobId)`
- [ ] Verify job status changes to "processing"
- [ ] Wait for completion
- [ ] Verify TextractDocuments created
- [ ] Verify each has embedding array
- [ ] Verify embedding_provider = "openai"
- [ ] Verify embedding_model matches config
- [ ] Verify job embedding_status = "synced"

**Test 6.3: TextractVectorStoreService - Search**
- [ ] Create job with embedded content
- [ ] Call `searchSimilar("test query")`
- [ ] Verify returns array of results
- [ ] Verify results have similarity scores
- [ ] Verify results sorted by similarity desc
- [ ] Verify limit parameter works

**Test 6.4: GraphRagService - Sync**
- [ ] Ensure Neo4j is enabled
- [ ] Call `syncTextractJob($jobId)`
- [ ] Verify job graph_sync_status = "processing"
- [ ] Wait for completion
- [ ] Verify TextractDocument nodes created in Neo4j
- [ ] Verify BELONGS_TO relationships created
- [ ] Verify keyword nodes extracted and linked
- [ ] Verify job graph_sync_status = "synced"

### 7. Queue Job Tests

**Test 7.1: RegenerateTextractEmbeddings Job**
- [ ] Dispatch job: `RegenerateTextractEmbeddings::dispatch($jobId)`
- [ ] Monitor queue worker logs
- [ ] Verify job runs without errors
- [ ] Verify TextractDocuments created/updated
- [ ] Verify embedding_status updates throughout
- [ ] Test retry logic by simulating API failure
- [ ] Verify exponential backoff works (2s, 4s, 8s)

**Test 7.2: SyncTextractToGraph Job**
- [ ] Dispatch job: `SyncTextractToGraph::dispatch($jobId)`
- [ ] Monitor queue worker logs
- [ ] Verify job runs without errors
- [ ] Verify graph_sync_status updates
- [ ] Open Neo4j browser
- [ ] Verify nodes created:
  ```cypher
  MATCH (d:TextractDocument) RETURN d LIMIT 10
  ```
- [ ] Verify relationships created:
  ```cypher
  MATCH (d:TextractDocument)-[r]->(n) RETURN d,r,n LIMIT 20
  ```

**Test 7.3: Job Failure Handling**
- [ ] Test with invalid job ID
- [ ] Verify error logged
- [ ] Verify job marked as failed
- [ ] Test with missing content
- [ ] Verify graceful failure
- [ ] Test with API key missing
- [ ] Verify retry logic triggers

---

## C. Integration Tests

### 8. End-to-End Workflow Tests

**Test 8.1: Fresh Document Processing**
- [ ] Upload new PDF to Google Drive
- [ ] Sync from Drive in TextractManager
- [ ] Process job (async)
- [ ] Wait for job to succeed
- [ ] Verify extracted_content populated
- [ ] Verify embedding_status = "pending"
- [ ] Verify graph_sync_status = "pending"
- [ ] Wait for auto-sync to complete
- [ ] Verify embedding_status = "synced"
- [ ] Verify graph_sync_status = "synced"
- [ ] Verify TextractDocuments exist
- [ ] Verify Neo4j nodes exist

**Test 8.2: Content Edit Workflow**
- [ ] Open succeeded job
- [ ] Click "Edit Content"
- [ ] Make significant changes
- [ ] Save changes
- [ ] Verify success toast
- [ ] Verify job refreshes with "Edited" badge
- [ ] Monitor queue
- [ ] Verify RegenerateTextractEmbeddings dispatched
- [ ] Verify old TextractDocuments deleted
- [ ] Verify new TextractDocuments created
- [ ] Verify SyncTextractToGraph dispatched
- [ ] Verify Neo4j updated with new nodes

**Test 8.3: Reset Workflow**
- [ ] Edit content multiple times
- [ ] Reset to original
- [ ] Verify content matches original extracted_content
- [ ] Verify manually_edited = false
- [ ] Verify edit metadata cleared
- [ ] Verify sync jobs triggered
- [ ] Verify embeddings regenerated from original

**Test 8.4: Manual Sync Workflow**
- [ ] Find job with failed embedding
- [ ] Open job details
- [ ] Go to sync status section
- [ ] Click "Regenerate Embeddings"
- [ ] Verify success toast
- [ ] Monitor queue
- [ ] Verify job processes successfully
- [ ] Verify status updates to "synced"

---

## D. Edge Case & Error Tests

### 9. Edge Cases

**Test 9.1: Empty Content**
- [ ] Try to save empty content
- [ ] Verify validation error
- [ ] Verify content not saved

**Test 9.2: Very Large Document**
- [ ] Process document >50 pages
- [ ] Verify chunking handles it
- [ ] Verify embeddings generated for all chunks
- [ ] Verify no timeout errors
- [ ] Verify all chunks synced to graph

**Test 9.3: Special Characters**
- [ ] Edit content with emojis: 📄✏️🔄
- [ ] Edit content with unicode: čćšđžČĆŠĐŽ
- [ ] Edit content with HTML: `<script>alert()</script>`
- [ ] Verify content saves correctly
- [ ] Verify embeddings generated
- [ ] Verify no XSS vulnerabilities

**Test 9.4: Concurrent Edits**
- [ ] Open same job in two browsers
- [ ] Edit in browser 1
- [ ] Save in browser 1
- [ ] Edit different content in browser 2
- [ ] Save in browser 2
- [ ] Verify last save wins
- [ ] Verify no data corruption

**Test 9.5: Network Failures**
- [ ] Disconnect network
- [ ] Try to save content
- [ ] Verify appropriate error message
- [ ] Reconnect network
- [ ] Retry save
- [ ] Verify succeeds

### 10. Error Scenarios

**Test 10.1: Missing OpenAI API Key**
- [ ] Remove OPENAI_API_KEY from .env
- [ ] Try to regenerate embeddings
- [ ] Verify clear error message
- [ ] Verify job marked as failed
- [ ] Verify error logged

**Test 10.2: Neo4j Disabled**
- [ ] Set NEO4J_SYNC_ENABLED=false
- [ ] Edit content
- [ ] Verify embedding sync still works
- [ ] Verify graph sync skipped
- [ ] Verify no errors

**Test 10.3: Queue Not Running**
- [ ] Stop queue worker
- [ ] Edit content
- [ ] Verify job shows embedding_status = "pending"
- [ ] Start queue worker
- [ ] Verify jobs process automatically

**Test 10.4: Malformed JSON in Metadata**
- [ ] If metadata editing is added later
- [ ] Test with invalid JSON
- [ ] Verify validation catches it
- [ ] Verify appropriate error message

---

## E. Performance Tests

### 11. Performance Benchmarks

**Test 11.1: Chunking Performance**
- [ ] Measure time to chunk 10,000 char document
- [ ] Verify completes in <1 second
- [ ] Measure time to chunk 100,000 char document
- [ ] Verify completes in <5 seconds

**Test 11.2: Embedding Generation**
- [ ] Measure time for 10 chunks
- [ ] Verify completes in <30 seconds
- [ ] Measure time for 100 chunks
- [ ] Verify batching works (100 per batch)
- [ ] Verify rate limit handling works

**Test 11.3: Graph Sync Performance**
- [ ] Measure time to sync 50 chunks
- [ ] Verify completes in <2 minutes
- [ ] Measure memory usage
- [ ] Verify no memory leaks

**Test 11.4: UI Responsiveness**
- [ ] Test with 500+ jobs in list
- [ ] Verify pagination works
- [ ] Verify modal loads in <500ms
- [ ] Verify no lag when scrolling

---

## F. Regression Tests

### 12. Existing Functionality

**Test 12.1: Original Job Processing**
- [ ] Process new PDF through Textract
- [ ] Verify all original steps still work:
  - [ ] Download from Drive
  - [ ] Upload to S3
  - [ ] Start Textract analysis
  - [ ] Fetch results
  - [ ] Save JSON
  - [ ] Collect lines
  - [ ] Check OCR quality
  - [ ] Extract metadata
  - [ ] Reconstruct PDF
  - [ ] Upload output
  - [ ] Persist to case

**Test 12.2: Case Document Integration**
- [ ] Verify CaseDocument still created
- [ ] Verify CaseDocumentUpload still created
- [ ] Verify case association works
- [ ] Verify existing vector search still works

**Test 12.3: Re-OCR Functionality**
- [ ] Click "Re-OCR" on job
- [ ] Verify warning confirmation
- [ ] Confirm re-process
- [ ] Verify job marked as superseded
- [ ] Verify new job created
- [ ] Verify case_id preserved

---

## G. Security Tests

### 13. Security Validation

**Test 13.1: Authorization**
- [ ] Verify only authenticated users can access
- [ ] Verify edited_by records correct user ID
- [ ] Test cross-user content access

**Test 13.2: Input Sanitization**
- [ ] Test SQL injection attempts in content
- [ ] Test XSS attempts in content
- [ ] Verify all inputs sanitized
- [ ] Verify no code execution

**Test 13.3: Rate Limiting**
- [ ] Test rapid repeated saves
- [ ] Verify no abuse possible
- [ ] Verify queue doesn't overflow

---

## H. Documentation Tests

### 14. Documentation Validation

**Test 14.1: Code Comments**
- [ ] Verify all services have docblocks
- [ ] Verify all public methods documented
- [ ] Verify parameters explained
- [ ] Verify return types documented

**Test 14.2: Configuration Documentation**
- [ ] Verify all env vars in .env.example
- [ ] Verify config comments explain purpose
- [ ] Verify defaults make sense

**Test 14.3: User Documentation**
- [ ] Test tooltips on all buttons
- [ ] Verify error messages are helpful
- [ ] Verify success messages are clear

---

## Test Results Template

```markdown
## Test Run: [Date]
**Tester:** [Name]
**Environment:** [local/staging/production]
**Branch:** [branch-name]

### Summary
- Tests Passed: X/Y
- Tests Failed: Z
- Tests Skipped: W

### Failed Tests
1. Test X.Y: [Test Name]
   - **Expected:** [...]
   - **Actual:** [...]
   - **Error:** [...]
   - **Screenshot:** [link]

### Notes
- [Any observations]
- [Performance issues]
- [Suggestions]
```

---

## Sign-off

### Development Team
- [ ] All unit tests pass
- [ ] All integration tests pass
- [ ] Code reviewed
- [ ] Documentation complete

### QA Team
- [ ] All manual tests pass
- [ ] Edge cases verified
- [ ] Performance acceptable
- [ ] Security validated

### Product Owner
- [ ] Requirements met
- [ ] UI/UX acceptable
- [ ] Ready for production
