<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AnalyzeLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:analyze
                            {--channel= : Specific log channel to analyze (performance, queue, security, etc.)}
                            {--errors : Show only error entries}
                            {--warnings : Show only warning entries}
                            {--since= : Show entries since this time (e.g., "1 hour ago", "2024-11-08")}
                            {--tail=100 : Number of recent lines to analyze}
                            {--stats : Show statistics summary}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze application logs and show insights';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $channel = $this->option('channel') ?: 'laravel';
        $logPath = storage_path("logs/{$channel}.log");

        if (! File::exists($logPath)) {
            $this->error("Log file not found: {$logPath}");
            $this->info('Available log channels:');
            $this->showAvailableChannels();

            return self::FAILURE;
        }

        $this->info("Analyzing logs: {$channel}");
        $this->newLine();

        if ($this->option('stats')) {
            $this->showStatistics($logPath);
        } else {
            $this->showLogEntries($logPath);
        }

        return self::SUCCESS;
    }

    /**
     * Show log statistics.
     */
    protected function showStatistics(string $logPath): void
    {
        $lines = $this->readLog($logPath);

        $stats = [
            'total' => count($lines),
            'emergency' => 0,
            'alert' => 0,
            'critical' => 0,
            'error' => 0,
            'warning' => 0,
            'notice' => 0,
            'info' => 0,
            'debug' => 0,
        ];

        $errors = [];
        $slowRequests = [];

        foreach ($lines as $line) {
            // Count log levels
            foreach (array_keys($stats) as $level) {
                if ($level !== 'total' && stripos($line, ".{$level}:") !== false) {
                    $stats[$level]++;
                }
            }

            // Extract error messages
            if (stripos($line, '.ERROR:') !== false || stripos($line, '.CRITICAL:') !== false) {
                $errors[] = $this->extractMessage($line);
            }

            // Extract slow requests
            if (strpos($line, 'Slow request') !== false) {
                $slowRequests[] = $this->extractSlowRequest($line);
            }
        }

        // Display statistics
        $this->info('📊 Log Statistics');
        $this->newLine();

        $this->table(['Level', 'Count', 'Percentage'], [
            ['Total Lines', $stats['total'], '100%'],
            ['EMERGENCY', $stats['emergency'], $this->percentage($stats['emergency'], $stats['total'])],
            ['ALERT', $stats['alert'], $this->percentage($stats['alert'], $stats['total'])],
            ['CRITICAL', $stats['critical'], $this->percentage($stats['critical'], $stats['total'])],
            ['ERROR', $stats['error'], $this->percentage($stats['error'], $stats['total'])],
            ['WARNING', $stats['warning'], $this->percentage($stats['warning'], $stats['total'])],
            ['NOTICE', $stats['notice'], $this->percentage($stats['notice'], $stats['total'])],
            ['INFO', $stats['info'], $this->percentage($stats['info'], $stats['total'])],
            ['DEBUG', $stats['debug'], $this->percentage($stats['debug'], $stats['total'])],
        ]);

        $this->newLine();

        // Show top errors
        if (! empty($errors)) {
            $this->warn('🔴 Recent Errors (last 10):');
            $this->newLine();

            $errorCounts = array_count_values($errors);
            arsort($errorCounts);

            $topErrors = array_slice($errorCounts, 0, 10, true);

            foreach ($topErrors as $error => $count) {
                $this->line("  [{$count}x] ".substr($error, 0, 100));
            }

            $this->newLine();
        }

        // Show slow requests
        if (! empty($slowRequests)) {
            $this->warn('⏱️  Slow Requests (last 10):');
            $this->newLine();

            foreach (array_slice($slowRequests, -10) as $request) {
                $this->line("  {$request}");
            }

            $this->newLine();
        }

        // Health assessment
        $errorRate = ($stats['error'] + $stats['critical'] + $stats['emergency']) / max($stats['total'], 1) * 100;

        $this->info('🏥 Health Assessment:');
        $this->newLine();

        if ($errorRate < 1) {
            $this->info('  ✅ Healthy - Error rate: '.round($errorRate, 2).'%');
        } elseif ($errorRate < 5) {
            $this->warn('  ⚠️  Warning - Error rate: '.round($errorRate, 2).'%');
        } else {
            $this->error('  ❌ Critical - Error rate: '.round($errorRate, 2).'%');
        }
    }

    /**
     * Show log entries.
     */
    protected function showLogEntries(string $logPath): void
    {
        $lines = $this->readLog($logPath);

        // Apply filters
        if ($this->option('errors')) {
            $lines = array_filter($lines, fn ($line) => stripos($line, '.ERROR:') !== false || stripos($line, '.CRITICAL:') !== false);
        }

        if ($this->option('warnings')) {
            $lines = array_filter($lines, fn ($line) => stripos($line, '.WARNING:') !== false);
        }

        if ($this->option('since')) {
            $since = strtotime($this->option('since'));
            $lines = array_filter($lines, function ($line) use ($since) {
                $timestamp = $this->extractTimestamp($line);

                return $timestamp && $timestamp >= $since;
            });
        }

        // Limit to tail
        $tail = (int) $this->option('tail');
        $lines = array_slice($lines, -$tail);

        if (empty($lines)) {
            $this->info('No log entries found matching the criteria.');

            return;
        }

        $this->info('📝 Log Entries ('.count($lines).' total):');
        $this->newLine();

        foreach ($lines as $line) {
            // Color code based on log level
            if (stripos($line, '.ERROR:') !== false || stripos($line, '.CRITICAL:') !== false) {
                $this->error($line);
            } elseif (stripos($line, '.WARNING:') !== false) {
                $this->warn($line);
            } else {
                $this->line($line);
            }
        }
    }

    /**
     * Read log file.
     */
    protected function readLog(string $logPath): array
    {
        $content = File::get($logPath);

        return array_filter(explode("\n", $content));
    }

    /**
     * Extract error message from log line.
     */
    protected function extractMessage(string $line): string
    {
        // Extract message after log level
        if (preg_match('/\.(ERROR|CRITICAL):\s+(.+?)(\s+\{|$)/', $line, $matches)) {
            return trim($matches[2]);
        }

        return substr($line, 0, 200);
    }

    /**
     * Extract slow request info.
     */
    protected function extractSlowRequest(string $line): string
    {
        if (preg_match('/"url":"([^"]+)".*"duration_ms":([\d.]+)/', $line, $matches)) {
            return "{$matches[1]} - {$matches[2]}ms";
        }

        return substr($line, 0, 200);
    }

    /**
     * Extract timestamp from log line.
     */
    protected function extractTimestamp(string $line): ?int
    {
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            return strtotime($matches[1]);
        }

        return null;
    }

    /**
     * Calculate percentage.
     */
    protected function percentage(int $value, int $total): string
    {
        if ($total === 0) {
            return '0%';
        }

        return round(($value / $total) * 100, 1).'%';
    }

    /**
     * Show available log channels.
     */
    protected function showAvailableChannels(): void
    {
        $logDir = storage_path('logs');
        $files = File::glob("{$logDir}/*.log");

        foreach ($files as $file) {
            $channel = basename($file, '.log');
            $size = $this->formatBytes(File::size($file));
            $modified = date('Y-m-d H:i:s', File::lastModified($file));

            $this->line("  {$channel} ({$size}, modified: {$modified})");
        }
    }

    /**
     * Format bytes to human readable.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
