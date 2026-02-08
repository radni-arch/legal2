# SudskaPraksa Search Integration — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Integrate the odluke.sudovi.hr keyword probing system (Artisan command + methodology + keyword JSON structure) into the existing Laravel application, with database persistence, scheduling, and a service-layer architecture for reuse by both CLI and future web UI.

**Architecture:** Drop-in Artisan command backed by a `SudskaPraksaService` that handles HTTP probing, result classification, and persistence. Keywords are stored as JSON files (filesystem) and results are persisted to PostgreSQL for trend analysis. A config file centralizes settings. The methodology documentation becomes a developer/user reference in `docs/`.

**Tech Stack:** Laravel 11+, PostgreSQL, Laravel HTTP client, Carbon, Laravel Scheduler, PHPUnit/Pest

---

## Sprint 1: Foundation — Files, Config, and Smoke Test

> **Objective:** Get the command running as-is, with proper file placement, config extraction, and a basic smoke test.

---

### Task 1.1: Place Source Files

**Files:**
- Create: `app/Console/Commands/SudskaPraksaSearch.php`
- Create: `storage/app/keywords/default.json`
- Create: `docs/sudska-praksa/METHODOLOGY.md`
- Create: `docs/sudska-praksa/README.md`

**Step 1: Copy Artisan command into project**

```bash
cp /path/to/SudskaPraksaSearch.php app/Console/Commands/SudskaPraksaSearch.php
```

Verify namespace is `App\Console\Commands`.

**Step 2: Copy keywords JSON**

```bash
mkdir -p storage/app/keywords
cp /path/to/default_keywords.json storage/app/keywords/default.json
```

**Step 3: Copy documentation**

```bash
mkdir -p docs/sudska-praksa
cp /path/to/METHODOLOGY.md docs/sudska-praksa/METHODOLOGY.md
cp /path/to/README.md docs/sudska-praksa/README.md
```

**Step 4: Verify command registration**

```bash
php artisan list | grep sudska-praksa
```

Expected: `sudska-praksa:search   Pretraži odluke.sudovi.hr za broj rezultata po ključnim riječima`

**Step 5: Commit**

```bash
git add app/Console/Commands/SudskaPraksaSearch.php storage/app/keywords/default.json docs/sudska-praksa/
git commit -m "feat: add SudskaPraksa search command and documentation"
```

---

### Task 1.2: Extract Configuration

**Files:**
- Create: `config/sudska-praksa.php`
- Modify: `app/Console/Commands/SudskaPraksaSearch.php`

**Step 1: Create config file**

Create `config/sudska-praksa.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Base URL for odluke.sudovi.hr search
    |--------------------------------------------------------------------------
    */
    'base_url' => env('SUDSKA_PRAKSA_BASE_URL', 'https://odluke.sudovi.hr/Document/DisplayList'),

    /*
    |--------------------------------------------------------------------------
    | Default court types (ct parameter)
    |--------------------------------------------------------------------------
    | vks = Vrhovni kazneni sud
    | vps = Visoki prekršajni sud
    | vs  = Vrhovni sud
    | zs  = Županijski sudovi
    */
    'default_courts' => env('SUDSKA_PRAKSA_COURTS', 'vks,vps,vs,zs'),

    /*
    |--------------------------------------------------------------------------
    | Request delay between queries (milliseconds)
    |--------------------------------------------------------------------------
    */
    'delay_ms' => (int) env('SUDSKA_PRAKSA_DELAY', 500),

    /*
    |--------------------------------------------------------------------------
    | HTTP timeout per request (seconds)
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('SUDSKA_PRAKSA_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Default keywords file path (relative to base_path)
    |--------------------------------------------------------------------------
    */
    'keywords_file' => env('SUDSKA_PRAKSA_KEYWORDS', 'storage/app/keywords/default.json'),

    /*
    |--------------------------------------------------------------------------
    | Default output directory (relative to base_path)
    |--------------------------------------------------------------------------
    */
    'output_dir' => env('SUDSKA_PRAKSA_OUTPUT', 'storage/app/results'),

    /*
    |--------------------------------------------------------------------------
    | Classification thresholds
    |--------------------------------------------------------------------------
    */
    'thresholds' => [
        'ultra'   => 5,    // ≤5 results = ULTRA ZLATO
        'zlato'   => 15,   // 6-15 = ZLATO
        'srebrno' => 50,   // 16-50 = SREBRNO
        'bronca'  => 150,  // 51-150 = BRONCA
    ],

    /*
    |--------------------------------------------------------------------------
    | User-Agent for HTTP requests
    |--------------------------------------------------------------------------
    */
    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
];
```

**Step 2: Update command to use config**

In `SudskaPraksaSearch.php`, replace the hardcoded `$baseUrl` property:

```php
// BEFORE:
private string $baseUrl = 'https://odluke.sudovi.hr/Document/DisplayList';

// AFTER:
private string $baseUrl;

public function __construct()
{
    parent::__construct();
    $this->baseUrl = config('sudska-praksa.base_url');
}
```

Update `fetchResultCount()` to use config timeout:

```php
// BEFORE:
$response = Http::timeout(15)

// AFTER:
$response = Http::timeout(config('sudska-praksa.timeout'))
```

Update User-Agent to use config:

```php
// BEFORE:
'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',

// AFTER:
'User-Agent' => config('sudska-praksa.user_agent'),
```

**Step 3: Add .env entries**

Append to `.env.example`:

```
# Sudska Praksa Search
SUDSKA_PRAKSA_BASE_URL=https://odluke.sudovi.hr/Document/DisplayList
SUDSKA_PRAKSA_COURTS=vks,vps,vs,zs
SUDSKA_PRAKSA_DELAY=500
SUDSKA_PRAKSA_TIMEOUT=15
```

**Step 4: Verify config loads**

```bash
php artisan tinker --execute="dump(config('sudska-praksa.base_url'));"
```

Expected: `"https://odluke.sudovi.hr/Document/DisplayList"`

**Step 5: Commit**

```bash
git add config/sudska-praksa.php app/Console/Commands/SudskaPraksaSearch.php .env.example
git commit -m "refactor: extract SudskaPraksa config to config file"
```

