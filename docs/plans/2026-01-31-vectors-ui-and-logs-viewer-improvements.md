# Vectors UI & Logs Viewer Improvements Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Improve the OpenAI Vector Stores Manager UI with pagination, caching, and better layout; enhance the Log Viewer with proper page-based pagination and improved log entry display.

**Architecture:** Two independent improvement areas: (1) `/openai/vectors` - add server-side pagination for stores/files, cache API responses, widen layout to full-width with scrollable columns; (2) `/logs` - replace line-limit with page-based pagination, fix log entry word-breaking/overflow, simplify entry cards for readability.

**Tech Stack:** Laravel Livewire 3, Tailwind CSS, Alpine.js, Laravel Cache

---

## Part A: OpenAI Vector Stores Manager (`/openai/vectors`)

### Task 1: Add pagination properties and caching to OpenAIVectorManager component

**Files:**
- Modify: `app/Http/Livewire/OpenAIVectorManager.php`

**Step 1: Add pagination and caching properties**

Add these properties after the existing properties in `OpenAIVectorManager.php`:

```php
// Pagination for stores
public int $storesPage = 1;
public int $storesPerPage = 10;
public int $storesTotalCount = 0;

// Pagination for files
public int $filesPage = 1;
public int $filesPerPage = 15;
public int $filesTotalCount = 0;

// Cache TTL in seconds
protected int $cacheTtl = 120;
```

**Step 2: Update fetchStores() to use caching and pagination**

Replace `fetchStores()` with:

```php
public function fetchStores()
{
    try {
        $service = app(OpenAIService::class);
        $cacheKey = 'openai_vector_stores_list';

        $allStores = \Illuminate\Support\Facades\Cache::remember($cacheKey, $this->cacheTtl, function () use ($service) {
            return $service->vectorStoreList()['data'] ?? [];
        });

        $this->storesTotalCount = count($allStores);
        $offset = ($this->storesPage - 1) * $this->storesPerPage;
        $this->stores = array_slice($allStores, $offset, $this->storesPerPage);
        $this->error = null;
    } catch (\Exception $e) {
        $this->error = $e->getMessage();
    }
}
```

**Step 3: Update fetchFiles() to use caching and pagination**

Replace `fetchFiles()` with:

```php
public function fetchFiles()
{
    $this->files = [];
    $this->selectedFile = null;
    $this->metadata = [];
    $this->fileAttributes = [];
    $this->filesPage = 1;
    $this->filesTotalCount = 0;

    if (! $this->selectedStore) {
        return;
    }

    try {
        $service = app(OpenAIService::class);
        $cacheKey = "openai_vector_files_{$this->selectedStore}";

        $allFiles = \Illuminate\Support\Facades\Cache::remember($cacheKey, $this->cacheTtl, function () use ($service) {
            return $service->vectorStoreListFiles($this->selectedStore)['data'] ?? [];
        });

        $this->filesTotalCount = count($allFiles);
        $offset = ($this->filesPage - 1) * $this->filesPerPage;
        $this->files = array_slice($allFiles, $offset, $this->filesPerPage);
        $this->error = null;
    } catch (\Exception $e) {
        $this->error = $e->getMessage();
    }
}
```

**Step 4: Add pagination navigation methods**

Add these methods:

```php
public function storesNextPage()
{
    if ($this->storesPage < $this->storesTotalPages()) {
        $this->storesPage++;
        $this->fetchStores();
    }
}

public function storesPreviousPage()
{
    if ($this->storesPage > 1) {
        $this->storesPage--;
        $this->fetchStores();
    }
}

public function storesTotalPages(): int
{
    return max(1, (int) ceil($this->storesTotalCount / $this->storesPerPage));
}

public function filesNextPage()
{
    if ($this->filesPage < $this->filesTotalPages()) {
        $this->filesPage++;
        $this->loadFilesPage();
    }
}

public function filesPreviousPage()
{
    if ($this->filesPage > 1) {
        $this->filesPage--;
        $this->loadFilesPage();
    }
}

public function filesTotalPages(): int
{
    return max(1, (int) ceil($this->filesTotalCount / $this->filesPerPage));
}

protected function loadFilesPage()
{
    if (! $this->selectedStore) {
        return;
    }

    try {
        $service = app(OpenAIService::class);
        $cacheKey = "openai_vector_files_{$this->selectedStore}";

        $allFiles = \Illuminate\Support\Facades\Cache::remember($cacheKey, $this->cacheTtl, function () use ($service) {
            return $service->vectorStoreListFiles($this->selectedStore)['data'] ?? [];
        });

        $this->filesTotalCount = count($allFiles);
        $offset = ($this->filesPage - 1) * $this->filesPerPage;
        $this->files = array_slice($allFiles, $offset, $this->filesPerPage);
        $this->error = null;
    } catch (\Exception $e) {
        $this->error = $e->getMessage();
    }
}

public function refreshStores()
{
    \Illuminate\Support\Facades\Cache::forget('openai_vector_stores_list');
    $this->storesPage = 1;
    $this->fetchStores();
}

public function refreshFiles()
{
    if ($this->selectedStore) {
        \Illuminate\Support\Facades\Cache::forget("openai_vector_files_{$this->selectedStore}");
    }
    $this->filesPage = 1;
    $this->fetchFiles();
}
```

