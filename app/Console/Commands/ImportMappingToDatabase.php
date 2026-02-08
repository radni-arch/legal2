<?php

namespace App\Console\Commands;

use App\Services\CatalogService;
use Illuminate\Console\Command;

class ImportMappingToDatabase extends Command
{
    protected $signature = 'catalog:import-mapping
        {--mapping= : Path to mapping.json (default: storage/app/tagged/mappping.json)}
        {--vs= : Default vector store ID}
        {--dry : Dry run, show what would be imported}';

    protected $description = 'One-time import of mapping.json into vector_documents table';

    public function handle(CatalogService $catalog): int
    {
        $path = $this->option('mapping') ?: storage_path('app/tagged/mappping.json');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $vsId = $this->option('vs') ?: $this->ask(
            'Default Vector Store ID?',
            $catalog->getDefaultVectorStoreId()
        );

        if ($this->option('dry')) {
            $mapping = json_decode(file_get_contents($path), true);
            $this->info('[DRY RUN] Would import '.count($mapping)." entries from {$path}");
            $this->info("Target vector store: {$vsId}");

            // Show sample
            $sample = array_slice($mapping, 0, 3);
            foreach ($sample as $item) {
                $this->line('  - '.$item['file_name'].' (file_id: '.($item['file_id'] ?? 'null').')');
            }
            if (count($mapping) > 3) {
                $this->line('  ... and '.(count($mapping) - 3).' more');
            }

            return self::SUCCESS;
        }

        $this->info("Importing from: {$path}");
        $this->info("Target vector store: {$vsId}");

        $count = $catalog->importFromMapping($path, $vsId);

        $this->info("Successfully imported {$count} documents into vector_documents table.");

        return self::SUCCESS;
    }
}
