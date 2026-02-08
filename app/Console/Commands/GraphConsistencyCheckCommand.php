<?php

namespace App\Console\Commands;

use App\Models\Law;
use App\Services\GraphDatabaseService;
use Illuminate\Console\Command;

class GraphConsistencyCheckCommand extends Command
{
    protected $signature = 'graph:consistency-check';
    protected $description = 'Check consistency between PostgreSQL and Neo4j graph database';

    public function __construct(
        private GraphDatabaseService $graphService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Check if Neo4j sync is enabled
        if (!config('neo4j.sync.enabled', false)) {
            $this->warn('Neo4j sync is disabled. Skipping consistency check.');
            return Command::SUCCESS;
        }

        $this->info('Checking graph database consistency...');

        $orphanedNodes = 0;
        $missingNodes = 0;

        try {
            // Check LawDocuments
            $graphLawIds = $this->getGraphNodeIds('LawDocument');
            $dbLawIds = Law::pluck('id')->toArray();

            $orphaned = array_diff($graphLawIds, $dbLawIds);
            $missing = array_diff($dbLawIds, $graphLawIds);

            $orphanedNodes += count($orphaned);
            $missingNodes += count($missing);

            // Report findings
            if ($orphanedNodes > 0) {
                $this->error("Found $orphanedNodes orphaned graph nodes");
            }

            if ($missingNodes > 0) {
                $this->error("Found $missingNodes missing graph nodes");
            }

            if ($orphanedNodes === 0 && $missingNodes === 0) {
                $this->info('Graph and database are consistent');
                return Command::SUCCESS;
            }

            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error('Error checking graph consistency: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getGraphNodeIds(string $label): array
    {
        $results = $this->graphService->run(
            "MATCH (n:$label) RETURN n.id as id"
        );

        return $results->map(fn($r) => $r->get('id'))->filter()->toArray();
    }
}
