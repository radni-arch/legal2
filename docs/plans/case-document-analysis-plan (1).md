# Case Document Analysis — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Automatically analyze case documents upon upload/sync — starting with simple extraction layers and progressively building toward AI-powered timeline construction, contradiction detection, and strategic case analysis.

**Architecture:** Event-driven pipeline triggered by document upload completion. Each analysis layer runs as a queued job, writing results to a `case_analyses` table and syncing to Neo4j. Layers are ordered by complexity: deterministic extraction first (cheap, fast), then Claude API analysis (expensive, rich). Each layer depends on the previous layer's output, enabling progressive enrichment.

**Tech Stack:** PHP 8.3 / Laravel 11 / PostgreSQL / Neo4j / Claude API (claude-sonnet-4-5-20250929) / Laravel Queues

---

## Architecture Overview

```
Document Upload/Sync Complete
        │
        ▼
  CaseAnalysisTrigger (Event Listener)
        │
        ▼
  ┌─────────────────────────────────────────────────┐
  │           LAYER 1: Deterministic (Local)         │
  │  Keywords · Entities · Dates · Citations · Stats │
  └──────────────────────┬──────────────────────────┘
                         ▼
  ┌─────────────────────────────────────────────────┐
  │           LAYER 2: Pattern Matching              │
  │  Date Clusters · Cross-doc References · Parties  │
  └──────────────────────┬──────────────────────────┘
                         ▼
  ┌─────────────────────────────────────────────────┐
  │           LAYER 3: AI Analysis (Claude API)      │
  │  Timeline · Summaries · Key Facts · Categories   │
  └──────────────────────┬──────────────────────────┘
                         ▼
  ┌─────────────────────────────────────────────────┐
  │           LAYER 4: AI Deep Analysis (Claude API) │
  │  Contradictions · Gaps · Strategy · Risk         │
  └──────────────────────┬──────────────────────────┘
                         ▼
  ┌─────────────────────────────────────────────────┐
  │           LAYER 5: Cross-Case Intelligence       │
  │  Precedent Links · Judge Patterns · Outcomes     │
  └─────────────────────────────────────────────────┘
```

Each layer writes to `document_analyses` (per-document) or `case_analyses` (cross-document).
Status tracking: `pending → processing → completed → failed`

---

## Sprint 1: Foundation — Trigger + Simple Extraction

**Sprint Goal:** When a document finishes OCR/ingest, automatically kick off analysis. Start with keyword extraction, entity extraction, date extraction — all deterministic, no API calls.

---

### Task 1: Migration — `document_analyses` table

**Files:**
- Create: `database/migrations/YYYY_MM_DD_create_document_analyses_table.php`
- Create: `database/migrations/YYYY_MM_DD_create_case_analyses_table.php`

**Step 1: Create the document-level analysis migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_document_id')->constrained('case_documents')->cascadeOnDelete();
            $table->string('analysis_layer');  // 'extraction', 'pattern', 'ai_basic', 'ai_deep'
            $table->string('analysis_type');   // 'keywords', 'entities', 'dates', 'timeline', 'contradictions', etc.
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->json('results')->nullable();
            $table->json('metadata')->nullable(); // token count, processing time, model used, etc.
            $table->text('error_message')->nullable();
            $table->integer('version')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['case_document_id', 'analysis_type', 'version']);
            $table->index(['status', 'analysis_layer']);
            $table->index(['case_document_id', 'analysis_layer']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_analyses');
    }
};
```

**Step 2: Create the case-level analysis migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('case_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('case_id');         // references your case identifier
            $table->string('analysis_type');    // 'timeline', 'contradictions', 'strategy', 'summary'
            $table->string('status')->default('pending');
            $table->json('results')->nullable();
            $table->json('metadata')->nullable();
            $table->json('document_ids')->nullable(); // which documents contributed
            $table->text('error_message')->nullable();
            $table->integer('version')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['case_id', 'analysis_type', 'version']);
            $table->index(['status', 'analysis_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_analyses');
    }
};
```

**Step 3: Run migration**

```bash
php artisan migrate
```

**Step 4: Commit**

```bash
git add database/migrations/
git commit -m "feat: add document_analyses and case_analyses tables"
```

---

### Task 2: Models — DocumentAnalysis + CaseAnalysis

**Files:**
- Create: `app/Models/DocumentAnalysis.php`
- Create: `app/Models/CaseAnalysis.php`
- Modify: `app/Models/CaseDocument.php` (add relationship)

**Step 1: Create DocumentAnalysis model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAnalysis extends Model
{
    protected $fillable = [
        'case_document_id',
        'analysis_layer',
        'analysis_type',
        'status',
        'results',
        'metadata',
        'error_message',
        'version',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'results' => 'json',
        'metadata' => 'json',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    // Layer constants
    const LAYER_EXTRACTION = 'extraction';
    const LAYER_PATTERN = 'pattern';
    const LAYER_AI_BASIC = 'ai_basic';
    const LAYER_AI_DEEP = 'ai_deep';

    // Type constants
    const TYPE_KEYWORDS = 'keywords';
    const TYPE_ENTITIES = 'entities';
    const TYPE_DATES = 'dates';
    const TYPE_CITATIONS = 'citations';
    const TYPE_STATISTICS = 'statistics';
    const TYPE_TIMELINE = 'timeline';
    const TYPE_SUMMARY = 'summary';
    const TYPE_KEY_FACTS = 'key_facts';
    const TYPE_CONTRADICTIONS = 'contradictions';
    const TYPE_STRATEGY = 'strategy';

    public function caseDocument(): BelongsTo
    {
        return $this->belongsTo(CaseDocument::class);
    }

    public function markProcessing(): self
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
        return $this;
    }

    public function markCompleted(array $results, array $metadata = []): self
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'results' => $results,
            'metadata' => $metadata,
            'completed_at' => now(),
        ]);
        return $this;
    }

    public function markFailed(string $error): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $error,
            'completed_at' => now(),
        ]);
        return $this;
    }

    public function scopeForDocument($query, int $documentId)
    {
        return $query->where('case_document_id', $documentId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('analysis_type', $type);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeLayer($query, string $layer)
    {
        return $query->where('analysis_layer', $layer);
    }
}
```

**Step 2: Create CaseAnalysis model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseAnalysis extends Model
{
    protected $table = 'case_analyses';

    protected $fillable = [
        'case_id',
        'analysis_type',
        'status',
        'results',
        'metadata',
        'document_ids',
        'error_message',
        'version',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'results' => 'json',
        'metadata' => 'json',
        'document_ids' => 'json',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    public function markProcessing(): self
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
        return $this;
    }

    public function markCompleted(array $results, array $metadata = []): self
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'results' => $results,
            'metadata' => $metadata,
            'completed_at' => now(),
        ]);
        return $this;
    }

    public function markFailed(string $error): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $error,
            'completed_at' => now(),
        ]);
        return $this;
    }
}
```

**Step 3: Add relationship to CaseDocument model**

Add to `app/Models/CaseDocument.php`:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function analyses(): HasMany
{
    return $this->hasMany(DocumentAnalysis::class);
}

public function latestAnalysis(string $type): ?DocumentAnalysis
{
    return $this->analyses()
        ->ofType($type)
        ->completed()
        ->orderByDesc('version')
        ->first();
}

public function hasCompletedAnalysis(string $type): bool
{
    return $this->analyses()
        ->ofType($type)
        ->completed()
        ->exists();
}
```

**Step 4: Commit**

```bash
git add app/Models/DocumentAnalysis.php app/Models/CaseAnalysis.php app/Models/CaseDocument.php
git commit -m "feat: add DocumentAnalysis and CaseAnalysis models"
```

---

### Task 3: Event + Listener — Auto-trigger on document ingest

**Files:**
- Create: `app/Events/CaseDocumentIngested.php`
- Create: `app/Listeners/TriggerDocumentAnalysis.php`
- Modify: `app/Providers/EventServiceProvider.php`

**Step 1: Create the event**

```php
<?php

namespace App\Events;

use App\Models\CaseDocument;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CaseDocumentIngested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CaseDocument $caseDocument,
        public string $caseId,
    ) {}
}
```

**Step 2: Create the listener**

```php
<?php

namespace App\Listeners;

use App\Events\CaseDocumentIngested;
use App\Jobs\Analysis\RunDocumentExtractionJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class TriggerDocumentAnalysis implements ShouldQueue
{
    public string $queue = 'analysis';

    public function handle(CaseDocumentIngested $event): void
    {
        // Dispatch Layer 1 (deterministic extraction) immediately
        RunDocumentExtractionJob::dispatch($event->caseDocument, $event->caseId);
    }
}
```

**Step 3: Register in EventServiceProvider**

Add to `$listen` array in `app/Providers/EventServiceProvider.php`:

```php
\App\Events\CaseDocumentIngested::class => [
    \App\Listeners\TriggerDocumentAnalysis::class,
],
```

**Step 4: Fire the event from the ingest pipeline**

Add at the end of `CaseIngestPipeline::ingest()` (or wherever document persistence completes):

```php
use App\Events\CaseDocumentIngested;

// After document is saved/persisted:
CaseDocumentIngested::dispatch($caseDocument, $caseId);
```

**Step 5: Commit**

```bash
git add app/Events/ app/Listeners/ app/Providers/EventServiceProvider.php
git commit -m "feat: auto-trigger analysis on document ingest"
```

---

### Task 4: Layer 1 Analyzers — Keyword Extraction

**Files:**
- Create: `app/Services/Analysis/Analyzers/KeywordAnalyzer.php`
- Create: `app/Services/Analysis/Contracts/DocumentAnalyzerInterface.php`

**Step 1: Create the analyzer contract**

```php
<?php

namespace App\Services\Analysis\Contracts;

use App\Models\CaseDocument;

interface DocumentAnalyzerInterface
{
    /**
     * @return array{results: array, metadata: array}
     */
    public function analyze(CaseDocument $document, string $text): array;

    public function type(): string;

    public function layer(): string;
}
```

**Step 2: Create KeywordAnalyzer (deterministic, no API calls)**

```php
<?php

namespace App\Services\Analysis\Analyzers;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;

class KeywordAnalyzer implements DocumentAnalyzerInterface
{
    /**
     * Croatian legal stop words to exclude from keyword extraction.
     */
    private const STOP_WORDS_HR = [
        'i', 'u', 'je', 'da', 'na', 'se', 'za', 'su', 'od', 'te', 'bi',
        'sa', 'po', 'ali', 'ili', 'kao', 'koje', 'koji', 'koja', 'nije',
        'bio', 'bila', 'bilo', 'biti', 'ne', 'do', 'iz', 'tog', 'toga',
        'tom', 'taj', 'ta', 'to', 'što', 'šta', 'sve', 'već', 'još',
        'samo', 'tek', 'čl', 'st', 'toč', 'stavak', 'stavka', 'članak',
        'članka', 'zakona', 'prema', 'nakon', 'prije', 'tijekom',
        'između', 'protiv', 'zbog', 'radi', 'ovaj', 'ova', 'ovo',
        'ovog', 'ovom', 'ovim', 'može', 'mogu', 'treba', 'ima', 'imati',
        'kada', 'kako', 'gdje', 'dok', 'jer', 'ako', 'tako', 'više',
        'manje', 'broj', 'dana', 'dan', 'mjesec', 'godina', 'str',
    ];

    /**
     * Croatian legal domain keywords get a boost.
     */
    private const LEGAL_DOMAIN_BOOST = [
        'optužen' => 2.0, 'okrivljen' => 2.0, 'oštećen' => 2.0,
        'presud' => 2.0, 'rješenj' => 2.0, 'žalb' => 2.0,
        'dokaz' => 2.5, 'svjedok' => 2.0, 'vještak' => 2.0,
        'pretres' => 2.0, 'pretraga' => 2.5, 'uhićenj' => 2.0,
        'pritvor' => 2.0, 'kazneno' => 1.5, 'prekršaj' => 1.5,
        'tužitelj' => 2.0, 'branitelj' => 2.0, 'sud' => 1.5,
        'nezakonit' => 3.0, 'izdvajan' => 3.0, 'ništav' => 2.5,
        'nalog' => 2.0, 'zapljen' => 2.0,
    ];

    public function type(): string
    {
        return DocumentAnalysis::TYPE_KEYWORDS;
    }

    public function layer(): string
    {
        return DocumentAnalysis::LAYER_EXTRACTION;
    }

    public function analyze(CaseDocument $document, string $text): array
    {
        $startTime = microtime(true);

        // Tokenize: lowercase, split on non-word chars, filter
        $words = $this->tokenize($text);
        $totalWords = count($words);

        // Calculate TF (term frequency)
        $tf = array_count_values($words);

        // Remove stop words
        foreach (self::STOP_WORDS_HR as $stop) {
            unset($tf[$stop]);
        }

        // Remove short words (< 3 chars) and pure numbers
        $tf = array_filter($tf, function ($count, $word) {
            return mb_strlen($word) >= 3 && !is_numeric($word);
        }, ARRAY_FILTER_USE_BOTH);

        // Score: TF normalized + domain boost
        $scored = [];
        foreach ($tf as $word => $count) {
            $tfScore = $count / max($totalWords, 1);
            $boost = $this->getDomainBoost($word);
            $scored[$word] = [
                'term' => $word,
                'count' => $count,
                'tf_score' => round($tfScore, 6),
                'boost' => $boost,
                'final_score' => round($tfScore * $boost, 6),
            ];
        }

        // Sort by final_score descending
        uasort($scored, fn($a, $b) => $b['final_score'] <=> $a['final_score']);

        // Top 50 keywords
        $topKeywords = array_slice(array_values($scored), 0, 50);

        // Extract bigrams (two-word phrases)
        $bigrams = $this->extractBigrams($words);

        $processingTime = round(microtime(true) - $startTime, 4);

        return [
            'results' => [
                'keywords' => $topKeywords,
                'bigrams' => array_slice($bigrams, 0, 30),
                'total_unique_terms' => count($tf),
                'total_words' => $totalWords,
            ],
            'metadata' => [
                'processing_time_seconds' => $processingTime,
                'analyzer' => 'KeywordAnalyzer',
                'api_calls' => 0,
                'cost' => 0,
            ],
        ];
    }

    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        // Split on non-letter/non-Croatian-diacritic chars
        $words = preg_split('/[^a-zA-ZčćžšđČĆŽŠĐ]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return $words ?: [];
    }

    private function getDomainBoost(string $word): float
    {
        foreach (self::LEGAL_DOMAIN_BOOST as $stem => $boost) {
            if (str_starts_with($word, $stem)) {
                return $boost;
            }
        }
        return 1.0;
    }

    private function extractBigrams(array $words): array
    {
        $stopSet = array_flip(self::STOP_WORDS_HR);
        $bigrams = [];

        for ($i = 0; $i < count($words) - 1; $i++) {
            $w1 = $words[$i];
            $w2 = $words[$i + 1];

            // Skip if either word is a stop word or too short
            if (isset($stopSet[$w1]) || isset($stopSet[$w2])) continue;
            if (mb_strlen($w1) < 3 || mb_strlen($w2) < 3) continue;

            $bigram = "$w1 $w2";
            $bigrams[$bigram] = ($bigrams[$bigram] ?? 0) + 1;
        }

        arsort($bigrams);

        return array_map(fn($phrase, $count) => [
            'phrase' => $phrase,
            'count' => $count,
        ], array_keys($bigrams), array_values($bigrams));
    }
}
```

**Step 3: Commit**

```bash
git add app/Services/Analysis/
git commit -m "feat: keyword analyzer with Croatian legal domain boosting"
```

---

### Task 5: Layer 1 Analyzers — Date Extraction

**Files:**
- Create: `app/Services/Analysis/Analyzers/DateExtractor.php`

**Step 1: Create DateExtractor**

```php
<?php

