<?php

namespace App\Console\Commands;

use App\Services\GraphDatabaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GraphSchemaApplyCommand extends Command
{
    protected $signature = 'graph:schema-apply
                            {--dry-run : Show what would be executed without running}
                            {--schema-file= : Path to schema file (default: documentation/GRAPH_SCHEMA.cypher)}';

    protected $description = 'Apply Neo4j schema constraints and indexes from GRAPH_SCHEMA.cypher';

    protected array $stats = [
        'constraints' => 0,
        'indexes' => 0,
        'skipped' => 0,
        'errors' => 0,
    ];

    public function handle(GraphDatabaseService $graph): int
    {
        $schemaFile = $this->option('schema-file')
            ?? base_path('documentation/GRAPH_SCHEMA.cypher');

        if (!File::exists($schemaFile)) {
            $this->error("Schema file not found: {$schemaFile}");
            return Command::FAILURE;
        }

        $this->info("Reading schema from: {$schemaFile}");

        $content = File::get($schemaFile);
        $statements = $this->extractStatements($content);

        if (empty($statements)) {
            $this->warn('No CREATE CONSTRAINT or CREATE INDEX statements found.');
            return Command::SUCCESS;
        }

        $this->info(sprintf('Found %d statements to apply', count($statements)));

        if ($this->option('dry-run')) {
            $this->info('DRY RUN - No changes will be made');
            $this->newLine();
            foreach ($statements as $stmt) {
                $this->line("  " . $this->truncateStatement($stmt));
            }
            return Command::SUCCESS;
        }

        $this->newLine();
        $bar = $this->output->createProgressBar(count($statements));
        $bar->start();

        foreach ($statements as $statement) {
            $this->applyStatement($graph, $statement);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->displaySummary();

        return $this->stats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    protected function extractStatements(string $content): array
    {
        $statements = [];

        // Match CREATE CONSTRAINT statements
        preg_match_all(
            '/CREATE\s+CONSTRAINT\s+\w+\s+IF\s+NOT\s+EXISTS[^;]+;?/i',
            $content,
            $constraints
        );

        foreach ($constraints[0] as $stmt) {
            $statements[] = rtrim($stmt, ';');
        }

        // Match CREATE INDEX statements
        preg_match_all(
            '/CREATE\s+INDEX\s+\w+\s+IF\s+NOT\s+EXISTS[^;]+;?/i',
            $content,
            $indexes
        );

        foreach ($indexes[0] as $stmt) {
            $statements[] = rtrim($stmt, ';');
        }

        return $statements;
    }

    protected function applyStatement(GraphDatabaseService $graph, string $statement): void
    {
        $isConstraint = stripos($statement, 'CONSTRAINT') !== false;
        $type = $isConstraint ? 'constraint' : 'index';

        try {
            $graph->run($statement);

            if ($isConstraint) {
                $this->stats['constraints']++;
            } else {
                $this->stats['indexes']++;
            }
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            // Check if it's an "already exists" error (which is fine)
            if (
                stripos($message, 'already exists') !== false ||
                stripos($message, 'An equivalent') !== false
            ) {
                $this->stats['skipped']++;
            } else {
                $this->stats['errors']++;
                $this->newLine();
                $this->error("Failed to apply {$type}: " . $this->truncateStatement($statement));
                $this->error("  Error: {$message}");
            }
        }
    }

    protected function truncateStatement(string $statement): string
    {
        $statement = preg_replace('/\s+/', ' ', trim($statement));
        return strlen($statement) > 80 ? substr($statement, 0, 77) . '...' : $statement;
    }

    protected function displaySummary(): void
    {
        $this->info('Schema Application Summary:');
        $this->table(
            ['Type', 'Count'],
            [
                ['Constraints Applied', $this->stats['constraints']],
                ['Indexes Applied', $this->stats['indexes']],
                ['Skipped (already exist)', $this->stats['skipped']],
                ['Errors', $this->stats['errors']],
            ]
        );
    }
}
