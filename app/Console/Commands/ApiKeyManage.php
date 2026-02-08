<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ApiKey;
use App\Services\ApiRotator\ApiKeyRotatorService;

class ApiKeyManage extends Command
{
    protected $signature = 'apikey:manage
                            {action : list|add|remove|disable|enable|status}
                            {--provider= : Provider name (gemini|mistral|openrouter)}
                            {--key= : API key value (for add action)}
                            {--name= : Human-readable name}
                            {--model= : Model to use with this key}
                            {--id= : Key ID (for remove/disable/enable)}';

    protected $description = 'Manage API keys for the rotation system';

    public function handle(ApiKeyRotatorService $rotator): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'list' => $this->listKeys(),
            'add' => $this->addKey(),
            'remove' => $this->removeKey(),
            'disable' => $this->toggleKey(false),
            'enable' => $this->toggleKey(true),
            'status' => $this->showStatus($rotator),
            default => $this->error("Unknown action: {$action}") ?? 1,
        };
    }

    private function listKeys(): int
    {
        $keys = ApiKey::withTrashed()->get();

        if ($keys->isEmpty()) {
            $this->warn('No API keys configured.');
            return 0;
        }

        $this->table(
            ['ID', 'Name', 'Provider', 'Model', 'RPD Limit', 'Active', 'PDF'],
            $keys->map(fn($k) => [
                $k->id,
                $k->name,
                $k->provider,
                $k->model ?? 'default',
                $k->rpd_limit,
                $k->is_active ? "\u{2713}" : "\u{2717}",
                $k->supports_pdf ? "\u{2713}" : "\u{2717}",
            ])
        );

        return 0;
    }

    private function addKey(): int
    {
        $provider = $this->option('provider') ?? $this->choice(
            'Select provider',
            ['gemini', 'mistral', 'openrouter']
        );

        $key = $this->option('key') ?? $this->secret('Enter API key');

        if (!$key) {
            $this->error('API key is required');
            return 1;
        }

        $name = $this->option('name') ?? $this->ask('Name for this key', "{$provider}-" . substr(md5($key), 0, 6));

        $defaults = config("api_rotator.providers.{$provider}.models", []);
        $modelChoices = array_keys($defaults);

        $model = $this->option('model');
        if (!$model && !empty($modelChoices)) {
            $model = $this->choice('Select model', $modelChoices, 0);
        }

        $modelDefaults = $defaults[$model] ?? [];

        $apiKey = ApiKey::create([
            'name' => $name,
            'provider' => $provider,
            'api_key' => $key,
            'model' => $model,
            'rpm_limit' => $modelDefaults['rpm'] ?? 10,
            'rpd_limit' => $modelDefaults['rpd'] ?? 100,
            'tpm_limit' => $modelDefaults['tpm'] ?? 250000,
            'supports_pdf' => $modelDefaults['supports_pdf'] ?? true,
            'supports_vision' => $modelDefaults['supports_vision'] ?? true,
            'priority' => 50,
            'rpm_reset_at' => now()->addMinute(),
            'rpd_reset_at' => now()->addDay(),
        ]);

        $this->info("API key added successfully! ID: {$apiKey->id}");
        return 0;
    }

    private function removeKey(): int
    {
        $id = $this->option('id') ?? $this->ask('Enter key ID to remove');

        $key = ApiKey::find($id);
        if (!$key) {
            $this->error("Key not found: {$id}");
            return 1;
        }

        if ($this->confirm("Remove key '{$key->name}' ({$key->provider})?")) {
            $key->delete();
            $this->info('Key removed.');
        }

        return 0;
    }

    private function toggleKey(bool $enable): int
    {
        $id = $this->option('id') ?? $this->ask('Enter key ID');

        $key = ApiKey::find($id);
        if (!$key) {
            $this->error("Key not found: {$id}");
            return 1;
        }

        $key->update(['is_active' => $enable]);
        $this->info($enable ? 'Key enabled.' : 'Key disabled.');

        return 0;
    }

    private function showStatus(ApiKeyRotatorService $rotator): int
    {
        $status = $rotator->getStatus();

        if (empty($status)) {
            $this->warn('No API keys configured.');
            return 0;
        }

        $this->table(
            ['ID', 'Name', 'Provider', 'RPM', 'RPD', 'Cooldown', 'Available'],
            collect($status)->map(fn($s) => [
                $s['id'],
                $s['name'],
                $s['provider'],
                $s['rpm'],
                $s['rpd'],
                $s['cooldown_ends'] ?? '-',
                $s['available'] ? "\u{2713}" : "\u{2717}",
            ])
        );

        return 0;
    }
}
