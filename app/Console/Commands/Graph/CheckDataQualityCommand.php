<?php

namespace App\Console\Commands\Graph;

use App\Services\Graph\DataQualityService;
use Illuminate\Console\Command;

/**
 * Check Graph Data Quality Command
 *
 * Sprint 8.4: Data Quality Checks & Graph Maintenance
 *
 * Runs comprehensive data quality checks on the Neo4j graph database
 * and optionally fixes detected issues.
 *
 * Usage:
 *   php artisan graph:check-quality
 *   php artisan graph:check-quality --fix-duplicates
 *   php artisan graph:check-quality --email
 */
class CheckDataQualityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'graph:check-quality
                            {--fix-duplicates : Automatically merge duplicate nodes}
                            {--email : Email the quality report to administrators}
                            {--node-type= : Check specific node type only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Neo4j graph data quality and detect duplicates, orphans, and inconsistencies';

    protected DataQualityService $qualityService;

    public function __construct(DataQualityService $qualityService)
    {
        parent::__construct();
        $this->qualityService = $qualityService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Running Graph Data Quality Checks...');
        $this->newLine();

        $startTime = microtime(true);

        // Generate comprehensive quality report
        $report = $this->qualityService->generateQualityReport($this->option('email'));

        $duration = round(microtime(true) - $startTime, 2);

        // Display results
        $this->displayQualityScore($report);
        $this->newLine();

        $this->displayMetrics($report);
        $this->newLine();

        $this->displayDuplicates($report);
        $this->newLine();

        $this->displayOrphans($report);
        $this->newLine();

        $this->displayInconsistencies($report);
        $this->newLine();

        $this->displayStatistics($report);
        $this->newLine();

        $this->displayRecommendations($report);
        $this->newLine();

        // Fix duplicates if requested
        if ($this->option('fix-duplicates')) {
            $this->fixDuplicates($report);
            $this->newLine();
        }

        $this->info("✅ Quality check completed in {$duration}s");

        // Return exit code based on quality score
        $score = $report['overall_quality_score'];
        if ($score < 85) {
            return self::FAILURE; // Critical quality issues
        }

        return self::SUCCESS;
    }

    /**
     * Display overall quality score
     */
    protected function displayQualityScore(array $report): void
    {
        $score = $report['overall_quality_score'];

        if ($score >= 95) {
            $this->info('═══════════════════════════════════════════════════════');
            $this->info("  OVERALL QUALITY SCORE: {$score}% ✅ EXCELLENT");
            $this->info('═══════════════════════════════════════════════════════');
        } elseif ($score >= 85) {
            $this->warn('═══════════════════════════════════════════════════════');
            $this->warn("  OVERALL QUALITY SCORE: {$score}% ⚠️  GOOD");
            $this->warn('═══════════════════════════════════════════════════════');
        } else {
            $this->error('═══════════════════════════════════════════════════════');
            $this->error("  OVERALL QUALITY SCORE: {$score}% ❌ NEEDS ATTENTION");
            $this->error('═══════════════════════════════════════════════════════');
        }
    }

    /**
     * Display quality metrics
     */
    protected function displayMetrics(array $report): void
    {
        $this->info('📊 Quality Metrics:');

        $metrics = $report['metrics'];

        $this->line("  Total Nodes: {$report['total_nodes']}");
        $this->newLine();

        $this->line("  Duplicate Score: {$metrics['duplicate_score']}%");
        $this->line("    - Duplicate Groups: {$metrics['duplicate_groups']}");
        $this->line("    - Duplicate Nodes: {$metrics['duplicate_nodes']}");
        $this->newLine();

        $this->line("  Orphan Score: {$metrics['orphan_score']}%");
        $this->line("    - Orphan Nodes: {$metrics['orphan_nodes']}");
        $this->newLine();

        $this->line("  Consistency Score: {$metrics['consistency_score']}%");
        $this->line("    - Inconsistent Nodes: {$metrics['inconsistent_nodes']}");
    }

    /**
     * Display duplicate nodes
     */
    protected function displayDuplicates(array $report): void
    {
        $duplicates = $report['duplicates'];

        if ($duplicates['total_groups'] === 0) {
            $this->info('✅ No duplicate nodes detected');

            return;
        }

        $this->warn("⚠️  Found {$duplicates['total_groups']} duplicate groups ({$duplicates['total_nodes']} nodes)");

        if (! empty($duplicates['groups'])) {
            $this->newLine();
            $this->line('  Top Duplicate Groups:');

            foreach (array_slice($duplicates['groups'], 0, 5) as $i => $group) {
                $num = $i + 1;
                $this->line("  {$num}. {$group['node_type']} - {$group['key_property']}: {$group['key_value']}");
                $this->line("     Duplicate Count: {$group['duplicate_count']} nodes");
            }

            if ($duplicates['total_groups'] > 5) {
                $remaining = $duplicates['total_groups'] - 5;
                $this->line("  ... and {$remaining} more duplicate groups");
            }
        }
    }

    /**
     * Display orphan nodes
     */
    protected function displayOrphans(array $report): void
    {
        $orphans = $report['orphans'];

        if ($orphans['total'] === 0) {
            $this->info('✅ No orphan nodes detected');

            return;
        }

        $this->warn("⚠️  Found {$orphans['total']} orphan nodes (nodes with no relationships)");

        if (! empty($orphans['nodes'])) {
            $this->newLine();
            $this->line('  Sample Orphan Nodes:');

            foreach (array_slice($orphans['nodes'], 0, 5) as $i => $node) {
                $num = $i + 1;
                $props = $node['properties'];
                $identifier = $props['title'] ?? $props['name'] ?? $props['case_number'] ?? $node['node_id'];
                $this->line("  {$num}. {$node['node_type']} - ID: {$node['node_id']} ({$identifier})");
            }

            if ($orphans['total'] > 5) {
                $remaining = $orphans['total'] - 5;
                $this->line("  ... and {$remaining} more orphan nodes");
            }
        }
    }

    /**
     * Display inconsistent data
     */
    protected function displayInconsistencies(array $report): void
    {
        $inconsistencies = $report['inconsistencies'];

        if ($inconsistencies['total'] === 0) {
            $this->info('✅ No data inconsistencies detected');

            return;
        }

        $this->warn("⚠️  Found {$inconsistencies['total']} nodes with missing required properties");

        if (! empty($inconsistencies['nodes'])) {
            $this->newLine();
            $this->line('  Sample Inconsistencies:');

            foreach (array_slice($inconsistencies['nodes'], 0, 5) as $i => $node) {
                $num = $i + 1;
                $this->line("  {$num}. {$node['node_type']} - ID: {$node['node_id']} - Missing: {$node['missing_property']}");
            }

            if ($inconsistencies['total'] > 5) {
                $remaining = $inconsistencies['total'] - 5;
                $this->line("  ... and {$remaining} more inconsistent nodes");
            }
        }
    }

    /**
     * Display graph statistics
     */
    protected function displayStatistics(array $report): void
    {
        $this->info('📈 Graph Statistics:');

        $this->newLine();
        $this->line('  Node Counts:');
        foreach ($report['statistics']['nodes'] as $type => $count) {
            $this->line("    {$type}: {$count}");
        }

        $this->newLine();
        $this->line('  Relationship Counts:');
        foreach ($report['statistics']['relationships'] as $type => $count) {
            $this->line("    {$type}: {$count}");
        }
    }

    /**
     * Display recommendations
     */
    protected function displayRecommendations(array $report): void
    {
        if (empty($report['recommendations'])) {
            $this->info('✨ No recommendations - Graph quality is excellent!');

            return;
        }

        $this->info('💡 Recommendations:');
        $this->newLine();

        foreach ($report['recommendations'] as $i => $rec) {
            $num = $i + 1;
            $priority = strtoupper($rec['priority']);

            if ($rec['priority'] === 'high') {
                $this->error("  {$num}. [{$priority}] {$rec['category']}");
            } else {
                $this->warn("  {$num}. [{$priority}] {$rec['category']}");
            }

            $this->line("     Issue: {$rec['issue']}");
            $this->line("     Action: {$rec['action']}");
            $this->newLine();
        }
    }

    /**
     * Fix duplicate nodes automatically
     */
    protected function fixDuplicates(array $report): void
    {
        $duplicates = $report['duplicates'];

        if ($duplicates['total_groups'] === 0) {
            $this->info('No duplicates to fix');

            return;
        }

        $this->warn('🔧 Fixing duplicate nodes...');
        $this->newLine();

        $progressBar = $this->output->createProgressBar(count($duplicates['groups']));
        $progressBar->start();

        $fixed = 0;
        $failed = 0;

        foreach ($duplicates['groups'] as $group) {
            $result = $this->qualityService->mergeDuplicateNodes($group);

            if ($result['success']) {
                $fixed++;
            } else {
                $failed++;
                $this->newLine();
                $this->error("  Failed to merge {$group['node_type']} - {$group['key_value']}: {$result['error']}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->newLine();

        $this->info("✅ Fixed {$fixed} duplicate groups");

        if ($failed > 0) {
            $this->error("❌ Failed to fix {$failed} duplicate groups");
        }

        // Regenerate report after fixes
        $this->info('Regenerating quality report after fixes...');
        $newReport = $this->qualityService->generateQualityReport(false);
        $this->info("New quality score: {$newReport['overall_quality_score']}%");
    }
}