namespace App\Services\Analysis\Analyzers;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;
use Carbon\Carbon;

class DateExtractor implements DocumentAnalyzerInterface
{
    /**
     * Croatian month names for parsing.
     */
    private const MONTHS_HR = [
        'siječnja' => 1, 'siječanj' => 1, 'januar' => 1,
        'veljače' => 2, 'veljača' => 2, 'februar' => 2,
        'ožujka' => 3, 'ožujak' => 3, 'mart' => 3,
        'travnja' => 4, 'travanj' => 4, 'april' => 4,
        'svibnja' => 5, 'svibanj' => 5, 'maj' => 5,
        'lipnja' => 6, 'lipanj' => 6, 'juni' => 6,
        'srpnja' => 7, 'srpanj' => 7, 'juli' => 7,
        'kolovoza' => 8, 'kolovoz' => 8, 'august' => 8,
        'rujna' => 9, 'rujan' => 9, 'septembar' => 9,
        'listopada' => 10, 'listopad' => 10, 'oktobar' => 10,
        'studenoga' => 11, 'studeni' => 11, 'novembar' => 11,
        'prosinca' => 12, 'prosinac' => 12, 'decembar' => 12,
    ];

    public function type(): string
    {
        return DocumentAnalysis::TYPE_DATES;
    }

    public function layer(): string
    {
        return DocumentAnalysis::LAYER_EXTRACTION;
    }

