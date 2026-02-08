<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class SetupTestDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:setup-db
                            {--source= : Source database name (defaults to current DB_DATABASE)}
                            {--target=laravel_test : Target test database name}
                            {--force : Force recreation of existing test database}
                            {--seed : Seed the test database after creation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup test database by copying from production or creating fresh';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('╔════════════════════════════════════════════════════════════╗');
        $this->info('║         Test Database Setup - Production Copy             ║');
        $this->info('╚════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $sourceDb = $this->option('source') ?? Config::get('database.connections.pgsql.database');
        $targetDb = $this->option('target');
        $force = $this->option('force');
        $seed = $this->option('seed');

        if (empty($sourceDb)) {
            $this->error('Source database name is required. Set DB_DATABASE in .env or use --source option.');

            return Command::FAILURE;
        }

        $this->table(
            ['Configuration', 'Value'],
            [
                ['Source Database', $sourceDb],
                ['Target Database', $targetDb],
                ['Force Overwrite', $force ? 'Yes' : 'No'],
                ['Seed After Creation', $seed ? 'Yes' : 'No'],
            ]
        );

        if (! $force && ! $this->confirm('Do you want to proceed?', true)) {
            $this->warn('Operation cancelled.');

            return Command::SUCCESS;
        }

        $this->newLine();

        // Execute the bash script
        $scriptPath = base_path('scripts/setup-test-db.sh');

        if (! file_exists($scriptPath)) {
            $this->error('Setup script not found at: '.$scriptPath);
            $this->info('Please ensure scripts/setup-test-db.sh exists.');

            return Command::FAILURE;
        }

        // Build command arguments
        $command = $scriptPath;
        $command .= ' --auto';
        $command .= ' --source-db='.escapeshellarg($sourceDb);
        $command .= ' --test-db='.escapeshellarg($targetDb);

        if ($force) {
            $command .= ' --force';
        }

        $this->info('Executing: '.$command);
        $this->newLine();

        // Execute the script
        $result = null;
        passthru($command, $result);

        if ($result !== 0) {
            $this->error('Failed to setup test database.');

            return Command::FAILURE;
        }

        // Seed if requested
        if ($seed) {
            $this->newLine();
            $this->info('Seeding test database...');

            // Temporarily switch to test database
            Config::set('database.connections.pgsql.database', $targetDb);
            DB::purge('pgsql');
            DB::reconnect('pgsql');

            Artisan::call('db:seed', [
                '--class' => 'TestDataSeeder',
                '--force' => true,
            ]);

            $this->info(Artisan::output());
        }

        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════════╗');
        $this->info('║              Test Database Setup Complete!                ║');
        $this->info('╚════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $this->info('Next steps:');
        $this->line('  1. Ensure .env.testing has DB_DATABASE='.$targetDb);
        $this->line('  2. Run tests with: composer test:integrated');

        return Command::SUCCESS;
    }
}
