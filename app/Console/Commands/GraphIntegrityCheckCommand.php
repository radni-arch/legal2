<?php

namespace App\Console\Commands;

use App\Services\Graph\GraphDataIntegrityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Command to check graph database integrity
 *
 * This command runs comprehensive health checks on the Neo4j graph
 * database and reports any issues found.
 *
 * Task 4.3: Cross-Database Integrity Scheduling - Added email option
 *
 * Usage:
 *   php artisan graph:integrity-check                      # Human-readable output
 *   php artisan graph:integrity-check --json               # JSON output for automation
 *   php artisan graph:integrity-check --email              # Email report to configured admin
 *   php artisan graph:integrity-check --email=admin@x.com  # Email report to specific address
 */
class GraphIntegrityCheckCommand extends Command
{
    protected $signature = 'graph:integrity-check
        {--json : Output results as JSON}
        {--fix : Attempt to fix issues automatically (future feature)}
        {--email= : Email report to this address (or config default if no value)}';

    protected $description = 'Check graph database integrity and report issues';

    public function __construct(
        protected GraphDataIntegrityService $integrityService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Graph Integrity Check');
        $this->newLine();

        try {
            $report = $this->integrityService->generateIntegrityReport();
        } catch (\Exception $e) {
            $this->error('Failed to generate integrity report: '.$e->getMessage());
            Log::error('Graph integrity check failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Send email if requested and issues found
            if ($this->hasEmailOption() && $report['summary']['health_status'] !== 'healthy') {
                $this->sendEmailReport($report);
            }

            return $report['summary']['health_status'] === 'healthy' ? self::SUCCESS : self::FAILURE;
        }

        // Display summary table
        $this->table(
            ['Metric', 'Count'],
            [
                ['Orphan Nodes', $report['summary']['total_orphan_nodes']],
                ['Missing Nodes', $report['summary']['total_missing_nodes']],
                ['Dangling Relationships', $report['summary']['total_dangling_relationships']],
            ]
        );

        $this->newLine();

        // Display detailed breakdown if issues found
        if ($report['summary']['health_status'] !== 'healthy') {
            $this->displayDetailedIssues($report);
        }

        // Display health status
        $status = $report['summary']['health_status'];
        if ($status === 'healthy') {
            $this->info('Health Status: HEALTHY');

            return self::SUCCESS;
        }

        $this->warn('Health Status: NEEDS ATTENTION');
        Log::warning('Graph integrity check found issues', $report['summary']);

        // Send email if requested
        if ($this->hasEmailOption()) {
            $this->sendEmailReport($report);
        }

        return self::FAILURE;
    }

    /**
     * Check if email option was provided
     */
    protected function hasEmailOption(): bool
    {
        // Check if --email was passed at all (with or without value)
        return $this->hasOption('email') && $this->input->getParameterOption('--email') !== false;
    }

    /**
     * Get the email address to send report to
     */
    protected function getEmailAddress(): ?string
    {
        $email = $this->option('email');

        // If --email was passed without a value, it will be an empty string or null
        if (empty($email) || $email === true) {
            return config('integrity.admin_email');
        }

        return $email;
    }

    /**
     * Send email report
     */
    protected function sendEmailReport(array $report): void
    {
        $email = $this->getEmailAddress();

        if (empty($email)) {
            $this->warn('No email address configured. Set INTEGRITY_ADMIN_EMAIL or provide --email=address');

            return;
        }

        try {
            Mail::raw($this->formatEmailReport($report), function ($message) use ($email, $report) {
                $message->to($email)
                    ->subject('[Graph Integrity Alert] Status: '.strtoupper($report['summary']['health_status']));
            });

            $this->info("Report emailed to: {$email}");
            Log::info('Graph integrity report emailed', ['to' => $email]);
        } catch (\Exception $e) {
            $this->error("Failed to send email: {$e->getMessage()}");
            Log::error('Failed to send integrity report email', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Format report for email
     */
    protected function formatEmailReport(array $report): string
    {
        $status = $report['summary']['health_status'];
        $orphans = $report['summary']['total_orphan_nodes'];
        $missing = $report['summary']['total_missing_nodes'];
        $dangling = $report['summary']['total_dangling_relationships'];

        $email = <<<EMAIL
Graph Data Integrity Report
Generated: {$report['generated_at']}

===============================================
HEALTH STATUS: {$status}
===============================================

SUMMARY:
  - Orphan Nodes: {$orphans}
  - Missing Nodes: {$missing}
  - Dangling Relationships: {$dangling}

EMAIL;

        if ($status !== 'healthy') {
            $email .= "\nDETAILED BREAKDOWN:\n";

            // Orphan nodes detail
            if ($orphans > 0) {
                $email .= "\nOrphan Nodes (in Neo4j but not in PostgreSQL):\n";
                foreach ($report['orphan_nodes'] as $type => $nodes) {
                    if (is_array($nodes) && ! isset($nodes['error']) && count($nodes) > 0) {
                        $email .= "  - {$type}: ".count($nodes)." nodes\n";
                    }
                }
            }

            // Missing nodes detail
            if ($missing > 0) {
                $email .= "\nMissing Nodes (in PostgreSQL but not in Neo4j):\n";
                foreach ($report['missing_nodes'] as $type => $nodes) {
                    if (is_array($nodes) && ! isset($nodes['error']) && count($nodes) > 0) {
                        $email .= "  - {$type}: ".count($nodes)." records\n";
                    }
                }
            }

            // Dangling relationships detail
            if ($dangling > 0) {
                $email .= "\nDangling Relationships:\n";
                foreach ($report['dangling_relationships'] as $type => $rels) {
                    if (is_array($rels) && ! isset($rels['error']) && count($rels) > 0) {
                        $email .= "  - {$type}: ".count($rels)." relationships\n";
                    }
                }
            }
        }

        $email .= <<<'EMAIL'

===============================================
RECOMMENDED ACTIONS:

1. Review orphan nodes and consider cleanup
2. Re-sync missing nodes from PostgreSQL
3. Investigate dangling relationships

Run: php artisan graph:integrity-check --fix
to attempt automatic repairs.
===============================================

This is an automated message from the AI Legal War Machine.
EMAIL;

        return $email;
    }

    /**
     * Display detailed breakdown of issues found
     */
    protected function displayDetailedIssues(array $report): void
    {
        // Orphan nodes
        if ($report['summary']['total_orphan_nodes'] > 0) {
            $this->warn('Orphan Nodes (in Neo4j but not in PostgreSQL):');
            foreach ($report['orphan_nodes'] as $type => $nodes) {
                if (is_array($nodes) && ! isset($nodes['error']) && count($nodes) > 0) {
                    $this->line("  - {$type}: ".count($nodes).' nodes');
                }
            }
            $this->newLine();
        }

        // Missing nodes
        if ($report['summary']['total_missing_nodes'] > 0) {
            $this->warn('Missing Nodes (in PostgreSQL but not in Neo4j):');
            foreach ($report['missing_nodes'] as $type => $nodes) {
                if (is_array($nodes) && ! isset($nodes['error']) && count($nodes) > 0) {
                    $this->line("  - {$type}: ".count($nodes).' records');
                }
            }
            $this->newLine();
        }

        // Dangling relationships
        if ($report['summary']['total_dangling_relationships'] > 0) {
            $this->warn('Dangling Relationships:');
            foreach ($report['dangling_relationships'] as $type => $rels) {
                if (is_array($rels) && ! isset($rels['error']) && count($rels) > 0) {
                    $this->line("  - {$type}: ".count($rels).' relationships');
                }
            }
            $this->newLine();
        }
    }
}
