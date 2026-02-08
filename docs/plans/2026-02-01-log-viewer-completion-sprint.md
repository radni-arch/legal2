# Laravel Log Viewer - Sprint Plan

## Overview

This document outlines the implementation plan for improving the Laravel Log Viewer Livewire component located at `/logs` route. The plan is structured into 4 sprints, each addressing a specific issue.

---

## Sprint 1: Pagination Loading Spinner

### Problem
When navigating pages using pagination buttons, there's no loading indicator. File switching has a loading spinner, but pagination doesn't.

### Solution
Add `wire:loading` states to pagination actions similar to file selection.

### Tasks

#### Task 1.1: Add loading state to pagination buttons
```blade
{{-- Wrap pagination in a container with loading overlay --}}
<div wire:loading.class="opacity-50 pointer-events-none" 
     wire:target="gotoPage, nextPage, previousPage">
    {{-- Pagination buttons --}}
</div>

{{-- Add spinner near pagination --}}
<div wire:loading wire:target="gotoPage, nextPage, previousPage" 
     class="inline-flex items-center">
    <svg class="animate-spin h-5 w-5 text-blue-500" ...>
    </svg>
</div>
```

#### Task 1.2: Add loading overlay to entries table
```blade
<div class="relative">
    {{-- Loading overlay --}}
    <div wire:loading 
         wire:target="gotoPage, nextPage, previousPage, selectFile, refreshNow, perPage, levelFilter"
         class="absolute inset-0 bg-gray-900/50 flex items-center justify-center z-10">
        <svg class="animate-spin h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>
    
    {{-- Entries table --}}
    <div class="...">
        @foreach($entries as $entry)
            ...
        @endforeach
    </div>
</div>
```

#### Task 1.3: Ensure all pagination-related methods trigger loading
Methods to target: `gotoPage`, `nextPage`, `previousPage`, `perPage` (when changed)

### Acceptance Criteria
- [ ] Spinner appears when clicking Previous/Next
- [ ] Spinner appears when clicking page numbers
- [ ] Spinner appears when changing "Per Page" dropdown
- [ ] Entries table shows loading overlay during pagination
- [ ] All log files exhibit this behavior consistently

---

## Sprint 2: Expandable Log Entry Cards

### Problem
Current visual effects are complex. Entries should be expandable on click, expanding vertically without horizontal overflow.

### Solution
Implement collapsible card pattern with Alpine.js for state management.

### Tasks

