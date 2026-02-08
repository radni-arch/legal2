<?php

namespace App\Services\Esljp;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class EsljpClient
{
    protected static float $lastCallAt = 0.0;

    protected int $rpm;

    protected int $backoffMs;

    public function __construct(
        protected string $baseUrl,
        protected int $timeout = 30,
        protected int $retry = 2,
        protected int $delayMs = 700,
        ?int $rpm = null,
        ?int $backoffMs = null,
    ) {
        $cfgRpm = $rpm ?? (int) (config('esljp.rpm') ?? 20);
        $this->rpm = $cfgRpm > 0 ? $cfgRpm : 20;
        $this->backoffMs = $backoffMs ?? (int) (config('esljp.backoff_ms') ?? 800);
    }

    public static function fromConfig(): self
    {
        $cfg = config('esljp');

        return new self(
            baseUrl: rtrim($cfg['base_url'] ?? 'https://sljeme.usud.hr', '/'),
            timeout: (int) ($cfg['timeout'] ?? 30),
            retry: (int) ($cfg['retry'] ?? 2),
            delayMs: (int) ($cfg['delay_ms'] ?? 700),
            rpm: (int) ($cfg['rpm'] ?? 20),
            backoffMs: (int) ($cfg['backoff_ms'] ?? 800),
        );
    }