    public function analyze(CaseDocument $document, string $text): array
    {
        $startTime = microtime(true);
        $dates = [];

        // Pattern 1: "15. siječnja 2024." or "15. siječnja 2024. godine"
        preg_match_all(
            '/(\d{1,2})\.\s*(' . implode('|', array_keys(self::MONTHS_HR)) . ')\s*(\d{4})\.?\s*(?:godine)?/ui',
            $text,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($matches as $m) {
            $day = (int)$m[1][0];
            $monthName = mb_strtolower($m[2][0]);
            $month = self::MONTHS_HR[$monthName] ?? null;
            $year = (int)$m[3][0];
            $offset = $m[0][1];

            if ($month && checkdate($month, $day, $year)) {
                $dates[] = $this->buildDateEntry(
                    Carbon::create($year, $month, $day),
                    $m[0][0],
                    $offset,
                    $text,
                    'croatian_long'
                );
            }
        }

        // Pattern 2: "15.01.2024." or "15.01.2024" (DD.MM.YYYY)
        preg_match_all(
            '/(\d{1,2})\.(\d{1,2})\.(\d{4})\.?/u',
            $text,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($matches as $m) {
            $day = (int)$m[1][0];
            $month = (int)$m[2][0];
            $year = (int)$m[3][0];
            $offset = $m[0][1];

            if ($month >= 1 && $month <= 12 && checkdate($month, $day, $year) && $year >= 1990 && $year <= 2030) {
                $dateStr = Carbon::create($year, $month, $day)->toDateString();
                // Avoid duplicates from pattern 1
                if (!$this->dateAlreadyFound($dates, $dateStr)) {
                    $dates[] = $this->buildDateEntry(
                        Carbon::create($year, $month, $day),
                        $m[0][0],
                        $offset,
                        $text,
                        'dd_mm_yyyy'
                    );
                }
            }
        }

        // Pattern 3: ISO format "2024-01-15"
        preg_match_all(
            '/(\d{4})-(\d{2})-(\d{2})/u',
            $text,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($matches as $m) {
            $year = (int)$m[1][0];
            $month = (int)$m[2][0];
            $day = (int)$m[3][0];
            $offset = $m[0][1];

            if (checkdate($month, $day, $year) && $year >= 1990 && $year <= 2030) {
                $dateStr = Carbon::create($year, $month, $day)->toDateString();
                if (!$this->dateAlreadyFound($dates, $dateStr)) {
                    $dates[] = $this->buildDateEntry(
                        Carbon::create($year, $month, $day),
                        $m[0][0],
                        $offset,
                        $text,
                        'iso'
                    );
                }
            }
        }

        // Sort by date ascending
        usort($dates, fn($a, $b) => $a['date'] <=> $b['date']);

        // Determine date range
        $dateRange = null;
        if (count($dates) >= 2) {
            $dateRange = [
                'earliest' => $dates[0]['date'],
                'latest' => end($dates)['date'],
                'span_days' => Carbon::parse($dates[0]['date'])->diffInDays(Carbon::parse(end($dates)['date'])),
            ];
        }

        return [
            'results' => [
                'dates' => $dates,
                'date_count' => count($dates),
                'unique_dates' => count(array_unique(array_column($dates, 'date'))),
                'date_range' => $dateRange,
            ],
            'metadata' => [
                'processing_time_seconds' => round(microtime(true) - $startTime, 4),
                'analyzer' => 'DateExtractor',
                'api_calls' => 0,
                'cost' => 0,
            ],
        ];
    }

    private function buildDateEntry(Carbon $date, string $raw, int $offset, string $text, string $format): array
    {
        // Extract surrounding context (±100 chars)
        $contextStart = max(0, $offset - 100);
        $contextLen = min(mb_strlen($text) - $contextStart, 200 + mb_strlen($raw));
        $context = mb_substr($text, $contextStart, $contextLen);

        return [
            'date' => $date->toDateString(),
            'raw_match' => trim($raw),
            'format_detected' => $format,
            'context' => trim(preg_replace('/\s+/', ' ', $context)),
            'position' => $offset,
        ];
    }

    private function dateAlreadyFound(array $dates, string $dateStr): bool
    {
        foreach ($dates as $d) {
            if ($d['date'] === $dateStr) return true;
        }
        return false;
    }
}
```

**Step 2: Commit**

```bash
git add app/Services/Analysis/Analyzers/DateExtractor.php
git commit -m "feat: date extractor with Croatian date format support"
```

---

### Task 6: Layer 1 Analyzers — Entity Extraction

**Files:**
- Create: `app/Services/Analysis/Analyzers/EntityExtractor.php`

**Step 1: Create EntityExtractor**

This reuses patterns from your existing `HrLegalCitationsDetector`, `CourtDetector`, `PartyDetector` but outputs in the unified analysis format.

```php
<?php

namespace App\Services\Analysis\Analyzers;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;

class EntityExtractor implements DocumentAnalyzerInterface
{
    public function type(): string
    {
        return DocumentAnalysis::TYPE_ENTITIES;
    }

    public function layer(): string
    {
        return DocumentAnalysis::LAYER_EXTRACTION;
    }

    public function analyze(CaseDocument $document, string $text): array
    {
        $startTime = microtime(true);

        $entities = [
            'case_numbers' => $this->extractCaseNumbers($text),
            'courts' => $this->extractCourts($text),
            'laws' => $this->extractLaws($text),
            'narodne_novine' => $this->extractNarodneNovine($text),
            'persons' => $this->extractPersonReferences($text),
            'institutions' => $this->extractInstitutions($text),
            'monetary_amounts' => $this->extractMonetaryAmounts($text),
        ];

        $totalEntities = array_sum(array_map('count', $entities));

        return [
            'results' => [
                'entities' => $entities,
                'total_entities' => $totalEntities,
                'entity_density' => round($totalEntities / max(str_word_count($text), 1) * 100, 2),
            ],
            'metadata' => [
                'processing_time_seconds' => round(microtime(true) - $startTime, 4),
                'analyzer' => 'EntityExtractor',
                'api_calls' => 0,
                'cost' => 0,
            ],
        ];
    }

    private function extractCaseNumbers(string $text): array
    {
        $caseNumbers = [];

        // Patterns: K-123/2024, Kž-456/23, Pp Prz-789/2024, I Kž Us 12/2023-5
        $patterns = [
            '/(?:I+\s+)?(?:K[ržo]?|Pp\s*Prz|Gž|Pn|Us|Kž\s*Us|Kov)[\s-]*\d+\/\d{2,4}(?:-\d+)?/ui',
            '/ECLI:[A-Z]{2}:[A-Z0-9.]+:\d{4}:\d+/i',
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            foreach ($matches[0] as $match) {
                $normalized = trim(preg_replace('/\s+/', ' ', $match));
                $caseNumbers[$normalized] = ($caseNumbers[$normalized] ?? 0) + 1;
            }
        }

        return array_map(fn($num, $count) => [
            'case_number' => $num,
            'mentions' => $count,
        ], array_keys($caseNumbers), array_values($caseNumbers));
    }

    private function extractCourts(string $text): array
    {
        $courts = [];
        $patterns = [
            '/(?:Općinski|Županijski|Vrhovni|Ustavni|Trgovački|Upravni|Visoki\s+(?:prekršajni|trgovački|upravni))\s+sud\s+u\s+[\wčćžšđČĆŽŠĐ]+/ui',
            '/(?:DORH|USKOK|VSRH|VTSRH)/i',
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            foreach ($matches[0] as $match) {
                $normalized = trim($match);
                $courts[$normalized] = ($courts[$normalized] ?? 0) + 1;
            }
        }

        return array_map(fn($court, $count) => [
            'court' => $court,
            'mentions' => $count,
        ], array_keys($courts), array_values($courts));
    }

    private function extractLaws(string $text): array
    {
        $laws = [];

        // "Zakon o kaznenom postupku", "Kazneni zakon", etc.
        preg_match_all(
            '/(?:Zakon\s+o\s+[\wčćžšđČĆŽŠĐ\s]{3,50}|Kazneni\s+zakon|Ustav\s+(?:Republike\s+)?Hrvatske|Ovršni\s+zakon|Prekršajni\s+zakon)/ui',
            $text,
            $matches
        );

        foreach ($matches[0] as $match) {
            $normalized = trim(preg_replace('/\s+/', ' ', $match));
            $laws[$normalized] = ($laws[$normalized] ?? 0) + 1;
        }

        // Also catch abbreviations: ZKP, KZ, OZ, ZPP
        preg_match_all('/\b(ZKP|KZ|OZ|ZPP|ZUSKOK|ZOS|ZOPNPP)\b/', $text, $abbrevMatches);
        foreach ($abbrevMatches[0] as $match) {
            $laws[$match] = ($laws[$match] ?? 0) + 1;
        }

        return array_map(fn($law, $count) => [
            'law' => $law,
            'mentions' => $count,
        ], array_keys($laws), array_values($laws));
    }

    private function extractNarodneNovine(string $text): array
    {
        $refs = [];
        // NN 152/08, NN br. 152/08, Narodne novine 152/08
        preg_match_all(
            '/(?:NN|Narodne\s+novine)\s*(?:br\.?\s*)?(\d+\/\d{2,4}(?:\.\s*(?:i\s+)?\d+\/\d{2,4})*)/ui',
            $text,
            $matches
        );

        foreach ($matches[0] as $match) {
            $normalized = trim($match);
            $refs[$normalized] = ($refs[$normalized] ?? 0) + 1;
        }

        return array_map(fn($ref, $count) => [
            'reference' => $ref,
            'mentions' => $count,
        ], array_keys($refs), array_values($refs));
    }

    private function extractPersonReferences(string $text): array
    {
        $persons = [];

        // "okrivljenik", "oštećenik", "svjedok", "vještak" + potential name patterns
        $rolePatterns = [
            'okrivljenik' => '/okrivljenik[a-z]*/ui',
            'oštećenik' => '/oštećenik[a-z]*/ui',
            'svjedok' => '/svjedok[a-z]*/ui',
            'vještak' => '/vještak[a-z]*/ui',
            'tužitelj' => '/(?:državni\s+)?tužitelj[a-z]*/ui',
            'branitelj' => '/branitelj[a-z]*/ui',
            'sudac' => '/(?:sudac|sutkinja|predsjednik[a-z]*\s+vijeća)/ui',
        ];

        foreach ($rolePatterns as $role => $pattern) {
            preg_match_all($pattern, $text, $matches);
            if (!empty($matches[0])) {
                $persons[] = [
                    'role' => $role,
                    'mentions' => count($matches[0]),
                ];
            }
        }

        return $persons;
    }

    private function extractInstitutions(string $text): array
    {
        $institutions = [];
        $patterns = [
            '/(?:MUP|Ministarstvo\s+unutarnjih\s+poslova)/ui',
            '/(?:Policijska\s+(?:uprava|postaja)\s+[\wčćžšđ]+)/ui',
            '/(?:Zatvor\s+u?\s*[\wčćžšđ]+|Kaznionica\s+u?\s*[\wčćžšđ]+)/ui',
            '/(?:DORH|Državno\s+odvjetništvo)/ui',
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            foreach ($matches[0] as $match) {
                $normalized = trim($match);
                $institutions[$normalized] = ($institutions[$normalized] ?? 0) + 1;
            }
        }

        return array_map(fn($inst, $count) => [
            'institution' => $inst,
            'mentions' => $count,
        ], array_keys($institutions), array_values($institutions));
    }

    private function extractMonetaryAmounts(string $text): array
    {
        $amounts = [];

        // "1.500,00 kuna", "1.500,00 EUR", "€1,500.00"
        preg_match_all(
            '/(\d{1,3}(?:\.\d{3})*(?:,\d{2})?)\s*(kuna|kn|HRK|EUR|eura|€)/ui',
            $text,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $m) {
            $amounts[] = [
                'raw' => $m[0],
                'amount' => $m[1],
                'currency' => $m[2],
            ];
        }

        return $amounts;
    }
}
```

**Step 2: Commit**

```bash
git add app/Services/Analysis/Analyzers/EntityExtractor.php
git commit -m "feat: entity extractor for Croatian legal documents"
```

---

### Task 7: Layer 1 Analyzers — Document Statistics

**Files:**
- Create: `app/Services/Analysis/Analyzers/DocumentStatisticsAnalyzer.php`

**Step 1: Create DocumentStatisticsAnalyzer**

```php
<?php

namespace App\Services\Analysis\Analyzers;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;

class DocumentStatisticsAnalyzer implements DocumentAnalyzerInterface
{
    public function type(): string
    {
        return DocumentAnalysis::TYPE_STATISTICS;
    }

    public function layer(): string
    {
        return DocumentAnalysis::LAYER_EXTRACTION;
    }

    public function analyze(CaseDocument $document, string $text): array
    {
        $startTime = microtime(true);

        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentences = preg_split('/[.!?]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $paragraphs = preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $lines = explode("\n", $text);

        // Language detection heuristic (Croatian vs other)
        $croatianIndicators = preg_match_all('/[čćžšđČĆŽŠĐ]/u', $text);
        $totalChars = mb_strlen($text);
        $croatianDensity = $totalChars > 0 ? round($croatianIndicators / $totalChars * 100, 3) : 0;

        // Reading time estimate (avg 200 words/min for legal text)
        $readingTimeMinutes = round(count($words) / 200, 1);

        return [
            'results' => [
                'word_count' => count($words),
                'sentence_count' => count($sentences),
                'paragraph_count' => count($paragraphs),
                'line_count' => count($lines),
                'character_count' => $totalChars,
                'avg_sentence_length' => count($sentences) > 0
                    ? round(count($words) / count($sentences), 1)
                    : 0,
                'avg_paragraph_length' => count($paragraphs) > 0
                    ? round(count($words) / count($paragraphs), 1)
                    : 0,
                'croatian_diacritic_density' => $croatianDensity,
                'estimated_reading_time_minutes' => $readingTimeMinutes,
                'estimated_page_count' => max(1, round(count($words) / 300)), // ~300 words/page for legal docs
            ],
            'metadata' => [
                'processing_time_seconds' => round(microtime(true) - $startTime, 4),
                'analyzer' => 'DocumentStatisticsAnalyzer',
                'api_calls' => 0,
                'cost' => 0,
            ],
        ];
    }
}
```

**Step 2: Commit**

```bash
git add app/Services/Analysis/Analyzers/DocumentStatisticsAnalyzer.php
git commit -m "feat: document statistics analyzer"
```

---

### Task 8: Analysis Pipeline Orchestrator

**Files:**
- Create: `app/Services/Analysis/DocumentAnalysisPipeline.php`
- Create: `app/Jobs/Analysis/RunDocumentExtractionJob.php`

**Step 1: Create the pipeline orchestrator**

```php
<?php

namespace App\Services\Analysis;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;
use App\Services\Analysis\Analyzers\KeywordAnalyzer;
use App\Services\Analysis\Analyzers\DateExtractor;
use App\Services\Analysis\Analyzers\EntityExtractor;
use App\Services\Analysis\Analyzers\DocumentStatisticsAnalyzer;
use Illuminate\Support\Facades\Log;

class DocumentAnalysisPipeline
{
    /**
     * Registry of analyzers per layer.
     * Order matters — analyzers run sequentially within a layer.
     */
    private function getAnalyzersForLayer(string $layer): array
    {
        return match ($layer) {
            DocumentAnalysis::LAYER_EXTRACTION => [
                new DocumentStatisticsAnalyzer(),
                new KeywordAnalyzer(),
                new DateExtractor(),
                new EntityExtractor(),
            ],
            DocumentAnalysis::LAYER_PATTERN => [
                // Sprint 2: CrossDocReferenceAnalyzer, DateClusterAnalyzer
            ],
            DocumentAnalysis::LAYER_AI_BASIC => [
                // Sprint 3: TimelineAnalyzer, SummaryAnalyzer, KeyFactsAnalyzer
            ],
            DocumentAnalysis::LAYER_AI_DEEP => [
                // Sprint 4: ContradictionAnalyzer, StrategyAnalyzer, RiskAnalyzer
            ],
            default => [],
        };
    }

    /**
     * Run all analyzers for a given layer on a document.
     */
    public function runLayer(CaseDocument $document, string $layer): array
    {
        $text = $this->getDocumentText($document);

        if (empty($text)) {
            Log::warning("DocumentAnalysisPipeline: No text for document {$document->id}");
            return [];
        }

        $analyzers = $this->getAnalyzersForLayer($layer);
        $results = [];

        foreach ($analyzers as $analyzer) {
            /** @var DocumentAnalyzerInterface $analyzer */
            try {
                $results[] = $this->runAnalyzer($analyzer, $document, $text);
            } catch (\Throwable $e) {
                Log::error("Analyzer {$analyzer->type()} failed for document {$document->id}: {$e->getMessage()}");

                // Record failure but continue with other analyzers
                $analysis = $this->getOrCreateAnalysis($document, $analyzer);
                $analysis->markFailed($e->getMessage());
                $results[] = $analysis;
            }
        }

        return $results;
    }

    /**
     * Run a single analyzer and persist results.
     */
    private function runAnalyzer(
        DocumentAnalyzerInterface $analyzer,
        CaseDocument $document,
        string $text
    ): DocumentAnalysis {
        $analysis = $this->getOrCreateAnalysis($document, $analyzer);
        $analysis->markProcessing();

        $result = $analyzer->analyze($document, $text);

        $analysis->markCompleted(
            $result['results'],
            $result['metadata']
        );

        Log::info("Analysis completed: {$analyzer->type()} for document {$document->id}", [
            'processing_time' => $result['metadata']['processing_time_seconds'] ?? null,
        ]);

        return $analysis;
    }

    private function getOrCreateAnalysis(
        CaseDocument $document,
        DocumentAnalyzerInterface $analyzer
    ): DocumentAnalysis {
        return DocumentAnalysis::updateOrCreate(
            [
                'case_document_id' => $document->id,
                'analysis_type' => $analyzer->type(),
                'version' => 1, // For now, always version 1
            ],
            [
                'analysis_layer' => $analyzer->layer(),
                'status' => DocumentAnalysis::STATUS_PENDING,
            ]
        );
    }

    private function getDocumentText(CaseDocument $document): string
    {
        // Adapt this to your actual text storage:
        // Option A: extracted_content on the document itself
        // Option B: from related TextractJob
        // Option C: concatenate chunks from case_document_chunks

        if (!empty($document->content)) {
            return $document->content;
        }

        // Fallback: concatenate chunks if they exist
        if ($document->chunks && $document->chunks->isNotEmpty()) {
            return $document->chunks->pluck('content')->implode("\n\n");
        }

        // Fallback: from related TextractJob
        if ($document->textractJob && !empty($document->textractJob->extracted_content)) {
            return $document->textractJob->extracted_content;
        }

        return '';
    }
}
```

**Step 2: Create the queued job**

```php
<?php

namespace App\Jobs\Analysis;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\DocumentAnalysisPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunDocumentExtractionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public CaseDocument $caseDocument,
        public string $caseId,
    ) {
        $this->queue = 'analysis';
    }

    public function handle(DocumentAnalysisPipeline $pipeline): void
    {
        Log::info("Starting Layer 1 analysis for document {$this->caseDocument->id} in case {$this->caseId}");

        // Run Layer 1: Deterministic extraction
        $results = $pipeline->runLayer($this->caseDocument, DocumentAnalysis::LAYER_EXTRACTION);

        $completedCount = collect($results)
            ->filter(fn($r) => $r->status === DocumentAnalysis::STATUS_COMPLETED)
            ->count();

        Log::info("Layer 1 complete for document {$this->caseDocument->id}: {$completedCount}/" . count($results) . " analyzers succeeded");

        // Chain: dispatch Layer 2 (pattern matching) after Layer 1 completes
        // Uncomment when Sprint 2 is ready:
        // RunPatternAnalysisJob::dispatch($this->caseDocument, $this->caseId);
    }
}
```

**Step 3: Commit**

```bash
git add app/Services/Analysis/DocumentAnalysisPipeline.php app/Jobs/Analysis/
git commit -m "feat: analysis pipeline orchestrator with queued job"
```

---

### Task 9: Artisan Command — Manual trigger + status check

**Files:**
- Create: `app/Console/Commands/AnalyzeCaseDocumentsCommand.php`

**Step 1: Create the command**

```php
<?php

namespace App\Console\Commands;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Jobs\Analysis\RunDocumentExtractionJob;
use Illuminate\Console\Command;

class AnalyzeCaseDocumentsCommand extends Command
{
    protected $signature = 'case:analyze
        {case_id : The case identifier}
        {--document= : Specific document ID (optional)}
        {--rerun : Force re-analysis even if already completed}
        {--status : Show analysis status instead of running}';

    protected $description = 'Run document analysis pipeline on case documents';

    public function handle(): int
    {
        $caseId = $this->argument('case_id');

        if ($this->option('status')) {
            return $this->showStatus($caseId);
        }

        $query = CaseDocument::where('case_id', $caseId);

        if ($documentId = $this->option('document')) {
            $query->where('id', $documentId);
        }

        $documents = $query->get();

        if ($documents->isEmpty()) {
            $this->error("No documents found for case {$caseId}");
            return 1;
        }

        $this->info("Dispatching analysis for {$documents->count()} document(s) in case {$caseId}");

        foreach ($documents as $document) {
            if (!$this->option('rerun') && $document->hasCompletedAnalysis(DocumentAnalysis::TYPE_KEYWORDS)) {
                $this->line("  Skipping document {$document->id} (already analyzed). Use --rerun to force.");
                continue;
            }

            RunDocumentExtractionJob::dispatch($document, $caseId);
            $this->info("  Dispatched analysis for document {$document->id}");
        }

        $this->info('Done. Jobs dispatched to the "analysis" queue.');
        $this->line('Run: php artisan queue:work --queue=analysis');

        return 0;
    }

    private function showStatus(string $caseId): int
    {
        $documents = CaseDocument::where('case_id', $caseId)
            ->with('analyses')
            ->get();

        if ($documents->isEmpty()) {
            $this->error("No documents found for case {$caseId}");
            return 1;
        }

        $this->info("Analysis status for case {$caseId}:");
        $this->newLine();

        foreach ($documents as $document) {
            $this->line("Document #{$document->id}: " . ($document->title ?? $document->filename ?? 'untitled'));

            if ($document->analyses->isEmpty()) {
                $this->line("  ⏳ No analyses yet");
                continue;
            }

            foreach ($document->analyses as $analysis) {
                $icon = match($analysis->status) {
                    'completed' => '✅',
                    'processing' => '🔄',
                    'failed' => '❌',
                    default => '⏳',
                };

                $time = $analysis->metadata['processing_time_seconds'] ?? '?';
                $this->line("  {$icon} {$analysis->analysis_type} [{$analysis->analysis_layer}] — {$analysis->status} ({$time}s)");

                if ($analysis->status === 'failed') {
                    $this->line("     Error: {$analysis->error_message}");
                }
            }

            $this->newLine();
        }

        return 0;
    }
}
```

**Step 2: Commit**

```bash
git add app/Console/Commands/AnalyzeCaseDocumentsCommand.php
git commit -m "feat: artisan command for manual analysis trigger and status"
```

---

## Sprint 2: Pattern Matching + Cross-Document Analysis

**Sprint Goal:** Aggregate Layer 1 results across documents within a case. Detect date clusters (events), cross-references between documents, and build a party/role map.

---

### Task 10: Cross-Document Date Clustering

**Files:**
- Create: `app/Services/Analysis/CaseLevel/DateClusterAnalyzer.php`
- Create: `app/Jobs/Analysis/RunCaseLevelAnalysisJob.php`

**Purpose:** Takes all extracted dates from all documents in a case, clusters them by proximity (±3 days), and identifies key events/periods. This is the foundation for the AI timeline builder in Sprint 3.

```php
<?php

namespace App\Services\Analysis\CaseLevel;

use App\Models\CaseAnalysis;
use App\Models\DocumentAnalysis;
use Carbon\Carbon;

class DateClusterAnalyzer
{
    private int $clusterGapDays = 3;

    public function analyze(string $caseId): array
    {
        $startTime = microtime(true);

        // Gather all dates from all documents in this case
        $dateAnalyses = DocumentAnalysis::whereHas('caseDocument', function ($q) use ($caseId) {
            $q->where('case_id', $caseId);
        })
            ->where('analysis_type', DocumentAnalysis::TYPE_DATES)
            ->where('status', DocumentAnalysis::STATUS_COMPLETED)
            ->get();

        $allDates = [];
        foreach ($dateAnalyses as $analysis) {
            $docId = $analysis->case_document_id;
            foreach ($analysis->results['dates'] ?? [] as $dateEntry) {
                $allDates[] = array_merge($dateEntry, ['document_id' => $docId]);
            }
        }

        // Sort by date
        usort($allDates, fn($a, $b) => $a['date'] <=> $b['date']);

        // Cluster dates within N days of each other
        $clusters = [];
        $currentCluster = [];

        foreach ($allDates as $entry) {
            if (empty($currentCluster)) {
                $currentCluster[] = $entry;
                continue;
            }

            $lastDate = Carbon::parse(end($currentCluster)['date']);
            $thisDate = Carbon::parse($entry['date']);

            if ($lastDate->diffInDays($thisDate) <= $this->clusterGapDays) {
                $currentCluster[] = $entry;
            } else {
                $clusters[] = $this->summarizeCluster($currentCluster);
                $currentCluster = [$entry];
            }
        }

        if (!empty($currentCluster)) {
            $clusters[] = $this->summarizeCluster($currentCluster);
        }

        // Rank clusters by document cross-reference count
        usort($clusters, fn($a, $b) => $b['document_count'] <=> $a['document_count']);

        return [
            'results' => [
                'clusters' => $clusters,
                'total_dates' => count($allDates),
                'total_clusters' => count($clusters),
                'multi_document_clusters' => count(array_filter($clusters, fn($c) => $c['document_count'] > 1)),
            ],
            'metadata' => [
                'processing_time_seconds' => round(microtime(true) - $startTime, 4),
                'documents_analyzed' => $dateAnalyses->count(),
            ],
        ];
    }

    private function summarizeCluster(array $entries): array
    {
        $dates = array_column($entries, 'date');
        $docIds = array_unique(array_column($entries, 'document_id'));
        $contexts = array_column($entries, 'context');

        return [
            'date_start' => min($dates),
            'date_end' => max($dates),
            'date_count' => count($entries),
            'document_count' => count($docIds),
            'document_ids' => array_values($docIds),
            'contexts' => array_slice($contexts, 0, 5), // Top 5 context snippets
        ];
    }
}
```

**Commit:**

```bash
git add app/Services/Analysis/CaseLevel/
git commit -m "feat: cross-document date clustering for case-level analysis"
```

---

### Task 11: Cross-Document Reference Map

**Files:**
- Create: `app/Services/Analysis/CaseLevel/CrossReferenceAnalyzer.php`

**Purpose:** Finds case numbers, law references, and entity mentions that appear across multiple documents — identifying which documents are related and how.

```php
<?php

namespace App\Services\Analysis\CaseLevel;

use App\Models\DocumentAnalysis;

class CrossReferenceAnalyzer
{
    public function analyze(string $caseId): array
    {
        $entityAnalyses = DocumentAnalysis::whereHas('caseDocument', function ($q) use ($caseId) {
            $q->where('case_id', $caseId);
        })
            ->where('analysis_type', DocumentAnalysis::TYPE_ENTITIES)
            ->where('status', DocumentAnalysis::STATUS_COMPLETED)
            ->get();

        $referenceMap = [
            'case_numbers' => [],
            'laws' => [],
            'courts' => [],
            'institutions' => [],
        ];

        foreach ($entityAnalyses as $analysis) {
            $docId = $analysis->case_document_id;
            $entities = $analysis->results['entities'] ?? [];

            foreach (['case_numbers', 'laws', 'courts', 'institutions'] as $type) {
                foreach ($entities[$type] ?? [] as $entity) {
                    $key = $entity['case_number'] ?? $entity['law'] ?? $entity['court'] ?? $entity['institution'] ?? null;
                    if ($key) {
                        $referenceMap[$type][$key][] = $docId;
                    }
                }
            }
        }

        // Find cross-references (entities appearing in 2+ documents)
        $crossRefs = [];
        foreach ($referenceMap as $type => $entries) {
            foreach ($entries as $key => $docIds) {
                $uniqueDocs = array_unique($docIds);
                if (count($uniqueDocs) >= 2) {
                    $crossRefs[] = [
                        'type' => $type,
                        'value' => $key,
                        'document_ids' => array_values($uniqueDocs),
                        'total_mentions' => count($docIds),
                    ];
                }
            }
        }

        // Sort by number of documents (most cross-referenced first)
        usort($crossRefs, fn($a, $b) => count($b['document_ids']) <=> count($a['document_ids']));

        return [
            'results' => [
                'cross_references' => $crossRefs,
                'total_cross_refs' => count($crossRefs),
            ],
            'metadata' => [
                'documents_analyzed' => $entityAnalyses->count(),
            ],
        ];
    }
}
```

**Commit:**

```bash
git add app/Services/Analysis/CaseLevel/CrossReferenceAnalyzer.php
git commit -m "feat: cross-document reference analyzer"
```

---

## Sprint 3: AI Analysis — Claude API Integration

**Sprint Goal:** Use Claude API (or Claude CLI) to generate intelligent analysis: document summaries, timeline construction, and key fact extraction.

---

### Task 12: Claude API Service

**Files:**
- Create: `app/Services/Analysis/AI/ClaudeAnalysisService.php`

**Purpose:** Wrapper around Claude API for legal document analysis. Handles prompt construction, token management, cost tracking, and retry logic.

```php
<?php

namespace App\Services\Analysis\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeAnalysisService
{
    private string $model;
    private string $apiKey;
    private int $maxTokens;

    public function __construct()
    {
        $this->model = config('services.claude.model', 'claude-sonnet-4-5-20250929');
        $this->apiKey = config('services.claude.api_key');
        $this->maxTokens = config('services.claude.max_tokens', 4096);
    }

    /**
     * Send a structured analysis prompt to Claude.
     *
     * @return array{content: string, usage: array, cost: float}
     */
    public function analyze(string $systemPrompt, string $userContent): array
    {
        $startTime = microtime(true);

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout(120)
            ->retry(3, 5000, throw: false)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userContent],
                ],
            ]);

        if (!$response->successful()) {
            Log::error('Claude API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException("Claude API error: {$response->status()} — {$response->body()}");
        }

        $data = $response->json();
        $usage = $data['usage'] ?? [];

        // Calculate cost (Sonnet 4.5 pricing as of 2025)
        $inputCost = ($usage['input_tokens'] ?? 0) / 1_000_000 * 3.0;
        $outputCost = ($usage['output_tokens'] ?? 0) / 1_000_000 * 15.0;

        return [
            'content' => collect($data['content'] ?? [])
                ->where('type', 'text')
                ->pluck('text')
                ->implode("\n"),
            'usage' => $usage,
            'cost' => round($inputCost + $outputCost, 6),
            'processing_time' => round(microtime(true) - $startTime, 4),
            'model' => $this->model,
        ];
    }

    /**
     * Analyze with JSON response expected.
     */
    public function analyzeJson(string $systemPrompt, string $userContent): array
    {
        $systemPrompt .= "\n\nIMPORTANT: Respond ONLY with valid JSON. No markdown fences, no preamble.";

        $result = $this->analyze($systemPrompt, $userContent);

        // Clean and parse JSON
        $jsonStr = trim($result['content']);
        $jsonStr = preg_replace('/^```(?:json)?\s*/i', '', $jsonStr);
        $jsonStr = preg_replace('/\s*```$/', '', $jsonStr);

        $parsed = json_decode($jsonStr, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Failed to parse Claude JSON response: " . json_last_error_msg());
        }

        $result['parsed'] = $parsed;
        return $result;
    }
}
```

**Commit:**

```bash
git add app/Services/Analysis/AI/
git commit -m "feat: Claude API analysis service with cost tracking"
```

---

### Task 13: AI Timeline Builder

**Files:**
- Create: `app/Services/Analysis/Analyzers/AI/TimelineAnalyzer.php`

**Purpose:** Uses Claude to build a chronological timeline from document text + extracted dates/entities. This is where the AI adds real value — understanding context around dates and constructing a narrative.

```php
<?php