**Step 5: Commit**

```bash
git add app/Http/Livewire/OpenAIVectorManager.php
git commit -m "feat: add pagination, caching, and refresh to OpenAIVectorManager"
```

---

### Task 2: Redesign the vectors Blade view with wider layout, scrollbars, and pagination controls

**Files:**
- Modify: `resources/views/livewire/openai-vector-manager.blade.php`

**Step 1: Replace the entire Blade view**

Replace the content of `openai-vector-manager.blade.php` with a wider, full-screen layout that has:
- `max-w-full` instead of `max-w-6xl` for wider layout
- Scrollable columns with `max-h-[calc(100vh-280px)] overflow-y-auto`
- Pagination controls (Previous/Page X of Y/Next) below each list
- A refresh button for stores and files to bust cache
- Proper `min-h-screen` background wrapper matching the logs viewer style

Key layout changes:
```html
<!-- Outer wrapper: full screen dark background -->
<div class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
  <div class="container mx-auto max-w-full px-6 py-6">
    <!-- Header -->
    ...
    <!-- 3-column grid: stores(3) | files(4) | metadata(5) -->
    <div class="grid grid-cols-12 gap-6">
      <div class="col-span-12 lg:col-span-3">
        <!-- Store list with scrollbar and pagination -->
      </div>
      <div class="col-span-12 lg:col-span-4">
        <!-- Files list with scrollbar and pagination -->
      </div>
      <div class="col-span-12 lg:col-span-5">
        <!-- Metadata panel with scrollbar -->
      </div>
    </div>
  </div>
</div>
```

Pagination control template for each column:
```html
<div class="flex items-center justify-between px-4 py-2 border-t border-slate-700">
    <button wire:click="storesPreviousPage" :disabled="$storesPage <= 1"
        class="px-3 py-1 text-xs rounded bg-slate-700 text-slate-300 hover:bg-slate-600 disabled:opacity-40 disabled:cursor-not-allowed">
        Previous
    </button>
    <span class="text-xs text-slate-400">Page {{ $storesPage }} of {{ $this->storesTotalPages() }}</span>
    <button wire:click="storesNextPage" :disabled="$storesPage >= $this->storesTotalPages()"
        class="px-3 py-1 text-xs rounded bg-slate-700 text-slate-300 hover:bg-slate-600 disabled:opacity-40 disabled:cursor-not-allowed">
        Next
    </button>
</div>
```

**Step 2: Commit**

```bash
git add resources/views/livewire/openai-vector-manager.blade.php
git commit -m "feat: redesign vectors UI with wider layout, scrollbars, and pagination"
```

---

## Part B: Laravel Log Viewer (`/logs`)

### Task 3: Add page-based pagination to LaravelLogViewer component

**Files:**
- Modify: `app/Http/Livewire/LaravelLogViewer.php`
- Modify: `app/Services/LogViewerService.php`

**Step 1: Add a `getPageOfEntries()` method to `LogViewerService`**

Add this method to `LogViewerService.php` that reads a specific page of entries from the end of a file:

