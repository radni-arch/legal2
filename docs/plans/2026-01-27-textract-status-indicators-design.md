# Textract Status Indicators & Flag-Dependent Actions

**Date:** 2026-01-27
**Status:** Approved
**Scope:** Enhance TextractManager with visual status indicators and context-sensitive actions

---

## Overview

Add visual pipeline status badges and flag-dependent action controls to the TextractManager Livewire component. Users will see at-a-glance document processing status and have access to relevant actions based on current state.

---

## Status Badge Component

### Visual Structure

```
[OCR ●] → [Embed ●] → [Graph ○]
 green     yellow      gray
```

Three badges showing pipeline progression with connecting arrows.

### Color Mapping

| Status | Color | CSS Class | Meaning |
|--------|-------|-----------|---------|
| `succeeded`/`synced` | Green | `bg-green-500` | Complete |
| `processing` | Yellow | `bg-yellow-500 animate-pulse` | In progress |
| `pending` | Gray | `bg-gray-400` | Waiting |
| `failed` | Red | `bg-red-500` | Error - needs attention |

### Badge Behavior

- Hover shows tooltip with timestamp (e.g., "Synced at Jan 27, 2026 10:30")
- Failed status shows error message in tooltip
- Pipeline arrows dim (`text-gray-300`) for incomplete stages
- Arrows brighten (`text-gray-600`) between completed stages

---

## Flag-Dependent Actions

### Action Matrix

| Action | Condition | Button State |
|--------|-----------|--------------|
| **Retry OCR** | `status = failed` | Enabled (red outline) |
| **Re-extract** | `status = succeeded` | Enabled |
| **Generate Embeddings** | `status = succeeded` AND `embedding_status != processing` | Enabled |
| **Retry Embeddings** | `embedding_status = failed` | Enabled (red outline) |
| **Sync to Graph** | `embedding_status = synced` AND `graph_sync_status != processing` | Enabled |
| **Retry Graph Sync** | `graph_sync_status = failed` | Enabled (red outline) |
| **View Content** | `status = succeeded` | Always enabled |
| **Edit Content** | `status = succeeded` | Always enabled |
| **View in Graph** | `graph_sync_status = synced` | Enabled (links to Neo4j) |

### UI Presentation

- Dropdown menu with grouped sections: "Processing", "Sync", "View"
- Disabled actions show grayed out with tooltip explaining why
- Failed actions highlighted with red indicator
- Icons prefix each action for quick recognition

---

## Metadata Display

### Compact View (Table Row)

```
📄 Judgment | 🏛️ 3 courts | 📜 12 citations | 👥 4 parties
```

Shown when `status = succeeded` and metadata exists.

### Expanded View (Click to Expand)

- Document type + confidence score
- Jurisdiction classification
- Citation breakdown (statutes, case numbers, ECLI)
- Listed courts and parties
- OCR quality indicator (average confidence %)
- Page/word count

### Quality Indicators

| Condition | Indicator |
|-----------|-----------|
| High confidence (>90%) | Green checkmark |
| Medium confidence (70-90%) | Yellow warning |
| Low confidence (<70%) | Red flag - "Review recommended" |
| Missing metadata | Gray "Not extracted" |

---

## Component Structure

### Files to Create/Modify

```
app/Http/Livewire/
├── TextractManager.php          # Enhance - add status/action logic
└── Components/
    └── TextractStatusBadge.php  # New - reusable status pipeline badge

resources/views/livewire/
├── textract-manager.blade.php   # Enhance - integrate badges/actions
└── components/
    └── textract-status-badge.blade.php  # New - badge template
```

### TextractStatusBadge Component

**Props:**
- `$textractJob` - TextractJob model instance

**Computed Properties:**
- `getOcrStatusColor()` - Color for OCR badge
- `getEmbeddingStatusColor()` - Color for embedding badge
- `getGraphStatusColor()` - Color for graph badge
- `getOcrTooltip()` - Tooltip text for OCR status
- `getEmbeddingTooltip()` - Tooltip for embedding status
- `getGraphTooltip()` - Tooltip for graph status
- `getAvailableActions()` - Array of enabled actions based on state

**Events Emitted:**
- `retryOcr` - Retry failed OCR processing
- `reExtract` - Re-run extraction on succeeded job
- `generateEmbeddings` - Trigger embedding generation
- `retryEmbeddings` - Retry failed embeddings
- `syncToGraph` - Trigger Neo4j sync
- `retryGraphSync` - Retry failed graph sync
- `viewContent` - Open content viewer modal
- `editContent` - Open content editor modal
- `viewInGraph` - Navigate to Neo4j visualization

### TextractManager Enhancements

**New Listeners:**
```php
protected $listeners = [
    'retryOcr',
    'reExtract',
    'generateEmbeddings',
    'retryEmbeddings',
    'syncToGraph',
    'retryGraphSync',
    'refreshJob',
];
```

**New Methods:**
- `retryOcr($jobId)` - Dispatch ProcessTextractJob
- `reExtract($jobId)` - Reset and re-dispatch extraction
- `generateEmbeddings($jobId)` - Dispatch GenerateEmbeddingsJob
- `retryEmbeddings($jobId)` - Reset embedding status and dispatch
- `syncToGraph($jobId)` - Dispatch SyncTextractToGraph
- `retryGraphSync($jobId)` - Reset graph status and dispatch

### Alpine.js Interactions

- `x-data="{ showTooltip: false, showActions: false, showMetadata: false }"`
- Tooltip display on hover (`@mouseenter`, `@mouseleave`)
- Dropdown menu toggle (`@click.away` to close)
- Expandable metadata panel (`x-show`, `x-collapse`)
- Pulse animation via Tailwind `animate-pulse` class

---

## Implementation Notes

### Job Dispatch Pattern

```php
public function retryOcr($jobId)
{
    $job = TextractJob::findOrFail($jobId);
    $job->update(['status' => 'queued']);
    ProcessTextractJob::dispatch($job);
    $this->dispatch('notify', ['message' => 'OCR retry queued']);
}
```

### Status Refresh

After any action, refresh the specific job:
```php
$this->jobs = $this->jobs->map(function ($job) use ($jobId) {
    return $job->id === $jobId ? $job->fresh() : $job;
});
```

### Error Handling

- Wrap dispatches in try/catch
- Show user-friendly error notifications
- Log failures for debugging

---

## Testing Strategy

1. **Unit Tests:** TextractStatusBadge computed properties
2. **Feature Tests:** Action dispatch and job status updates
3. **Browser Tests:** Visual indicators and interaction flows
