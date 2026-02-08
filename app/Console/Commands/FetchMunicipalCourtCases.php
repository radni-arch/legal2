<?php

namespace App\Console\Commands;

use App\Models\Court;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class FetchMunicipalCourtCases extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'epredmet:fetch-municipal
                            {--years=2025,2024,2023 : Comma-separated years to fetch}
                            {--register=Pp Prz : Register to fetch}
                            {--dry-run : Show what would be fetched without executing}';

    /**
     * The console command description.
     */
    protected $description = 'Fetch cases from all municipal courts (Općinski) for specified years';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $years = array_map('intval', explode(',', $this->option('years')));
        $register = $this->option('register');
        $dryRun = $this->option('dry-run');

        // Fetch all municipal courts (Općinski)
        $courts = Court::where(function ($query) {
            $query->where('name', 'ILIKE', '%Općinski%')
                ->orWhere('name', 'ILIKE', '%prekršajni%');
        })->whereRaw("name NOT ILIKE '%radni%'")->orderBy('external_id', 'desc')->get();

        if ($courts->isEmpty()) {
            $this->error('No municipal courts found matching "Općinski"');
            Log::warning('FetchMunicipalCourtCases: No courts found matching criteria');

            return self::FAILURE;
        }

        $this->info("Found {$courts->count()} municipal courts");
        Log::info("FetchMunicipalCourtCases: Starting fetch for {$courts->count()} courts", [
            'years' => $years,
            'register' => $register,
        ]);

        // Calculate total iterations for progress
        $totalIterations = $courts->count() * count($years);
        $currentIteration = 0;
        $successCount = 0;
        $failureCount = 0;

        // Display courts to be processed
        if ($dryRun) {
            $this->info('DRY RUN - Would process the following courts:');
            $this->table(
                ['ID', 'External ID', 'Name'],
                $courts->map(fn ($c) => [$c->id, $c->external_id, $c->name])->toArray()
            );
            $this->info('Years: ' . implode(', ', $years));

            return self::SUCCESS;
        }

        // Create main progress bar
        $progressBar = $this->output->createProgressBar($totalIterations);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');
        $progressBar->start();

        foreach ($courts as $court) {
            foreach ($years as $year) {
                $currentIteration++;
                $progressBar->setMessage("Court: {$court->short_name} | Year: {$year}");
                $progressBar->setProgress($currentIteration);

                Log::info("FetchMunicipalCourtCases: Processing court", [
                    'court_id' => $court->id,
                    'external_id' => $court->external_id,
                    'court_name' => $court->name,
                    'year' => $year,
                ]);

                $this->newLine();
                $this->info("Processing: {$court->name} ({$year})");

                try {
                    $exitCode = Artisan::call('epredmet:fetch', [
                        '--court' => $court->external_id,
                        '--year' => $year,
                        '--register' => $register,
                    ], $this->output);

                    if ($exitCode === self::SUCCESS) {
                        $successCount++;
                        Log::info("FetchMunicipalCourtCases: Successfully fetched", [
                            'court_name' => $court->name,
                            'year' => $year,
                        ]);
                    } else {
                        $failureCount++;
                        Log::error("FetchMunicipalCourtCases: Fetch failed", [
                            'court_name' => $court->name,
                            'year' => $year,
                            'exit_code' => $exitCode,
                        ]);
                        $this->warn("Failed to fetch cases for {$court->name} ({$year})");
                    }
                } catch (\Exception $e) {
                    $failureCount++;
                    Log::error("FetchMunicipalCourtCases: Exception during fetch", [
                        'court_name' => $court->name,
                        'year' => $year,
                        'error' => $e->getMessage(),
                    ]);
                    $this->error("Error: {$e->getMessage()}");
                }
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('=== Fetch Complete ===');
        $this->info("Total courts processed: {$courts->count()}");
        $this->info("Years processed: " . implode(', ', $years));
        $this->info("Successful fetches: {$successCount}");
        $this->info("Failed fetches: {$failureCount}");

        Log::info("FetchMunicipalCourtCases: Completed", [
            'total_courts' => $courts->count(),
            'years' => $years,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
        ]);

        return $failureCount === 0 ? self::SUCCESS : self::FAILURE;
    }
}
