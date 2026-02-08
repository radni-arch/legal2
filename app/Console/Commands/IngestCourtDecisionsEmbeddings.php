<?php

namespace App\Console\Commands;

use App\Services\Odluke\OdlukeClient;
use App\Services\Odluke\OdlukeIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class IngestCourtDecisionsEmbeddings extends Command
{
    /**
     * @var string
     */
    protected $signature = 'decisions:ingest
        {--id=* : One or more decision IDs}
        {--query= : Optional search query to collect IDs}
        {--params= : Optional raw query params from the site}
        {--limit=50 : Max IDs to collect when using --query/--params}
        {--page=1 : Page number for search results}
        {--prefer=auto : Source preference: auto|html|pdf}
        {--model= : Embedding model override}
        {--chunk=1500 : Chunk size in characters}
        {--overlap=200 : Overlap in characters}
        {--sync-graph : Sync ingested decisions to graph database}
        {--force : Force re-ingestion of already-processed decisions}
        {--dry : Dry run, do not persist}';

    /**
     * @var string
     */
    protected $description = 'Consolidated court decisions ingestion: search→meta→download→ingest via Odluke flow';

    public function __construct(
        protected OdlukeIngestService $ingest,
        protected OdlukeClient $client
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting court decisions ingestion from odluke.sudovi.hr');

        $ids = (array) $this->option('id');

        // Collect IDs by search if none provided
        if (empty($ids) && ($this->option('query') !== null || $this->option('params') !== null)) {
            $q = $this->option('query');
            $params = $this->option('params');
            $limit = (int) $this->option('limit');
            $page = (int) $this->option('page');

            $this->line(sprintf(
                'Searching: query="%s" params="%s" limit=%d page=%d',
                $q ?? 'none',
                $params ?? 'none',
                $limit,
                $page
            ));

            try {
                $col = $this->client->collectIdsFromList($q, $params, $limit, $page);
                $ids = $col['ids'] ?? [];
                $this->info('Collected IDs: '.count($ids));
            } catch (\Throwable $e) {
                $this->error('Failed to collect IDs: '.$e->getMessage());
                Log::error('Odluke search failed', [
                    'query' => $q,
                    'params' => $params,
                    'error' => $e->getMessage(),
                ]);

                return self::FAILURE;
            }
        }

        if (empty($ids)) {
            $this->error('Provide at least one --id, or use --query/--params to collect IDs.');

            return self::FAILURE;
        }

        $this->info(sprintf('Processing %d decision(s)...', count($ids)));

        $prefer = (string) $this->option('prefer') ?: 'auto';
        $dry = (bool) $this->option('dry');
        $force = (bool) $this->option('force');
        $model = $this->option('model') ?: config('openai.models.embeddings');
        $chunk = (int) ($this->option('chunk') ?? 1500);
        $overlap = (int) ($this->option('overlap') ?? 200);

        if (! in_array($prefer, ['auto', 'html', 'pdf'], true)) {
            $this->warn("Invalid --prefer value '{$prefer}', using 'auto'");
            $prefer = 'auto';
        }

        if ($dry) {
            $this->warn('DRY RUN MODE - No data will be persisted');
        }

        if ($force) {
            $this->warn('FORCE MODE - Re-ingesting already-processed decisions');
        }

        $this->line(sprintf(
            'Options: model=%s chunk=%d overlap=%d prefer=%s force=%s',
            $model,
            $chunk,
            $overlap,
            $prefer,
            $force ? 'yes' : 'no'
        ));

        // Run ingestion with progress bar
        $progressBar = $this->output->createProgressBar(count($ids));
        $progressBar->start();

        try {
            $res = $this->ingest->ingestByIds($ids, [
                'prefer' => $prefer,
                'dry' => $dry,
                'force' => $force,
                'model' => $model,
                'chunk_chars' => $chunk,
                'overlap' => $overlap,
                'sync_graph' => $this->option('sync-graph'), // Pass flag to service layer
            ]);

            $progressBar->finish();
            $this->newLine(2);

            // Display results in table format
            $this->info('Ingestion completed!');
            $this->newLine();

            $tableData = [
                ['IDs Total', $res['ids_total'] ?? count($ids)],
                ['Already Ingested (skipped)', $res['already_ingested'] ?? 0],
                ['IDs Processed', $res['ids_processed'] ?? 0],
                ['Documents Inserted', $res['inserted'] ?? 0],
                ['Chunks Generated', $res['would_chunks'] ?? 0],
                ['Errors', $res['errors'] ?? 0],
                ['Skipped (empty text)', $res['skipped'] ?? 0],
                ['Model', $res['model'] ?? 'unknown'],
                ['Force Mode', $force ? 'Yes' : 'No'],
                ['Dry Run', ($res['dry'] ?? false) ? 'Yes' : 'No'],
            ];

            // Add graph sync stats if enabled
            if ($this->option('sync-graph')) {
                $tableData[] = ['Graph Synced', $res['graph_synced'] ?? 0];
                $tableData[] = ['Graph Errors', $res['graph_errors'] ?? 0];
            }

            $this->table(
                ['Metric', 'Value'],
                $tableData
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $progressBar->finish();
            $this->newLine(2);
            $this->error('Ingestion failed: '.$e->getMessage());
            Log::error('Odluke ingestion failed', [
                'ids_count' => count($ids),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
