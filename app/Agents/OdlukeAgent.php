<?php

namespace App\Agents;

use App\Tools\LawArticleByIdTool;
use App\Tools\LawArticlesSearchTool;
use App\Tools\OdlukeDownloadTool;
use App\Tools\OdlukeMetaTool;
use App\Tools\OdlukeSearchTool;
use Illuminate\Support\Facades\Cache;
use Vizra\VizraADK\Agents\BaseLlmAgent;

class OdlukeAgent extends BaseLlmAgent
{
    protected string $name = 'odluke_agent';

    protected string $description = 'Autonomous agent for searching and downloading Croatian court decisions from odluke.sudovi.hr using MCP tools.';

    // Explicit OpenAI provider and a broadly available model
    protected ?string $provider = 'openai';

    protected string $model = 'gpt-4o-mini';

    protected int $maxSteps = 8;

    // Use direct tools instead of MCP servers (works in all contexts: HTTP, dashboard, artisan)
    protected array $tools = [
        OdlukeSearchTool::class,
        OdlukeMetaTool::class,
        OdlukeDownloadTool::class,
        LawArticlesSearchTool::class,
        LawArticleByIdTool::class,
    ];

    protected bool $showInChatUi = true;

    protected bool $includeConversationHistory = true;

    protected int $historyLimit = 8;

    protected string $contextStrategy = 'recent';

    protected bool $useStatefulResponses = true;

    public function __construct()
    {
        parent::__construct();
        $this->instructions = $this->buildInstructions();
    }

    private function buildInstructions(): string
    {
        $today = date('Y-m-d');

        return <<<SYS
You are an autonomous legal-research assistant specialized in Croatian court decisions and laws.
You have these tools available:
- odluke_search: search the list endpoint and return IDs with paging and optional filters (q, params, page, limit, base_url)
- odluke_meta: fetch metadata for one or more IDs (id or ids[], base_url)
- odluke_download: get direct download URLs and optionally save PDF/HTML locally (id, format in {pdf|html|both}, save, base_url)
- law_articles_search: search Croatian laws and law articles by query, law_number, title (limit 1-100)
- law_article_by_id: get a specific law article by its ID

Operating rules:
1) If key constraints are missing, briefly ask for them; otherwise proceed. Useful filters:
   - sud (court) e.g. "Vrhovni sud Republike Hrvatske"
   - vo (vrsta odluke), e.g. "Presuda", "Rješenje"
   - od/do (datum raspona, YYYY-MM-DD). Today's date is {$today}.
2) Start with odluke-search. Provide sensible defaults:
   - limit: 50–100; page: 1 initially; iterate if needed
   - q: user topic/keywords; keep concise
   - params: encode extra filters into a query string, e.g. "sort=dat&vo=Presuda&od=2024-01-01&do={$today}" when applicable
3) Use odluke-meta on top candidates to inspect title, date, court, subject. Prefer recent and relevant results.
4) When downloads are requested, call odluke-download for chosen IDs:
   - Prefer format=pdf unless HTML requested; use format=both if both are requested
   - Set save=true only when the user wants local files
5) Be efficient—do not download too many items. Usually 1–10 unless the user specifies otherwise.
6) Summarize clearly:
   - Compact list: date, court, number, subject
   - Download locations: direct URLs; include saved file paths if save=true
7) If no results, relax filters and retry once (e.g., broaden q or remove vo/date). Explain the change.
8) Respect base_url overrides when provided.
9) Validate arguments before each tool call. On failures, adjust and retry once.

Language: Reply in Croatian if the user writes Croatian; otherwise reply in the user's language. Keep responses concise and practical.
SYS;
    }

    /**
     * Execute query asynchronously with environment detection.
     *
     * @param  bool  $async  Force async execution (default: auto-detect)
     */
    public static function executeAsync(string $query, array $context = [], bool $async = true): mixed
    {
        // Generate unique cache key for this query
        $cacheKey = 'odluke_agent:'.md5($query.json_encode($context));

        if (! $async) {
            // Synchronous execution
            $agent = new self;
            $result = $agent->run($query);

            Cache::put($cacheKey, [
                'status' => 'completed',
                'result' => $result,
                'completed_at' => now()->toIso8601String(),
            ], 3600);

            return [
                'mode' => 'sync',
                'cache_key' => $cacheKey,
                'result' => $result,
            ];
        }

        // Async execution with environment detection
        $job = \App\Jobs\ExecuteOdlukeAgentJob::dispatchWithEnvDetection($query, $context, $cacheKey);

        return [
            'mode' => 'async',
            'cache_key' => $cacheKey,
            'message' => 'Query processing started. Check status using the cache_key.',
        ];
    }

    /**
     * Get status of async execution.
     */
    public static function getAsyncStatus(string $cacheKey): ?array
    {
        return Cache::get($cacheKey);
    }

    /**
     * Wait for async execution to complete (polling).
     */
    public static function waitForAsync(string $cacheKey, int $maxWaitSeconds = 60, int $pollIntervalMs = 500): ?array
    {
        $startTime = time();

        while ((time() - $startTime) < $maxWaitSeconds) {
            $status = self::getAsyncStatus($cacheKey);

            if ($status && $status['status'] !== 'running') {
                return $status;
            }

            usleep($pollIntervalMs * 1000);
        }

        return [
            'status' => 'timeout',
            'message' => 'Query execution exceeded maximum wait time',
        ];
    }
}
