<?php

namespace App\Console\Commands;

use App\Services\Database\ConnectionPoolMonitor;
use Illuminate\Console\Command;

/**
 * Display real-time database connection pool status.
 *
 * Shows active connections, pool utilization, idle connections,
 * and long-running queries with color-coded output.
 */
class DatabaseConnectionStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:connections
                          {--detailed : Show detailed connection information}
                          {--long-running=60 : Threshold in seconds for long-running queries}
                          {--refresh=0 : Auto-refresh interval in seconds (0 = no refresh)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display database connection pool status and health metrics';

    /**
     * Connection pool monitor instance.
     */
    protected ConnectionPoolMonitor $monitor;

    /**
     * Create a new command instance.
     */
    public function __construct(ConnectionPoolMonitor $monitor)
    {
        parent::__construct();
        $this->monitor = $monitor;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $refreshInterval = (int) $this->option('refresh');

        if ($refreshInterval > 0) {
            $this->info("Auto-refreshing every {$refreshInterval} seconds. Press Ctrl+C to stop.");
            $this->newLine();

            while (true) {
                $this->displayStatus();
                sleep($refreshInterval);
                // Clear screen for next refresh
                $this->output->write("\033[2J\033[;H");
            }
        } else {
            $this->displayStatus();
        }

        return Command::SUCCESS;
    }

    /**
     * Display the connection pool status.
     */
    protected function displayStatus(): void
    {
        $this->displayHeader();
        $this->displayPoolMetrics();
        $this->displayConnectionStats();

        if ($this->option('detailed')) {
            $this->displayIdleConnections();
        }

        $this->displayLongRunningQueries();
        $this->displayHealthStatus();
    }

    /**
     * Display the header with timestamp.
     */
    protected function displayHeader(): void
    {
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║         DATABASE CONNECTION POOL STATUS                      ║');
        $this->info('╠══════════════════════════════════════════════════════════════╣');
        $this->info('║ Timestamp: '.now()->format('Y-m-d H:i:s').'                                  ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    /**
     * Display pool metrics with color coding.
     */
    protected function displayPoolMetrics(): void
    {
        $active = $this->monitor->getActiveConnections();
        $max = $this->monitor->getMaxConnections();
        $utilization = $this->monitor->getPoolUtilization();

        $this->line('<fg=cyan>Connection Pool Metrics</>');
        $this->line(str_repeat('─', 60));

        $this->line(sprintf('  Active Connections:     <fg=white>%d</> / <fg=white>%d</>', $active, $max));

        // Color-code utilization based on threshold
        $utilizationColor = $this->getUtilizationColor($utilization);
        $this->line(sprintf('  Pool Utilization:       <%s>%.2f%%</>', $utilizationColor, $utilization));

        // Visual progress bar
        $this->displayProgressBar($utilization);

        $this->newLine();
    }

    /**
     * Display connection statistics by state.
     */
    protected function displayConnectionStats(): void
    {
        $stats = $this->monitor->getConnectionStats();

        if (empty($stats)) {
            return;
        }

        $this->line('<fg=cyan>Connection Breakdown by State</>');
        $this->line(str_repeat('─', 60));

        $headers = ['State', 'Count', 'Max Duration (s)'];
        $rows = array_map(function ($stat) {
            return [
                $stat['state'],
                $stat['count'],
                number_format($stat['max_duration'], 2),
            ];
        }, $stats);

        $this->table($headers, $rows);
        $this->newLine();
    }

    /**
     * Display idle connections if detailed mode is enabled.
     */
    protected function displayIdleConnections(): void
    {
        $idle = $this->monitor->getIdleConnections();

        if (empty($idle)) {
            return;
        }

        $this->line('<fg=cyan>Idle Connections</> (<fg=yellow>'.count($idle).'</>)');
        $this->line(str_repeat('─', 60));

        $headers = ['PID', 'Username', 'Application', 'Idle Duration (s)'];
        $rows = array_slice(array_map(function ($conn) {
            return [
                $conn['pid'],
                $conn['username'],
                $conn['application_name'] ?? 'N/A',
                number_format($conn['idle_duration'], 2),
            ];
        }, $idle), 0, 10); // Limit to 10 for readability

        $this->table($headers, $rows);

        if (count($idle) > 10) {
            $this->line('  <fg=gray>... and '.(count($idle) - 10).' more</>');
        }

        $this->newLine();
    }

    /**
     * Display long-running queries.
     */
    protected function displayLongRunningQueries(): void
    {
        $threshold = (int) $this->option('long-running');
        $queries = $this->monitor->getLongRunningQueries($threshold);

        if (empty($queries)) {
            $this->line('<fg=green>No long-running queries detected</> (threshold: '.$threshold.'s)');
            $this->newLine();

            return;
        }

        $this->line('<fg=red>Long-Running Queries</> (<fg=red>'.count($queries).'</>) - Threshold: '.$threshold.'s');
        $this->line(str_repeat('─', 60));

        $headers = ['PID', 'Duration (s)', 'Query Preview'];
        $rows = array_map(function ($query) {
            return [
                $query['pid'],
                number_format($query['duration'], 2),
                substr($query['query_preview'], 0, 50).'...',
            ];
        }, $queries);

        $this->table($headers, $rows);
        $this->newLine();
    }

    /**
     * Display overall health status.
     */
    protected function displayHealthStatus(): void
    {
        $health = $this->monitor->checkHealth();

        $this->line('<fg=cyan>Overall Health Status</>');
        $this->line(str_repeat('─', 60));

        $statusColor = match ($health['status']) {
            'healthy' => 'green',
            'degraded' => 'yellow',
            'unhealthy' => 'red',
            default => 'white',
        };

        $statusIcon = match ($health['status']) {
            'healthy' => '✓',
            'degraded' => '⚠',
            'unhealthy' => '✗',
            default => '?',
        };

        $this->line(sprintf(
            '  Status: <%s>%s %s</>',
            $statusColor,
            $statusIcon,
            strtoupper($health['status'])
        ));

        $this->line(sprintf('  Message: %s', $health['message']));
        $this->line(sprintf('  Utilization: %.2f%%', $health['utilization']));
        $this->line(sprintf('  Long-Running Queries: %d', $health['long_running_queries']));

        $this->newLine();
    }

    /**
     * Get color based on utilization percentage.
     */
    protected function getUtilizationColor(float $utilization): string
    {
        if ($utilization > 90) {
            return 'fg=red';
        } elseif ($utilization > 70) {
            return 'fg=yellow';
        } else {
            return 'fg=green';
        }
    }

    /**
     * Display a visual progress bar for utilization.
     */
    protected function displayProgressBar(float $utilization): void
    {
        $barWidth = 50;
        $filled = (int) round(($utilization / 100) * $barWidth);
        $empty = $barWidth - $filled;

        $color = $utilization > 90 ? 'red' : ($utilization > 70 ? 'yellow' : 'green');

        $bar = str_repeat('█', $filled).str_repeat('░', $empty);

        $this->line(sprintf('  [<%s>%s</>]', "fg={$color}", $bar));
    }
}