---

### Task 1.3: Write Smoke Test

**Files:**
- Create: `tests/Feature/Commands/SudskaPraksaSearchTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Commands;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;

class SudskaPraksaSearchTest extends TestCase
{
    public function test_command_runs_with_default_keywords(): void
    {
        // Mock HTTP responses from odluke.sudovi.hr
        Http::fake([
            'odluke.sudovi.hr/*' => Http::response(
                '<html><body>Pronađeno je 42 rezultata za vaš upit</body></html>',
                200
            ),
        ]);

        $this->artisan('sudska-praksa:search', [
            '--delay' => 0,
            '--format' => 'json',
        ])->assertSuccessful();
    }

    public function test_command_fails_on_missing_keywords_file(): void
    {
        $this->artisan('sudska-praksa:search', [
            '--keywords-file' => 'nonexistent.json',
        ])->assertFailed();
    }

    public function test_generate_keywords_option_creates_file(): void
    {
        $this->artisan('sudska-praksa:search', [
            '--generate-keywords' => true,
        ])->assertSuccessful();
    }

    public function test_result_count_parsing_extracts_number(): void
    {
        Http::fake([
            'odluke.sudovi.hr/*' => Http::response(
                '<html><body>7 rezultata</body></html>',
                200
            ),
        ]);

        $this->artisan('sudska-praksa:search', [
            '--delay' => 0,
            '--format' => 'json',
            '--max' => 10,
        ])->assertSuccessful();
    }

    public function test_nema_rezultata_returns_zero(): void
    {
        Http::fake([
            'odluke.sudovi.hr/*' => Http::response(
                '<html><body>Nema rezultata</body></html>',
                200
            ),
        ]);

        $this->artisan('sudska-praksa:search', [
            '--delay' => 0,
            '--format' => 'json',
        ])->assertSuccessful();
    }
}
```

**Step 2: Run tests to verify they pass**

```bash
php artisan test --filter=SudskaPraksaSearchTest
```

Expected: 5 tests pass (all use Http::fake, no real network calls).

**Step 3: Commit**

```bash
git add tests/Feature/Commands/SudskaPraksaSearchTest.php
git commit -m "test: add smoke tests for SudskaPraksa search command"
```

---

## Sprint 2: Service Layer Extraction

> **Objective:** Extract the HTTP probing and result parsing logic into a reusable `SudskaPraksaService` so both CLI and future web routes can use it.

---

### Task 2.1: Create the Service Class

**Files:**
- Create: `app/Services/SudskaPraksaService.php`
- Create: `tests/Unit/Services/SudskaPraksaServiceTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\SudskaPraksaService;
use Illuminate\Support\Facades\Http;

class SudskaPraksaServiceTest extends TestCase
{
    private SudskaPraksaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SudskaPraksaService();
    }

    public function test_fetch_result_count_parses_number(): void
    {
        Http::fake([
            'odluke.sudovi.hr/*' => Http::response(
                '<html><body>42 rezultata za vaš upit</body></html>',
                200
            ),
        ]);

        $result = $this->service->fetchResultCount('pretraga AND doma');
        $this->assertEquals(42, $result);
    }

    public function test_fetch_result_count_returns_zero_for_no_results(): void
    {
        Http::fake([
            'odluke.sudovi.hr/*' => Http::response(
                '<html><body>Nema rezultata</body></html>',
                200
            ),
        ]);

        $result = $this->service->fetchResultCount('nonexistent AND query');
        $this->assertEquals(0, $result);
    }

    public function test_fetch_result_count_returns_negative_on_error(): void
    {
        Http::fake([
            'odluke.sudovi.hr/*' => Http::response('Server Error', 500),
        ]);

        $result = $this->service->fetchResultCount('error AND query');
        $this->assertEquals(-1, $result);
    }

    public function test_build_url_creates_correct_url(): void
    {
        $url = $this->service->buildUrl('pretraga AND doma', 'vks,vps');
        $this->assertStringContainsString('q=pretraga+AND+doma', $url);
        $this->assertStringContainsString('ct=vks%2Cvps', $url);
    }

    public function test_classify_result_count(): void
    {
        $this->assertEquals('ultra', $this->service->classify(3));
        $this->assertEquals('zlato', $this->service->classify(10));
        $this->assertEquals('srebrno', $this->service->classify(30));
        $this->assertEquals('bronca', $this->service->classify(100));
        $this->assertEquals('bulk', $this->service->classify(500));
        $this->assertEquals('error', $this->service->classify(-1));
    }

    public function test_run_queries_returns_collection(): void
    {
        Http::fake([
            'odluke.sudovi.hr/*' => Http::response(
                '<html><body>5 rezultata</body></html>',
                200
            ),
        ]);

        $queries = [
            [
                'category' => 'Test',
                'category_description' => 'Test category',
                'queries' => [
                    ['q' => 'test AND query', 'comment' => 'Test comment'],
                ],
            ],
        ];

        $results = $this->service->runQueries($queries, delayMs: 0);

        $this->assertCount(1, $results);
        $this->assertEquals('test AND query', $results[0]['query']);
        $this->assertEquals(5, $results[0]['count']);
        $this->assertEquals('ultra', $results[0]['classification']);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
php artisan test --filter=SudskaPraksaServiceTest
```

Expected: FAIL — `Class 'App\Services\SudskaPraksaService' not found`

**Step 3: Write the service**

