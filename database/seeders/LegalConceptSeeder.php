<?php

namespace Database\Seeders;

use App\Services\Graph\LegalConceptGraphSyncService;
use Illuminate\Database\Seeder;

class LegalConceptSeeder extends Seeder
{
    public function run(LegalConceptGraphSyncService $service): void
    {
        $count = $service->seedConcepts();
        $this->command->info("Seeded {$count} legal concepts to Neo4j.");
    }
}
