<?php

namespace App\Services;

use App\Contracts\External\EoglasnaServiceInterface;
use App\Exceptions\EoglasnaException;
use App\Models\EoglasnaCourt;
use App\Models\EoglasnaKeyword;
use App\Repositories\EoglasnaKeywordMatchRepository;
use App\Repositories\EoglasnaKeywordRepository;
use App\Repositories\EoglasnaNoticeRepository;
use App\Repositories\EoglasnaOsijekMonitoringRepository;
use App\Services\Eoglasna\Api\EoglasnaHttpClient;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EoglasnaService implements EoglasnaServiceInterface
{
    public function __construct(
        protected EoglasnaHttpClient $client,
        protected EoglasnaNoticeRepository $noticeRepo,
        protected EoglasnaKeywordRepository $keywordRepo,
        protected EoglasnaKeywordMatchRepository $matchRepo,
        protected EoglasnaOsijekMonitoringRepository $osijekRepo,
    ) {}

    /**
     * Monitor all enabled keywords
     *
     * @throws EoglasnaException if monitoring fails
     */
    public function monitorKeywords(): void
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        try {
            Log::info('Eoglasna keyword monitoring initiated', [
                'user_id' => auth()->id(),
            ]);

            $keywords = $this->keywordRepo->getEnabled();

            $monitored = 0;
            $failed = 0;

            foreach ($keywords as $keyword) {
                try {
                    $this->monitorSingleKeyword($keyword);
                    $monitored++;
                } catch (\Exception $e) {
                    $failed++;
                    Log::error('Failed to monitor keyword', [
                        'keyword_id' => $keyword->id,
                        'query' => $keyword->query,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue with other keywords
                }
            }

            Log::info('Eoglasna keyword monitoring completed', [
                'total_keywords' => count($keywords),
                'monitored' => $monitored,
                'failed' => $failed,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

        } catch (\Exception $e) {
            Log::error('Eoglasna keyword monitoring failed', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new EoglasnaException(
                'Keyword monitoring failed: '.$e->getMessage(),
                EoglasnaException::MONITORING_FAILED,
                $e
            );
        }
    }

    protected function monitorSingleKeyword(EoglasnaKeyword $keyword): void
    {
        $query = trim($keyword->query);
        if ($query === '') {
            return;
        }

        $this->keywordRepo->touchRun($keyword);

        $page = 0;
        $newestDatePublished = $keyword->last_date_published; // track latest in this run
        $hasMore = true;

        while ($hasMore) {
            $filter = ['text' => $query];
            $response = $this->callNoticeEndpointForScope($keyword->scope, $filter, $page);
            $content = Arr::get($response, 'content', []);
            if (empty($content)) {
                break;
            }

            foreach ($content as $payload) {
                // Stop if older or equal than last cursor
                $datePublished = Arr::get($payload, 'datePublished');
                $datePublishedTs = $datePublished ? Carbon::parse($datePublished) : null;

                if ($keyword->last_date_published && $datePublishedTs && $datePublishedTs->lessThanOrEqualTo($keyword->last_date_published)) {
                    // reached already-processed zone; end this keyword scanning
                    $hasMore = false;
                    break;
                }

                $notice = $this->noticeRepo->upsertFromApiPayload($payload);

                // Record match metadata
                $matchedFields = $this->detectMatchFields($payload, $query);
                $this->matchRepo->recordMatch($keyword->id, $notice->uuid, $matchedFields);

                if (! $newestDatePublished || ($datePublishedTs && $datePublishedTs->greaterThan($newestDatePublished))) {
                    $newestDatePublished = $datePublishedTs;
                }
            }

            // Next page if still has more
            if ($hasMore) {
                $page++;
                $current = (int) Arr::get($response, 'number', $page - 1);
                $totalPages = (int) Arr::get($response, 'totalPages', $page);
                if ($page >= $totalPages) {
                    $hasMore = false;
                }
            }
        }

        // Update cursor
        if ($newestDatePublished && (! $keyword->last_date_published || $newestDatePublished->greaterThan($keyword->last_date_published))) {
            $this->keywordRepo->updateCursor($keyword, $newestDatePublished);
        }
    }

    protected function callNoticeEndpointForScope(string $scope, array $filter, int $page): array
    {
        $sort = config('eoglasna.default_sort', 'datePublished,desc');

        return match ($scope) {
            'court' => $this->client->getCourtNotices($filter, $page, $sort),
            'institution' => $this->client->getInstitutionNotices($filter, $page, $sort),
            'court_legal_bankruptcy' => $this->client->getCourtNoticesLegalPersonBankruptcy($filter, $page, $sort),
            'court_natural_bankruptcy' => $this->client->getCourtNoticesNaturalPersonBankruptcy($filter, $page, $sort),
            default => $this->client->getNotices($filter, $page, $sort), // 'notice' (all)
        };
    }

    protected function detectMatchFields(array $payload, string $query): array
    {
        $q = mb_strtolower($query);
        $fields = [];

        $title = (string) Arr::get($payload, 'title');
        if ($title && mb_stripos($title, $q) !== false) {
            $fields[] = 'title';
        }

        $caseNumber = (string) Arr::get($payload, 'caseNumber');
        if ($caseNumber && mb_stripos($caseNumber, $q) !== false) {
            $fields[] = 'caseNumber';
        }

        $participants = Arr::get($payload, 'participants', []);
        foreach ($participants as $p) {
            $titles = (string) Arr::get($p, 'titles');
            if ($titles && mb_stripos($titles, $q) !== false) {
                $fields[] = 'participants.titles';
                break;
            }
        }

        if (empty($fields)) {
            $fields[] = 'api.text';
        }

        return ['fields' => array_values(array_unique($fields))];
    }

    /**
     * Deep scan for exact term matches
     *
     * @param  string  $term  Search term
     * @param  string  $scope  Search scope
     * @return int Number of records saved
     *
     * @throws EoglasnaException if scan fails
     */
    public function deepScanExact(string $term, string $scope = 'notice'): int
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('EoglasnaService: deepScanExact initiated', [
            'term' => $term,
            'scope' => $scope,
            'user_id' => auth()->id(),
        ]);

        try {
            $term = trim($term);

            if ($term === '') {
                Log::warning('EoglasnaService: deepScanExact called with empty term');

                return 0;
            }

            $page = 0;
            $countSaved = 0;
            $maxPages = (int) config('eoglasna.deep_scan_max_pages', 500);
            $sort = config('eoglasna.default_sort', 'datePublished,desc');

            while (true) {
                $resp = $this->callNoticeEndpointWithErrorHandling($scope, ['text' => $term], $page);
                $content = Arr::get($resp, 'content', []);

                if (empty($content)) {
                    break;
                }

                foreach ($content as $payload) {
                    // Local exact phrase match (case-insensitive) across key fields
                    if ($this->isExactMatch($payload, $term)) {
                        $this->noticeRepo->upsertFromApiPayload($payload);
                        $countSaved++;
                    }
                }

                $current = (int) Arr::get($resp, 'number', $page);
                $totalPages = (int) Arr::get($resp, 'totalPages', $page + 1);
                $page++;

                if ($page >= $totalPages) {
                    break;
                }

                if ($page >= $maxPages) {
                    Log::warning('deepScanExact reached configured page cap', [
                        'scope' => $scope,
                        'term' => $term,
                        'page' => $page,
                    ]);
                    break;
                }
            }

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('EoglasnaService: deepScanExact completed', [
                'term' => $term,
                'scope' => $scope,
                'saved' => $countSaved,
                'pages_scanned' => $page,
                'duration_ms' => round($duration, 2),
            ]);

            return $countSaved;

        } catch (EoglasnaException $e) {
            Log::error('EoglasnaService: deepScanExact failed with EoglasnaException', [
                'term' => $term,
                'scope' => $scope,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('EoglasnaService: deepScanExact failed with unexpected exception', [
                'term' => $term,
                'scope' => $scope,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new EoglasnaException(
                'Deep scan failed: '.$e->getMessage(),
                EoglasnaException::MONITORING_FAILED,
                $e
            );
        }
    }

    protected function isExactMatch(array $payload, string $term): bool
    {
        $needle = mb_strtolower($term);

        $fields = [];
        $fields[] = (string) Arr::get($payload, 'title');
        $fields[] = (string) Arr::get($payload, 'caseNumber');

        $participants = Arr::get($payload, 'participants', []);
        foreach ($participants as $p) {
            $fields[] = (string) Arr::get($p, 'titles');
        }

        foreach ($fields as $f) {
            if ($f && (mb_stripos(mb_strtolower($f), $needle) !== false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sync courts from Eoglasna API
     *
     * @return int Number of courts synced
     *
     * @throws EoglasnaException if sync fails
     */
    public function syncCourts(): int
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('EoglasnaService: syncCourts initiated', [
            'user_id' => auth()->id(),
        ]);

        try {

            $list = $this->fetchCourtsWithErrorHandling();
            $count = 0;

            foreach ($list as $court) {
                $code = Arr::get($court, 'code');
                $name = Arr::get($court, 'name');
                $type = Arr::get($court, 'courtType');

                if (! $code) {
                    Log::debug('Skipping court without code', ['court' => $court]);

                    continue;
                }

                EoglasnaCourt::updateOrCreate(
                    ['code' => $code],
                    ['name' => $name, 'court_type' => $type]
                );
                $count++;
            }

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('EoglasnaService: syncCourts completed', [
                'synced' => $count,
                'duration_ms' => round($duration, 2),
            ]);

            return $count;

        } catch (EoglasnaException $e) {
            Log::error('EoglasnaService: syncCourts failed with EoglasnaException', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('EoglasnaService: syncCourts failed with unexpected exception', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new EoglasnaException(
                'Court sync failed: '.$e->getMessage(),
                EoglasnaException::SYNC_FAILED,
                $e
            );
        }
    }

    /**
     * Fetch and upsert ALL court notices for Općinski sud u Osijeku into eoglasna_osijek_monitoring.
     * Always scans all pages (no cursor), respecting API throttling.
     *
     * @return int Number of records upserted in this run
     *
     * @throws EoglasnaException if monitoring fails or court not found
     */
    public function monitorOsijekCourtAll(): int
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('EoglasnaService: monitorOsijekCourtAll initiated', [
            'user_id' => auth()->id(),
        ]);

        try {

            // Try to locate court code locally first
            $court = $this->findOsijekCourt();

            if (! $court) {
                // Fallback: sync courts from API, then search again
                try {
                    $this->syncCourts();
                    $court = $this->findOsijekCourt();
                } catch (\Exception $e) {
                    Log::warning('Failed to sync courts for Osijek monitoring', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (! $court) {
                throw new EoglasnaException(
                    'Osijek municipal court not found in court registry',
                    EoglasnaException::COURT_NOT_FOUND
                );
            }

            $code = $court->code;
            $page = 0;
            $totalUpserted = 0;
            $sort = config('eoglasna.default_sort', 'datePublished,desc');

            while (true) {
                $resp = $this->fetchCourtNoticesWithErrorHandling($code, $page, $sort);
                $content = Arr::get($resp, 'content', []);

                if (empty($content)) {
                    break;
                }

                foreach ($content as $payload) {
                    $this->osijekRepo->upsertFromApiPayload($payload);
                    $totalUpserted++;
                }

                $current = (int) Arr::get($resp, 'number', $page);
                $totalPages = (int) Arr::get($resp, 'totalPages', $page + 1);
                $page++;

                if ($page >= $totalPages) {
                    break;
                }
            }

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('EoglasnaService: monitorOsijekCourtAll completed', [
                'court_code' => $code,
                'upserted' => $totalUpserted,
                'pages_scanned' => $page,
                'duration_ms' => round($duration, 2),
            ]);

            return $totalUpserted;

        } catch (EoglasnaException $e) {
            Log::error('EoglasnaService: monitorOsijekCourtAll failed with EoglasnaException', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('EoglasnaService: monitorOsijekCourtAll failed with unexpected exception', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new EoglasnaException(
                'Osijek court monitoring failed: '.$e->getMessage(),
                EoglasnaException::MONITORING_FAILED,
                $e
            );
        }
    }

    /**
     * Call notice endpoint with error handling
     *
     * @throws EoglasnaException
     */
    protected function callNoticeEndpointWithErrorHandling(string $scope, array $filter, int $page): array
    {
        try {
            return $this->callNoticeEndpointForScope($scope, $filter, $page);

        } catch (\Exception $e) {
            Log::error('Eoglasna API request failed', [
                'scope' => $scope,
                'filter' => $filter,
                'page' => $page,
                'error' => $e->getMessage(),
            ]);

            throw new EoglasnaException(
                'API request failed: '.$e->getMessage(),
                EoglasnaException::API_REQUEST_FAILED,
                $e
            );
        }
    }

    /**
     * Fetch courts from API with error handling
     *
     * @throws EoglasnaException
     */
    protected function fetchCourtsWithErrorHandling(): array
    {
        try {
            return $this->client->getCourts();

        } catch (\Exception $e) {
            Log::error('Failed to fetch courts from Eoglasna API', [
                'error' => $e->getMessage(),
            ]);

            throw new EoglasnaException(
                'Failed to fetch courts: '.$e->getMessage(),
                EoglasnaException::API_REQUEST_FAILED,
                $e
            );
        }
    }

    /**
     * Find Osijek municipal court
     */
    protected function findOsijekCourt(): ?EoglasnaCourt
    {
        return EoglasnaCourt::query()
            ->where('name', 'like', '%Općinski%')
            ->where('name', 'like', '%Osijek%')
            ->first();
    }

    /**
     * Fetch court notices with error handling
     *
     * @throws EoglasnaException
     */
    protected function fetchCourtNoticesWithErrorHandling(string $courtCode, int $page, string $sort): array
    {
        try {
            return $this->client->getCourtNotices(['courtCode' => [$courtCode]], $page, $sort);

        } catch (\Exception $e) {
            Log::error('Failed to fetch court notices from Eoglasna API', [
                'court_code' => $courtCode,
                'page' => $page,
                'error' => $e->getMessage(),
            ]);

            throw new EoglasnaException(
                'Failed to fetch court notices: '.$e->getMessage(),
                EoglasnaException::API_REQUEST_FAILED,
                $e
            );
        }
    }
}
