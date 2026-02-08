<?php

namespace App\Services;

use App\Models\SudskaPraksaSearch;
use App\Models\SudskaPraksaResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SudskaPraksaService
{
    private string $baseUrl;
    private string $userAgent;
    private int $timeout;
    private array $thresholds;

    public function __construct()
    {
        $this->baseUrl = config('sudska-praksa.base_url');
        $this->userAgent = config('sudska-praksa.user_agent');
        $this->timeout = config('sudska-praksa.timeout');
        $this->thresholds = config('sudska-praksa.thresholds');
    }

    /**
     * Fetch the result count for a single query from odluke.sudovi.hr
     */
    public function fetchResultCount(string $query, ?string $courts = null): int
    {
        $courts = $courts ?? config('sudska-praksa.default_courts');
        $maxRetries = config('sudska-praksa.max_retries', 3);
        $retryDelay = config('sudska-praksa.retry_delay_ms', 2000);

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Accept' => 'text/html',
                        'User-Agent' => $this->userAgent,
                    ])
                    ->withCookies(['cookieConsent' => '{"type":"accept-all"}'], 'odluke.sudovi.hr')
                    ->get($this->baseUrl, [
                        'q' => $query,
                        'sort' => 'rel',
                        'ct' => $courts,
                    ]);

                if ($response->successful()) {
                    $html = $response->body();

                    if (preg_match('/(\d+)\s+rezultat/', $html, $matches)) {
                        return (int) $matches[1];
                    }

                    if (Str::contains($html, ['Nema rezultata', 'nema rezultata'])) {
                        return 0;
                    }
                }

                // On server error, retry
                if ($response->serverError() && $attempt < $maxRetries) {
                    usleep($retryDelay * 1000 * $attempt); // Exponential backoff
                    continue;
                }

                return -1;
            } catch (\Exception $e) {
                if ($attempt < $maxRetries) {
                    usleep($retryDelay * 1000 * $attempt);
                    continue;
                }
                return -1;
            }
        }

        return -1;
    }

    /**
     * Build the full search URL
     */
    public function buildUrl(string $query, ?string $courts = null): string
    {
        $courts = $courts ?? config('sudska-praksa.default_courts');

        return $this->baseUrl . '?' . http_build_query([
            'q' => $query,
            'sort' => 'rel',
            'ct' => $courts,
        ]);
    }

    /**
     * Classify a result count into a tier
     */
    public function classify(int $count): string
    {
        if ($count < 0) return 'error';
        if ($count === 0) return 'empty';
        if ($count <= $this->thresholds['ultra']) return 'ultra';
        if ($count <= $this->thresholds['zlato']) return 'zlato';
        if ($count <= $this->thresholds['srebrno']) return 'srebrno';
        if ($count <= $this->thresholds['bronca']) return 'bronca';
        return 'bulk';
    }

    /**
     * Run all queries from a categories array and return results collection
     *
     * @param array $categories  Array of category objects with 'queries' arrays
     * @param string|null $courts  Court types filter
     * @param int $delayMs  Delay between requests in milliseconds
     * @param callable|null $onProgress  Callback(query, count, index, total)
     * @return array
     */
    public function runQueries(
        array $categories,
        ?string $courts = null,
        int $delayMs = 500,
        ?callable $onProgress = null,
    ): array {
        $courts = $courts ?? config('sudska-praksa.default_courts');
        $results = [];
        $index = 0;
        $total = collect($categories)->sum(fn($cat) => count($cat['queries'] ?? []));

        foreach ($categories as $category) {
            $categoryName = $category['name'] ?? 'Unnamed';
            $categoryDescription = $category['description'] ?? '';

            foreach ($category['queries'] ?? [] as $queryItem) {
                $query = is_string($queryItem) ? $queryItem : ($queryItem['q'] ?? $queryItem['query'] ?? '');
                $comment = is_string($queryItem) ? '' : ($queryItem['comment'] ?? '');

                if (empty($query)) continue;

                $count = $this->fetchResultCount($query, $courts);

                $result = [
                    'category' => $categoryName,
                    'category_description' => $categoryDescription,
                    'query' => $query,
                    'comment' => $comment,
                    'count' => $count,
                    'classification' => $this->classify($count),
                    'url' => $this->buildUrl($query, $courts),
                    'fetched_at' => Carbon::now()->toIso8601String(),
                ];

                $results[] = $result;
                $index++;

                if ($onProgress) {
                    $onProgress($query, $count, $index, $total);
                }

                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }
        }

        return $results;
    }

    /**
     * Expand successful queries with additional keywords
     */
    public function expandQueries(array $results, ?string $courts = null, int $delayMs = 500): array
    {
        $courts = $courts ?? config('sudska-praksa.default_courts');
        $existingQueries = collect($results)->pluck('query')->toArray();
        $expansions = [];

        foreach ($results as $r) {
            if ($r['count'] > $this->thresholds['srebrno'] || $r['count'] < 0) continue;

            $query = $r['query'];
            $words = preg_split('/\s+AND\s+/', $query);
            if (count($words) < 2) continue;

            $additions = ['izdvajanje', 'nezakonit'];
            foreach ($additions as $keyword) {
                if (Str::contains($query, $keyword)) continue;

                $expanded = $query . ' AND ' . $keyword;
                if (in_array($expanded, $existingQueries)) continue;

                $count = $this->fetchResultCount($expanded, $courts);
                $expansions[] = [
                    'category' => $r['category'] . ' (expanded)',
                    'category_description' => 'Automatski generirane varijacije',
                    'query' => $expanded,
                    'comment' => "Auto-expand: +{$keyword} od '{$query}'",
                    'count' => $count,
                    'classification' => $this->classify($count),
                    'url' => $this->buildUrl($expanded, $courts),
                    'fetched_at' => Carbon::now()->toIso8601String(),
                ];
                $existingQueries[] = $expanded;

                if ($delayMs > 0) usleep($delayMs * 1000);
            }
        }

        return $expansions;
    }

    /**
     * Run queries and persist results to database
     */
    public function runAndPersist(
        array $categories,
        string $name,
        string $keywordsFile,
        ?string $courts = null,
        int $delayMs = 500,
        ?array $metadata = null,
        bool $expand = false,
        ?callable $onProgress = null,
    ): SudskaPraksaSearch {
        $courts = $courts ?? config('sudska-praksa.default_courts');

        $search = SudskaPraksaSearch::create([
            'name' => $name,
            'keywords_file' => $keywordsFile,
            'courts' => $courts,
            'metadata' => $metadata,
            'started_at' => now(),
        ]);

        $results = $this->runQueries($categories, $courts, $delayMs, $onProgress);

        if ($expand) {
            $expansions = $this->expandQueries($results, $courts, $delayMs);
            $results = array_merge($results, $expansions);
        }

        // Persist each result
        foreach ($results as $r) {
            SudskaPraksaResult::create([
                'search_id' => $search->id,
                'category' => $r['category'],
                'category_description' => $r['category_description'],
                'query' => $r['query'],
                'comment' => $r['comment'],
                'count' => $r['count'],
                'classification' => $r['classification'],
                'url' => $r['url'],
                'is_expanded' => str_contains($r['category'], '(expanded)'),
                'fetched_at' => $r['fetched_at'],
            ]);
        }

        $search->finished_at = now();
        $search->computeStats();

        return $search;
    }
}
