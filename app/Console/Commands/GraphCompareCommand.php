<?php

namespace App\Console\Commands;

use App\Services\GraphDatabaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Graph Diff QA Tool
 *
 * Captures node/relationship counts from Neo4j and compares against baselines
 * to detect significant changes in graph structure. Useful for:
 * - QA validation after data imports
 * - Regression testing for graph operations
 * - Monitoring graph growth over time
 */
class GraphCompareCommand extends Command
{
    protected $signature = 'graph:compare
                            {--baseline= : Name of baseline snapshot to compare against}
                            {--save= : Save current state as named snapshot}
                            {--threshold=10 : Variance threshold percentage (default: 10%)}
                            {--show-diff : Show detailed differences}
                            {--list : List available snapshots}';

    protected $description = 'Compare Neo4j graph state against baseline snapshots';

    protected string $snapshotDir;

    protected array $currentState = [];

    protected float $threshold;

    public function __construct()
    {
        parent::__construct();
        $this->snapshotDir = storage_path('graph-snapshots');
    }

    public function handle(GraphDatabaseService $graphDb): int
    {
        // Ensure snapshot directory exists
        if (! File::exists($this->snapshotDir)) {
            File::makeDirectory($this->snapshotDir, 0755, true);
        }

        $this->threshold = (float) $this->option('threshold');

        // Handle --list option
        if ($this->option('list')) {
            return $this->listSnapshots();
        }

        // Check Neo4j availability
        if (! $graphDb->isAvailable()) {
            $this->error('❌ Neo4j is not available');
            $this->line('   Please check your Neo4j connection settings');

            return self::FAILURE;
        }

        // Capture current state
        $this->info('📊 Capturing current graph state...');
        $this->currentState = $this->captureState($graphDb);

        $this->displayState($this->currentState, 'Current State');

        // Handle --save option
        if ($saveName = $this->option('save')) {
            return $this->saveSnapshot($saveName);
        }

        // Handle --baseline comparison
        if ($baselineName = $this->option('baseline')) {
            return $this->compareWithBaseline($baselineName);
        }

        // No action specified
        $this->newLine();
        $this->comment('💡 Tip: Use --save to create a snapshot, or --baseline to compare');
        $this->comment('   Example: graph:compare --save production-2024-11-01');
        $this->comment('   Example: graph:compare --baseline production-2024-11-01');

        return self::SUCCESS;
    }

    protected function captureState(GraphDatabaseService $graphDb): array
    {
        $state = [
            'timestamp' => now()->toIso8601String(),
            'nodes' => [],
            'relationships' => [],
            'totals' => [],
        ];

        // Capture node counts by label
        $nodeLabels = ['Decision', 'Law', 'Article', 'Court', 'Judge'];
        foreach ($nodeLabels as $label) {
            try {
                $count = $graphDb->countNodes($label);
                $state['nodes'][$label] = $count;
            } catch (\Exception $e) {
                $state['nodes'][$label] = 0;
            }
        }

        // Capture relationship counts by type
        $relationshipTypes = [
            'CITES_LAW',
            'CITES_DECISION',
            'CITES_ARTICLE',
            'DECIDED_BY',
            'PART_OF',
            'REFERENCES',
        ];

        foreach ($relationshipTypes as $type) {
            try {
                $count = $graphDb->countRelationships($type);
                $state['relationships'][$type] = $count;
            } catch (\Exception $e) {
                $state['relationships'][$type] = 0;
            }
        }

        // Capture totals
        try {
            $state['totals']['nodes'] = $graphDb->countNodes();
            $state['totals']['relationships'] = $graphDb->countRelationships();
        } catch (\Exception $e) {
            $state['totals']['nodes'] = array_sum($state['nodes']);
            $state['totals']['relationships'] = array_sum($state['relationships']);
        }

        return $state;
    }

