<?php

namespace App\Services\Odluke;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class OdlukeClient
{
    protected int $rpm;

    protected int $backoffMs;

    protected static float $lastCallAt = 0.0; // monotonic spacing between requests (per-process)

    /**
     * Circuit breaker state tracking
     */
    protected static array $circuitState = [
        'failures' => 0,
        'last_failure_time' => 0,
        'state' => 'closed', // closed, open, half_open
    ];

    /**
     * Circuit breaker thresholds
     */
    protected const CIRCUIT_FAILURE_THRESHOLD = 3;

    protected const CIRCUIT_TIMEOUT = 60; // seconds before attempting recovery

    protected const CIRCUIT_SUCCESS_THRESHOLD = 2; // successful requests to close circuit

    public function __construct(
        protected string $baseUrl,
        protected int $timeout = 30,
        protected int $retry = 2,
        protected int $delayMs = 700,
        ?int $rpm = null,
        ?int $backoffMs = null,
    ) {
        $cfgRpm = $rpm ?? (int) (config('odluke.rpm') ?? 30);
        $this->rpm = $cfgRpm > 0 ? $cfgRpm : 30;
        $this->backoffMs = $backoffMs ?? (int) (config('odluke.backoff_ms') ?? 800);
    }

    public static function fromConfig(): self
    {
        $cfg = config('odluke');

        return new self(
            baseUrl: rtrim($cfg['base_url'] ?? 'https://odluke.sudovi.hr', '/'),
            timeout: (int) ($cfg['timeout'] ?? 30),
            retry: (int) ($cfg['retry'] ?? 2),
            delayMs: (int) ($cfg['delay_ms'] ?? 700),
            rpm: (int) ($cfg['rpm'] ?? 30),
            backoffMs: (int) ($cfg['backoff_ms'] ?? 800),
        );
    }

    /**
     * @return $this|self
     */
    public function withBaseUrl(?string $baseUrl): self
    {
        if (! $baseUrl) {
            return $this;
        }

        return new self($baseUrl, $this->timeout, $this->retry, $this->delayMs, $this->rpm, $this->backoffMs);
    }

    /**
     * Check if circuit breaker allows request
     */
    protected function isCircuitOpen(): bool
    {
        $state = self::$circuitState['state'];

        if ($state === 'closed') {
            return false;
        }

        if ($state === 'open') {
            $timeSinceFailure = time() - self::$circuitState['last_failure_time'];
            if ($timeSinceFailure >= self::CIRCUIT_TIMEOUT) {
                self::$circuitState['state'] = 'half_open';
                Log::debug('[OdlukeClient] Circuit breaker entering half-open state');

                return false;
            }

            return true;
        }

        // half_open state allows requests through
        return false;
    }

    /**
     * Record successful request for circuit breaker
     */
    protected function recordSuccess(): void
    {
        if (self::$circuitState['state'] === 'half_open') {
            self::$circuitState['failures'] = max(0, self::$circuitState['failures'] - 1);

            if (self::$circuitState['failures'] === 0) {
                self::$circuitState['state'] = 'closed';
                Log::info('[OdlukeClient] Circuit breaker closed after recovery');
            }
        } elseif (self::$circuitState['state'] === 'closed') {
            // Reset failure count on success
            self::$circuitState['failures'] = 0;
        }
    }

    /**
     * Record failed request for circuit breaker
     */
    protected function recordFailure(): void
    {
        self::$circuitState['failures']++;
        self::$circuitState['last_failure_time'] = time();

        if (self::$circuitState['failures'] >= self::CIRCUIT_FAILURE_THRESHOLD) {
            if (self::$circuitState['state'] !== 'open') {
                self::$circuitState['state'] = 'open';
                Log::warning('[OdlukeClient] Circuit breaker opened after '.self::$circuitState['failures'].' failures');
            }
        }
    }

