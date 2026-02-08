<?php

namespace App\Console\Commands;

use App\Models\Court;
use App\Services\EPredmetService;
use Illuminate\Console\Command;

class ListCourts extends Command
{
    protected $signature = 'epredmet:courts
                            {--sync : Sync courts from API}
                            {--level=1 : Filter by court level (1=municipal)}';

    protected $description = 'List available courts from e-Predmet system';

    protected EPredmetService $api;

    public function __construct(EPredmetService $api)
    {
        parent::__construct();
        $this->api = $api;
    }

    public function handle(): int
    {
        if ($this->option('sync')) {
            $this->syncCourts();
        }

        $level = (int) $this->option('level');

        // First try database
        $courts = Court::when($level, fn($q) => $q->where('level', $level))
            ->orderBy('name')
            ->get();

        if ($courts->isEmpty()) {
            $this->info('No courts in database. Fetching from API...');
            $this->syncCourts();
            $courts = Court::when($level, fn($q) => $q->where('level', $level))
                ->orderBy('name')
                ->get();
        }

        $this->table(
            ['ID', 'External ID', 'Name', 'Level', 'County'],
            $courts->map(fn($c) => [
                $c->id,
                $c->external_id,
                $c->short_name,
                $c->level,
                $c->county ?? '-',
            ])
        );

        $this->info("Total: {$courts->count()} courts");

        return self::SUCCESS;
    }

    protected function syncCourts(): void
    {
        $this->info('Syncing courts from API...');

        $apiCourts = $this->api->getCourts();

        $bar = $this->output->createProgressBar(count($apiCourts));

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
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Courts synced successfully.');
    }
}
