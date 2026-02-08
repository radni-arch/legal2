<?php

namespace App\Console\Commands;

use App\Agents\DecisionDiscoveryAgent;
use App\Models\AgentInsightEvent;
use App\Models\CourtDecision;
use App\Services\GraphDatabaseService;
use App\Services\Odluke\OdlukeIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * End-to-end discovery pipeline dry run command.
 *
 * Executes the full pipeline:
 * 1. Discovery (court decision discovery agent)
 * 2. Ingestion with graph sync
 * 3. Insight fetch
 * 4. Metrics summary
 *
 * This command is designed for staging validation and operational testing.
 */
class DiscoveryEndToEndCommand extends Command
{
    protected $signature = 'discovery:e2e
                            {--topics=3 : Number of topics to generate}
                            {--per-topic=20 : Decisions to evaluate per topic}
                            {--ingest=5 : Top decisions to ingest per topic}
                            {--threshold=70 : Relevance threshold (0-100)}
                            {--skip-discovery : Skip discovery, use existing decisions}
                            {--skip-graph : Skip graph sync}
                            {--no-log : Do not write to log file}';

    protected $description = 'Execute full discovery pipeline (discovery → ingestion → graph → insights) with metrics';

    protected array $metrics = [];

    protected string $logFile = '';

    protected float $startTime = 0;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(
        DecisionDiscoveryAgent $discoveryAgent,
        GraphDatabaseService $graphDb,
        OdlukeIngestService $ingestService
    ): int {
        $this->startTime = microtime(true);
        $this->metrics = [
            'start_time' => now()->toIso8601String(),
            'discovery' => [],
            'ingestion' => [],
            'graph' => [],
            'insights' => [],
            'errors' => [],
        ];

        // Set up logging
        if (! $this->option('no-log')) {
            $this->setupLogging();
        }

        $this->displayHeader();

        try {
            // Step 1: Discovery
            if (! $this->option('skip-discovery')) {
                $this->executeDiscovery($discoveryAgent);
            } else {
                $this->warn('⏭️  Skipping discovery (using existing decisions)');
                $this->metrics['discovery']['skipped'] = true;
            }

            // Step 2: Ingestion with Graph Sync
            $this->executeIngestion($ingestService, ! $this->option('skip-graph'));

            // Step 3: Graph Verification
            if (! $this->option('skip-graph')) {
                $this->verifyGraph($graphDb);
            } else {
                $this->warn('⏭️  Skipping graph verification');
                $this->metrics['graph']['skipped'] = true;
            }

            // Step 4: Insight Fetch
            $this->fetchInsights();

            // Step 5: Display Summary
            $this->displaySummary();

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Pipeline failed: '.$e->getMessage());
            $this->metrics['errors'][] = [
                'stage' => 'pipeline',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];

            Log::error('Discovery E2E pipeline failed', [
                'error' => $e->getMessage(),
                'metrics' => $this->metrics,
            ]);

            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        } finally {
            $this->metrics['end_time'] = now()->toIso8601String();
            $this->metrics['total_duration_seconds'] = round(microtime(true) - $this->startTime, 2);

            if (! $this->option('no-log')) {
                $this->writeMetricsToLog();
            }
        }
    }