    protected function displayState(array $state, string $title): void
    {
        $this->newLine();
        $this->info($title);
        $this->info(str_repeat('─', 60));

        if (isset($state['timestamp'])) {
            $this->line('Timestamp: '.$state['timestamp']);
        }

        $this->newLine();
        $this->info('📦 Nodes:');
        $nodeData = [];
        foreach ($state['nodes'] as $label => $count) {
            $nodeData[] = [$label, number_format($count)];
        }
        $this->table(['Label', 'Count'], $nodeData);

        $this->newLine();
        $this->info('🔗 Relationships:');
        $relData = [];
        foreach ($state['relationships'] as $type => $count) {
            $relData[] = [$type, number_format($count)];
        }
        $this->table(['Type', 'Count'], $relData);

        $this->newLine();
        $this->info('📊 Totals:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Nodes', number_format($state['totals']['nodes'])],
                ['Total Relationships', number_format($state['totals']['relationships'])],
            ]
        );
    }

    protected function saveSnapshot(string $name): int
    {
        $filename = $this->getSnapshotFilename($name);

        try {
            File::put($filename, json_encode($this->currentState, JSON_PRETTY_PRINT));
            $this->newLine();
            $this->info("✅ Snapshot saved: {$name}");
            $this->line("   Location: {$filename}");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Failed to save snapshot: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function compareWithBaseline(string $baselineName): int
    {
        $filename = $this->getSnapshotFilename($baselineName);

        if (! File::exists($filename)) {
            $this->error("❌ Baseline snapshot not found: {$baselineName}");
            $this->line("   Expected: {$filename}");
            $this->newLine();
            $this->comment('💡 Available snapshots:');
            $this->listSnapshots();

            return self::FAILURE;
        }

        try {
            $baseline = json_decode(File::get($filename), true);
        } catch (\Exception $e) {
            $this->error("❌ Failed to load baseline: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->displayState($baseline, "Baseline: {$baselineName}");

        // Calculate differences
        $differences = $this->calculateDifferences($baseline, $this->currentState);

        // Display differences
        $this->displayDifferences($differences);

        // Check for significant variances
        $hasSignificantVariance = $this->checkVariances($differences);

        if ($hasSignificantVariance) {
            $this->newLine();
            $this->error("❌ SIGNIFICANT VARIANCE DETECTED (>{$this->threshold}%)");
            $this->line('   Graph state has changed significantly from baseline');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('✅ No significant variance detected');
        $this->line("   All changes are within {$this->threshold}% threshold");

        return self::SUCCESS;
    }

    protected function calculateDifferences(array $baseline, array $current): array
    {
        $diff = [
            'nodes' => [],
            'relationships' => [],
            'totals' => [],
        ];

        // Calculate node differences
        $allNodeLabels = array_unique(array_merge(
            array_keys($baseline['nodes'] ?? []),
            array_keys($current['nodes'] ?? [])
        ));

        foreach ($allNodeLabels as $label) {
            $baseCount = $baseline['nodes'][$label] ?? 0;
            $currCount = $current['nodes'][$label] ?? 0;
            $diff['nodes'][$label] = $this->calculateChange($baseCount, $currCount);
        }

        // Calculate relationship differences
        $allRelTypes = array_unique(array_merge(
            array_keys($baseline['relationships'] ?? []),
            array_keys($current['relationships'] ?? [])
        ));

        foreach ($allRelTypes as $type) {
            $baseCount = $baseline['relationships'][$type] ?? 0;
            $currCount = $current['relationships'][$type] ?? 0;
            $diff['relationships'][$type] = $this->calculateChange($baseCount, $currCount);
        }

        // Calculate total differences
        $diff['totals']['nodes'] = $this->calculateChange(
            $baseline['totals']['nodes'] ?? 0,
            $current['totals']['nodes'] ?? 0
        );

        $diff['totals']['relationships'] = $this->calculateChange(
            $baseline['totals']['relationships'] ?? 0,
            $current['totals']['relationships'] ?? 0
        );

        return $diff;
    }

    protected function calculateChange(int $baseline, int $current): array
    {
        $absolute = $current - $baseline;
        $percentage = $baseline > 0 ? (($current - $baseline) / $baseline) * 100 : 0;

        return [
            'baseline' => $baseline,
            'current' => $current,
            'absolute' => $absolute,
            'percentage' => round($percentage, 2),
        ];
    }

    protected function displayDifferences(array $differences): void
    {
        $this->newLine();
        $this->info('🔍 Differences (Current vs Baseline)');
        $this->info(str_repeat('─', 60));

        if ($this->option('show-diff')) {
            // Detailed differences
            $this->newLine();
            $this->info('📦 Node Changes:');
            $nodeData = [];
            foreach ($differences['nodes'] as $label => $change) {
                $nodeData[] = [
                    $label,
                    number_format($change['baseline']),
                    number_format($change['current']),
                    $this->formatChange($change['absolute']),
                    $this->formatPercentage($change['percentage']),
                ];
            }
            $this->table(['Label', 'Baseline', 'Current', 'Change', '%'], $nodeData);

            $this->newLine();
            $this->info('🔗 Relationship Changes:');
            $relData = [];
            foreach ($differences['relationships'] as $type => $change) {
                $relData[] = [
                    $type,
                    number_format($change['baseline']),
                    number_format($change['current']),
                    $this->formatChange($change['absolute']),
                    $this->formatPercentage($change['percentage']),
                ];
            }
            $this->table(['Type', 'Baseline', 'Current', 'Change', '%'], $relData);
        }

        $this->newLine();
        $this->info('📊 Total Changes:');
        $totalData = [];
        foreach ($differences['totals'] as $metric => $change) {
            $totalData[] = [
                ucfirst($metric),
                number_format($change['baseline']),
                number_format($change['current']),
                $this->formatChange($change['absolute']),
                $this->formatPercentage($change['percentage']),
            ];
        }
        $this->table(['Metric', 'Baseline', 'Current', 'Change', '%'], $totalData);
    }

    protected function checkVariances(array $differences): bool
    {
        $hasSignificantVariance = false;

        // Check node variances
        foreach ($differences['nodes'] as $label => $change) {
            if (abs($change['percentage']) > $this->threshold) {
                $hasSignificantVariance = true;
                $this->warn("⚠️  {$label} nodes: {$change['percentage']}% change exceeds threshold");
            }
        }

        // Check relationship variances
        foreach ($differences['relationships'] as $type => $change) {
            if (abs($change['percentage']) > $this->threshold) {
                $hasSignificantVariance = true;
                $this->warn("⚠️  {$type} relationships: {$change['percentage']}% change exceeds threshold");
            }
        }

        // Check total variances
        foreach ($differences['totals'] as $metric => $change) {
            if (abs($change['percentage']) > $this->threshold) {
                $hasSignificantVariance = true;
                $this->warn("⚠️  Total {$metric}: {$change['percentage']}% change exceeds threshold");
            }
        }

        return $hasSignificantVariance;
    }

    protected function listSnapshots(): int
    {
        $snapshots = File::glob($this->snapshotDir.'/*.json');

        if (empty($snapshots)) {
            $this->info('📂 No snapshots found');
            $this->line("   Directory: {$this->snapshotDir}");

            return self::SUCCESS;
        }

        $this->info('📂 Available Snapshots:');
        $this->newLine();

        $snapshotData = [];
        foreach ($snapshots as $snapshot) {
            $name = basename($snapshot, '.json');
            $timestamp = File::lastModified($snapshot);
            $size = File::size($snapshot);

            try {
                $data = json_decode(File::get($snapshot), true);
                $nodes = $data['totals']['nodes'] ?? 0;
                $rels = $data['totals']['relationships'] ?? 0;

                $snapshotData[] = [
                    $name,
                    date('Y-m-d H:i:s', $timestamp),
                    number_format($nodes),
                    number_format($rels),
                    $this->formatBytes($size),
                ];
            } catch (\Exception $e) {
                $snapshotData[] = [
                    $name,
                    date('Y-m-d H:i:s', $timestamp),
                    'N/A',
                    'N/A',
                    $this->formatBytes($size),
                ];
            }
        }

        $this->table(
            ['Snapshot', 'Created', 'Nodes', 'Relationships', 'Size'],
            $snapshotData
        );

        return self::SUCCESS;
    }

    protected function getSnapshotFilename(string $name): string
    {
        // Sanitize filename
        $name = preg_replace('/[^a-z0-9_-]/i', '-', $name);

        return $this->snapshotDir.'/'.$name.'.json';
    }

    protected function formatChange(int $value): string
    {
        if ($value > 0) {
            return '<fg=green>+'.number_format($value).'</>';
        } elseif ($value < 0) {
            return '<fg=red>'.number_format($value).'</>';
        }

        return '0';
    }

    protected function formatPercentage(float $value): string
    {
        if ($value > 0) {
            return '<fg=green>+'.number_format($value, 2).'%</>';
        } elseif ($value < 0) {
            return '<fg=red>'.number_format($value, 2).'%</>';
        }

        return '0.00%';
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
}
