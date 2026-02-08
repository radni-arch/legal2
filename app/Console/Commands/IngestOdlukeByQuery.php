<?php

namespace App\Console\Commands;

use App\Jobs\IngestOdlukeDecision;
use App\Services\Odluke\OdlukeClient;
use App\Services\Odluke\OdlukeIngestService;
use Illuminate\Console\Command;

class IngestOdlukeByQuery extends Command
{
    protected $signature = 'odluke:ingest-query
                            {query : Search query string}
                            {--limit=50 : Max decisions to ingest from results}
                            {--params= : Optional query params string}
                            {--force : Reingest even if already ingested}
                            {--dry-run : Show IDs without ingesting}';

    protected $description = 'Search odluke.sudovi.hr by query and ingest decisions synchronously using the queue job';

    public function handle(OdlukeClient $client, OdlukeIngestService $ingestService): int
    {
        $query = trim((string) $this->argument('query'));
        if ($query === '') {
            $this->error('Query is required.');

            return 1;
        }

        $limit = max(1, (int) $this->option('limit'));
        $params = (string) ($this->option('params') ?? '');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Searching odluke.sudovi.hr...');

        $result = $client->collectIdsFromList($query, $params, limit: $limit, page: 1);

        if (isset($result['error'])) {
            $this->error('Search failed: '.$result['error']);

            return 1;
        }

        $ids = $result['ids'] ?? [];
        if (empty($ids)) {
            $this->info('No decisions found.');

            return 0;
        }

        $ids = array_values(array_unique($ids));

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

        $this->info('Ingesting decisions synchronously via job:');

        foreach ($ids as $id) {
            $this->line(' - '.$id);
            IngestOdlukeDecision::dispatchSync($id, [
                'queue' => false,
                'sync_graph' => (bool) env('ODLUKE_SYNC_GRAPH', true),
                'force' => $force,
            ]);
        }

        $this->info('Done.');

        return 0;
    }
}