#### Task 2.1: Create simplified entry card structure
```blade
@foreach($entries as $index => $entry)
<div x-data="{ expanded: false }" 
     class="border-b border-gray-700 hover:bg-gray-800/50 transition-colors">
    
    {{-- Collapsed Header (always visible) --}}
    <div @click="expanded = !expanded" 
         class="flex items-center gap-3 p-3 cursor-pointer select-none">
        
        {{-- Expand/Collapse Icon --}}
        <svg :class="{ 'rotate-90': expanded }" 
             class="w-4 h-4 text-gray-400 transition-transform flex-shrink-0" 
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        
        {{-- Level Badge --}}
        <span class="px-2 py-0.5 text-xs font-semibold rounded flex-shrink-0
            @switch($entry['level'])
                @case('error') bg-red-600 text-white @break
                @case('warning') bg-yellow-600 text-white @break
                @case('info') bg-blue-600 text-white @break
                @case('debug') bg-gray-600 text-white @break
                @default bg-gray-500 text-white
            @endswitch">
            {{ strtoupper($entry['level']) }}
        </span>
        
        {{-- Timestamp --}}
        @if($entry['parsed']['datetime'] ?? null)
        <span class="text-xs text-gray-400 flex-shrink-0 font-mono">
            {{ $entry['parsed']['datetime'] }}
        </span>
        @endif
        
        {{-- Environment --}}
        @if($entry['parsed']['environment'] ?? null)
        <span class="px-1.5 py-0.5 text-xs bg-gray-700 rounded flex-shrink-0">
            {{ $entry['parsed']['environment'] }}
        </span>
        @endif
        
        {{-- Message Preview (truncated) --}}
        <span class="text-sm text-gray-300 truncate min-w-0">
            {{ Str::limit($entry['parsed']['message'] ?? $entry['raw'], 100) }}
        </span>
    </div>
    
    {{-- Expanded Content --}}
    <div x-show="expanded" 
         x-collapse
         class="px-3 pb-3 pt-0">
        
        {{-- Full Message --}}
        <div class="bg-gray-900 rounded p-3 mb-2 overflow-x-auto">
            <pre class="text-sm text-gray-200 whitespace-pre-wrap break-words font-mono">{{ $entry['parsed']['message'] ?? $entry['raw'] }}</pre>
        </div>
        
        {{-- Stack Trace (if present) --}}
        @if($entry['stack_trace'])
        <details class="mt-2">
            <summary class="text-xs text-gray-400 cursor-pointer hover:text-gray-300">
                Stack Trace
            </summary>
            <div class="bg-gray-950 rounded p-3 mt-1 overflow-x-auto max-h-64 overflow-y-auto">
                <pre class="text-xs text-red-300 whitespace-pre-wrap break-words font-mono">{{ $entry['stack_trace'] }}</pre>
            </div>
        </details>
        @endif
        
        {{-- Raw Entry (collapsible) --}}
        @if($entry['truncated'] || $entry['raw'] !== ($entry['parsed']['message'] ?? ''))
        <details class="mt-2">
            <summary class="text-xs text-gray-400 cursor-pointer hover:text-gray-300">
                Raw Entry @if($entry['truncated'])(truncated)@endif
            </summary>
            <div class="bg-gray-950 rounded p-3 mt-1 overflow-x-auto max-h-48 overflow-y-auto">
                <pre class="text-xs text-gray-400 whitespace-pre-wrap break-words font-mono">{{ $entry['raw'] }}</pre>
            </div>
        </details>
        @endif
    </div>
</div>
@endforeach
```

#### Task 2.2: Add CSS for vertical-only expansion
```css
/* Prevent horizontal overflow on expanded cards */
.log-entry-expanded {
    max-width: 100%;
    overflow-x: hidden;
}

.log-entry-expanded pre {
    white-space: pre-wrap;
    word-break: break-word;
    overflow-wrap: break-word;
}
```

#### Task 2.3: Optional - Add "Expand All" / "Collapse All" buttons
```blade
<div class="flex gap-2 mb-4">
    <button @click="$dispatch('expand-all')" class="text-xs text-gray-400 hover:text-white">
        Expand All
    </button>
    <button @click="$dispatch('collapse-all')" class="text-xs text-gray-400 hover:text-white">
        Collapse All
    </button>
</div>
```

### Acceptance Criteria
- [ ] Each entry has a clickable header that expands/collapses
- [ ] Expanded content shows full message, stack trace, raw entry
- [ ] No horizontal overflow when expanded
- [ ] Smooth animation on expand/collapse
- [ ] Stack traces are in a scrollable container with max-height

---

## Sprint 3: Robust Log Entry Parsing

### Problem
Log entries starting with `[2025-10-04 22:04:13] local.ERROR:` should be parsed as single entries. Multi-line entries (with stack traces) should be grouped correctly.

### Current Behavior Analysis
- `browser.log` and `laravel.log`: Multi-line entries starting with `[YYYY-MM-DD HH:MM:SS] env.LEVEL:`
- `openai.log` and others: Single line per entry (JSON or plain text)

### Solution
Implement file-type-aware parsing with proper multi-line grouping for Laravel-format logs.

### Tasks

