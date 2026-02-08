<?php

namespace App\Console\Commands;

use App\Services\CircuitBreaker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Circuit Breaker Status Command
 *
 * Displays the current status of all circuit breakers including failure counts,
 * states, and retry times for monitoring and debugging.
 */
class CircuitBreakerStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'circuit-breaker:status
                            {--service= : Show status for a specific service only}
                            {--watch : Watch mode - continuously monitor status}
                            {--interval=5 : Refresh interval in seconds for watch mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display circuit breaker status for all services';

    /**
     * Services to monitor
     */
    private array $services = ['openai', 'eoglasna', 'neo4j', 'aws_textract'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $service = $this->option('service');
        $watch = $this->option('watch');
        $interval = (int) $this->option('interval');

        if ($service) {
            $this->services = [$service];
        }

        if ($watch) {
            $this->info('Starting circuit breaker status watch mode...');
            $this->info("Refresh interval: {$interval} seconds");
            $this->info('Press Ctrl+C to exit');
            $this->newLine();

            while (true) {
                $this->displayStatus();
                sleep($interval);
                // Clear screen for watch mode
                if (PHP_OS_FAMILY !== 'Windows') {
                    system('clear');
                }
            }
        } else {
            $this->displayStatus();
        }

        return Command::SUCCESS;
    }

    /**
     * Display circuit breaker status
     */
    private function displayStatus(): void
    {
        $this->info('Circuit Breaker Status - '.now()->format('Y-m-d H:i:s'));
        $this->newLine();

        $tableData = [];

        foreach ($this->services as $service) {
            try {
                $circuitBreaker = new CircuitBreaker($service);
                $status = $circuitBreaker->getStatus();

                $stateColor = match ($status['state']) {
                    'closed' => 'info',
                    'half_open' => 'comment',
                    'open' => 'error',
                    default => 'line',
                };

                $retryInfo = '';
                if ($status['state'] === 'open' && $status['last_failure']) {
                    $lastFailure = strtotime($status['last_failure']);
                    $elapsed = time() - $lastFailure;
                    $timeout = 60; // Default timeout
                    $remaining = max(0, $timeout - $elapsed);
                    $retryInfo = $remaining > 0 ? "Retry in {$remaining}s" : 'Can retry now';
                } elseif ($status['state'] === 'half_open') {
                    $retryInfo = 'Testing...';
                } else {
                    $retryInfo = 'N/A';
                }

                $tableData[] = [
                    'service' => strtoupper($service),
                    'state' => "<{$stateColor}>".strtoupper($status['state'])."</{$stateColor}>",
                    'failures' => "{$status['failure_count']}/{$status['failure_threshold']}",
                    'successes' => $status['state'] === 'half_open'
                        ? "{$status['success_count']}/{$status['success_threshold']}"
                        : 'N/A',
                    'last_failure' => $status['last_failure']
                        ? \Carbon\Carbon::parse($status['last_failure'])->diffForHumans()
                        : 'Never',
                    'retry' => $retryInfo,
                ];
            } catch (\Throwable $e) {
                $tableData[] = [
                    'service' => strtoupper($service),
                    'state' => '<error>ERROR</error>',
                    'failures' => 'N/A',
                    'successes' => 'N/A',
                    'last_failure' => 'N/A',
                    'retry' => $e->getMessage(),
                ];
            }
        }

        $this->table(
            ['Service', 'State', 'Failures', 'Successes', 'Last Failure', 'Retry Info'],
            $tableData
        );

        // Display recent events summary
        $this->displayRecentEvents();
    }

    /**
     * Display summary of recent circuit breaker events
     */
    private function displayRecentEvents(): void
    {
        try {
            $recentEvents = DB::table('circuit_breaker_events')
                ->where('created_at', '>=', now()->subHour())
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            if ($recentEvents->isNotEmpty()) {
                $this->newLine();
                $this->info('Recent Events (Last Hour):');

                $eventData = [];
                foreach ($recentEvents as $event) {
                    $eventData[] = [
                        'time' => \Carbon\Carbon::parse($event->created_at)->format('H:i:s'),
                        'service' => strtoupper($event->service),
                        'state' => strtoupper($event->state),
                        'failures' => $event->failure_count,
                    ];
                }

                $this->table(
                    ['Time', 'Service', 'State', 'Failures'],
                    $eventData
                );
            }
        } catch (\Throwable $e) {
            // Database table might not exist yet
            $this->comment('No recent events available (database table may not exist yet)');
        }
    }
}
