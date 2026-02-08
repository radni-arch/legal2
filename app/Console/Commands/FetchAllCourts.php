<?php

namespace App\Console\Commands;

use App\Models\Court;
use App\Models\SyncLog;
use App\Services\EPredmetService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FetchAllCourts extends Command
{
    protected $signature = 'epredmet:fetch-all 
                            {--years=2025 : Comma-separated years to fetch}
                            {--register=Pp Prz : Register to fetch}
                            {--level=1 : Court level (1=municipal)}
                            {--continue : Continue from last incomplete sync}
                            {--dry-run : Show what would be fetched without fetching}';

    protected $description = 'Fetch Pp Prz cases from ALL courts for specified years';

    protected EPredmetService $api;

    public function __construct(EPredmetService $api)
    {
        parent::__construct();
        $this->api = $api;
    }

    public function handle(): int
    {
        $years = array_map('intval', explode(',', $this->option('years')));
        $register = $this->option('register');
        $level = (int) $this->option('level');
        $continue = $this->option('continue');
        $dryRun = $this->option('dry-run');

        // Sync courts first
        $this->info('Syncing court list from API...');
        $this->syncCourts();

        // Get courts to process
        $courts = Court::where('level', $level)->orderBy('name')->get();
        $this->info("Found {$courts->count()} courts at level {$level}");

        if ($dryRun) {
            $this->showDryRun($courts, $years, $register);
            return self::SUCCESS;
        }

        // Calculate total jobs
        $totalJobs = $courts->count() * count($years);
        $completed = 0;

        $this->newLine();
        $this->info("Starting batch fetch: {$courts->count()} courts × " . count($years) . " years = {$totalJobs} jobs");
        $this->newLine();

        foreach ($years as $year) {
            $this->line("═══════════════════════════════════════════════════════════════");
            $this->info("YEAR: {$year}");
            $this->line("═══════════════════════════════════════════════════════════════");

            foreach ($courts as $court) {
                $completed++;
                $progress = round(100 * $completed / $totalJobs, 1);

                // Check if already completed
                $syncLog = SyncLog::where('court_id', $court->id)
                    ->where('register', $register)
                    ->where('year', $year)
                    ->first();

                if ($syncLog && $syncLog->status === 'completed' && !$continue) {
                    $this->line("[{$progress}%] {$court->short_name} {$year}: Already completed ({$syncLog->total_saved} cases)");
                    continue;
                }

                // Skip if running
                if ($syncLog && $syncLog->status === 'running') {
                    $this->warn("[{$progress}%] {$court->short_name} {$year}: Already running, skipping");
                    continue;
                }

                $this->info("[{$progress}%] Fetching: {$court->short_name} {$year}...");

                // Call the fetch command
                $exitCode = $this->call('epredmet:fetch', [
                    '--court' => $court->external_id,
                    '--year' => $year,
                    '--register' => $register,
                    '--resume' => $continue,
                ]);

                if ($exitCode !== 0) {
                    $this->error("  → Failed with exit code {$exitCode}");
                } else {
                    $syncLog = SyncLog::where('court_id', $court->id)
                        ->where('register', $register)
                        ->where('year', $year)
                        ->first();
                    
                    $saved = $syncLog?->total_saved ?? 0;
                    $this->info("  → Completed: {$saved} cases saved");
                }

                // Rate limiting between courts
                sleep(1);
            }
        }

        $this->newLine();
        $this->showSummary($years, $register);

        return self::SUCCESS;
    }

    protected function syncCourts(): void
    {
        $apiCourts = $this->api->getCourts();

        foreach ($apiCourts as $apiCourt) {
            Court::updateOrCreate(
                ['external_id' => $apiCourt['id']],
                [
                    'name' => $apiCourt['sudNaziv'],
                    'code' => $apiCourt['sudOznaka'] ?? null,
                    'level' => $apiCourt['razina'] ?? null,
                    'county' => EPredmetService::mapCountyFromCourtName($apiCourt['sudNaziv']),
                ]
            );
        }
    }

    protected function showDryRun($courts, array $years, string $register): void
    {
        $this->info("DRY RUN - Would fetch:");
        $this->newLine();

        $table = [];
        foreach ($courts as $court) {
            foreach ($years as $year) {
                $syncLog = SyncLog::where('court_id', $court->id)
                    ->where('register', $register)
                    ->where('year', $year)
                    ->first();

                $status = match($syncLog?->status) {
                    'completed' => "✓ Done ({$syncLog->total_saved})",
                    'running' => "⏳ Running",
                    'failed' => "✗ Failed",
                    'partial' => "◐ Partial ({$syncLog->total_saved})",
                    default => "○ Pending"
                };

                $table[] = [$court->short_name, $year, $status];
            }
        }

        $this->table(['Court', 'Year', 'Status'], $table);

        $pending = collect($table)->filter(fn($r) => str_starts_with($r[2], '○'))->count();
        $this->info("Pending jobs: {$pending}");
    }

    protected function showSummary(array $years, string $register): void
    {
        $this->line("═══════════════════════════════════════════════════════════════");
        $this->info("SUMMARY");
        $this->line("═══════════════════════════════════════════════════════════════");

        $stats = SyncLog::whereIn('year', $years)
            ->where('register', $register)
            ->select(
                'year',
                DB::raw('COUNT(*) as courts'),
                DB::raw('SUM(total_saved) as total_cases'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            )
            ->groupBy('year')
            ->get();

        $this->table(
            ['Year', 'Courts', 'Total Cases', 'Completed', 'Failed'],
            $stats->map(fn($s) => [$s->year, $s->courts, $s->total_cases, $s->completed, $s->failed])
        );

        $grandTotal = $stats->sum('total_cases');
        $this->newLine();
        $this->info("Grand Total: {$grandTotal} cases across all years");
    }
}
