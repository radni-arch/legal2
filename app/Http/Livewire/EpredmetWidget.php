<?php

namespace App\Http\Livewire;

use App\GraphQL\AutoDiscovery\Exceptions\GraphQLQueryException;
use App\GraphQL\AutoDiscovery\GraphQLAutoClient;
use App\Jobs\FetchCourtCasesJob;
use App\Models\CaseParty;
use App\Models\Court;
use App\Models\CourtCase;
use App\Models\CourtCaseDocument;
use App\Models\SyncLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * EpredmetWidget - Croatian E-Court Case Lookup Widget
 *
 * Displays case (predmet) data from the Croatian e-court system (EKOM)
 * via GraphQL API integration. Shows case details, hearings, parties,
 * documents, and timeline information.
 *
 * @property int|string $sud Court ID (default: 5107 for Općinski sud u Osijeku)
 * @property string $oznakaBroj Case number/designation (e.g., "Pp Prz-74/2025")
 * @property array|null $data Fetched case data from GraphQL API
 * @property string|null $error Error message if fetch fails
 * @property bool $loading Loading state indicator
 * @property float|null $tookMs Request duration in milliseconds
 * @property array $dateLabels Croatian labels for key dates in case timeline
 */
class EpredmetWidget extends Component
{
    /**
     * Court ID (sud) - can be numeric or string
     */
    public int|string $sud = 5107;

    /**
     * Case number/designation (oznaka/broj)
     */
    public string $oznakaBroj = 'Pp Prz-74/2025';

    /**
     * Fetched case data from GraphQL API
     */
    public ?array $data = null;

    /**
     * Error message if fetch fails
     */
    public ?string $error = null;

    /**
     * Loading state indicator
     */
    public bool $loading = false;

    /**
     * Request duration in milliseconds
     */
    public ?float $tookMs = null;

    /**
     * Active tab: 'lookup', 'sync-status', 'batch-fetch', or 'analytics'
     */
    public string $activeTab = 'lookup';

    /**
     * Sync logs for status display
     */
    public array $syncLogs = [];

    /**
     * Summary statistics across all sync logs
     */
    public array $syncSummary = [
        'total_fetched' => 0,
        'total_saved' => 0,
        'total_errors' => 0,
        'courts_completed' => 0,
        'courts_running' => 0,
        'courts_failed' => 0,
        'courts_pending' => 0,
        'courts_total' => 0,
    ];

    /**
     * Available courts for selection
     */
    public array $courts = [];

    /**
     * Selected year filter for sync status (0 = all years)
     */
    public int $filterYear;

    /**
     * Selected register filter
     */
    public string $filterRegister = 'Pp Prz';

    /**
     * Full sync: all years range
     */
    public const FULL_SYNC_START_YEAR = 2019;

    /**
     * Batch fetch: selected court ID
     */
    public ?int $batchCourtId = null;

    /**
     * Batch fetch: year to fetch
     */
    public int $batchYear;

    /**
     * Batch fetch: register to fetch
     */
    public string $batchRegister = 'Pp Prz';

    /**
     * Batch fetch: status indicator (null, 'dispatched', 'running', 'completed', 'failed')
     */
    public ?string $batchFetchStatus = null;

    /**
     * Batch fetch: last error message
     */
    public ?string $batchFetchError = null;

    /**
     * Whether to enable live polling for active syncs
     */
    public bool $pollingEnabled = false;

    /**
     * Tracks which action is currently in progress for targeted loading states
     */
    public ?string $activeAction = null;

    /**
     * Analyzed pismena insights for current case
     */
    public array $pismenaAnalysis = [];

    /**
     * Analytics dashboard data
     */
    public array $analytics = [];

    /**
     * Analytics year filter (0 = all years)
     */
    public int $analyticsYear;

    /**
     * Chart-ready data for D3.js visualizations
     */
    public array $chartData = [];

    /**
     * Per-case defense argument card
     */
    public array $caseArguments = [];

    /**
     * Comparison mode: court IDs and result data
     */
    public ?int $compareCourtA = null;
    public ?int $compareCourtB = null;
    public array $comparisonData = [];

    /**
     * Croatian labels for key dates in case timeline
     */
    public array $dateLabels = [
        'datumDodjele' => 'Dodjela',
        'datumDonosenjaOdluke' => 'Odluka',
        'datumOtpreme' => 'Otprema',
        'datumOvrsnosti' => 'Ovršnost',
        'datumZalbe' => 'Žalba',
        'datumArhiviranja' => 'Arhiviranje',
    ];

    /**
     * Livewire mount lifecycle hook
     */
    public function mount(): void
    {
        $this->filterYear = (int) date('Y');
        $this->batchYear = (int) date('Y');
        $this->analyticsYear = (int) date('Y');
    }

    /**
     * Called via wire:init after the component is mounted and rendered.
     *
     * Deferring the API fetch from mount() to wire:init prevents a
     * DOMDocument parsing failure in Livewire's
     * SupportMultipleRootElementDetection: the initial HTML (checked
     * during mount) no longer contains UTF-8 API response data that
     * can cause DOMDocument::loadHTML() to produce a DOM without a
     * <body> element.
     */
    public function initFetch(): void
    {
        if (! config('graphql_client.endpoint')) {
            return;
        }

        if (app()->environment('testing', 'dusk.local', 'local')) {
            return;
        }

        $this->fetch();
    }

    /**
     * Livewire updated lifecycle hook
     */
    public function updated($property): void
    {
        $this->error = null;
    }

    /**
     * Load sync status data from SyncLog model
     */
    public function loadSyncStatus(): void
    {
        $query = SyncLog::with('court')
            ->where('register', $this->filterRegister)
            ->when($this->filterYear > 0, fn ($q) => $q->where('year', $this->filterYear))
            ->orderByDesc('updated_at');

        $this->syncLogs = $query->get()->map(fn ($log) => [
            'id' => $log->id,
            'court_name' => $log->court?->short_name ?? 'Unknown',
            'court_id' => $log->court_id,
            'year' => $log->year,
            'register' => $log->register,
            'status' => $log->status,
            'total_fetched' => $log->total_fetched ?? 0,
            'total_saved' => $log->total_saved ?? 0,
            'total_errors' => $log->total_errors ?? 0,
            'last_case_number' => $log->last_case_number ?? 0,
            'duration_seconds' => $log->duration_seconds,
            'error_message' => $log->error_message,
            'started_at' => $log->started_at?->format('Y-m-d H:i'),
            'completed_at' => $log->completed_at?->format('Y-m-d H:i'),
        ])->toArray();

        $totalCourts = Court::where('level', 1)->count();

        $this->syncSummary = [
            'total_fetched' => collect($this->syncLogs)->sum('total_fetched'),
            'total_saved' => collect($this->syncLogs)->sum('total_saved'),
            'total_errors' => collect($this->syncLogs)->sum('total_errors'),
            'courts_completed' => collect($this->syncLogs)->where('status', 'completed')->count(),
            'courts_running' => collect($this->syncLogs)->where('status', 'running')->count(),
            'courts_failed' => collect($this->syncLogs)->where('status', 'failed')->count(),
            'courts_pending' => collect($this->syncLogs)->where('status', 'pending')->count(),
            'courts_total' => $totalCourts,
        ];

        // Auto-disable polling if no running syncs
        if ($this->syncSummary['courts_running'] === 0) {
            $this->pollingEnabled = false;
        }
    }

