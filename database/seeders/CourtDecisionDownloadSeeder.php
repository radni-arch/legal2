<?php

namespace Database\Seeders;

use App\Models\CourtDecision;
use App\Services\Odluke\OdlukeClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class CourtDecisionDownloadSeeder extends Seeder
{
    /**
     * Target number of decisions to download
     */
    protected int $targetCount = 100;

    /**
     * Number of IDs to fetch per batch
     */
    protected int $batchSize = 20;

    /**
     * Delay between batches in seconds
     */
    protected int $delayBetweenBatches = 2;

    /**
     * Search query for decisions
     */
    protected string $searchQuery = '';

    /**
     * Additional search parameters
     */
    protected ?string $searchParams = null;

    /**
     * Max retry attempts for failed requests
     */
    protected int $maxRetries = 3;

    /**
     * OdlukeClient instance
     */
    protected OdlukeClient $client;

    /**
     * Constructor
     */
    public function __construct(OdlukeClient $client)
    {
        $this->client = $client;

        // Load configuration
        $this->targetCount = (int) config('testing.court_decision_download_count', 100);
        $this->searchQuery = config('testing.court_decision_download_query', '');
        $this->searchParams = config('testing.court_decision_download_params', null);
        $this->batchSize = (int) config('testing.court_decision_download_batch_size', 20);
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info("Starting download of {$this->targetCount} court decisions from Odluke API...");

        if ($this->searchQuery) {
            $this->command->info("Search query: {$this->searchQuery}");
        }

        $downloaded = 0;
        $page = 1;
        $errors = [];

        try {
            while ($downloaded < $this->targetCount) {
                $this->command->info("Fetching batch {$page} (limit: {$this->batchSize})...");

                try {
                    // Fetch IDs from the API
                    $result = $this->fetchBatchWithRetry($page);

                    if (empty($result['ids'])) {
                        $this->command->warn('No more decisions found');
                        break;
                    }

                    // Process each decision ID
                    foreach ($result['ids'] as $id) {
                        try {
                            $saved = $this->saveDecision($id);

                            if ($saved) {
                                $downloaded++;
                                $this->command->info("Downloaded {$downloaded}/{$this->targetCount} decisions");

                                if ($downloaded >= $this->targetCount) {
                                    break;
                                }
                            }
                        } catch (\Exception $e) {
                            $errors[] = [
                                'id' => $id,
                                'error' => $e->getMessage(),
                            ];
                            $this->command->error("Error processing decision {$id}: ".$e->getMessage());
                        }
                    }

                    // Rate limiting between batches
                    if ($downloaded < $this->targetCount && ! empty($result['ids'])) {
                        $this->command->info("Waiting {$this->delayBetweenBatches} seconds before next batch...");
                        sleep($this->delayBetweenBatches);
                    }

                    $page++;
                } catch (\Exception $e) {
                    $this->command->error("Error fetching batch {$page}: ".$e->getMessage());
                    Log::error('CourtDecisionDownloadSeeder batch error', [
                        'page' => $page,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    // If circuit breaker is open, stop trying
                    if (str_contains($e->getMessage(), 'circuit breaker')) {
                        $this->command->error('Circuit breaker is open. Stopping download.');
                        break;
                    }

                    break;
                }
            }
        } catch (\Exception $e) {
            $this->command->error('Fatal error during seeding: '.$e->getMessage());
            Log::error('CourtDecisionDownloadSeeder fatal error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Summary
        $this->command->newLine();
        $this->command->info('=== Download Summary ===');
        $this->command->info("Successfully downloaded: {$downloaded} decisions");
        $this->command->info('Total errors: '.count($errors));

        if (! empty($errors)) {
            $this->command->warn('Errors encountered:');
            foreach (array_slice($errors, 0, 10) as $error) {
                $this->command->line("  - {$error['id']}: {$error['error']}");
            }
            if (count($errors) > 10) {
                $this->command->line('  ... and '.(count($errors) - 10).' more');
            }
        }
    }

    /**
     * Fetch a batch of decision IDs with retry logic
     */
    protected function fetchBatchWithRetry(int $page): array
    {
        $attempt = 0;

        while ($attempt < $this->maxRetries) {
            try {
                $result = $this->client->collectIdsFromList(
                    $this->searchQuery,
                    $this->searchParams,
                    $this->batchSize,
                    $page
                );

                // Check if we got valid results
                if (isset($result['ids'])) {
                    return $result;
                }

                // No IDs but no error - might be end of results
                return ['ids' => []];
            } catch (\Exception $e) {
                $attempt++;
                $delay = pow(2, $attempt); // Exponential backoff: 2, 4, 8 seconds

                if ($attempt >= $this->maxRetries) {
                    throw $e;
                }

                $this->command->warn("Retry {$attempt}/{$this->maxRetries} after {$delay}s due to: ".$e->getMessage());
                sleep($delay);
            }
        }

        return ['ids' => []];
    }

    /**
     * Save a decision to the database
     */
    protected function saveDecision(string $id): bool
    {
        try {
            // Check if decision already exists
            if (CourtDecision::where('id', $id)->exists()) {
                $this->command->info("  Skipping {$id} (already exists)");

                return false;
            }

            // Fetch metadata
            $meta = $this->fetchMetadataWithRetry($id);

            if (empty($meta)) {
                $this->command->warn("  No metadata for {$id}, creating minimal record");
            }

            // Create or update decision
            $decision = $this->upsertDecision($id, $meta);

            Log::info('Court decision downloaded via seeder', [
                'decision_id' => $id,
                'case_number' => $decision->case_number,
                'court' => $decision->court,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to save decision', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Fetch metadata with retry logic
     */
    protected function fetchMetadataWithRetry(string $id): ?array
    {
        $attempt = 0;

        while ($attempt < $this->maxRetries) {
            try {
                return $this->client->fetchDecisionMeta($id);
            } catch (\Exception $e) {
                $attempt++;
                $delay = pow(2, $attempt);

                if ($attempt >= $this->maxRetries) {
                    return null; // Give up but don't fail the whole seeding
                }

                $this->command->warn("  Metadata retry {$attempt}/{$this->maxRetries} after {$delay}s");
                sleep($delay);
            }
        }

        return null;
    }

    /**
     * Create or update a CourtDecision from metadata
     */
    protected function upsertDecision(string $id, ?array $meta): CourtDecision
    {
        $data = [
            'id' => $id,
            'case_number' => $meta['broj_odluke'] ?? null,
            'title' => $this->generateTitle($meta),
            'court' => $meta['sud'] ?? null,
            'jurisdiction' => 'HR',
            'decision_date' => $meta['datum_odluke'] ?? null,
            'publication_date' => $meta['datum_objave'] ?? null,
            'decision_type' => $meta['vrsta_odluke'] ?? null,
            'register' => $meta['upisnik'] ?? null,
            'finality' => $meta['pravomocnost'] ?? null,
            'ecli' => $meta['ecli'] ?? null,
            'description' => $this->generateDescription($meta),
        ];

        // Remove null values to avoid overwriting existing data
        $data = array_filter($data, fn ($value) => $value !== null);

        return CourtDecision::updateOrCreate(
            ['id' => $id],
            $data
        );
    }

    /**
     * Generate a title from metadata
     */
    protected function generateTitle(?array $meta): string
    {
        if (empty($meta)) {
            return 'Court Decision';
        }

        $parts = [];

        if (! empty($meta['vrsta_odluke'])) {
            $parts[] = $meta['vrsta_odluke'];
        }

        if (! empty($meta['broj_odluke'])) {
            $parts[] = $meta['broj_odluke'];
        }

        if (! empty($meta['sud'])) {
            // Shorten court name
            $court = $meta['sud'];
            $court = str_replace('Republike Hrvatske', 'RH', $court);
            $parts[] = $court;
        }

        return ! empty($parts) ? implode(' - ', $parts) : 'Court Decision';
    }

    /**
     * Generate a description from metadata
     */
    protected function generateDescription(?array $meta): ?string
    {
        if (empty($meta)) {
            return null;
        }

        $parts = [];

        if (! empty($meta['sud'])) {
            $parts[] = "Sud: {$meta['sud']}";
        }

        if (! empty($meta['broj_odluke'])) {
            $parts[] = "Broj: {$meta['broj_odluke']}";
        }

        if (! empty($meta['datum_odluke'])) {
            $parts[] = "Datum: {$meta['datum_odluke']}";
        }

        if (! empty($meta['vrsta_odluke'])) {
            $parts[] = "Vrsta: {$meta['vrsta_odluke']}";
        }

        if (! empty($meta['pravomocnost'])) {
            $parts[] = "Pravomoćnost: {$meta['pravomocnost']}";
        }

        return ! empty($parts) ? implode(' | ', $parts) : null;
    }
}