    /**
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function http()
    {
        // Simple retry with increasing sleep when 429/5xx
        $sleepMs = max(100, $this->delayMs);

        return Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; OdlukeMCP/1.0)',
            'Accept-Language' => 'hr-HR,hr;q=0.9,en-US;q=0.8,en;q=0.7',
            'Referer' => $this->baseUrl.'/',
            'Connection' => 'keep-alive', // Enable connection pooling
        ])->withOptions([
            'pool' => true, // Enable connection pooling
            'verify' => true,
        ])->timeout($this->timeout)
            ->retry($this->retry, $sleepMs, function ($exception, $request) {
                // Backoff on 429/5xx
                Log::debug('[OdlukeClient] Retry triggered', [
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                ]);
                usleep($this->backoffMs * 1000);

                return true;
            });
    }

    /**
     * Perform HTTP GET request with circuit breaker and logging
     *
     * @return \Illuminate\Http\Client\Response
     *
     * @throws \Illuminate\Http\Client\ConnectionException
     * @throws \RuntimeException
     */
    protected function safeGet(string $url)
    {
        // Check circuit breaker
        if ($this->isCircuitOpen()) {
            Log::warning('[OdlukeClient] Request blocked by circuit breaker', ['url' => $url]);
            throw new \RuntimeException('Service temporarily unavailable (circuit breaker open)');
        }

        Log::debug('[OdlukeClient] Request', [
            'url' => $url,
            'circuit_state' => self::$circuitState['state'],
        ]);

        $startTime = microtime(true);

        try {
            $response = $this->http()->get($url);
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::debug('[OdlukeClient] Response', [
                'url' => $url,
                'status' => $response->status(),
                'duration_ms' => $duration,
                'content_type' => $response->header('Content-Type'),
                'size_bytes' => strlen($response->body()),
            ]);

            // Record success if OK response
            if ($response->ok()) {
                $this->recordSuccess();
            } else {
                // Non-OK status codes are considered failures
                $this->recordFailure();
                Log::warning('[OdlukeClient] Non-OK response', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);
            }

            return $response;
        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->recordFailure();

            Log::error('[OdlukeClient] Request failed', [
                'url' => $url,
                'duration_ms' => $duration,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Validate response structure before parsing
     *
     * @param  \Illuminate\Http\Client\Response  $response
     */
    protected function validateResponse($response, string $context): bool
    {
        if (! $response->ok()) {
            Log::warning("[OdlukeClient] Invalid response in {$context}", [
                'status' => $response->status(),
            ]);

            return false;
        }

        $contentType = $response->header('Content-Type');
        $body = $response->body();

        // Check if body is not empty
        if (empty($body)) {
            Log::warning("[OdlukeClient] Empty response body in {$context}");

            return false;
        }

        // For HTML responses, check basic structure
        if ($contentType && str_contains($contentType, 'text/html')) {
            if (strlen($body) < 100) {
                Log::warning("[OdlukeClient] Response too short in {$context}", [
                    'size' => strlen($body),
                ]);

                return false;
            }

            // Check for common error pages
            if (preg_match('/(error|404|500|503)/i', $body) && strlen($body) < 5000) {
                Log::warning("[OdlukeClient] Possible error page detected in {$context}");

                return false;
            }
        }

        return true;
    }

    protected function throttle(): void
    {
        $minIntervalMs = (int) max($this->delayMs, floor(1000 / max(1, $this->rpm)));
        $now = microtime(true) * 1000;
        $waitMs = (int) max(0, (self::$lastCallAt + $minIntervalMs) - $now);
        if ($waitMs > 0) {
            usleep($waitMs * 1000);
        }
        self::$lastCallAt = microtime(true) * 1000;
    }

    /**
     * Build the URL for a specific page of the document list.
     */
    protected function buildListUrl(?string $q, ?string $params, int $page): string
    {
        $url = $this->baseUrl.'/Document/DisplayList';
        $qs = [];
        if ($q !== null && $q !== '') {
            $qs['q'] = $q;
        }
        if ($page > 1) {
            $qs['page'] = $page;
        }

        if ($params && $params !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?')
                .http_build_query($qs)
                .($qs ? '&' : '')
                .ltrim($params, '&');
        } else {
            if ($qs) {
                $url .= '?'.http_build_query($qs);
            }
        }

        return $url;
    }

    /**
     * Collect IDs from a single page (internal helper).
     *
     * @return array{url: string, ids: array, html: string|null, error: string|null}
     *
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    protected function fetchSinglePage(?string $q, ?string $params, int $page): array
    {
        $url = $this->buildListUrl($q, $params, $page);

        $this->throttle();

        try {
            $resp = $this->safeGet($url);

            if (! $this->validateResponse($resp, 'collectIdsFromList')) {
                return ['url' => $url, 'ids' => [], 'html' => null, 'error' => 'Invalid response: '.$resp->status()];
            }

            $html = $resp->body();
            // Extract all IDs from this page (no limit here, we'll limit in the caller)
            $ids = $this->extractIds($html, PHP_INT_MAX);

            return [
                'url' => $url,
                'ids' => $ids,
                'html' => $html,
                'error' => null,
            ];
        } catch (\RuntimeException $e) {
            // Re-throw circuit breaker exceptions - they should propagate to caller
            if (str_contains($e->getMessage(), 'circuit breaker')) {
                throw $e;
            }

            return ['url' => $url, 'ids' => [], 'html' => null, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['url' => $url, 'ids' => [], 'html' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Collect decision IDs from search results, paginating through all pages as needed.
     *
     * @param  string|null  $q  Search query
     * @param  string|null  $params  Additional URL parameters
     * @param  int  $limit  Maximum number of IDs to collect (0 or negative = unlimited)
     * @param  int  $page  Starting page number (default: 1)
     * @return array{url: string, ids: array, count: int, pages_fetched?: int, total_pages?: int, error?: string}
     *
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function collectIdsFromList(?string $q, ?string $params, int $limit = 50, int $page = 1): array
    {
        $allIds = [];
        $currentPage = $page;
        $maxPage = null;
        $pagesFetched = 0;
        $firstUrl = null;
        $lastError = null;
        $unlimited = $limit <= 0;

        while (true) {
            $result = $this->fetchSinglePage($q, $params, $currentPage);
            $pagesFetched++;

            if ($firstUrl === null) {
                $firstUrl = $result['url'];
            }

            // Check for errors
            if ($result['error'] !== null) {
                $lastError = $result['error'];
                Log::warning('[OdlukeClient] collectIdsFromList page failed', [
                    'page' => $currentPage,
                    'error' => $result['error'],
                ]);

                // If first page fails, return error immediately
                if ($currentPage === $page) {
                    return [
                        'url' => $firstUrl,
                        'ids' => [],
                        'count' => 0,
                        'pages_fetched' => $pagesFetched,
                        'error' => $lastError,
                    ];
                }

                // For subsequent pages, stop pagination but return what we have
                break;
            }

            // Extract max page from first page's HTML
            if ($maxPage === null && $result['html'] !== null) {
                $maxPage = $this->extractMaxPage($result['html']);
                Log::debug('[OdlukeClient] Detected pagination', [
                    'max_page' => $maxPage,
                    'query' => $q,
                ]);
            }

            // Collect IDs from this page
            $pageIds = $result['ids'];
            if (empty($pageIds)) {
                // No IDs found on this page, stop pagination
                Log::debug('[OdlukeClient] No IDs found on page, stopping', ['page' => $currentPage]);
                break;
            }

            foreach ($pageIds as $id) {
                if (! isset($allIds[$id])) {
                    $allIds[$id] = true;

                    // Check if we've reached the limit
                    if (! $unlimited && count($allIds) >= $limit) {
                        Log::debug('[OdlukeClient] Limit reached', [
                            'limit' => $limit,
                            'collected' => count($allIds),
                            'page' => $currentPage,
                        ]);
                        break 2; // Exit both loops
                    }
                }
            }

            // Check if we should fetch more pages
            $currentPage++;
            if ($maxPage !== null && $currentPage > $maxPage) {
                // Reached the last page
                Log::debug('[OdlukeClient] Reached last page', ['max_page' => $maxPage]);
                break;
            }

            // Safety limit to prevent infinite loops (max 1000 pages)
            if ($pagesFetched >= 1000) {
                Log::warning('[OdlukeClient] Safety limit reached, stopping pagination', [
                    'pages_fetched' => $pagesFetched,
                ]);
                break;
            }
        }

        $ids = array_keys($allIds);

        // Apply limit if not unlimited
        if (! $unlimited && count($ids) > $limit) {
            $ids = array_slice($ids, 0, $limit);
        }

        $response = [
            'url' => $firstUrl ?? $this->buildListUrl($q, $params, $page),
            'ids' => $ids,
            'count' => count($ids),
            'pages_fetched' => $pagesFetched,
        ];

        if ($maxPage !== null) {
            $response['total_pages'] = $maxPage;
        }

        if ($lastError !== null && empty($ids)) {
            $response['error'] = $lastError;
        }

        return $response;
    }

    /**
     * Extract the maximum page number from pagination HTML.
     *
     * Parses pagination like:
     * <nav><ul class="pagination">
     *   <li class="page-item active"><a href="...page=1...">1</a></li>
     *   <li class="page-item"><a href="...page=2...">2</a></li>
     *   ...
     *   <li class="page-item"><a href="...page=58...">58</a></li>
     * </ul></nav>
     */
    protected function extractMaxPage(string $html): int
    {
        $maxPage = 1;

        try {
            if (class_exists(Crawler::class)) {
                $crawler = new Crawler($html);
                $crawler->filter('nav ul.pagination li.page-item a.page-link')->each(function (Crawler $a) use (&$maxPage) {
                    $href = $a->attr('href') ?? '';
                    if ($href === '') {
                        return;
                    }

                    // Extract page number from href (e.g., "...page=58...")
                    $href = html_entity_decode($href, ENT_QUOTES);
                    if (preg_match('~[?&]page=(\d+)~', $href, $m)) {
                        $pageNum = (int) $m[1];
                        if ($pageNum > $maxPage) {
                            $maxPage = $pageNum;
                        }
                    }
                });
            }
        } catch (\Throwable $e) {
            // Fallback to regex if DomCrawler fails
        }

        // Regex fallback
        if ($maxPage === 1) {
            if (preg_match_all('~[?&]page=(\d+)~', html_entity_decode($html, ENT_QUOTES), $matches)) {
                foreach ($matches[1] as $pageStr) {
                    $pageNum = (int) $pageStr;
                    if ($pageNum > $maxPage) {
                        $maxPage = $pageNum;
                    }
                }
            }
        }

        return $maxPage;
    }

    protected function extractIds(string $html, int $limit): array
    {
        $found = [];

        // 1) DomCrawler
        try {
            if (class_exists(Crawler::class)) {
                $crawler = new Crawler($html);
                $crawler->filter('a[href*="id="]')->each(function (Crawler $a) use (&$found) {
                    $href = $a->attr('href') ?? '';
                    if ($href === '') {
                        return;
                    }
                    $href = html_entity_decode($href, ENT_QUOTES);
                    $query = parse_url($href, PHP_URL_QUERY) ?: '';
                    parse_str($query, $q);
                    $id = $q['id'] ?? null;
                    if ($id && preg_match('~^[0-9a-fA-F-]{8,}$~', $id)) {
                        $found[$id] = true;
                    }
                });
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // 2) Regex fallback za /Document/* i legacy decision*
        if (! $found) {
            if (preg_match_all(
                '~/(?:Document/(?:View|Text|Download)|decision(?:View|Text|Download))\?[^"\']*?\bid=([0-9a-fA-F-]{8,})~',
                $html,
                $m
            )) {
                foreach ($m[1] as $id) {
                    $found[$id] = true;
                }
            }
        }

        return array_slice(array_keys($found), 0, $limit);
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function fetchDecisionMeta(string $id): ?array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('OdlukeClient fetchDecisionMeta initiated', [
            'decision_id' => $id,
            'user_id' => auth()->id(),
        ]);

        try {
            $url = $this->baseUrl.'/Document/View?id='.urlencode($id);
            $this->throttle();
            $resp = $this->safeGet($url);

            if (! $this->validateResponse($resp, 'fetchDecisionMeta')) {
                return null;
            }

            $html = $resp->body();
            $meta = [];

            // 1) Prefer structured DOM parsing
            try {
                if (class_exists(Crawler::class)) {
                    $parsed = $this->parseMetadataModal($html);
                    if ($parsed) {
                        $meta = $parsed;
                    }
                }
            } catch (\Throwable $e) {
                // ignore and fallback to regex
            }

            // 2) Fallback to previous regex scraping for any missing fields
            $text = trim(strip_tags($html));
            $rx = fn (string $p) => (preg_match($p, $text, $m) ? trim(preg_replace('~\s+~u', ' ', $m[1])) : null);

            $meta += array_filter([
                'broj_odluke' => $meta['broj_odluke'] ?? $rx('~Broj odluke:\s*([^\r\n]+)~u'),
                'sud' => $meta['sud'] ?? $rx('~Sud:\s*([^\r\n]+)~u'),
                'datum_odluke' => $meta['datum_odluke'] ?? $this->normalizeHrDate($rx('~Datum odluke:\s*([0-9.\-\/]+)~u')),
                'pravomocnost' => $meta['pravomocnost'] ?? $rx('~Pravomoćnost:\s*([^\r\n]+)~u'),
                'datum_objave' => $meta['datum_objave'] ?? $this->normalizeHrDate($rx('~Datum objave:\s*([0-9.\-\/]+)~u')),
                'upisnik' => $meta['upisnik'] ?? $rx('~Upisnik:\s*([^\r\n]+)~u'),
                'vrsta_odluke' => $meta['vrsta_odluke'] ?? $rx('~Vrsta odluke:\s*([^\r\n]+)~u'),
                'ecli' => $meta['ecli'] ?? $rx('~ECLI broj:\s*([A-Z0-9:\.\-]+)~u'),
            ], static fn ($v) => $v !== null && $v !== '');

            // 3) Always include source and a direct download hint
            $meta['src'] = $url;
            try {
                if (class_exists(Crawler::class)) {
                    $meta['_download_href'] = $this->baseUrl.'/Document/DownloadPdf?id='.urlencode($id);
                }
            } catch (\Throwable $e) {
                // ignore
            }

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('OdlukeClient fetchDecisionMeta completed', [
                'decision_id' => $id,
                'fields_extracted' => count($meta),
                'has_ecli' => isset($meta['ecli']),
                'duration_ms' => round($duration, 2),
            ]);

            return $meta;
        } catch (\Throwable $e) {
            Log::error('OdlukeClient fetchDecisionMeta failed', [
                'decision_id' => $id,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Parse the structured metadata modal.
     */
    protected function parseMetadataModal(string $html): ?array
    {
        $dom = new Crawler($html);
        $container = $dom->filter('#MetadataModal .metadata')->first();
        if ($container->count() === 0) {
            $container = $dom->filter('.metadata')->first();
        }
        if ($container->count() === 0) {
            return null;
        }

        $meta = [
            // keep flat fields for backward compatibility
            'broj_odluke' => null,
            'sud' => null,
            'datum_odluke' => null,
            'pravomocnost' => null,
            'datum_objave' => null,
            'upisnik' => null,
            'vrsta_odluke' => null,
            'ecli' => null,

            // new structured fields
            'prethodna_odluka' => null,
            'stvarno_kazalo' => [],
            'zakonsko_kazalo' => [],
            'eurovoc' => [],
        ];

        $laws = [];
        $currentLaw = null;

        $container->filter('.metadata-item')->each(function (Crawler $item) use (&$meta, &$laws, &$currentLaw) {
            $type = trim((string) ($item->attr('data-metadata-type') ?? ''));

            // Simple single-value content
            $pContent = $item->filter('p.metadata-content');
            $pText = $this->crawlerText($pContent);

            switch ($type) {
                case 'decision-number':
                    $meta['broj_odluke'] = $pText;
                    break;

                case 'court':
                    $meta['sud'] = $pText;
                    break;

                case 'decision-date':
                    $meta['datum_odluke'] = $this->normalizeHrDate($pText);
                    break;

                case 'decision-finality':
                    $meta['pravomocnost'] = $pText;
                    break;

                case 'publication-date':
                    $meta['datum_objave'] = $this->normalizeHrDate($pText);
                    break;

                case 'court-registry-type':
                    $meta['upisnik'] = $pText;
                    break;

                case 'decision-type':
                    $meta['vrsta_odluke'] = $pText;
                    break;

                case 'previous-decisions':
                    // Keep as raw string; can be parsed further if needed
                    $meta['prethodna_odluka'] = $this->crawlerText($item->filter('.metadata-content'));
                    break;

                case 'ecli':
                case 'ecli-number':
                    $meta['ecli'] = $pText;
                    break;

                case 'stvarno-kazalo-index':
                    $list = $item->filter('ul.metadata-content > li');
                    $list->each(function (Crawler $li) use (&$meta) {
                        $label = $this->crawlerText($li->filter('a')) ?? $this->crawlerText($li);
                        $class = trim((string) ($li->attr('class') ?? ''));
                        $level = null;
                        if (preg_match('~thesaurus-indent-(\d+)~', $class, $m)) {
                            $level = (int) $m[1];
                        }
                        $href = null;
                        try {
                            $a = $li->filter('a')->first();
                            if ($a->count() > 0) {
                                $href = $this->absolutize($a->attr('href'));
                            }
                        } catch (\Throwable $e) {
                        }

                        $meta['stvarno_kazalo'][] = [
                            'label' => $label,
                            'level' => $level,
                            'href' => $href,
                        ];
                    });
                    break;

                case 'zakonsko-kazalo-index':
                    $lis = $item->filter('ul.metadata-content > li');
                    $lis->each(function (Crawler $li) use (&$laws, &$currentLaw) {
                        $class = trim((string) ($li->attr('class') ?? ''));

                        if (str_contains($class, 'law-title')) {
                            // finalize previous
                            if ($currentLaw && (! empty($currentLaw['title']) || ! empty($currentLaw['articles']))) {
                                $laws[] = $currentLaw;
                            }
                            $currentLaw = [
                                'title' => null,
                                'href' => null,
                                'nn' => null,
                                'nn_url' => null,
                                'articles' => [],
                            ];
                            try {
                                $aLaw = $li->filter('a')->first();
                                if ($aLaw->count() > 0) {
                                    $currentLaw['title'] = trim($aLaw->text());
                                    $currentLaw['href'] = $this->absolutize($aLaw->attr('href'));
                                }
                                // optional NN link is usually the second <a>
                                $aLinks = $li->filter('a');
                                if ($aLinks->count() > 1) {
                                    $nn = $aLinks->eq(1);
                                    $currentLaw['nn'] = trim($nn->text());
                                    $currentLaw['nn_url'] = $this->absolutize($nn->attr('href'));
                                }
                            } catch (\Throwable $e) {
                            }
                        } elseif (str_contains($class, 'law-article-index')) {
                            $article = $this->crawlerText($li->filter('span')) ?? $this->crawlerText($li);
                            if (! $currentLaw) {
                                $currentLaw = ['title' => null, 'href' => null, 'nn' => null, 'nn_url' => null, 'articles' => []];
                            }
                            if ($article) {
                                $currentLaw['articles'][] = $article;
                            }
                        }
                    });
                    // finalize last
                    if ($currentLaw && (! empty($currentLaw['title']) || ! empty($currentLaw['articles']))) {
                        $laws[] = $currentLaw;
                    }
                    $meta['zakonsko_kazalo'] = $laws;
                    break;

                case 'eurovoc-index':
                    $list = $item->filter('ul.metadata-content > li');
                    $list->each(function (Crawler $li) use (&$meta) {
                        $label = $this->crawlerText($li->filter('a')) ?? $this->crawlerText($li);
                        $class = trim((string) ($li->attr('class') ?? ''));
                        $level = null;
                        if (preg_match('~thesaurus-indent-(\d+)~', $class, $m)) {
                            $level = (int) $m[1];
                        }
                        $href = null;
                        try {
                            $a = $li->filter('a')->first();
                            if ($a->count() > 0) {
                                $href = $this->absolutize($a->attr('href'));
                            }
                        } catch (\Throwable $e) {
                        }

                        $meta['eurovoc'][] = [
                            'label' => $label,
                            'level' => $level,
                            'href' => $href,
                        ];
                    });
                    break;

                default:
                    // ignore unknown blocks but keep future extensibility
                    break;
            }
        });

        // Trim empties
        foreach (['broj_odluke', 'sud', 'pravomocnost', 'upisnik', 'vrsta_odluke', 'ecli', 'prethodna_odluka'] as $k) {
            if (isset($meta[$k])) {
                $meta[$k] = $meta[$k] !== null ? trim((string) $meta[$k]) : null;
                if ($meta[$k] === '') {
                    $meta[$k] = null;
                }
            }
        }

        return $meta;
    }

    protected function normalizeHrDate(?string $s): ?string
    {
        if (! $s) {
            return null;
        }
        $s = trim($s);
        // Match e.g. 12.5.2025. or 31.07.2025.
        if (preg_match('~^(\d{1,2})\.(\d{1,2})\.(\d{2,4})\.?$~u', $s, $m)) {
            $d = (int) $m[1];
            $mo = (int) $m[2];
            $y = (int) $m[3];
            if ($y < 100) {
                $y += 2000;
            }

            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
        // ISO or other formats
        $ts = strtotime($s);

        return $ts ? date('Y-m-d', $ts) : null;
    }

    protected function crawlerText(?Crawler $node): ?string
    {
        if (! $node || $node->count() === 0) {
            return null;
        }
        try {
            $t = $node->text();
        } catch (\Throwable $e) {
            return null;
        }
        $t = preg_replace('~\s+~u', ' ', $t);
        $t = trim((string) $t);

        return $t === '' ? null : $t;
    }

    public function downloadPdfUrl(string $id): string
    {
        return $this->baseUrl.'/Document/DownloadPdf?id='.urlencode($id);
    }

    public function downloadHtmlUrl(string $id): string
    {
        return $this->baseUrl.'/Document/Text?id='.urlencode($id);
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function downloadPdf(string $id): array
    {
        $url = $this->downloadPdfUrl($id);
        $this->throttle();

        try {
            $resp = $this->safeGet($url);

            // fallback na legacy ako treba
            if (! $resp->ok() || stripos((string) $resp->header('Content-Type'), 'pdf') === false) {
                $url = $this->baseUrl.'/decisionDownload?id='.urlencode($id);
                $this->throttle();
                $resp = $this->safeGet($url);
            }

            $result = [
                'ok' => $resp->ok(),
                'status' => $resp->status(),
                'content_type' => (string) $resp->header('Content-Type'),
                'bytes' => $resp->ok() ? $resp->body() : null,
                'url' => $url,
            ];

            // Add error message for non-OK responses
            if (! $resp->ok()) {
                $result['error'] = "HTTP {$resp->status()}: ".$resp->body();
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('[OdlukeClient] downloadPdf failed', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'status' => 0,
                'content_type' => null,
                'bytes' => null,
                'url' => $url,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function downloadHtml(string $id): array
    {
        $url = $this->downloadHtmlUrl($id);
        $this->throttle();

        try {
            $resp = $this->safeGet($url);

            if (! $resp->ok()) {
                $url = $this->baseUrl.'/decisionText?id='.urlencode($id);
                $this->throttle();
                $resp = $this->safeGet($url);
            }

            $html = $resp->body();
            $hasHtmlTag = stripos($html, '<html') !== false || stripos($html, '<body') !== false;
            if (! $hasHtmlTag) {
                $html = '<!doctype html><meta charset="utf-8"><pre style="white-space:pre-wrap;font:14px/1.4 sans-serif">'
                    .htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    .'</pre>';
            }

            $result = [
                'ok' => $resp->ok(),
                'status' => $resp->status(),
                'content_type' => (string) $resp->header('Content-Type'),
                'bytes' => $resp->ok() ? $html : null,
                'url' => $url,
            ];

            // Add error message for non-OK responses
            if (! $resp->ok()) {
                $result['error'] = "HTTP {$resp->status()}: ".$resp->body();
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('[OdlukeClient] downloadHtml failed', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'status' => 0,
                'content_type' => null,
                'bytes' => null,
                'url' => $url,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function buildBaseFileName(array $meta, string $id): string
    {
        $alias = $this->guessCourtAlias($meta['sud'] ?? '');
        $broj = $meta['broj_odluke'] ?? 'NEPOZNATO';
        $date = ! empty($meta['datum_odluke']) ? date('Y-m-d', strtotime($meta['datum_odluke'])) : '0000-00-00';
        $base = sprintf('%s_%s_%s_%s', $alias, $this->slug($broj), $date, $id);

        return substr($base, 0, 220);
    }

    protected function guessCourtAlias(string $court): string
    {
        $c = mb_strtolower($court);

        return match (true) {
            str_contains($c, 'vrhovni sud') => 'VSRH',
            str_contains($c, 'visoki kazneni sud') => 'VKSRH',
            str_contains($c, 'visoki prekr') => 'VPSRH',
            str_contains($c, 'visoki trgova') => 'VTSRH',
            str_contains($c, 'visoki upravni') => 'VUSR',
            str_contains($c, 'županijski sud') => 'ZUP',
            str_contains($c, 'općinski') => 'OPS',
            str_contains($c, 'trgovački sud') => 'TS',
            str_contains($c, 'upravni sud') => 'US',
            default => 'SUD',
        };
    }

    protected function slug(string $s): string
    {
        $s = preg_replace('~[^\pL\pN]+~u', '-', $s);
        $s = trim($s, '-');
        $s = mb_strtolower($s);
        $s = preg_replace('~[^a-z0-9\-]+~', '', $s);

        return $s ?: 'x';
    }

    protected function absolutize(?string $href): ?string
    {
        if (! $href) {
            return null;
        }

        return str_starts_with($href, 'http')
            ? $href
            : $this->baseUrl.'/'.ltrim($href, '/');
    }
}