namespace App\Services\Analysis\Analyzers\AI;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\AI\ClaudeAnalysisService;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;

class TimelineAnalyzer implements DocumentAnalyzerInterface
{
    public function __construct(
        private ClaudeAnalysisService $claude,
    ) {}

    public function type(): string
    {
        return DocumentAnalysis::TYPE_TIMELINE;
    }

    public function layer(): string
    {
        return DocumentAnalysis::LAYER_AI_BASIC;
    }

    public function analyze(CaseDocument $document, string $text): array
    {
        // Get previously extracted dates and entities to feed as context
        $dateAnalysis = $document->latestAnalysis(DocumentAnalysis::TYPE_DATES);
        $entityAnalysis = $document->latestAnalysis(DocumentAnalysis::TYPE_ENTITIES);

        $context = "PREVIOUSLY EXTRACTED DATA:\n";
        if ($dateAnalysis) {
            $context .= "Dates found: " . json_encode($dateAnalysis->results['dates'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
        }
        if ($entityAnalysis) {
            $context .= "Entities: " . json_encode($entityAnalysis->results['entities'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
        }

        $systemPrompt = <<<PROMPT
You are a Croatian legal document analyst. Your task is to extract a chronological timeline of events from the provided legal document.

For each event, provide:
- date: ISO date (YYYY-MM-DD) or approximate ("2024-01" for month-only, "2024" for year-only)
- description: Clear description of what happened (in Croatian)
- actors: Who was involved
- significance: "high", "medium", or "low" based on legal relevance
- source_quote: Brief quote from the document supporting this event (max 50 words)
- document_section: Where in the document this was found (e.g., "page 3", "paragraph 12")

Output as JSON with this structure:
{
  "events": [...],
  "narrative_summary": "Brief narrative summary of the case chronology (in Croatian)",
  "key_periods": [{"label": "...", "start": "...", "end": "...", "description": "..."}],
  "gaps": ["Periods where events are missing or unclear"]
}
PROMPT;

        $userContent = "{$context}\n\nDOCUMENT TEXT:\n{$text}";

        // Truncate if too long (leave room for system prompt)
        if (mb_strlen($userContent) > 150000) {
            $userContent = mb_substr($userContent, 0, 150000) . "\n\n[DOCUMENT TRUNCATED]";
        }

        $result = $this->claude->analyzeJson($systemPrompt, $userContent);

        return [
            'results' => $result['parsed'],
            'metadata' => [
                'model' => $result['model'],
                'input_tokens' => $result['usage']['input_tokens'] ?? 0,
                'output_tokens' => $result['usage']['output_tokens'] ?? 0,
                'cost_usd' => $result['cost'],
                'processing_time_seconds' => $result['processing_time'],
                'analyzer' => 'TimelineAnalyzer',
                'api_calls' => 1,
            ],
        ];
    }
}
```

**Commit:**

```bash
git add app/Services/Analysis/Analyzers/AI/
git commit -m "feat: AI-powered timeline builder using Claude API"
```

---

### Task 14: AI Document Summary + Key Facts

**Files:**
- Create: `app/Services/Analysis/Analyzers/AI/SummaryAnalyzer.php`
- Create: `app/Services/Analysis/Analyzers/AI/KeyFactsAnalyzer.php`

Same pattern as TimelineAnalyzer but with different prompts:

- **SummaryAnalyzer:** Generates structured summary (parties, subject matter, procedural posture, key arguments, ruling/outcome)
- **KeyFactsAnalyzer:** Extracts discrete factual claims with source references (for contradiction detection in Sprint 4)

Both output JSON, both use `ClaudeAnalysisService::analyzeJson()`.

**Commit:**

```bash
git commit -m "feat: AI summary and key facts analyzers"
```

---

## Sprint 4: Deep AI Analysis — Contradictions + Strategy

**Sprint Goal:** Cross-document AI analysis using Claude to detect contradictions, identify gaps, and suggest strategic insights.

---

### Task 15: Contradiction Detector

**Files:**
- Create: `app/Services/Analysis/CaseLevel/AI/ContradictionDetector.php`

**Purpose:** Feeds key facts from ALL documents in a case to Claude, asks it to identify contradictions, inconsistencies, and conflicts between statements/dates/claims across documents.

**Approach:**
1. Collect all `key_facts` analysis results across documents
2. Construct a prompt: "Here are factual claims from N documents in the same case. Identify contradictions."
3. Claude returns structured JSON with contradiction pairs, severity, and suggested investigation points

**Key prompt elements:**
- Compare witness statements across documents
- Compare dates/timelines for inconsistencies
- Identify claims in one document that contradict evidence in another
- Flag procedural irregularities (e.g., search warrant date vs. actual search date)

---

### Task 16: Gap Analysis

**Files:**
- Create: `app/Services/Analysis/CaseLevel/AI/GapAnalyzer.php`

**Purpose:** Identifies what's MISSING from the case file — expected documents not present, time periods not covered, procedural steps not documented.

---

### Task 17: Strategic Insights

**Files:**
- Create: `app/Services/Analysis/CaseLevel/AI/StrategyAnalyzer.php`

**Purpose:** Given all analysis layers, suggests legal strategy directions — which contradictions to exploit, which evidence to challenge, which procedural arguments are strongest. Specifically tuned for Croatian criminal defense patterns (evidence exclusion under čl. 10 ZKP, search warrant challenges, etc.)

---

## Sprint 5: Infrastructure + UI

---

### Task 18: Analysis Dashboard (Livewire Component)

**Files:**
- Create: `app/Livewire/CaseAnalysisDashboard.php`
- Create: `resources/views/livewire/case-analysis-dashboard.blade.php`

**Purpose:** Shows analysis status per document, results browser, timeline visualization, contradiction highlights. Real-time updates via Livewire polling.

---

### Task 19: Neo4j Graph Sync for Analysis Results

**Purpose:** Sync timeline events, contradictions, and cross-references as Neo4j nodes and relationships for graph-based querying.

```cypher
(doc1:CaseDocument)-[:MENTIONS_DATE]->(event:TimelineEvent {date: '2024-01-15'})
(doc1:CaseDocument)-[:CLAIMS]->(fact1:KeyFact)
(doc2:CaseDocument)-[:CLAIMS]->(fact2:KeyFact)
(fact1)-[:CONTRADICTS {severity: 'high'}]->(fact2)
```

---

### Task 20: Claude CLI Agent Alternative

**Purpose:** For complex multi-document analysis (Sprint 4), consider dispatching Claude CLI (`claude`) as a subprocess instead of API calls. This enables:
- Longer context windows via extended thinking
- Multi-turn analysis within a single invocation
- File system access for reading documents directly
- Ability to use Claude Code's tool use for iterative analysis

```php
// Example: dispatch Claude CLI for contradiction analysis
$process = new Process([
    'claude',
    '--print',
    '--model', 'claude-sonnet-4-5-20250929',
    '-p', $prompt,
], timeout: 300);
```

**Trade-offs:**
- CLI: More powerful, longer context, better for complex reasoning. Requires CLI installed on server.
- API: Simpler, stateless, easier to track costs, better for high-volume per-document analysis.

**Recommendation:** Use API for Layer 1-3 (per-document), CLI for Layer 4-5 (case-level deep analysis).

---

## Configuration

Add to `config/services.php`:

```php
'claude' => [
    'api_key' => env('ANTHROPIC_API_KEY'),
    'model' => env('CLAUDE_MODEL', 'claude-sonnet-4-5-20250929'),
    'max_tokens' => env('CLAUDE_MAX_TOKENS', 4096),
],
```

Add to `.env`:

```
ANTHROPIC_API_KEY=sk-ant-...
CLAUDE_MODEL=claude-sonnet-4-5-20250929
CLAUDE_MAX_TOKENS=4096
```

---

## Queue Configuration

Add an `analysis` queue to your queue worker:

```bash
php artisan queue:work --queue=analysis,default
```

For Vapor, add to `vapor.yml`:

```yaml
workers:
    analysis-worker:
        queue: analysis
        timeout: 300
        memory: 1024
```

---

## Summary: Sprint Roadmap

| Sprint | Focus | API Cost | Complexity |
|--------|-------|----------|------------|
| **Sprint 1** | Foundation + Layer 1 extraction (keywords, dates, entities, stats) | $0 | Low |
| **Sprint 2** | Cross-document pattern matching (date clusters, references) | $0 | Medium |
| **Sprint 3** | AI per-document analysis (timeline, summary, key facts) | ~$0.01-0.05/doc | Medium |
| **Sprint 4** | AI cross-case analysis (contradictions, gaps, strategy) | ~$0.10-0.50/case | High |
| **Sprint 5** | UI dashboard + Neo4j sync + CLI integration | $0 | Medium |

**Total tasks:** 20
**Estimated implementation time:** 3-4 weeks





# Case Document Analysis — Plan v2 Addendum

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.
> This addendum adds three major features to the original plan. Tasks are numbered 21+ to continue from the original.

**New Features:**
1. **Unified Case Reference Extraction** — KLASA, URBROJ, Broj, case numbers (K-, Pp Prz-, Kž-, etc.) organized into a single `CaseReferenceExtractor` class with file completeness tracking
2. **Metacase Detection** — Automatic detection of case hierarchies (one main case + satellite cases like Pp Prz for home search warrants)
3. **Claude Code CLI Agent** — Laravel-orchestrated autonomous agent for bulk file processing using the `claude` binary

**Source of Truth:** All regex patterns are ported from `extract_references.sh` v4 — battle-tested against actual Croatian criminal case PDFs.

---

## Updated Architecture Overview

```
Document Upload/Sync Complete
        │
        ▼
  CaseAnalysisTrigger (Event Listener)
        │
        ├─── Layer 1a: CaseReferenceExtractor (KLASA, URBROJ, Broj, Case#)
        ├─── Layer 1b: DateContextExtractor (dates + surrounding context)
        ├─── Layer 1c: KeywordAnalyzer, EntityExtractor, StatisticsAnalyzer
        │
        ▼
  ┌─────────────────────────────────────────────────────────┐
  │  Layer 2a: CaseFileRegistry — what references exist     │
  │            across ALL docs, what's MISSING              │
  │  Layer 2b: MetacaseDetector — case hierarchy mapping    │
  │  Layer 2c: DateClusterAnalyzer, CrossReferenceAnalyzer  │
  └──────────────────────┬──────────────────────────────────┘
                         ▼
  ┌─────────────────────────────────────────────────────────┐
  │  Layer 3+: AI Analysis (Claude API or Claude Code CLI)  │
  │  Orchestrated by Laravel, bulk via claude binary        │
  └─────────────────────────────────────────────────────────┘
```

---

## Feature 1: Unified Case Reference Extraction

### Task 21: CaseReferenceExtractor — Unified class for all reference types

**Files:**
- Create: `app/Services/Analysis/Analyzers/CaseReferenceExtractor.php`
- Create: `app/DTOs/Analysis/CaseReference.php`
- Create: `app/DTOs/Analysis/CaseReferenceCollection.php`

**Why:** Currently KLASA, URBROJ, Broj, and case numbers are scattered concepts. In Croatian legal practice they are all interconnected — a single document will have a KLASA + URBROJ pair (government classification), a Broj (internal number), and reference case numbers. They belong in one extractor that understands their relationships and outputs a structured registry.

**Step 1: Create the CaseReference DTO**

```php
<?php

namespace App\DTOs\Analysis;

class CaseReference
{
    public function __construct(
        public readonly string $type,       // 'klasa', 'urbroj', 'broj', 'case_number'
        public readonly string $value,      // Normalized value
        public readonly string $rawMatch,   // Original text as found
        public readonly ?string $subType,   // For case_numbers: 'kazneni', 'prekrsajni', 'dorh', 'gradanski', 'upravni'
        public readonly ?string $context,   // ±150 chars surrounding text
        public readonly int $position,      // Character offset in document
        public readonly int $mentions,      // How many times found (set during dedup)
        public readonly ?string $pairedWith = null, // KLASA ↔ URBROJ pairing
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'value' => $this->value,
            'raw_match' => $this->rawMatch,
            'sub_type' => $this->subType,
            'context' => $this->context,
            'position' => $this->position,
            'mentions' => $this->mentions,
            'paired_with' => $this->pairedWith,
        ];
    }
}
```

**Step 2: Create CaseReferenceCollection DTO**

```php
<?php

namespace App\DTOs\Analysis;

class CaseReferenceCollection
{
    /** @var CaseReference[] */
    public array $klasa = [];

    /** @var CaseReference[] */
    public array $urbroj = [];

    /** @var CaseReference[] */
    public array $broj = [];

    /** @var CaseReference[] */
    public array $caseNumbers = [];

    /** @var array<string, string> KLASA → URBROJ pairings found in same document */
    public array $klasaUrbrojPairs = [];

    public function all(): array
    {
        return array_merge($this->klasa, $this->urbroj, $this->broj, $this->caseNumbers);
    }

    public function uniqueValues(): array
    {
        return [
            'klasa' => array_unique(array_map(fn($r) => $r->value, $this->klasa)),
            'urbroj' => array_unique(array_map(fn($r) => $r->value, $this->urbroj)),
            'broj' => array_unique(array_map(fn($r) => $r->value, $this->broj)),
            'case_numbers' => array_unique(array_map(fn($r) => $r->value, $this->caseNumbers)),
        ];
    }

    public function caseNumbersByType(): array
    {
        $grouped = [];
        foreach ($this->caseNumbers as $ref) {
            $grouped[$ref->subType ?? 'unknown'][] = $ref->value;
        }
        return array_map('array_unique', $grouped);
    }

    public function toArray(): array
    {
        return [
            'klasa' => array_map(fn($r) => $r->toArray(), $this->klasa),
            'urbroj' => array_map(fn($r) => $r->toArray(), $this->urbroj),
            'broj' => array_map(fn($r) => $r->toArray(), $this->broj),
            'case_numbers' => array_map(fn($r) => $r->toArray(), $this->caseNumbers),
            'klasa_urbroj_pairs' => $this->klasaUrbrojPairs,
            'unique_values' => $this->uniqueValues(),
            'case_numbers_by_type' => $this->caseNumbersByType(),
        ];
    }
}
```

**Step 3: Create the CaseReferenceExtractor**

This is the big one. All patterns are ported directly from `extract_references.sh` v4.

```php
<?php

namespace App\Services\Analysis\Analyzers;

use App\DTOs\Analysis\CaseReference;
use App\DTOs\Analysis\CaseReferenceCollection;
use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;

class CaseReferenceExtractor implements DocumentAnalyzerInterface
{
    public function type(): string
    {
        return 'case_references';
    }

    public function layer(): string
    {
        return DocumentAnalysis::LAYER_EXTRACTION;
    }

    public function analyze(CaseDocument $document, string $text): array
    {
        $startTime = microtime(true);
        $collection = new CaseReferenceCollection();

        $collection->klasa = $this->extractKlasa($text);
        $collection->urbroj = $this->extractUrbroj($text);
        $collection->broj = $this->extractBroj($text);
        $collection->caseNumbers = $this->extractCaseNumbers($text);
        $collection->klasaUrbrojPairs = $this->detectKlasaUrbrojPairs($text);

        return [
            'results' => $collection->toArray(),
            'metadata' => [
                'processing_time_seconds' => round(microtime(true) - $startTime, 4),
                'analyzer' => 'CaseReferenceExtractor',
                'api_calls' => 0,
                'cost' => 0,
                'total_references' => count($collection->all()),
                'source_script_version' => 'extract_references.sh v4',
            ],
        ];
    }

    // ========================================
    // KLASA
    // Formats seen in practice:
    //   KLASA: UP/I-034-02/20-01/123
    //   KLASA: 034-02/25-01/5
    //   K L A S A : UP/I-034-02/20-01/999
    //   Klasa: UP/I-561-08/25-01/122
    // ========================================

    private function extractKlasa(string $text): array
    {
        $results = [];

        $patterns = [
            // Standard with UP/I prefix
            '/K\s*L\s*A\s*S\s*A\s*:\s*[A-Z]{2,}\/[A-Z]-?\d+-\d+\/\d+-\d+\/\d+/ui',
            // Without UP/I prefix
            '/K\s*L\s*A\s*S\s*A\s*:\s*\d{3}-\d{2}\/\d{2}-\d{2}\/\d+/ui',
            // General fallback: KLASA: then pattern with dashes and slashes
            '/KLASA\s*:\s*[A-Z\/]*-?\d+[-\/]\d+[-\/]\d+[-\/]\d+(\/\d+)?/ui',
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] as $m) {
                $results[] = new CaseReference(
                    type: 'klasa',
                    value: $this->normalizeWhitespace($m[0]),
                    rawMatch: $m[0],
                    subType: null,
                    context: $this->extractContext($text, $m[1]),
                    position: $m[1],
                    mentions: 1,
                );
            }
        }

        return $this->dedup($results);
    }

    // ========================================
    // URBROJ
    // Formats:
    //   URBROJ: 511-01-02-03-20-1
    //   Urbroj: 2158-64-16-01-25-1
    //   U R B R O J: ...
    //   Ur.br.: ...
    // ========================================

    private function extractUrbroj(string $text): array
    {
        $results = [];

        $patterns = [
            // With spaces in word
            '/U\s*R\s*B\s*R\s*O\s*J\s*:\s*\d[\d\-\/]{8,50}/ui',
            // Standard URBROJ
            '/URBROJ\s*:\s*\d{3,4}-\d[\d\-\/]+/ui',
            // Mixed case Urbroj
            '/Urbroj\s*:\s*\d{3,4}-\d[\d\-\/]+/ui',
            // Ur.br. variations
            '/Ur\.?\s*br(?:oj)?\.?\s*:\s*\d+[-\/]\d+[-\/\d]+/ui',
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] as $m) {
                $results[] = new CaseReference(
                    type: 'urbroj',
                    value: $this->normalizeWhitespace($m[0]),
                    rawMatch: $m[0],
                    subType: $this->classifyUrbroj($m[0]),
                    context: $this->extractContext($text, $m[1]),
                    position: $m[1],
                    mentions: 1,
                );
            }
        }

        return $this->dedup($results);
    }

