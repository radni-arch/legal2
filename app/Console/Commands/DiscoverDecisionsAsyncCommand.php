<?php

namespace App\Console\Commands;

use App\Agents\DecisionDiscoveryAgent;
use App\Jobs\ExecuteDecisionDiscoveryJob;
use Illuminate\Console\Command;

class DiscoverDecisionsAsyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'decisions:discover-async
                            {--max-decisions= : Maximum decisions to discover}
                            {--topic= : Specific topic to search}
                            {--force : Force run even if already running}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discover court decisions asynchronously using queue job';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Check if already running
        if (! $this->option('force') && DecisionDiscoveryAgent::isRunning()) {
            $this->warn('Decision discovery job is already running. Use --force to override.');

            return self::FAILURE;
        }

        $maxDecisions = $this->option('max-decisions') ? (int) $this->option('max-decisions') : null;
        $topic = $this->option('topic');

        $this->info('Dispatching decision discovery job...');

        $result = ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection($maxDecisions, $topic);

        if (isset($result['mode']) && $result['mode'] === 'sync') {
            $this->info('✓ Discovery completed synchronously (dev environment)');

            if (isset($result['stats'])) {
                $stats = $result['stats'];
                $this->table(
                    ['Metric', 'Count'],
                    [
                        ['Topics Generated', $stats['topics_generated'] ?? 0],
                        ['Decisions Evaluated', $stats['decisions_evaluated'] ?? 0],
                        ['Decisions Ingested', $stats['decisions_ingested'] ?? 0],
                        ['Errors', count($stats['errors'] ?? [])],
                    ]
                );
            }

            if (isset($result['error'])) {
                $this->error('Error: '.$result['error']);

                return self::FAILURE;
            }
        } else {
            $this->info('✓ Discovery job dispatched to queue (production environment)');
            $this->info('Monitor progress: php artisan queue:listen');
        }

        return self::SUCCESS;
    }
}