#### Task 3.1: Add file type detection to LogViewerService
```php
// app/Services/LogViewerService.php

/**
 * Determine the log format based on filename
 */
public function detectLogFormat(string $filename): string
{
    $multiLineFormats = ['laravel.log', 'browser.log'];
    
    foreach ($multiLineFormats as $pattern) {
        if (str_contains($filename, $pattern) || fnmatch($pattern, $filename)) {
            return 'laravel';  // Multi-line format
        }
    }
    
    return 'single-line';  // One entry per line (JSON, plain)
}

/**
 * Check if a line is a log entry start (Laravel format)
 */
public function isLogEntryStart(string $line): bool
{
    // Match: [YYYY-MM-DD HH:MM:SS] environment.LEVEL:
    return (bool) preg_match('/^\[\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\]\s+\w+\.\w+:/i', $line);
}
```

#### Task 3.2: Modify readPage() to handle multi-line entries
```php
/**
 * Read a page of entries (not lines) for multi-line log formats
 */
public function readPageMultiLine(string $filepath, int $page, int $perPage): array
{
    $content = File::get($filepath);
    
    // Split by log entry pattern, keeping the delimiter
    $pattern = '/(?=\[\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\]\s+\w+\.\w+:)/';
    $entries = preg_split($pattern, $content, -1, PREG_SPLIT_NO_EMPTY);
    
    $totalEntries = count($entries);
    if ($totalEntries === 0) {
        return ['entries' => [], 'totalLines' => 0, 'totalPages' => 0];
    }
    
    $totalPages = max(1, (int) ceil($totalEntries / $perPage));
    $page = max(1, min($page, $totalPages));
    
    // Reverse for most recent first
    $entries = array_reverse($entries);
    
    $offset = ($page - 1) * $perPage;
    $pageEntries = array_slice($entries, $offset, $perPage);
    
    return [
        'entries' => $pageEntries,
        'totalLines' => $totalEntries,
        'totalPages' => $totalPages,
    ];
}
```

#### Task 3.3: Update the Livewire component to use format-aware parsing
```php
// app/Http/Livewire/LaravelLogViewer.php

protected function loadEntries(): void
{
    $this->entries = [];
    $this->totalPages = 1;
    $this->totalLines = 0;

    if (!$this->selectedFile || !isset($this->logFiles[$this->selectedFile])) {
        return;
    }

    $filepath = $this->logFiles[$this->selectedFile]['path'];
    $format = $this->logService->detectLogFormat($this->selectedFile);
    
    if ($format === 'laravel') {
        // Multi-line format: entries are pre-grouped
        $pageResult = $this->logService->readPageMultiLine($filepath, $this->currentPage, $this->perPage);
        $rawEntries = $pageResult['entries'];
        
        $this->totalLines = $pageResult['totalLines'];
        $this->totalPages = $pageResult['totalPages'];
        
        $parsed = [];
        foreach ($rawEntries as $rawEntry) {
            $lines = explode("\n", trim($rawEntry));
            $firstLine = $lines[0] ?? '';
            $result = $this->logService->parseLine($firstLine);
            
            // Everything after the first line is stack trace
            $stackTrace = count($lines) > 1 
                ? implode("\n", array_slice($lines, 1)) 
                : null;
            
            $level = $result['parsed']['level'] ?? 'unknown';
            $level = strtolower($level);
            
            // Apply filters
            if ($this->levelFilter !== 'all' && $level !== strtolower($this->levelFilter)) {
                continue;
            }
            
            if ($this->search && !str_contains(strtolower($rawEntry), strtolower($this->search))) {
                continue;
            }
            
            // Truncate if needed
            $raw = $rawEntry;
            $isTruncated = false;
            if (strlen($raw) > self::MAX_RAW_SIZE) {
                $raw = substr($raw, 0, self::MAX_RAW_SIZE) . "\n... [truncated]";
                $isTruncated = true;
            }
            
            if ($stackTrace && strlen($stackTrace) > self::MAX_RAW_SIZE) {
                $stackTrace = substr($stackTrace, 0, self::MAX_RAW_SIZE) . "\n... [truncated]";
            }
            
            $parsed[] = [
                'raw' => $raw,
                'parsed' => $result['parsed'] ? [
                    'datetime' => $result['parsed']['datetime'] ?? $result['parsed']['date'] ?? null,
                    'message' => isset($result['parsed']['message']) 
                        ? substr($result['parsed']['message'], 0, 500) 
                        : null,
                    'level' => $result['parsed']['level'] ?? null,
                    'environment' => $result['parsed']['environment'] ?? $result['parsed']['channel'] ?? null,
                ] : null,
                'type' => $result['type'],
                'level' => $level,
                'truncated' => $isTruncated,
                'stack_trace' => $stackTrace,
            ];
        }
        
        $this->entries = $parsed;
    } else {
        // Single-line format: existing logic
        $pageResult = $this->logService->readPage($filepath, $this->currentPage, $this->perPage);
        // ... existing single-line parsing logic ...
    }
}
```

