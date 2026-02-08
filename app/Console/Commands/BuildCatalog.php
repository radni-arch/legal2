<?php

namespace App\Console\Commands;

use App\Models\VectorDocument;
use App\Services\CatalogService;
use Illuminate\Console\Command;

class BuildCatalog extends Command
{
    protected $signature = 'catalog:build
        {vs : Vector Store ID}
        {--upload : Upload catalog to VS after building}
        {--out= : Output directory (default: from config)}
        {--dry : Dry run, show what would be built}';

    protected $description = 'Build catalog.json from database and optionally upload to VS';

    public function handle(CatalogService $catalog): int
    {
        $vsId = $this->argument('vs');

        $count = VectorDocument::forStore($vsId)
            ->whereIn('status', [
                VectorDocument::STATUS_UPLOADED,
                VectorDocument::STATUS_CATALOGED,
            ])
            ->count();

        if ($count === 0) {
            $this->warn("No documents found for VS {$vsId}");

            return self::SUCCESS;
        }

        $this->info("Building catalog for VS {$vsId} ({$count} documents)...");

        if ($this->option('dry')) {
            $entries = [];
            $docs = VectorDocument::forStore($vsId)
                ->whereIn('status', [
                    VectorDocument::STATUS_UPLOADED,
                    VectorDocument::STATUS_CATALOGED,
                ])
                ->limit(5)
                ->get();

            foreach ($docs as $doc) {
                $entries[] = $catalog->buildCatalogEntry($doc);
            }

            $this->line("\n[DRY RUN] Sample catalog entries:");
            $this->line(json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            if ($count > 5) {
                $this->line("\n... and ".($count - 5).' more documents');
            }

            return self::SUCCESS;
        }

        $outDir = $this->option('out') ?: config('vector-stores.catalog_dir', storage_path('app/catalog'));
        $upload = (bool) $this->option('upload');

        $result = $catalog->buildAndUploadCatalog($vsId, $outDir, $upload);

        $this->info("Catalog built: {$result['entries']} entries");
        $this->info("Saved to: {$result['path']}");

        if ($upload && $result['file_id']) {
            $this->info("Uploaded as: {$result['file_id']}");
        }

        return self::SUCCESS;
    }
}