    protected function displayHeader(): void
    {
        $this->info('🚀 Discovery End-to-End Pipeline');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        $this->info('📋 Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Topics', $this->option('topics')],
                ['Decisions per topic', $this->option('per-topic')],
                ['To ingest per topic', $this->option('ingest')],
                ['Relevance threshold', $this->option('threshold').'%'],
                ['Skip discovery', $this->option('skip-discovery') ? 'Yes' : 'No'],
                ['Skip graph', $this->option('skip-graph') ? 'Yes' : 'No'],
                ['Log file', $this->logFile ?: 'Disabled'],
            ]
        );
        $this->newLine();
    }

    protected function executeDiscovery(DecisionDiscoveryAgent $agent): void
    {
        $this->info('🔍 Step 1: Discovery');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        $discoveryStart = microtime(true);

        // Configure agent
        $agent->setTopicsPerRun((int) $this->option('topics'))
            ->setDecisionsPerTopic((int) $this->option('per-topic'))
            ->setIngestPerTopic((int) $this->option('ingest'))
            ->setRelevanceThreshold((float) $this->option('threshold'));

        $this->line('Running autonomous discovery...');

        try {
            $stats = $agent->discover();

            $discoveryDuration = microtime(true) - $discoveryStart;

            $this->metrics['discovery'] = [
                'duration_seconds' => round($discoveryDuration, 2),
                'topics_generated' => $stats['topics_generated'],
                'decisions_evaluated' => $stats['decisions_evaluated'],
                'decisions_ingested' => $stats['decisions_ingested'],
                'errors' => $stats['errors'] ?? [],
            ];

            $this->info('✅ Discovery completed in '.round($discoveryDuration, 2).'s');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Topics generated', $stats['topics_generated']],
                    ['Decisions evaluated', $stats['decisions_evaluated']],
                    ['Decisions ingested', $stats['decisions_ingested']],
                ]
            );

            if (! empty($stats['errors'])) {
                $this->warn('⚠️  Some errors occurred during discovery');
                foreach ($stats['errors'] as $error) {
                    $this->line("  - {$error['topic']}: {$error['error']}");
                }
            }

            $this->newLine();

        } catch (\Exception $e) {
            $this->error('❌ Discovery failed: '.$e->getMessage());
            $this->metrics['discovery']['error'] = $e->getMessage();
            $this->metrics['errors'][] = [
                'stage' => 'discovery',
                'error' => $e->getMessage(),
            ];
            throw $e;
        }
    }

    protected function executeIngestion(OdlukeIngestService $ingestService, bool $syncGraph): void
    {
        $this->info('📥 Step 2: Ingestion'.($syncGraph ? ' (with graph sync)' : ''));
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        $ingestStart = microtime(true);

        try {
            // Get recently added decisions (last 5 minutes)
            $recentDecisions = CourtDecision::where('created_at', '>=', now()->subMinutes(5))
                ->orderBy('created_at', 'desc')
                ->get();

            $this->line("Found {$recentDecisions->count()} recent decisions to process");

            $processed = 0;
            $succeeded = 0;
            $failed = 0;

            foreach ($recentDecisions as $decision) {
                $processed++;
                $this->line("Processing {$processed}/{$recentDecisions->count()}: {$decision->ecli}");

                try {
                    // Ingest with embeddings
                    $result = $ingestService->ingestByIds(
                        [$decision->decision_id],
                        [
                            'sync_graph' => $syncGraph,
                            'force' => false,
                        ]
                    );

                    if ($result['success']) {
                        $succeeded++;
                    } else {
                        $failed++;
                        $this->warn('  ⚠️  Failed: '.($result['error'] ?? 'Unknown error'));
                    }

                } catch (\Exception $e) {
                    $failed++;
                    $this->warn('  ⚠️  Exception: '.$e->getMessage());
                }
            }

            $ingestDuration = microtime(true) - $ingestStart;

            $this->metrics['ingestion'] = [
                'duration_seconds' => round($ingestDuration, 2),
                'decisions_processed' => $processed,
                'succeeded' => $succeeded,
                'failed' => $failed,
                'graph_sync_enabled' => $syncGraph,
            ];

            $this->info('✅ Ingestion completed in '.round($ingestDuration, 2).'s');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Decisions processed', $processed],
                    ['Succeeded', $succeeded],
                    ['Failed', $failed],
                    ['Graph sync', $syncGraph ? 'Enabled' : 'Disabled'],
                ]
            );
            $this->newLine();

        } catch (\Exception $e) {
            $this->error('❌ Ingestion failed: '.$e->getMessage());
            $this->metrics['ingestion']['error'] = $e->getMessage();
            $this->metrics['errors'][] = [
                'stage' => 'ingestion',
                'error' => $e->getMessage(),
            ];
            throw $e;
        }
    }

    protected function verifyGraph(GraphDatabaseService $graphDb): void
    {
        $this->info('🌐 Step 3: Graph Verification');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        $graphStart = microtime(true);

        try {
            // Check Neo4j availability
            if (! $graphDb->isAvailable()) {
                throw new \Exception('Neo4j is not available');
            }

            // Get node counts
            $decisionCount = $graphDb->countNodes('Decision');
            $lawCount = $graphDb->countNodes('Law');
            $relationshipCount = $graphDb->countRelationships();

            // Get recently added nodes (estimate)
            $recentDecisionNodes = CourtDecision::where('created_at', '>=', now()->subMinutes(5))->count();

            $graphDuration = microtime(true) - $graphStart;

            $this->metrics['graph'] = [
                'duration_seconds' => round($graphDuration, 2),
                'total_decision_nodes' => $decisionCount,
                'total_law_nodes' => $lawCount,
                'total_relationships' => $relationshipCount,
                'recent_nodes_added_estimate' => $recentDecisionNodes,
                'neo4j_available' => true,
            ];

            $this->info('✅ Graph verification completed in '.round($graphDuration, 2).'s');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Decision nodes', $decisionCount],
                    ['Total Law nodes', $lawCount],
                    ['Total relationships', $relationshipCount],
                    ['Recent nodes (estimate)', $recentDecisionNodes],
                ]
            );
            $this->newLine();

        } catch (\Exception $e) {
            $this->warn('⚠️  Graph verification failed: '.$e->getMessage());
            $this->metrics['graph']['error'] = $e->getMessage();
            $this->metrics['graph']['neo4j_available'] = false;
            $this->metrics['errors'][] = [
                'stage' => 'graph',
                'error' => $e->getMessage(),
            ];
            // Don't throw - graph issues shouldn't fail the whole pipeline
        }
    }

    protected function fetchInsights(): void
    {
        $this->info('💡 Step 4: Insight Fetch');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        $insightStart = microtime(true);

        try {
            // Fetch recent insight events (last 10 minutes)
            $recentInsights = AgentInsightEvent::where('created_at', '>=', now()->subMinutes(10))
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            $insightDuration = microtime(true) - $insightStart;

            // Categorize by severity
            $bySeverity = $recentInsights->groupBy('severity');

            $this->metrics['insights'] = [
                'duration_seconds' => round($insightDuration, 2),
                'total_recent_insights' => $recentInsights->count(),
                'by_severity' => [
                    'info' => $bySeverity->get('info', collect())->count(),
                    'warning' => $bySeverity->get('warning', collect())->count(),
                    'critical' => $bySeverity->get('critical', collect())->count(),
                ],
                'unique_agents' => $recentInsights->pluck('agent_name')->unique()->count(),
                'unique_objectives' => $recentInsights->pluck('objective')->unique()->filter()->count(),
            ];

            $this->info('✅ Insight fetch completed in '.round($insightDuration, 2).'s');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Recent insights (10min)', $recentInsights->count()],
                    ['Info severity', $bySeverity->get('info', collect())->count()],
                    ['Warning severity', $bySeverity->get('warning', collect())->count()],
                    ['Critical severity', $bySeverity->get('critical', collect())->count()],
                    ['Unique agents', $recentInsights->pluck('agent_name')->unique()->count()],
                ]
            );

            // Show sample insights
            if ($recentInsights->isNotEmpty() && $this->output->isVerbose()) {
                $this->newLine();
                $this->info('Sample insights:');
                foreach ($recentInsights->take(3) as $insight) {
                    $this->line(sprintf(
                        '  [%s] %s: %s',
                        $insight->severity,
                        $insight->agent_name,
                        Str::limit($insight->insight, 80)
                    ));
                }
            }

            $this->newLine();

        } catch (\Exception $e) {
            $this->warn('⚠️  Insight fetch failed: '.$e->getMessage());
            $this->metrics['insights']['error'] = $e->getMessage();
            $this->metrics['errors'][] = [
                'stage' => 'insights',
                'error' => $e->getMessage(),
            ];
            // Don't throw - insight fetch issues shouldn't fail the whole pipeline
        }
    }

    protected function displaySummary(): void
    {
        $totalDuration = microtime(true) - $this->startTime;

        $this->newLine();
        $this->info('📊 Pipeline Summary');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        $summaryData = [];

        // Discovery summary
        if (isset($this->metrics['discovery']['topics_generated'])) {
            $summaryData[] = ['Discovery', 'Topics', $this->metrics['discovery']['topics_generated']];
            $summaryData[] = ['Discovery', 'Decisions evaluated', $this->metrics['discovery']['decisions_evaluated']];
            $summaryData[] = ['Discovery', 'Decisions ingested', $this->metrics['discovery']['decisions_ingested']];
            $summaryData[] = ['Discovery', 'Duration', $this->metrics['discovery']['duration_seconds'].'s'];
        }

        // Ingestion summary
        if (isset($this->metrics['ingestion']['decisions_processed'])) {
            $summaryData[] = ['Ingestion', 'Processed', $this->metrics['ingestion']['decisions_processed']];
            $summaryData[] = ['Ingestion', 'Succeeded', $this->metrics['ingestion']['succeeded']];
            $summaryData[] = ['Ingestion', 'Failed', $this->metrics['ingestion']['failed']];
            $summaryData[] = ['Ingestion', 'Duration', $this->metrics['ingestion']['duration_seconds'].'s'];
        }

        // Graph summary
        if (isset($this->metrics['graph']['total_decision_nodes'])) {
            $summaryData[] = ['Graph', 'Decision nodes', $this->metrics['graph']['total_decision_nodes']];
            $summaryData[] = ['Graph', 'Relationships', $this->metrics['graph']['total_relationships']];
            $summaryData[] = ['Graph', 'Recent nodes', $this->metrics['graph']['recent_nodes_added_estimate']];
        }

        // Insights summary
        if (isset($this->metrics['insights']['total_recent_insights'])) {
            $summaryData[] = ['Insights', 'Total recent', $this->metrics['insights']['total_recent_insights']];
            $summaryData[] = ['Insights', 'Critical', $this->metrics['insights']['by_severity']['critical']];
            $summaryData[] = ['Insights', 'Unique agents', $this->metrics['insights']['unique_agents']];
        }

        $summaryData[] = ['Total', 'Duration', round($totalDuration, 2).'s'];
        $summaryData[] = ['Total', 'Errors', count($this->metrics['errors'])];

        $this->table(['Stage', 'Metric', 'Value'], $summaryData);

        if (! empty($this->metrics['errors'])) {
            $this->newLine();
            $this->error('❌ Errors encountered:');
            foreach ($this->metrics['errors'] as $error) {
                $this->line("  [{$error['stage']}] {$error['error']}");
            }
        }

        $this->newLine();
        $this->info('✅ End-to-end pipeline completed');

        if (! $this->option('no-log')) {
            $this->info("📄 Full metrics saved to: {$this->logFile}");
        }
    }

    protected function setupLogging(): void
    {
        $logsDir = storage_path('logs');
        if (! File::exists($logsDir)) {
            File::makeDirectory($logsDir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_His');
        $this->logFile = $logsDir."/discovery-e2e-{$timestamp}.log";

        // Write initial log entry
        File::put($this->logFile, "Discovery E2E Pipeline Log\n");
        File::append($this->logFile, 'Started: '.now()->toDateTimeString()."\n");
        File::append($this->logFile, str_repeat('=', 80)."\n\n");
    }

    protected function writeMetricsToLog(): void
    {
        if (empty($this->logFile)) {
            return;
        }

        File::append($this->logFile, "\n\nMetrics:\n");
        File::append($this->logFile, str_repeat('=', 80)."\n");
        File::append($this->logFile, json_encode($this->metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::append($this->logFile, "\n\n");
        File::append($this->logFile, 'Completed: '.now()->toDateTimeString()."\n");
    }
}