#### Task 3.4: Update parseLine() regex to be more robust
```php
public function parseLine(string $line): array
{
    $line = trim($line);

    if (empty($line)) {
        return ['raw' => $line, 'parsed' => null, 'type' => 'empty'];
    }

    // Try JSON format first
    if (str_starts_with($line, '{')) {
        $data = json_decode($line, true);
        if (is_array($data)) {
            return ['raw' => $line, 'parsed' => $data, 'type' => 'json'];
        }
    }

    // Laravel log format: [YYYY-MM-DD HH:MM:SS] environment.LEVEL: message
    // More robust regex that handles various formats
    $laravelPattern = '/^\[(?P<date>\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\]\s+(?P<env>\w+)\.(?P<level>DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY):\s*(?P<message>.*)/is';
    
    if (preg_match($laravelPattern, $line, $matches)) {
        return [
            'raw' => $line,
            'parsed' => [
                'datetime' => $matches['date'],
                'environment' => $matches['env'],
                'level' => strtoupper($matches['level']),
                'message' => $matches['message'],
            ],
            'type' => 'laravel',
        ];
    }

    // Unknown format
    return ['raw' => $line, 'parsed' => null, 'type' => 'plain'];
}
```

### Acceptance Criteria
- [ ] `browser.log` entries are properly grouped (entry + stack trace as one unit)
- [ ] `laravel.log` entries are properly grouped
- [ ] `openai.log` treats each line as a separate entry
- [ ] Other log files default to single-line-per-entry
- [ ] Pagination counts entries correctly (not raw lines for multi-line files)
- [ ] Stack traces are properly separated from main message

---

## Sprint 4: Server-Side Level Filtering with grep

### Problem
Level filtering currently operates on already-fetched content. Need to filter at the file level using efficient methods like `grep`.

### Solution
Implement server-side filtering using shell commands for efficiency on large files.

### Tasks

