<?php

namespace App\Services\Informator;

use App\Models\IngestedLaw;
use Illuminate\Support\Facades\Log;

/**
 * InformatorEnrichmentService
 *
 * Enriches IngestedLaw records with cross-reference data from the Informator API.
 * Stores enrichment results in the law's metadata JSON column.
 *
 * SOT-011: Integrates Informator into the law ingest pipeline.
 */
class InformatorEnrichmentService
{
    public function __construct(
        protected InformatorClient $client,
    ) {}

    /**
     * Enrich an ingested law with Informator cross-reference data.
     */
    public function enrich(IngestedLaw $law): void
    {
        if (! config('services.informator.enrichment_enabled', false)) {
            Log::debug('Informator enrichment disabled via feature flag');
            return;
        }

        $metadata = $law->metadata ?? [];

        // Skip if already enriched
        if (isset($metadata['informator_enrichment']) && ! ($metadata['informator_enrichment']['failed'] ?? false)) {
            Log::debug('IngestedLaw already enriched via Informator', ['law_id' => $law->id]);
            return;
        }

        try {
            Log::info('Starting Informator enrichment', ['law_id' => $law->id, 'title' => $law->title]);

            $html = $this->client->fetchListingHtml(['q' => $law->title ?? $law->law_number]);
            $items = $this->client->parseListingForItems($html);

            $metadata['informator_enrichment'] = [
                'cross_references' => $items,
                'enriched_at' => now()->toIso8601String(),
                'reference_count' => count($items),
            ];

            $law->update(['metadata' => $metadata]);

            Log::info('Informator enrichment complete', [
                'law_id' => $law->id,
                'reference_count' => count($items),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Informator enrichment failed', [
                'law_id' => $law->id,
                'error' => $e->getMessage(),
            ]);

            $metadata['informator_enrichment'] = [
                'failed' => true,
                'error' => $e->getMessage(),
                'failed_at' => now()->toIso8601String(),
            ];

            $law->update(['metadata' => $metadata]);
        }
    }
}
