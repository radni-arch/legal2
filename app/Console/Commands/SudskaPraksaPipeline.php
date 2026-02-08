<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SudskaPraksaPipeline extends Command
{
    protected $signature = 'sudska-praksa:pipeline
        {--case-description= : Opis slucaja}
        {--case-file= : Datoteka s opisom slucaja}
        {--expand : Auto-expand kljucne rijeci}
        {--courts=vks,vps,vs,zs : Sudovi}';

    protected $description = 'Cijeli pipeline: AI generiranje -> pretraga -> spremanje';

    public function handle(): int
    {
        $this->info("Sudska Praksa Pipeline");
        $this->line("=".str_repeat("=", 40));
        $this->newLine();

        // Get case description from options or prompt
        $description = $this->option('case-description');

        if (!$description && $this->option('case-file')) {
            $filePath = $this->option('case-file');
            if (file_exists($filePath)) {
                $description = $filePath; // Will be read by generate-keywords command
            } elseif (file_exists(base_path($filePath))) {
                $description = base_path($filePath);
            } else {
                $this->error("Datoteka nije pronadena: {$filePath}");
                return self::FAILURE;
            }
        }

        if (!$description) {
            $description = $this->ask('Opisi slucaj (cinjenicno stanje, pravni problemi):');
        }

        if (!$description) {
            $this->error('Opis slucaja je obavezan.');
            return self::FAILURE;
        }

        // Step 1: Generate keywords
        $this->info("KORAK 1/3: Generiranje kljucnih rijeci");
        $this->line("-".str_repeat("-", 40));

        $keywordsFile = 'storage/app/keywords/pipeline_' . now()->format('Y-m-d_H-i-s') . '.json';

        $exitCode = $this->call('sudska-praksa:generate-keywords', [
            '--case-description' => $description,
            '--output' => $keywordsFile,
        ]);

        if ($exitCode !== self::SUCCESS) {
            $this->error("Generiranje kljucnih rijeci nije uspjelo.");
            return self::FAILURE;
        }

        $this->newLine();

        // Step 2: Run search with persistence
        $this->info("KORAK 2/3: Pretraga odluke.sudovi.hr");
        $this->line("-".str_repeat("-", 40));

        $searchArgs = [
            '--keywords-file' => $keywordsFile,
            '--persist' => true,
            '--courts' => $this->option('courts'),
            '--format' => 'all',
        ];

        if ($this->option('expand')) {
            $searchArgs['--expand'] = true;
        }

        $exitCode = $this->call('sudska-praksa:search', $searchArgs);

        if ($exitCode !== self::SUCCESS) {
            $this->error("Pretraga nije uspjela.");
            return self::FAILURE;
        }

        $this->newLine();

        // Step 3: Summary
        $this->info("KORAK 3/3: Sazetak");
        $this->line("-".str_repeat("-", 40));
        $this->newLine();

        $this->info("Pipeline zavrsen uspjesno!");
        $this->line("   Keywords: " . base_path($keywordsFile));
        $this->line("   Rezultati: " . base_path(config('sudska-praksa.output_dir')));
        $this->newLine();

        $this->info("Sljedeci koraci:");
        $this->line("   1. Pregledaj rezultate u storage/app/results/");
        $this->line("   2. Koristi php artisan sudska-praksa:compare za usporedbu s prethodnim runovima");

        return self::SUCCESS;
    }
}
