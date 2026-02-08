<?php

namespace App\Console\Commands;

use App\Models\CourtDecision;
use App\Models\IngestedLaw;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CacheWarmProduction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:warm-production
                            {--force : Force re-caching even if already cached}
                            {--verbose : Show detailed progress}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm up production cache with commonly accessed data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔥 Starting production cache warming...');
        $startTime = microtime(true);

        try {
            // Warm up laws cache
            $lawsCount = $this->cacheMostAccessedLaws();
            $this->info("✓ Cached {$lawsCount} frequently accessed laws");

            // Warm up recent court decisions
            $decisionsCount = $this->cacheRecentCourtDecisions();
            $this->info("✓ Cached {$decisionsCount} recent court decisions");

            // Warm up law articles
            $articlesCount = $this->cacheLawArticles();
            $this->info("✓ Cached {$articlesCount} law articles");

            // Warm up system configuration
            $configCount = $this->cacheSystemConfiguration();
            $this->info("✓ Cached {$configCount} system configurations");

            // Warm up user statistics (if needed)
            $statsCount = $this->cacheUserStatistics();
            $this->info("✓ Cached {$statsCount} user statistics");

            $duration = round(microtime(true) - $startTime, 2);
            $totalItems = $lawsCount + $decisionsCount + $articlesCount + $configCount + $statsCount;

            $this->newLine();
            $this->info('✅ Cache warming completed successfully!');
            $this->info("📊 Total items cached: {$totalItems}");
            $this->info("⏱️  Duration: {$duration} seconds");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Cache warming failed: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }

    /**
     * Cache most frequently accessed laws.
     */
    protected function cacheMostAccessedLaws(): int
    {
        $count = 0;

        // Top Croatian laws that are queried most often
        $topLaws = [
            'ZKP',      // Zakon o kaznenom postupku (Criminal Procedure Act)
            'KZ',       // Kazneni zakon (Criminal Code)
            'Ustav',    // Ustav Republike Hrvatske (Constitution)
            'ZODO',     // Zakon o općem upravnom postupku (General Administrative Procedure Act)
            'ZSSS',     // Zakon o sudovima (Courts Act)
            'OZ',       // Obiteljski zakon (Family Act)
            'ZOO',      // Zakon o obveznim odnosima (Obligations Act)
        ];

        if ($this->option('verbose')) {
            $this->line('Caching most accessed laws...');
        }

        foreach ($topLaws as $lawCode) {
            $cacheKey = "law:code:{$lawCode}";

            if ($this->option('force') || ! Cache::has($cacheKey)) {
                $law = IngestedLaw::where('law_code', $lawCode)
                    ->with(['articles' => function ($query) {
                        $query->orderBy('article_number');
                    }])
                    ->first();

                if ($law) {
                    Cache::put($cacheKey, $law, 86400); // 24 hours
                    $count++;

                    if ($this->option('verbose')) {
                        $this->line("  ✓ {$lawCode}");
                    }
                }
            } else {
                if ($this->option('verbose')) {
                    $this->line("  ⊘ {$lawCode} (already cached)");
                }
            }
        }

        // Cache law index (list of all laws)
        $lawIndexKey = 'laws:index';
        if ($this->option('force') || ! Cache::has($lawIndexKey)) {
            $lawsIndex = IngestedLaw::select('id', 'law_code', 'title', 'created_at')
                ->orderBy('law_code')
                ->get();
            Cache::put($lawIndexKey, $lawsIndex, 86400); // 24 hours
            $count++;
        }

        return $count;
    }

    /**
     * Cache recent court decisions.
     */
    protected function cacheRecentCourtDecisions(): int
    {
        $count = 0;

        if ($this->option('verbose')) {
            $this->line('Caching recent court decisions...');
        }

        // Cache recent decisions from each major court
        $courts = [
            'Vrhovni sud Republike Hrvatske',
            'Visoki upravni sud Republike Hrvatske',
            'Ustavni sud Republike Hrvatske',
        ];

        foreach ($courts as $court) {
            $cacheKey = "court_decisions:court:{$court}:recent";

            if ($this->option('force') || ! Cache::has($cacheKey)) {
                $decisions = CourtDecision::where('court', $court)
                    ->orderBy('date', 'desc')
                    ->limit(50)
                    ->get();

                if ($decisions->isNotEmpty()) {
                    Cache::put($cacheKey, $decisions, 3600); // 1 hour
                    $count += $decisions->count();

                    if ($this->option('verbose')) {
                        $this->line("  ✓ {$court}: {$decisions->count()} decisions");
                    }
                }
            }
        }

        // Cache decision index (recent 100)
        $decisionIndexKey = 'court_decisions:recent:100';
        if ($this->option('force') || ! Cache::has($decisionIndexKey)) {
            $recentDecisions = CourtDecision::select('id', 'ecli', 'court', 'date', 'created_at')
                ->orderBy('date', 'desc')
                ->limit(100)
                ->get();
            Cache::put($decisionIndexKey, $recentDecisions, 3600); // 1 hour
            $count++;
        }

        return $count;
    }

    /**
     * Cache law articles for most accessed laws.
     */
    protected function cacheLawArticles(): int
    {
        $count = 0;

        if ($this->option('verbose')) {
            $this->line('Caching law articles...');
        }

        // Get most accessed laws
        $topLawCodes = ['ZKP', 'KZ', 'Ustav'];

        foreach ($topLawCodes as $lawCode) {
            $law = IngestedLaw::where('law_code', $lawCode)->first();

            if ($law) {
                $cacheKey = "law:{$law->id}:articles";

                if ($this->option('force') || ! Cache::has($cacheKey)) {
                    $articles = $law->articles()
                        ->orderBy('article_number')
                        ->get();

                    Cache::put($cacheKey, $articles, 86400); // 24 hours
                    $count++;

                    if ($this->option('verbose')) {
                        $this->line("  ✓ {$lawCode}: {$articles->count()} articles");
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Cache system configuration and metadata.
     */
    protected function cacheSystemConfiguration(): int
    {
        $count = 0;

        if ($this->option('verbose')) {
            $this->line('Caching system configuration...');
        }

        // Cache database statistics
        $statsKey = 'system:stats:database';
        if ($this->option('force') || ! Cache::has($statsKey)) {
            $stats = [
                'laws_count' => IngestedLaw::count(),
                'court_decisions_count' => CourtDecision::count(),
                'last_updated' => now(),
            ];
            Cache::put($statsKey, $stats, 3600); // 1 hour
            $count++;
        }

        // Cache application metadata
        $metadataKey = 'system:metadata';
        if ($this->option('force') || ! Cache::has($metadataKey)) {
            $metadata = [
                'app_name' => config('app.name'),
                'app_version' => '1.0.0', // Update this as needed
                'cache_warmed_at' => now(),
            ];
            Cache::put($metadataKey, $metadata, 86400); // 24 hours
            $count++;
        }

        return $count;
    }

    /**
     * Cache user statistics (optional).
     */
    protected function cacheUserStatistics(): int
    {
        $count = 0;

        if ($this->option('verbose')) {
            $this->line('Caching user statistics...');
        }

        // Cache total users count
        $usersCountKey = 'system:stats:users_count';
        if ($this->option('force') || ! Cache::has($usersCountKey)) {
            $usersCount = DB::table('users')->count();
            Cache::put($usersCountKey, $usersCount, 3600); // 1 hour
            $count++;
        }

        return $count;
    }
}
