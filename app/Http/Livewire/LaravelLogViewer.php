<?php

namespace App\Http\Livewire;

use App\Services\LogViewerService;
use Livewire\Component;

class LaravelLogViewer extends Component
{
    protected LogViewerService $logService;

    public array $logFiles = [];

    public ?string $selectedFile = null;

    public int $perPage = 50;
    public int $currentPage = 1;
    public int $totalPages = 1;
    public int $totalLines = 0;

    /**
     * Maximum size of raw log entry to store (3KB)
     */
    protected const MAX_RAW_SIZE = 3072;

    public string $search = '';

    public string $levelFilter = 'all';

    public bool $autoRefresh = false;

    public array $entries = [];

    public function boot(LogViewerService $logService)
    {
        $this->logService = $logService;
    }

    public function mount()
    {
        $this->loadLogFiles();
        if (! empty($this->logFiles)) {
            $this->selectedFile = array_key_first($this->logFiles);
            $this->loadEntries();
        }
    }

    public function updated($property)
    {
        if (in_array($property, ['selectedFile', 'perPage', 'search', 'levelFilter'])) {
            $this->currentPage = 1;
            $this->loadEntries();
        }
    }

    public function loadLogFiles()
    {
        $this->logFiles = $this->logService->getLogFiles();
    }

    public function selectFile(string $filename)
    {
        $this->selectedFile = $filename;
        $this->currentPage = 1;
        $this->loadEntries();
    }

    public function refreshNow()
    {
        $this->loadLogFiles();
        $this->loadEntries();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->levelFilter = 'all';
        $this->loadEntries();
    }

    public function deleteFile(string $filename)
    {
        if ($this->logService->deleteLogFile($filename)) {
            $this->loadLogFiles();
            if ($this->selectedFile === $filename) {
                $this->selectedFile = ! empty($this->logFiles) ? array_key_first($this->logFiles) : null;
            }
            $this->loadEntries();
        }
    }

    public function downloadFile(string $filename)
    {
        $content = $this->logService->getLogContent($filename);
        if ($content !== null) {
            return response()->streamDownload(function () use ($content) {
                echo $content;
            }, $filename, [
                'Content-Type' => 'text/plain',
            ]);
        }

        // Large file: stream directly from disk
        $path = $this->logService->getLogFilePath($filename);
        if ($path === null) {
            return;
        }

        return response()->streamDownload(function () use ($path) {
            $f = fopen($path, 'rb');
            if ($f) {
                while (!feof($f)) {
                    echo fread($f, 65536);
                    flush();
                }
                fclose($f);
            }
        }, $filename, [
            'Content-Type' => 'text/plain',
        ]);
    }

    protected function loadEntries(): void
    {
        $this->entries = [];
        $this->totalPages = 1;
        $this->totalLines = 0;

        if (! $this->selectedFile || ! isset($this->logFiles[$this->selectedFile])) {
            return;
        }

        $filepath = $this->logFiles[$this->selectedFile]['path'];
        $format = $this->logService->detectLogFormat($this->selectedFile);

        if ($format === 'laravel') {
            $this->loadEntriesMultiLine($filepath);
        } else {
            $this->loadEntriesSingleLine($filepath);
        }
    }

