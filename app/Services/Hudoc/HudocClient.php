<?php

namespace App\Services\Hudoc;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HudocClient
{
    protected HudocResponseParser $parser;

    protected string $baseUrl;

    protected int $rateLimitPause;

    public function __construct()
    {
        $this->parser = new HudocResponseParser;
        $this->baseUrl = config('hudoc.base_url');
        $this->rateLimitPause = config('hudoc.rate_limit.pause_ms', 2000);
    }

    public function search(HudocSearchQuery $query): Collection
    {
        $payload = $query->toPayload();
        $cacheKey = 'hudoc_search_'.md5(json_encode($payload));

        if (config('hudoc.cache.enabled')) {
            return Cache::remember($cacheKey, config('hudoc.cache.ttl'), function () use ($payload) {
                return $this->executeSearch($payload);
            });
        }

        return $this->executeSearch($payload);
    }

    protected function executeSearch(array $payload): Collection
    {
        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ])
                ->asForm()
                ->post("{$this->baseUrl}/app/query/results", $payload);

            usleep($this->rateLimitPause * 1000);

            if ($response->successful()) {
                return $this->parser->parseSearchResults($response->json());
            }

            Log::error('HUDOC search failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return collect();

        } catch (\Exception $e) {
            Log::error('HUDOC search exception', ['error' => $e->getMessage()]);

            return collect();
        }
    }

    public function fetchDocument(string $itemId): ?string
    {
        $cacheKey = "hudoc_doc_{$itemId}";

        if (config('hudoc.cache.enabled') && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $url = "{$this->baseUrl}/app/conversion/docx/pdf";
            $response = Http::timeout(120)
                ->get($url, [
                    'library' => 'ECHR',
                    'id' => $itemId,
                    'filename' => 'document.pdf',
                ]);

            usleep($this->rateLimitPause * 1000);

            if ($response->successful()) {
                $content = $response->body();

                if (config('hudoc.cache.enabled')) {
                    Cache::put($cacheKey, $content, config('hudoc.cache.ttl'));
                }

                return $content;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('HUDOC document fetch failed', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function fetchHtmlDocument(string $itemId): ?string
    {
        try {
            $url = "{$this->baseUrl}/eng?i={$itemId}";
            $response = Http::timeout(60)->get($url);

            usleep($this->rateLimitPause * 1000);

            if ($response->successful()) {
                return $this->extractTextFromHtml($response->body());
            }

            return null;

        } catch (\Exception $e) {
            Log::error('HUDOC HTML fetch failed', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function extractTextFromHtml(string $html): string
    {
        // Extract the document content from HUDOC HTML
        preg_match('/<div[^>]*class="[^"]*docContent[^"]*"[^>]*>(.*?)<\/div>/s', $html, $matches);

        $content = $matches[1] ?? $html;

        // Clean HTML
        $content = strip_tags($content);
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        $content = preg_replace('/\s+/', ' ', $content);

        return trim($content);
    }

    public function searchCroatianCases(int $limit = 500): Collection
    {
        return $this->search(
            HudocSearchQuery::make()
                ->respondent('Croatia')
                ->judgmentsOnly()
                ->language('ENG')
                ->importance(['1', '2', '3'])
                ->sortBy('kpdate', 'Descending')
                ->limit($limit)
        );
    }

    public function searchByArticle(string $article, ?string $state = null, int $limit = 100): Collection
    {
        $query = HudocSearchQuery::make()
            ->article($article)
            ->judgmentsOnly()
            ->limit($limit);

        if ($state) {
            $query->respondent($state);
        }

        return $this->search($query);
    }
}
