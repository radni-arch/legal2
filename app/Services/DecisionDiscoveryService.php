<?php

namespace App\Services;

use App\Exceptions\DecisionException;
use App\Models\CourtDecision;
use App\Services\Odluke\OdlukeClient;
use App\Services\Odluke\OdlukeIngestService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Decision Discovery Service
 *
 * Provides tools for discovering and ingesting court decisions from odluke.sudovi.hr
 * Supports keyword search, filtering, preview, and batch ingestion.
 *
 * Sprint 7: UX Features Completion - Worker G
 */
class DecisionDiscoveryService
{
    protected OdlukeClient $client;

    protected OdlukeIngestService $ingestService;

    public function __construct(
        ?OdlukeClient $client = null,
        ?OdlukeIngestService $ingestService = null
    ) {
        $this->client = $client ?? OdlukeClient::fromConfig();
        $this->ingestService = $ingestService ?? app(OdlukeIngestService::class);
    }

    /**
     * Start a new discovery search
     *
     * @param  array  $params  Search parameters
     * @return array Discovery result with ID, search params, and results
     */
    public function startDiscovery(array $params): array
    {
        $startTime = microtime(true);

        $searchQuery = $params['keywords'] ?? '';
        $court = $params['court'] ?? null;
        $dateFrom = $params['date_from'] ?? null;
        $dateTo = $params['date_to'] ?? null;
        $decisionType = $params['decision_type'] ?? null;
        $limit = (int) ($params['limit'] ?? 50);
        $page = (int) ($params['page'] ?? 1);

        // Generate discovery ID
        $discoveryId = 'disc_'.uniqid().time();

        Log::info('Starting decision discovery', [
            'discovery_id' => $discoveryId,
            'keywords' => $searchQuery,
            'court' => $court,
            'date_range' => [$dateFrom, $dateTo],
            'decision_type' => $decisionType,
            'limit' => $limit,
            'page' => $page,
        ]);

        try {
            // Phase 1: Build query parameters
            $queryBuildStart = microtime(true);
            try {
                $queryParams = $this->buildQueryParams($court, $dateFrom, $dateTo, $decisionType);
                $queryBuildDuration = microtime(true) - $queryBuildStart;

                Log::debug('Query parameters built', [
                    'discovery_id' => $discoveryId,
                    'query_params' => $queryParams,
                    'duration_ms' => round($queryBuildDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $queryBuildDuration = microtime(true) - $queryBuildStart;
                Log::error('Query parameter building failed', [
                    'discovery_id' => $discoveryId,
                    'error' => $e->getMessage(),
                    'duration_ms' => round($queryBuildDuration * 1000, 2),
                ]);
                throw new DecisionException(
                    "Failed to build query parameters: {$e->getMessage()}",
                    DecisionException::INVALID_PARAMETERS,
                    $e
                );
            }

            // Phase 2: Search odluke.sudovi.hr
            $searchStart = microtime(true);
            try {
                $result = $this->client->collectIdsFromList(
                    $searchQuery,
                    $queryParams,
                    $limit,
                    $page
                );
                $searchDuration = microtime(true) - $searchStart;

                Log::info('Decision search completed', [
                    'discovery_id' => $discoveryId,
                    'ids_found' => count($result['ids'] ?? []),
                    'duration_ms' => round($searchDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $searchDuration = microtime(true) - $searchStart;
                Log::error('Decision search failed', [
                    'discovery_id' => $discoveryId,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($searchDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw new DecisionException(
                    "Decision search failed: {$e->getMessage()}",
                    DecisionException::SEARCH_FAILED,
                    $e
                );
            }

            // Phase 3: Fetch metadata for each decision
            $metadataStart = microtime(true);
            $decisions = [];
            $metadataErrors = 0;
            foreach ($result['ids'] ?? [] as $id) {
                try {
                    $meta = $this->client->fetchDecisionMeta($id);
                    if ($meta) {
                        $meta['id'] = $id;
                        $meta['ingested'] = $this->isDecisionIngested($id, $meta['ecli'] ?? null);
                        $decisions[] = $meta;
                    }
                } catch (\Throwable $e) {
                    $metadataErrors++;
                    Log::warning('Failed to fetch decision metadata', [
                        'discovery_id' => $discoveryId,
                        'decision_id' => $id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            $metadataDuration = microtime(true) - $metadataStart;

            Log::info('Metadata fetching completed', [
                'discovery_id' => $discoveryId,
                'successful' => count($decisions),
                'errors' => $metadataErrors,
                'duration_ms' => round($metadataDuration * 1000, 2),
            ]);

            // Phase 4: Store discovery in cache
            $cacheStart = microtime(true);
            try {
                $discovery = [
                    'id' => $discoveryId,
                    'search_params' => [
                        'keywords' => $searchQuery,
                        'court' => $court,
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                        'decision_type' => $decisionType,
                        'limit' => $limit,
                        'page' => $page,
                    ],
                    'results' => $decisions,
                    'total_found' => count($decisions),
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'created_at' => now()->toIso8601String(),
                    'status' => 'completed',
                ];

                Cache::put("discovery:{$discoveryId}", $discovery, 3600);
                $cacheDuration = microtime(true) - $cacheStart;

                Log::debug('Discovery cached', [
                    'discovery_id' => $discoveryId,
                    'cache_ttl_seconds' => 3600,
                    'duration_ms' => round($cacheDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $cacheDuration = microtime(true) - $cacheStart;
                Log::error('Failed to cache discovery', [
                    'discovery_id' => $discoveryId,
                    'error' => $e->getMessage(),
                    'duration_ms' => round($cacheDuration * 1000, 2),
                ]);
                throw new DecisionException(
                    "Failed to cache discovery: {$e->getMessage()}",
                    DecisionException::CACHE_ERROR,
                    $e
                );
            }

            $totalDuration = microtime(true) - $startTime;
            Log::info('Decision discovery completed successfully', [
                'discovery_id' => $discoveryId,
                'total_found' => count($decisions),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'timing_breakdown' => [
                    'query_build_ms' => round($queryBuildDuration * 1000, 2),
                    'search_ms' => round($searchDuration * 1000, 2),
                    'metadata_ms' => round($metadataDuration * 1000, 2),
                    'cache_ms' => round($cacheDuration * 1000, 2),
                ],
            ]);

            return $discovery;
        } catch (DecisionException $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('Decision discovery failed', [
                'discovery_id' => $discoveryId,
                'error_code' => $e->getCode(),
                'error' => $e->getMessage(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);

            // Store failed discovery in cache for debugging
            try {
                $discovery = [
                    'id' => $discoveryId,
                    'search_params' => [
                        'keywords' => $searchQuery,
                        'court' => $court,
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                        'decision_type' => $decisionType,
                        'limit' => $limit,
                        'page' => $page,
                    ],
                    'results' => [],
                    'total_found' => 0,
                    'execution_time_ms' => round($totalDuration * 1000, 2),
                    'created_at' => now()->toIso8601String(),
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                ];
                Cache::put("discovery:{$discoveryId}", $discovery, 3600);
            } catch (\Throwable $cacheError) {
                Log::warning('Failed to cache failed discovery', [
                    'discovery_id' => $discoveryId,
                    'cache_error' => $cacheError->getMessage(),
                ]);
            }

            throw $e;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('Decision discovery failed with unexpected error', [
                'discovery_id' => $discoveryId,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new DecisionException(
                "Unexpected error in discovery: {$e->getMessage()}",
                DecisionException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Get discovery details by ID
     */
    public function getDiscovery(string $discoveryId): ?array
    {
        $startTime = microtime(true);

        try {
            Log::debug('Retrieving discovery from cache', [
                'discovery_id' => $discoveryId,
            ]);

            $discovery = Cache::get("discovery:{$discoveryId}");
            $duration = microtime(true) - $startTime;

            if ($discovery) {
                Log::debug('Discovery retrieved from cache', [
                    'discovery_id' => $discoveryId,
                    'duration_ms' => round($duration * 1000, 2),
                ]);
            } else {
                Log::warning('Discovery not found in cache', [
                    'discovery_id' => $discoveryId,
                    'duration_ms' => round($duration * 1000, 2),
                ]);
            }

            return $discovery;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;
            Log::error('Failed to retrieve discovery from cache', [
                'discovery_id' => $discoveryId,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * List all discoveries from cache
     */
    public function listDiscoveries(): array
    {
        // Since we're using cache, we can't easily list all discoveries
        // This would require a more persistent storage solution
        // For now, return empty array with a note
        return [
            'note' => 'Discovery listing requires persistent storage. Discoveries are cached for 1 hour.',
            'discoveries' => [],
        ];
    }

    /**
     * Ingest selected decisions
     *
     * @param  string  $discoveryId  Discovery ID
     * @param  array  $decisionIds  Array of decision IDs to ingest
     * @return array Ingestion results
     */
    public function ingestDecisions(string $discoveryId, array $decisionIds): array
    {
        $startTime = microtime(true);

        Log::info('Starting batch decision ingestion', [
            'discovery_id' => $discoveryId,
            'decision_count' => count($decisionIds),
        ]);

        try {
            $results = [
                'discovery_id' => $discoveryId,
                'requested_count' => count($decisionIds),
                'successful' => [],
                'failed' => [],
                'already_ingested' => [],
                'started_at' => now()->toIso8601String(),
            ];

            // Phase 1: Get discovery metadata
            $discoveryLoadStart = microtime(true);
            try {
                $discovery = $this->getDiscovery($discoveryId);
                $discoveryLoadDuration = microtime(true) - $discoveryLoadStart;

                $decisionMetaMap = [];
                if ($discovery && isset($discovery['results'])) {
                    foreach ($discovery['results'] as $decision) {
                        $decisionMetaMap[$decision['id']] = $decision;
                    }
                }

                Log::debug('Discovery metadata loaded', [
                    'discovery_id' => $discoveryId,
                    'metadata_count' => count($decisionMetaMap),
                    'duration_ms' => round($discoveryLoadDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $discoveryLoadDuration = microtime(true) - $discoveryLoadStart;
                Log::warning('Failed to load discovery metadata, proceeding without it', [
                    'discovery_id' => $discoveryId,
                    'error' => $e->getMessage(),
                    'duration_ms' => round($discoveryLoadDuration * 1000, 2),
                ]);
                $decisionMetaMap = [];
            }

            // Phase 2: Process each decision
            $ingestionStart = microtime(true);
            $processedCount = 0;
            foreach ($decisionIds as $index => $decisionId) {
                $decisionStart = microtime(true);
                try {
                    // Check if already ingested
                    $checkStart = microtime(true);
                    $ecli = $decisionMetaMap[$decisionId]['ecli'] ?? null;
                    if ($this->isDecisionIngested($decisionId, $ecli)) {
                        $checkDuration = microtime(true) - $checkStart;
                        $results['already_ingested'][] = $decisionId;
                        Log::debug('Decision already ingested', [
                            'decision_id' => $decisionId,
                            'ecli' => $ecli,
                            'check_duration_ms' => round($checkDuration * 1000, 2),
                        ]);
                        $processedCount++;

                        continue;
                    }

                    // Ingest decision
                    $ingestStart = microtime(true);
                    try {
                        $ingestResult = $this->ingestService->ingestByIds([$decisionId]);
                        $ingestDuration = microtime(true) - $ingestStart;

                        if (! empty($ingestResult['succeeded'])) {
                            $results['successful'][] = $decisionId;
                            Log::info('Decision ingested successfully', [
                                'decision_id' => $decisionId,
                                'ingest_duration_ms' => round($ingestDuration * 1000, 2),
                            ]);
                        } else {
                            $errorMsg = $ingestResult['errors'][$decisionId] ?? 'Unknown error';
                            $results['failed'][] = [
                                'decision_id' => $decisionId,
                                'error' => $errorMsg,
                            ];
                            Log::warning('Decision ingestion failed', [
                                'decision_id' => $decisionId,
                                'error' => $errorMsg,
                                'ingest_duration_ms' => round($ingestDuration * 1000, 2),
                            ]);
                        }
                    } catch (\Throwable $e) {
                        $ingestDuration = microtime(true) - $ingestStart;
                        $results['failed'][] = [
                            'decision_id' => $decisionId,
                            'error' => $e->getMessage(),
                        ];
                        Log::error('Decision ingestion exception', [
                            'decision_id' => $decisionId,
                            'error' => $e->getMessage(),
                            'error_class' => get_class($e),
                            'ingest_duration_ms' => round($ingestDuration * 1000, 2),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }

                    $processedCount++;
                    $decisionDuration = microtime(true) - $decisionStart;

                    // Log progress every 10 decisions
                    if ($processedCount % 10 === 0) {
                        Log::info('Batch ingestion progress', [
                            'discovery_id' => $discoveryId,
                            'processed' => $processedCount,
                            'total' => count($decisionIds),
                            'progress_pct' => round(($processedCount / count($decisionIds)) * 100, 1),
                        ]);
                    }
                } catch (\Throwable $e) {
                    $decisionDuration = microtime(true) - $decisionStart;
                    $results['failed'][] = [
                        'decision_id' => $decisionId,
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Decision processing failed', [
                        'decision_id' => $decisionId,
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'decision_duration_ms' => round($decisionDuration * 1000, 2),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $processedCount++;
                }
            }
            $ingestionDuration = microtime(true) - $ingestionStart;

            $results['completed_at'] = now()->toIso8601String();
            $results['success_count'] = count($results['successful']);
            $results['failure_count'] = count($results['failed']);
            $results['already_ingested_count'] = count($results['already_ingested']);

            $totalDuration = microtime(true) - $startTime;
            Log::info('Batch decision ingestion completed', [
                'discovery_id' => $discoveryId,
                'success_count' => $results['success_count'],
                'failure_count' => $results['failure_count'],
                'already_ingested_count' => $results['already_ingested_count'],
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'avg_per_decision_ms' => $processedCount > 0 ? round(($ingestionDuration / $processedCount) * 1000, 2) : 0,
            ]);

            return $results;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('Batch decision ingestion failed with unexpected error', [
                'discovery_id' => $discoveryId,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new DecisionException(
                "Batch ingestion failed: {$e->getMessage()}",
                DecisionException::BATCH_INGESTION_FAILED,
                $e
            );
        }
    }

    /**
     * Get discovery statistics
     */
    public function getDiscoveryStatistics(string $discoveryId): ?array
    {
        $discovery = $this->getDiscovery($discoveryId);

        if (! $discovery) {
            return null;
        }

        $totalFound = count($discovery['results'] ?? []);
        $alreadyIngested = 0;
        $notIngested = 0;
        $courtBreakdown = [];
        $decisionTypeBreakdown = [];

        foreach ($discovery['results'] ?? [] as $decision) {
            if ($decision['ingested'] ?? false) {
                $alreadyIngested++;
            } else {
                $notIngested++;
            }

            // Court breakdown
            $court = $decision['sud'] ?? 'Unknown';
            $courtBreakdown[$court] = ($courtBreakdown[$court] ?? 0) + 1;

            // Decision type breakdown
            $type = $decision['vrsta_odluke'] ?? 'Unknown';
            $decisionTypeBreakdown[$type] = ($decisionTypeBreakdown[$type] ?? 0) + 1;
        }

        return [
            'discovery_id' => $discoveryId,
            'total_found' => $totalFound,
            'already_ingested' => $alreadyIngested,
            'not_ingested' => $notIngested,
            'execution_time_ms' => $discovery['execution_time_ms'] ?? 0,
            'created_at' => $discovery['created_at'] ?? null,
            'status' => $discovery['status'] ?? 'unknown',
            'breakdown' => [
                'by_court' => $courtBreakdown,
                'by_decision_type' => $decisionTypeBreakdown,
            ],
        ];
    }

    /**
     * Check if a decision is already ingested
     *
     * Since odluke.sudovi.hr decisions are identified by ECLI in the database,
     * we need to check the metadata to determine if it's already ingested.
     * For now, we'll check by ECLI if available, otherwise return false.
     */
    protected function isDecisionIngested(string $decisionId, ?string $ecli = null): bool
    {
        if ($ecli) {
            return CourtDecision::where('ecli', $ecli)->exists();
        }

        // Without ECLI, we can't reliably check, so assume not ingested
        // The ingestion service will handle duplicates
        return false;
    }

    /**
     * Build query parameters for odluke.sudovi.hr
     */
    protected function buildQueryParams(
        ?string $court,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $decisionType
    ): string {
        $params = [];

        // Court filter
        if ($court) {
            // Map court names to odluke.sudovi.hr court codes
            $courtCode = $this->getCourtCode($court);
            if ($courtCode) {
                $params[] = "court={$courtCode}";
            }
        }

        // Date range filters
        if ($dateFrom) {
            $params[] = 'dateFrom='.urlencode($dateFrom);
        }

        if ($dateTo) {
            $params[] = 'dateTo='.urlencode($dateTo);
        }

        // Decision type filter
        if ($decisionType) {
            $params[] = 'decisionType='.urlencode($decisionType);
        }

        return implode('&', $params);
    }

    /**
     * Map court names to odluke.sudovi.hr court codes
     */
    protected function getCourtCode(string $courtName): ?string
    {
        $courtMap = [
            'Vrhovni sud' => 'VSRH',
            'Vrhovni sud Republike Hrvatske' => 'VSRH',
            'Visoki kazneni sud' => 'VKSRH',
            'Visoki kazneni sud Republike Hrvatske' => 'VKSRH',
            'Županijski sud u Osijeku' => 'ZUP-OSIJEK',
            'Županijski sud u Zagrebu' => 'ZUP-ZAGREB',
            'Županijski sud u Splitu' => 'ZUP-SPLIT',
            'Županijski sud u Rijeci' => 'ZUP-RIJEKA',
        ];

        return $courtMap[$courtName] ?? null;
    }
}
