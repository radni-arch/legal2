<?php

namespace App\Console\Commands;

use App\Models\CourtDecision;
use App\Services\GraphDatabaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GraphSmokeTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'graph:smoke-test
                            {--cleanup : Clean up test data after running}
                            {--verbose : Show detailed output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run smoke tests on graph database integration using fixtures';

    protected GraphDatabaseService $graphDb;

    protected array $testIds = [];

    protected array $results = [];

    /**
     * Execute the console command.
     */
    public function handle(GraphDatabaseService $graphDb): int
    {
        $this->graphDb = $graphDb;
        $this->info('🔥 Starting Graph Database Smoke Tests');
        $this->newLine();

        // Step 1: Check Neo4j availability
        if (! $this->checkNeo4jAvailability()) {
            return self::FAILURE;
        }

        // Step 2: Load fixtures
        $fixtures = $this->loadFixtures();
        if (empty($fixtures)) {
            $this->error('❌ No fixtures found in tests/Fixtures/odluke/');

            return self::FAILURE;
        }

        $this->info("✅ Loaded {count($fixtures)} fixture(s)");
        $this->newLine();

        // Step 3: Create database rows
        if (! $this->createDatabaseRows($fixtures)) {
            $this->cleanup();

            return self::FAILURE;
        }

        // Step 4: Store in graph database
        if (! $this->storeInGraph($fixtures)) {
            $this->cleanup();

            return self::FAILURE;
        }

        // Step 5: Verify graph data
        if (! $this->verifyGraphData()) {
            $this->cleanup();

            return self::FAILURE;
        }

        // Step 6: Cleanup if requested
        if ($this->option('cleanup')) {
            $this->cleanup();
        } else {
            $this->warn('⚠️  Test data not cleaned up. Run with --cleanup to remove test data.');
            $this->info('Test IDs: '.implode(', ', $this->testIds));
        }

        $this->newLine();
        $this->displayResults();

        return self::SUCCESS;
    }

    protected function checkNeo4jAvailability(): bool
    {
        $this->task('Checking Neo4j availability', function () {
            if (! $this->graphDb->isAvailable()) {
                throw new \RuntimeException('Neo4j is not available');
            }

            $status = $this->graphDb->getHealthStatus();

            if ($this->option('verbose')) {
                $this->table(
                    ['Property', 'Value'],
                    [
                        ['Available', $status['available'] ? '✅ Yes' : '❌ No'],
                        ['Healthy', $status['healthy'] ? '✅ Yes' : '❌ No'],
                        ['URI', $status['uri'] ?? 'N/A'],
                        ['Version', $status['neo4j_version'] ?? 'N/A'],
                        ['Edition', $status['edition'] ?? 'N/A'],
                        ['Node Count', $status['node_count'] ?? 0],
                    ]
                );
            }

            return true;
        });

        return true;
    }

    protected function loadFixtures(): array
    {
        $fixturesPath = base_path('tests/Fixtures/odluke');
        $files = File::glob($fixturesPath.'/*.json');

        $fixtures = [];
        foreach ($files as $file) {
            try {
                $content = File::get($file);
                $data = json_decode($content, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->warn('⚠️  Skipping invalid JSON: '.basename($file));

                    continue;
                }

                $fixtures[] = $data;

                if ($this->option('verbose')) {
                    $this->line('  - Loaded: '.basename($file));
                }
            } catch (\Exception $e) {
                $this->warn('⚠️  Failed to load: '.basename($file).' - '.$e->getMessage());
            }
        }

        return $fixtures;
    }

    protected function createDatabaseRows(array $fixtures): bool
    {
        $this->info('📝 Creating database rows...');

        $created = 0;
        foreach ($fixtures as $meta) {
            try {
                $decision = $this->upsertDecisionFromMeta($meta);
                $this->testIds[] = (string) $decision->id;
                $created++;

                if ($this->option('verbose')) {
                    $this->line("  ✓ Created decision: {$meta['broj_odluke']} (ID: {$decision->id})");
                }
            } catch (\Exception $e) {
                $this->error("  ✗ Failed to create decision: {$meta['broj_odluke']} - ".$e->getMessage());

                return false;
            }
        }

        $this->results['database_rows_created'] = $created;
        $this->info("✅ Created {$created} database row(s)");
        $this->newLine();

        return true;
    }

    protected function storeInGraph(array $fixtures): bool
    {
        $this->info('📊 Storing in Neo4j graph...');

        $stored = 0;
        foreach ($fixtures as $meta) {
            $docId = $meta['ecli'] ?? 'test-'.Str::random(8);

            try {
                $this->graphDb->storeDecisionInGraph($meta, $docId);
                $stored++;

                if ($this->option('verbose')) {
                    $this->line("  ✓ Stored in graph: {$meta['broj_odluke']} (ECLI: {$docId})");
                }
            } catch (\Exception $e) {
                $this->error("  ✗ Failed to store in graph: {$meta['broj_odluke']} - ".$e->getMessage());

                return false;
            }
        }

        $this->results['graph_nodes_created'] = $stored;
        $this->info("✅ Stored {$stored} node(s) in graph");
        $this->newLine();

        return true;
    }

    protected function verifyGraphData(): bool
    {
        $this->info('🔍 Verifying graph data...');

        try {
            // Query 1: Count CourtDecisionDocument nodes
            $countResult = $this->graphDb->run(
                'MATCH (d:CourtDecisionDocument) WHERE d.ecli STARTS WITH "HR:" AND d.ecli CONTAINS "TEST" RETURN count(d) as count'
            );
            $nodeCount = $countResult->first()->get('count');

            if ($this->option('verbose')) {
                $this->line("  - Found {$nodeCount} test decision node(s)");
            }

            // Query 2: Verify node properties
            $nodesResult = $this->graphDb->run(
                'MATCH (d:CourtDecisionDocument) WHERE d.ecli STARTS WITH "HR:" AND d.ecli CONTAINS "TEST" RETURN d.ecli as ecli, d.case_number as case_number, d.court as court'
            );

            $verifiedNodes = 0;
            foreach ($nodesResult as $record) {
                $ecli = $record->get('ecli');
                $caseNumber = $record->get('case_number');
                $court = $record->get('court');

                if ($this->option('verbose')) {
                    $this->line("  ✓ Node: ECLI={$ecli}, Case={$caseNumber}, Court={$court}");
                }

                $verifiedNodes++;
            }

            // Query 3: Check for Court nodes
            $courtResult = $this->graphDb->run(
                'MATCH (d:CourtDecisionDocument)-[:DECIDED_BY]->(c:Court) WHERE d.ecli STARTS WITH "HR:" AND d.ecli CONTAINS "TEST" RETURN count(DISTINCT c) as count'
            );
            $courtCount = $courtResult->first()->get('count');

            if ($this->option('verbose')) {
                $this->line("  - Found {$courtCount} related court node(s)");
            }

            $this->results['nodes_verified'] = $verifiedNodes;
            $this->results['court_relationships'] = $courtCount;

            $this->info("✅ Verified {$verifiedNodes} node(s) with {$courtCount} court relationship(s)");
            $this->newLine();

            return true;
        } catch (\Exception $e) {
            $this->error('  ✗ Graph verification failed: '.$e->getMessage());

            return false;
        }
    }

    protected function cleanup(): void
    {
        if (empty($this->testIds)) {
            return;
        }

        $this->info('🧹 Cleaning up test data...');

        // Clean up database rows
        try {
            $deleted = DB::table('court_decisions')
                ->whereIn('id', $this->testIds)
                ->delete();

            if ($this->option('verbose')) {
                $this->line("  ✓ Deleted {$deleted} database row(s)");
            }
        } catch (\Exception $e) {
            $this->warn('  ⚠️  Failed to clean up database: '.$e->getMessage());
        }

        // Clean up graph nodes
        try {
            $this->graphDb->run(
                'MATCH (d:CourtDecisionDocument) WHERE d.ecli STARTS WITH "HR:" AND d.ecli CONTAINS "TEST" DETACH DELETE d'
            );

            if ($this->option('verbose')) {
                $this->line('  ✓ Deleted test node(s) from graph');
            }
        } catch (\Exception $e) {
            $this->warn('  ⚠️  Failed to clean up graph: '.$e->getMessage());
        }

        $this->info('✅ Cleanup complete');
        $this->newLine();
    }

    protected function displayResults(): void
    {
        $this->info('📊 Test Results Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Database rows created', $this->results['database_rows_created'] ?? 0],
                ['Graph nodes created', $this->results['graph_nodes_created'] ?? 0],
                ['Nodes verified', $this->results['nodes_verified'] ?? 0],
                ['Court relationships', $this->results['court_relationships'] ?? 0],
            ]
        );

        $this->info('✅ All smoke tests passed!');
    }

    protected function upsertDecisionFromMeta(array $m): CourtDecision
    {
        $caseNumber = trim((string) ($m['broj_odluke'] ?? '')) ?: null;
        $court = trim((string) ($m['sud'] ?? '')) ?: null;
        $ecli = trim((string) ($m['ecli'] ?? '')) ?: null;

        $attrs = [
            'title' => (string) ($m['vrsta_odluke'] ?? 'Sudska odluka'),
            'court' => $court,
            'jurisdiction' => 'HR',
            'judge' => null,
            'decision_date' => $m['datum_odluke'] ?? null,
            'publication_date' => $m['datum_objave'] ?? null,
            'decision_type' => $m['vrsta_odluke'] ?? null,
            'register' => $m['upisnik'] ?? null,
            'finality' => $m['pravomocnost'] ?? null,
            'ecli' => $ecli,
            'tags' => array_filter([
                $m['vrsta_odluke'] ?? null,
                $m['upisnik'] ?? null,
                $m['pravomocnost'] ?? null,
            ]),
            'description' => null,
        ];

        // Try to find existing by ECLI first, then by case_number + court
        $decision = null;
        if ($ecli) {
            $decision = CourtDecision::query()->where('ecli', $ecli)->first();
        }
        if (! $decision && $caseNumber && $court) {
            $decision = CourtDecision::query()->where('case_number', $caseNumber)->where('court', $court)->first();
        }

        if ($decision) {
            $decision->fill($attrs)->save();

            return $decision;
        }

        $payload = array_merge(['id' => (string) Str::ulid(), 'case_number' => $caseNumber], (array) $attrs);
        $decision = new CourtDecision($payload);
        $decision->save();

        return $decision;
    }
}
