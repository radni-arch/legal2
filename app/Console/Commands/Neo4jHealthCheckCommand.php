<?php

namespace App\Console\Commands;

use App\Services\GraphDatabaseService;
use Illuminate\Console\Command;

class Neo4jHealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'neo4j:health
                          {--json : Output as JSON}
                          {--verbose : Show detailed information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Neo4j database connection and health status';

    /**
     * Execute the console command.
     */
    public function handle(GraphDatabaseService $graphDb): int
    {
        $this->info('Neo4j Health Check');
        $this->info('==================');
        $this->newLine();

        $status = $graphDb->getHealthStatus();

        if ($this->option('json')) {
            $this->line(json_encode($status, JSON_PRETTY_PRINT));

            return $status['healthy'] ? self::SUCCESS : self::FAILURE;
        }

        // Display status
        $this->table(
            ['Property', 'Value'],
            [
                ['Enabled in Config', $status['enabled'] ? '✓ Yes' : '✗ No'],
                ['Available', $status['available'] ? '✓ Yes' : '✗ No'],
                ['Healthy', $status['healthy'] ? '✓ Yes' : '✗ No'],
                ['URI', $status['uri']],
            ]
        );

        if (isset($status['neo4j_version'])) {
            $this->newLine();
            $this->table(
                ['Database Info', 'Value'],
                [
                    ['Neo4j Version', $status['neo4j_version']],
                    ['Edition', $status['edition']],
                    ['Node Count', number_format($status['node_count'])],
                ]
            );
        }

        if (isset($status['error'])) {
            $this->newLine();
            $this->error('Error: '.$status['error']);
        }

        $this->newLine();

        if ($status['healthy']) {
            $this->info('✓ Neo4j is healthy and ready to use');

            return self::SUCCESS;
        } else {
            $this->error('✗ Neo4j is not available');
            $this->newLine();
            $this->warn('Troubleshooting tips:');
            $this->line('  1. Check if Neo4j is running: docker ps | grep neo4j');
            $this->line('  2. Check Neo4j logs: docker logs <neo4j-container>');
            $this->line('  3. Verify connection settings in .env file:');
            $this->line('     - NEO4J_URI='.config('neo4j.uri'));
            $this->line('     - NEO4J_USERNAME='.config('neo4j.connections.bolt.username'));
            $this->line('  4. Test connection: curl '.str_replace('bolt://', 'http://', $status['uri']));
            $this->line('  5. Disable Neo4j temporarily: set NEO4J_ENABLED=false in .env');

            return self::FAILURE;
        }
    }
}