    // ========================================
    // BROJ (police and court internal numbers)
    // Formats:
    //   Broj: 511-07-11-K-51/2025.
    //   67-00-731/2025
    //   Pp Prz-74/2025-2
    // ========================================

    private function extractBroj(string $text): array
    {
        $results = [];

        $patterns = [
            // Broj: with content
            '/Broj\s*:\s*\d[\d\-A-Za-z\/]+/ui',
            // Police number: 511-XX-XX-X-XX/YYYY
            '/511-\d{2}-\d{2}-[A-Z]-\d+\/\d{4}/u',
            // DO number: 67-00-731/2025 format
            '/\d{2}-\d{2}-\d+\/\d{4}/u',
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] as $m) {
                $results[] = new CaseReference(
                    type: 'broj',
                    value: $this->normalizeWhitespace($m[0]),
                    rawMatch: $m[0],
                    subType: $this->classifyBroj($m[0]),
                    context: $this->extractContext($text, $m[1]),
                    position: $m[1],
                    mentions: 1,
                );
            }
        }

        return $this->dedup($results);
    }

    // ========================================
    // CASE NUMBERS — Full pattern set from extract_references.sh
    // ========================================

    private function extractCaseNumbers(string $text): array
    {
        $results = [];

        // Categorized patterns with their sub-types
        $patternGroups = [
            'kazneni' => [
                '/\bK-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKž-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKžm-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKr-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKv-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKv\s+II-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKO-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKIO-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKIR-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKov-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bI\s+Kž-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKis-\d+\/\d{2,4}(-\d+)?/ui',
            ],
            'prekrsajni' => [
                '/\bPp\s+Prz-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bPp\s+J-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bJž-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bPn-\d+\/\d{2,4}(-\d+)?/ui',
            ],
            'dorh' => [
                '/\bDO-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bDORH-[A-Z]+-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKP-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKP-DO-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKis-DO-\d+\/\d{2,4}(-\d+)?/ui',
            ],
            'gradanski' => [
                '/\bP-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bGž-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bPovrv-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bSu-\d+\/\d{2,4}(-\d+)?/ui',
            ],
            'upravni' => [
                '/\bUs-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bUsž-\d+\/\d{2,4}(-\d+)?/ui',
            ],
        ];

        foreach ($patternGroups as $subType => $patterns) {
            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
                foreach ($matches[0] as $m) {
                    $results[] = new CaseReference(
                        type: 'case_number',
                        value: $this->normalizeWhitespace($m[0]),
                        rawMatch: $m[0],
                        subType: $subType,
                        context: $this->extractContext($text, $m[1]),
                        position: $m[1],
                        mentions: 1,
                    );
                }
            }
        }

        return $this->dedup($results);
    }

    // ========================================
    // KLASA ↔ URBROJ PAIRING
    // In Croatian admin documents, KLASA and URBROJ always appear
    // together within ~200 chars. Detect these pairs.
    // ========================================

    private function detectKlasaUrbrojPairs(string $text): array
    {
        $pairs = [];

        // Find KLASA positions
        preg_match_all('/KLASA\s*:\s*([^\n]+)/ui', $text, $klasaMatches, PREG_OFFSET_CAPTURE);
        preg_match_all('/URBROJ\s*:\s*([^\n]+)/ui', $text, $urbrojMatches, PREG_OFFSET_CAPTURE);

        foreach ($klasaMatches[0] as $ki => $km) {
            $klasaPos = $km[1];
            $klasaVal = trim($klasaMatches[1][$ki][0] ?? $km[0]);

            // Find nearest URBROJ within 300 chars
            $bestUrbroj = null;
            $bestDist = 300;

            foreach ($urbrojMatches[0] as $ui => $um) {
                $urbrojPos = $um[1];
                $dist = abs($urbrojPos - $klasaPos);

                if ($dist < $bestDist) {
                    $bestDist = $dist;
                    $bestUrbroj = trim($urbrojMatches[1][$ui][0] ?? $um[0]);
                }
            }

            if ($bestUrbroj) {
                $pairs[$klasaVal] = $bestUrbroj;
            }
        }

        return $pairs;
    }

    // ========================================
    // URBROJ classification
    // 511-* = MUP (police)
    // 2158-* etc = courts (municipal/county codes)
    // ========================================

    private function classifyUrbroj(string $urbroj): string
    {
        if (preg_match('/511-/', $urbroj)) return 'mup_policija';
        if (preg_match('/2158-/', $urbroj)) return 'sud'; // Osijek area
        if (preg_match('/2168-/', $urbroj)) return 'sud'; // Zagreb area
        return 'other';
    }

    private function classifyBroj(string $broj): string
    {
        if (preg_match('/511-/', $broj)) return 'policijski';
        if (preg_match('/\d{2}-00-/', $broj)) return 'drzavno_odvjetnistvo';
        return 'other';
    }

    // ========================================
    // Helpers
    // ========================================

    private function extractContext(string $text, int $offset, int $radius = 150): string
    {
        $start = max(0, $offset - $radius);
        $length = min(mb_strlen($text) - $start, $radius * 2 + 100);
        $context = mb_substr($text, $start, $length);
        return trim(preg_replace('/\s+/', ' ', $context));
    }

    private function normalizeWhitespace(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    /**
     * Deduplicate references by normalized value, summing mentions.
     * Keep first occurrence's position and context.
     *
     * @param CaseReference[] $refs
     * @return CaseReference[]
     */
    private function dedup(array $refs): array
    {
        $seen = [];
        $deduped = [];

        foreach ($refs as $ref) {
            $key = $ref->type . '|' . mb_strtolower($ref->value);
            if (isset($seen[$key])) {
                // Increment mention count on the first occurrence
                $existing = $deduped[$seen[$key]];
                $deduped[$seen[$key]] = new CaseReference(
                    type: $existing->type,
                    value: $existing->value,
                    rawMatch: $existing->rawMatch,
                    subType: $existing->subType,
                    context: $existing->context,
                    position: $existing->position,
                    mentions: $existing->mentions + 1,
                    pairedWith: $existing->pairedWith,
                );
            } else {
                $seen[$key] = count($deduped);
                $deduped[] = $ref;
            }
        }

        return $deduped;
    }
}
```

**Step 4: Commit**

```bash
git add app/Services/Analysis/Analyzers/CaseReferenceExtractor.php app/DTOs/Analysis/
git commit -m "feat: unified case reference extractor (KLASA, URBROJ, Broj, case numbers)"
```

---

### Task 22: DateContextExtractor — Dates with surrounding context

**Files:**
- Create: `app/Services/Analysis/Analyzers/DateContextExtractor.php`

**Why:** The original DateExtractor captures dates but minimal context. This enhanced version captures the **legal context** around each date — what happened, who was involved, what type of event it was. This feeds directly into the AI timeline builder.

**Step 1: Create DateContextExtractor**

This replaces/enhances the original Task 5 DateExtractor. Key difference: it captures ±200 chars of context and attempts to classify the event type from the surrounding text.

```php
<?php

namespace App\Services\Analysis\Analyzers;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;
use Carbon\Carbon;

class DateContextExtractor implements DocumentAnalyzerInterface
{
    /**
     * Croatian month names (genitive forms as they appear in legal text).
     */
    private const MONTHS_HR = [
        'siječnja' => 1, 'siječanj' => 1,
        'veljače' => 2, 'veljača' => 2,
        'ožujka' => 3, 'ožujak' => 3,
        'travnja' => 4, 'travanj' => 4,
        'svibnja' => 5, 'svibanj' => 5,
        'lipnja' => 6, 'lipanj' => 6,
        'srpnja' => 7, 'srpanj' => 7,
        'kolovoza' => 8, 'kolovoz' => 8,
        'rujna' => 9, 'rujan' => 9,
        'listopada' => 10, 'listopad' => 10,
        'studenog' => 11, 'studenoga' => 11, 'studeni' => 11,
        'prosinca' => 12, 'prosinac' => 12,
    ];

    /**
     * Legal event type classification patterns.
     * Applied against the ±200 char context around each date.
     */
    private const EVENT_CLASSIFIERS = [
        'pretraga' => '/pretrag[aieu]|pretraživanj/ui',
        'uhicenje' => '/uhić|uhit|lišen|lisšen|privođenj/ui',
        'ispitivanje' => '/ispitivan|saslušan|iskazao|izjavi/ui',
        'nalog_izdavanje' => '/naredbu?|naloga?|naložio|odobri/ui',
        'prijava' => '/prijav[aieu]|kaznena.*prijava|podn[ie][jo]/ui',
        'presuda' => '/presud[aieu]|osuđ|oslobođ|pravomoćn/ui',
        'rjesenje' => '/rješenj[aieu]|odluk[aieu]|zaključ/ui',
        'zapljena' => '/zapljen|oduzim|oduzet|pronađ|pronašao/ui',
        'vještačenje' => '/vješta[čck]|analiz|laboratorij|nalaz/ui',
        'rociste' => '/ročišt[aieu]|rasprav[aieu]|sjednic/ui',
        'zalba' => '/žalb[aieu]|prigovor|ulog|pobija/ui',
        'podnesak' => '/podnes[akieu]|zahtjev|prijedlog/ui',
        'dostava' => '/dostav[aieu]|uručen|primljen|zaprim/ui',
    ];

