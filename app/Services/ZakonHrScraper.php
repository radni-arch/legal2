<?php

namespace App\Services;

use App\Exceptions\ScraperException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class ZakonHrScraper
{
    private const BASE_URL = 'https://zakon.hr';

    private const CATEGORY_URLS = [
        98 => 'https://www.zakon.hr/search.htm?povezani=98',
        99 => 'https://www.zakon.hr/search.htm?povezani=99',
        100 => 'https://www.zakon.hr/search.htm?povezani=100',
        101 => 'https://www.zakon.hr/search.htm?povezani=101',
    ];

    /**
     * Scrape all configured category URLs and return collected laws
     */
    public function scrapeAllCategories(): array
    {
        $startTime = microtime(true);

        Log::info('Starting scraping of all categories', [
            'category_count' => count(self::CATEGORY_URLS),
        ]);

        try {
            $allLaws = [];
            $totalLaws = 0;
            $successfulCategories = 0;
            $failedCategories = 0;

            foreach (self::CATEGORY_URLS as $categoryId => $url) {
                $categoryStart = microtime(true);

                try {
                    Log::debug('Scraping category', [
                        'category_id' => $categoryId,
                        'url' => $url,
                    ]);

                    $laws = $this->scrapeCategoryPage($url);
                    $categoryDuration = microtime(true) - $categoryStart;

                    $allLaws[$categoryId] = [
                        'url' => $url,
                        'count' => count($laws),
                        'laws' => $laws,
                        'duration_ms' => round($categoryDuration * 1000, 2),
                    ];

                    $totalLaws += count($laws);
                    $successfulCategories++;

                    Log::info('Category scraped successfully', [
                        'category_id' => $categoryId,
                        'law_count' => count($laws),
                        'duration_ms' => round($categoryDuration * 1000, 2),
                    ]);
                } catch (\Throwable $e) {
                    $categoryDuration = microtime(true) - $categoryStart;
                    $failedCategories++;

                    Log::error("Failed to scrape category {$categoryId}", [
                        'url' => $url,
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'duration_ms' => round($categoryDuration * 1000, 2),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    $allLaws[$categoryId] = [
                        'url' => $url,
                        'count' => 0,
                        'laws' => [],
                        'error' => $e->getMessage(),
                        'duration_ms' => round($categoryDuration * 1000, 2),
                    ];
                }
            }

            $totalDuration = microtime(true) - $startTime;
            Log::info('All category scraping completed', [
                'total_categories' => count(self::CATEGORY_URLS),
                'successful' => $successfulCategories,
                'failed' => $failedCategories,
                'total_laws' => $totalLaws,
                'total_duration_s' => round($totalDuration, 2),
            ]);

            return $allLaws;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('Category scraping failed with unexpected error', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'total_duration_s' => round($totalDuration, 2),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new ScraperException(
                "Failed to scrape categories: {$e->getMessage()}",
                ScraperException::SCRAPING_FAILED,
                $e
            );
        }
    }

    /**
     * Scrape a single category page and extract law links
     */
    public function scrapeCategoryPage(string $url): array
    {
        $startTime = microtime(true);

        Log::debug('Starting category page scraping', [
            'url' => $url,
        ]);

        try {
            // Phase 1: Fetch HTML
            $fetchStart = microtime(true);
            try {
                $html = $this->fetchWithRetry($url, 'category_page');
                $fetchDuration = microtime(true) - $fetchStart;

                Log::debug('HTML fetched successfully', [
                    'url' => $url,
                    'html_length' => strlen($html),
                    'fetch_duration_ms' => round($fetchDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $fetchDuration = microtime(true) - $fetchStart;
                Log::error('Failed to fetch HTML', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                    'fetch_duration_ms' => round($fetchDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw new ScraperException(
                    "Failed to fetch category page: {$e->getMessage()}",
                    ScraperException::HTTP_REQUEST_FAILED,
                    $e
                );
            }

            // Phase 2: Parse HTML
            $parseStart = microtime(true);
            try {
                $laws = $this->parseHtml($html);
                $parseDuration = microtime(true) - $parseStart;

                Log::debug('HTML parsed successfully', [
                    'url' => $url,
                    'laws_found' => count($laws),
                    'parse_duration_ms' => round($parseDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $parseDuration = microtime(true) - $parseStart;
                Log::error('Failed to parse HTML', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                    'parse_duration_ms' => round($parseDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw new ScraperException(
                    "Failed to parse category page: {$e->getMessage()}",
                    ScraperException::PARSE_ERROR,
                    $e
                );
            }

            $totalDuration = microtime(true) - $startTime;
            Log::info('Category page scraped successfully', [
                'url' => $url,
                'laws_found' => count($laws),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'timing_breakdown' => [
                    'fetch_ms' => round($fetchDuration * 1000, 2),
                    'parse_ms' => round($parseDuration * 1000, 2),
                ],
            ]);

            return $laws;
        } catch (ScraperException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('Category page scraping failed with unexpected error', [
                'url' => $url,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new ScraperException(
                "Unexpected error scraping category page: {$e->getMessage()}",
                ScraperException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Fetch URL with exponential backoff and jitter
     *
     * @throws \Exception
     */
    protected function fetchWithRetry(string $url, string $context, int $maxRetries = 3): string
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            $attempt++;

            try {
                $response = Http::timeout(30)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'Accept-Language' => 'hr-HR,hr;q=0.9,en-US;q=0.8,en;q=0.7',
                    ])
                    ->get($url);

                if ($response->successful()) {
                    if ($attempt > 1) {
                        Log::info('HTTP request succeeded after retry', [
                            'url' => $url,
                            'context' => $context,
                            'attempt' => $attempt,
                            'total_attempts' => $attempt,
                        ]);
                    }

                    return $response->body();
                }

                throw new \Exception("HTTP {$response->status()}: {$response->body()}");
            } catch (\Exception $e) {
                $lastException = $e;

                Log::warning('HTTP request failed', [
                    'url' => $url,
                    'context' => $context,
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'error' => $e->getMessage(),
                ]);

                // Don't sleep after the last attempt
                if ($attempt < $maxRetries) {
                    $delay = $this->calculateBackoffDelay($attempt);
                    Log::debug('Retrying after delay', [
                        'url' => $url,
                        'delay_ms' => $delay,
                        'next_attempt' => $attempt + 1,
                    ]);
                    usleep($delay * 1000); // Convert ms to microseconds
                }
            }
        }

        // All retries exhausted
        Log::error('HTTP request failed after all retries', [
            'url' => $url,
            'context' => $context,
            'total_attempts' => $attempt,
            'error' => $lastException->getMessage(),
        ]);

        throw new \Exception(
            "Failed to fetch URL after {$maxRetries} attempts: {$url}. Last error: {$lastException->getMessage()}",
            0,
            $lastException
        );
    }

    /**
     * Calculate exponential backoff delay with jitter
     *
     * Uses configurable base delay and jitter percentage.
     * Configuration keys:
     * - services.zakon_hr_scraper.retry_base_delay (default: 1000ms)
     * - services.zakon_hr_scraper.retry_jitter_percent (default: 0.5 = 50%)
     *
     * @param  int  $attempt  Attempt number (1-based)
     * @return int Delay in milliseconds
     */
    protected function calculateBackoffDelay(int $attempt): int
    {
        // Exponential backoff: base_delay * 2^(attempt-1)
        $baseDelay = config('services.zakon_hr_scraper.retry_base_delay', 1000); // 1 second default
        $exponentialDelay = $baseDelay * pow(2, $attempt - 1);

        // Add jitter: random value between 0 and configured percentage of the delay
        $jitterPercent = config('services.zakon_hr_scraper.retry_jitter_percent', 0.5);
        $jitter = rand(0, (int) ($exponentialDelay * $jitterPercent));

        return (int) ($exponentialDelay + $jitter);
    }

    /**
     * Parse HTML and extract law links
     */
    private function parseHtml(string $html): array
    {
        $crawler = new Crawler($html);
        $laws = [];

        // Find the main content div
        $contentDiv = $crawler->filter('div.tekst-zakona.strana-f');

        if ($contentDiv->count() === 0) {
            Log::warning('Could not find content div with class "tekst-zakona strana-f"');

            return [];
        }

        // Extract all links within the content div
        $contentDiv->filter('a')->each(function (Crawler $node) use (&$laws) {
            $href = $node->attr('href');
            $title = $node->attr('title') ?? $node->text();

            // Only collect links that start with /z/ (law links)
            if ($href && str_starts_with($href, '/z/')) {
                // Make absolute URL
                $absoluteUrl = self::BASE_URL.$href;

                // Extract law number from URL (e.g., /z/98/kazneni-zakon -> 98)
                preg_match('/\/z\/(\d+)\//', $href, $matches);
                $lawNumber = $matches[1] ?? null;

                // Extract slug from URL
                preg_match('/\/z\/\d+\/(.+)$/', $href, $slugMatches);
                $slug = $slugMatches[1] ?? null;

                $laws[] = [
                    'title' => trim($title),
                    'url' => $absoluteUrl,
                    'relative_url' => $href,
                    'law_number' => $lawNumber,
                    'slug' => $slug,
                ];
            }
        });

        // Remove duplicates based on URL
        $uniqueLaws = [];
        $seenUrls = [];

        foreach ($laws as $law) {
            if (! in_array($law['url'], $seenUrls)) {
                $uniqueLaws[] = $law;
                $seenUrls[] = $law['url'];
            }
        }

        return $uniqueLaws;
    }

    /**
     * Get all unique laws from all categories
     */
    public function getUniqueLaws(): array
    {
        $categoriesData = $this->scrapeAllCategories();
        $allLaws = [];
        $seenUrls = [];

        foreach ($categoriesData as $categoryId => $data) {
            foreach ($data['laws'] ?? [] as $law) {
                if (! in_array($law['url'], $seenUrls)) {
                    $law['found_in_categories'] = [$categoryId];
                    $allLaws[$law['url']] = $law;
                    $seenUrls[] = $law['url'];
                } else {
                    // Law already exists, just add this category to the list
                    $allLaws[$law['url']]['found_in_categories'][] = $categoryId;
                }
            }
        }

        return array_values($allLaws);
    }

    /**
     * Get statistics about scraped laws
     */
    public function getStatistics(): array
    {
        $categoriesData = $this->scrapeAllCategories();
        $totalLaws = 0;
        $totalUnique = 0;
        $seenUrls = [];

        foreach ($categoriesData as $data) {
            $totalLaws += $data['count'];
            foreach ($data['laws'] ?? [] as $law) {
                if (! in_array($law['url'], $seenUrls)) {
                    $totalUnique++;
                    $seenUrls[] = $law['url'];
                }
            }
        }

        return [
            'total_laws' => $totalLaws,
            'unique_laws' => $totalUnique,
            'categories_scraped' => count($categoriesData),
            'categories' => $categoriesData,
        ];
    }

    /**
     * Scrape the full content of a specific law page
     *
     * @param  string  $url  The law URL to scrape
     */
    public function scrapeLawContent(string $url): array
    {
        $html = $this->fetchWithRetry($url, 'law_content');
        $crawler = new Crawler($html);

        $content = [];
        $metadata = [];

        // Extract the main law title
        $titleNode = $crawler->filter('h1, h2.naslov-zakona, .naslov-zakona');
        $title = $titleNode->count() > 0 ? trim($titleNode->first()->text()) : '';

        // Extract law metadata (number, date, etc.)
        $metaNode = $crawler->filter('.meta-zakon, .podaci-zakon, .metadata');
        if ($metaNode->count() > 0) {
            $metadata['raw_meta'] = trim($metaNode->first()->text());
        }

        // Extract the main law text content
        // Try multiple selectors as the structure might vary
        $contentSelectors = [
            'div.tekst-zakona',
            'div.law-content',
            'div.content',
            'article',
            'div.main-content',
        ];

        $lawText = '';
        foreach ($contentSelectors as $selector) {
            $contentNode = $crawler->filter($selector);
            if ($contentNode->count() > 0) {
                // Get the HTML content and clean it up
                $lawText = $contentNode->first()->html();
                break;
            }
        }

        // If no specific content div found, try to get all paragraph text
        if (empty($lawText)) {
            $crawler->filter('p')->each(function (Crawler $node) use (&$lawText) {
                $lawText .= $node->text()."\n\n";
            });
        }

        // Extract articles/sections if present
        $articles = [];
        $crawler->filter('.clanak, article.law-article, div[id^="cl"], div[class*="article"]')->each(function (Crawler $node, $i) use (&$articles) {
            $articleTitle = '';
            $articleContent = '';

            // Try to find article title
            $titleNode = $node->filter('h3, h4, strong, .article-title');
            if ($titleNode->count() > 0) {
                $articleTitle = trim($titleNode->first()->text());
            }

            // Get article content
            $articleContent = trim($node->text());

            if (! empty($articleContent)) {
                $articles[] = [
                    'index' => $i,
                    'title' => $articleTitle,
                    'content' => $articleContent,
                ];
            }
        });

        // Clean up the law text
        $lawText = strip_tags($lawText, '<p><br><strong><em><ul><li><ol><h1><h2><h3><h4>');
        $lawText = preg_replace('/\s+/', ' ', $lawText);
        $lawText = trim($lawText);

        return [
            'url' => $url,
            'title' => $title,
            'content' => $lawText,
            'articles' => $articles,
            'metadata' => $metadata,
            'scraped_at' => now()->toIso8601String(),
            'content_length' => mb_strlen($lawText),
        ];
    }

    /**
     * Scrape content for multiple laws
     *
     * @param  array  $urls  Array of law URLs to scrape
     */
    public function scrapeLawsContent(array $urls): array
    {
        $results = [];

        foreach ($urls as $url) {
            try {
                $results[$url] = $this->scrapeLawContent($url);
                $results[$url]['status'] = 'success';

                // Add a small delay to avoid overwhelming the server
                usleep(500000); // 0.5 second delay
            } catch (\Exception $e) {
                Log::error('Failed to scrape law content', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
                $results[$url] = [
                    'url' => $url,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
