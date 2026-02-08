<?php

namespace App\Console\Commands;

use App\Services\Graph\GraphEmbeddingService;
use Illuminate\Console\Command;

/**
 * Artisan Command: Generate Graph Embeddings (Sprint 4.5)
 *
 * Trains Node2Vec graph embeddings for court decisions based on citation structure.
 *
 * Usage:
 *   php artisan graph:generate-embeddings
 *   php artisan graph:generate-embeddings --clear
 *   php artisan graph:generate-embeddings --stats
 *
 * This command:
 * 1. Exports decision citation graph from Neo4j
 * 2. Trains Node2Vec model (128 dimensions)
 * 3. Stores embeddings in PostgreSQL
 *
 * Requirements:
 * - Python 3 with packages: neo4j, networkx, node2vec, psycopg2
 * - Neo4j running with decision citation data
 * - PostgreSQL with decision_graph_embeddings table
 */
class GenerateGraphEmbeddingsCommand extends Command
{
    protected $signature = 'graph:generate-embeddings
                            {--clear : Clear existing embeddings before training}
                            {--stats : Show statistics only, do not train}';

    protected $description = 'Generate Node2Vec graph embeddings for structural similarity search';

    public function __construct(
        protected GraphEmbeddingService $embeddingService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🧠 Graph Embeddings Generator (Node2Vec)');
        $this->newLine();

        // Option: Show statistics only
        if ($this->option('stats')) {
            return $this->showStatistics();
        }

        // Option: Clear existing embeddings
        if ($this->option('clear')) {
            if ($this->confirm('⚠️  This will delete all existing graph embeddings. Continue?')) {
                $count = $this->embeddingService->clearEmbeddings();
                $this->info("✓ Cleared {$count} existing embeddings");
                $this->newLine();
            } else {
                $this->warn('Cancelled');

                return self::SUCCESS;
            }
        }

        // Check prerequisites
        if (! $this->checkPrerequisites()) {
            return self::FAILURE;
        }

        // Display current statistics
        $this->showStatistics();
        $this->newLine();

        // Confirm training
        if (! $this->confirm('Start Node2Vec training? (This may take several minutes)', true)) {
            $this->warn('Training cancelled');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('🚀 Starting Node2Vec training...');
        $this->newLine();

        // Run training
        $result = $this->embeddingService->generateEmbeddings();

        $this->newLine();

        if ($result['success']) {
            $this->info('✅ Training completed successfully!');
            $this->newLine();
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Embeddings generated', $result['embedding_count']],
                    ['Duration', round($result['duration_ms'] / 1000, 2).'s'],
                    ['Embedding dimension', '128'],
                    ['Model', 'Node2Vec v1.0'],
                ]
            );

            // Show updated statistics
            $this->newLine();
            $this->showStatistics();

            return self::SUCCESS;
        } else {
            $this->error('❌ Training failed');
            $this->newLine();
            $this->error('Error: '.$result['error']);
            $this->newLine();

            if (isset($result['exit_code'])) {
                $this->error('Exit code: '.$result['exit_code']);
            }

            $this->newLine();
            $this->info('💡 Troubleshooting:');
            $this->line('  • Ensure Neo4j is running and accessible');
            $this->line('  • Verify Python dependencies: pip install neo4j networkx node2vec psycopg2');
            $this->line('  • Check logs for detailed error messages');

            return self::FAILURE;
        }
    }

    /**
     * Check if all prerequisites are met
     */
    protected function checkPrerequisites(): bool
    {
        $this->info('🔍 Checking prerequisites...');

        $allGood = true;

        // Check if Python script exists
        $pythonScript = base_path('scripts/train_graph_embeddings.py');
        if (! file_exists($pythonScript)) {
            $this->error('  ✗ Python training script not found');
            $this->line("    Expected: {$pythonScript}");
            $allGood = false;
        } else {
            $this->info('  ✓ Python training script found');
        }

        // Check if Neo4j is configured
        $neo4jConfig = config('neo4j');
        if (empty($neo4jConfig['uri']) || empty($neo4jConfig['password'])) {
            $this->error('  ✗ Neo4j configuration incomplete');
            $this->line('    Check .env: NEO4J_URI, NEO4J_PASSWORD');
            $allGood = false;
        } else {
            $this->info('  ✓ Neo4j configuration found');
        }

        // Check if PostgreSQL is configured
        $pgConfig = config('database.connections.pgsql');
        if (empty($pgConfig['host']) || empty($pgConfig['database'])) {
            $this->error('  ✗ PostgreSQL configuration incomplete');
            $allGood = false;
        } else {
            $this->info('  ✓ PostgreSQL configuration found');
        }

        $this->newLine();

        return $allGood;
    }

    /**
     * Display embedding statistics
     */
    protected function showStatistics(): int
    {
        $stats = $this->embeddingService->getStatistics();

        $this->info('📊 Graph Embedding Statistics:');
        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Decisions', number_format($stats['total_decisions'])],
                ['Embeddings Generated', number_format($stats['total_embeddings'])],
                ['Coverage', $stats['coverage_percentage'].'%'],
                ['Model Version', $stats['model_version'] ?? 'N/A'],
                ['Last Trained', $stats['last_trained_at'] ?? 'Never'],
            ]
        );

        return self::SUCCESS;
    }
}
