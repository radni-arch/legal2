<?php

namespace App\Console\Commands;

use App\Services\GraphDatabaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Clean Orphaned Textract Nodes Command
 *
 * Finds and removes TextractDocument nodes in Neo4j where the corresponding
 * database record no longer exists (orphaned nodes).
 *
 * This can happen when:
 * - Documents are deleted from the database but not removed from Neo4j
 * - Database records are deleted without triggering unsync
 * - Manual database operations bypass the application layer
 *
 * Usage:
 *   php artisan textract:clean-orphaned-nodes                    # Delete orphans
 *   php artisan textract:clean-orphaned-nodes --dry-run          # Preview without deleting
 *   php artisan textract:clean-orphaned-nodes --batch=50         # Custom batch size
 */
class CleanOrphanedTextractNodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'textract:clean-orphaned-nodes
                            {--dry-run : Preview orphaned nodes without deleting them}
                            {--batch=100 : Batch size for processing nodes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up orphaned TextractDocument nodes in Neo4j (nodes without corresponding DB records)';

    protected GraphDatabaseService $graph;

    public function __construct(GraphDatabaseService $graph)
    {
        parent::__construct();
        $this->graph = $graph;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No nodes will be deleted');
            $this->newLine();
        }

        $this->info('🧹 Cleaning Orphaned TextractDocument Nodes...');
        $this->newLine();

        // Check if Neo4j is available
        if (! $this->graph->isAvailable()) {
            $this->error('❌ Neo4j is not available. Please check the connection.');
            Log::error('CleanOrphanedTextractNodes: Neo4j unavailable');

            return self::FAILURE;
        }

        $startTime = microtime(true);

        try {
            // Find orphaned nodes
            $this->info('Step 1: Finding orphaned nodes...');
            $orphanedNodeIds = $this->findOrphanedNodes($batchSize);

            $orphanCount = count($orphanedNodeIds);

            if ($orphanCount === 0) {
                $this->info('✅ No orphaned nodes found. Graph is clean!');

                return self::SUCCESS;
            }

            $this->warn("Found {$orphanCount} orphaned TextractDocument node(s)");
            $this->newLine();

            // Display sample orphaned IDs
            $this->displayOrphanedSample($orphanedNodeIds);
            $this->newLine();

            if ($isDryRun) {
                $this->info('DRY RUN: No nodes deleted. Run without --dry-run to delete orphaned nodes.');

                return self::SUCCESS;
            }

            // Delete orphaned nodes
            $this->info('Step 2: Deleting orphaned nodes...');
            $result = $this->deleteOrphanedNodes($orphanedNodeIds);

            $this->newLine();
            $this->displayResults($result, $orphanCount);

            $duration = round(microtime(true) - $startTime, 2);
            $this->info("✅ Cleanup completed in {$duration}s");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error during cleanup: {$e->getMessage()}");
            Log::error('CleanOrphanedTextractNodes failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Find orphaned TextractDocument nodes in Neo4j
     *
     * Returns array of node IDs that exist in Neo4j but not in the database
     *
     * @param  int  $batchSize  Size of batches for database lookups
     * @return array List of orphaned node IDs
     */
    protected function findOrphanedNodes(int $batchSize): array
    {
        if (! $this->graph->isAvailable()) {
            return [];
        }

        // Get all TextractDocument node IDs from Neo4j
        $query = 'MATCH (n:TextractDocument) RETURN n.id as id';
        $result = $this->graph->run($query, []);

        $neo4jNodeIds = [];
        foreach ($result as $record) {
            $neo4jNodeIds[] = $record->get('id');
        }

        if (empty($neo4jNodeIds)) {
            return [];
        }

        $this->line("  Found {$this->count($neo4jNodeIds)} TextractDocument nodes in Neo4j");

        // Check which nodes exist in the database (in batches)
        $existingIds = [];
        $batches = array_chunk($neo4jNodeIds, $batchSize);

        foreach ($batches as $batch) {
            $batchExisting = DB::table('textract_documents')
                ->whereIn('id', $batch)
                ->pluck('id')
                ->toArray();

            $existingIds = array_merge($existingIds, $batchExisting);
        }

        $this->line("  Found {$this->count($existingIds)} corresponding records in database");

        // Orphaned nodes = nodes in Neo4j but not in database
        $orphanedNodeIds = array_diff($neo4jNodeIds, $existingIds);

        return array_values($orphanedNodeIds);
    }

    /**
     * Delete orphaned nodes from Neo4j
     *
     * @param  array  $orphanedNodeIds  List of node IDs to delete
     * @return array Results with counts
     */
    protected function deleteOrphanedNodes(array $orphanedNodeIds): array
    {
        $deleted = 0;
        $errors = 0;
        $errorDetails = [];

        $progressBar = $this->output->createProgressBar(count($orphanedNodeIds));
        $progressBar->start();

        foreach ($orphanedNodeIds as $nodeId) {
            try {
                $this->graph->deleteNode('TextractDocument', $nodeId);
                $deleted++;

                Log::info('Deleted orphaned TextractDocument node', [
                    'node_id' => $nodeId,
                ]);
            } catch (\Exception $e) {
                $errors++;
                $errorDetails[] = [
                    'node_id' => $nodeId,
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to delete orphaned TextractDocument node', [
                    'node_id' => $nodeId,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        return [
            'deleted' => $deleted,
            'errors' => $errors,
            'error_details' => $errorDetails,
        ];
    }

    /**
     * Display sample of orphaned node IDs
     */
    protected function displayOrphanedSample(array $orphanedNodeIds): void
    {
        $sampleSize = min(10, count($orphanedNodeIds));
        $sample = array_slice($orphanedNodeIds, 0, $sampleSize);

        $this->line('Sample of orphaned node IDs:');
        foreach ($sample as $i => $nodeId) {
            $num = $i + 1;
            $this->line("  {$num}. {$nodeId}");
        }

        if (count($orphanedNodeIds) > $sampleSize) {
            $remaining = count($orphanedNodeIds) - $sampleSize;
            $this->line("  ... and {$remaining} more");
        }
    }

    /**
     * Display cleanup results
     */
    protected function displayResults(array $result, int $totalOrphans): void
    {
        $deleted = $result['deleted'];
        $errors = $result['errors'];

        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  CLEANUP SUMMARY');
        $this->info('═══════════════════════════════════════════════════════');
        $this->line("  Total orphaned nodes found: {$totalOrphans}");
        $this->line("  Deleted successfully: {$deleted}");

        if ($errors > 0) {
            $this->warn("  Errors encountered: {$errors}");
            $this->newLine();

            if (! empty($result['error_details'])) {
                $this->warn('Error details:');
                foreach (array_slice($result['error_details'], 0, 5) as $i => $error) {
                    $num = $i + 1;
                    $this->line("  {$num}. Node: {$error['node_id']} - {$error['error']}");
                }

                if ($errors > 5) {
                    $remaining = $errors - 5;
                    $this->line("  ... and {$remaining} more errors (see logs)");
                }
            }
        } else {
            $this->info('  Errors: 0');
        }

        $this->info('═══════════════════════════════════════════════════════');
    }

    /**
     * Helper to count array elements (for readability)
     */
    protected function count(array $items): int
    {
        return count($items);
    }
}