    /**
     * Time extraction patterns — many Croatian legal docs include time alongside date.
     */
    private const TIME_PATTERNS = [
        // "u 14:30 sati" or "u 14,30 sati"
        '/u\s+(\d{1,2})[:\.,](\d{2})\s*sati/ui',
        // "u 14 sati"
        '/u\s+(\d{1,2})\s+sati/ui',
        // "14:30" within 20 chars of a date
        '/(\d{1,2}):(\d{2})(?:\s*(?:h|sati?))?/u',
    ];

    public function type(): string
    {
        return 'dates_with_context';
    }

    public function layer(): string
    {
        return DocumentAnalysis::LAYER_EXTRACTION;
    }

    public function analyze(CaseDocument $document, string $text): array
    {
        $startTime = microtime(true);
        $dates = [];

        // Pattern 1: "15. siječnja 2024." / "15. siječnja 2024. godine"
        $monthNames = implode('|', array_keys(self::MONTHS_HR));
        preg_match_all(
            '/(\d{1,2})\.?\s*(' . $monthNames . ')\s*(\d{4})\.?\s*(?:god(?:ine)?\.?)?/ui',
            $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );
        foreach ($matches as $m) {
            $dates[] = $this->buildDateEntry($m, $text, 'croatian_long');
        }

        // Pattern 2: DD.MM.YYYY. (with optional spaces, g., godine)
        // From extract_references.sh: handles "01. 02. 2025. g."
        preg_match_all(
            '/(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})\.?\s*(?:g\.|god\.|godine?)?/ui',
            $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );
        foreach ($matches as $m) {
            $dates[] = $this->buildDateEntryFromDMY($m, $text, 'dd_mm_yyyy');
        }

        // Pattern 3: "dana DD.MM.YYYY." (contextual prefix)
        preg_match_all(
            '/dana\s+(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})\.?\s*(?:g\.|god\.|godine?)?/ui',
            $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );
        foreach ($matches as $m) {
            $dates[] = $this->buildDateEntryFromDMY($m, $text, 'dana_prefix');
        }

        // Pattern 4: ISO YYYY-MM-DD
        preg_match_all(
            '/(\d{4})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])/u',
            $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );
        foreach ($matches as $m) {
            $dates[] = $this->buildDateEntryFromISO($m, $text);
        }

        // Pattern 5: "od DD.MM.YYYY." / "do DD.MM.YYYY." (range endpoints)
        preg_match_all(
            '/(od|do)\s+(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})\.?/ui',
            $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );
        foreach ($matches as $m) {
            $dates[] = $this->buildDateEntryFromRange($m, $text);
        }

        // Deduplicate by date + position proximity (same date within 10 chars = same mention)
        $dates = $this->dedupDates($dates);

        // Sort chronologically
        usort($dates, fn($a, $b) => ($a['date'] ?? '') <=> ($b['date'] ?? ''));

        // Compute timeline span
        $validDates = array_filter($dates, fn($d) => !empty($d['date']));
        $dateRange = null;
        if (count($validDates) >= 2) {
            $allDates = array_column($validDates, 'date');
            $dateRange = [
                'earliest' => min($allDates),
                'latest' => max($allDates),
                'span_days' => Carbon::parse(min($allDates))->diffInDays(Carbon::parse(max($allDates))),
            ];
        }

        return [
            'results' => [
                'dates' => $dates,
                'date_count' => count($dates),
                'unique_dates' => count(array_unique(array_column($dates, 'date'))),
                'date_range' => $dateRange,
                'events_by_type' => $this->groupByEventType($dates),
            ],
            'metadata' => [
                'processing_time_seconds' => round(microtime(true) - $startTime, 4),
                'analyzer' => 'DateContextExtractor',
                'api_calls' => 0,
                'cost' => 0,
            ],
        ];
    }

    private function buildDateEntry(array $match, string $text, string $format): ?array
    {
        $day = (int)$match[1][0];
        $monthName = mb_strtolower($match[2][0]);
        $month = self::MONTHS_HR[$monthName] ?? null;
        $year = (int)$match[3][0];
        $offset = $match[0][1];

        if (!$month || !checkdate($month, $day, $year)) return null;
        if ($year < 1990 || $year > 2030) return null;

        $context = $this->extractContext($text, $offset, 200);
        $time = $this->extractNearbyTime($text, $offset);

        return [
            'date' => Carbon::create($year, $month, $day)->toDateString(),
            'time' => $time,
            'raw_match' => trim($match[0][0]),
            'format_detected' => $format,
            'context' => $context,
            'event_type' => $this->classifyEvent($context),
            'position' => $offset,
        ];
    }

    private function buildDateEntryFromDMY(array $match, string $text, string $format): ?array
    {
        // Index offsets differ because of leading group (e.g., "dana")
        $dayIdx = ($format === 'dana_prefix') ? 1 : 1;
        $monthIdx = ($format === 'dana_prefix') ? 2 : 2;
        $yearIdx = ($format === 'dana_prefix') ? 3 : 3;

        $day = (int)$match[$dayIdx][0];
        $month = (int)$match[$monthIdx][0];
        $year = (int)$match[$yearIdx][0];
        $offset = $match[0][1];

        if ($month < 1 || $month > 12 || !checkdate($month, $day, $year)) return null;
        if ($year < 1990 || $year > 2030) return null;

        $context = $this->extractContext($text, $offset, 200);
        $time = $this->extractNearbyTime($text, $offset);

        return [
            'date' => Carbon::create($year, $month, $day)->toDateString(),
            'time' => $time,
            'raw_match' => trim($match[0][0]),
            'format_detected' => $format,
            'context' => $context,
            'event_type' => $this->classifyEvent($context),
            'position' => $offset,
        ];
    }

    private function buildDateEntryFromISO(array $match, string $text): ?array
    {
        $year = (int)$match[1][0];
        $month = (int)$match[2][0];
        $day = (int)$match[3][0];
        $offset = $match[0][1];

        if (!checkdate($month, $day, $year)) return null;

        $context = $this->extractContext($text, $offset, 200);

        return [
            'date' => Carbon::create($year, $month, $day)->toDateString(),
            'time' => $this->extractNearbyTime($text, $offset),
            'raw_match' => trim($match[0][0]),
            'format_detected' => 'iso',
            'context' => $context,
            'event_type' => $this->classifyEvent($context),
            'position' => $offset,
        ];
    }

    private function buildDateEntryFromRange(array $match, string $text): ?array
    {
        $rangeType = mb_strtolower($match[1][0]); // "od" or "do"
        $day = (int)$match[2][0];
        $month = (int)$match[3][0];
        $year = (int)$match[4][0];
        $offset = $match[0][1];

        if ($month < 1 || $month > 12 || !checkdate($month, $day, $year)) return null;
        if ($year < 1990 || $year > 2030) return null;

        $context = $this->extractContext($text, $offset, 200);

        return [
            'date' => Carbon::create($year, $month, $day)->toDateString(),
            'time' => $this->extractNearbyTime($text, $offset),
            'raw_match' => trim($match[0][0]),
            'format_detected' => "range_{$rangeType}",
            'context' => $context,
            'event_type' => $this->classifyEvent($context),
            'range_position' => $rangeType, // 'od' (start) or 'do' (end)
            'position' => $offset,
        ];
    }

    /**
     * Classify the event type from surrounding context using keyword patterns.
     */
    private function classifyEvent(string $context): ?string
    {
        foreach (self::EVENT_CLASSIFIERS as $type => $pattern) {
            if (preg_match($pattern, $context)) {
                return $type;
            }
        }
        return null;
    }

    /**
     * Look for time mentions within ±50 chars of a date position.
     */
    private function extractNearbyTime(string $text, int $dateOffset): ?string
    {
        $searchStart = max(0, $dateOffset - 50);
        $searchEnd = min(mb_strlen($text), $dateOffset + 100);
        $nearby = mb_substr($text, $searchStart, $searchEnd - $searchStart);

        // "u 14:30 sati" or "u 14,30 sati"
        if (preg_match('/u\s+(\d{1,2})[:\.,](\d{2})\s*sati/ui', $nearby, $tm)) {
            return sprintf('%02d:%02d', (int)$tm[1], (int)$tm[2]);
        }

        // "u 14 sati"
        if (preg_match('/u\s+(\d{1,2})\s+sati/ui', $nearby, $tm)) {
            return sprintf('%02d:00', (int)$tm[1]);
        }

        // Bare HH:MM near a date
        if (preg_match('/(\d{1,2}):(\d{2})/', $nearby, $tm)) {
            $h = (int)$tm[1];
            $m = (int)$tm[2];
            if ($h >= 0 && $h <= 23 && $m >= 0 && $m <= 59) {
                return sprintf('%02d:%02d', $h, $m);
            }
        }

        return null;
    }

    private function extractContext(string $text, int $offset, int $radius = 200): string
    {
        $start = max(0, $offset - $radius);
        $length = min(mb_strlen($text) - $start, $radius * 2 + 100);
        $context = mb_substr($text, $start, $length);
        return trim(preg_replace('/\s+/', ' ', $context));
    }

    private function dedupDates(array $dates): array
    {
        $dates = array_filter($dates); // Remove nulls
        $deduped = [];

        foreach ($dates as $entry) {
            $dominated = false;
            foreach ($deduped as $existing) {
                // Same date within 20 chars = same mention
                if ($existing['date'] === $entry['date']
                    && abs($existing['position'] - $entry['position']) < 20) {
                    $dominated = true;
                    break;
                }
            }
            if (!$dominated) {
                $deduped[] = $entry;
            }
        }

        return $deduped;
    }

    private function groupByEventType(array $dates): array
    {
        $grouped = [];
        foreach ($dates as $d) {
            $type = $d['event_type'] ?? 'unclassified';
            $grouped[$type][] = $d['date'] ?? 'unknown';
        }
        return $grouped;
    }
}
```

**Step 2: Commit**

```bash
git add app/Services/Analysis/Analyzers/DateContextExtractor.php
git commit -m "feat: date extractor with context, time, and event classification"
```

---

### Task 23: CaseFileRegistry — Track what's missing

**Files:**
- Create: `app/Services/Analysis/CaseLevel/CaseFileRegistry.php`
- Create: `database/migrations/YYYY_MM_DD_create_case_reference_registry_table.php`

**Why:** Once we know all KLASA/URBROJ/Case numbers across all documents, we can build a registry of what references exist and — critically — what's MISSING. If Document A mentions case Pp Prz-74/2025 but there's no document in the case file with that number, that's a gap.

**Step 1: Migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('case_reference_registry', function (Blueprint $table) {
            $table->id();
            $table->string('case_id');
            $table->string('reference_type');     // klasa, urbroj, broj, case_number
            $table->string('reference_value');     // The normalized reference
            $table->string('sub_type')->nullable(); // kazneni, prekrsajni, mup_policija, etc.
            $table->string('status')->default('referenced'); // referenced, present, missing
            $table->json('found_in_documents')->nullable();    // Document IDs where this ref appears
            $table->json('is_source_of_documents')->nullable(); // Document IDs that HAVE this as their own ref
            $table->string('paired_klasa')->nullable();
            $table->string('paired_urbroj')->nullable();
            $table->integer('total_mentions')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['case_id', 'reference_type', 'reference_value'], 'case_ref_unique');
            $table->index(['case_id', 'status']);
            $table->index(['case_id', 'reference_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_reference_registry');
    }
};
```

**Step 2: CaseFileRegistry service**

```php
<?php

namespace App\Services\Analysis\CaseLevel;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CaseFileRegistry
{
    /**
     * Build/rebuild the reference registry for a case.
     *
     * Cross-references all CaseReferenceExtractor results to determine:
     * - Which references are PRESENT (a document has this as its own ref)
     * - Which are only REFERENCED (mentioned in other docs but no source doc)
     * - Which are MISSING (referenced but not present in case file)
     */
    public function build(string $caseId): array
    {
        $startTime = microtime(true);

        // Gather all case_references analysis results
        $analyses = DocumentAnalysis::whereHas('caseDocument', function ($q) use ($caseId) {
            $q->where('case_id', $caseId);
        })
            ->where('analysis_type', 'case_references')
            ->where('status', DocumentAnalysis::STATUS_COMPLETED)
            ->with('caseDocument')
            ->get();

        // Phase 1: Collect all references and which documents they appear in
        $registry = []; // key: "type|value" → data

        foreach ($analyses as $analysis) {
            $docId = $analysis->case_document_id;
            $results = $analysis->results;

            foreach (['klasa', 'urbroj', 'broj', 'case_numbers'] as $refGroup) {
                foreach ($results[$refGroup] ?? [] as $ref) {
                    $type = $ref['type'] ?? $refGroup;
                    $value = $ref['value'];
                    $key = $type . '|' . mb_strtolower($value);

                    if (!isset($registry[$key])) {
                        $registry[$key] = [
                            'reference_type' => $type,
                            'reference_value' => $value,
                            'sub_type' => $ref['sub_type'] ?? null,
                            'found_in_documents' => [],
                            'is_source_of_documents' => [],
                            'total_mentions' => 0,
                        ];
                    }

                    $registry[$key]['found_in_documents'][] = $docId;
                    $registry[$key]['total_mentions'] += ($ref['mentions'] ?? 1);
                }
            }

            // KLASA-URBROJ pairs
            foreach ($results['klasa_urbroj_pairs'] ?? [] as $klasa => $urbroj) {
                $klasaKey = "klasa|" . mb_strtolower($klasa);
                $urbrojKey = "urbroj|" . mb_strtolower($urbroj);

                if (isset($registry[$klasaKey])) {
                    $registry[$klasaKey]['paired_urbroj'] = $urbroj;
                }
                if (isset($registry[$urbrojKey])) {
                    $registry[$urbrojKey]['paired_klasa'] = $klasa;
                }
            }
        }

        // Phase 2: Determine which documents are the SOURCE of each reference
        // A document is the "source" if the ref is in the document's header/first 500 chars
        // vs just being mentioned in the body
        foreach ($analyses as $analysis) {
            $docId = $analysis->case_document_id;
            $results = $analysis->results;

            foreach (['klasa', 'urbroj', 'broj', 'case_numbers'] as $refGroup) {
                foreach ($results[$refGroup] ?? [] as $ref) {
                    // Heuristic: if found within first 500 chars, it's the document's own reference
                    if (($ref['position'] ?? 999) < 500) {
                        $key = ($ref['type'] ?? $refGroup) . '|' . mb_strtolower($ref['value']);
                        if (isset($registry[$key])) {
                            $registry[$key]['is_source_of_documents'][] = $docId;
                        }
                    }
                }
            }
        }

        // Phase 3: Determine status
        $output = [];
        $missing = [];
        $present = [];

        foreach ($registry as $key => $data) {
            $data['found_in_documents'] = array_unique($data['found_in_documents']);
            $data['is_source_of_documents'] = array_unique($data['is_source_of_documents']);

            if (!empty($data['is_source_of_documents'])) {
                $data['status'] = 'present';
                $present[] = $data;
            } else {
                $data['status'] = 'missing';
                $missing[] = $data;
            }

            $output[] = $data;
        }

        // Phase 4: Persist to case_reference_registry table
        DB::table('case_reference_registry')->where('case_id', $caseId)->delete();

        foreach ($output as $row) {
            DB::table('case_reference_registry')->insert([
                'case_id' => $caseId,
                'reference_type' => $row['reference_type'],
                'reference_value' => $row['reference_value'],
                'sub_type' => $row['sub_type'],
                'status' => $row['status'],
                'found_in_documents' => json_encode($row['found_in_documents']),
                'is_source_of_documents' => json_encode($row['is_source_of_documents']),
                'paired_klasa' => $row['paired_klasa'] ?? null,
                'paired_urbroj' => $row['paired_urbroj'] ?? null,
                'total_mentions' => $row['total_mentions'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'results' => [
                'total_references' => count($output),
                'present' => count($present),
                'missing' => count($missing),
                'missing_references' => $missing,
                'present_references' => $present,
                'references_by_type' => collect($output)->groupBy('reference_type')
                    ->map(fn($group) => [
                        'total' => $group->count(),
                        'present' => $group->where('status', 'present')->count(),
                        'missing' => $group->where('status', 'missing')->count(),
                    ])->toArray(),
            ],
            'metadata' => [
                'processing_time_seconds' => round(microtime(true) - $startTime, 4),
                'documents_analyzed' => $analyses->count(),
            ],
        ];
    }
}
```