#### Task 4.1: Add grep-based filtering method to LogViewerService
```php
/**
 * Filter log file by level using grep for efficiency
 * Returns entries with context lines for stack traces
 *
 * @param string $filepath Full path to log file
 * @param string $level Level to filter (ERROR, WARNING, DEBUG, INFO, etc.)
 * @param int $page Current page number
 * @param int $perPage Entries per page
 * @return array{entries: array, totalLines: int, totalPages: int}
 */
public function filterByLevelWithGrep(string $filepath, string $level, int $page, int $perPage): array
{
    if (!File::exists($filepath)) {
        return ['entries' => [], 'totalLines' => 0, 'totalPages' => 0];
    }
    
    $level = strtoupper($level);
    $safeFilepath = escapeshellarg($filepath);
    $safeLevel = escapeshellarg(".$level:");
    
    // For multi-line logs, we need context lines for stack traces
    // Using grep with -A (after) to capture stack traces
    // First, count total matches
    $countCmd = "grep -c {$safeLevel} {$safeFilepath} 2>/dev/null || echo 0";
    $totalMatches = (int) trim(shell_exec($countCmd));
    
    if ($totalMatches === 0) {
        return ['entries' => [], 'totalLines' => 0, 'totalPages' => 0];
    }
    
    $totalPages = max(1, (int) ceil($totalMatches / $perPage));
    $page = max(1, min($page, $totalPages));
    
    // Extract matching entries with context
    // Using grep -n for line numbers, then processing
    $grepCmd = "grep -n {$safeLevel} {$safeFilepath} 2>/dev/null";
    $matchingLines = shell_exec($grepCmd);
    
    if (empty($matchingLines)) {
        return ['entries' => [], 'totalLines' => 0, 'totalPages' => 0];
    }
    
    // Parse line numbers from grep output
    $lineNumbers = [];
    foreach (explode("\n", trim($matchingLines)) as $match) {
        if (preg_match('/^(\d+):/', $match, $m)) {
            $lineNumbers[] = (int) $m[1];
        }
    }
    
    // Reverse for most recent first
    $lineNumbers = array_reverse($lineNumbers);
    
    // Get the subset for this page
    $offset = ($page - 1) * $perPage;
    $pageLineNumbers = array_slice($lineNumbers, $offset, $perPage);
    
    // Now extract each entry with its stack trace
    $entries = [];
    $fileLines = file($filepath, FILE_IGNORE_NEW_LINES);
    $totalFileLines = count($fileLines);
    
    foreach ($pageLineNumbers as $startLine) {
        $lineIndex = $startLine - 1; // Convert to 0-based
        $entryLines = [$fileLines[$lineIndex] ?? ''];
        
        // Collect continuation lines (stack trace)
        for ($i = $lineIndex + 1; $i < $totalFileLines; $i++) {
            $line = $fileLines[$i];
            // Stop if we hit another log entry start
            if ($this->isLogEntryStart($line)) {
                break;
            }
            // Stop if we've collected too many lines (safety limit)
            if (count($entryLines) > 100) {
                $entryLines[] = '... [truncated]';
                break;
            }
            $entryLines[] = $line;
        }
        
        $entries[] = implode("\n", $entryLines);
    }
    
    return [
        'entries' => $entries,
        'totalLines' => $totalMatches,
        'totalPages' => $totalPages,
    ];
}
```

#### Task 4.2: Add search filtering with grep
```php
/**
 * Search log file content using grep
 *
 * @param string $filepath Full path to log file
 * @param string $search Search term
 * @param int $page Current page
 * @param int $perPage Entries per page
 * @return array{entries: array, totalLines: int, totalPages: int}
 */
public function searchWithGrep(string $filepath, string $search, int $page, int $perPage): array
{
    if (!File::exists($filepath) || empty($search)) {
        return ['entries' => [], 'totalLines' => 0, 'totalPages' => 0];
    }
    
    $safeFilepath = escapeshellarg($filepath);
    $safeSearch = escapeshellarg($search);
    
    // Case-insensitive search with grep -i
    $countCmd = "grep -ci {$safeSearch} {$safeFilepath} 2>/dev/null || echo 0";
    $totalMatches = (int) trim(shell_exec($countCmd));
    
    if ($totalMatches === 0) {
        return ['entries' => [], 'totalLines' => 0, 'totalPages' => 0];
    }
    
    $totalPages = max(1, (int) ceil($totalMatches / $perPage));
    $page = max(1, min($page, $totalPages));
    
    // Get matching lines with context
    // -B 2 = 2 lines before, -A 5 = 5 lines after (for stack traces)
    $contextBefore = 2;
    $contextAfter = 10;
    
    $grepCmd = "grep -in -B {$contextBefore} -A {$contextAfter} {$safeSearch} {$safeFilepath} 2>/dev/null";
    $output = shell_exec($grepCmd);
    
    // Parse the grouped output (groups separated by --)
    $groups = preg_split('/^--$/m', $output, -1, PREG_SPLIT_NO_EMPTY);
    $groups = array_reverse($groups); // Most recent first
    
    $offset = ($page - 1) * $perPage;
    $pageGroups = array_slice($groups, $offset, $perPage);
    
    $entries = array_map('trim', $pageGroups);
    
    return [
        'entries' => $entries,
        'totalLines' => $totalMatches,
        'totalPages' => $totalPages,
    ];
}
```