    /**
     * Load available courts (municipal level only)
     */
    public function loadCourts(): void
    {
        $this->courts = Court::where('level', 1)
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'external_id' => $c->external_id,
                'name' => $c->short_name,
                'full_name' => $c->name,
            ])
            ->toArray();
    }

    /**
     * Start a batch fetch job for selected court
     */
    public function startBatchFetch(): void
    {
        $this->activeAction = 'startBatchFetch';

        $this->validate([
            'batchCourtId' => 'required|exists:courts,id',
            'batchYear' => 'required|integer|min:2020|max:'.(date('Y') + 1),
            'batchRegister' => 'required|string',
        ]);

        $court = Court::find($this->batchCourtId);

        if (! $court) {
            $this->batchFetchError = 'Court not found';
            $this->activeAction = null;

            return;
        }

        FetchCourtCasesJob::dispatch(
            $court->external_id,
            $this->batchYear,
            $this->batchRegister
        );

        $this->batchFetchStatus = 'dispatched';
        $this->batchFetchError = null;
        $this->pollingEnabled = true;
        $this->activeAction = null;

        $this->loadSyncStatus();
    }

    /**
     * Switch to sync status tab and load data
     */
    public function switchToSyncStatus(): void
    {
        $this->activeTab = 'sync-status';
        $this->loadSyncStatus();
    }

    /**
     * Switch to batch fetch tab and load courts
     */
    public function switchToBatchFetch(): void
    {
        $this->activeTab = 'batch-fetch';
        $this->loadCourts();
    }

    /**
     * Switch to analytics tab and load data
     */
    public function switchToAnalytics(): void
    {
        $this->activeTab = 'analytics';
        $this->loadAnalytics();
    }

    /**
     * Fetch all pending courts that haven't been synced yet
     */
    public function fetchAllPending(): void
    {
        $this->activeAction = 'fetchAllPending';

        $pendingLogs = SyncLog::where('year', $this->batchYear)
            ->where('register', $this->batchRegister)
            ->where('status', 'pending')
            ->with('court')
            ->get();

        $courts = Court::where('level', 1)
            ->whereNotIn('id', $pendingLogs->pluck('court_id'))
            ->get();

        foreach ($courts as $court) {
            FetchCourtCasesJob::dispatch(
                $court->external_id,
                $this->batchYear,
                $this->batchRegister
            );
        }

        foreach ($pendingLogs as $log) {
            if ($log->court) {
                FetchCourtCasesJob::dispatch(
                    $log->court->external_id,
                    $this->batchYear,
                    $this->batchRegister
                );
            }
        }

        $this->batchFetchStatus = 'dispatched';
        $this->pollingEnabled = true;
        $this->activeAction = null;
    }

    /**
     * Retry all failed sync jobs
     */
    public function retryFailed(): void
    {
        $this->activeAction = 'retryFailed';

        $failedLogs = SyncLog::where('year', $this->batchYear)
            ->where('register', $this->batchRegister)
            ->where('status', 'failed')
            ->with('court')
            ->get();

        foreach ($failedLogs as $log) {
            if ($log->court) {
                $log->update(['status' => 'pending', 'error_message' => null]);

                FetchCourtCasesJob::dispatch(
                    $log->court->external_id,
                    $this->batchYear,
                    $this->batchRegister
                );
            }
        }

        $this->batchFetchStatus = 'dispatched';
        $this->pollingEnabled = true;
        $this->activeAction = null;
        $this->loadSyncStatus();
    }

    /**
     * Full sync: dispatch jobs for all courts, all years (2019-present), Pp Prz register
     */
    public function fullSyncAllCourts(): void
    {
        $this->activeAction = 'fullSyncAllCourts';

        $courts = Court::where('level', 1)->get();
        $currentYear = (int) date('Y');
        $dispatched = 0;

        foreach ($courts as $court) {
            for ($year = self::FULL_SYNC_START_YEAR; $year <= $currentYear; $year++) {
                // Skip if already completed
                $existing = SyncLog::where('court_id', $court->id)
                    ->where('year', $year)
                    ->where('register', 'Pp Prz')
                    ->where('status', 'completed')
                    ->exists();

                if (! $existing) {
                    FetchCourtCasesJob::dispatch(
                        $court->external_id,
                        $year,
                        'Pp Prz'
                    );
                    $dispatched++;
                }
            }
        }

        $this->batchFetchStatus = 'dispatched';
        $this->pollingEnabled = true;
        $this->activeAction = null;
        $this->fullSyncJobsDispatched = $dispatched;
    }

    /**
     * Number of jobs dispatched in last full sync
     */
    public int $fullSyncJobsDispatched = 0;

    /**
     * Handle filter year update - reload sync status if on that tab
     */
    public function updatedFilterYear(): void
    {
        if ($this->activeTab === 'sync-status') {
            $this->loadSyncStatus();
        }
    }

    /**
     * Handle filter register update - reload sync status if on that tab
     */
    public function updatedFilterRegister(): void
    {
        if ($this->activeTab === 'sync-status') {
            $this->loadSyncStatus();
        }
    }

    /**
     * Handle analytics year update
     */
    public function updatedAnalyticsYear(): void
    {
        if ($this->activeTab === 'analytics') {
            $this->loadAnalytics();
        }
    }

    /**
     * Fetch case data from GraphQL API
     */
    public function fetch(): void
    {
        $this->validate([
            'sud' => 'required',
            'oznakaBroj' => 'required|string|min:2',
        ]);

        $this->loading = true;
        $this->error = null;
        $this->tookMs = null;
        $this->pismenaAnalysis = [];

        $start = microtime(true);
        try {
            /** @var GraphQLAutoClient $client */
            $client = app(GraphQLAutoClient::class);
            $resp = $client->run('predmet', [
                'sud' => is_numeric($this->sud) ? (int) $this->sud : $this->sud,
                'oznakaBroj' => $this->oznakaBroj,
            ]);
            $this->data = is_array($resp) ? $resp : [];
            $this->normalizeData();
            $this->analyzePismena();
        } catch (GraphQLQueryException $e) {
            $this->error = $e->getMessage();
            $this->data = null;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            $this->data = null;
        } finally {
            $this->tookMs = round((microtime(true) - $start) * 1000, 1);
            $this->loading = false;
        }
    }

    /**
     * Analyze pismena for interesting patterns and insights
     */
    private function analyzePismena(): void
    {
        $pismena = $this->data['pismena'] ?? [];
        if (empty($pismena)) {
            $this->pismenaAnalysis = [];

            return;
        }

        $analysis = [
            'total_documents' => count($pismena),
            'by_type' => [],
            'by_kind' => [],
            'zahtjev_uvid_u_spis' => [],
            'zahtjevi' => [],
            'naredbe' => [],
            'izvjesca' => [],
            'highlights' => [],
            'submitters' => [],
            'timeline_span' => null,
        ];

        $dates = [];
        foreach ($pismena as $p) {
            $tip = $p['tip'] ?? 'N/A';
            $vrsta = $p['vrsta'] ?? 'N/A';
            $datum = $p['datum'] ?? null;
            $podnositelj = $p['podnositelj'] ?? null;

            // Count by type (tip)
            $analysis['by_type'][$tip] = ($analysis['by_type'][$tip] ?? 0) + 1;

            // Count by kind (vrsta)
            $analysis['by_kind'][$vrsta] = ($analysis['by_kind'][$vrsta] ?? 0) + 1;

            // Track dates
            if ($datum) {
                try {
                    $dates[] = Carbon::parse($datum);
                } catch (\Throwable $e) {
                    // skip
                }
            }

            // Track submitters
            if ($podnositelj) {
                $decoded = CourtCaseDocument::decodeSubmitter($podnositelj);
                $key = $decoded ?: $podnositelj;
                $analysis['submitters'][$key] = ($analysis['submitters'][$key] ?? 0) + 1;
            }

            // Identify "Zahtjev za uvid u spis"
            if (preg_match('/uvid\s+u\s+spis/i', $vrsta)) {
                $analysis['zahtjev_uvid_u_spis'][] = [
                    'datum' => $datum,
                    'vrsta' => $vrsta,
                    'podnositelj' => $podnositelj ? CourtCaseDocument::decodeSubmitter($podnositelj) : null,
                    'tip' => $tip,
                ];
            }

            // Identify all zahtjevi (requests)
            if (preg_match('/zahtjev/i', $vrsta)) {
                $analysis['zahtjevi'][] = [
                    'datum' => $datum,
                    'vrsta' => $vrsta,
                    'podnositelj' => $podnositelj ? CourtCaseDocument::decodeSubmitter($podnositelj) : null,
                    'tip' => $tip,
                ];
            }

            // Identify naredbe (orders/warrants)
            if (preg_match('/naredba|nalog/i', $vrsta)) {
                $analysis['naredbe'][] = [
                    'datum' => $datum,
                    'vrsta' => $vrsta,
                    'tip' => $tip,
                ];
            }

            // Identify izvješća (reports)
            if (preg_match('/izvješće|izvjesce|izvjestaj|zapisnik/i', $vrsta)) {
                $analysis['izvjesca'][] = [
                    'datum' => $datum,
                    'vrsta' => $vrsta,
                    'podnositelj' => $podnositelj ? CourtCaseDocument::decodeSubmitter($podnositelj) : null,
                    'tip' => $tip,
                ];
            }
        }

        // Timeline span
        if (count($dates) >= 2) {
            usort($dates, fn ($a, $b) => $a->getTimestamp() <=> $b->getTimestamp());
            $first = $dates[0];
            $last = end($dates);
            $analysis['timeline_span'] = [
                'from' => $first->format('Y-m-d'),
                'to' => $last->format('Y-m-d'),
                'days' => $first->diffInDays($last),
            ];
        }

        // Sort by_kind descending by count
        arsort($analysis['by_kind']);
        arsort($analysis['by_type']);
        arsort($analysis['submitters']);

        // Generate highlights
        if (! empty($analysis['zahtjev_uvid_u_spis'])) {
            $count = count($analysis['zahtjev_uvid_u_spis']);
            $analysis['highlights'][] = [
                'type' => 'info',
                'text' => "{$count} zahtjev(a) za uvid u spis",
                'detail' => 'Requests to inspect the case file',
            ];
        }

        $searchRequests = collect($analysis['zahtjevi'])->filter(
            fn ($z) => preg_match('/pretrag/i', $z['vrsta'])
        );
        if ($searchRequests->isNotEmpty()) {
            $analysis['highlights'][] = [
                'type' => 'warning',
                'text' => $searchRequests->count().' zahtjev(a) za pretragu',
                'detail' => 'Search warrant requests found',
            ];
        }

        if (! empty($analysis['naredbe'])) {
            $analysis['highlights'][] = [
                'type' => 'warning',
                'text' => count($analysis['naredbe']).' naredba/nalog',
                'detail' => 'Court orders issued',
            ];
        }

        // Check for SOKO submissions
        $sokoSubmissions = collect($analysis['submitters'])->filter(
            fn ($count, $name) => str_contains(strtoupper($name), 'SOKO')
        );
        if ($sokoSubmissions->isNotEmpty()) {
            $analysis['highlights'][] = [
                'type' => 'danger',
                'text' => 'SOKO submissions detected',
                'detail' => 'Organized crime unit submitted documents via misdemeanor procedure',
            ];
        }

        // Processing speed insight
        if ($analysis['timeline_span'] && $analysis['timeline_span']['days'] === 0) {
            $analysis['highlights'][] = [
                'type' => 'danger',
                'text' => 'Same-day processing',
                'detail' => 'All documents processed on the same day',
            ];
        }

        $this->pismenaAnalysis = $analysis;
    }

    /**
     * Build a year-scoped base query for CourtCase.
     * When $year=0, no year filter is applied (all years).
     */
    private function caseQuery(int $year): \Illuminate\Database\Eloquent\Builder
    {
        return CourtCase::query()->when($year > 0, fn ($q) => $q->where('year', $year));
    }

    /**
     * Build a year-scoped document query.
     */
    private function documentQuery(int $year): \Illuminate\Database\Eloquent\Builder
    {
        return CourtCaseDocument::whereHas('courtCase', function ($q) use ($year) {
            $q->where('is_search_warrant', true);
            if ($year > 0) {
                $q->where('year', $year);
            }
        });
    }

    /**
     * Load analytics data from database (mirrors ShowStatistics2/Dashboard)
     */
    public function loadAnalytics(): void
    {
        $this->activeAction = 'loadAnalytics';
        $year = $this->analyticsYear;
        $allYears = $year === 0;
        $yearLabel = $allYears ? 'All Years (2019-present)' : (string) $year;

        // Number of days for rate calculations
        $daySpan = $allYears ? max(1, ((int) date('Y') - self::FULL_SYNC_START_YEAR + 1) * 365) : 365;

        try {
            $analytics = [];
            $analytics['year_label'] = $yearLabel;
            $analytics['all_years'] = $allYears;

            // 1. Volume overview
            $totalCases = $this->caseQuery($year)->count();
            $totalWarrants = $this->caseQuery($year)->searchWarrants()->count();
            $sameDayCount = $this->caseQuery($year)->searchWarrants()
                ->where(function ($q) {
                    $q->where('processing_days', 0)->orWhere('is_same_day', true);
                })->count();
            $weekendCount = $this->caseQuery($year)->searchWarrants()->weekend()->count();
            $avgProcessing = $this->caseQuery($year)->searchWarrants()->avg('processing_days');

            // Per-capita: total Croatian population
            $totalPopulation = Court::where('level', 1)->sum('population') ?: 3870000;
            $per100k = $totalPopulation > 0 ? round(100000 * $totalWarrants / $totalPopulation, 1) : 0;

            $analytics['volume'] = [
                'total_cases' => $totalCases,
                'total_warrants' => $totalWarrants,
                'same_day' => $sameDayCount,
                'same_day_pct' => $totalWarrants > 0 ? round(100 * $sameDayCount / $totalWarrants, 1) : 0,
                'weekend' => $weekendCount,
                'weekend_pct' => $totalWarrants > 0 ? round(100 * $weekendCount / $totalWarrants, 1) : 0,
                'avg_processing_days' => round($avgProcessing ?? 0, 2),
                'daily_rate' => round($totalWarrants / $daySpan, 1),
                'weekly_rate' => round($totalWarrants / ($daySpan / 7), 1),
                'per_100k' => $per100k,
            ];

            // 2. Top courts by warrants (with per-capita)
            $analytics['top_courts'] = $this->caseQuery($year)
                ->where('is_search_warrant', true)
                ->select(
                    'court_id',
                    DB::raw('COUNT(*) as warrants'),
                    DB::raw('SUM(CASE WHEN processing_days = 0 OR is_same_day THEN 1 ELSE 0 END) as same_day'),
                    DB::raw('AVG(processing_days) as avg_days')
                )
                ->groupBy('court_id')
                ->orderByDesc('warrants')
                ->limit(10)
                ->get()
                ->map(function ($row) use ($totalWarrants) {
                    $court = Court::find($row->court_id);
                    $pop = $court?->population ?? 0;

                    return [
                        'court' => $court?->short_name ?? 'N/A',
                        'county' => $court?->county ?? 'N/A',
                        'warrants' => (int) $row->warrants,
                        'share_pct' => $totalWarrants > 0 ? round(100 * $row->warrants / $totalWarrants, 1) : 0,
                        'same_day_pct' => $row->warrants > 0 ? round(100 * $row->same_day / $row->warrants, 0) : 0,
                        'avg_days' => round($row->avg_days ?? 0, 1),
                        'population' => $pop,
                        'per_100k' => $pop > 0 ? round(100000 * $row->warrants / $pop, 1) : 0,
                    ];
                })->toArray();

            // 3. Judge concentration (top 10) with HHI per court
            $analytics['top_judges'] = $this->caseQuery($year)
                ->where('is_search_warrant', true)
                ->whereNotNull('judge_id')
                ->select(
                    'judge_id',
                    'judge_name',
                    'court_id',
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN processing_days = 0 OR is_same_day THEN 1 ELSE 0 END) as same_day'),
                    DB::raw('AVG(processing_days) as avg_days'),
                    DB::raw('SUM(CASE WHEN processing_days = 0 THEN 1 ELSE 0 END) as zero_days')
                )
                ->groupBy('judge_id', 'judge_name', 'court_id')
                ->orderByDesc('total')
                ->limit(10)
                ->get()
                ->map(function ($row) use ($totalWarrants) {
                    $court = Court::find($row->court_id);

                    return [
                        'judge' => $row->judge_name ?? 'N/A',
                        'court' => $court?->short_name ?? 'N/A',
                        'total' => (int) $row->total,
                        'concentration_pct' => $totalWarrants > 0 ? round(100 * $row->total / $totalWarrants, 1) : 0,
                        'same_day_pct' => $row->total > 0 ? round(100 * $row->same_day / $row->total, 0) : 0,
                        'avg_days' => round($row->avg_days ?? 0, 2),
                        'zero_day_pct' => $row->total > 0 ? round(100 * $row->zero_days / $row->total, 0) : 0,
                    ];
                })->toArray();

            // 3b. HHI per court (Herfindahl-Hirschman Index)
            $judgeData = $this->caseQuery($year)
                ->where('is_search_warrant', true)
                ->whereNotNull('judge_id')
                ->select('court_id', 'judge_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('court_id', 'judge_id')
                ->get()
                ->groupBy('court_id');

            $hhiResults = [];
            foreach ($judgeData as $courtId => $judges) {
                $courtTotal = (int) $judges->sum('cnt');
                if ($courtTotal < 5) {
                    continue;
                }
                $court = Court::find($courtId);
                $shares = $judges->map(fn ($j) => 100 * $j->cnt / $courtTotal);
                $hhi = round($shares->sum(fn ($s) => pow($s, 2)), 0);
                $maxShare = round($shares->max(), 0);
                $hhiResults[] = [
                    'court' => $court?->short_name ?? 'N/A',
                    'judges' => $judges->count(),
                    'warrants' => $courtTotal,
                    'max_share_pct' => $maxShare,
                    'hhi' => $hhi,
                    'flag' => $hhi > 5000 ? 'EXTREME' : ($hhi > 2500 ? 'HIGH' : ''),
                ];
            }
            usort($hhiResults, fn ($a, $b) => $b['hhi'] <=> $a['hhi']);
            $analytics['hhi_per_court'] = array_slice($hhiResults, 0, 10);

            // 4. Police unit breakdown
            $analytics['police_units'] = $this->documentQuery($year)
                ->where('is_request', true)
                ->select('police_unit_type', DB::raw('COUNT(DISTINCT court_case_id) as cases'))
                ->groupBy('police_unit_type')
                ->orderByDesc('cases')
                ->get()
                ->map(fn ($row) => [
                    'unit' => $row->police_unit_type ?? 'Unknown',
                    'cases' => (int) $row->cases,
                    'is_soko' => $row->police_unit_type === 'SOKO',
                ])->toArray();

            // 5. Pismena type breakdown
            $analytics['pismena_types'] = $this->documentQuery($year)
                ->select('document_kind', DB::raw('COUNT(*) as total'))
                ->groupBy('document_kind')
                ->orderByDesc('total')
                ->limit(15)
                ->get()
                ->map(fn ($row) => [
                    'kind' => $row->document_kind ?? 'N/A',
                    'total' => (int) $row->total,
                ])->toArray();

            // 6. "Zahtjev za uvid u spis" stats
            $analytics['uvid_u_spis'] = $this->documentQuery($year)
                ->where('document_kind', 'LIKE', '%uvid%spis%')
                ->select('submitter_decoded', DB::raw('COUNT(*) as cnt'))
                ->groupBy('submitter_decoded')
                ->orderByDesc('cnt')
                ->limit(10)
                ->get()
                ->map(fn ($row) => [
                    'submitter' => $row->submitter_decoded ?? 'N/A',
                    'count' => (int) $row->cnt,
                ])->toArray();

            $analytics['uvid_u_spis_total'] = $this->documentQuery($year)
                ->where('document_kind', 'LIKE', '%uvid%spis%')
                ->count();

            $analytics['uvid_u_spis_cases'] = $this->documentQuery($year)
                ->where('document_kind', 'LIKE', '%uvid%spis%')
                ->distinct('court_case_id')
                ->count('court_case_id');

            // 7. Yearly trend (always full range)
            $trendRange = range(self::FULL_SYNC_START_YEAR, (int) date('Y'));
            $analytics['yearly_trend'] = CourtCase::query()
                ->whereIn('year', $trendRange)
                ->select(
                    'year',
                    DB::raw('COUNT(*) as total_cases'),
                    DB::raw('SUM(CASE WHEN is_search_warrant THEN 1 ELSE 0 END) as warrants'),
                    DB::raw('SUM(CASE WHEN is_search_warrant AND (processing_days = 0 OR is_same_day) THEN 1 ELSE 0 END) as same_day'),
                    DB::raw('AVG(CASE WHEN is_search_warrant THEN processing_days END) as avg_days'),
                    DB::raw('SUM(CASE WHEN is_search_warrant AND is_weekend THEN 1 ELSE 0 END) as weekend')
                )
                ->groupBy('year')
                ->orderBy('year')
                ->get()
                ->map(fn ($row) => [
                    'year' => $row->year,
                    'total_cases' => (int) $row->total_cases,
                    'warrants' => (int) $row->warrants,
                    'same_day_pct' => $row->warrants > 0 ? round(100 * $row->same_day / $row->warrants, 0) : 0,
                    'avg_days' => round($row->avg_days ?? 0, 1),
                    'weekend' => (int) $row->weekend,
                    'weekend_pct' => $row->warrants > 0 ? round(100 * $row->weekend / $row->warrants, 0) : 0,
                ])->toArray();

            // 8. Rejection rate per court
            $analytics['rejection_rate'] = $this->caseQuery($year)
                ->where('register', 'Pp Prz')
                ->select(
                    'court_id',
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN decision_type IN ('Naredba', 'Nalog - pretrage') THEN 1 ELSE 0 END) as approved"),
                    DB::raw("SUM(CASE WHEN decision_type LIKE '%odbij%' OR decision_type LIKE '%odbac%' THEN 1 ELSE 0 END) as rejected")
                )
                ->groupBy('court_id')
                ->havingRaw('COUNT(*) >= ?', [10])
                ->get()
                ->map(function ($row) {
                    $court = Court::find($row->court_id);

                    return [
                        'court' => $court?->short_name ?? 'N/A',
                        'total' => (int) $row->total,
                        'approved' => (int) $row->approved,
                        'rejected' => (int) $row->rejected,
                        'approval_pct' => $row->total > 0 ? round(100 * $row->approved / $row->total, 1) : 0,
                        'rejection_pct' => $row->total > 0 ? round(100 * $row->rejected / $row->total, 1) : 0,
                        'is_zero_rejection' => (int) $row->rejected === 0,
                    ];
                })
                ->sortBy('rejection_pct')
                ->values()
                ->take(10)
                ->toArray();

            // 9. Regional disproportion (per-capita ranking)
            $analytics['regional_per_capita'] = $this->caseQuery($year)
                ->where('is_search_warrant', true)
                ->select('court_id', DB::raw('COUNT(*) as warrants'))
                ->groupBy('court_id')
                ->get()
                ->map(function ($row) {
                    $court = Court::find($row->court_id);
                    $pop = $court?->population ?? 0;

                    return [
                        'court' => $court?->short_name ?? 'N/A',
                        'county' => $court?->county ?? 'N/A',
                        'warrants' => (int) $row->warrants,
                        'population' => $pop,
                        'per_100k' => $pop > 0 ? round(100000 * $row->warrants / $pop, 1) : 0,
                    ];
                })
                ->filter(fn ($r) => $r['population'] > 0)
                ->sortByDesc('per_100k')
                ->values()
                ->take(10)
                ->toArray();

            // 10. Processing time distribution
            $analytics['processing_distribution'] = $this->caseQuery($year)
                ->where('is_search_warrant', true)
                ->whereNotNull('processing_days')
                ->select(
                    DB::raw("CASE
                        WHEN processing_days = 0 THEN '0 (same day)'
                        WHEN processing_days = 1 THEN '1 day'
                        WHEN processing_days BETWEEN 2 AND 3 THEN '2-3 days'
                        WHEN processing_days BETWEEN 4 AND 7 THEN '4-7 days'
                        WHEN processing_days BETWEEN 8 AND 14 THEN '8-14 days'
                        WHEN processing_days BETWEEN 15 AND 30 THEN '15-30 days'
                        ELSE '30+ days'
                    END as bucket"),
                    DB::raw('COUNT(*) as cnt')
                )
                ->groupBy('bucket')
                ->orderByRaw("MIN(processing_days)")
                ->get()
                ->map(fn ($r) => [
                    'bucket' => $r->bucket,
                    'count' => (int) $r->cnt,
                ])->toArray();

            // 11. Judge Rubber-Stamp Profiles
            $judgeProfiles = $this->caseQuery($year)
                ->where('is_search_warrant', true)
                ->whereNotNull('judge_id')
                ->select(
                    'judge_id',
                    'judge_name',
                    'court_id',
                    DB::raw('COUNT(*) as total_warrants'),
                    DB::raw('SUM(CASE WHEN processing_days = 0 OR is_same_day THEN 1 ELSE 0 END) as same_day'),
                    DB::raw('SUM(CASE WHEN processing_days = 0 THEN 1 ELSE 0 END) as zero_day'),
                    DB::raw("SUM(CASE WHEN decision_type LIKE '%odbij%' OR decision_type LIKE '%odbac%' THEN 1 ELSE 0 END) as rejections"),
                    DB::raw('AVG(processing_days) as avg_days'),
                    DB::raw('MIN(CASE WHEN is_search_warrant THEN date_decision END) as first_warrant'),
                    DB::raw('MAX(CASE WHEN is_search_warrant THEN date_decision END) as last_warrant')
                )
                ->groupBy('judge_id', 'judge_name', 'court_id')
                ->havingRaw('COUNT(*) >= ?', [10])
                ->orderByDesc('total_warrants')
                ->limit(15)
                ->get()
                ->map(function ($row) {
                    $court = Court::find($row->court_id);
                    $sameDayPct = $row->total_warrants > 0 ? round(100 * $row->same_day / $row->total_warrants, 0) : 0;
                    $zeroDayPct = $row->total_warrants > 0 ? round(100 * $row->zero_day / $row->total_warrants, 0) : 0;
                    $isRubberStamp = $row->total_warrants >= 50 && $sameDayPct >= 90 && (int) $row->rejections === 0;

                    return [
                        'judge' => $row->judge_name ?? 'N/A',
                        'court' => $court?->short_name ?? 'N/A',
                        'total_warrants' => (int) $row->total_warrants,
                        'same_day_pct' => $sameDayPct,
                        'zero_day_pct' => $zeroDayPct,
                        'rejections' => (int) $row->rejections,
                        'avg_days' => round($row->avg_days ?? 0, 2),
                        'first_warrant' => $row->first_warrant,
                        'last_warrant' => $row->last_warrant,
                        'is_rubber_stamp' => $isRubberStamp,
                    ];
                })->toArray();

            $analytics['judge_profiles'] = $judgeProfiles;

            // 12. Judge Workload Impossibility
            $analytics['workload_impossibility'] = $this->caseQuery($year)
                ->where('is_search_warrant', true)
                ->whereNotNull('judge_id')
                ->whereNotNull('date_decision')
                ->select(
                    'judge_id',
                    'judge_name',
                    'court_id',
                    DB::raw('DATE(date_decision) as decision_date'),
                    DB::raw('COUNT(*) as warrants_on_day')
                )
                ->groupBy('judge_id', 'judge_name', 'court_id', DB::raw('DATE(date_decision)'))
                ->havingRaw('COUNT(*) >= ?', [5])
                ->orderByDesc('warrants_on_day')
                ->limit(20)
                ->get()
                ->map(function ($row) {
                    $court = Court::find($row->court_id);

                    return [
                        'judge' => $row->judge_name ?? 'N/A',
                        'court' => $court?->short_name ?? 'N/A',
                        'date' => $row->decision_date,
                        'warrants_on_day' => (int) $row->warrants_on_day,
                        'is_impossible' => $row->warrants_on_day >= 10,
                    ];
                })->toArray();

            // 13. Repeat Targeting
            $analytics['repeat_targets'] = CaseParty::whereHas('courtCase', function ($q) use ($year) {
                    $q->where('is_search_warrant', true);
                    if ($year > 0) {
                        $q->where('year', $year);
                    }
                })
                ->where('is_defendant', true)
                ->whereNotNull('name')
                ->where('name', '!=', '')
                ->select('name', DB::raw('COUNT(DISTINCT court_case_id) as case_count'))
                ->groupBy('name')
                ->havingRaw('COUNT(DISTINCT court_case_id) >= ?', [2])
                ->orderByDesc('case_count')
                ->limit(15)
                ->get()
                ->map(fn ($row) => [
                    'name' => $row->name,
                    'case_count' => (int) $row->case_count,
                    'is_excessive' => $row->case_count >= 3,
                ])->toArray();

            // 14. Police Unit Approval Rate
            $analytics['police_approval_rate'] = DB::table('court_case_documents as d')
                ->join('court_cases as c', 'c.id', '=', 'd.court_case_id')
                ->where('c.is_search_warrant', true)
                ->where('d.is_request', true)
                ->whereNotNull('d.police_unit_type')
                ->when($year > 0, fn ($q) => $q->where('c.year', $year))
                ->select(
                    'd.police_unit_type',
                    DB::raw('COUNT(DISTINCT c.id) as total_requests'),
                    DB::raw("SUM(CASE WHEN c.decision_type IN ('Naredba', 'Nalog - pretrage', 'Nalog-pretrage') THEN 1 ELSE 0 END) as approved"),
                    DB::raw("SUM(CASE WHEN c.decision_type LIKE '%odbij%' OR c.decision_type LIKE '%odbac%' THEN 1 ELSE 0 END) as rejected"),
                    DB::raw('AVG(c.processing_days) as avg_processing'),
                    DB::raw('SUM(CASE WHEN c.processing_days = 0 OR c.is_same_day THEN 1 ELSE 0 END) as same_day')
                )
                ->groupBy('d.police_unit_type')
                ->havingRaw('COUNT(DISTINCT c.id) >= ?', [5])
                ->orderByDesc('total_requests')
                ->get()
                ->map(fn ($row) => [
                    'unit' => $row->police_unit_type,
                    'total_requests' => (int) $row->total_requests,
                    'approved' => (int) $row->approved,
                    'rejected' => (int) $row->rejected,
                    'approval_pct' => $row->total_requests > 0 ? round(100 * $row->approved / $row->total_requests, 1) : 0,
                    'same_day_pct' => $row->total_requests > 0 ? round(100 * $row->same_day / $row->total_requests, 0) : 0,
                    'avg_processing' => round($row->avg_processing ?? 0, 1),
                    'is_100pct' => (int) $row->rejected === 0,
                    'is_soko' => $row->police_unit_type === 'SOKO',
                ])->toArray();

            // 15. Proportionality Index
            $totalPpPrz = $this->caseQuery($year)->where('register', 'Pp Prz')->count();
            $warrantsInPpPrz = $this->caseQuery($year)->where('register', 'Pp Prz')
                ->where('is_search_warrant', true)->count();
            $urgentMisdemeanor = $this->caseQuery($year)->where('register', 'Pp Prz')
                ->where('is_search_warrant', true)
                ->where(function ($q) {
                    $q->where('is_weekend', true)->orWhere('is_same_day', true);
                })->count();

            $analytics['proportionality'] = [
                'total_misdemeanor' => $totalPpPrz,
                'warrants_in_misdemeanor' => $warrantsInPpPrz,
                'warrants_pct' => $totalPpPrz > 0 ? round(100 * $warrantsInPpPrz / $totalPpPrz, 1) : 0,
                'urgent_count' => $urgentMisdemeanor,
                'urgent_pct' => $warrantsInPpPrz > 0 ? round(100 * $urgentMisdemeanor / $warrantsInPpPrz, 1) : 0,
            ];

            // 16. Document Completeness
            $warrantCaseIds = $this->caseQuery($year)
                ->where('is_search_warrant', true)
                ->pluck('id');

            $totalWarrantCases = $warrantCaseIds->count();

            $casesWithRequest = CourtCaseDocument::whereIn('court_case_id', $warrantCaseIds)
                ->where('is_request', true)
                ->distinct('court_case_id')
                ->count('court_case_id');

            $casesWithDecision = CourtCaseDocument::whereIn('court_case_id', $warrantCaseIds)
                ->where('is_decision', true)
                ->distinct('court_case_id')
                ->count('court_case_id');

            $casesWithNoDocs = $totalWarrantCases - CourtCaseDocument::whereIn('court_case_id', $warrantCaseIds)
                ->distinct('court_case_id')
                ->count('court_case_id');

            $decisionNoRequest = CourtCaseDocument::whereIn('court_case_id', $warrantCaseIds)
                ->where('is_decision', true)
                ->whereNotIn('court_case_id', function ($q) use ($warrantCaseIds) {
                    $q->select('court_case_id')
                        ->from('court_case_documents')
                        ->whereIn('court_case_id', $warrantCaseIds)
                        ->where('is_request', true);
                })
                ->distinct('court_case_id')
                ->count('court_case_id');

            $analytics['document_completeness'] = [
                'total_warrant_cases' => $totalWarrantCases,
                'with_request' => $casesWithRequest,
                'with_decision' => $casesWithDecision,
                'no_documents' => $casesWithNoDocs,
                'decision_no_request' => $decisionNoRequest,
                'request_pct' => $totalWarrantCases > 0 ? round(100 * $casesWithRequest / $totalWarrantCases, 1) : 0,
                'decision_pct' => $totalWarrantCases > 0 ? round(100 * $casesWithDecision / $totalWarrantCases, 1) : 0,
                'completeness_pct' => $totalWarrantCases > 0
                    ? round(100 * min($casesWithRequest, $casesWithDecision) / $totalWarrantCases, 1) : 0,
            ];

            // 17. Monthly Trend with Anomaly Detection
            $monthlyRaw = $this->caseQuery($year)
                ->where('is_search_warrant', true)
                ->whereNotNull('date_decision')
                ->select(
                    DB::raw("TO_CHAR(date_decision, 'YYYY-MM') as month"),
                    DB::raw('COUNT(*) as warrants'),
                    DB::raw('SUM(CASE WHEN is_same_day THEN 1 ELSE 0 END) as same_day'),
                    DB::raw('SUM(CASE WHEN is_weekend THEN 1 ELSE 0 END) as weekend')
                )
                ->groupBy(DB::raw("TO_CHAR(date_decision, 'YYYY-MM')"))
                ->orderBy('month')
                ->get()
                ->toArray();

            $monthly = [];
            foreach ($monthlyRaw as $i => $m) {
                $prev = $i > 0 ? $monthlyRaw[$i - 1]['warrants'] : null;
                $momChange = $prev ? round(100 * ($m['warrants'] - $prev) / max($prev, 1), 0) : null;

                $rollingSlice = array_slice($monthlyRaw, max(0, $i - 3), min($i, 3));
                $rollingAvg = count($rollingSlice) > 0
                    ? array_sum(array_column($rollingSlice, 'warrants')) / count($rollingSlice)
                    : $m['warrants'];
                $vsRolling = $rollingAvg > 0 ? round($m['warrants'] / $rollingAvg, 2) : 1;

                $isSpike = ($momChange !== null && $momChange > 50) || $vsRolling > 2;
                $isDrop = ($momChange !== null && $momChange < -40);

                $monthly[] = [
                    'month' => $m['month'],
                    'warrants' => (int) $m['warrants'],
                    'same_day' => (int) $m['same_day'],
                    'weekend' => (int) $m['weekend'],
                    'same_day_pct' => $m['warrants'] > 0 ? round(100 * $m['same_day'] / $m['warrants'], 0) : 0,
                    'mom_change' => $momChange,
                    'vs_rolling_avg' => $vsRolling,
                    'is_spike' => $isSpike,
                    'is_drop' => $isDrop,
                ];
            }
            $analytics['monthly_trend'] = $monthly;

            // 17b. Per-Court Monthly Spike Detection
            $topCourtIds = $this->caseQuery($year)
                ->where('is_search_warrant', true)
                ->select('court_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('court_id')
                ->orderByDesc('cnt')
                ->limit(5)
                ->pluck('court_id');

            $courtMonthly = [];
            foreach ($topCourtIds as $courtId) {
                $court = Court::find($courtId);
                $rows = $this->caseQuery($year)
                    ->where('is_search_warrant', true)
                    ->where('court_id', $courtId)
                    ->whereNotNull('date_decision')
                    ->select(
                        DB::raw("TO_CHAR(date_decision, 'YYYY-MM') as month"),
                        DB::raw('COUNT(*) as warrants')
                    )
                    ->groupBy(DB::raw("TO_CHAR(date_decision, 'YYYY-MM')"))
                    ->orderBy('month')
                    ->get()
                    ->toArray();

                $spikes = [];
                foreach ($rows as $ri => $r) {
                    $rPrev = $ri > 0 ? $rows[$ri - 1]['warrants'] : null;
                    $rChange = $rPrev ? round(100 * ($r['warrants'] - $rPrev) / max($rPrev, 1), 0) : null;
                    if ($rChange !== null && $rChange > 100) {
                        $spikes[] = ['month' => $r['month'], 'warrants' => (int) $r['warrants'], 'change' => $rChange];
                    }
                }

                if (count($spikes) > 0) {
                    $courtMonthly[] = [
                        'court' => $court?->short_name ?? 'N/A',
                        'spikes' => $spikes,
                    ];
                }
            }
            $analytics['court_monthly_spikes'] = $courtMonthly;

            // 18. Argument Strength Scoring
            $scores = [];
            $vol = $analytics['volume'] ?? [];

            // Same-day score
            $sdPct = $vol['same_day_pct'] ?? 0;
            $scores['same_day'] = [
                'label' => 'Same-Day Rubber Stamping',
                'article' => 'Art. 8 ECHR',
                'value' => $sdPct . '%',
                'score' => $sdPct > 90 ? 100 : ($sdPct > 80 ? 75 : ($sdPct > 70 ? 50 : 25)),
                'detail' => $sdPct . '% of warrants approved same day (expected: 50-70%)',
            ];

            // Rejection rate score
            $zeroRejCourts = collect($analytics['rejection_rate'] ?? [])->where('is_zero_rejection', true)->count();
            $totalRejCourts = count($analytics['rejection_rate'] ?? []);
            $rejScore = $totalRejCourts > 0 ? round(100 * $zeroRejCourts / $totalRejCourts) : 0;
            $scores['rejection_rate'] = [
                'label' => 'Zero Rejection Courts',
                'article' => 'Art. 6 & 8 ECHR',
                'value' => $zeroRejCourts . '/' . $totalRejCourts,
                'score' => $rejScore > 50 ? 100 : ($rejScore > 30 ? 75 : ($rejScore > 10 ? 50 : 25)),
                'detail' => $zeroRejCourts . ' of ' . $totalRejCourts . ' courts have 0% rejection rate',
            ];

            // HHI score
            $extremeHHI = collect($analytics['hhi_per_court'] ?? [])->where('flag', 'EXTREME')->count();
            $scores['judge_concentration'] = [
                'label' => 'Judge Concentration (HHI)',
                'article' => 'Art. 6 ECHR',
                'value' => $extremeHHI . ' extreme',
                'score' => $extremeHHI > 3 ? 100 : ($extremeHHI > 1 ? 75 : ($extremeHHI > 0 ? 50 : 25)),
                'detail' => $extremeHHI . ' courts with HHI > 5000 (extreme judge concentration)',
            ];

            // Processing time score
            $avgDays = $vol['avg_processing_days'] ?? 5;
            $scores['processing_time'] = [
                'label' => 'Processing Speed',
                'article' => 'Art. 8 ECHR',
                'value' => $avgDays . ' days',
                'score' => $avgDays < 0.5 ? 100 : ($avgDays < 1 ? 75 : ($avgDays < 2 ? 50 : 25)),
                'detail' => 'Avg ' . $avgDays . ' days processing (near-zero = no individual review)',
            ];

            // Rubber stamp judges score
            $rubberStampCount = collect($analytics['judge_profiles'] ?? [])->where('is_rubber_stamp', true)->count();
            $scores['rubber_stamp_judges'] = [
                'label' => 'Rubber-Stamp Judges',
                'article' => 'Art. 8 ECHR',
                'value' => $rubberStampCount . ' flagged',
                'score' => $rubberStampCount > 3 ? 100 : ($rubberStampCount > 1 ? 75 : ($rubberStampCount > 0 ? 50 : 10)),
                'detail' => $rubberStampCount . ' judges: 50+ warrants, 90%+ same-day, 0 rejections',
            ];

            // Workload impossibility score
            $impossibleDays = collect($analytics['workload_impossibility'] ?? [])->where('is_impossible', true)->count();
            $scores['workload_impossibility'] = [
                'label' => 'Workload Impossibility',
                'article' => 'Art. 8 ECHR',
                'value' => $impossibleDays . ' days',
                'score' => $impossibleDays > 5 ? 100 : ($impossibleDays > 2 ? 75 : ($impossibleDays > 0 ? 60 : 10)),
                'detail' => $impossibleDays . ' days where judge issued 10+ warrants (physically impossible)',
            ];

            // Proportionality score
            $propPct = $analytics['proportionality']['warrants_pct'] ?? 0;
            $scores['proportionality'] = [
                'label' => 'Proportionality Violation',
                'article' => 'Art. 8 ECHR',
                'value' => $propPct . '%',
                'score' => $propPct > 50 ? 100 : ($propPct > 20 ? 75 : ($propPct > 5 ? 50 : 25)),
                'detail' => $propPct . '% of misdemeanor cases involve home search (max invasiveness for min sanction)',
            ];

            // Document completeness score
            $decNoReq = $analytics['document_completeness']['decision_no_request'] ?? 0;
            $scores['document_gaps'] = [
                'label' => 'Document Completeness Gaps',
                'article' => 'Procedural',
                'value' => $decNoReq . ' violations',
                'score' => $decNoReq > 20 ? 100 : ($decNoReq > 10 ? 75 : ($decNoReq > 0 ? 50 : 10)),
                'detail' => $decNoReq . ' warrants issued without formal police request on file',
            ];

            // Monthly spikes score
            $spikeCount = collect($monthly)->where('is_spike', true)->count();
            $scores['temporal_anomalies'] = [
                'label' => 'Temporal Anomalies',
                'article' => 'Art. 8 ECHR',
                'value' => $spikeCount . ' spikes',
                'score' => $spikeCount > 5 ? 100 : ($spikeCount > 2 ? 75 : ($spikeCount > 0 ? 50 : 10)),
                'detail' => $spikeCount . ' months with >50% increase or >2x rolling average',
            ];

            // Sort by score descending
            uasort($scores, fn ($a, $b) => $b['score'] <=> $a['score']);

            // Composite score (weighted average of top 5)
            $topScores = array_slice(array_column($scores, 'score'), 0, 5);
            $compositeScore = count($topScores) > 0 ? round(array_sum($topScores) / count($topScores)) : 0;

            $analytics['argument_scores'] = $scores;
            $analytics['composite_score'] = $compositeScore;

            // Build chart data for D3.js
            $this->chartData = $this->buildChartData($analytics);

            // Dispatch browser event so @script can render charts
            $this->dispatch('epredmet-charts-updated');

            $this->analytics = $analytics;
        } catch (\Throwable $e) {
            $this->analytics = ['error' => $e->getMessage()];
            $this->chartData = [];
        } finally {
            $this->activeAction = null;
        }
    }

    /**
     * Build D3.js-compatible chart data from analytics.
     */
    private function buildChartData(array $analytics): array
    {
        $charts = [];

        // Chart 1: Yearly trend – home search warrants per year + cumulative
        $trend = $analytics['yearly_trend'] ?? [];
        $warrants = array_column($trend, 'warrants');
        $cumulative = [];
        $runningTotal = 0;
        foreach ($warrants as $w) {
            $runningTotal += $w;
            $cumulative[] = $runningTotal;
        }
        $charts['yearly_trend'] = [
            'labels' => array_column($trend, 'year'),
            'warrants' => $warrants,
            'cumulative' => $cumulative,
            'same_day_pct' => array_column($trend, 'same_day_pct'),
            'weekend_pct' => array_column($trend, 'weekend_pct'),
        ];

        // Chart 2: Court bar chart (top 10 by warrants)
        $courts = $analytics['top_courts'] ?? [];
        $charts['court_bar'] = [
            'labels' => array_column($courts, 'court'),
            'warrants' => array_column($courts, 'warrants'),
            'same_day_pct' => array_column($courts, 'same_day_pct'),
        ];

        // Chart 3: Same-day donut
        $vol = $analytics['volume'] ?? [];
        $sameDay = $vol['same_day'] ?? 0;
        $notSameDay = ($vol['total_warrants'] ?? 0) - $sameDay;
        $charts['same_day_donut'] = [
            'same_day' => $sameDay,
            'not_same_day' => max(0, $notSameDay),
        ];

        // Chart 4: Processing time distribution
        $dist = $analytics['processing_distribution'] ?? [];
        $charts['processing_dist'] = [
            'labels' => array_column($dist, 'bucket'),
            'counts' => array_column($dist, 'count'),
        ];

        // Chart 5: Regional per-capita
        $regional = $analytics['regional_per_capita'] ?? [];
        $charts['regional_bar'] = [
            'labels' => array_column($regional, 'court'),
            'per_100k' => array_column($regional, 'per_100k'),
        ];

        // Chart 6: HHI concentration
        $hhi = $analytics['hhi_per_court'] ?? [];
        $charts['hhi_bar'] = [
            'labels' => array_column($hhi, 'court'),
            'hhi' => array_column($hhi, 'hhi'),
        ];

        // Chart 7: Judge rubber-stamp profile
        $profiles = $analytics['judge_profiles'] ?? [];
        $charts['judge_profile_bar'] = [
            'labels' => array_column($profiles, 'judge'),
            'warrants' => array_column($profiles, 'total_warrants'),
            'same_day_pct' => array_column($profiles, 'same_day_pct'),
            'is_rubber_stamp' => array_column($profiles, 'is_rubber_stamp'),
        ];

        // Chart 8: Police unit approval rate
        $policeData = $analytics['police_approval_rate'] ?? [];
        $charts['police_approval_bar'] = [
            'labels' => array_column($policeData, 'unit'),
            'approval_pct' => array_column($policeData, 'approval_pct'),
            'same_day_pct' => array_column($policeData, 'same_day_pct'),
        ];

        // Chart 9: Monthly trend with anomalies
        $monthlyData = $analytics['monthly_trend'] ?? [];
        $charts['monthly_trend'] = [
            'labels' => array_column($monthlyData, 'month'),
            'warrants' => array_column($monthlyData, 'warrants'),
            'same_day_pct' => array_column($monthlyData, 'same_day_pct'),
            'is_spike' => array_column($monthlyData, 'is_spike'),
            'is_drop' => array_column($monthlyData, 'is_drop'),
        ];

        // Chart 10: Argument strength scores
        $argScores = $analytics['argument_scores'] ?? [];
        $charts['argument_scores'] = [
            'labels' => array_column($argScores, 'label'),
            'scores' => array_column($argScores, 'score'),
        ];

        return $charts;
    }

    /**
     * Generate defense argument card for a specific case.
     */
    public function generateCaseArguments(int $caseId): void
    {
        $case = CourtCase::with(['court', 'documents', 'parties'])->find($caseId);
        if (! $case) {
            return;
        }

        $arguments = [];
        $court = $case->court;
        $judge = $case->judge_name;

        // National averages for comparison
        $nationalTotal = CourtCase::where('is_search_warrant', true)->count() ?: 1;
        $nationalSameDay = CourtCase::where('is_search_warrant', true)->where('is_same_day', true)->count();
        $nationalSameDayPct = round(100 * $nationalSameDay / $nationalTotal, 0);
        $nationalAvgDays = round(CourtCase::where('is_search_warrant', true)->avg('processing_days') ?? 0, 1);

        // 1. Same-day approval
        if ($case->is_same_day || $case->processing_days === 0) {
            $arguments[] = [
                'type' => 'same_day',
                'title' => 'Same-Day Approval',
                'article' => 'Art. 8 ECHR',
                'text' => 'Warrant approved on same day as request — ' . ($case->processing_days ?? 0) . ' day(s) processing. National avg: ' . $nationalAvgDays . ' days.',
                'strength' => $case->processing_days === 0 ? 90 : 70,
            ];
        }

        // 2. Judge profile
        if ($case->judge_id) {
            $judgeStats = CourtCase::where('judge_id', $case->judge_id)
                ->where('is_search_warrant', true)
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_same_day THEN 1 ELSE 0 END) as same_day')
                ->selectRaw("SUM(CASE WHEN decision_type LIKE '%odbij%' OR decision_type LIKE '%odbac%' THEN 1 ELSE 0 END) as rejections")
                ->first();

            if ($judgeStats && $judgeStats->total >= 10) {
                $jSdPct = round(100 * $judgeStats->same_day / $judgeStats->total, 0);
                $isRubber = $judgeStats->total >= 50 && $jSdPct >= 90 && (int) $judgeStats->rejections === 0;
                $arguments[] = [
                    'type' => 'judge_profile',
                    'title' => 'Judge ' . ($judge ?? 'N/A') . ' Profile',
                    'article' => 'Art. 8 ECHR',
                    'text' => $judgeStats->total . ' total warrants, ' . $jSdPct . '% same-day, ' . $judgeStats->rejections . ' rejections.' . ($isRubber ? ' RUBBER STAMP pattern.' : ''),
                    'strength' => $isRubber ? 95 : ($jSdPct > 90 ? 75 : 50),
                ];
            }
        }

        // 3. Court rejection rate
        if ($court) {
            $courtTotal = CourtCase::where('court_id', $court->id)->where('is_search_warrant', true)->count();
            $courtRejected = CourtCase::where('court_id', $court->id)->where('is_search_warrant', true)
                ->where(fn ($q) => $q->where('decision_type', 'LIKE', '%odbij%')->orWhere('decision_type', 'LIKE', '%odbac%'))
                ->count();
            $rejPct = $courtTotal > 0 ? round(100 * $courtRejected / $courtTotal, 1) : 0;
            if ($rejPct < 5) {
                $arguments[] = [
                    'type' => 'rejection_rate',
                    'title' => 'Court Rejection Rate: ' . $rejPct . '%',
                    'article' => 'Art. 6 & 8 ECHR',
                    'text' => $court->short_name . ': ' . $courtRejected . '/' . $courtTotal . ' rejected. Expected 5-10% for meaningful oversight.',
                    'strength' => $rejPct === 0.0 ? 90 : 60,
                ];
            }
        }

        // 4. Weekend warrant
        if ($case->is_weekend) {
            $arguments[] = [
                'type' => 'weekend',
                'title' => 'Weekend/Holiday Warrant',
                'article' => 'Art. 8 ECHR',
                'text' => 'Warrant issued outside business hours — heightened scrutiny required.',
                'strength' => 60,
            ];
        }

        // 5. SOKO check
        $sokoDoc = $case->documents->first(fn ($d) => $d->police_unit_type === 'SOKO');
        if ($sokoDoc) {
            $arguments[] = [
                'type' => 'soko',
                'title' => 'SOKO via Misdemeanor Path',
                'article' => 'Art. 6 ECHR',
                'text' => 'Organized crime unit (SOKO) used misdemeanor procedure instead of criminal — procedural circumvention.',
                'strength' => 85,
            ];
        }

        // 6. Proportionality (misdemeanor register)
        if ($case->register === 'Pp Prz') {
            $arguments[] = [
                'type' => 'proportionality',
                'title' => 'Proportionality Violation',
                'article' => 'Art. 8 ECHR',
                'text' => 'Home search (maximum invasiveness) via misdemeanor register (maximum sanction: fine).',
                'strength' => 80,
            ];
        }

        // 7. Document completeness
        $hasRequest = $case->documents->where('is_request', true)->isNotEmpty();
        $hasDecision = $case->documents->where('is_decision', true)->isNotEmpty();
        if ($hasDecision && ! $hasRequest) {
            $arguments[] = [
                'type' => 'missing_request',
                'title' => 'Missing Police Request',
                'article' => 'Procedural',
                'text' => 'Court order exists but no formal police request document on file.',
                'strength' => 85,
            ];
        }

        // 8. Repeat targeting
        $defendant = $case->parties->where('is_defendant', true)->first();
        if ($defendant && $defendant->name) {
            $repeatCount = CaseParty::where('name', $defendant->name)
                ->where('is_defendant', true)
                ->whereHas('courtCase', fn ($q) => $q->where('is_search_warrant', true))
                ->distinct('court_case_id')
                ->count('court_case_id');
            if ($repeatCount >= 2) {
                $arguments[] = [
                    'type' => 'repeat_target',
                    'title' => 'Repeat Targeting',
                    'article' => 'Art. 8 ECHR',
                    'text' => $defendant->name . ' appears in ' . $repeatCount . ' search warrant cases — disproportionate targeting.',
                    'strength' => $repeatCount >= 3 ? 85 : 65,
                ];
            }
        }

        // Sort by strength descending
        usort($arguments, fn ($a, $b) => $b['strength'] <=> $a['strength']);

        $this->caseArguments = [
            'case_number' => $case->case_number,
            'court' => $court?->short_name ?? 'N/A',
            'judge' => $judge ?? 'N/A',
            'decision_date' => $case->date_decision,
            'arguments' => $arguments,
            'overall_strength' => count($arguments) > 0 ? round(collect($arguments)->avg('strength')) : 0,
        ];
    }

    /**
     * Load court comparison data.
     */
    public function loadComparison(): void
    {
        if (! $this->compareCourtA || ! $this->compareCourtB) {
            return;
        }

        $year = $this->analyticsYear;
        $courtIds = [$this->compareCourtA, $this->compareCourtB];
        $sides = [];

        // National baseline
        $natTotal = $this->caseQuery($year)->where('is_search_warrant', true)->count() ?: 1;
        $natSameDayPct = round(100 * $this->caseQuery($year)->where('is_search_warrant', true)->where('is_same_day', true)->count() / $natTotal, 0);
        $natAvgDays = round($this->caseQuery($year)->where('is_search_warrant', true)->avg('processing_days') ?? 0, 1);
        $natWeekendPct = round(100 * $this->caseQuery($year)->where('is_search_warrant', true)->where('is_weekend', true)->count() / $natTotal, 0);

        foreach ($courtIds as $courtId) {
            $court = Court::find($courtId);
            $total = $this->caseQuery($year)->where('court_id', $courtId)->where('is_search_warrant', true)->count() ?: 1;
            $sameDay = $this->caseQuery($year)->where('court_id', $courtId)->where('is_search_warrant', true)->where('is_same_day', true)->count();
            $weekend = $this->caseQuery($year)->where('court_id', $courtId)->where('is_search_warrant', true)->where('is_weekend', true)->count();
            $rejected = $this->caseQuery($year)->where('court_id', $courtId)->where('is_search_warrant', true)
                ->where(fn ($q) => $q->where('decision_type', 'LIKE', '%odbij%')->orWhere('decision_type', 'LIKE', '%odbac%'))->count();
            $avgDays = round($this->caseQuery($year)->where('court_id', $courtId)->where('is_search_warrant', true)->avg('processing_days') ?? 0, 1);

            // HHI for this court
            $judgeCounts = $this->caseQuery($year)->where('court_id', $courtId)->where('is_search_warrant', true)
                ->whereNotNull('judge_id')
                ->select('judge_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('judge_id')->pluck('cnt');
            $courtCaseTotal = $judgeCounts->sum() ?: 1;
            $hhi = $judgeCounts->sum(fn ($c) => pow(round(100 * $c / $courtCaseTotal), 2));

            $perCapita = ($court && $court->population > 0) ? round($total / ($court->population / 100000), 1) : null;

            $sides[] = [
                'court' => $court?->short_name ?? 'N/A',
                'court_id' => $courtId,
                'warrants' => $total,
                'same_day_pct' => round(100 * $sameDay / $total, 0),
                'weekend_pct' => round(100 * $weekend / $total, 0),
                'rejection_pct' => round(100 * $rejected / $total, 1),
                'avg_days' => $avgDays,
                'hhi' => round($hhi),
                'per_100k' => $perCapita,
            ];
        }

        $this->comparisonData = [
            'courts' => $sides,
            'national' => [
                'same_day_pct' => $natSameDayPct,
                'avg_days' => $natAvgDays,
                'weekend_pct' => $natWeekendPct,
            ],
        ];
    }

    /**
     * Normalize GraphQL response data
     */
    private function normalizeData(): void
    {
        if (! is_array($this->data)) {
            return;
        }

        if (isset($this->data['data']) && is_array($this->data['data'])) {
            $unwrapped = $this->unwrapArr($this->data['data']);
            if (! empty($unwrapped)) {
                $this->data = $unwrapped[0] ?? $this->data;
            }
        }

        foreach (['pismena', 'rocista', 'stranke', 'vjecnici', 'povezaniPredmeti', 'stariPredmet'] as $field) {
            if (isset($this->data[$field])) {
                $this->data[$field] = $this->unwrapArr($this->data[$field]);
            }
        }

        $pismenaArray = $this->data['pismena'] ?? [];
        if (is_array($pismenaArray) && ! empty($pismenaArray)) {
            $pismenaArray = collect($pismenaArray)
                ->map(function ($pisma) {
                    if (! is_array($pisma)) {
                        return $pisma;
                    }
                    $datumPismena = $pisma['datum'] ?? null;
                    if ($datumPismena) {
                        try {
                            $pisma['datum'] = Carbon::parse($datumPismena)->format('Y-m-d H:i:s');
                        } catch (\Throwable $e) {
                            // leave as-is
                        }
                    }

                    return $pisma;
                })
                ->sortByDesc(function ($pisma) {
                    return $pisma['datum'] ?? null;
                })
                ->values()
                ->toArray();
            $this->data['pismena'] = $pismenaArray;
        }

        if (! empty($this->data['stranke'])) {
            $this->data['stranke'] = array_values(array_filter($this->data['stranke'], 'is_array'));
        }
    }

    private function unwrapArr(mixed $value): array
    {
        $out = [];
        $this->flattenWrapped($value, $out);

        return $out;
    }

    private function flattenWrapped(mixed $value, array &$out): void
    {
        if (! is_array($value)) {
            return;
        }

        if ($this->isAssoc($value) && count($value) === 1 && isset($value['s']) && $value['s'] === 'arr') {
            return;
        }

        if ($this->isList($value) && count($value) === 2 && is_array($value[1]) && isset($value[1]['s']) && $value[1]['s'] === 'arr') {
            $this->flattenWrapped($value[0], $out);

            return;
        }

        if ($this->isList($value)) {
            foreach ($value as $v) {
                $this->flattenWrapped($v, $out);
            }

            return;
        }

        $out[] = $value;
    }

    private function isList(array $array): bool
    {
        $expected = 0;
        foreach (array_keys($array) as $key) {
            if ($key !== $expected) {
                return false;
            }
            $expected++;
        }

        return true;
    }

    private function isAssoc(array $array): bool
    {
        return ! $this->isList($array);
    }

    public function render()
    {
        return view('livewire.epredmet-widget');
    }
}
