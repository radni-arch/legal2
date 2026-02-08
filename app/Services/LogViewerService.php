<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class LogViewerService
{
    /**
     * Get list of all log files in storage/logs
     *
     * @return array<string, array{name: string, path: string, size: int, modified: int}>
     */
    public function getLogFiles(): array
    {
        $logsPath = storage_path('logs');
        if (! File::isDirectory($logsPath)) {
            return [];
        }

        $files = File::files($logsPath);
        $logFiles = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'log') {
                $logFiles[$file->getFilename()] = [
                    'name' => $file->getFilename(),
                    'path' => $file->getPathname(),
                    'size' => $file->getSize(),
                    'modified' => $file->getMTime(),
                ];
            }
        }

        // Sort by modified time, most recent first
        uasort($logFiles, fn ($a, $b) => $b['modified'] <=> $a['modified']);

        return $logFiles;
    }

    /**
     * Read last N lines from a log file
     *
     * @return array<int, string>
     */
    public function tail(string $filepath, int $lines = 200): array
    {
        if (! File::exists($filepath)) {
            return [];
        }

        $f = @fopen($filepath, 'rb');
        if ($f === false) {
            return [];
        }

        $buffer = '';
        $chunkSize = 4096;
        // Maximum buffer size to prevent memory exhaustion and timeout on huge files
        // 10MB should be sufficient for any reasonable log viewing use case
        $maxBufferSize = 10 * 1024 * 1024;
        fseek($f, 0, SEEK_END);
        $fileSize = ftell($f);

        if ($fileSize === 0) {
            fclose($f);

            return [];
        }

        $cursor = $fileSize;
        while ($cursor > 0) {
            $read = min($chunkSize, $cursor);
            $cursor -= $read;
            fseek($f, $cursor);
            $chunk = fread($f, $read);
            if ($chunk === false) {
                break;
            }
            $buffer = $chunk.$buffer;

            // Safety: stop if buffer exceeds max size to prevent timeout
            if (strlen($buffer) > $maxBufferSize) {
                break;
            }

            $linesInBuffer = substr_count($buffer, "\n");
            if ($linesInBuffer >= $lines + 1) {
                break;
            }
        }
        fclose($f);

        $arr = preg_split("/[\r\n]+/", $buffer);
        $arr = array_values(array_filter($arr, fn ($l) => $l !== null && $l !== ''));

        return array_slice($arr, -$lines);
    }

    /**
     * Parse a log line (supports both JSON and standard Laravel format)
     *
     * @return array{raw: string, parsed: array|null, type: string}
     */
    public function parseLine(string $line): array
    {
        $line = trim($line);

        if (empty($line)) {
            return [
                'raw' => $line,
                'parsed' => null,
                'type' => 'empty',
            ];
        }

        // Try JSON format first
        $data = json_decode($line, true);
        if (is_array($data)) {
            return [
                'raw' => $line,
                'parsed' => $data,
                'type' => 'json',
            ];
        }

        // Try standard Laravel log format: [YYYY-MM-DD HH:MM:SS] environment.LEVEL: message
        if (preg_match('/^\[(?P<date>\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\]\s+(?P<env>\w+)\.(?P<level>DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY):\s*(?P<message>.*)/is', $line, $matches)) {
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
        return [
            'raw' => $line,
            'parsed' => null,
            'type' => 'plain',
        ];
    }

    /**
     * Format file size to human-readable format
     */
    public function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }

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
     * Read a page of lines from end of file.
     * Page 1 = most recent entries, Page 2 = next older batch, etc.
     *
     * @return array{lines: array, totalLines: int, totalPages: int}
     */
    public function readPage(string $filepath, int $page, int $perPage): array
    {
        $totalLines = $this->countLines($filepath);
        if ($totalLines === 0) {
            return ['lines' => [], 'totalLines' => 0, 'totalPages' => 0];
        }

        $totalPages = max(1, (int) ceil($totalLines / $perPage));
        $page = max(1, min($page, $totalPages));

        // We need to read enough lines from the end to cover the requested page
        $linesFromEnd = $page * $perPage;
        $allLines = $this->tail($filepath, $linesFromEnd);

        // Page 1 = last $perPage lines, Page 2 = the ones before those, etc.
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

    /**
     * Delete a log file
     */
    public function deleteLogFile(string $filename): bool
    {
        $path = storage_path('logs/'.$filename);

        // Security check: ensure it's actually in logs directory
        if (! str_starts_with(realpath($path) ?: '', realpath(storage_path('logs')))) {
            return false;
        }

        if (File::exists($path)) {
            return File::delete($path);
        }

        return false;
    }

    /**
     * Filter log entries by level (ERROR, WARNING, DEBUG, etc.)
     *
     * @param string $filepath Full path to log file
     * @param string $level Log level to filter (case-insensitive)
     * @param int $limit Max entries to return
     * @return array<int, array{raw: string, parsed: array|null, type: string}>
     */
    public function filterByLevel(string $filepath, string $level, int $limit = 50): array
    {
        $level = strtoupper($level);
        $tailLines = $this->tail($filepath, $limit * 20);
        $matches = [];

        foreach ($tailLines as $line) {
            $parsed = $this->parseLine($line);
            if ($parsed['type'] === 'laravel'
                && isset($parsed['parsed']['level'])
                && strtoupper($parsed['parsed']['level']) === $level
            ) {
                $matches[] = $parsed;
            }
        }

        return array_slice($matches, -$limit);
    }

    /**
     * Filter log entries by multiple levels.
     *
     * @param string $filepath Full path to log file
     * @param array<string> $levels Log levels to filter (case-insensitive)
     * @param int $limit Max entries to return
     * @return array<int, array{raw: string, parsed: array|null, type: string}>
     */
    public function filterByLevels(string $filepath, array $levels, int $limit = 50): array
    {
        $levels = array_map('strtoupper', $levels);
        $tailLines = $this->tail($filepath, $limit * 20);
        $matches = [];

        foreach ($tailLines as $line) {
            $parsed = $this->parseLine($line);
            if ($parsed['type'] === 'laravel'
                && isset($parsed['parsed']['level'])
                && in_array(strtoupper($parsed['parsed']['level']), $levels, true)
            ) {
                $matches[] = $parsed;
            }
        }

        return array_slice($matches, -$limit);
    }

    /**
     * Determine the log format based on filename
     */
    public function detectLogFormat(string $filename): string
    {
        $multiLineFormats = ['laravel.log', 'browser.log'];

        foreach ($multiLineFormats as $pattern) {
            if (str_contains($filename, $pattern) || fnmatch($pattern, $filename)) {
                return 'laravel';
            }
        }

        return 'single-line';
    }

    /**
     * Check if a line is a log entry start (Laravel format)
     */
    public function isLogEntryStart(string $line): bool
    {
        return (bool) preg_match('/^\[\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\]\s+\w+\.\w+:/i', $line);
    }

    /**
     * Read a page of entries (not lines) for multi-line log formats.
     * Uses grep+sed for memory-efficient extraction without loading the full file.
     *
     * @return array{entries: array, totalLines: int, totalPages: int}
     */
    public function readPageMultiLine(string $filepath, int $page, int $perPage): array
    {
        if (!File::exists($filepath)) {
            return ['entries' => [], 'totalLines' => 0, 'totalPages' => 0];
        }

        $safeFilepath = escapeshellarg($filepath);
        $entryPattern = '^\[[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9] [0-9][0-9]:[0-9][0-9]:[0-9][0-9]\] [a-zA-Z]';

        // Count total entries using grep (memory-safe)
        $countCmd = "grep -c '{$entryPattern}' {$safeFilepath} 2>/dev/null || echo 0";
        $totalEntries = (int) trim(shell_exec($countCmd));

        if ($totalEntries === 0) {
            return ['entries' => [], 'totalLines' => 0, 'totalPages' => 0];
        }

        $totalPages = max(1, (int) ceil($totalEntries / $perPage));
        $page = max(1, min($page, $totalPages));

        // Get all entry start line numbers (only numbers via cut, not content)
        $grepCmd = "grep -n '{$entryPattern}' {$safeFilepath} 2>/dev/null | cut -d: -f1";
        $grepOutput = shell_exec($grepCmd);

        if (empty($grepOutput)) {
            return ['entries' => [], 'totalLines' => 0, 'totalPages' => 0];
        }

        $startLines = [];
        foreach (explode("\n", trim($grepOutput)) as $num) {
            if ($num !== '') {
                $startLines[] = (int) $num;
            }
        }

        // Get total file lines for last entry boundary
        $totalFileLines = (int) trim(shell_exec("wc -l < {$safeFilepath} 2>/dev/null || echo 0"));

        // Reverse for most recent first, then paginate
        $startLines = array_reverse($startLines);
        $offset = ($page - 1) * $perPage;
        $pageStartLines = array_slice($startLines, $offset, $perPage);

        // Build a map of start line -> end line for the full (non-reversed) set
        $allStartLines = array_reverse($startLines); // back to original order for lookups

        // Extract each entry using sed (memory-safe: reads only needed lines)
        $entries = [];
        foreach ($pageStartLines as $startLine) {
            $endLine = $this->findNextEntryStart($allStartLines, $startLine, $totalFileLines);
            $cmd = "sed -n '{$startLine},{$endLine}p' {$safeFilepath}";
            $entryContent = shell_exec($cmd);
            if ($entryContent !== null) {
                $entries[] = rtrim($entryContent, "\n");
            }
        }

        return [
            'entries' => $entries,
            'totalLines' => $totalEntries,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * Find the line before the next entry start after the given start line.
     */
    protected function findNextEntryStart(array $sortedStartLines, int $currentStart, int $totalFileLines): int
    {
        foreach ($sortedStartLines as $line) {
            if ($line > $currentStart) {
                return $line - 1;
            }
        }
        return $totalFileLines;
    }

    /**
     * Get all entry start line numbers for a multi-line log file.
     * Memory-safe: uses grep, never loads the file.
     *
     * @return array<int> Sorted ascending line numbers
     */
    protected function getEntryStartLineNumbers(string $safeFilepath): array
    {
        $entryPattern = '^\[[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9] [0-9][0-9]:[0-9][0-9]:[0-9][0-9]\] [a-zA-Z]';
        $grepCmd = "grep -n '{$entryPattern}' {$safeFilepath} 2>/dev/null | cut -d: -f1";
        $output = shell_exec($grepCmd);

        if (empty($output)) {
            return [];
        }

        $lines = [];
        foreach (explode("\n", trim($output)) as $num) {
            if ($num !== '') {
                $lines[] = (int) $num;
            }
        }

        return $lines;
    }

    /**
     * Extract entries at given start line numbers using sed.
     * Memory-safe: extracts only the needed line ranges.
     *
     * @param string $safeFilepath Escaped file path
     * @param array<int> $startLineNumbers Line numbers where entries start (1-based)
     * @param array<int> $allEntryStarts All entry start line numbers (ascending)
     * @param int $totalFileLines Total lines in the file
     * @return array<string> Raw entry strings
     */
    protected function extractEntriesAtLines(string $safeFilepath, array $startLineNumbers, array $allEntryStarts, int $totalFileLines): array
    {
        $entries = [];
        foreach ($startLineNumbers as $startLine) {
            $endLine = $this->findNextEntryStart($allEntryStarts, $startLine, $totalFileLines);
            // Cap at 101 lines per entry to prevent huge extractions
            $endLine = min($endLine, $startLine + 100);
            $cmd = "sed -n '{$startLine},{$endLine}p' {$safeFilepath}";
            $entryContent = shell_exec($cmd);
            if ($entryContent !== null) {
                $entries[] = rtrim($entryContent, "\n");
            }
        }

        return $entries;
    }

    /**
     * Filter log file by level using grep for efficiency.
     * Memory-safe: uses grep+sed, never loads the full file.
     */
    public function filterByLevelWithGrep(string $filepath, string $level, int $page, int $perPage): array
    {
        if (!File::exists($filepath)) {
            return ['entries' => [], 'totalLines' => 0, 'totalPages' => 0];
        }

        $level = strtoupper($level);
        $safeFilepath = escapeshellarg($filepath);
        $safeLevel = escapeshellarg(".$level:");

        // Count total matches
        $countCmd = "grep -c {$safeLevel} {$safeFilepath} 2>/dev/null || echo 0";
        $totalMatches = (int) trim(shell_exec($countCmd));

        if ($totalMatches === 0) {
            return ['entries' => [], 'totalLines' => 0, 'totalPages' => 0];
        }

        $totalPages = max(1, (int) ceil($totalMatches / $perPage));
        $page = max(1, min($page, $totalPages));

        // Get matching line numbers (only numbers via cut)
        $grepCmd = "grep -n {$safeLevel} {$safeFilepath} 2>/dev/null | cut -d: -f1";
        $matchingLines = shell_exec($grepCmd);

        if (empty($matchingLines)) {
            return ['entries' => [], 'totalLines' => 0, 'totalPages' => 0];
        }

        $lineNumbers = [];
        foreach (explode("\n", trim($matchingLines)) as $num) {
            if ($num !== '') {
                $lineNumbers[] = (int) $num;
            }
        }

        // Reverse for most recent first
        $lineNumbers = array_reverse($lineNumbers);

        $offset = ($page - 1) * $perPage;
        $pageLineNumbers = array_slice($lineNumbers, $offset, $perPage);

        // Get all entry starts and total lines for boundary detection
        $allEntryStarts = $this->getEntryStartLineNumbers($safeFilepath);
        $totalFileLines = (int) trim(shell_exec("wc -l < {$safeFilepath} 2>/dev/null || echo 0"));

        $entries = $this->extractEntriesAtLines($safeFilepath, $pageLineNumbers, $allEntryStarts, $totalFileLines);

        return [
            'entries' => $entries,
            'totalLines' => $totalMatches,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * Search log file content using grep.
     * Memory-safe: uses grep+sed to find parent entries without loading the file.
     */
    public function searchWithGrep(string $filepath, string $search, int $page, int $perPage): array
    {
        if (!File::exists($filepath) || empty($search)) {
            return ['entries' => [], 'totalLines' => 0, 'totalPages' => 0];
        }

        $safeFilepath = escapeshellarg($filepath);
        $safeSearch = escapeshellarg($search);

        // Get matching line numbers (only numbers via cut)
        $grepCmd = "grep -ni {$safeSearch} {$safeFilepath} 2>/dev/null | cut -d: -f1";
        $matchingLines = shell_exec($grepCmd);

        if (empty($matchingLines)) {
            return ['entries' => [], 'totalLines' => 0, 'totalPages' => 0];
        }

        $lineNumbers = [];
        foreach (explode("\n", trim($matchingLines)) as $num) {
            if ($num !== '') {
                $lineNumbers[] = (int) $num;
            }
        }

        // Get all entry start line numbers to map matches to parent entries
        $allEntryStarts = $this->getEntryStartLineNumbers($safeFilepath);

        if (empty($allEntryStarts)) {
            return ['entries' => [], 'totalLines' => 0, 'totalPages' => 0];
        }

        // For each matching line, find its parent entry start using binary search
        $entryStartSet = [];
        foreach ($lineNumbers as $lineNum) {
            $parentStart = $this->findParentEntryStart($allEntryStarts, $lineNum);
            $entryStartSet[$parentStart] = true;
        }

        $uniqueStarts = array_keys($entryStartSet);
        sort($uniqueStarts);
        $uniqueStarts = array_reverse($uniqueStarts); // most recent first

        $totalUniqueEntries = count($uniqueStarts);
        $totalPages = max(1, (int) ceil($totalUniqueEntries / $perPage));
        $page = max(1, min($page, $totalPages));

        $offset = ($page - 1) * $perPage;
        $pageStarts = array_slice($uniqueStarts, $offset, $perPage);

        $totalFileLines = (int) trim(shell_exec("wc -l < {$safeFilepath} 2>/dev/null || echo 0"));
        $entries = $this->extractEntriesAtLines($safeFilepath, $pageStarts, $allEntryStarts, $totalFileLines);

        return [
            'entries' => $entries,
            'totalLines' => $totalUniqueEntries,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * Find the entry start line that contains the given line number.
     * Uses the sorted entry start lines array.
     */
    protected function findParentEntryStart(array $sortedStarts, int $lineNum): int
    {
        $result = $sortedStarts[0];
        foreach ($sortedStarts as $start) {
            if ($start <= $lineNum) {
                $result = $start;
            } else {
                break;
            }
        }
        return $result;
    }

    /**
     * Filter by both level and search term using grep.
     * Memory-safe: uses grep+sed, never loads the full file.
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
        $safeLevel = escapeshellarg("." . strtoupper($level) . ":");
        $safeSearch = escapeshellarg($search);

        // Pipe: filter by level, then by search
        $countCmd = "grep {$safeLevel} {$safeFilepath} 2>/dev/null | grep -ci {$safeSearch} || echo 0";
        $totalMatches = (int) trim(shell_exec($countCmd));

        if ($totalMatches === 0) {
            return ['entries' => [], 'totalLines' => 0, 'totalPages' => 0];
        }

        $totalPages = max(1, (int) ceil($totalMatches / $perPage));
        $page = max(1, min($page, $totalPages));

        // Get line numbers matching both criteria (cut strips content, keeps only line nums)
        $grepCmd = "grep -n {$safeLevel} {$safeFilepath} 2>/dev/null | grep -i {$safeSearch} | cut -d: -f1";
        $matchingLines = shell_exec($grepCmd);

        if (empty($matchingLines)) {
            return ['entries' => [], 'totalLines' => 0, 'totalPages' => 0];
        }

        $lineNumbers = [];
        foreach (explode("\n", trim($matchingLines)) as $num) {
            if ($num !== '') {
                $lineNumbers[] = (int) $num;
            }
        }

        $lineNumbers = array_reverse($lineNumbers);

        $offset = ($page - 1) * $perPage;
        $pageLineNumbers = array_slice($lineNumbers, $offset, $perPage);

        // Get all entry starts and total lines for boundary detection
        $allEntryStarts = $this->getEntryStartLineNumbers($safeFilepath);
        $totalFileLines = (int) trim(shell_exec("wc -l < {$safeFilepath} 2>/dev/null || echo 0"));

        $entries = $this->extractEntriesAtLines($safeFilepath, $pageLineNumbers, $allEntryStarts, $totalFileLines);

        return [
            'entries' => $entries,
            'totalLines' => $totalMatches,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * Stream download a log file without loading it into memory.
     */
    public function getLogContent(string $filename): ?string
    {
        $path = storage_path('logs/'.$filename);

        // Security check
        if (! str_starts_with(realpath($path) ?: '', realpath(storage_path('logs')))) {
            return null;
        }

        if (! File::exists($path)) {
            return null;
        }

        // For files under 10MB, return content directly
        if (filesize($path) < 10 * 1024 * 1024) {
            return File::get($path);
        }

        // For larger files, return null (caller should use streaming)
        return null;
    }

    /**
     * Get the file path for streaming download (memory-safe for large files).
     */
    public function getLogFilePath(string $filename): ?string
    {
        $path = storage_path('logs/'.$filename);

        if (! str_starts_with(realpath($path) ?: '', realpath(storage_path('logs')))) {
            return null;
        }

        if (File::exists($path)) {
            return $path;
        }

        return null;
    }
}