**Step 3: Commit**

```bash
git add app/Services/Analysis/CaseLevel/CaseFileRegistry.php database/migrations/
git commit -m "feat: case file registry with missing reference detection"
```

---

## Feature 2: Metacase Detection

### Task 24: MetacaseDetector — Case hierarchy mapper

**Files:**
- Create: `app/Services/Analysis/CaseLevel/MetacaseDetector.php`
- Create: `database/migrations/YYYY_MM_DD_create_case_hierarchy_table.php`

**Why:** In Croatian criminal proceedings, there is typically one **main case** (e.g., `K-123/2025` — the criminal trial) surrounded by multiple **satellite cases** that exist to support specific procedural steps:

| Case Type | Purpose | Example |
|-----------|---------|---------|
| `K-*` | Main criminal case | K-123/2025 |
| `Pp Prz-*` | Prekršajni — home search warrant | Pp Prz-74/2025 |
| `Kv-*` / `Kv II-*` | Detention hearings | Kv-89/2025 |
| `Kis-*` | Investigative judge actions | Kis-45/2025 |
| `KIR-*` | Investigation opening | KIR-12/2025 |
| `Kž-*` | Appeals | Kž-200/2025 |
| `KP-*` / `DO-*` | State attorney's case | KP-DO-321/2025 |

The metacase detector analyzes which case numbers co-occur across documents and builds a hierarchy.