    protected function loadEntriesMultiLine(string $filepath): void
    {
        $needsGrepFilter = $this->levelFilter !== 'all' || !empty($this->search);

        if ($needsGrepFilter) {
            // Use grep for efficient server-side filtering
            if ($this->levelFilter !== 'all' && empty($this->search)) {
                $pageResult = $this->logService->filterByLevelWithGrep(
                    $filepath, $this->levelFilter, $this->currentPage, $this->perPage
                );
            } elseif (!empty($this->search) && $this->levelFilter === 'all') {
                $pageResult = $this->logService->searchWithGrep(
                    $filepath, $this->search, $this->currentPage, $this->perPage
                );
            } else {
                $pageResult = $this->logService->filterByLevelAndSearchWithGrep(
                    $filepath, $this->levelFilter, $this->search, $this->currentPage, $this->perPage
                );
            }

            $this->totalLines = $pageResult['totalLines'];
            $this->totalPages = $pageResult['totalPages'];

            // Parse the grep results
            $this->entries = $this->parseGrepResults($pageResult['entries']);
        } else {
            // No filter - use multi-line reading
            $pageResult = $this->logService->readPageMultiLine($filepath, $this->currentPage, $this->perPage);
            $rawEntries = $pageResult['entries'];

            $this->totalLines = $pageResult['totalLines'];
            $this->totalPages = $pageResult['totalPages'];

            $parsed = [];
            foreach ($rawEntries as $rawEntry) {
                $lines = explode("\n", trim($rawEntry));
                $firstLine = $lines[0] ?? '';
                $result = $this->logService->parseLine($firstLine);

                $stackTrace = count($lines) > 1
                    ? implode("\n", array_slice($lines, 1))
                    : null;

                $level = strtolower($result['parsed']['level'] ?? 'unknown');

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

    protected function loadEntriesSingleLine(string $filepath): void
    {
        $pageResult = $this->logService->readPage($filepath, $this->currentPage, $this->perPage);

        $this->totalLines = $pageResult['totalLines'];
        $this->totalPages = $pageResult['totalPages'];

        // Group continuation lines (stack traces) with their parent log entry
        $grouped = $this->groupLogLines($pageResult['lines']);

        $parsed = [];
        foreach ($grouped as $group) {
            $level = 'unknown';
            if ($group['type'] === 'json' && isset($group['parsed']['level_name'])) {
                $level = strtolower($group['parsed']['level_name']);
            } elseif ($group['type'] === 'json' && isset($group['parsed']['level'])) {
                $level = strtolower($group['parsed']['level']);
            } elseif ($group['type'] === 'laravel' && isset($group['parsed']['level'])) {
                $level = strtolower($group['parsed']['level']);
            }

            if ($this->levelFilter !== 'all' && $level !== strtolower($this->levelFilter)) {
                continue;
            }

            $raw = $group['raw'];
            if ($this->search) {
                if (! str_contains(strtolower($raw), strtolower($this->search))) {
                    continue;
                }
            }

            $isTruncated = false;
            if (strlen($raw) > self::MAX_RAW_SIZE) {
                $raw = substr($raw, 0, self::MAX_RAW_SIZE) . "\n... [truncated]";
                $isTruncated = true;
            }

            $essentialParsed = null;
            if ($group['parsed']) {
                $essentialParsed = [
                    'datetime' => $group['parsed']['datetime'] ?? $group['parsed']['date'] ?? null,
                    'message' => isset($group['parsed']['message'])
                        ? substr($group['parsed']['message'], 0, 500)
                        : null,
                    'level' => $group['parsed']['level'] ?? $group['parsed']['level_name'] ?? null,
                    'environment' => $group['parsed']['environment'] ?? $group['parsed']['channel'] ?? null,
                ];
            }

            $stackTrace = $group['stack_trace'] ?? null;
            if ($stackTrace && strlen($stackTrace) > self::MAX_RAW_SIZE) {
                $stackTrace = substr($stackTrace, 0, self::MAX_RAW_SIZE) . "\n... [truncated]";
            }

            $parsed[] = [
                'raw' => $raw,
                'parsed' => $essentialParsed,
                'type' => $group['type'],
                'level' => $level,
                'truncated' => $isTruncated,
                'stack_trace' => $stackTrace,
            ];
        }

        $this->entries = array_reverse($parsed);
    }

    /**
     * Group continuation lines (stack traces, context) with their parent log entry.
     * Lines that don't match a log format are appended to the previous structured entry.
     */
    protected function groupLogLines(array $lines): array
    {
        $grouped = [];
        $currentEntry = null;

        foreach ($lines as $line) {
            $result = $this->logService->parseLine($line);

            if ($result['type'] === 'empty') {
                continue;
            }

            // Structured log entry (json or laravel format) starts a new group
            if ($result['type'] !== 'plain') {
                if ($currentEntry !== null) {
                    $grouped[] = $currentEntry;
                }
                $currentEntry = [
                    'raw' => $result['raw'],
                    'parsed' => $result['parsed'],
                    'type' => $result['type'],
                    'stack_trace' => null,
                ];
            } else {
                // Plain line = continuation/stack trace
                if ($currentEntry !== null) {
                    // Append to current entry's stack trace
                    if ($currentEntry['stack_trace'] === null) {
                        $currentEntry['stack_trace'] = $result['raw'];
                    } else {
                        $currentEntry['stack_trace'] .= "\n" . $result['raw'];
                    }
                } else {
                    // Orphan plain line (no parent entry yet) - show as standalone
                    $currentEntry = [
                        'raw' => $result['raw'],
                        'parsed' => null,
                        'type' => 'plain',
                        'stack_trace' => null,
                    ];
                }
            }
        }

        // Don't forget the last entry
        if ($currentEntry !== null) {
            $grouped[] = $currentEntry;
        }

        return $grouped;
    }

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

    public function render()
    {
        return view('livewire.laravel-log-viewer');
    }
}
