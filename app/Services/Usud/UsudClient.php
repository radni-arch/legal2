<?php

namespace App\Services\Usud;

use Carbon\Carbon;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class UsudClient
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
        $cfgRpm = $rpm ?? (int) (config('usud.rpm') ?? 20);
        $this->rpm = $cfgRpm > 0 ? $cfgRpm : 20;
        $this->backoffMs = $backoffMs ?? (int) (config('usud.backoff_ms') ?? 800);
    }

    public static function fromConfig(): self
    {
        $cfg = config('usud');

        return new self(
            baseUrl: rtrim($cfg['base_url'] ?? 'https://sljeme.usud.hr', '/'),
            timeout: (int) ($cfg['timeout'] ?? 30),
            retry: (int) ($cfg['retry'] ?? 2),
            delayMs: (int) ($cfg['delay_ms'] ?? 700),
            rpm: (int) ($cfg['rpm'] ?? 20),
            backoffMs: (int) ($cfg['backoff_ms'] ?? 800),
        );
    }

    public function search(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['error' => 'Query is empty'];
        }

        $searchUrl = $this->searchUrl();
        $jar = new CookieJar();

        $get = $this->request('GET', $searchUrl, $jar);
        if (! ($get['ok'] ?? false)) {
            return [
                'error' => $get['error'] ?? 'Search page request failed',
                'status' => $get['status'] ?? null,
                'url' => $searchUrl,
            ];
        }

        $viewId = $this->extractViewId($get['body'] ?? '', $get['content_type'] ?? null);
        if (! $viewId) {
            return [
                'error' => 'Missing $$viewid token',
                'status' => $get['status'] ?? null,
                'url' => $searchUrl,
            ];
        }

        $multipart = [
            ['name' => 'view:_id1:_id2:ebSearchGlobal', 'contents' => $query],
            ['name' => '$$viewid', 'contents' => $viewId],
            ['name' => '$$xspsubmitid', 'contents' => 'view:_id1:_id2:_id22'],
            ['name' => '$$xspexecid', 'contents' => ''],
            ['name' => '$$xspsubmitvalue', 'contents' => ''],
            ['name' => '$$xspsubmitscroll', 'contents' => '0|0'],
            ['name' => 'view:_id1', 'contents' => 'view:_id1'],
        ];

        $post = $this->request('POST', $searchUrl, $jar, $multipart);
        if (! ($post['ok'] ?? false)) {
            return [
                'error' => $post['error'] ?? 'Search POST failed',
                'status' => $post['status'] ?? null,
                'url' => $searchUrl,
            ];
        }

        $html = $this->normalizeHtml($post['body'] ?? '', $post['content_type'] ?? null);
        $items = $this->extractListItems($html);

        return [
            'url' => $searchUrl,
            'items' => $items,
        ];
    }

    public function fetchDecisionDetail(string $documentId, bool $includeHtml = false): array
    {
        $url = $this->detailUrl($documentId);
        $res = $this->request('GET', $url, null);
        if (! ($res['ok'] ?? false)) {
            return [
                'error' => $res['error'] ?? 'Detail request failed',
                'status' => $res['status'] ?? null,
                'url' => $url,
                'id' => $documentId,
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
        $meta = $this->parseText($title);

        $payload = array_merge($meta, [
            'id' => $documentId,
            'title' => $title !== '' ? $title : ($meta['title'] ?? null),
            'detail_url' => $url,
            'pdf_url' => $pdfUrl,
        ]);

        if ($includeHtml) {
            $payload['html'] = $html;
        }

        if (! $payload['case_number'] && $pdfUrl) {
            $payload['case_number'] = $this->caseNumberFromPdfUrl($pdfUrl);
        }

        return $payload;
    }

    public function downloadPdf(string $pdfUrl): array
    {
        $res = $this->request('GET', $pdfUrl, null);
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

    public function buildPdfUrl(string $documentId, string $filename): string
    {
        $base = rtrim((string) config('usud.pdf_base_url', $this->baseUrl.'/Usud/Praksaw.nsf'), '/');
        $filename = str_ends_with($filename, '.pdf') ? $filename : ($filename.'.pdf');

        return $base.'/'.$documentId.'/$FILE/'.$filename;
    }

    public function searchUrl(): string
    {
        $path = (string) (config('usud.search_path') ?? '/usud/praksaw.nsf/vSearchResults.xsp');

        return $this->baseUrl.$path;
    }

    public function detailUrl(string $documentId): string
    {
        return $this->baseUrl.'/usud/praksaw.nsf/fOdluka.xsp?action=openDocument&documentId='.$documentId;
    }

    protected function extractViewId(string $html, ?string $contentType = null): ?string
    {
        $html = $this->normalizeHtml($html, $contentType);
        $crawler = new Crawler($html);
        $input = $crawler->filter('input[name="$$viewid"]');
        if ($input->count() === 0) {
            return null;
        }

        $value = trim((string) $input->attr('value'));

        return $value !== '' ? $value : null;
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

            $documentId = $this->extractDocumentId($href);
            if (! $documentId) {
                continue;
            }

            $text = trim($node->textContent ?? '');
            $meta = $this->parseText($text);
            $filename = $this->filenameFromCaseNumber($meta['case_number'] ?? null);
            $pdfUrl = $filename ? $this->buildPdfUrl($documentId, $filename) : null;

            $items[$documentId] = array_merge($meta, [
                'id' => $documentId,
                'title' => $text,
                'detail_url' => $this->toAbsoluteUrl($href),
                'pdf_url' => $pdfUrl,
            ]);
        }

        return array_values($items);
    }

    protected function isDecisionLink(?string $href): bool
    {
        if (! $href) {
            return false;
        }

        return stripos($href, 'fOdluka.xsp') !== false
            && stripos($href, 'documentId=') !== false;
    }

    protected function extractDocumentId(string $href): ?string
    {
        $href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $parts = parse_url($href);
        $query = $parts['query'] ?? '';
        if ($query === '') {
            return null;
        }

        parse_str($query, $vars);
        $documentId = $vars['documentId'] ?? null;

        return $documentId ?: null;
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

    protected function parseText(string $text): array
    {
        $date = $this->extractDate($text);
        $caseNumber = $this->extractCaseNumber($text);
        $decisionType = $this->extractDecisionType($text);

        return [
            'title' => $text !== '' ? $text : null,
            'decision_date' => $date,
            'case_number' => $caseNumber,
            'decision_type' => $decisionType,
            'court' => 'USUD',
        ];
    }

    protected function extractDate(string $text): ?string
    {
        if (! preg_match('/\b(\d{1,2}\.\d{1,2}\.\d{4})\b/u', $text, $m)) {
            return null;
        }

        $raw = rtrim($m[1], '.');
        try {
            return Carbon::createFromFormat('j.n.Y', $raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function extractCaseNumber(string $text): ?string
    {
        if (preg_match('/\b([A-ZČĆŽŠĐ\-]{1,10}[0-9A-Za-z\-]*\-\d+\/\d{4})\b/u', $text, $m)) {
            return trim($m[1]);
        }

        $segments = array_map('trim', explode(' - ', $text));
        if (isset($segments[1]) && str_contains($segments[1], '/')) {
            return $segments[1];
        }

        return null;
    }

    protected function extractDecisionType(string $text): ?string
    {
        $lower = mb_strtolower($text, 'UTF-8');
        $hasDecision = str_contains($lower, 'odluka');
        $hasOrder = str_contains($lower, 'rješenje') || str_contains($lower, 'rjesenje');
        $hasConclusion = str_contains($lower, 'zaključak') || str_contains($lower, 'zakljucak');

        if ($hasDecision && $hasOrder) {
            return 'Odluka i rješenje';
        }
        if ($hasOrder) {
            return 'Rješenje';
        }
        if ($hasDecision) {
            return 'Odluka';
        }
        if ($hasConclusion) {
            return 'Zaključak';
        }

        return null;
    }

    protected function filenameFromCaseNumber(?string $caseNumber): ?string
    {
        if (! $caseNumber) {
            return null;
        }

        $file = str_replace('/', '-', $caseNumber);
        $file = preg_replace('/\s+/', '', $file ?? '');
        $file = preg_replace('/[^A-Za-z0-9\-]/', '', $file ?? '');

        return $file !== '' ? $file.'.pdf' : null;
    }

    protected function caseNumberFromPdfUrl(string $pdfUrl): ?string
    {
        $path = parse_url($pdfUrl, PHP_URL_PATH);
        if (! $path) {
            return null;
        }

        $name = basename($path);
        $name = preg_replace('/\.pdf$/i', '', $name ?? '');
        if ($name === '') {
            return null;
        }

        if (preg_match('/^(.*)-(\d{4})$/', $name, $m)) {
            return $m[1].'/'.$m[2];
        }

        return str_replace('-', '/', $name);
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

    protected function http(?CookieJar $jar = null)
    {
        $sleepMs = max(100, $this->delayMs);

        $req = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; UsudFetcher/1.0)',
            'Accept-Language' => 'hr-HR,hr;q=0.9,en-US;q=0.8,en;q=0.7',
            'Referer' => $this->baseUrl.'/',
            'Connection' => 'keep-alive',
        ])->timeout($this->timeout)
            ->retry($this->retry, $sleepMs, function ($exception) {
                Log::debug('[UsudClient] Retry triggered', [
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                ]);
                usleep($this->backoffMs * 1000);

                return true;
            })
            ->withOptions([
                'allow_redirects' => true,
            ]);

        if ($jar) {
            $req = $req->withOptions(['cookies' => $jar]);
        }

        return $req;
    }

    protected function request(string $method, string $url, ?CookieJar $jar = null, ?array $multipart = null): array
    {
        $this->throttle();
        try {
            $req = $this->http($jar);
            if ($multipart !== null) {
                $req = $req->asMultipart();
                $response = $req->post($url, $multipart);
            } elseif (strtoupper($method) === 'GET') {
                $response = $req->get($url);
            } else {
                $response = $req->send($method, $url);
            }

            if (! $response->ok()) {
                Log::warning('[UsudClient] Request failed', [
                    'method' => $method,
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
            Log::error('[UsudClient] Request error', [
                'method' => $method,
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
