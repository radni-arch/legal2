<?php

namespace App\Console\Commands;

use App\Agents\DecisionDiscoveryAgent;
use Illuminate\Console\Command;

class DiscoverCourtDecisions extends Command
{
    protected $signature = 'decisions:discover
                            {--topics=5 : Number of topics to generate}
                            {--per-topic=50 : Decisions to evaluate per topic}
                            {--ingest=10 : Top decisions to ingest per topic}
                            {--threshold=70 : Relevance threshold (0-100)}
                            {--dry-run : Dry run - evaluate but don\'t ingest}';

    protected $description = 'Autonomously discover and ingest interesting court decisions';

    public function handle(DecisionDiscoveryAgent $agent): int
    {
        $this->info('🤖 Starting Autonomous Court Decision Discovery');
        $this->newLine();

        // Configure agent
        $agent->setTopicsPerRun((int) $this->option('topics'))
            ->setDecisionsPerTopic((int) $this->option('per-topic'))
            ->setIngestPerTopic((int) $this->option('ingest'))
            ->setRelevanceThreshold((float) $this->option('threshold'));

        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE: Decisions will be evaluated but not ingested');
            $this->newLine();
            // Set ingest per topic to 0 for dry run
            $agent->setIngestPerTopic(0);
        }

        $this->info('📋 Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Topics to generate', $this->option('topics')],
                ['Decisions per topic', $this->option('per-topic')],
                ['Top to ingest', $dryRun ? '0 (dry run)' : $this->option('ingest')],
                ['Relevance threshold', $this->option('threshold').'%'],
                ['Mode', $dryRun ? 'DRY RUN' : 'LIVE'],
            ]
        );
        $this->newLine();

        $this->info('🔍 Running discovery...');
        $this->newLine();

        try {
            $startTime = microtime(true);
            $stats = $agent->discover();
            $duration = microtime(true) - $startTime;

            $this->newLine();
            $this->info('✅ Discovery Completed in '.round($duration, 2).' seconds');
            $this->newLine();

            $this->info('📊 Summary Statistics:');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Topics generated', $stats['topics_generated']],
                    ['Decisions evaluated', $stats['decisions_evaluated']],
                    ['Decisions ingested', $dryRun ? '0 (dry run)' : $stats['decisions_ingested']],
                    ['Errors', count($stats['errors'])],
                ]
            );

            if (! empty($stats['errors'])) {
                $this->newLine();
                $this->error('❌ Errors encountered:');
                foreach ($stats['errors'] as $error) {
                    $this->line("  - {$error['topic']}: {$error['error']}");
                }
            }

            if ($dryRun) {
                $this->newLine();
                $this->comment('💡 This was a dry run. Re-run without --dry-run to actually ingest decisions.');
            }

            $this->newLine();

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error('❌ Discovery failed: '.$e->getMessage());
            $this->newLine();

            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            } else {
                $this->comment('Run with -v for full stack trace');
            }

            return self::FAILURE;
        }
    }
}
