<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Laudis\Neo4j\Contracts\ClientInterface;

class CreateNeo4jIndexes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'neo4j:create-indexes
                            {--force : Drop existing indexes before creating new ones}
                            {--show-only : Only show what would be created without creating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create all Neo4j graph indexes for optimal query performance';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('neo4j.enabled')) {
            $this->error('Neo4j is not enabled. Set NEO4J_ENABLED=true in .env');

            return self::FAILURE;
        }

        try {
            $client = app(ClientInterface::class);

            $this->info('🗄️  Creating Neo4j indexes...');
            $this->newLine();

            // Test connection
            $result = $client->run('RETURN "Connected" AS status');
            if ($result->first()->get('status') !== 'Connected') {
                throw new \Exception('Failed to connect to Neo4j');
            }

            $this->info('✓ Connected to Neo4j');
            $this->newLine();

            if ($this->option('force')) {
                $this->warn('⚠️  Force mode: Dropping existing indexes...');
                $this->dropExistingIndexes($client);
                $this->newLine();
            }

            // Create indexes
            $indexes = $this->getIndexDefinitions();
            $created = 0;
            $skipped = 0;
            $failed = 0;

            foreach ($indexes as $name => $cypher) {
                if ($this->option('show-only')) {
                    $this->line("  Would create: {$name}");

                    continue;
                }

                try {
                    $client->run($cypher);
                    $this->info("  ✓ {$name}");
                    $created++;
                } catch (\Exception $e) {
                    if (str_contains($e->getMessage(), 'already exists')) {
                        $this->line("  ⊘ {$name} (already exists)");
                        $skipped++;
                    } else {
                        $this->error("  ✗ {$name}: ".$e->getMessage());
                        $failed++;
                    }
                }
            }

            $this->newLine();

            if ($this->option('show-only')) {
                $this->info('📊 Would create '.count($indexes).' indexes');
            } else {
                $this->info('📊 Index creation complete:');
                $this->line("  Created: {$created}");
                $this->line("  Skipped: {$skipped}");
                if ($failed > 0) {
                    $this->line("  Failed:  {$failed}");
                }

                $this->newLine();
                $this->info('Verifying indexes...');
                $this->showIndexes($client);
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to create indexes: '.$e->getMessage());
            Log::error('Neo4j index creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Get all index definitions.
     */
    protected function getIndexDefinitions(): array
    {
        return [
            // Node property indexes
            'case_number' => 'CREATE INDEX case_number IF NOT EXISTS FOR (c:Case) ON (c.case_number)',
            'case_id' => 'CREATE INDEX case_id IF NOT EXISTS FOR (c:Case) ON (c.id)',
            'case_created_at' => 'CREATE INDEX case_created_at IF NOT EXISTS FOR (c:Case) ON (c.created_at)',
            'case_status' => 'CREATE INDEX case_status IF NOT EXISTS FOR (c:Case) ON (c.status)',

            'law_code' => 'CREATE INDEX law_code IF NOT EXISTS FOR (l:Law) ON (l.code)',
            'law_id' => 'CREATE INDEX law_id IF NOT EXISTS FOR (l:Law) ON (l.id)',
            'law_title' => 'CREATE INDEX law_title IF NOT EXISTS FOR (l:Law) ON (l.title)',

            'decision_ecli' => 'CREATE INDEX decision_ecli IF NOT EXISTS FOR (d:Decision) ON (d.ecli)',
            'decision_id' => 'CREATE INDEX decision_id IF NOT EXISTS FOR (d:Decision) ON (d.id)',
            'decision_court' => 'CREATE INDEX decision_court IF NOT EXISTS FOR (d:Decision) ON (d.court)',
            'decision_date' => 'CREATE INDEX decision_date IF NOT EXISTS FOR (d:Decision) ON (d.date)',
            'decision_created_at' => 'CREATE INDEX decision_created_at IF NOT EXISTS FOR (d:Decision) ON (d.created_at)',

            'keyword_term' => 'CREATE INDEX keyword_term IF NOT EXISTS FOR (k:Keyword) ON (k.term)',
            'keyword_weight' => 'CREATE INDEX keyword_weight IF NOT EXISTS FOR (k:Keyword) ON (k.weight)',

            'topic_name' => 'CREATE INDEX topic_name IF NOT EXISTS FOR (t:Topic) ON (t.name)',
            'topic_category' => 'CREATE INDEX topic_category IF NOT EXISTS FOR (t:Topic) ON (t.category)',

            'court_name' => 'CREATE INDEX court_name IF NOT EXISTS FOR (c:Court) ON (c.name)',
            'court_type' => 'CREATE INDEX court_type IF NOT EXISTS FOR (c:Court) ON (c.type)',

            'concept_name' => 'CREATE INDEX concept_name IF NOT EXISTS FOR (lc:LegalConcept) ON (lc.name)',
            'concept_category' => 'CREATE INDEX concept_category IF NOT EXISTS FOR (lc:LegalConcept) ON (lc.category)',

            'article_number' => 'CREATE INDEX article_number IF NOT EXISTS FOR (a:Article) ON (a.number)',
            'article_law_id' => 'CREATE INDEX article_law_id IF NOT EXISTS FOR (a:Article) ON (a.law_id)',

            'document_type' => 'CREATE INDEX document_type IF NOT EXISTS FOR (doc:Document) ON (doc.type)',
            'document_created_at' => 'CREATE INDEX document_created_at IF NOT EXISTS FOR (doc:Document) ON (doc.created_at)',

            // Composite indexes
            'decision_court_date' => 'CREATE INDEX decision_court_date IF NOT EXISTS FOR (d:Decision) ON (d.court, d.date)',
            'article_law_number' => 'CREATE INDEX article_law_number IF NOT EXISTS FOR (a:Article) ON (a.law_id, a.number)',

            // Full-text indexes
            'lawFulltext' => 'CREATE FULLTEXT INDEX lawFulltext IF NOT EXISTS FOR (l:Law) ON EACH [l.title, l.content]',
            'decisionFulltext' => 'CREATE FULLTEXT INDEX decisionFulltext IF NOT EXISTS FOR (d:Decision) ON EACH [d.title, d.reasoning, d.summary]',
            'caseFulltext' => 'CREATE FULLTEXT INDEX caseFulltext IF NOT EXISTS FOR (c:Case) ON EACH [c.description, c.notes]',
            'conceptFulltext' => 'CREATE FULLTEXT INDEX conceptFulltext IF NOT EXISTS FOR (lc:LegalConcept) ON EACH [lc.name, lc.description]',
            'keywordFulltext' => 'CREATE FULLTEXT INDEX keywordFulltext IF NOT EXISTS FOR (k:Keyword) ON EACH [k.term]',

            // Unique constraints
            'case_number_unique' => 'CREATE CONSTRAINT case_number_unique IF NOT EXISTS FOR (c:Case) REQUIRE c.case_number IS UNIQUE',
            'law_code_unique' => 'CREATE CONSTRAINT law_code_unique IF NOT EXISTS FOR (l:Law) REQUIRE l.code IS UNIQUE',
            'decision_ecli_unique' => 'CREATE CONSTRAINT decision_ecli_unique IF NOT EXISTS FOR (d:Decision) REQUIRE d.ecli IS UNIQUE',
            'keyword_term_unique' => 'CREATE CONSTRAINT keyword_term_unique IF NOT EXISTS FOR (k:Keyword) REQUIRE k.term IS UNIQUE',
            'topic_name_unique' => 'CREATE CONSTRAINT topic_name_unique IF NOT EXISTS FOR (t:Topic) REQUIRE t.name IS UNIQUE',
            'court_name_unique' => 'CREATE CONSTRAINT court_name_unique IF NOT EXISTS FOR (c:Court) REQUIRE c.name IS UNIQUE',
        ];
    }

    /**
     * Drop existing indexes (use with caution).
     */
    protected function dropExistingIndexes(ClientInterface $client): void
    {
        try {
            // Get all indexes
            $result = $client->run('SHOW INDEXES');

            $indexes = [];
            foreach ($result as $record) {
                $name = $record->get('name');
                if ($name) {
                    $indexes[] = $name;
                }
            }

            // Drop each index
            foreach ($indexes as $indexName) {
                try {
                    $client->run("DROP INDEX {$indexName} IF EXISTS");
                    $this->line("  Dropped: {$indexName}");
                } catch (\Exception $e) {
                    $this->warn("  Failed to drop {$indexName}: ".$e->getMessage());
                }
            }

            // Get all constraints
            $result = $client->run('SHOW CONSTRAINTS');

            $constraints = [];
            foreach ($result as $record) {
                $name = $record->get('name');
                if ($name) {
                    $constraints[] = $name;
                }
            }

            // Drop each constraint
            foreach ($constraints as $constraintName) {
                try {
                    $client->run("DROP CONSTRAINT {$constraintName} IF EXISTS");
                    $this->line("  Dropped: {$constraintName}");
                } catch (\Exception $e) {
                    $this->warn("  Failed to drop {$constraintName}: ".$e->getMessage());
                }
            }

        } catch (\Exception $e) {
            $this->warn('Warning: Could not drop existing indexes: '.$e->getMessage());
        }
    }

    /**
     * Show all indexes.
     */
    protected function showIndexes(ClientInterface $client): void
    {
        try {
            $result = $client->run('SHOW INDEXES');

            $this->newLine();
            $this->info('Current Indexes:');

            $rows = [];
            foreach ($result as $record) {
                $rows[] = [
                    $record->get('name'),
                    $record->get('type'),
                    $record->get('state'),
                ];
            }

            if (empty($rows)) {
                $this->line('  No indexes found');
            } else {
                $this->table(['Name', 'Type', 'State'], $rows);
            }

        } catch (\Exception $e) {
            $this->warn('Could not show indexes: '.$e->getMessage());
        }
    }
}
