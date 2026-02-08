<?php
// app/Services/Informator/InformatorClient.php

namespace App\Services\Informator;

use App\Exceptions\ContinueException;
use App\Services\Informator\Extractors\PdfTextExtractor;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;
use TypeError;

class InformatorClient
{
    private ?string $sessionCookie = null;
    private ?CookieJar $cookieJar = null;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly PdfTextExtractor $pdfTextExtractor,
    ) {}

    /**
     * Authenticate with the Informator service and obtain a session cookie.
     * This is required for PDF downloads.
     */
    public function authenticate(): void
    {
        if ($this->sessionCookie !== null) {
            return; // Already authenticated
        }

        $baseUrl = rtrim((string) config('informator.base_url'), '/');
        $signInPath = (string) config('informator.auth.sign_in_url', '/users/sign_in');
        $signInUrl = $baseUrl . '/' . ltrim($signInPath, '/');

        $email = (string) config('informator.auth.email');
        $password = (string) config('informator.auth.password');

        if ($email === '' || $password === '') {
            throw new RuntimeException('Informator authentication credentials not configured. Set INFORMATOR_EMAIL and INFORMATOR_PASSWORD.');
        }

        $headers = $this->getBrowserHeaders();
        $timeout = (int) config('informator.http.timeout', 30);

        // Use a cookie jar to track cookies across requests
        $this->cookieJar = new CookieJar();

        $client = new Client([
            'timeout' => $timeout,
            'http_errors' => false,
            'cookies' => $this->cookieJar,
            'allow_redirects' => false,
        ]);

        // Step 1: GET the sign-in page to extract authenticity_token
        dump("Fetching sign-in page: {$signInUrl}");
        $getRequest = new Request('GET', $signInUrl, $headers);
        $getResponse = $client->send($getRequest);

        $status = (int) $getResponse->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Failed to fetch sign-in page: HTTP {$status}");
        }

        $signInHtml = $getResponse->getBody()->getContents();
        $authenticityToken = $this->extractAuthenticityToken($signInHtml);

        if ($authenticityToken === null) {
            throw new RuntimeException('Could not extract authenticity_token from sign-in page');
        }

        dump("Extracted authenticity_token: " . substr($authenticityToken, 0, 20) . "...");

        // Step 2: POST login credentials
        $postHeaders = array_merge($headers, [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Origin' => $baseUrl,
            'Referer' => $signInUrl,
        ]);

        $formData = http_build_query([
            'authenticity_token' => $authenticityToken,
            'user[email]' => $email,
            'user[password]' => $password,
            'user[remember_me]' => '0',
            'commit' => 'Prijava',
        ]);

        dump("Posting login to: {$signInUrl}");
        $postRequest = new Request('POST', $signInUrl, $postHeaders, $formData);
        $postResponse = $client->send($postRequest);

        $postStatus = (int) $postResponse->getStatusCode();

        // Successful login typically returns 302 redirect
        if ($postStatus !== 302 && $postStatus !== 200) {
            throw new RuntimeException("Login failed: HTTP {$postStatus}");
        }

        // Extract session cookie from the cookie jar
        $this->sessionCookie = $this->extractSessionCookie();

        if ($this->sessionCookie === null) {
            throw new RuntimeException('Login succeeded but no session cookie was set');
        }

        dump("Authentication successful. Session cookie obtained.");
    }

    /**
     * Extract the authenticity_token from the sign-in HTML page.
     */
    private function extractAuthenticityToken(string $html): ?string
    {
        $crawler = new Crawler($html);

        try {
            $tokenNode = $crawler->filter('input[name="authenticity_token"]');
            if ($tokenNode->count() > 0) {
                return $tokenNode->attr('value');
            }
        } catch (\Throwable $e) {
            // Fall through to regex fallback
        }

        // Fallback: regex extraction
        if (preg_match('/name="authenticity_token"\s+value="([^"]+)"/', $html, $matches)) {
            return $matches[1];
        }

        if (preg_match('/value="([^"]+)"\s+name="authenticity_token"/', $html, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract the session cookie from the cookie jar.
     */
    private function extractSessionCookie(): ?string
    {
        if ($this->cookieJar === null) {
            return null;
        }

        $cookies = $this->cookieJar->toArray();
        $cookieParts = [];

        foreach ($cookies as $cookie) {
            $name = $cookie['Name'] ?? '';
            $value = $cookie['Value'] ?? '';
            if ($name !== '' && $value !== '') {
                $cookieParts[] = "{$name}={$value}";
            }
        }

        return $cookieParts !== [] ? implode('; ', $cookieParts) : null;
    }

    /**
     * Get browser-like headers for requests.
     */
    private function getBrowserHeaders(): array
    {
        return [
            'User-Agent' => (string) config('informator.headers.common.User-Agent', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language' => (string) config('informator.headers.common.Accept-Language', 'en-US,en;q=0.9'),
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Sec-CH-UA' => '"Chromium";v="142", "Google Chrome";v="142", "Not_A Brand";v="99"',
            'Sec-CH-UA-Mobile' => '?0',
            'Sec-CH-UA-Platform' => '"Linux"',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
            'Upgrade-Insecure-Requests' => '1',
        ];
    }

    /**
     * Get the current session cookie, authenticating if necessary.
     */
    public function getSessionCookie(): string
    {
        if ($this->sessionCookie === null) {
            $this->authenticate();
        }

        return $this->sessionCookie ?? '';
    }

    /**
     * Clear the current session (force re-authentication on next request).
     */
    public function clearSession(): void
    {
        $this->sessionCookie = null;
        $this->cookieJar = null;
    }

    /**
     * Preconfigured request template (headers, base URL, retry, timeout).
     */
    private function request(string $profile): PendingRequest
    {
        $baseUrl = (string) config('informator.base_url');

        $commonHeaders = (array) config('informator.headers.common', []);
        $profileHeaders = (array) config("informator.headers.$profile", []);

        $headers = array_merge($commonHeaders, $profileHeaders);

        $timeout = (int) config('informator.http.timeout', 30);

        $retryTimes = (int) data_get(config('informator.http.retry', []), 'times', 0);
        $retrySleepMs = (int) data_get(config('informator.http.retry', []), 'sleep', 0);

        $req = $this->http
            ->baseUrl($baseUrl)
            ->withHeaders($headers)
            ->timeout($timeout);

        if ($retryTimes > 0) {
            // throw: false => you decide when to throw; we validate responses manually.
            $req = $req->retry($retryTimes, $retrySleepMs, throw: false);
        }

        return $req;
    }

    /**
     * Listing Endpoint (HTML): fetch raw HTML.
     *
     * NOTE: This intentionally mirrors the known-working Guzzle flow:
     * Request -> sendAsync()->wait() -> getBody()->getContents()
     */
    public function fetchListingHtml(array $query = []): string
    {
        $baseUrl = rtrim((string) config('informator.base_url'), '/');
        $path = (string) config('informator.endpoints.list');
        $url = $baseUrl . '/' . ltrim($path, '/');

        // IMPORTANT: Use ONLY the listing header profile.
        // Merging with "common" can introduce duplicates or override casing-sensitive headers.
        $headers = (array) config('informator.headers.listing', []);

        // Safe fallback if someone removed them from config.
        $headers['User-Agent'] ??= (string) config('informator.headers.common.User-Agent', 'Mozilla/5.0');
        $headers['Accept-Language'] ??= (string) config('informator.headers.common.Accept-Language', 'en-US,en;q=0.9');

        // Avoid sending an empty Cookie header.
        if (array_key_exists('Cookie', $headers) && trim((string) $headers['Cookie']) === '') {
            unset($headers['Cookie']);
        }

        $timeout = (int) config('informator.http.timeout', 30);
        $retryTimes = (int) data_get(config('informator.http.retry', []), 'times', 0);
        $retrySleepMs = (int) data_get(config('informator.http.retry', []), 'sleep', 0);

        $client = new Client([
            'timeout' => $timeout,
            'http_errors' => false,
        ]);

        $attempts = max(1, $retryTimes + 1);
        $lastErrorMessage = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $request = new Request('GET', $url, $headers);

                $res = $client
                    ->sendAsync($request, [
                        'query' => $query,
                    ])
                    ->wait();

                $status = (int) $res->getStatusCode();
                if ($status < 200 || $status >= 300) {
                    throw new RuntimeException("Listing request failed: HTTP {$status}");
                }

                $body = $res->getBody()->getContents();
                if ($body === '') {
                    throw new RuntimeException('Listing request returned an empty body.');
                }

                dump($url . '?' . http_build_query($query) . ' - fetched listing HTML, length=' . strlen($body));
                return $body;
            } catch (GuzzleException|RuntimeException $e) {
                $lastErrorMessage = $e->getMessage();

                if ($attempt < $attempts && $retrySleepMs > 0) {
                    usleep($retrySleepMs * 1000);
                }
            }
        }

        throw new RuntimeException(
            'Listing request failed after retries.' . ($lastErrorMessage ? ' Last error: ' . $lastErrorMessage : '')
        );
    }

    /**
     * Parse listing HTML and extract items (including the ID).
     * This is where we bridge "step 1 → step 2" by extracting the ID from hrefs.
     */
    public function parseListingForItems(string $html): array
    {
        $linkSelector = config('informator.parsing.listing_item_link_selector');
        $hrefRegex = config('informator.parsing.listing_item_href_regex');

        $crawler = new Crawler($html);

        $items = [];

        $crawler->filter($linkSelector)->each(function (Crawler $node) use (&$items, $hrefRegex) {
            $href = (string) ($node->attr('href') ?? '');
            $path = (string) (parse_url($href, PHP_URL_PATH) ?? '');

            if (!$href || !$path) {
                return;
            }

            if (!preg_match($hrefRegex, $path, $m)) {
                return;
            }

            $id = (string) (Arr::get($m, 'id') ?? Arr::get($m, 1) ?? '');
            if ($id === '') {
                return;
            }

            // Listing text (optional): “text only” from the listing item node.
            // This can be useful for quick previews or dedupe.
            $text = trim(preg_replace('/\s+/u', ' ', $node->text(' ', true)));

            $items[] = [
                'id' => $id,
                'href' => $href,
                'text' => $text,
            ];
        });

        // De-dupe by ID while preserving first occurrence order.
        $unique = [];
        foreach ($items as $item) {
            $unique[$item['id']] ??= $item;
        }

        return array_values($unique);
    }

    /**
     * Convenience: listing endpoint → extracted IDs.
     */
    public function listDecisionIds(array $query = []): array
    {
        $html = $this->fetchListingHtml($query);
        $items = $this->parseListingForItems($html);

        return array_values(array_map(fn ($i) => $i['id'], $items));
    }

    /**
     * Single Fetch Endpoint (PDF): download raw PDF bytes by ID.
     *
     * Authenticates first if needed, then uses the session cookie.
     */
    public function fetchDecisionPdfBinary(string|int $id): string
    {
        // Ensure we have a valid session cookie
        $sessionCookie = $this->getSessionCookie();

        $baseUrl = rtrim((string) config('informator.base_url'), '/');

        $template = (string) config('informator.endpoints.single_pdf');
        $resolved = str_replace('{id}', (string) $id, $template);

        // Allow config to be either absolute or relative.
        $url = str_starts_with($resolved, 'http://') || str_starts_with($resolved, 'https://')
            ? $resolved
            : $baseUrl . '/' . ltrim($resolved, '/');

        // Use browser-like headers with authenticated session cookie
        $headers = $this->getBrowserHeaders();
        $headers['Cookie'] = $sessionCookie;
        $headers['Referer'] = $baseUrl . '/sudske-odluke';

        $timeout = (int) config('informator.http.timeout', 30);
        $retryTimes = (int) data_get(config('informator.http.retry', []), 'times', 0);
        $retrySleepMs = (int) data_get(config('informator.http.retry', []), 'sleep', 0);

        $client = new Client([
            'timeout' => $timeout,
            'http_errors' => false,
        ]);

        $attempts = max(1, $retryTimes + 1);
        $lastErrorMessage = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $request = new Request('GET', $url, $headers);

                $res = $client->send($request);
                $status = (int) $res->getStatusCode();

                // If we get 401/403, the session may have expired - try re-authenticating once
                if (($status === 401 || $status === 403) && $attempt === 1) {
                    dump("Session expired for id={$id}, re-authenticating...");
                    $this->clearSession();
                    $sessionCookie = $this->getSessionCookie();
                    $headers['Cookie'] = $sessionCookie;
                    continue;
                }

                if ($status < 200 || $status >= 300) {
                    throw new ContinueException("PDF request failed for id={$id}: HTTP {$status}");
                }

                $contentType = strtolower((string) $res->getHeaderLine('Content-Type'));
                if ($contentType !== '' && !str_contains($contentType, 'pdf')) {
                    dump("PDF request for id={$id} returned unexpected Content-Type={$contentType}");
                    throw new ContinueException("Expected PDF for id={$id}, got Content-Type={$contentType}");
                }

                $pdfBinary = $res->getBody()->getContents();
                if ($pdfBinary === '') {
                    throw new ContinueException("PDF request returned empty body for id={$id}");
                }

                dump("url={$url} - fetched PDF binary for id={$id}, length=" . strlen($pdfBinary));

                return $pdfBinary;
            } catch (GuzzleException|ContinueException $e) {
                $lastErrorMessage = $e->getMessage();

                if ($attempt < $attempts && $retrySleepMs > 0) {
                    usleep($retrySleepMs * 1000);
                }
            }
        }

        throw new ContinueException(
            "PDF request failed after retries for id={$id}." . ($lastErrorMessage ? " Last error: {$lastErrorMessage}" : '')
        );
    }

    /**
     * Single Fetch Endpoint (PDF): fetch and return text content only.
     * @throws ContinueException
     */
    public function fetchDecisionText(string|int $id): string
    {
        $pdfBinary = $this->fetchDecisionPdfBinary($id);

        return $this->pdfTextExtractor->extractText($pdfBinary);
    }

    /**
     * High-level helper: scrape one listing page and fetch text for each ID.
     */
    public function scrapeListingPage(array $query = [], ?int $limit = null): array
    {
        $queryHash = md5(serialize($query));

        $ids = Cache::remember($queryHash, 300, function () use ($query) {
            return $this->listDecisionIds($query);
        });

        if ($limit !== null) {
            $ids = array_slice($ids, 0, max(0, $limit));
        }

        $out = [];
        foreach ($ids as $id) {
            dump("Fetching text for id={$id}/" . count($ids) . " from listing query={$queryHash}");
            try {
                $out[] = [
                    'id' => $id,
                    'text' => $this->fetchDecisionText($id),
                ];
            } catch (ContinueException $e) {
                continue;
            } catch (Throwable $error) {
                dump("Error fetching text for id={$id}: " . $error->getMessage());
                continue;
            }
        }

        return $out;
    }
}
