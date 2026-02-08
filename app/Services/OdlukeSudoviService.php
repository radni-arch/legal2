<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Service for fetching court decisions from odluke.sudovi.hr
 *
 * This is the official Croatian court decisions database containing:
 * - VPS (Visoki prekršajni sud) decisions
 * - County court decisions
 * - Supreme court decisions
 * - Full decision text with ECLI numbers
 *
 * For Pp Prz cases, this contains appeal decisions that reference
 * first-instance search warrant orders.
 */
class OdlukeSudoviService
{
    const BASE_URL = 'https://odluke.sudovi.hr';

    /**
     * Search for decisions by query
     *
     * @param string $query Search query (e.g., "Pp Prz", "pretres doma")
     * @param int $page Page number (1-based)
     * @param string $sort Sort order: 'rel' (relevance), 'date' (date desc), 'date_asc'
     * @return array
     */
    public function search(string $query, int $page = 1, string $sort = 'rel'): array
    {
        $url = self::BASE_URL . '/Document/DisplayList';

        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; LegalResearchBot/1.0)',
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'hr,en;q=0.9',
            ])
            ->get($url, [
                'q' => $query,
                'page' => $page,
                'sort' => $sort,
            ]);

        if (!$response->successful()) {
            Log::error('OdlukeSudovi search failed', [
                'query' => $query,
                'page' => $page,
                'status' => $response->status(),
            ]);
            return ['success' => false, 'error' => 'HTTP ' . $response->status()];
        }

        return $this->parseSearchResults($response->body(), $query);
    }

    /**
     * Parse search results HTML
     */
    protected function parseSearchResults(string $html, string $query): array
    {
        $results = [];

        // Extract document links
        preg_match_all(
            '/href="\/Document\/View\?id=([a-f0-9-]+)&amp;q=[^"]*"/',
            $html,
            $matches
        );

        $documentIds = array_unique($matches[1] ?? []);

        // Extract total pages
        preg_match('/page=(\d+)&amp;q=[^"]*">\d+<\/a><\/li>\s*<li[^>]*><a[^>]*aria-label="Next"/', $html, $pageMatch);
        $totalPages = isset($pageMatch[1]) ? (int)$pageMatch[1] : 1;

        foreach ($documentIds as $id) {
            $results[] = [
                'id' => $id,
                'url' => self::BASE_URL . "/Document/View?id={$id}&q=" . urlencode($query),
            ];
        }

        return [
            'success' => true,
            'documents' => $results,
            'count' => count($results),
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Get full document by ID
     *
     * @param string $documentId UUID of the document
     * @return array
     */
    public function getDocument(string $documentId): array
    {
        $cacheKey = "odluke_document_{$documentId}";

        return Cache::remember($cacheKey, 86400, function () use ($documentId) {
            $url = self::BASE_URL . "/Document/View";

            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; LegalResearchBot/1.0)',
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'hr,en;q=0.9',
                ])
                ->get($url, ['id' => $documentId]);

            if (!$response->successful()) {
                return ['success' => false, 'error' => 'HTTP ' . $response->status()];
            }

            return $this->parseDocument($response->body(), $documentId);
        });
    }

    /**
     * Parse document HTML to extract metadata and text
     */
    protected function parseDocument(string $html, string $documentId): array
    {
        $document = [
            'id' => $documentId,
            'url' => self::BASE_URL . "/Document/View?id={$documentId}",
            'success' => true,
        ];

        // Extract metadata fields
        $metadataPatterns = [
            'decision_number' => '/data-metadata-type="decision-number"[^>]*>.*?<p class="metadata-content">([^<]+)/s',
            'court' => '/data-metadata-type="court"[^>]*>.*?<p class="metadata-content">([^<]+)/s',
            'decision_date' => '/data-metadata-type="decision-date"[^>]*>.*?<p class="metadata-content">([^<]+)/s',
            'finality' => '/data-metadata-type="decision-finality"[^>]*>.*?<p class="metadata-content">([^<]+)/s',
            'publication_date' => '/data-metadata-type="publication-date"[^>]*>.*?<p class="metadata-content">([^<]+)/s',
            'registry_type' => '/data-metadata-type="court-registry-type"[^>]*>.*?<p class="metadata-content">([^<]+)/s',
            'decision_type' => '/data-metadata-type="decision-type"[^>]*>.*?<p class="metadata-content">([^<]+)/s',
            'previous_decision' => '/data-metadata-type="previous-decisions"[^>]*>.*?<p class="metadata-content">([^<]+)/s',
            'ecli' => '/data-metadata-type="ecli-number"[^>]*>.*?<p class="metadata-content">([^<]+)/s',
        ];

        foreach ($metadataPatterns as $key => $pattern) {
            if (preg_match($pattern, $html, $match)) {
                $document[$key] = trim(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));
            }
        }

        // Extract decision text
        if (preg_match('/<div class="decision-text">(.*?)<\/div>\s*<div class="metadata-item"/s', $html, $textMatch)) {
            $rawText = $textMatch[1];
            // Clean HTML tags but preserve structure
            $document['text_html'] = $rawText;
            $document['text_plain'] = $this->htmlToPlainText($rawText);
        }

        // Parse previous decision reference (e.g., "Pp Prz-34/2024-2, Općinski sud u Slavonskom Brodu, 2.12.2024.")
        if (!empty($document['previous_decision'])) {
            $document['previous_decision_parsed'] = $this->parsePreviousDecision($document['previous_decision']);
        }

        // Extract legal references
        if (preg_match_all('/čl\.\s*(\d+)\.\s*(?:st\.\s*(\d+)\.)?/u', $document['text_plain'] ?? '', $lawMatches, PREG_SET_ORDER)) {
            $document['legal_references'] = array_map(function ($m) {
                return [
                    'article' => $m[1],
                    'paragraph' => $m[2] ?? null,
                ];
            }, $lawMatches);
        }

        return $document;
    }

    /**
     * Parse previous decision reference string
     *
     * @param string $ref e.g., "Pp Prz-34/2024-2, Općinski sud u Slavonskom Brodu, 2.12.2024."
     * @return array
     */
    protected function parsePreviousDecision(string $ref): array
    {
        $parsed = ['raw' => $ref];

        // Extract case number (Pp Prz-XX/YYYY or Pp Prz-XX/YYYY-Z)
        if (preg_match('/(Pp\s*Prz[- ]\d+\/\d+(?:-\d+)?)/i', $ref, $match)) {
            $parsed['case_number'] = $match[1];
        }

        // Extract court name
        if (preg_match('/,\s*(Općinski sud[^,]+|OS\s+[^,]+)/i', $ref, $match)) {
            $parsed['court'] = trim($match[1]);
        }

        // Extract date (DD.MM.YYYY or D.M.YYYY)
        if (preg_match('/(\d{1,2}\.\d{1,2}\.\d{4})/', $ref, $match)) {
            $parsed['date'] = $match[1];
        }

        return $parsed;
    }

    /**
     * Convert HTML to plain text
     */
    protected function htmlToPlainText(string $html): string
    {
        // Remove style tags
        $text = preg_replace('/<style[^>]*>.*?<\/style>/s', '', $html);
        // Replace <p> and <br> with newlines
        $text = preg_replace('/<\/p>|<br\s*\/?>/i', "\n", $text);
        // Remove all other HTML tags
        $text = strip_tags($text);
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        // Normalize whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Search specifically for Pp Prz (search warrant) decisions
     *
     * @param int $maxPages Maximum pages to fetch (10 results per page)
     * @return \Generator
     */
    public function searchPpPrzDecisions(int $maxPages = 100): \Generator
    {
        $page = 1;

        while ($page <= $maxPages) {
            $results = $this->search('Pp Prz', $page);

            if (!$results['success'] || empty($results['documents'])) {
                break;
            }

            foreach ($results['documents'] as $doc) {
                yield $this->getDocument($doc['id']);
            }

            if ($page >= $results['total_pages']) {
                break;
            }

            $page++;

            // Rate limiting
            usleep(500000); // 0.5 second delay
        }
    }

    /**
     * Get count of Pp Prz decisions available
     */
    public function countPpPrzDecisions(): int
    {
        $results = $this->search('Pp Prz', 1);

        if (!$results['success']) {
            return 0;
        }

        // Approximately 10 results per page
        return $results['total_pages'] * 10;
    }

    /**
     * Search for decisions mentioning specific article (e.g., pretres doma)
     */
    public function searchByArticle(string $law, int $article): array
    {
        $query = sprintf('"%s" čl. %d', $law, $article);
        return $this->search($query);
    }

    /**
     * Get VPS decisions only (Visoki prekršajni sud)
     */
    public function searchVpsDecisions(string $query = 'Pp Prz', int $page = 1): array
    {
        // The site doesn't have direct API filtering, but VPS decisions
        // are identifiable by court name in results
        $results = $this->search($query, $page);

        if (!$results['success']) {
            return $results;
        }

        // Filter to only VPS decisions (they contain appeal decisions)
        $vpsDocuments = [];
        foreach ($results['documents'] as $doc) {
            $full = $this->getDocument($doc['id']);
            if (isset($full['court']) && str_contains($full['court'], 'Visoki prekršajni sud')) {
                $vpsDocuments[] = $full;
            }
        }

        $results['documents'] = $vpsDocuments;
        $results['count'] = count($vpsDocuments);

        return $results;
    }
}
