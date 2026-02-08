<?php

namespace App\Console\Commands;

use App\Jobs\IngestEsljpDecision;
use App\Services\Esljp\EsljpIngestService;
use Illuminate\Console\Command;

class IngestEsljpByQuery extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'esljp:ingest-query
                            {query : Search query string}
                            {--limit=200 : Max decisions to ingest from results}
                            {--count=200 : Search result count to request}
                            {--force : Reingest even if already ingested}
                            {--dry-run : Show IDs without ingesting}';

    /**
     * The console command description.
     */
    protected $description = 'Search ESLJP decisions by text query and ingest them synchronously using the queue job';

    /**
     * Execute the console command.
     */
    public function handle(EsljpIngestService $ingestService): int
    {
        $query = trim((string) $this->argument('query'));
        if ($query === '') {
            $this->error('Query is required.');

            return 1;
        }

        $limit = max(1, (int) $this->option('limit'));
        $count = max(1, (int) $this->option('count'));
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Searching ESLJP decisions...');

        $result = $ingestService->searchList($query, [
            'count' => $count,
        ]);

        if (isset($result['error'])) {
            $this->error('Search failed: '.$result['error']);

            return 1;
        }

        $items = $result['items'] ?? [];
        if (empty($items)) {
            $this->info('No ESLJP decisions found.');

            return 0;
        }

        $ids = [];
        foreach ($items as $item) {
            if (! empty($item['id'])) {
                $ids[] = $item['id'];
            }
        }

        $ids = array_values(array_unique($ids));
        $ids = array_slice($ids, 0, $limit);

        $totalFound = count($ids);
        if (! $force) {
            $filtered = $ingestService->filterAlreadyIngestedIds($ids);
            $skipped = $totalFound - count($filtered);
            if ($skipped > 0) {
                $this->info("Skipping {$skipped} already-ingested decision(s).");
            }
            $ids = $filtered;
        }

        if (empty($ids)) {
            $this->info('Nothing to ingest after filtering.');

            return 0;
        }

        if ($dryRun) {
            $this->info('Dry run - would ingest the following IDs:');
            foreach ($ids as $id) {
                $this->line(' - '.$id);
            }

            return 0;
        }

        $this->info('Ingesting ESLJP decisions synchronously via job:');

        foreach ($ids as $id) {
            $this->line(' - '.$id);
            IngestEsljpDecision::dispatchSync($id, [
                'queue' => false,
                'sync_graph' => (bool) config('esljp.sync_graph', false),
                'force' => $force,
            ]);
        }

        $this->info('Done.');

        return 0;
    }
}