**Step 1: Migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('case_hierarchy', function (Blueprint $table) {
            $table->id();
            $table->string('case_id');             // The system case ID
            $table->string('main_case_number');     // e.g., K-123/2025
            $table->string('main_case_type');       // 'kazneni'
            $table->string('satellite_case_number'); // e.g., Pp Prz-74/2025
            $table->string('satellite_case_type');   // 'prekrsajni'
            $table->string('relationship');          // 'search_warrant', 'detention', 'appeal', 'investigation', 'prosecution'
            $table->float('confidence')->default(1.0);
            $table->json('evidence')->nullable();    // Document IDs and co-occurrence data
            $table->timestamps();

            $table->unique(['case_id', 'main_case_number', 'satellite_case_number'], 'hierarchy_unique');
            $table->index(['case_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_hierarchy');
    }
};
```

**Step 2: MetacaseDetector service**

```php
<?php

namespace App\Services\Analysis\CaseLevel;

use App\Models\DocumentAnalysis;
use Illuminate\Support\Facades\DB;

class MetacaseDetector
{
    /**
     * Case type → procedural relationship mapping.
     * What role each satellite case type plays relative to the main case.
     */
    private const SATELLITE_ROLES = [
        'Pp Prz' => 'search_warrant',       // Prekršajni — pretraga (home search)
        'Pp J'   => 'misdemeanor',           // Prekršajni — javni red
        'Kv'     => 'detention_hearing',     // Vijeće — pritvor
        'Kv II'  => 'detention_appeal',      // Žalbeno vijeće — pritvor
        'Kis'    => 'investigative_action',  // Istražne radnje
        'KIR'    => 'investigation_opening', // Otvaranje istrage
        'KIO'    => 'investigation',         // Istraga
        'Kž'     => 'appeal',               // Žalba
        'Kžm'    => 'appeal_minor',          // Žalba maloljetnik
        'I Kž'   => 'supreme_appeal',        // Vrhovni sud žalba
        'KP'     => 'prosecution',           // Državno odvjetništvo
        'KP-DO'  => 'prosecution',           // DO kaznena prijava
        'Kis-DO' => 'prosecution_investigative', // DO istražne
        'DO'     => 'prosecution_case',      // DO predmet
        'Kr'     => 'registry',              // Upisnik
        'Kov'    => 'execution',             // Izvršenje
        'Ko'     => 'execution',             // Izvršenje
    ];

    /**
     * Priority for determining which case is "main".
     * Lower number = more likely to be the main case.
     */
    private const MAIN_CASE_PRIORITY = [
        'K' => 1,     // Criminal trial — always main
        'KO' => 2,    // Criminal trial variant
        'KP' => 3,    // Prosecution case
        'DO' => 4,    // State attorney case
        'KIR' => 5,   // Investigation
        'KIO' => 6,   // Investigation
    ];

    public function detect(string $caseId): array
    {
        $startTime = microtime(true);

        // Gather all case_references analysis results
        $analyses = DocumentAnalysis::whereHas('caseDocument', function ($q) use ($caseId) {
            $q->where('case_id', $caseId);
        })
            ->where('analysis_type', 'case_references')
            ->where('status', DocumentAnalysis::STATUS_COMPLETED)
            ->get();

        // Build: case_number → [document_ids where it appears]
        $caseNumberDocs = [];
        foreach ($analyses as $analysis) {
            $docId = $analysis->case_document_id;
            foreach ($analysis->results['case_numbers'] ?? [] as $ref) {
                $value = $ref['value'];
                $caseNumberDocs[$value][] = $docId;
            }
        }

        // Deduplicate document lists
        $caseNumberDocs = array_map('array_unique', $caseNumberDocs);

        // Parse case numbers into prefix + number
        $parsed = [];
        foreach (array_keys($caseNumberDocs) as $caseNum) {
            $parsed[$caseNum] = $this->parseCaseNumber($caseNum);
        }

        // Determine the main case(s) — highest priority prefix
        $mainCases = [];
        $satelliteCases = [];

        foreach ($parsed as $caseNum => $info) {
            if ($info && isset(self::MAIN_CASE_PRIORITY[$info['prefix']])) {
                $mainCases[$caseNum] = [
                    'case_number' => $caseNum,
                    'prefix' => $info['prefix'],
                    'priority' => self::MAIN_CASE_PRIORITY[$info['prefix']],
                    'document_count' => count($caseNumberDocs[$caseNum]),
                ];
            } else {
                $satelliteCases[$caseNum] = $info;
            }
        }

        // Sort main cases by priority (lowest = most main)
        uasort($mainCases, fn($a, $b) => $a['priority'] <=> $b['priority']);

        // If no clear main case, pick the one with the most document mentions
        if (empty($mainCases) && !empty($caseNumberDocs)) {
            $mostMentioned = array_keys(array_map('count', $caseNumberDocs));
            usort($mostMentioned, fn($a, $b) => count($caseNumberDocs[$b]) <=> count($caseNumberDocs[$a]));
            $mainCaseNum = $mostMentioned[0];
            $mainCases[$mainCaseNum] = [
                'case_number' => $mainCaseNum,
                'prefix' => $parsed[$mainCaseNum]['prefix'] ?? 'unknown',
                'priority' => 99,
                'document_count' => count($caseNumberDocs[$mainCaseNum]),
            ];
        }

        $primaryMain = array_key_first($mainCases);

        // Build hierarchy: connect satellites to main case
        $hierarchy = [];
        foreach ($satelliteCases as $satNum => $satInfo) {
            if (!$satInfo) continue;

            $role = $this->determineRole($satInfo['prefix']);

            // Confidence: based on co-occurrence (same document as main case)
            $mainDocs = $caseNumberDocs[$primaryMain] ?? [];
            $satDocs = $caseNumberDocs[$satNum] ?? [];
            $overlap = count(array_intersect($mainDocs, $satDocs));
            $confidence = $overlap > 0
                ? min(1.0, 0.5 + ($overlap * 0.2))
                : 0.3; // Lower confidence if no doc overlap

            // Check year match (same year = higher confidence)
            $mainYear = $parsed[$primaryMain]['year'] ?? null;
            $satYear = $satInfo['year'] ?? null;
            if ($mainYear && $satYear && $mainYear === $satYear) {
                $confidence = min(1.0, $confidence + 0.2);
            }

            $hierarchy[] = [
                'main_case' => $primaryMain,
                'satellite_case' => $satNum,
                'satellite_prefix' => $satInfo['prefix'],
                'relationship' => $role,
                'confidence' => round($confidence, 2),
                'co_occurring_documents' => array_values(array_intersect($mainDocs, $satDocs)),
                'satellite_only_documents' => array_values(array_diff($satDocs, $mainDocs)),
            ];
        }

        // Sort by confidence descending
        usort($hierarchy, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

        // Persist
        DB::table('case_hierarchy')->where('case_id', $caseId)->delete();
        foreach ($hierarchy as $h) {
            DB::table('case_hierarchy')->insert([
                'case_id' => $caseId,
                'main_case_number' => $h['main_case'],
                'main_case_type' => $parsed[$h['main_case']]['prefix'] ?? 'unknown',
                'satellite_case_number' => $h['satellite_case'],
                'satellite_case_type' => $h['satellite_prefix'],
                'relationship' => $h['relationship'],
                'confidence' => $h['confidence'],
                'evidence' => json_encode([
                    'co_occurring_documents' => $h['co_occurring_documents'],
                    'satellite_only_documents' => $h['satellite_only_documents'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'results' => [
                'main_cases' => array_values($mainCases),
                'primary_main_case' => $primaryMain,
                'hierarchy' => $hierarchy,
                'satellite_count' => count($hierarchy),
                'case_types_found' => array_unique(array_filter(
                    array_map(fn($p) => $p['prefix'] ?? null, $parsed)
                )),
                'summary' => $this->buildHumanSummary($primaryMain, $hierarchy),
            ],
            'metadata' => [
                'processing_time_seconds' => round(microtime(true) - $startTime, 4),
                'documents_analyzed' => $analyses->count(),
                'total_case_numbers_found' => count($caseNumberDocs),
            ],
        ];
    }

    private function parseCaseNumber(string $caseNum): ?array
    {
        // Match: "Prefix-Number/Year(-Suffix)"
        // Examples: K-123/2025, Pp Prz-74/2025-2, Kv II-89/2025, KP-DO-321/2025
        if (preg_match('/^((?:Pp\s+Prz|Pp\s+J|Kv\s+II|Kis-DO|KP-DO|I\s+Kž|[A-Za-zŽžĆćČčŠšĐđ]+))-(\d+)\/(\d{2,4})(?:-(\d+))?$/ui', $caseNum, $m)) {
            $year = strlen($m[3]) === 2 ? (int)('20' . $m[3]) : (int)$m[3];
            return [
                'prefix' => trim($m[1]),
                'number' => (int)$m[2],
                'year' => $year,
                'suffix' => $m[4] ?? null,
            ];
        }

        return null;
    }

    private function determineRole(string $prefix): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($prefix));
        return self::SATELLITE_ROLES[$normalized] ?? 'related';
    }

    private function buildHumanSummary(string $mainCase, array $hierarchy): string
    {
        if (empty($hierarchy)) {
            return "Pronađen samo jedan predmet: {$mainCase}";
        }

        $parts = ["Glavni predmet: {$mainCase}"];

        $byRole = [];
        foreach ($hierarchy as $h) {
            $byRole[$h['relationship']][] = $h['satellite_case'];
        }

        $roleLabels = [
            'search_warrant' => 'Nalog za pretragu',
            'detention_hearing' => 'Pritvor',
            'detention_appeal' => 'Žalba na pritvor',
            'appeal' => 'Žalba',
            'investigation_opening' => 'Otvaranje istrage',
            'investigation' => 'Istraga',
            'investigative_action' => 'Istražne radnje',
            'prosecution' => 'Državno odvjetništvo',
            'prosecution_investigative' => 'DO istražne radnje',
            'prosecution_case' => 'DO predmet',
            'execution' => 'Izvršenje',
            'misdemeanor' => 'Prekršaj',
            'related' => 'Povezano',
        ];

        foreach ($byRole as $role => $cases) {
            $label = $roleLabels[$role] ?? $role;
            $parts[] = "  {$label}: " . implode(', ', $cases);
        }

        return implode("\n", $parts);
    }
}
```

**Step 3: Neo4j representation**

When syncing to Neo4j, the metacase hierarchy becomes:

```cypher
// Main case node
CREATE (main:CaseNumber {value: 'K-123/2025', type: 'kazneni', role: 'main'})

// Satellite cases
CREATE (sat1:CaseNumber {value: 'Pp Prz-74/2025', type: 'prekrsajni', role: 'satellite'})
CREATE (sat2:CaseNumber {value: 'Kv-89/2025', type: 'kazneni', role: 'satellite'})

// Relationships
CREATE (main)-[:HAS_SATELLITE {relationship: 'search_warrant', confidence: 0.9}]->(sat1)
CREATE (main)-[:HAS_SATELLITE {relationship: 'detention_hearing', confidence: 0.85}]->(sat2)

// Link to documents
CREATE (sat1)-[:ORIGINATES_FROM]->(doc1:CaseDocument)
CREATE (sat1)-[:REFERENCED_IN]->(doc2:CaseDocument)
```

**Step 4: Commit**

```bash
git add app/Services/Analysis/CaseLevel/MetacaseDetector.php database/migrations/
git commit -m "feat: metacase detector with case hierarchy mapping"
```

---

## Feature 3: Claude Code CLI Agent for Bulk Processing

### Task 25: ClaudeCodeAgent — Laravel service wrapping the `claude` binary

**Files:**
- Create: `app/Services/Analysis/AI/ClaudeCodeAgent.php`
- Create: `config/claude-code.php`

**Why:** The Claude Code CLI (`claude` binary) is fundamentally different from the Claude API:

| Feature | API | Claude Code CLI |
|---------|-----|-----------------|
| Context | Single prompt | Multi-turn with tools |
| File access | Must send content | Reads files directly |
| Tools | None (raw completion) | bash, file read/write, web search |
| Reasoning | Standard | Extended thinking |
| Cost tracking | Per-call tokens | Session-level |
| Autonomy | Stateless | Autonomous agent |

**For bulk file processing**, Claude Code CLI is superior because:
1. It can read files directly from disk — no need to serialize 50-page PDFs into API calls
2. It can write structured output files (JSON) directly
3. It can use bash tools to run your existing `extract_references.sh` as part of its analysis
4. It can iterate: analyze → find gaps → re-analyze

**Step 1: Config**

```php
<?php
// config/claude-code.php

return [
    'binary' => env('CLAUDE_CODE_BINARY', '/usr/local/bin/claude'),
    'model' => env('CLAUDE_CODE_MODEL', 'claude-sonnet-4-5-20250929'),

    // Maximum time a single agent session can run
    'timeout' => env('CLAUDE_CODE_TIMEOUT', 600), // 10 minutes

    // Working directory for agent sessions
    'work_dir' => env('CLAUDE_CODE_WORK_DIR', storage_path('app/claude-agent')),

    // Output directory for agent results
    'output_dir' => env('CLAUDE_CODE_OUTPUT_DIR', storage_path('app/claude-agent/output')),

    // Max tokens for extended thinking
    'max_turns' => env('CLAUDE_CODE_MAX_TURNS', 25),

    // Allowed tools
    'allowed_tools' => ['bash', 'file_read', 'file_write'],

    // System prompt template for legal analysis
    'legal_analysis_prompt' => <<<'PROMPT'
You are a Croatian legal document analyst. You have access to case files on disk.

Your task: {TASK_DESCRIPTION}

Working directory: {WORK_DIR}
Output your results as JSON to: {OUTPUT_FILE}

Rules:
- All analysis must reference specific documents by filename
- Dates must be in ISO format (YYYY-MM-DD)
- Include confidence scores for every assertion
- Write Croatian descriptions, but JSON keys in English
- If you find contradictions, flag them with severity: high/medium/low
PROMPT,
];
```

**Step 2: ClaudeCodeAgent service**

```php
<?php

namespace App\Services\Analysis\AI;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class ClaudeCodeAgent
{
    private string $binary;
    private string $model;
    private int $timeout;
    private string $workDir;
    private string $outputDir;

    public function __construct()
    {
        $this->binary = config('claude-code.binary');
        $this->model = config('claude-code.model');
        $this->timeout = config('claude-code.timeout');
        $this->workDir = config('claude-code.work_dir');
        $this->outputDir = config('claude-code.output_dir');

        // Ensure directories exist
        if (!is_dir($this->workDir)) mkdir($this->workDir, 0755, true);
        if (!is_dir($this->outputDir)) mkdir($this->outputDir, 0755, true);
    }

    /**
     * Run an autonomous analysis session on a set of files.
     *
     * @param string[] $filePaths  Absolute paths to files to analyze
     * @param string   $task       Description of what to analyze
     * @param string   $caseId     Case identifier for output naming
     * @return array{success: bool, output_file: string, results: ?array, raw_output: string, exit_code: int}
     */
    public function analyzeFiles(array $filePaths, string $task, string $caseId): array
    {
        $sessionId = Str::uuid()->toString();
        $sessionDir = $this->workDir . '/' . $sessionId;
        $outputFile = $this->outputDir . "/{$caseId}_{$sessionId}.json";

        mkdir($sessionDir, 0755, true);

        // Symlink or copy files into session directory
        foreach ($filePaths as $path) {
            $basename = basename($path);
            if (file_exists($path)) {
                symlink($path, "{$sessionDir}/{$basename}");
            }
        }

        // Build the prompt
        $fileList = implode("\n", array_map('basename', $filePaths));
        $promptTemplate = config('claude-code.legal_analysis_prompt');
        $prompt = str_replace(
            ['{TASK_DESCRIPTION}', '{WORK_DIR}', '{OUTPUT_FILE}'],
            [$task, $sessionDir, $outputFile],
            $promptTemplate
        );
        $prompt .= "\n\nFiles available:\n{$fileList}";

        Log::info("ClaudeCodeAgent: Starting session {$sessionId}", [
            'case_id' => $caseId,
            'file_count' => count($filePaths),
            'task' => Str::limit($task, 200),
        ]);

        $startTime = microtime(true);

        // Run claude CLI
        $result = Process::timeout($this->timeout)
            ->path($sessionDir)
            ->env([
                'ANTHROPIC_MODEL' => $this->model,
            ])
            ->run([
                $this->binary,
                '--print',          // Non-interactive mode
                '--output-format', 'json',
                '--max-turns', (string)config('claude-code.max_turns'),
                '--allowedTools', implode(',', config('claude-code.allowed_tools')),
                '-p', $prompt,
            ]);

        $elapsed = round(microtime(true) - $startTime, 2);
        $exitCode = $result->exitCode();
        $rawOutput = $result->output();

        Log::info("ClaudeCodeAgent: Session {$sessionId} completed", [
            'exit_code' => $exitCode,
            'elapsed_seconds' => $elapsed,
            'output_length' => strlen($rawOutput),
        ]);

        // Parse output JSON if the agent wrote it
        $parsedResults = null;
        if (file_exists($outputFile)) {
            $json = file_get_contents($outputFile);
            $parsedResults = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning("ClaudeCodeAgent: Failed to parse output JSON", [
                    'error' => json_last_error_msg(),
                ]);
                $parsedResults = null;
            }
        }

        // Cleanup session directory (keep output)
        $this->cleanupSession($sessionDir);

        return [
            'success' => $exitCode === 0 && $parsedResults !== null,
            'output_file' => $outputFile,
            'results' => $parsedResults,
            'raw_output' => $rawOutput,
            'exit_code' => $exitCode,
            'session_id' => $sessionId,
            'elapsed_seconds' => $elapsed,
        ];
    }

    /**
     * Run a bulk analysis across all documents in a case.
     * This is the "orchestrated by Laravel" part — Laravel manages
     * the high-level workflow, Claude Code does the deep analysis.
     */
    public function bulkCaseAnalysis(string $caseId, array $filePaths): array
    {
        $results = [];

        // Phase 1: Individual document analysis (can be parallelized)
        $perDocTask = <<<TASK
For EACH document in the working directory:
1. Read the full text
2. Extract all dates with their surrounding context (±200 chars)
3. Extract all case numbers (K-, Pp Prz-, Kv-, etc.), KLASA, URBROJ
4. Identify the document type (presuda, rješenje, zapisnik, naredba, prijava, etc.)
5. Summarize key facts (max 5 bullet points per document)
6. Note any procedural irregularities

Output a JSON file with one entry per document.
TASK;

        $results['per_document'] = $this->analyzeFiles($filePaths, $perDocTask, "{$caseId}_docs");

        // Phase 2: Cross-document analysis (uses Phase 1 output)
        if ($results['per_document']['success']) {
            $crossTask = <<<TASK
Read the per-document analysis from: {$results['per_document']['output_file']}

Now perform CROSS-DOCUMENT analysis:
1. Build a complete timeline of events across all documents
2. Identify contradictions: dates that don't match, facts that conflict
3. Map the case hierarchy (main case vs satellite cases like Pp Prz)
4. Identify missing documents: references to case numbers that have no source document
5. Flag procedural issues: was the search warrant issued BEFORE or AFTER the search?

Output comprehensive JSON with sections: timeline, contradictions, hierarchy, gaps, issues.
TASK;

            $results['cross_document'] = $this->analyzeFiles(
                [$results['per_document']['output_file']],
                $crossTask,
                "{$caseId}_cross"
            );
        }

        return $results;
    }

    private function cleanupSession(string $sessionDir): void
    {
        // Remove symlinks and temp files
        $files = glob("{$sessionDir}/*");
        foreach ($files as $file) {
            if (is_link($file)) {
                unlink($file);
            }
        }
        @rmdir($sessionDir);
    }
}
```

**Step 3: Commit**

```bash
git add app/Services/Analysis/AI/ClaudeCodeAgent.php config/claude-code.php
git commit -m "feat: Claude Code CLI agent service for autonomous bulk analysis"
```

---

### Task 26: Laravel Job for Claude Code bulk processing

**Files:**
- Create: `app/Jobs/Analysis/RunClaudeCodeBulkAnalysisJob.php`
- Create: `app/Console/Commands/AnalyzeCaseWithClaudeCodeCommand.php`

**Step 1: Queued job**

```php
<?php

namespace App\Jobs\Analysis;

use App\Models\CaseAnalysis;
use App\Services\Analysis\AI\ClaudeCodeAgent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunClaudeCodeBulkAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 900; // 15 minutes — Claude Code sessions can be long

    public function __construct(
        public string $caseId,
        public array $filePaths,
    ) {
        $this->queue = 'claude-agent';
    }

    public function handle(ClaudeCodeAgent $agent): void
    {
        Log::info("Starting Claude Code bulk analysis for case {$this->caseId}");

        $analysis = CaseAnalysis::updateOrCreate(
            ['case_id' => $this->caseId, 'analysis_type' => 'claude_code_bulk'],
            ['status' => CaseAnalysis::STATUS_PROCESSING, 'started_at' => now()]
        );

        try {
            $results = $agent->bulkCaseAnalysis($this->caseId, $this->filePaths);

            $allSucceeded = collect($results)->every(fn($r) => $r['success'] ?? false);

            if ($allSucceeded) {
                $analysis->markCompleted($results, [
                    'file_count' => count($this->filePaths),
                    'total_elapsed' => collect($results)->sum('elapsed_seconds'),
                ]);
            } else {
                $failures = collect($results)
                    ->filter(fn($r) => !($r['success'] ?? false))
                    ->keys()
                    ->implode(', ');

                $analysis->markFailed("Some phases failed: {$failures}");
            }
        } catch (\Throwable $e) {
            Log::error("Claude Code bulk analysis failed: {$e->getMessage()}");
            $analysis->markFailed($e->getMessage());
        }
    }
}
```

**Step 2: Artisan command**

```php
<?php

namespace App\Console\Commands;

use App\Jobs\Analysis\RunClaudeCodeBulkAnalysisJob;
use App\Models\CaseDocument;
use Illuminate\Console\Command;

class AnalyzeCaseWithClaudeCodeCommand extends Command
{
    protected $signature = 'case:analyze-claude-code
        {case_id : The case identifier}
        {--sync : Run synchronously instead of queuing}';

    protected $description = 'Run Claude Code CLI agent for deep case analysis';

    public function handle(): int
    {
        $caseId = $this->argument('case_id');

        $documents = CaseDocument::where('case_id', $caseId)->get();

        if ($documents->isEmpty()) {
            $this->error("No documents found for case {$caseId}");
            return 1;
        }

        // Collect file paths
        $filePaths = $documents->map(function ($doc) {
            // Adapt to your actual file storage
            return storage_path("app/case-documents/{$doc->filename}");
        })->filter(fn($p) => file_exists($p))->values()->toArray();

        $this->info("Found {$documents->count()} documents, " . count($filePaths) . " files accessible");

        if ($this->option('sync')) {
            $this->info('Running synchronously...');
            $agent = app(\App\Services\Analysis\AI\ClaudeCodeAgent::class);
            $results = $agent->bulkCaseAnalysis($caseId, $filePaths);

            $this->info('Results:');
            foreach ($results as $phase => $result) {
                $status = ($result['success'] ?? false) ? '✅' : '❌';
                $time = $result['elapsed_seconds'] ?? '?';
                $this->line("  {$status} {$phase}: {$time}s");
            }
        } else {
            RunClaudeCodeBulkAnalysisJob::dispatch($caseId, $filePaths);
            $this->info('Job dispatched to "claude-agent" queue.');
            $this->line('Run: php artisan queue:work --queue=claude-agent --timeout=900');
        }

        return 0;
    }
}
```

**Step 3: Commit**

```bash
git add app/Jobs/Analysis/RunClaudeCodeBulkAnalysisJob.php app/Console/Commands/AnalyzeCaseWithClaudeCodeCommand.php
git commit -m "feat: Laravel-orchestrated Claude Code bulk analysis command"
```

---

### Task 27: Integrate extract_references.sh into Claude Code agent workflow

**Files:**
- Modify: `app/Services/Analysis/AI/ClaudeCodeAgent.php`
- Create: `scripts/extract_references.sh` (deploy your existing script)

**Why:** The Claude Code CLI agent can call your bash script as a tool! This means Claude Code gets the benefit of your battle-tested regex patterns AND can reason about the results with AI.

**Step 1: Enhanced prompt that leverages the script**

Add to the agent's prompt:

```
You have access to a reference extraction script at: {SCRIPT_PATH}/extract_references.sh

Usage examples:
  ./extract_references.sh --json document.pdf          # Full extraction as JSON
  ./extract_references.sh --batch --json Documents/    # Batch mode
  ./extract_references.sh --case document.pdf          # Case numbers only

ALWAYS run this script first on each document to get the structured extraction,
then use the results as a foundation for your deeper analysis.
```

**Step 2: Updated analyzeFiles method**

```php
// In ClaudeCodeAgent::analyzeFiles(), after creating symlinks:

// Also symlink the extraction script
$scriptPath = base_path('scripts/extract_references.sh');
if (file_exists($scriptPath)) {
    symlink($scriptPath, "{$sessionDir}/extract_references.sh");
    chmod("{$sessionDir}/extract_references.sh", 0755);
}
```

**Step 3: Commit**

```bash
git add scripts/extract_references.sh app/Services/Analysis/AI/ClaudeCodeAgent.php
git commit -m "feat: integrate extract_references.sh into Claude Code agent workflow"
```

---

## Updated Sprint Roadmap

| Sprint | Tasks | Focus | API Cost |
|--------|-------|-------|----------|
| **Sprint 1** (original) | 1-9 | Foundation + basic extraction | $0 |
| **Sprint 1.5** (new) | 21-23 | Unified references + file registry + enhanced dates | $0 |
| **Sprint 2** (original + new) | 10-11, 24 | Cross-doc patterns + metacase detection | $0 |
| **Sprint 3** (original) | 12-14 | AI per-document (Claude API) | ~$0.01-0.05/doc |
| **Sprint 3.5** (new) | 25-27 | Claude Code CLI agent + Laravel orchestration | ~$0.05-0.20/session |
| **Sprint 4** (original) | 15-17 | AI deep analysis (contradictions, strategy) | ~$0.10-0.50/case |
| **Sprint 5** (original) | 18-20 | UI dashboard + Neo4j sync | $0 |

**New total tasks:** 27
**Key additions:** Unified reference tracking (KLASA/URBROJ/case#), missing file detection, case hierarchy mapping, Claude Code autonomous agent, extract_references.sh integration.

---

## Register new analyzers in the pipeline

Update `DocumentAnalysisPipeline::getAnalyzersForLayer()`:

```php
DocumentAnalysis::LAYER_EXTRACTION => [
    new DocumentStatisticsAnalyzer(),
    new KeywordAnalyzer(),
    new DateContextExtractor(),      // ← REPLACES DateExtractor
    new EntityExtractor(),
    new CaseReferenceExtractor(),    // ← NEW
],
```

Update the event listener to trigger case-level analysis after all documents complete:

```php
// In TriggerDocumentAnalysis listener, after dispatching per-document job:

// Check if all documents in the case have completed Layer 1
// If so, dispatch case-level analysis
RunCaseLevelAnalysisJob::dispatch($event->caseId)
    ->delay(now()->addSeconds(30)); // Small delay to let final doc finish
```

The case-level job runs: CaseFileRegistry, MetacaseDetector, DateClusterAnalyzer, CrossReferenceAnalyzer.
