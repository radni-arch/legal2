<?php

namespace App\Console\Commands;

use App\Services\SudskaPraksaService;
use App\Validators\KeywordsValidator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SudskaPraksaSearch extends Command
{
    protected $signature = 'sudska-praksa:search
        {--keywords-file= : Putanja do keywords JSON datoteke}
        {--courts= : Sudovi za pretragu (default: vks,vps,vs,zs)}
        {--delay=500 : Pauza između zahtjeva u ms}
        {--format=all : Format outputa (json|csv|md|all|table)}
        {--output= : Putanja za spremanje rezultata}
        {--expand : Auto-generiraj varijacije ključnih riječi}
        {--max= : Maksimalan broj upita za izvršiti}
        {--min-results=0 : Minimalan broj rezultata za prikaz}
        {--max-results= : Maksimalan broj rezultata za prikaz}
        {--filter= : Filtriraj po klasifikaciji (ultra|zlato|srebrno|bronca|bulk|empty|error)}
        {--generate-keywords : Generiraj primjer keywords datoteke}
        {--persist : Spremi rezultate u bazu podataka}';

    protected $description = 'Pretraži odluke.sudovi.hr za broj rezultata po ključnim riječima';

    private string $baseUrl;
    private array $results = [];
    private int $totalQueries = 0;
    private array $thresholds;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->baseUrl = config('sudska-praksa.base_url');
        $this->thresholds = config('sudska-praksa.thresholds');

        // Handle --generate-keywords option
        if ($this->option('generate-keywords')) {
            return $this->generateSampleKeywords();
        }

        // Load keywords
        $keywordsFile = $this->option('keywords-file')
            ?? config('sudska-praksa.keywords_file');

        $keywordsPath = base_path($keywordsFile);

        if (!file_exists($keywordsPath)) {
            $this->error("Keywords datoteka nije pronađena: {$keywordsPath}");
            $this->line("Koristite --generate-keywords za generiranje primjera.");
            return self::FAILURE;
        }

        $keywords = json_decode(file_get_contents($keywordsPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Neispravan JSON: " . json_last_error_msg());
            return self::FAILURE;
        }

        // Validate keywords structure
        $validation = KeywordsValidator::validate($keywords);
        if (!$validation['valid']) {
            $this->warn("Upozorenja u keywords datoteci:");
            foreach ($validation['errors'] as $error) {
                $this->line("   - {$error}");
            }
            $this->newLine();
            if (!$this->confirm('Nastavi unatoc upozorenjima?', true)) {
                return self::FAILURE;
            }
        }

        // Display metadata
        $this->info("🔍 Sudska Praksa Search");
        $this->newLine();

        if (isset($keywords['metadata'])) {
            $meta = $keywords['metadata'];
            $this->line("📋 Slučaj: " . ($meta['name'] ?? 'N/A'));
            if (isset($meta['description'])) {
                $this->line("   " . Str::limit($meta['description'], 80));
            }
            $this->newLine();
        }

        // Count queries
        $categories = $keywords['categories'] ?? [];
        $this->totalQueries = collect($categories)->sum(fn($cat) => count($cat['queries'] ?? []));

        $max = $this->option('max') ? (int) $this->option('max') : $this->totalQueries;
        $this->totalQueries = min($max, $this->totalQueries);

        $courts = $this->option('courts') ?? config('sudska-praksa.default_courts');
        $delay = (int) $this->option('delay');

        $this->info("⚙️  Postavke:");
        $this->line("   Sudovi: {$courts}");
        $this->line("   Upiti: {$this->totalQueries}");
        $this->line("   Pauza: {$delay}ms");
        $this->newLine();

        // Create progress bar
        $bar = $this->output->createProgressBar($this->totalQueries);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | %message%');
        $bar->setMessage('Započinjem...');
        $bar->start();

        // Run queries
        $index = 0;
        foreach ($categories as $category) {
            $categoryName = $category['name'] ?? 'Unnamed';
            $categoryDescription = $category['description'] ?? '';

            foreach ($category['queries'] ?? [] as $queryItem) {
                if ($index >= $this->totalQueries) {
                    break 2;
                }

                $query = is_string($queryItem) ? $queryItem : ($queryItem['q'] ?? $queryItem['query'] ?? '');
                $comment = is_string($queryItem) ? '' : ($queryItem['comment'] ?? '');

                if (empty($query)) {
                    continue;
                }

                $bar->setMessage(Str::limit($query, 50));

                $count = $this->fetchResultCount($query, $courts);

                $this->results[] = [
                    'category' => $categoryName,
                    'category_description' => $categoryDescription,
                    'query' => $query,
                    'comment' => $comment,
                    'count' => $count,
                    'classification' => $this->classify($count),
                    'url' => $this->buildUrl($query, $courts),
                    'fetched_at' => Carbon::now()->toIso8601String(),
                ];

                $bar->advance();
                $index++;

                if ($delay > 0) {
                    usleep($delay * 1000);
                }
            }
        }

        $bar->finish();
        $this->newLine(2);

        // Expand keywords if requested
        if ($this->option('expand')) {
            $this->info("🔄 Generiram varijacije ključnih riječi...");
            $expansions = $this->expandKeywords($courts, $delay);
            $this->results = array_merge($this->results, $expansions);
            $this->info("   Pronađeno " . count($expansions) . " varijacija.");
            $this->newLine();
        }

        // Filter results
        $minResults = (int) $this->option('min-results');
        $maxResults = $this->option('max-results') ? (int) $this->option('max-results') : PHP_INT_MAX;
        $filterClass = $this->option('filter');

        $filtered = collect($this->results)
            ->filter(fn($r) => $r['count'] >= $minResults && $r['count'] <= $maxResults)
            ->when($filterClass, fn($c) => $c->filter(fn($r) => $r['classification'] === $filterClass));

        // Display summary
        $this->displaySummary($filtered->toArray());

        // Persist to database if requested
        if ($this->option('persist')) {
            // Create search record directly (don't re-fetch via service)
            $search = \App\Models\SudskaPraksaSearch::create([
                'name' => $keywords['metadata']['name'] ?? 'Unnamed Search',
                'keywords_file' => $keywordsFile,
                'courts' => $courts,
                'metadata' => $keywords['metadata'] ?? null,
                'started_at' => now(),
            ]);

            // Save all results
            foreach ($filtered->toArray() as $r) {
                \App\Models\SudskaPraksaResult::create([
                    'search_id' => $search->id,
                    'category' => $r['category'],
                    'category_description' => $r['category_description'],
                    'query' => $r['query'],
                    'comment' => $r['comment'],
                    'count' => $r['count'],
                    'classification' => $r['classification'],
                    'url' => $r['url'],
                    'is_expanded' => str_contains($r['category'], '(expanded)'),
                    'fetched_at' => $r['fetched_at'],
                ]);
            }

            $search->finished_at = now();
            $search->computeStats();

            $this->newLine();
            $this->info("Spremljeno u bazu: Search #{$search->id}");
        }

        // Save results to files
        $this->saveResults($keywords, $filtered->toArray());

        return self::SUCCESS;
    }

    /**
     * Fetch result count from odluke.sudovi.hr
     */
    private function fetchResultCount(string $query, string $courts): int
    {
        try {
            $response = Http::timeout(config('sudska-praksa.timeout', 15))
                ->withHeaders([
                    'Accept' => 'text/html',
                    'User-Agent' => config('sudska-praksa.user_agent'),
                ])
                ->withCookies(['cookieConsent' => '{"type":"accept-all"}'], 'odluke.sudovi.hr')
                ->get($this->baseUrl, [
                    'q' => $query,
                    'sort' => 'rel',
                    'ct' => $courts,
                ]);

            if ($response->successful()) {
                $html = $response->body();

                // Try to extract result count
                if (preg_match('/(\d+)\s+rezultat/', $html, $matches)) {
                    return (int) $matches[1];
                }

                // Check for "no results" message
                if (Str::contains($html, ['Nema rezultata', 'nema rezultata'])) {
                    return 0;
                }
            }

            return -1;
        } catch (\Exception $e) {
            return -1;
        }
    }

    /**
     * Build the full search URL
     */
    private function buildUrl(string $query, string $courts): string
    {
        return $this->baseUrl . '?' . http_build_query([
            'q' => $query,
            'sort' => 'rel',
            'ct' => $courts,
        ]);
    }

    /**
     * Classify a result count into a tier
     */
    private function classify(int $count): string
    {
        if ($count < 0) return 'error';
        if ($count === 0) return 'empty';
        if ($count <= $this->thresholds['ultra']) return 'ultra';
        if ($count <= $this->thresholds['zlato']) return 'zlato';
        if ($count <= $this->thresholds['srebrno']) return 'srebrno';
        if ($count <= $this->thresholds['bronca']) return 'bronca';
        return 'bulk';
    }

    /**
     * Expand successful queries with additional keywords
     */
    private function expandKeywords(string $courts, int $delay): array
    {
        $existingQueries = collect($this->results)->pluck('query')->toArray();
        $expansions = [];

        foreach ($this->results as $r) {
            if ($r['count'] > $this->thresholds['srebrno'] || $r['count'] < 0) {
                continue;
            }

            $query = $r['query'];
            $words = preg_split('/\s+AND\s+/', $query);
            if (count($words) < 2) {
                continue;
            }

            $additions = ['izdvajanje', 'nezakonit'];
            foreach ($additions as $keyword) {
                if (Str::contains($query, $keyword)) {
                    continue;
                }

                $expanded = $query . ' AND ' . $keyword;
                if (in_array($expanded, $existingQueries)) {
                    continue;
                }

                $count = $this->fetchResultCount($expanded, $courts);
                $expansions[] = [
                    'category' => $r['category'] . ' (expanded)',
                    'category_description' => 'Automatski generirane varijacije',
                    'query' => $expanded,
                    'comment' => "Auto-expand: +{$keyword} od '{$query}'",
                    'count' => $count,
                    'classification' => $this->classify($count),
                    'url' => $this->buildUrl($expanded, $courts),
                    'fetched_at' => Carbon::now()->toIso8601String(),
                ];
                $existingQueries[] = $expanded;

                if ($delay > 0) {
                    usleep($delay * 1000);
                }
            }
        }

        return $expansions;
    }

    /**
     * Display summary of results
     */
    private function displaySummary(array $results): void
    {
        $byClass = collect($results)->groupBy('classification');

        $this->info("📊 Sažetak rezultata:");
        $this->newLine();

        $classEmojis = [
            'ultra' => '🏆 ULTRA',
            'zlato' => '🥇 ZLATO',
            'srebrno' => '🥈 SREBRNO',
            'bronca' => '🥉 BRONCA',
            'bulk' => '📦 BULK',
            'empty' => '⚪ PRAZNO',
            'error' => '❌ GREŠKA',
        ];

        $rows = [];
        foreach ($classEmojis as $class => $label) {
            $count = $byClass->get($class, collect())->count();
            if ($count > 0) {
                $rows[] = [$label, $count];
            }
        }

        $this->table(['Klasifikacija', 'Broj upita'], $rows);
        $this->newLine();

        // Show top results (ultra + zlato)
        $gold = collect($results)
            ->filter(fn($r) => in_array($r['classification'], ['ultra', 'zlato']))
            ->sortBy('count');

        if ($gold->isNotEmpty()) {
            $this->info("🎯 Top rezultati (ULTRA + ZLATO):");
            $this->newLine();

            $goldRows = $gold->map(fn($r) => [
                $r['classification'] === 'ultra' ? '🏆' : '🥇',
                $r['count'],
                Str::limit($r['query'], 60),
                $r['comment'] ?: '-',
            ])->toArray();

            $this->table(['', 'Broj', 'Upit', 'Komentar'], $goldRows);
        }
    }

    /**
     * Save results to files
     */
    private function saveResults(array $keywords, array $results): void
    {
        $format = $this->option('format');
        $outputDir = $this->option('output')
            ?? base_path(config('sudska-praksa.output_dir'));

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $name = Str::slug($keywords['metadata']['name'] ?? 'search');
        $basename = "{$name}_{$timestamp}";

        if ($format === 'json' || $format === 'all') {
            $jsonPath = "{$outputDir}/{$basename}.json";
            $jsonData = [
                'metadata' => $keywords['metadata'] ?? [],
                'generated_at' => Carbon::now()->toIso8601String(),
                'summary' => [
                    'total' => count($results),
                    'ultra' => collect($results)->where('classification', 'ultra')->count(),
                    'zlato' => collect($results)->where('classification', 'zlato')->count(),
                    'srebrno' => collect($results)->where('classification', 'srebrno')->count(),
                    'bronca' => collect($results)->where('classification', 'bronca')->count(),
                    'errors' => collect($results)->where('classification', 'error')->count(),
                ],
                'results' => $results,
            ];
            file_put_contents($jsonPath, json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->line("📁 JSON: {$jsonPath}");
        }

        if ($format === 'csv' || $format === 'all') {
            $csvPath = "{$outputDir}/{$basename}.csv";
            $fp = fopen($csvPath, 'w');
            fputcsv($fp, ['Kategorija', 'Upit', 'Komentar', 'Broj', 'Klasifikacija', 'URL']);
            foreach ($results as $r) {
                fputcsv($fp, [
                    $r['category'],
                    $r['query'],
                    $r['comment'],
                    $r['count'],
                    $r['classification'],
                    $r['url'],
                ]);
            }
            fclose($fp);
            $this->line("📁 CSV: {$csvPath}");
        }

        if ($format === 'md' || $format === 'all') {
            $mdPath = "{$outputDir}/{$basename}.md";
            $md = $this->generateMarkdown($keywords, $results);
            file_put_contents($mdPath, $md);
            $this->line("📁 Markdown: {$mdPath}");
        }
    }

    /**
     * Generate markdown report
     */
    private function generateMarkdown(array $keywords, array $results): string
    {
        $meta = $keywords['metadata'] ?? [];
        $name = $meta['name'] ?? 'Sudska Praksa Search';

        $md = "# {$name}\n\n";
        $md .= "**Datum:** " . Carbon::now()->format('d.m.Y H:i') . "\n\n";

        if (isset($meta['description'])) {
            $md .= "## Opis\n\n{$meta['description']}\n\n";
        }

        // Summary
        $byClass = collect($results)->groupBy('classification');
        $md .= "## Sažetak\n\n";
        $md .= "| Klasifikacija | Broj |\n";
        $md .= "|--------------|------|\n";
        foreach (['ultra', 'zlato', 'srebrno', 'bronca', 'bulk', 'empty', 'error'] as $class) {
            $count = $byClass->get($class, collect())->count();
            if ($count > 0) {
                $md .= "| {$class} | {$count} |\n";
            }
        }
        $md .= "\n";

        // Top results
        $gold = collect($results)
            ->filter(fn($r) => in_array($r['classification'], ['ultra', 'zlato']))
            ->sortBy('count');

        if ($gold->isNotEmpty()) {
            $md .= "## Top rezultati (ULTRA + ZLATO)\n\n";
            $md .= "| Broj | Upit | Komentar | Link |\n";
            $md .= "|------|------|----------|------|\n";
            foreach ($gold as $r) {
                $link = "[Pretraži]({$r['url']})";
                $md .= "| {$r['count']} | {$r['query']} | {$r['comment']} | {$link} |\n";
            }
            $md .= "\n";
        }

        // Full results by category
        $md .= "## Svi rezultati po kategorijama\n\n";
        $byCategory = collect($results)->groupBy('category');
        foreach ($byCategory as $category => $catResults) {
            $md .= "### {$category}\n\n";
            $md .= "| Broj | Klasifikacija | Upit | Komentar |\n";
            $md .= "|------|---------------|------|----------|\n";
            foreach ($catResults->sortBy('count') as $r) {
                $md .= "| {$r['count']} | {$r['classification']} | {$r['query']} | {$r['comment']} |\n";
            }
            $md .= "\n";
        }

        return $md;
    }

    /**
     * Generate sample keywords file
     */
    private function generateSampleKeywords(): int
    {
        $sample = [
            'metadata' => [
                'name' => 'Primjer pretrage',
                'description' => 'Primjer keywords datoteke za testiranje sustava',
                'version' => '1.0',
                'created_at' => Carbon::now()->toIso8601String(),
                'author' => 'AI Legal War Machine',
            ],
            'categories' => [
                [
                    'name' => '1. Pretraga doma',
                    'description' => 'Upiti vezani za pretragu stana i doma',
                    'queries' => [
                        ['q' => 'pretraga AND doma', 'comment' => 'Osnovni upit'],
                        ['q' => 'pretraga AND stana', 'comment' => 'Alternativa: stan umjesto dom'],
                        ['q' => 'pretres AND doma AND nalog', 'comment' => 'Sa nalogom'],
                    ],
                ],
                [
                    'name' => '2. Nezakoniti dokazi',
                    'description' => 'Upiti vezani za izdvajanje nezakonitih dokaza',
                    'queries' => [
                        ['q' => 'nezakonit AND dokaz AND izdvajanje', 'comment' => 'Izdvajanje dokaza'],
                        ['q' => 'plod AND otrovne AND stabljike', 'comment' => 'Fruit of poisonous tree'],
                    ],
                ],
            ],
        ];

        // Use --keywords-file option if provided, otherwise use config default
        $keywordsFile = $this->option('keywords-file');
        if ($keywordsFile) {
            // If it starts with storage_path format, use as-is
            $outputPath = str_starts_with($keywordsFile, '/') ? $keywordsFile : base_path($keywordsFile);
        } else {
            $outputPath = base_path(config('sudska-praksa.keywords_file'));
        }

        $dir = dirname($outputPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $outputPath,
            json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $this->info("Generirana primjer keywords datoteka:");
        $this->line("   {$outputPath}");
        $this->newLine();
        $this->info("Sljedeci korak:");
        $this->line("   php artisan sudska-praksa:search");

        return self::SUCCESS;
    }
}
