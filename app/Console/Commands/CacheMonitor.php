<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class CacheMonitor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:monitor
                            {--watch : Continuously monitor cache (refresh every 5 seconds)}
                            {--detailed : Show detailed per-database statistics}
                            {--keys : Show sample keys from each database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor Redis cache performance and statistics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('watch')) {
            return $this->watchMode();
        }

        $this->displayCacheStatistics();

        return self::SUCCESS;
    }

    /**
     * Continuously monitor cache statistics.
     */
    protected function watchMode(): int
    {
        $this->info('📊 Watching cache statistics (Press Ctrl+C to exit)...');
        $this->newLine();

        while (true) {
            // Clear screen (Unix/Linux/Mac)
            if (PHP_OS_FAMILY !== 'Windows') {
                system('clear');
            }

            $this->info('🔄 Cache Monitor - '.now()->format('Y-m-d H:i:s'));
            $this->newLine();

            $this->displayCacheStatistics();

            $this->newLine();
            $this->info('Refreshing in 5 seconds... (Press Ctrl+C to exit)');

            sleep(5);
        }

        return self::SUCCESS;
    }

    /**
     * Display cache statistics.
     */
    protected function displayCacheStatistics(): void
    {
        try {
            // Redis connection info
            $this->info('🔗 Redis Connection Info');
            $this->displayRedisConnectionInfo();
            $this->newLine();

            // Overall Redis statistics
            $this->info('📈 Redis Server Statistics');
            $this->displayRedisServerStats();
            $this->newLine();

            // Memory usage
            $this->info('💾 Memory Usage');
            $this->displayMemoryStats();
            $this->newLine();

            // Per-database statistics
            if ($this->option('detailed')) {
                $this->info('🗄️  Database Statistics');
                $this->displayDatabaseStats();
                $this->newLine();
            }

            // Sample keys
            if ($this->option('keys')) {
                $this->info('🔑 Sample Keys');
                $this->displaySampleKeys();
                $this->newLine();
            }

            // Cache performance metrics
            $this->info('⚡ Performance Metrics');
            $this->displayPerformanceMetrics();

        } catch (\Exception $e) {
            $this->error('❌ Failed to retrieve cache statistics: '.$e->getMessage());
        }
    }

    /**
     * Display Redis connection information.
     */
    protected function displayRedisConnectionInfo(): void
    {
        $redis = Redis::connection('default');
        $info = $redis->info('server');

        $rows = [
            ['Redis Version', $info['redis_version'] ?? 'Unknown'],
            ['Redis Mode', $info['redis_mode'] ?? 'Unknown'],
            ['OS', $info['os'] ?? 'Unknown'],
            ['Uptime (days)', isset($info['uptime_in_days']) ? $info['uptime_in_days'] : 'Unknown'],
        ];

        $this->table(['Metric', 'Value'], $rows);
    }

    /**
     * Display Redis server statistics.
     */
    protected function displayRedisServerStats(): void
    {
        $redis = Redis::connection('default');
        $info = $redis->info('stats');

        $totalConnections = $info['total_connections_received'] ?? 0;
        $totalCommands = $info['total_commands_processed'] ?? 0;
        $keyspaceHits = $info['keyspace_hits'] ?? 0;
        $keyspaceMisses = $info['keyspace_misses'] ?? 0;

        // Calculate hit ratio
        $totalRequests = $keyspaceHits + $keyspaceMisses;
        $hitRatio = $totalRequests > 0 ? ($keyspaceHits / $totalRequests) * 100 : 0;

        $rows = [
            ['Total Connections', number_format($totalConnections)],
            ['Total Commands', number_format($totalCommands)],
            ['Keyspace Hits', number_format($keyspaceHits)],
            ['Keyspace Misses', number_format($keyspaceMisses)],
            ['Hit Ratio', sprintf('%.2f%%', $hitRatio).$this->getHitRatioIndicator($hitRatio)],
        ];

        $this->table(['Metric', 'Value'], $rows);
    }

    /**
     * Get hit ratio indicator (emoji).
     */
    protected function getHitRatioIndicator(float $ratio): string
    {
        if ($ratio >= 90) {
            return ' ✅ Excellent';
        } elseif ($ratio >= 80) {
            return ' ✓ Good';
        } elseif ($ratio >= 70) {
            return ' ⚠ Fair';
        } else {
            return ' ❌ Poor';
        }
    }

    /**
     * Display memory usage statistics.
     */
    protected function displayMemoryStats(): void
    {
        $redis = Redis::connection('default');
        $info = $redis->info('memory');

        $usedMemory = $info['used_memory_human'] ?? 'Unknown';
        $usedMemoryRss = $info['used_memory_rss_human'] ?? 'Unknown';
        $usedMemoryPeak = $info['used_memory_peak_human'] ?? 'Unknown';
        $maxMemory = $info['maxmemory_human'] ?? 'No limit';
        $memFragmentationRatio = $info['mem_fragmentation_ratio'] ?? 'Unknown';

        $rows = [
            ['Used Memory', $usedMemory],
            ['Used Memory RSS', $usedMemoryRss],
            ['Peak Memory', $usedMemoryPeak],
            ['Max Memory', $maxMemory],
            ['Fragmentation Ratio', $memFragmentationRatio.$this->getFragmentationIndicator($memFragmentationRatio)],
        ];

        $this->table(['Metric', 'Value'], $rows);
    }

    /**
     * Get fragmentation ratio indicator.
     */
    protected function getFragmentationIndicator($ratio): string
    {
        if (! is_numeric($ratio)) {
            return '';
        }

        $ratio = (float) $ratio;

        if ($ratio >= 1.0 && $ratio <= 1.5) {
            return ' ✅ Healthy';
        } elseif ($ratio > 1.5) {
            return ' ⚠ High (consider restart)';
        } else {
            return ' ❌ Low (memory issue)';
        }
    }

    /**
     * Display per-database statistics.
     */
    protected function displayDatabaseStats(): void
    {
        $redis = Redis::connection('default');
        $info = $redis->info('keyspace');

        $databases = [
            0 => 'Default/Cache',
            1 => 'Sessions',
            2 => 'Queues',
            3 => 'Broadcasting',
        ];

        $rows = [];

        foreach ($databases as $dbNum => $dbName) {
            $dbInfo = $info["db{$dbNum}"] ?? null;

            if ($dbInfo) {
                // Parse db info: "keys=123,expires=45,avg_ttl=12345"
                preg_match('/keys=(\d+)/', $dbInfo, $keysMatch);
                preg_match('/expires=(\d+)/', $dbInfo, $expiresMatch);

                $keys = $keysMatch[1] ?? 0;
                $expires = $expiresMatch[1] ?? 0;

                $rows[] = [
                    "DB{$dbNum} ({$dbName})",
                    number_format($keys),
                    number_format($expires),
                ];
            } else {
                $rows[] = [
                    "DB{$dbNum} ({$dbName})",
                    '0',
                    '0',
                ];
            }
        }

        $this->table(['Database', 'Keys', 'Keys with TTL'], $rows);
    }

    /**
     * Display sample keys from each database.
     */
    protected function displaySampleKeys(): void
    {
        $databases = [
            'default' => 'Default/Cache',
            'session' => 'Sessions',
            'queue' => 'Queues',
            'broadcast' => 'Broadcasting',
        ];

        foreach ($databases as $connection => $name) {
            try {
                $redis = Redis::connection($connection);

                // Get sample keys (limit to 10)
                $keys = $redis->keys('*');
                $sampleKeys = array_slice($keys, 0, 10);

                if (! empty($sampleKeys)) {
                    $this->line("  {$name}:");
                    foreach ($sampleKeys as $key) {
                        $ttl = $redis->ttl($key);
                        $ttlStr = $ttl > 0 ? "TTL: {$ttl}s" : ($ttl === -1 ? 'No expiry' : 'Expired');
                        $this->line("    - {$key} ({$ttlStr})");
                    }
                } else {
                    $this->line("  {$name}: (empty)");
                }

                $this->newLine();

            } catch (\Exception $e) {
                $this->warn("  Failed to retrieve keys from {$name}: ".$e->getMessage());
            }
        }
    }

    /**
     * Display performance metrics.
     */
    protected function displayPerformanceMetrics(): void
    {
        $redis = Redis::connection('default');
        $info = $redis->info('stats');

        $instantaneousOpsPerSec = $info['instantaneous_ops_per_sec'] ?? 0;
        $instantaneousInputKbps = $info['instantaneous_input_kbps'] ?? 0;
        $instantaneousOutputKbps = $info['instantaneous_output_kbps'] ?? 0;

        $rows = [
            ['Operations per second', number_format($instantaneousOpsPerSec)],
            ['Input (KB/s)', number_format($instantaneousInputKbps, 2)],
            ['Output (KB/s)', number_format($instantaneousOutputKbps, 2)],
        ];

        // Database query cache hit ratio (PostgreSQL)
        try {
            $dbCacheRatio = $this->getDatabaseCacheRatio();
            if ($dbCacheRatio !== null) {
                $rows[] = ['DB Cache Hit Ratio', sprintf('%.2f%%', $dbCacheRatio).$this->getHitRatioIndicator($dbCacheRatio)];
            }
        } catch (\Exception $e) {
            // Silently ignore DB cache ratio errors
        }

        $this->table(['Metric', 'Value'], $rows);
    }

    /**
     * Get database cache hit ratio from PostgreSQL.
     */
    protected function getDatabaseCacheRatio(): ?float
    {
        try {
            $result = DB::select('
                SELECT
                    sum(heap_blks_read) as heap_read,
                    sum(heap_blks_hit) as heap_hit
                FROM pg_statio_user_tables
            ');

            if (! empty($result)) {
                $heapRead = $result[0]->heap_read ?? 0;
                $heapHit = $result[0]->heap_hit ?? 0;
                $total = $heapRead + $heapHit;

                if ($total > 0) {
                    return ($heapHit / $total) * 100;
                }
            }
        } catch (\Exception $e) {
            // Return null if query fails
        }

        return null;
    }
}