#### Task 4.3: Update Livewire component to use grep-based filtering
```php
// In LaravelLogViewer.php

protected function loadEntries(): void
{
    $this->entries = [];
    $this->totalPages = 1;
    $this->totalLines = 0;

    if (!$this->selectedFile || !isset($this->logFiles[$this->selectedFile])) {
        return;
    }

    $filepath = $this->logFiles[$this->selectedFile]['path'];
    $format = $this->logService->detectLogFormat($this->selectedFile);
    
    // Determine if we need grep-based filtering
    $needsGrepFilter = $this->levelFilter !== 'all' || !empty($this->search);
    
    if ($needsGrepFilter && $format === 'laravel') {
        // Use grep for efficient server-side filtering
        if ($this->levelFilter !== 'all' && empty($this->search)) {
            $pageResult = $this->logService->filterByLevelWithGrep(
                $filepath, 
                $this->levelFilter, 
                $this->currentPage, 
                $this->perPage
            );
        } elseif (!empty($this->search) && $this->levelFilter === 'all') {
            $pageResult = $this->logService->searchWithGrep(
                $filepath, 
                $this->search, 
                $this->currentPage, 
                $this->perPage
            );
        } else {
            // Combined filter: level + search
            $pageResult = $this->logService->filterByLevelAndSearchWithGrep(
                $filepath,
                $this->levelFilter,
                $this->search,
                $this->currentPage,
                $this->perPage
            );
        }
        
        $this->totalLines = $pageResult['totalLines'];
        $this->totalPages = $pageResult['totalPages'];
        
        // Parse the grep results
        $this->entries = $this->parseGrepResults($pageResult['entries']);
    } elseif ($format === 'laravel') {
        // No filter, use multi-line reading
        $this->loadEntriesMultiLine($filepath);
    } else {
        // Single-line format
        $this->loadEntriesSingleLine($filepath);
    }
}

/**
 * Parse grep results into structured entries
 */
protected function parseGrepResults(array $rawEntries): array
{
    $parsed = [];
    
    foreach ($rawEntries as $rawEntry) {
        $lines = explode("\n", trim($rawEntry));
        
        // Find the main log line (starts with timestamp)
        $mainLineIndex = 0;
        foreach ($lines as $i => $line) {
            if ($this->logService->isLogEntryStart($line)) {
                $mainLineIndex = $i;
                break;
            }
        }
        
        $mainLine = $lines[$mainLineIndex] ?? $lines[0];
        $result = $this->logService->parseLine($mainLine);
        
        $stackTrace = array_slice($lines, $mainLineIndex + 1);
        $stackTrace = !empty($stackTrace) ? implode("\n", $stackTrace) : null;
        
        $level = strtolower($result['parsed']['level'] ?? 'unknown');
        
        // Truncate if needed
        $raw = $rawEntry;
        $isTruncated = false;
        if (strlen($raw) > self::MAX_RAW_SIZE) {
            $raw = substr($raw, 0, self::MAX_RAW_SIZE) . "\n... [truncated]";
            $isTruncated = true;
        }
        
        if ($stackTrace && strlen($stackTrace) > self::MAX_RAW_SIZE) {
            $stackTrace = substr($stackTrace, 0, self::MAX_RAW_SIZE) . "\n... [truncated]";
        }
        
        $parsed[] = [
            'raw' => $raw,
            'parsed' => $result['parsed'] ? [
                'datetime' => $result['parsed']['datetime'] ?? null,
                'message' => isset($result['parsed']['message']) 
                    ? substr($result['parsed']['message'], 0, 500) 
                    : null,
                'level' => $result['parsed']['level'] ?? null,
                'environment' => $result['parsed']['environment'] ?? null,
            ] : null,
            'type' => $result['type'],
            'level' => $level,
            'truncated' => $isTruncated,
            'stack_trace' => $stackTrace,
        ];
    }
    
    return $parsed;
}
```

