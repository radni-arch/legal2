<?php

namespace Database\Seeders;

use App\Services\Graph\LegalTopicGraphSyncService;
use Illuminate\Database\Seeder;

/**
 * Seeder for Croatian legal topic taxonomy
 *
 * Task G.1 - Legal Topic Extraction and Classification
 *
 * Seeds the legal topic taxonomy into Neo4j:
 * - Građansko pravo (Civil Law)
 * - Kazneno pravo (Criminal Law)
 * - Radno pravo (Labor Law)
 * - Upravno pravo (Administrative Law)
 * - Trgovačko pravo (Commercial Law)
 * - Obiteljsko pravo (Family Law)
 *
 * Usage:
 * php artisan db:seed --class=LegalTopicSeeder
 */
class LegalTopicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(LegalTopicGraphSyncService $service): void
    {
        $this->command->info('Seeding Croatian legal topic taxonomy to Neo4j...');

        $count = $service->seedTaxonomy();

        $this->command->info("Successfully seeded {$count} legal topics to Neo4j.");
    }
}