```php
/**
 * Count total lines in a log file efficiently
 */
public function countLines(string $filepath): int
{
    if (! File::exists($filepath)) {
        return 0;
    }

    $f = @fopen($filepath, 'rb');
    if ($f === false) {
        return 0;
    }

    $count = 0;
    $chunkSize = 65536;
    while (!feof($f)) {
        $chunk = fread($f, $chunkSize);
        if ($chunk === false) {
            break;
        }
        $count += substr_count($chunk, "\n");
    }
    fclose($f);

    return $count;
}

/**
 * Read a range of lines from a file (1-indexed, from end of file)
 * Page 1 = most recent entries, Page 2 = next older batch, etc.
 */
public function readPage(string $filepath, int $page, int $perPage): array
{
    $totalLines = $this->countLines($filepath);
    if ($totalLines === 0) {
        return ['lines' => [], 'totalLines' => 0, 'totalPages' => 0];
    }

    $totalPages = max(1, (int) ceil($totalLines / $perPage));
    $page = max(1, min($page, $totalPages));

    // Page 1 = last $perPage lines, Page 2 = lines before that, etc.
    $linesFromEnd = $page * $perPage;
    $allLines = $this->tail($filepath, $linesFromEnd);

    // Take only the page slice (from the older end)
    $startIndex = max(0, count($allLines) - ($page * $perPage));
    $endCount = min($perPage, count($allLines) - (($page - 1) * $perPage));
    $offset = max(0, count($allLines) - ($page * $perPage));

    // For page 1: take last $perPage items
    // For page 2: take the $perPage items before that
    $skipFromEnd = ($page - 1) * $perPage;
    $takeEnd = count($allLines) - $skipFromEnd;
    $takeStart = max(0, $takeEnd - $perPage);

    $pageLines = array_slice($allLines, $takeStart, $takeEnd - $takeStart);

    return [
        'lines' => $pageLines,
        'totalLines' => $totalLines,
        'totalPages' => $totalPages,
    ];
}
```

**Step 2: Update LaravelLogViewer component with page-based pagination**

Add properties to `LaravelLogViewer.php`:

```php
public int $currentPage = 1;
public int $perPage = 50;
public int $totalPages = 1;
public int $totalLines = 0;
```

Remove the old `$limit` property and replace `loadEntries()`:

```php
protected function loadEntries(): void
{
    $this->entries = [];
    $this->totalPages = 1;
    $this->totalLines = 0;

    if (! $this->selectedFile || ! isset($this->logFiles[$this->selectedFile])) {
        return;
    }

    $filepath = $this->logFiles[$this->selectedFile]['path'];
    $result = $this->logService->readPage($filepath, $this->currentPage, $this->perPage);

    $this->totalLines = $result['totalLines'];
    $this->totalPages = $result['totalPages'];

    $parsed = [];
    foreach ($result['lines'] as $line) {
        $result2 = $this->logService->parseLine($line);

        if ($result2['type'] === 'empty') {
            continue;
        }

        $level = 'unknown';
        if ($result2['type'] === 'json' && isset($result2['parsed']['level_name'])) {
            $level = strtolower($result2['parsed']['level_name']);
        } elseif ($result2['type'] === 'json' && isset($result2['parsed']['level'])) {
            $level = strtolower($result2['parsed']['level']);
        } elseif ($result2['type'] === 'laravel' && isset($result2['parsed']['level'])) {
            $level = strtolower($result2['parsed']['level']);
        }

        if ($this->levelFilter !== 'all' && $level !== strtolower($this->levelFilter)) {
            continue;
        }

        if ($this->search) {
            if (! str_contains(strtolower($result2['raw']), strtolower($this->search))) {
                continue;
            }
        }

        $raw = $result2['raw'];
        $isTruncated = false;
        if (strlen($raw) > self::MAX_RAW_SIZE) {
            $raw = substr($raw, 0, self::MAX_RAW_SIZE) . "\n... [truncated]";
            $isTruncated = true;
        }

        $essentialParsed = null;
        if ($result2['parsed']) {
            $essentialParsed = [
                'datetime' => $result2['parsed']['datetime'] ?? $result2['parsed']['date'] ?? null,
                'message' => isset($result2['parsed']['message'])
                    ? substr($result2['parsed']['message'], 0, 500)
                    : null,
                'level' => $result2['parsed']['level'] ?? $result2['parsed']['level_name'] ?? null,
                'environment' => $result2['parsed']['environment'] ?? $result2['parsed']['channel'] ?? null,
            ];
        }

        $parsed[] = [
            'raw' => $raw,
            'parsed' => $essentialParsed,
            'type' => $result2['type'],
            'level' => $level,
            'truncated' => $isTruncated,
        ];
    }

    // Show latest first within the page
    $this->entries = array_reverse($parsed);
}
```

Add pagination methods:

```php
public function gotoPage(int $page)
{
    $this->currentPage = max(1, min($page, $this->totalPages));
    $this->loadEntries();
}

public function nextPage()
{
    if ($this->currentPage < $this->totalPages) {
        $this->currentPage++;
        $this->loadEntries();
    }
}

public function previousPage()
{
    if ($this->currentPage > 1) {
        $this->currentPage--;
        $this->loadEntries();
    }
}
```

Update `selectFile` and `updated` to reset page:

