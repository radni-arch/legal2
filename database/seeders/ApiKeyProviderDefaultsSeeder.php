<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * ApiKeyProviderDefaultsSeeder
 *
 * Displays the default provider configurations for the API Key Rotation system.
 * This seeder is informational - it shows available providers, models, and
 * their rate limits to help users understand the system configuration.
 *
 * Usage:
 *   php artisan db:seed --class=ApiKeyProviderDefaultsSeeder
 *
 * To add actual API keys:
 *   php artisan apikey:manage add
 */
class ApiKeyProviderDefaultsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Provider Default Configurations:');
        $this->command->newLine();
        $this->command->table(
            ['Provider', 'Model', 'RPM', 'RPD', 'TPM', 'Context', 'PDF Support'],
            [
                ['gemini', 'gemini-2.5-flash-preview-05-20', 10, 250, 250000, '1M', 'Yes'],
                ['gemini', 'gemini-2.5-pro', 5, 50, 250000, '1M', 'Yes'],
                ['gemini', 'gemini-2.0-flash-exp', 10, 100, 250000, '1M', 'Yes'],
                ['mistral', 'mistral-small-latest', 60, 1000, 500000, '128K', 'Paid OCR'],
                ['mistral', 'pixtral-12b-latest', 60, 1000, 500000, '128K', 'Vision only'],
                ['openrouter', 'google/gemini-2.0-flash-exp:free', 20, 50, 0, '1M', 'Yes'],
                ['openrouter', 'deepseek/deepseek-r1:free', 20, 50, 0, '164K', 'No'],
                ['openrouter', 'meta-llama/llama-3.3-70b-instruct:free', 20, 1000, 0, '131K', 'No'],
            ]
        );

        $this->command->newLine();
        $this->command->info('Add keys using: php artisan apikey:manage add');
    }
}
