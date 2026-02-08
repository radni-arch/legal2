<?php

namespace App\Console\Commands\LegalArtillery;

use App\Agents\Contracts\LegalArtilleryAgentContract;
use App\DTOs\DocumentProfile;
use App\Services\LegalArtillery\DocumentInventory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class FireCommand extends Command
{
    protected $signature = 'legal:fire
        {profile? : Kljuc profila dokumenta (npr. predsjednik_suda)}
        {--list : Prikazi sve dostupne profile}
        {--audit : Prikazi audit dostupnosti dokumenata}
        {--no-send : Generiraj dokument bez slanja emailom}
        {--draft : Spremi kao Gmail draft umjesto slanja}
        {--to= : Override email adrese primatelja}
        {--context=* : Dodatan kontekst (key=value)}
        {--dry-run : Samo generiraj outline bez pisanja}
        {--confirm-escalation : Potvrdi generiranje eskalacijskog dopisa}';

    protected $description = 'Pravna Artiljerija - generiraj i ispali pravni dopis';

    public function handle(): int
    {
        // List mode
        if ($this->option('list')) {
            return $this->listProfiles();
        }

        if ($this->option('audit')) {
            $inventory = new DocumentInventory();
            $audit = $inventory->audit();

            $this->info('📦 Inventar dokumenata:');
            $this->info('  ✅ Pronađeno: ' . count($audit['found']));
            $this->warn('  ❌ Nedostaje: ' . count($audit['missing']));
            if (count($audit['critical_missing']) > 0) {
                $this->error('  🔴 KRITIČNI koji nedostaju: ' . count($audit['critical_missing']));
                foreach ($audit['critical_missing'] as $doc) {
                    $this->error("     - {$doc['description_hr']}");
                }
            }

            return self::SUCCESS;
        }

        $profileKey = $this->argument('profile');
        if (!$profileKey) {
            $this->error('Navedi profil. Koristi --list za popis dostupnih profila.');
            return self::FAILURE;
        }

        // Validate profile
        try {
            $profile = DocumentProfile::fromConfig($profileKey);
        } catch (\InvalidArgumentException $e) {
            $this->error("Profil '{$profileKey}' ne postoji. Koristi --list za popis.");
            return self::FAILURE;
        }

        $this->info("Cilj: {$profile->name}");
        $this->info("Primatelj: {$profile->recipientLine()}");
        $this->info("Pravni temelj: " . implode(', ', $profile->legalBasis));
        $this->newLine();

        $confirmEscalation = (bool) $this->option('confirm-escalation');
        if ($profile->requiresEscalationConfirmation() && ! $confirmEscalation) {
            $this->error('Ovaj profil je eskalacijski. Dodaj --confirm-escalation za nastavak.');
            return self::FAILURE;
        }

        // Parse additional context
        $additionalContext = [];
        foreach ($this->option('context') as $ctx) {
            [$key, $value] = explode('=', $ctx, 2);
            $additionalContext[$key] = $value;
        }

        if ($confirmEscalation) {
            $additionalContext['escalation_confirmed'] = true;
        }

        // Step 1: Generate
        $this->info('Faza 1: Rekurzivno generiranje sadrzaja...');

        if ($this->option('dry-run')) {
            $this->info('Outline (iz profila):');
            foreach ($profile->structure as $index => $section) {
                $this->line('  ' . ($index + 1) . '. ' . $section);
            }
            return self::SUCCESS;
        }

        $agent = app(LegalArtilleryAgentContract::class);
        $userId = $this->resolveUserId();
        $sendEmail = ! $this->option('no-send') && config('legal-artillery.gmail.enabled');
        $to = $this->option('to') ?? null;
        $isDraft = (bool) $this->option('draft');

        $run = $agent->fire(
            $profileKey,
            $userId,
            $additionalContext,
            $sendEmail,
            $isDraft,
            $to,
        );

        $docxPath = $run->model_config['docx_path'] ?? null;
        if ($docxPath) {
            $this->info("Dokument: {$docxPath}");
        }

        if ($sendEmail) {
            $this->warn('Dokument je generiran i ceka odobrenje prije slanja.');
        }

        $this->newLine();
        $this->info('Artiljerija pogodila cilj!');

        return self::SUCCESS;
    }

    private function listProfiles(): int
    {
        $profiles = DocumentProfile::all();
        $this->info('Dostupni profili pravne artiljerije:');
        $this->newLine();

        $rows = [];
        foreach ($profiles as $profile) {
            $rows[] = [
                $profile->key,
                $profile->name,
                $profile->recipient['institution'] ?? '-',
                $profile->metadata['priority'] ?? '-',
                $profile->tone,
            ];
        }

        $this->table(
            ['Kljuc', 'Naziv', 'Forum', 'Prioritet', 'Ton'],
            $rows,
        );

        $this->newLine();
        $this->line('Koristenje: php artisan legal:fire <kljuc> [--no-send] [--draft] [--dry-run]');

        return self::SUCCESS;
    }

    private function resolveUserId(): int
    {
        return Auth::id() ?? 1;
    }
}