Create `app/Services/SudskaPraksaService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SudskaPraksaService
{
    private string $baseUrl;
    private string $userAgent;
    private int $timeout;
    private array $thresholds;

    public function __construct()
    {
        $this->baseUrl = config('sudska-praksa.base_url');
        $this->userAgent = config('sudska-praksa.user_agent');
        $this->timeout = config('sudska-praksa.timeout');
        $this->thresholds = config('sudska-praksa.thresholds');
    }

    /**
     * Fetch the result count for a single query from odluke.sudovi.hr
     */
    public function fetchResultCount(string $query, ?string $courts = null): int
    {
        $courts = $courts ?? config('sudska-praksa.default_courts');

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Accept' => 'text/html',
                    'User-Agent' => $this->userAgent,
                ])
                ->withCookies(['cookieConsent' => '{"type":"accept-all"}'], 'odluke.sudovi.hr')
                ->get($this->baseUrl, [
                    'q' => $query,
                    'sort' => 'rel',
                    'ct' => $courts,
                ]);

            if ($response->successful()) {
                $html = $response->body();

                if (preg_match('/(\d+)\s+rezultat/', $html, $matches)) {
                    return (int) $matches[1];
                }

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
    public function buildUrl(string $query, ?string $courts = null): string
    {
        $courts = $courts ?? config('sudska-praksa.default_courts');

        return $this->baseUrl . '?' . http_build_query([
            'q' => $query,
            'sort' => 'rel',
            'ct' => $courts,
        ]);
    }

    /**
     * Classify a result count into a tier
     */
    public function classify(int $count): string
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
     * Run all queries from a categories array and return results collection
     *
     * @param array $categories  Array of category objects with 'queries' arrays
     * @param string|null $courts  Court types filter
     * @param int $delayMs  Delay between requests in milliseconds
     * @param callable|null $onProgress  Callback(query, count, index, total)
     * @return array
     */
    public function runQueries(
        array $categories,
        ?string $courts = null,
        int $delayMs = 500,
        ?callable $onProgress = null,
    ): array {
        $courts = $courts ?? config('sudska-praksa.default_courts');
        $results = [];
        $index = 0;
        $total = collect($categories)->sum(fn($cat) => count($cat['queries'] ?? []));

        foreach ($categories as $category) {
            $categoryName = $category['name'];
            $categoryDescription = $category['description'] ?? '';

            foreach ($category['queries'] ?? [] as $queryItem) {
                $query = is_string($queryItem) ? $queryItem : ($queryItem['q'] ?? $queryItem['query'] ?? '');
                $comment = is_string($queryItem) ? '' : ($queryItem['comment'] ?? '');

                if (empty($query)) continue;

                $count = $this->fetchResultCount($query, $courts);

                $result = [
                    'category' => $categoryName,
                    'category_description' => $categoryDescription,
                    'query' => $query,
                    'comment' => $comment,
                    'count' => $count,
                    'classification' => $this->classify($count),
                    'url' => $this->buildUrl($query, $courts),
                    'fetched_at' => Carbon::now()->toIso8601String(),
                ];

                $results[] = $result;
                $index++;

                if ($onProgress) {
                    $onProgress($query, $count, $index, $total);
                }

                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }
        }

        return $results;
    }

    /**
     * Expand successful queries with additional keywords
     */
    public function expandQueries(array $results, ?string $courts = null, int $delayMs = 500): array
    {
        $courts = $courts ?? config('sudska-praksa.default_courts');
        $existingQueries = collect($results)->pluck('query')->toArray();
        $expansions = [];

        foreach ($results as $r) {
            if ($r['count'] > $this->thresholds['srebrno'] || $r['count'] < 0) continue;

            $query = $r['query'];
            $words = preg_split('/\s+AND\s+/', $query);
            if (count($words) < 2) continue;

            $additions = ['izdvajanje', 'nezakonit'];
            foreach ($additions as $keyword) {
                if (Str::contains($query, $keyword)) continue;

                $expanded = $query . ' AND ' . $keyword;
                if (in_array($expanded, $existingQueries)) continue;

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

                if ($delayMs > 0) usleep($delayMs * 1000);
            }
        }

        return $expansions;
    }
}
```

**Step 4: Run test to verify it passes**

```bash
php artisan test --filter=SudskaPraksaServiceTest
```

Expected: All 7 tests PASS.

**Step 5: Commit**

```bash
git add app/Services/SudskaPraksaService.php tests/Unit/Services/SudskaPraksaServiceTest.php
git commit -m "feat: extract SudskaPraksaService from command logic"
```

---

### Task 2.2: Refactor Command to Use Service

**Files:**
- Modify: `app/Console/Commands/SudskaPraksaSearch.php`
- Modify: `tests/Feature/Commands/SudskaPraksaSearchTest.php`

**Step 1: Refactor the command**

Replace the `fetchResultCount()`, `buildUrl()`, and `expandKeywords()` methods in the command with delegations to the service. The command becomes a thin CLI wrapper:

```php
// In handle() method, inject the service:
public function handle(SudskaPraksaService $service): int
{
    // ... (keep keyword file loading and validation as-is)

    // Replace the manual loop with:
    $bar = $this->output->createProgressBar($this->totalQueries);
    $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | %message%');
    $bar->start();

    $this->results = $service->runQueries(
        categories: $keywords['categories'] ?? [],
        courts: $courts,
        delayMs: $delay,
        onProgress: function ($query, $count, $index, $total) use ($bar) {
            $bar->setMessage(Str::limit($query, 50));
            $bar->advance();
        },
    );

    $bar->finish();
    $this->newLine(2);

    // Expand if requested
    if ($this->option('expand')) {
        $this->info("🔄 Generiram varijacije ključnih riječi...");
        $expansions = $service->expandQueries($this->results, $courts, $delay);
        $this->results = array_merge($this->results, $expansions);
        $this->info("   Pronađeno " . count($expansions) . " varijacija.");
    }

    // ... (keep filtering, sorting, display, and save logic as-is)
}
```

Remove these private methods from the command (they now live in the service):
- `fetchResultCount()`
- `buildUrl()`
- `expandKeywords()`

**Step 2: Run existing tests**

```bash
php artisan test --filter=SudskaPraksaSearchTest
```

Expected: All tests still PASS.

**Step 3: Commit**

```bash
git add app/Console/Commands/SudskaPraksaSearch.php
git commit -m "refactor: command delegates to SudskaPraksaService"
```

---

## Sprint 3: Database Persistence

> **Objective:** Store search results in PostgreSQL so we can track trends, compare runs, and avoid re-running identical queries.

---

### Task 3.1: Create Migration

**Files:**
- Create: migration for `sudska_praksa_searches` and `sudska_praksa_results` tables