```php
public function selectFile(string $filename)
{
    $this->selectedFile = $filename;
    $this->currentPage = 1;
    $this->loadEntries();
}
```

Update `updated()` to include `perPage` and reset page on filter changes:

```php
public function updated($property)
{
    if (in_array($property, ['selectedFile', 'perPage', 'search', 'levelFilter'])) {
        $this->currentPage = 1;
        $this->loadEntries();
    }
}
```

**Step 3: Commit**

```bash
git add app/Http/Livewire/LaravelLogViewer.php app/Services/LogViewerService.php
git commit -m "feat: add page-based pagination to log viewer"
```

---

### Task 4: Redesign log viewer Blade template with pagination controls and improved log entry display

**Files:**
- Modify: `resources/views/livewire/laravel-log-viewer.blade.php`

**Step 1: Replace the lines selector with per-page selector**

Change the "Lines:" dropdown to a "Per Page:" dropdown with values 25, 50, 100, 200 bound to `$perPage`.

**Step 2: Add pagination controls above and below entries**

Add a pagination bar:

```html
<div class="flex items-center justify-between px-4 py-3 border-b border-slate-700">
    <div class="flex items-center gap-2">
        <button wire:click="previousPage" @disabled($currentPage <= 1)
            class="px-3 py-1.5 text-sm rounded bg-slate-700 text-slate-300 hover:bg-slate-600 disabled:opacity-40 disabled:cursor-not-allowed">
            Previous
        </button>

        {{-- Page number buttons --}}
        @php
            $start = max(1, $currentPage - 2);
            $end = min($totalPages, $currentPage + 2);
        @endphp
        @for($p = $start; $p <= $end; $p++)
            <button wire:click="gotoPage({{ $p }})"
                class="px-3 py-1.5 text-sm rounded {{ $p === $currentPage ? 'bg-blue-600 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600' }}">
                {{ $p }}
            </button>
        @endfor

        <button wire:click="nextPage" @disabled($currentPage >= $totalPages)
            class="px-3 py-1.5 text-sm rounded bg-slate-700 text-slate-300 hover:bg-slate-600 disabled:opacity-40 disabled:cursor-not-allowed">
            Next
        </button>
    </div>
    <span class="text-sm text-slate-400">
        Page {{ $currentPage }} of {{ $totalPages }} ({{ number_format($totalLines) }} lines)
    </span>
</div>
```

**Step 3: Fix log entry display to prevent word-breaking**

The current log entries use `whitespace-pre-wrap break-words` which breaks words mid-line and makes log entries hard to read. Change to:

For log message display (Laravel format):
```html
<div class="text-sm text-slate-200 mt-2 overflow-x-auto">
    <pre class="text-sm text-slate-200 whitespace-pre font-mono">{{ $entry['parsed']['message'] }}</pre>
</div>
```

For plain format:
```html
<div class="overflow-x-auto">
    <pre class="text-sm text-slate-300 font-mono whitespace-pre">{{ $entry['raw'] }}</pre>
</div>
```

Key CSS changes:
- Replace `whitespace-pre-wrap break-words` with `whitespace-pre` + `overflow-x-auto` on message containers
- This preserves formatting (stack traces, JSON) with horizontal scroll instead of breaking words
- Add `max-h-64 overflow-y-auto` to long entries for vertical scrolling within cards

**Step 4: Simplify entry card layout for readability**

- Make level badge + datetime + env on a single compact header line
- Message below in a scrollable pre block
- Reduce padding from `p-4` to `p-3` for denser display
- Use monospace font consistently for all log content

**Step 5: Update the entries count footer**

Replace:
```html
Showing {{ count($entries) }} of last {{ $limit }} lines
```
With:
```html
Showing {{ count($entries) }} entries on page {{ $currentPage }} of {{ $totalPages }}
```

**Step 6: Commit**

```bash
git add resources/views/livewire/laravel-log-viewer.blade.php
git commit -m "feat: redesign log viewer with pagination controls and improved entry display"
```

---

### Task 5: Final verification and combined commit

**Step 1: Run existing tests to verify no regressions**

```bash
./scripts/run-focused-tests.sh OpenAIVectorManagerTest
./scripts/run-focused-tests.sh LaravelLogViewerTest
```

**Step 2: Fix any test failures caused by property changes**

Update tests that reference `$limit` to use `$perPage` instead, and add test assertions for new pagination properties.

**Step 3: Commit fixes**

```bash
git add -A
git commit -m "fix: update tests for new pagination properties"
```

**Step 4: Push to branch**

```bash
git push -u origin claude/improve-vectors-ui-performance-DCAnJ
```