#### Task 4.4: Add combined level + search filtering
```php
/**
 * Filter by both level and search term using grep
 */
public function filterByLevelAndSearchWithGrep(
    string $filepath, 
    string $level, 
    string $search, 
    int $page, 
    int $perPage
): array {
    if (!File::exists($filepath)) {
        return ['entries' => [], 'totalLines' => 0, 'totalPages' => 0];
    }
    
    $safeFilepath = escapeshellarg($filepath);
    $safeLevel = escapeshellarg(".$level:");
    $safeSearch = escapeshellarg($search);
    
    // Pipe grep commands: first filter by level, then by search term
    $countCmd = "grep {$safeLevel} {$safeFilepath} 2>/dev/null | grep -ci {$safeSearch} || echo 0";
    $totalMatches = (int) trim(shell_exec($countCmd));
    
    if ($totalMatches === 0) {
        return ['entries' => [], 'totalLines' => 0, 'totalPages' => 0];
    }
    
    $totalPages = max(1, (int) ceil($totalMatches / $perPage));
    $page = max(1, min($page, $totalPages));
    
    // Get matching entries
    $grepCmd = "grep -n {$safeLevel} {$safeFilepath} 2>/dev/null | grep -i {$safeSearch}";
    $matchingLines = shell_exec($grepCmd);
    
    // ... similar processing as filterByLevelWithGrep
}
```

### Performance Considerations

For very large log files (>100MB), consider these optimizations:

1. **Cache line counts**: Store total lines/entries count with file modification time
2. **Use `tac` for reverse reading**: More efficient for showing recent entries first
3. **Implement lazy loading**: Load entries on scroll rather than all at once
4. **Add file size warnings**: Alert users when viewing very large files

### Acceptance Criteria
- [ ] Level filter queries the entire file, not just loaded content
- [ ] Filtering is fast even on large (100MB+) log files
- [ ] Search filter works across entire file
- [ ] Combined level + search filtering works correctly
- [ ] Page counts reflect filtered results
- [ ] Stack traces are properly included with filtered entries

---

## Implementation Order

| Sprint | Priority | Estimated Effort | Dependencies |
|--------|----------|------------------|--------------|
| Sprint 1 | High | 1-2 hours | None |
| Sprint 2 | Medium | 2-3 hours | None |
| Sprint 3 | High | 3-4 hours | None |
| Sprint 4 | High | 4-6 hours | Sprint 3 |

**Recommended order**: Sprint 1 → Sprint 3 → Sprint 4 → Sprint 2

Sprint 1 is quick and improves UX immediately. Sprint 3 lays the groundwork for Sprint 4's efficient filtering. Sprint 2 can be done independently but benefits from the parsing improvements in Sprint 3.

---

## Testing Checklist

### Manual Testing
- [ ] Test pagination loading spinner with different file sizes
- [ ] Test expandable cards with various entry lengths
- [ ] Test multi-line entry parsing with `laravel.log` and `browser.log`
- [ ] Test single-line parsing with `openai.log`
- [ ] Test level filtering on files >50MB
- [ ] Test search filtering with special characters
- [ ] Test combined level + search filters
- [ ] Test edge cases: empty files, single entry, corrupted entries

### Automated Testing
```php
// tests/Feature/LogViewerTest.php

public function test_pagination_returns_correct_entries(): void
{
    // Create test log file with known content
    // Assert pagination returns correct subset
}

public function test_level_filter_uses_entire_file(): void
{
    // Create log with ERROR at beginning, middle, end
    // Filter by ERROR, verify all are found regardless of page
}

public function test_multiline_entries_grouped_correctly(): void
{
    // Create log with entry + stack trace
    // Assert they appear as single entry
}

public function test_single_line_format_detected(): void
{
    // Create openai-style log
    // Assert each line is separate entry
}
```

---

## Notes

- All shell commands use `escapeshellarg()` for security
- File path validation prevents directory traversal attacks
- Consider adding rate limiting for large file operations
- Memory usage is managed with streaming and truncation
