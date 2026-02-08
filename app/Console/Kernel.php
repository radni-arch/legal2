<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // ------------------------------------------------------------------
        // Court Practice Fetchings (cron jobs)
        // ------------------------------------------------------------------
        $this->scheduleCourtPracticeFetchings($schedule);

        // Separate schedules for e-Oglasna monitoring
        $schedule->command('eoglasna:watch-keywords')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('eoglasna:watch-osijek')->hourly()->withoutOverlapping();

        // Autonomous agent scheduled research - runs weekly on Sunday at 02:00
        $schedule->command('agent:research-scheduled --max-iterations=15 --time-limit=1800')
            ->weekly()
            ->sundays()
            ->at('02:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Graph metrics analysis - weekly on Sundays at 4 AM
        // Analyzes PageRank, clusters, and network statistics
        $schedule->command('graph:analyze-metrics')
            ->weekly()
            ->sundays()
            ->at('04:00')
            ->name('graph-metrics-analysis')
            ->onOneServer()
            ->withoutOverlapping(60); // Skip if previous run still active (1 hour window)

        // Graph embeddings training (Sprint 7.1)
        // Daily updates - trains/updates embeddings for all decisions (ON CONFLICT DO UPDATE)
        // Weekly full retraining - clears and regenerates all embeddings from scratch
        $schedule->command('graph:generate-embeddings')
            ->dailyAt('05:00')
            ->name('graph-embeddings-daily')
            ->onOneServer()
            ->withoutOverlapping(60); // Skip if training still running (1 hour window)

        $schedule->command('graph:generate-embeddings --clear')
            ->weekly()
            ->sundays()
            ->at('05:30')
            ->name('graph-embeddings-weekly-full')
            ->onOneServer()
            ->withoutOverlapping(600); // Skip if training still running (10 hour window)

        // Cross-Database Integrity Checks (Task 4.3)
        // Daily integrity check - validates Neo4j/PostgreSQL data consistency
        // Checks for orphan nodes, missing nodes, and dangling relationships
        $schedule->command('graph:integrity-check --email')
            ->dailyAt(config('integrity.schedule.integrity_check_time', '02:00'))
            ->name('graph-integrity-daily')
            ->onOneServer()
            ->withoutOverlapping(60) // Skip if previous run still active (1 hour window)
            ->emailOutputOnFailure(config('integrity.admin_email'));

        // Graph data quality checks (Sprint 8.4)
        // Weekly quality report - checks for duplicates, orphans, and inconsistencies
        $schedule->command('graph:check-quality --email')
            ->weekly()
            ->sundays()
            ->at(config('integrity.schedule.quality_check_time', '23:00'))
            ->name('graph-quality-weekly')
            ->onOneServer()
            ->withoutOverlapping(60); // Skip if previous run still active (1 hour window)

        // Emerging entity detection (Sprint 8.1)
        // Weekly detection on Mondays at 6:00 AM
        // Detects new prosecutors, judges, keywords, courts from past 7 days
        // Tags entities in Neo4j and stores in database for analytics
        $schedule->command('graph:detect-new-entities --tag --store')
            ->weekly()
            ->mondays()
            ->at('06:00')
            ->name('emerging-entity-detection')
            ->onOneServer()
            ->withoutOverlapping(30); // Skip if previous run still active (30 min window)

        // Topic spike analytics (Sprint 8.2)
        // Detects sudden increases in specific legal topics for attorney alerting
        // Runs weekly on Mondays at 7:00 AM (after entity detection would run)
        $schedule->command('graph:analyze-topic-trends')
            ->weekly()
            ->mondays()
            ->at('07:00')
            ->name('topic-spike-analytics')
            ->onOneServer()
            ->withoutOverlapping(30); // Skip if analysis still running (30 min window)

        // Prosecutorial outlier detection (Sprint 8.3)
        // Statistical analysis for prosecutors/courts with anomalous suppression/violation rates
        // Runs monthly on first Monday at 8:00 AM (z-score analysis with |z| > 2.0 threshold)
        // Stores outliers in database for Grafana visualization and attorney alerts
        $schedule->command('graph:detect-outliers --min-cases=10 --store')
            ->monthlyOn(1, '08:00') // First day of month at 8:00 AM
            ->mondays() // Only if first day is Monday, otherwise next Monday
            ->name('prosecutorial-outlier-detection')
            ->onOneServer()
            ->withoutOverlapping(60); // Skip if analysis still running (1 hour window)

    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }

    protected function scheduleCourtPracticeFetchings(Schedule $schedule): void
    {
        // Optional autonomous discovery (LLM) for odluke.sudovi.hr
        if ((bool) env('DECISION_DISCOVERY_SCHEDULE_ENABLED', true)) {
            $schedule->command('decisions:discover-async')
                ->dailyAt('02:00')
                ->name('decision-discovery-daily')
                ->onOneServer()
                ->withoutOverlapping(60);

            $schedule->command('decisions:discover --topics=10 --threshold=60')
                ->weekly()
                ->sundays()
                ->at('03:00')
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->command('decisions:discovery --queue')
                ->dailyAt('03:00')
                ->name('decision-discovery-scheduled')
                ->onOneServer()
                ->withoutOverlapping(120);
        }

        // Regular court decisions (odluke.sudovi.hr) fetching - keyword driven (SudskaPraksaSearch)
        $schedule->call(function () {
            $this->runOdlukeKeywordFetching();
        })
            ->dailyAt(env('ODLUKE_FETCH_TIME', '01:00'))
            ->name('odluke-keyword-fetching')
            ->onOneServer()
            ->withoutOverlapping(240)
            ->runInBackground();

        // ECHR + Constitutional Court fetching (guidelines driven)
        $schedule->call(function () {
            $this->runGuidelineFetchings();
        })
            ->dailyAt(env('COURT_FETCH_TIME', '01:15'))
            ->name('court-practice-fetchings')
            ->onOneServer()
            ->withoutOverlapping(240)
            ->runInBackground();
    }

    protected function runFetchCommand(string $command, string $topic, int $limit): void
    {
        try {
            $status = Artisan::call($command, [
                'query' => $topic,
                '--limit' => $limit,
            ]);
            if ($status !== 0) {
                Log::warning('Court practice fetching command returned non-zero status', [
                    'command' => $command,
                    'topic' => $topic,
                    'status' => $status,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Court practice fetching command failed', [
                'command' => $command,
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function runGuidelineFetchings(): void
    {
        $topics = $this->resolveGuidelineTopics();
        if (empty($topics)) {
            Log::debug('Guideline fetchings skipped: no topics resolved.');

            return;
        }

        $maxTopics = max(1, (int) env('COURT_FETCH_MAX_TOPICS', 3));
        $topics = array_slice($topics, 0, $maxTopics);

        $esljpLimit = max(1, (int) env('ESLJP_FETCH_LIMIT', 200));
        $usudLimit = max(1, (int) env('USUD_FETCH_LIMIT', 200));

        foreach ($topics as $topic) {
            $topic = trim((string) $topic);
            if ($topic === '') {
                continue;
            }

            $this->runFetchCommand('esljp:ingest-query', $topic, $esljpLimit);
            $this->runFetchCommand('usud:ingest-query', $topic, $usudLimit);
        }
    }

    protected function runOdlukeKeywordFetching(): void
    {
        if ((bool) env('SUDSKA_PRAKSA_RUN_BEFORE_FETCH', true)) {
            $this->runSudskaPraksaSearch();
        }

        $queries = $this->resolveOdlukeQueriesFromSudskaPraksa();
        if (empty($queries)) {
            Log::debug('Odluke keyword fetching skipped: no queries resolved.');

            return;
        }

        $maxQueries = max(1, (int) env('ODLUKE_FETCH_MAX_QUERIES', 10));
        $queries = array_slice($queries, 0, $maxQueries);
        $limit = max(1, (int) env('ODLUKE_FETCH_LIMIT', 50));

        foreach ($queries as $query) {
            $query = trim((string) $query);
            if ($query === '') {
                continue;
            }

            $this->runFetchCommand('odluke:ingest-query', $query, $limit);
        }
    }

    protected function runSudskaPraksaSearch(): void
    {
        $keywordsFile = config('sudska-praksa.keywords_file');
        $args = [
            '--keywords-file' => $keywordsFile,
            '--persist' => true,
        ];

        $max = env('SUDSKA_PRAKSA_FETCH_MAX', null);
        if ($max !== null && $max !== '') {
            $args['--max'] = $max;
        }

        $delay = env('SUDSKA_PRAKSA_FETCH_DELAY', null);
        if ($delay !== null && $delay !== '') {
            $args['--delay'] = $delay;
        }

        try {
            $status = Artisan::call('sudska-praksa:search', $args);
            if ($status !== 0) {
                Log::warning('SudskaPraksaSearch returned non-zero status', [
                    'status' => $status,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SudskaPraksaSearch failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function resolveOdlukeQueriesFromSudskaPraksa(): array
    {
        $classFilter = (string) env('SUDSKA_PRAKSA_FETCH_CLASSIFICATIONS', 'ultra,zlato');
        $allowed = array_filter(array_map('trim', explode(',', $classFilter)));

        try {
            $latest = \App\Models\SudskaPraksaSearch::query()
                ->orderBy('created_at', 'desc')
                ->first();

            if ($latest) {
                $query = $latest->results();
                if (! empty($allowed)) {
                    $query->whereIn('classification', $allowed);
                }

                $queries = $query->pluck('query')->toArray();
                $queries = array_filter(array_map('trim', $queries), fn ($q) => $q !== '');
                if (! empty($queries)) {
                    return array_values(array_unique($queries));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to resolve Odluke queries from SudskaPraksa results', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->resolveQueriesFromKeywordsFile();
    }

    protected function resolveQueriesFromKeywordsFile(): array
    {
        $keywordsFile = config('sudska-praksa.keywords_file');
        $path = base_path($keywordsFile);
        if (! is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return [];
        }

        $queries = [];
        foreach ($data['categories'] ?? [] as $category) {
            foreach ($category['queries'] ?? [] as $queryItem) {
                $query = is_string($queryItem) ? $queryItem : ($queryItem['q'] ?? $queryItem['query'] ?? '');
                $query = trim((string) $query);
                if ($query !== '') {
                    $queries[] = $query;
                }
            }
        }

        return array_values(array_unique($queries));
    }

    protected function resolveGuidelineTopics(): array
    {
        $path = base_path('usud-guidelines.md');
        if (! is_file($path)) {
            return $this->resolveFallbackTopics();
        }

        $raw = @file_get_contents($path);
        if (! is_string($raw) || $raw === '') {
            return $this->resolveFallbackTopics();
        }

        $topics = [];

        if (preg_match_all('/\bČlanak\s+(\d{1,2})\b/iu', $raw, $m)) {
            foreach ($m[1] as $num) {
                $topics[] = 'članak '.$num;
            }
        }

        if (preg_match_all('/\bU-[A-Z0-9-]+\/\d{4}\b/u', $raw, $m)) {
            foreach ($m[0] as $case) {
                $topics[] = $case;
            }
        }

        if (preg_match_all('/\bPp\s*Prz-?\s*\d+\/\d{4}\b/u', $raw, $m)) {
            foreach ($m[0] as $case) {
                $topics[] = preg_replace('/\s+/', ' ', trim($case));
            }
        }

        if (preg_match_all('/\b[A-Z][A-Za-zÀ-ž]+(?:\s+[A-Z][A-Za-zÀ-ž]+)*\s+v\.\s+[A-Z][A-Za-zÀ-ž]+/u', $raw, $m)) {
            foreach ($m[0] as $case) {
                $topics[] = trim($case);
            }
        }

        $keywords = [
            'pravo na pošteno suđenje',
            'pravo na privatni i obiteljski život',
            'djelotvorno pravno sredstvo',
            'pravo na žalbu',
            'pravično suđenje',
            'nepovredivost doma',
            'operativni izvidi',
            'nulti dokument',
            'ustavna tužba',
        ];

        foreach ($keywords as $keyword) {
            if (stripos($raw, $keyword) !== false) {
                $topics[] = $keyword;
            }
        }

        $topics = array_filter(array_map('trim', $topics), fn ($t) => $t !== '');
        $topics = array_values(array_unique($topics));

        return ! empty($topics) ? $topics : $this->resolveFallbackTopics();
    }

    protected function resolveFallbackTopics(): array
    {
        $raw = (string) env('COURT_FETCH_TOPICS', '');
        $topics = array_filter(array_map('trim', explode(',', $raw)), fn ($t) => $t !== '');

        return array_values(array_unique($topics));
    }
}