**Step 1: Generate migration**

```bash
php artisan make:migration create_sudska_praksa_tables
```

**Step 2: Write migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A "search run" — one execution of the command
        Schema::create('sudska_praksa_searches', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable()->comment('Case/search name from metadata');
            $table->string('keywords_file')->comment('Path to keywords JSON used');
            $table->string('courts')->default('vks,vps,vs,zs');
            $table->integer('total_queries')->default(0);
            $table->integer('ultra_count')->default(0)->comment('Queries with ≤5 results');
            $table->integer('zlato_count')->default(0)->comment('Queries with 6-15 results');
            $table->integer('srebrno_count')->default(0)->comment('Queries with 16-50 results');
            $table->integer('bronca_count')->default(0)->comment('Queries with 51-150 results');
            $table->integer('error_count')->default(0);
            $table->json('metadata')->nullable()->comment('Full metadata from keywords JSON');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // Individual query results within a search run
        Schema::create('sudska_praksa_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('search_id')
                ->constrained('sudska_praksa_searches')
                ->cascadeOnDelete();
            $table->string('category');
            $table->string('category_description')->nullable();
            $table->text('query')->comment('The AND-joined keyword query');
            $table->string('comment')->nullable();
            $table->integer('count')->default(-1)->comment('Number of results, -1 = error');
            $table->string('classification', 20)->default('unknown')
                ->comment('ultra|zlato|srebrno|bronca|bulk|error|empty');
            $table->text('url');
            $table->boolean('is_expanded')->default(false);
            $table->timestamp('fetched_at');
            $table->timestamps();

            // Index for fast lookups
            $table->index(['search_id', 'classification']);
            $table->index(['query', 'count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sudska_praksa_results');
        Schema::dropIfExists('sudska_praksa_searches');
    }
};
```

**Step 3: Run migration**

```bash
php artisan migrate
```

Expected: Both tables created successfully.

**Step 4: Commit**

```bash
git add database/migrations/*create_sudska_praksa_tables*
git commit -m "feat: add sudska_praksa_searches and results tables"
```

---

### Task 3.2: Create Eloquent Models

**Files:**
- Create: `app/Models/SudskaPraksaSearch.php`
- Create: `app/Models/SudskaPraksaResult.php`

**Step 1: Create models**

`app/Models/SudskaPraksaSearch.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SudskaPraksaSearch extends Model
{
    protected $table = 'sudska_praksa_searches';

    protected $fillable = [
        'name', 'keywords_file', 'courts', 'total_queries',
        'ultra_count', 'zlato_count', 'srebrno_count', 'bronca_count',
        'error_count', 'metadata', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(SudskaPraksaResult::class, 'search_id');
    }

    /**
     * Get only high-value results (ultra + zlato)
     */
    public function goldResults(): HasMany
    {
        return $this->results()->whereIn('classification', ['ultra', 'zlato']);
    }

    /**
     * Compute summary stats from results
     */
    public function computeStats(): void
    {
        $results = $this->results;
        $this->total_queries = $results->count();
        $this->ultra_count = $results->where('classification', 'ultra')->count();
        $this->zlato_count = $results->where('classification', 'zlato')->count();
        $this->srebrno_count = $results->where('classification', 'srebrno')->count();
        $this->bronca_count = $results->where('classification', 'bronca')->count();
        $this->error_count = $results->where('classification', 'error')->count();
        $this->save();
    }
}
```

`app/Models/SudskaPraksaResult.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SudskaPraksaResult extends Model
{
    protected $table = 'sudska_praksa_results';

    protected $fillable = [
        'search_id', 'category', 'category_description', 'query',
        'comment', 'count', 'classification', 'url', 'is_expanded',
        'fetched_at',
    ];

    protected $casts = [
        'is_expanded' => 'boolean',
        'fetched_at' => 'datetime',
    ];

    public function search(): BelongsTo
    {
        return $this->belongsTo(SudskaPraksaSearch::class, 'search_id');
    }

    /**
     * Scope: only high-value results
     */
    public function scopeGold($query)
    {
        return $query->whereIn('classification', ['ultra', 'zlato']);
    }

    /**
     * Scope: only results with classification
     */
    public function scopeClassification($query, string $classification)
    {
        return $query->where('classification', $classification);
    }
}
```

**Step 2: Verify models load**

```bash
php artisan tinker --execute="new \App\Models\SudskaPraksaSearch(); echo 'OK';"
```

Expected: `OK`

**Step 3: Commit**

```bash
git add app/Models/SudskaPraksaSearch.php app/Models/SudskaPraksaResult.php
git commit -m "feat: add SudskaPraksa Eloquent models"
```

---

### Task 3.3: Add Persistence to Service

**Files:**
- Modify: `app/Services/SudskaPraksaService.php`
- Create: `tests/Feature/Services/SudskaPraksaServicePersistenceTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Services\SudskaPraksaService;
use App\Models\SudskaPraksaSearch;
use App\Models\SudskaPraksaResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SudskaPraksaServicePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_and_persist_creates_search_and_results(): void
    {
        Http::fake([
            'odluke.sudovi.hr/*' => Http::response(
                '<html><body>8 rezultata</body></html>',
                200
            ),
        ]);

        $service = new SudskaPraksaService();

        $categories = [
            [
                'name' => 'Test Category',
                'description' => 'Test description',
                'queries' => [
                    ['q' => 'test AND query', 'comment' => 'Test'],
                ],
            ],
        ];

        $search = $service->runAndPersist(
            categories: $categories,
            name: 'Test Search',
            keywordsFile: 'test.json',
            delayMs: 0,
        );

        $this->assertInstanceOf(SudskaPraksaSearch::class, $search);
        $this->assertEquals(1, $search->total_queries);
        $this->assertEquals(1, $search->results()->count());

        $result = $search->results->first();
        $this->assertEquals('test AND query', $result->query);
        $this->assertEquals(8, $result->count);
        $this->assertEquals('zlato', $result->classification);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
php artisan test --filter=SudskaPraksaServicePersistenceTest
```

Expected: FAIL — `runAndPersist` method not found.

**Step 3: Add `runAndPersist` method to service**

Add to `SudskaPraksaService.php`:

```php
use App\Models\SudskaPraksaSearch;
use App\Models\SudskaPraksaResult;

/**
 * Run queries and persist results to database
 */
public function runAndPersist(
    array $categories,
    string $name,
    string $keywordsFile,
    ?string $courts = null,
    int $delayMs = 500,
    ?array $metadata = null,
    bool $expand = false,
    ?callable $onProgress = null,
): SudskaPraksaSearch {
    $courts = $courts ?? config('sudska-praksa.default_courts');

    $search = SudskaPraksaSearch::create([
        'name' => $name,
        'keywords_file' => $keywordsFile,
        'courts' => $courts,
        'metadata' => $metadata,
        'started_at' => now(),
    ]);

    $results = $this->runQueries($categories, $courts, $delayMs, $onProgress);

    if ($expand) {
        $expansions = $this->expandQueries($results, $courts, $delayMs);
        $results = array_merge($results, $expansions);
    }

    // Persist each result
    foreach ($results as $r) {
        SudskaPraksaResult::create([
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

    return $search;
}
```

**Step 4: Run test to verify it passes**

```bash
php artisan test --filter=SudskaPraksaServicePersistenceTest
```

Expected: PASS.

**Step 5: Commit**

```bash
git add app/Services/SudskaPraksaService.php tests/Feature/Services/SudskaPraksaServicePersistenceTest.php
git commit -m "feat: add runAndPersist for database storage of search results"
```

---

### Task 3.4: Wire Command to Use Persistence

**Files:**
- Modify: `app/Console/Commands/SudskaPraksaSearch.php`

**Step 1: Add `--persist` option to command signature**

```php
{--persist : Spremi rezultate u bazu podataka}
```

**Step 2: Update handle() to persist when requested**

In the `handle()` method, after running queries:

```php
if ($this->option('persist')) {
    $search = $service->runAndPersist(
        categories: $keywords['categories'] ?? [],
        name: $keywords['metadata']['name'] ?? 'Unnamed Search',
        keywordsFile: $keywordsFile,
        courts: $courts,
        delayMs: $delay,
        metadata: $keywords['metadata'] ?? null,
        expand: (bool) $this->option('expand'),
        onProgress: function ($query, $count, $index, $total) use ($bar) {
            $bar->setMessage(Str::limit($query, 50));
            $bar->advance();
        },
    );

    $bar->finish();
    $this->newLine(2);

    $this->info("💾 Spremljeno u bazu: Search #{$search->id}");
    $this->results = $search->results->toArray();
} else {
    // ... existing non-persistent flow
}
```

**Step 3: Run all tests**

```bash
php artisan test --filter=SudskaPraksa
```

Expected: All tests PASS.

**Step 4: Commit**

```bash
git add app/Console/Commands/SudskaPraksaSearch.php
git commit -m "feat: add --persist option to store results in database"
```

---

## Sprint 4: Scheduling and Trend Detection

> **Objective:** Allow automated periodic runs and detect when result counts change significantly (new case law published).

---

### Task 4.1: Add Scheduled Command

**Files:**
- Modify: `routes/console.php` or `app/Console/Kernel.php` (depending on Laravel version)

**Step 1: Register schedule**

In `routes/console.php` (Laravel 11+):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('sudska-praksa:search', [
    '--persist',
    '--delay' => 1000,   // Be nice to the server
    '--format' => 'json',
])->weekly()->sundays()->at('03:00')
  ->withoutOverlapping()
  ->appendOutputTo(storage_path('logs/sudska-praksa.log'));
```

**Step 2: Verify schedule is registered**

```bash
php artisan schedule:list | grep sudska-praksa
```

Expected: Shows the weekly schedule entry.

**Step 3: Commit**

```bash
git add routes/console.php
git commit -m "feat: schedule weekly sudska-praksa search"
```

---

### Task 4.2: Create Trend Comparison Command

**Files:**
- Create: `app/Console/Commands/SudskaPraksaCompare.php`
- Create: `tests/Feature/Commands/SudskaPraksaCompareTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Commands;

use Tests\TestCase;
use App\Models\SudskaPraksaSearch;
use App\Models\SudskaPraksaResult;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SudskaPraksaCompareTest extends TestCase
{
    use RefreshDatabase;

    public function test_compare_shows_changes_between_two_runs(): void
    {
        // Create two search runs with different counts for same query
        $search1 = SudskaPraksaSearch::create([
            'name' => 'Run 1', 'keywords_file' => 'test.json',
            'courts' => 'vks', 'started_at' => now()->subDay(),
            'finished_at' => now()->subDay(),
        ]);
        SudskaPraksaResult::create([
            'search_id' => $search1->id,
            'category' => 'Test', 'query' => 'pretraga AND doma',
            'count' => 10, 'classification' => 'zlato',
            'url' => 'https://example.com', 'fetched_at' => now()->subDay(),
        ]);

        $search2 = SudskaPraksaSearch::create([
            'name' => 'Run 2', 'keywords_file' => 'test.json',
            'courts' => 'vks', 'started_at' => now(),
            'finished_at' => now(),
        ]);
        SudskaPraksaResult::create([
            'search_id' => $search2->id,
            'category' => 'Test', 'query' => 'pretraga AND doma',
            'count' => 15, 'classification' => 'zlato',
            'url' => 'https://example.com', 'fetched_at' => now(),
        ]);

        $this->artisan('sudska-praksa:compare', [
            'search1' => $search1->id,
            'search2' => $search2->id,
        ])->assertSuccessful();
    }
}
```

**Step 2: Run test to verify it fails**

```bash
php artisan test --filter=SudskaPraksaCompareTest
```

Expected: FAIL — command not found.

**Step 3: Create the compare command**

Create `app/Console/Commands/SudskaPraksaCompare.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SudskaPraksaSearch;

class SudskaPraksaCompare extends Command
{
    protected $signature = 'sudska-praksa:compare
        {search1 : ID prvog searcha}
        {search2 : ID drugog searcha}
        {--threshold=0 : Min razlika za prikaz}';

    protected $description = 'Usporedi dva search runa i pokaži promjene';

    public function handle(): int
    {
        $s1 = SudskaPraksaSearch::with('results')->find($this->argument('search1'));
        $s2 = SudskaPraksaSearch::with('results')->find($this->argument('search2'));

        if (!$s1 || !$s2) {
            $this->error('Search ID not found.');
            return self::FAILURE;
        }

        $threshold = (int) $this->option('threshold');

        $r1 = $s1->results->keyBy('query');
        $r2 = $s2->results->keyBy('query');

        $allQueries = $r1->keys()->merge($r2->keys())->unique();

        $changes = [];
        foreach ($allQueries as $query) {
            $count1 = $r1->get($query)?->count ?? 0;
            $count2 = $r2->get($query)?->count ?? 0;
            $diff = $count2 - $count1;

            if (abs($diff) >= $threshold) {
                $changes[] = [
                    'query' => $query,
                    'before' => $count1,
                    'after' => $count2,
                    'diff' => $diff,
                    'direction' => $diff > 0 ? '📈' : ($diff < 0 ? '📉' : '➡️'),
                ];
            }
        }

        usort($changes, fn($a, $b) => abs($b['diff']) <=> abs($a['diff']));

        $this->info("Usporedba: #{$s1->id} ({$s1->started_at->format('d.m.Y')}) → #{$s2->id} ({$s2->started_at->format('d.m.Y')})");
        $this->newLine();

        $this->table(
            ['Smjer', 'Upit', 'Prije', 'Poslije', 'Razlika'],
            collect($changes)->map(fn($c) => [
                $c['direction'],
                \Illuminate\Support\Str::limit($c['query'], 50),
                $c['before'],
                $c['after'],
                ($c['diff'] > 0 ? '+' : '') . $c['diff'],
            ]),
        );

        $this->info("Ukupno promjena: " . count($changes));

        return self::SUCCESS;
    }
}
```

**Step 4: Run test to verify it passes**

```bash
php artisan test --filter=SudskaPraksaCompareTest
```

Expected: PASS.

**Step 5: Commit**

```bash
git add app/Console/Commands/SudskaPraksaCompare.php tests/Feature/Commands/SudskaPraksaCompareTest.php
git commit -m "feat: add sudska-praksa:compare command for trend detection"
```

---

## Sprint 5: AI Keyword Generation Integration

> **Objective:** Connect the METHODOLOGY.md workflow to AI-powered keyword generation, allowing the system to automatically generate keyword files from case descriptions.

---

### Task 5.1: Create AI Keyword Generator Command

**Files:**
- Create: `app/Console/Commands/SudskaPraksaGenerateKeywords.php`

**Step 1: Create the command**

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class SudskaPraksaGenerateKeywords extends Command
{
    protected $signature = 'sudska-praksa:generate-keywords
        {--case-description= : Opis slučaja (tekst ili putanja do datoteke)}
        {--output=storage/app/keywords/generated.json : Putanja za spremanje}
        {--model=claude-sonnet-4-5-20250929 : AI model za generiranje}';

    protected $description = 'Generiraj keywords JSON datoteku pomoću AI-a prema METHODOLOGY.md';

    public function handle(): int
    {
        $description = $this->option('case-description');

        if (!$description) {
            $description = $this->ask('Opiši slučaj (činjenično stanje, pravni problemi):');
        }

        // If it's a file path, read it
        if (file_exists(base_path($description))) {
            $description = file_get_contents(base_path($description));
        }

        $methodology = file_get_contents(base_path('docs/sudska-praksa/METHODOLOGY.md'));

        $this->info("🤖 Generiram ključne riječi prema metodologiji...");

        $systemPrompt = <<<PROMPT
Ti si stručnjak za pretragu hrvatske sudske prakse na odluke.sudovi.hr.

Slijedi METHODOLOGY.md upute za generiranje ključnih riječi:

{$methodology}

PRAVILA:
- Koristi UVIJEK "AND" operator (nikad OR)
- Optimalna duljina: 2-4 ključne riječi po upitu
- Koristi pravnu terminologiju, ne kolokvijalne izraze
- Koristi infinitiv/nominativ formu
- Uključi članke zakona kad je moguće
- Cilj: ≤50 rezultata po upitu za preciznost

IZLAZ: Vrati SAMO validan JSON u formatu:
{
  "metadata": {
    "name": "...",
    "description": "...",
    "version": "1.0",
    "created_at": "ISO8601"
  },
  "categories": [
    {
      "name": "1. Naziv kategorije",
      "description": "Opis kategorije",
      "queries": [
        { "q": "keyword1 AND keyword2", "comment": "Zašto ovaj upit" }
      ]
    }
  ]
}
PROMPT;

        // This is a placeholder — the actual AI call depends on your
        // integration (Anthropic API, OpenAI, or local model).
        // Adapt the HTTP call to your AI provider:

        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'x-api-key' => config('services.anthropic.key'),
                    'anthropic-version' => '2023-06-01',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $this->option('model'),
                    'max_tokens' => 4096,
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => "Slučaj:\n\n{$description}"],
                    ],
                ]);

            if (!$response->successful()) {
                $this->error("AI API error: " . $response->status());
                return self::FAILURE;
            }

            $content = $response->json('content.0.text');

            // Extract JSON from response (may be wrapped in markdown)
            if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                $json = $matches[0];
            } else {
                $this->error("Nije moguće izdvojiti JSON iz AI odgovora.");
                return self::FAILURE;
            }

            $keywords = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("Neispravan JSON: " . json_last_error_msg());
                return self::FAILURE;
            }

            // Ensure metadata has created_at
            $keywords['metadata']['created_at'] ??= Carbon::now()->toIso8601String();
            $keywords['metadata']['methodology'] = 'METHODOLOGY.md';
            $keywords['metadata']['author'] = '3P Solutions (AI-generated)';

            $outputPath = $this->option('output');
            $dir = dirname(base_path($outputPath));
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            file_put_contents(
                base_path($outputPath),
                json_encode($keywords, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            $queryCount = collect($keywords['categories'])
                ->sum(fn($cat) => count($cat['queries'] ?? []));

            $this->info("✅ Generirano {$queryCount} upita u " . count($keywords['categories']) . " kategorija");
            $this->info("📁 Spremljeno u: {$outputPath}");
            $this->newLine();
            $this->info("Sljedeći korak:");
            $this->line("  php artisan sudska-praksa:search --keywords-file={$outputPath} --persist");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Greška: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
```

**Step 2: Add Anthropic API key to config**

In `config/services.php`, add:

```php
'anthropic' => [
    'key' => env('ANTHROPIC_API_KEY'),
],
```

And to `.env.example`:

```
ANTHROPIC_API_KEY=
```

**Step 3: Commit**

```bash
git add app/Console/Commands/SudskaPraksaGenerateKeywords.php config/services.php .env.example
git commit -m "feat: add AI-powered keyword generation command"
```

---

### Task 5.2: Create Pipeline Command (Generate → Search → Persist)

**Files:**
- Create: `app/Console/Commands/SudskaPraksaPipeline.php`

**Step 1: Create the pipeline command**

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SudskaPraksaPipeline extends Command
{
    protected $signature = 'sudska-praksa:pipeline
        {--case-description= : Opis slučaja}
        {--case-file= : Datoteka s opisom slučaja}
        {--expand : Auto-expand ključne riječi}
        {--courts=vks,vps,vs,zs : Sudovi}';

    protected $description = 'Cijeli pipeline: AI generiranje → pretraga → spremanje';

    public function handle(): int
    {
        $this->info("🚀 Sudska Praksa Pipeline");
        $this->newLine();

        $description = $this->option('case-description')
            ?? $this->option('case-file')
            ?? $this->ask('Opiši slučaj:');

        // Step 1: Generate keywords
        $this->info("━━━ KORAK 1/3: Generiranje ključnih riječi ━━━");
        $keywordsFile = 'storage/app/keywords/pipeline_' . now()->format('Y-m-d_H-i-s') . '.json';

        $exitCode = $this->call('sudska-praksa:generate-keywords', [
            '--case-description' => $description,
            '--output' => $keywordsFile,
        ]);

        if ($exitCode !== self::SUCCESS) {
            $this->error("Generiranje ključnih riječi nije uspjelo.");
            return self::FAILURE;
        }

        // Step 2: Run search with persistence
        $this->newLine();
        $this->info("━━━ KORAK 2/3: Pretraga odluke.sudovi.hr ━━━");

        $searchArgs = [
            '--keywords-file' => $keywordsFile,
            '--persist' => true,
            '--courts' => $this->option('courts'),
            '--format' => 'all',
        ];

        if ($this->option('expand')) {
            $searchArgs['--expand'] = true;
        }

        $exitCode = $this->call('sudska-praksa:search', $searchArgs);

        if ($exitCode !== self::SUCCESS) {
            $this->error("Pretraga nije uspjela.");
            return self::FAILURE;
        }

        // Step 3: Summary
        $this->newLine();
        $this->info("━━━ KORAK 3/3: Sažetak ━━━");
        $this->info("✅ Pipeline završen uspješno!");
        $this->line("   Keywords: {$keywordsFile}");
        $this->line("   Rezultati: storage/app/results/");

        return self::SUCCESS;
    }
}
```

**Step 2: Commit**

```bash
git add app/Console/Commands/SudskaPraksaPipeline.php
git commit -m "feat: add full pipeline command (generate → search → persist)"
```

---

## Sprint 6: Quality and Polish

> **Objective:** Add input validation, rate limiting, retry logic, and comprehensive documentation.

---

### Task 6.1: Add Retry Logic to Service

**Files:**
- Modify: `app/Services/SudskaPraksaService.php`

**Step 1: Add retry config**

In `config/sudska-praksa.php`, add:

```php
'max_retries' => (int) env('SUDSKA_PRAKSA_MAX_RETRIES', 3),
'retry_delay_ms' => (int) env('SUDSKA_PRAKSA_RETRY_DELAY', 2000),
```

**Step 2: Update fetchResultCount with retry**

```php
public function fetchResultCount(string $query, ?string $courts = null): int
{
    $courts = $courts ?? config('sudska-praksa.default_courts');
    $maxRetries = config('sudska-praksa.max_retries', 3);
    $retryDelay = config('sudska-praksa.retry_delay_ms', 2000);

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Accept' => 'text/html',
                    'User-Agent' => $this->userAgent,
                ])
                ->withCookies(['cookieConsent' => '{"type":"accept-all"}'], 'odluke.sudovi.hr')
                ->get($this->baseUrl, [
                    'q' => $query,
                    'sort' => 'rel',
                    'ct' => $courts,
                ]);

            if ($response->successful()) {
                $html = $response->body();

                if (preg_match('/(\d+)\s+rezultat/', $html, $matches)) {
                    return (int) $matches[1];
                }

                if (Str::contains($html, ['Nema rezultata', 'nema rezultata'])) {
                    return 0;
                }
            }

            // On server error, retry
            if ($response->serverError() && $attempt < $maxRetries) {
                usleep($retryDelay * 1000 * $attempt); // Exponential backoff
                continue;
            }

            return -1;
        } catch (\Exception $e) {
            if ($attempt < $maxRetries) {
                usleep($retryDelay * 1000 * $attempt);
                continue;
            }
            return -1;
        }
    }

    return -1;
}
```

**Step 3: Commit**

```bash
git add app/Services/SudskaPraksaService.php config/sudska-praksa.php
git commit -m "feat: add retry logic with exponential backoff"
```

---

### Task 6.2: Add Keywords Validation

**Files:**
- Create: `app/Validators/KeywordsValidator.php`

**Step 1: Create validator**

```php
<?php

namespace App\Validators;

class KeywordsValidator
{
    /**
     * Validate a keywords JSON structure
     *
     * @return array ['valid' => bool, 'errors' => string[]]
     */
    public static function validate(array $data): array
    {
        $errors = [];

        if (!isset($data['categories']) || !is_array($data['categories'])) {
            $errors[] = "Missing or invalid 'categories' array.";
            return ['valid' => false, 'errors' => $errors];
        }

        foreach ($data['categories'] as $i => $category) {
            $catName = $category['name'] ?? "Category #{$i}";

            if (empty($category['name'])) {
                $errors[] = "{$catName}: Missing 'name'.";
            }

            if (!isset($category['queries']) || !is_array($category['queries'])) {
                $errors[] = "{$catName}: Missing or invalid 'queries' array.";
                continue;
            }

            foreach ($category['queries'] as $j => $query) {
                $q = is_string($query) ? $query : ($query['q'] ?? $query['query'] ?? '');

                if (empty($q)) {
                    $errors[] = "{$catName}, query #{$j}: Empty query.";
                    continue;
                }

                // Warn about OR usage (methodology requires AND only)
                if (preg_match('/\bOR\b/', $q)) {
                    $errors[] = "{$catName}, query '{$q}': Contains OR operator. Use AND per methodology.";
                }

                // Warn about too many terms (>6 usually returns 0)
                $terms = preg_split('/\s+AND\s+/', $q);
                if (count($terms) > 6) {
                    $errors[] = "{$catName}, query '{$q}': Too many terms (" . count($terms) . "). Optimal is 2-4.";
                }
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }
}
```

**Step 2: Wire into command**

In `SudskaPraksaSearch.php`, after loading the JSON:

```php
use App\Validators\KeywordsValidator;

// After json_decode:
$validation = KeywordsValidator::validate($keywords);
if (!$validation['valid']) {
    $this->warn("⚠️  Upozorenja u keywords datoteci:");
    foreach ($validation['errors'] as $error) {
        $this->line("   - {$error}");
    }
    if (!$this->confirm('Nastavi unatoč upozorenjima?', true)) {
        return self::FAILURE;
    }
}
```

**Step 3: Commit**

```bash
git add app/Validators/KeywordsValidator.php app/Console/Commands/SudskaPraksaSearch.php
git commit -m "feat: add keywords file validation with methodology rules"
```

---

### Task 6.3: Final Documentation Update

**Files:**
- Modify: `docs/sudska-praksa/README.md`

**Step 1: Update README with full command reference**

Update `docs/sudska-praksa/README.md` to include all new commands:

```markdown
# Sudska Praksa Search — Sustav za pretragu odluke.sudovi.hr

## Komande

| Komanda | Opis |
|---------|------|
| `sudska-praksa:search` | Pretraži odluke.sudovi.hr po ključnim riječima |
| `sudska-praksa:generate-keywords` | AI generiranje keywords JSON datoteke |
| `sudska-praksa:pipeline` | Cijeli pipeline: AI → pretraga → baza |
| `sudska-praksa:compare` | Usporedi dva search runa za trend analizu |

## Quick Start

### 1. Osnovna pretraga
```bash
php artisan sudska-praksa:search
```

### 2. Pretraga s bazom podataka
```bash
php artisan sudska-praksa:search --persist
```

### 3. AI generiranje + pretraga
```bash
php artisan sudska-praksa:pipeline --case-description="Opis slučaja..."
```

### 4. Usporedba dva runa
```bash
php artisan sudska-praksa:compare 1 2
```

## Konfiguracija

Sve postavke u `config/sudska-praksa.php` i `.env`:

| ENV varijabla | Default | Opis |
|---|---|---|
| `SUDSKA_PRAKSA_BASE_URL` | `https://odluke.sudovi.hr/Document/DisplayList` | Base URL |
| `SUDSKA_PRAKSA_COURTS` | `vks,vps,vs,zs` | Default sudovi |
| `SUDSKA_PRAKSA_DELAY` | `500` | Pauza između zahtjeva (ms) |
| `SUDSKA_PRAKSA_TIMEOUT` | `15` | HTTP timeout (s) |
| `SUDSKA_PRAKSA_MAX_RETRIES` | `3` | Broj pokušaja |

## Arhitektura

```
app/
├── Console/Commands/
│   ├── SudskaPraksaSearch.php          # Artisan pretraga
│   ├── SudskaPraksaCompare.php         # Usporedba runova
│   ├── SudskaPraksaGenerateKeywords.php # AI generiranje
│   └── SudskaPraksaPipeline.php        # Full pipeline
├── Models/
│   ├── SudskaPraksaSearch.php          # Search run model
│   └── SudskaPraksaResult.php          # Individual result model
├── Services/
│   └── SudskaPraksaService.php         # Core probing logic
└── Validators/
    └── KeywordsValidator.php           # Keywords JSON validation

config/
└── sudska-praksa.php                   # Configuration

storage/app/
├── keywords/                           # Keywords JSON files
│   └── default.json
└── results/                            # Output files (JSON/CSV/MD)

docs/sudska-praksa/
├── METHODOLOGY.md                      # 6-phase keyword methodology
└── README.md                           # This file
```

## Metodologija

Vidi `METHODOLOGY.md` za detaljnu 6-faznu metodologiju generiranja ključnih riječi.
```

**Step 2: Commit**

```bash
git add docs/sudska-praksa/README.md
git commit -m "docs: update README with full command reference and architecture"
```

---

## Summary

| Sprint | Focus | Tasks | Deliverables |
|--------|-------|-------|-------------|
| **1** | Foundation | 3 tasks | Files placed, config extracted, smoke tests |
| **2** | Service Layer | 2 tasks | `SudskaPraksaService` with full unit tests |
| **3** | Database | 4 tasks | Migrations, models, `--persist` option |
| **4** | Scheduling & Trends | 2 tasks | Weekly schedule, `compare` command |
| **5** | AI Integration | 2 tasks | AI keyword generation, full pipeline |
| **6** | Quality & Polish | 3 tasks | Retries, validation, documentation |

**Total: 16 tasks across 6 sprints**

Each sprint builds on the previous one. Sprint 1 gets the system running as-is, Sprint 2 makes it reusable, Sprint 3 adds persistence, Sprint 4 enables monitoring, Sprint 5 adds AI-powered automation, and Sprint 6 hardens everything for production.
