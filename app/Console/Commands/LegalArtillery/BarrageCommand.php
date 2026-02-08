<?php

namespace App\Console\Commands\LegalArtillery;

use App\Agents\Contracts\LegalArtilleryAgentContract;
use App\DTOs\DocumentProfile;
use App\Models\DocumentGenerationRun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Console\Command;

class BarrageCommand extends Command
{
    protected $signature = 'legal:barrage
        {--profiles= : Comma-separated lista profila (default: scenario profili)}
        {--no-send : Samo generiraj bez slanja}
        {--draft : Spremi sve kao draftove}';

    protected $description = 'Masovna paljba - generiraj i ispali sve dopise odjednom';

    public function handle(): int
    {
        $agent = app(LegalArtilleryAgentContract::class);
        $userId = $this->resolveUserId();

        // Determine which profiles to fire
        $profileKeys = $this->resolveProfiles();
        $targetCount = $profileKeys ? count($profileKeys) : 'scenario profili';

        $this->info("BARRAGE MODE - Ciljeva: " . $targetCount);
        $this->newLine();

        $sendEmail = ! $this->option('no-send') && config('legal-artillery.gmail.enabled');
        $asDraft = (bool) $this->option('draft');
        $barrageResults = $agent->barrage($profileKeys, $userId, $sendEmail, $asDraft);

        $results = [];
        foreach ($barrageResults as $key => $result) {
            $profile = DocumentProfile::fromConfig($key);
            $this->info("Paljba: {$profile->name}...");

            if ($result instanceof DocumentGenerationRun) {
                $docxPath = $result->model_config['docx_path'] ?? '-';
                $results[] = [
                    'profile' => $key,
                    'status' => 'HIT ' . $result->status,
                    'path' => $docxPath,
                ];
                $this->info("  Pogodak: {$key}");
                continue;
            }

            $error = $result['error'] ?? 'Unknown error';
            $results[] = [
                'profile' => $key,
                'status' => 'MISS ' . $error,
                'path' => '-',
            ];
            $this->error("  Promasaj: {$key} - {$error}");
        }

        $this->newLine();
        $this->table(['Profil', 'Status', 'Putanja'], $results);

        $hits = count(array_filter($results, fn($r) => str_starts_with($r['status'], 'HIT')));
        $this->newLine();
        $this->info("Rezultat: {$hits}/" . count($results) . " pogodaka");

        return self::SUCCESS;
    }

    private function resolveUserId(): int
    {
        return Auth::id() ?? 1;
    }

    private function resolveProfiles(): array
    {
        if ($this->option('profiles')) {
            return explode(',', $this->option('profiles'));
        }

        return [];
    }
}