    public function search(string $query, array $options = []): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['error' => 'Query is empty'];
        }

        $url = $this->buildSearchUrl($query, $options);
        $res = $this->safeGet($url, 'search');
        if (! ($res['ok'] ?? false)) {
            return [
                'error' => $res['error'] ?? 'Search request failed',
                'status' => $res['status'] ?? null,
                'url' => $url,
            ];
        }

        $html = $this->normalizeHtml($res['body'] ?? '', $res['content_type'] ?? null);
        $items = $this->extractListItems($html);

        return [
            'url' => $url,
            'items' => $items,
        ];
    }

    public function fetchDecisionDetail(string $id, bool $includeHtml = false): array
    {
        $url = $this->detailUrl($id);
        $res = $this->safeGet($url, 'detail');
        if (! ($res['ok'] ?? false)) {
            return [
                'error' => $res['error'] ?? 'Detail request failed',
                'status' => $res['status'] ?? null,
                'url' => $url,
                'id' => $id,
            ];
        }

        $html = $this->normalizeHtml($res['body'] ?? '', $res['content_type'] ?? null);
        $crawler = new Crawler($html);

        $title = '';
        $titleNode = $crawler->filter('title');
        if ($titleNode->count() > 0) {
            $title = trim($titleNode->text());
        }

        $pdfUrl = $this->extractPdfUrl($crawler);
        $meta = $this->parseTitle($title);

        $payload = array_merge($meta, [
            'id' => $id,
            'title' => $title !== '' ? $title : ($meta['title'] ?? null),
            'detail_url' => $url,
            'pdf_url' => $pdfUrl,
        ]);

        if ($includeHtml) {
            $payload['html'] = $html;
        }

        return $payload;
    }

    public function downloadPdf(string $pdfUrl): array
    {
        $res = $this->safeGet($pdfUrl, 'pdf');
        if (! ($res['ok'] ?? false)) {
            return [
                'ok' => false,
                'status' => $res['status'] ?? null,
                'error' => $res['error'] ?? 'PDF request failed',
                'url' => $pdfUrl,
            ];
        }

        return [
            'ok' => true,
            'status' => $res['status'] ?? 200,
            'bytes' => $res['body'] ?? '',
            'url' => $pdfUrl,
            'content_type' => $res['content_type'] ?? null,
        ];
    }

    public function detailUrl(string $id): string
    {
        return $this->baseUrl.'/usud/prakES.nsf/Praksa/'.$id.'?OpenDocument';
    }

    protected function buildSearchUrl(string $query, array $options): string
    {
        $path = rtrim((string) (config('esljp.search_path') ?? '/usud/prakES.nsf/PraksaP/'), '/').'/';
        $searchOrder = (int) ($options['search_order'] ?? config('esljp.search_order', 4));
        $start = max(1, (int) ($options['start'] ?? 1));
        $count = max(1, (int) ($options['count'] ?? $options['limit'] ?? config('esljp.count', 1000)));
        $searchMax = max($count, (int) ($options['search_max'] ?? config('esljp.search_max', 1000)));

        return $this->baseUrl.$path.
            '?SearchView'.
            '&Query='.urlencode($query).
            '&SearchOrder='.$searchOrder.
            '&Start='.$start.
            '&Count='.$count.
            '&SearchMax='.$searchMax;
    }

    protected function extractListItems(string $html): array
    {
        $crawler = new Crawler($html);
        $items = [];

        foreach ($crawler->filter('a[href]') as $node) {
            $href = $node->getAttribute('href');
            if (! $this->isDecisionLink($href)) {
                continue;
            }

            $id = $this->extractDecisionId($href);
            if ($id === null) {
                continue;
            }

            $title = trim($node->textContent ?? '');
            $meta = $this->parseTitle($title);

            $items[$id] = array_merge($meta, [
                'id' => $id,
                'title' => $title,
                'detail_url' => $this->toAbsoluteUrl($href),
            ]);
        }

        return array_values($items);
    }

    protected function isDecisionLink(?string $href): bool
    {
        if (! $href) {
            return false;
        }

        return str_contains($href, '/usud/prakES.nsf/Praksa/')
            && stripos($href, 'OpenDocument') !== false;
    }

    protected function extractDecisionId(string $href): ?string
    {
        if (preg_match('~Praksa/([^?]+)\?OpenDocument~i', $href, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function extractPdfUrl(Crawler $crawler): ?string
    {
        foreach ($crawler->filter('a[href]') as $node) {
            $href = $node->getAttribute('href');
            if (! $href) {
                continue;
            }

            if (stripos($href, '$FILE/') === false) {
                continue;
            }

            if (! preg_match('~\.pdf($|[?#])~i', $href)) {
                continue;
            }

            return $this->toAbsoluteUrl($href);
        }

        return null;
    }

    protected function parseTitle(string $title): array
    {
        $decisionType = $this->extractDecisionType($title);
        $decisionDate = $this->extractDate($title);
        $caseNumber = $this->extractApplicationNumber($title);

        return [
            'title' => $title !== '' ? $title : null,
            'decision_type' => $decisionType,
            'decision_date' => $decisionDate,
            'case_number' => $caseNumber,
            'court' => 'ESLJP',
        ];
    }

    protected function extractDecisionType(string $title): ?string
    {
        $haystack = mb_strtolower($title, 'UTF-8');
        $map = [
            'presuda' => 'Presuda',
            'odluka' => 'Odluka',
            'rješenje' => 'Rješenje',
            'rjesenje' => 'Rješenje',
            'zaključak' => 'Zaključak',
            'zakljucak' => 'Zaključak',
        ];

        foreach ($map as $needle => $label) {
            if (str_contains($haystack, $needle)) {
                return $label;
            }
        }

        return null;
    }

    protected function extractDate(string $title): ?string
    {
        if (! preg_match('/\b(\d{1,2}\.\d{1,2}\.\d{4})\.?\b/u', $title, $m)) {
            return null;
        }

        $raw = rtrim($m[1], '.');
        try {
            return Carbon::createFromFormat('j.n.Y', $raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function extractApplicationNumber(string $title): ?string
    {
        if (preg_match('/zahtjev\s+br\.?\s*([0-9\/\-]+)/iu', $title, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    protected function toAbsoluteUrl(string $href): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        return $this->baseUrl.'/'.ltrim($href, '/');
    }

    protected function normalizeHtml(string $html, ?string $contentType = null): string
    {
        $charset = null;
        if ($contentType && preg_match('/charset=([a-zA-Z0-9\-]+)/i', $contentType, $m)) {
            $charset = $m[1];
        }
        if (! $charset && preg_match('/charset=([a-zA-Z0-9\-]+)/i', $html, $m)) {
            $charset = $m[1];
        }

        if ($charset && strcasecmp($charset, 'UTF-8') !== 0) {
            $converted = function_exists('mb_convert_encoding')
                ? @mb_convert_encoding($html, 'UTF-8', $charset)
                : false;

            if ($converted !== false && $converted !== null) {
                return $converted;
            }

            $converted = @iconv($charset, 'UTF-8//IGNORE', $html);
            if ($converted !== false) {
                return $converted;
            }
        }

        return $html;
    }

    protected function http()
    {
        $sleepMs = max(100, $this->delayMs);

        return Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; ESLJPFetcher/1.0)',
            'Accept-Language' => 'hr-HR,hr;q=0.9,en-US;q=0.8,en;q=0.7',
            'Referer' => $this->baseUrl.'/',
            'Connection' => 'keep-alive',
        ])->timeout($this->timeout)
            ->retry($this->retry, $sleepMs, function ($exception) {
                Log::debug('[EsljpClient] Retry triggered', [
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                ]);
                usleep($this->backoffMs * 1000);

                return true;
            });
    }

    protected function safeGet(string $url, string $context): array
    {
        $this->throttle();
        try {
            $response = $this->http()->get($url);

            if (! $response->ok()) {
                Log::warning('[EsljpClient] Request failed', [
                    'context' => $context,
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return [
                    'ok' => false,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'content_type' => $response->header('Content-Type'),
                ];
            }

            return [
                'ok' => true,
                'status' => $response->status(),
                'body' => $response->body(),
                'content_type' => $response->header('Content-Type'),
            ];
        } catch (\Throwable $e) {
            Log::error('[EsljpClient] Request error', [
                'context' => $context,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function throttle(): void
    {
        $minInterval = 60 / max(1, $this->rpm);
        $now = microtime(true);
        $elapsed = $now - self::$lastCallAt;

        if ($elapsed < $minInterval) {
            usleep((int) (($minInterval - $elapsed) * 1_000_000));
        }

        self::$lastCallAt = microtime(true);
    }
}
