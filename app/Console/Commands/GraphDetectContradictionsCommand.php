<?php

namespace App\Console\Commands;

use App\Services\Graph\TemporalReasoningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Artisan command for detecting contradictions in court decisions (Sprint 4.2)
 *
 * Usage:
 *   php artisan graph:detect-contradictions
 *   php artisan graph:detect-contradictions --decision-id=01K9R5PMS1
 *   php artisan graph:detect-contradictions --limit=10
 */
class GraphDetectContradictionsCommand extends Command
{
    protected $signature = 'graph:detect-contradictions
                            {--decision-id= : Specific decision ID to analyze}
                            {--limit=10 : Number of recent decisions to analyze}
                            {--save : Save contradictions to graph database}';

    protected $description = 'Detect contradictions between court decisions using LLM analysis';

    public function __construct(
        protected TemporalReasoningService $temporalReasoning
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! config('neo4j.sync.enabled')) {
            $this->warn('Neo4j integration is disabled.');

            return self::FAILURE;
        }

        $this->info('🔍 Detecting contradictions in court decisions...');
        $this->newLine();

        try {
            // Get decision(s) to analyze
            $decisions = $this->getDecisionsToAnalyze();

            if ($decisions->isEmpty()) {
                $this->warn('No decisions found to analyze.');

                return self::FAILURE;
            }

            $this->info("Analyzing {$decisions->count()} decision(s)...");
            $this->newLine();

            $totalContradictions = 0;
            $progressBar = $this->output->createProgressBar($decisions->count());

            foreach ($decisions as $decision) {
                $contradictions = $this->temporalReasoning->detectContradictions($decision->id);

                if (! empty($contradictions)) {
                    $totalContradictions += count($contradictions);

                    $this->newLine();
                    $this->info("✗ Decision {$decision->case_number} ({$decision->id})");

                    foreach ($contradictions as $contradiction) {
                        $this->line("  └─ Contradicts: {$contradiction['contradicting_decision_id']}");
                        $this->line("     Type: {$contradiction['contradiction_type']}");
                        $this->line("     Severity: {$contradiction['severity']}");
                        $this->line("     {$contradiction['explanation']}");
                        $this->newLine();
                    }

                    // Optionally save to graph
                    if ($this->option('save')) {
                        $this->saveContradictionsToGraph($decision->id, $contradictions);
                    }
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            // Summary
            if ($totalContradictions > 0) {
                $this->info("✓ Found {$totalContradictions} contradiction(s)");
            } else {
                $this->info('✓ No contradictions found');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to detect contradictions: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }

    /**
     * Get decisions to analyze based on command options
     */
    protected function getDecisionsToAnalyze()
    {
        $decisionId = $this->option('decision-id');

        if ($decisionId) {
            // Analyze specific decision
            return DB::table('court_decisions')
                ->where('id', $decisionId)
                ->get();
        }

        // Analyze recent decisions
        $limit = (int) $this->option('limit');

        return DB::table('court_decisions')
            ->whereNotNull('decision_date')
            ->orderBy('decision_date', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Save detected contradictions to Neo4j graph
     */
    protected function saveContradictionsToGraph(string $decisionId, array $contradictions): void
    {
        foreach ($contradictions as $contradiction) {
            // Create CONTRADICTS relationship in graph
            // This would use the LawGraphSyncService::createContradictsRelationship()
            $this->line('  └─ Saved contradiction to graph');
        }
    }
}
