# Level 2: Strategic Advisor - Implementation Tasks

**Estimated Total Effort:** 104-120 hours (13-15 work days, or ~3 months part-time)

**Goal:** Transform from research assistant → strategic legal advisor with predictive capabilities

---

## FEATURE 1: PRECEDENT STRENGTH SCORING

**Goal:** Calculate authority/importance scores for court decisions (0-100 scale)

**Estimated Effort:** 28-32 hours

### 1.1 Database Schema Changes (3 hours)

**Task:** Add scoring fields to `court_decisions` table

**Files to modify:**
- `database/migrations/2025_10_28_120000_add_precedent_scoring_to_court_decisions.php` (NEW)

**Implementation:**
```php
// Add these columns to court_decisions table:
- precedent_strength_score (float, default 0)
- citation_count (integer, default 0)
- cited_by_supreme_court (boolean, default false)
- court_hierarchy_weight (float, default 0)
- recency_score (float, default 0)
- consistency_score (float, nullable)
- last_scored_at (timestamp, nullable)
```

**Run:**
```bash
php artisan make:migration add_precedent_scoring_to_court_decisions
php artisan migrate
```

---

### 1.2 Citation Extraction Service (8 hours)

**Task:** Extract citations from decision text (which decisions cite which)

**Files to create:**
- `app/Services/Citations/CitationExtractor.php` (NEW)
- `app/Services/Citations/EcliLinkDetector.php` (NEW)
- `app/Services/Citations/CaseNumberLinkDetector.php` (NEW)

**Files to modify:**
- `app/Services/Odluke/OdlukeIngestService.php` (add citation extraction during ingestion)

**Implementation:**

`app/Services/Citations/CitationExtractor.php`:
```php
namespace App\Services\Citations;

class CitationExtractor
{
    public function extractCitations(string $text): array
    {
        // Returns: ['ecli' => [...], 'case_numbers' => [...], 'law_references' => [...]]
        // 1. Find ECLI codes (e.g., ECLI:HR:VSRH:2023:123)
        // 2. Find case numbers (e.g., Rev-1234/2023)
        // 3. Find law references (e.g., NN 93/14)
    }

    public function linkCitations(string $decisionId, array $citations): void
    {
        // Create relationships in Neo4j: (Decision)-[:CITES]->(Decision)
        // Update citation_count for cited decisions
    }
}
```

**Integration point:**
- `app/Services/Odluke/OdlukeIngestService.php:240` - Add after text ingestion

**Test coverage:**
- `tests/Unit/Services/Citations/CitationExtractorTest.php` (NEW)

---

### 1.3 Citation Graph Building (6 hours)

**Task:** Build Neo4j citation graph relationships

**Files to modify:**
- `app/Services/Neo4jService.php` (add citation methods)
- `docs/GRAPH_SCHEMA.cypher` (update schema)

**Implementation:**

Add to `app/Services/Neo4jService.php`:
```php
public function createCitationLink(string $citingDecisionId, string $citedDecisionId, string $citationType): void
{
    $cypher = '
        MERGE (citing:CourtDecision {id: $citing_id})
        MERGE (cited:CourtDecision {id: $cited_id})
        MERGE (citing)-[r:CITES {type: $citation_type}]->(cited)
        SET r.created_at = datetime()
    ';
    $this->client->run($cypher, [
        'citing_id' => $citingDecisionId,
        'cited_id' => $citedDecisionId,
        'citation_type' => $citationType
    ]);
}

public function getCitationCount(string $decisionId): int
{
    $cypher = '
        MATCH (d:CourtDecision {id: $decision_id})<-[:CITES]-(citing)
        RETURN count(citing) as citation_count
    ';
    $result = $this->client->run($cypher, ['decision_id' => $decisionId]);
    return $result->first()->get('citation_count');
}

public function getCitedBySupremeCourt(string $decisionId): bool
{
    $cypher = '
        MATCH (d:CourtDecision {id: $decision_id})<-[:CITES]-(citing:CourtDecision)
        WHERE citing.court CONTAINS "Vrhovni sud"
        RETURN count(citing) > 0 as cited_by_supreme
    ';
    $result = $this->client->run($cypher, ['decision_id' => $decisionId]);
    return $result->first()->get('cited_by_supreme');
}
```

**Test coverage:**
- `tests/Unit/Services/Neo4jServiceCitationTest.php` (NEW)

---

### 1.4 Precedent Strength Calculator Service (8 hours)

**Task:** Core scoring algorithm implementation

**Files to create:**
- `app/Services/Precedent/PrecedentStrengthCalculator.php` (NEW)
- `app/Services/Precedent/CourtHierarchyWeights.php` (NEW)

**Implementation:**

`app/Services/Precedent/CourtHierarchyWeights.php`:
```php
namespace App\Services\Precedent;

class CourtHierarchyWeights
{
    public const WEIGHTS = [
        'Vrhovni sud' => 100,
        'Vrhovni sud Republike Hrvatske' => 100,
        'Visoki trgovački sud' => 85,
        'Visoki upravni sud' => 85,
        'Županijski sud' => 60,
        'Trgovački sud' => 55,
        'Upravni sud' => 55,
        'Općinski sud' => 30,
        'Općinski građanski sud' => 30,
    ];

    public function getWeight(string $courtName): float
    {
        // Fuzzy match court names
        foreach (self::WEIGHTS as $pattern => $weight) {
            if (str_contains($courtName, $pattern)) {
                return $weight;
            }
        }
        return 20; // Default for unknown courts
    }
}
```

`app/Services/Precedent/PrecedentStrengthCalculator.php`:
```php
namespace App\Services\Precedent;

use App\Models\CourtDecision;
use App\Services\Neo4jService;
use Carbon\Carbon;

class PrecedentStrengthCalculator
{
    public function __construct(
        protected Neo4jService $neo4j,
        protected CourtHierarchyWeights $weights
    ) {}

    public function calculateStrength(CourtDecision $decision): float
    {
        $score = 0;

        // 1. Court hierarchy (40% weight, max 40 points)
        $courtWeight = $this->weights->getWeight($decision->court);
        $score += ($courtWeight / 100) * 40;

        // 2. Citation count (30% weight, max 30 points)
        $citationCount = $decision->citation_count ?? 0;
        $citationScore = min(30, $citationCount * 2); // 2 points per citation, capped at 30
        $score += $citationScore;

        // 3. Supreme Court endorsement (15% weight, +15 if true)
        if ($decision->cited_by_supreme_court) {
            $score += 15;
        }

        // 4. Recency (10% weight, max 10 points)
        $recencyScore = $this->calculateRecencyScore($decision->decision_date);
        $score += $recencyScore * 10;

        // 5. Consistency with other rulings (5% weight, max 5 points)
        // (Can be calculated later with ML clustering)
        $consistencyScore = $decision->consistency_score ?? 0.5; // Neutral default
        $score += $consistencyScore * 5;

        return round(min($score, 100), 2);
    }

    protected function calculateRecencyScore(?string $decisionDate): float
    {
        if (!$decisionDate) return 0.3; // Old decisions without dates

        $date = Carbon::parse($decisionDate);
        $yearsAgo = $date->diffInYears(now());

        // Exponential decay: 1.0 (this year) → 0.5 (5 years) → 0.2 (10+ years)
        if ($yearsAgo === 0) return 1.0;
        if ($yearsAgo <= 2) return 0.9;
        if ($yearsAgo <= 5) return 0.7;
        if ($yearsAgo <= 10) return 0.4;
        return 0.2;
    }

    public function scoreAll(): void
    {
        CourtDecision::chunk(100, function ($decisions) {
            foreach ($decisions as $decision) {
                $this->scoreDecision($decision);
            }
        });
    }

    public function scoreDecision(CourtDecision $decision): void
    {
        // Calculate individual components
        $courtWeight = $this->weights->getWeight($decision->court);
        $citationCount = $this->neo4j->getCitationCount($decision->id);
        $citedBySupreme = $this->neo4j->getCitedBySupremeCourt($decision->id);
        $recencyScore = $this->calculateRecencyScore($decision->decision_date);

        // Update decision
        $decision->update([
            'court_hierarchy_weight' => $courtWeight,
            'citation_count' => $citationCount,
            'cited_by_supreme_court' => $citedBySupreme,
            'recency_score' => $recencyScore,
            'precedent_strength_score' => $this->calculateStrength($decision),
            'last_scored_at' => now(),
        ]);
    }
}
```

**Test coverage:**
- `tests/Unit/Services/Precedent/PrecedentStrengthCalculatorTest.php` (NEW)

---

### 1.5 Console Command & Scheduling (2 hours)

**Task:** Daily scoring job

**Files to create:**
- `app/Console/Commands/ScorePrecedents.php` (NEW)

**Files to modify:**
- `routes/console.php` (add scheduling)

**Implementation:**

`app/Console/Commands/ScorePrecedents.php`:
```php
namespace App\Console\Commands;

use App\Services\Precedent\PrecedentStrengthCalculator;
use Illuminate\Console\Command;

class ScorePrecedents extends Command
{
    protected $signature = 'precedents:score {--all : Score all decisions}';
    protected $description = 'Calculate precedent strength scores for court decisions';

    public function handle(PrecedentStrengthCalculator $calculator): int
    {
        $this->info('Starting precedent scoring...');

        if ($this->option('all')) {
            $calculator->scoreAll();
            $this->info('All decisions scored successfully.');
        } else {
            // Score only unscored or outdated (>7 days)
            $this->info('Scoring recent/unscored decisions...');
            // Implementation...
        }

        return 0;
    }
}
```

Add to `routes/console.php`:
```php
Schedule::command('precedents:score')->daily()->at('03:00');
```

---

### 1.6 API Endpoints (3 hours)

**Task:** Expose scoring data via API

**Files to modify:**
- `routes/api.php`
- `app/Http/Controllers/PrecedentController.php` (NEW)

**Implementation:**

`app/Http/Controllers/PrecedentController.php`:
```php
namespace App\Http\Controllers;

use App\Models\CourtDecision;
use App\Services\Precedent\PrecedentStrengthCalculator;

class PrecedentController extends Controller
{
    public function getStrength(string $id, PrecedentStrengthCalculator $calculator)
    {
        $decision = CourtDecision::findOrFail($id);

        // Recalculate if never scored or outdated (>7 days)
        if (!$decision->last_scored_at || $decision->last_scored_at->lt(now()->subDays(7))) {
            $calculator->scoreDecision($decision);
            $decision->refresh();
        }

        return response()->json([
            'decision_id' => $decision->id,
            'precedent_strength_score' => $decision->precedent_strength_score,
            'breakdown' => [
                'court_hierarchy_weight' => $decision->court_hierarchy_weight,
                'citation_count' => $decision->citation_count,
                'cited_by_supreme_court' => $decision->cited_by_supreme_court,
                'recency_score' => $decision->recency_score,
                'consistency_score' => $decision->consistency_score,
            ],
            'last_scored_at' => $decision->last_scored_at,
        ]);
    }

    public function topPrecedents(Request $request)
    {
        $limit = min((int) $request->get('limit', 20), 100);
        $court = $request->get('court');
        $topic = $request->get('topic'); // Free text search

        $query = CourtDecision::query()
            ->where('precedent_strength_score', '>', 0)
            ->orderByDesc('precedent_strength_score');

        if ($court) {
            $query->where('court', 'like', "%{$court}%");
        }

        if ($topic) {
            // Join with documents for semantic search
            // Or use keyword search on title/description
        }

        $decisions = $query->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $decisions,
            'count' => $decisions->count(),
        ]);
    }
}
```

Add to `routes/api.php`:
```php
Route::prefix('precedents')->group(function () {
    Route::get('/{id}/strength', [PrecedentController::class, 'getStrength']);
    Route::get('/top', [PrecedentController::class, 'topPrecedents']);
});
```

---

## FEATURE 2: ARGUMENT MAPPING & PATTERN DETECTION

**Goal:** Identify what legal arguments/reasoning patterns lead to successful outcomes

**Estimated Effort:** 24-28 hours

### 2.1 Database Schema for Arguments (2 hours)

**Task:** Store extracted arguments and outcomes

**Files to create:**
- `database/migrations/2025_10_28_130000_create_decision_arguments_table.php` (NEW)

**Schema:**
```php
Schema::create('decision_arguments', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->ulid('decision_id')->index();
    $table->string('argument_type'); // 'legal_basis', 'procedural', 'factual', 'policy'
    $table->text('argument_text');
    $table->vector('argument_embedding', 1536)->nullable();
    $table->string('outcome'); // 'plaintiff_wins', 'defendant_wins', 'partial', 'dismissed'
    $table->float('confidence_score')->default(0);
    $table->json('metadata')->nullable(); // {statute_cited, court_level, etc.}
    $table->timestamps();

    $table->foreign('decision_id')->references('id')->on('court_decisions')->onDelete('cascade');
    $table->index(['argument_type', 'outcome']);
});

Schema::create('argument_patterns', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('pattern_name'); // 'overtime_compensation_demand'
    $table->string('legal_area'); // 'labor_law', 'contract_law', etc.
    $table->text('pattern_description');
    $table->integer('occurrence_count')->default(0);
    $table->float('success_rate')->default(0); // % of times plaintiff wins with this argument
    $table->json('winning_arguments')->nullable(); // Array of common winning arg types
    $table->json('losing_arguments')->nullable();
    $table->timestamps();
});
```

---

### 2.2 LLM-Based Argument Extractor (10 hours)

**Task:** Use GPT to extract arguments from decision text

**Files to create:**
- `app/Services/Arguments/ArgumentExtractor.php` (NEW)
- `app/Services/Arguments/OutcomeDetector.php` (NEW)
- `app/Jobs/ExtractDecisionArguments.php` (NEW)

**Implementation:**

`app/Services/Arguments/ArgumentExtractor.php`:
```php
namespace App\Services\Arguments;

use App\Models\CourtDecision;
use App\Models\DecisionArgument;
use App\Services\OpenAIService;

class ArgumentExtractor
{
    public function __construct(
        protected OpenAIService $openai,
        protected OutcomeDetector $outcomeDetector
    ) {}

    public function extract(CourtDecision $decision): array
    {
        $text = $this->getDecisionText($decision);
        $outcome = $this->outcomeDetector->detectOutcome($text, $decision);

        // Use GPT-4 to extract structured arguments
        $prompt = $this->buildExtractionPrompt($text);
        $response = $this->openai->chatCompletion($prompt, [
            'model' => 'gpt-4o-mini',
            'response_format' => ['type' => 'json_object'],
        ]);

        $extracted = json_decode($response, true);

        // Store arguments
        $arguments = [];
        foreach ($extracted['arguments'] as $arg) {
            $embedding = $this->openai->generateEmbedding($arg['text']);

            $arguments[] = DecisionArgument::create([
                'decision_id' => $decision->id,
                'argument_type' => $arg['type'],
                'argument_text' => $arg['text'],
                'argument_embedding' => $embedding,
                'outcome' => $outcome,
                'confidence_score' => $arg['confidence'] ?? 0.5,
                'metadata' => $arg['metadata'] ?? [],
            ]);
        }

        return $arguments;
    }

    protected function buildExtractionPrompt(string $text): string
    {
        return <<<PROMPT
Extract legal arguments from this Croatian court decision. Return JSON:

{
  "arguments": [
    {
      "type": "legal_basis|procedural|factual|policy",
      "text": "The plaintiff argued that...",
      "confidence": 0.9,
      "metadata": {
        "statute_cited": "NN 93/14 Article 86",
        "party": "plaintiff|defendant"
      }
    }
  ]
}

Decision text:
{$text}

Return only valid JSON.
PROMPT;
    }

    protected function getDecisionText(CourtDecision $decision): string
    {
        return $decision->documents()
            ->orderBy('chunk_index')
            ->pluck('content')
            ->implode("\n\n");
    }
}
```

`app/Services/Arguments/OutcomeDetector.php`:
```php
namespace App\Services\Arguments;

use App\Models\CourtDecision;

class OutcomeDetector
{
    public function detectOutcome(string $text, CourtDecision $decision): string
    {
        // 1. Check decision_type field
        $type = strtolower($decision->decision_type ?? '');

        if (str_contains($type, 'odbija')) return 'defendant_wins';
        if (str_contains($type, 'usvaja')) return 'plaintiff_wins';
        if (str_contains($type, 'djelomično')) return 'partial';
        if (str_contains($type, 'odbacuje')) return 'dismissed';

        // 2. Text pattern matching
        $text_lower = mb_strtolower($text);

        if (str_contains($text_lower, 'tužbeni zahtjev se usvaja')) return 'plaintiff_wins';
        if (str_contains($text_lower, 'tužbeni zahtjev se odbija')) return 'defendant_wins';
        if (str_contains($text_lower, 'tužba se usvaja djelomično')) return 'partial';

        return 'unknown';
    }
}
```

**Background job:**

`app/Jobs/ExtractDecisionArguments.php`:
```php
namespace App\Jobs;

use App\Models\CourtDecision;
use App\Services\Arguments\ArgumentExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExtractDecisionArguments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $decisionId) {}

    public function handle(ArgumentExtractor $extractor): void
    {
        $decision = CourtDecision::find($this->decisionId);
        if (!$decision) return;

        $extractor->extract($decision);
    }
}
```

**Integration point:**
- `app/Services/Odluke/OdlukeIngestService.php:260` - Dispatch job after ingestion

**Test coverage:**
- `tests/Unit/Services/Arguments/ArgumentExtractorTest.php` (NEW)

---

### 2.3 Pattern Detection & Success Rate Calculator (8 hours)

**Task:** Find common argument patterns and calculate success rates

**Files to create:**
- `app/Services/Arguments/PatternDetector.php` (NEW)
- `app/Console/Commands/DetectArgumentPatterns.php` (NEW)

**Implementation:**

`app/Services/Arguments/PatternDetector.php`:
```php
namespace App\Services\Arguments;

use App\Models\ArgumentPattern;
use App\Models\DecisionArgument;
use Illuminate\Support\Facades\DB;

class PatternDetector
{
    public function detectPatterns(): void
    {
        // 1. Cluster similar arguments using embeddings
        $clusters = $this->clusterArguments();

        // 2. For each cluster, calculate success rate
        foreach ($clusters as $cluster) {
            $this->analyzeCluster($cluster);
        }
    }

    protected function clusterArguments(): array
    {
        // Use k-means or DBSCAN on argument_embeddings
        // For MVP: Group by argument_type + similar embeddings (cosine > 0.85)

        $arguments = DecisionArgument::whereNotNull('argument_embedding')->get();

        $clusters = [];
        $processed = [];

        foreach ($arguments as $arg) {
            if (in_array($arg->id, $processed)) continue;

            $similar = $this->findSimilarArguments($arg, $arguments);
            $clusters[] = $similar;
            $processed = array_merge($processed, $similar->pluck('id')->toArray());
        }

        return $clusters;
    }

    protected function findSimilarArguments(DecisionArgument $target, $allArguments, float $threshold = 0.85)
    {
        $similar = collect([$target]);

        foreach ($allArguments as $arg) {
            if ($arg->id === $target->id) continue;

            $similarity = $this->cosineSimilarity(
                $target->argument_embedding,
                $arg->argument_embedding
            );

            if ($similarity >= $threshold) {
                $similar->push($arg);
            }
        }

        return $similar;
    }

    protected function analyzeCluster($cluster): void
    {
        $total = $cluster->count();
        if ($total < 5) return; // Ignore small clusters

        // Count outcomes
        $outcomes = $cluster->groupBy('outcome')->map->count();
        $plaintiffWins = $outcomes->get('plaintiff_wins', 0);
        $successRate = ($plaintiffWins / $total) * 100;

        // Identify common characteristics
        $legalArea = $this->identifyLegalArea($cluster);
        $patternName = $this->generatePatternName($cluster);
        $patternDescription = $this->generateDescription($cluster);

        // Store pattern
        ArgumentPattern::updateOrCreate(
            ['pattern_name' => $patternName],
            [
                'legal_area' => $legalArea,
                'pattern_description' => $patternDescription,
                'occurrence_count' => $total,
                'success_rate' => $successRate,
                'winning_arguments' => $this->extractCommonArgs($cluster, 'plaintiff_wins'),
                'losing_arguments' => $this->extractCommonArgs($cluster, 'defendant_wins'),
            ]
        );
    }

    protected function identifyLegalArea($cluster): string
    {
        // Check metadata for common statute references
        $statutes = $cluster->pluck('metadata.statute_cited')->filter()->flatten();
        $mostCommon = $statutes->mode();

        // Map statute to legal area
        if (str_contains($mostCommon, 'Zakon o radu')) return 'labor_law';
        if (str_contains($mostCommon, 'Zakon o obveznim odnosima')) return 'contract_law';

        return 'general';
    }

    protected function generatePatternName($cluster): string
    {
        // Use LLM to generate human-readable pattern name
        $sampleTexts = $cluster->take(3)->pluck('argument_text')->implode("\n---\n");

        $prompt = "Generate a concise pattern name (2-4 words) for these similar legal arguments:\n\n{$sampleTexts}";

        $response = app(OpenAIService::class)->chatCompletion($prompt, ['model' => 'gpt-4o-mini']);

        return trim($response);
    }

    protected function generateDescription($cluster): string
    {
        $sampleTexts = $cluster->take(5)->pluck('argument_text')->implode("\n---\n");

        $prompt = "Summarize this argument pattern in 1-2 sentences:\n\n{$sampleTexts}";

        $response = app(OpenAIService::class)->chatCompletion($prompt, ['model' => 'gpt-4o-mini']);

        return trim($response);
    }

    protected function extractCommonArgs($cluster, string $outcome): array
    {
        return $cluster->where('outcome', $outcome)
            ->take(10)
            ->pluck('argument_text')
            ->toArray();
    }

    protected function cosineSimilarity($vecA, $vecB): float
    {
        if (is_string($vecA)) $vecA = json_decode($vecA, true);
        if (is_string($vecB)) $vecB = json_decode($vecB, true);

        $dotProduct = 0;
        $magnitudeA = 0;
        $magnitudeB = 0;

        for ($i = 0; $i < count($vecA); $i++) {
            $dotProduct += $vecA[$i] * $vecB[$i];
            $magnitudeA += $vecA[$i] ** 2;
            $magnitudeB += $vecB[$i] ** 2;
        }

        return $dotProduct / (sqrt($magnitudeA) * sqrt($magnitudeB));
    }
}
```

**Console command:**

`app/Console/Commands/DetectArgumentPatterns.php`:
```php
namespace App\Console\Commands;

use App\Services\Arguments\PatternDetector;
use Illuminate\Console\Command;

class DetectArgumentPatterns extends Command
{
    protected $signature = 'arguments:detect-patterns';
    protected $description = 'Detect common argument patterns and calculate success rates';

    public function handle(PatternDetector $detector): int
    {
        $this->info('Detecting argument patterns...');
        $detector->detectPatterns();
        $this->info('Pattern detection complete.');
        return 0;
    }
}
```

Add to `routes/console.php`:
```php
Schedule::command('arguments:detect-patterns')->weekly();
```

---

### 2.4 Argument Pattern API (4 hours)

**Task:** Expose pattern data to users

**Files to modify:**
- `routes/api.php`
- `app/Http/Controllers/ArgumentPatternController.php` (NEW)

**Implementation:**

`app/Http/Controllers/ArgumentPatternController.php`:
```php
namespace App\Http\Controllers;

use App\Models\ArgumentPattern;
use App\Models\DecisionArgument;
use Illuminate\Http\Request;

class ArgumentPatternController extends Controller
{
    public function index(Request $request)
    {
        $legalArea = $request->get('legal_area');
        $minSuccessRate = (float) $request->get('min_success_rate', 0);

        $query = ArgumentPattern::query()
            ->where('success_rate', '>=', $minSuccessRate)
            ->orderByDesc('success_rate');

        if ($legalArea) {
            $query->where('legal_area', $legalArea);
        }

        $patterns = $query->paginate(20);

        return response()->json($patterns);
    }

    public function show(string $id)
    {
        $pattern = ArgumentPattern::findOrFail($id);

        // Get sample decisions using this pattern
        $sampleDecisions = DecisionArgument::where('argument_type', $pattern->legal_area)
            ->with('decision')
            ->limit(10)
            ->get();

        return response()->json([
            'pattern' => $pattern,
            'sample_decisions' => $sampleDecisions,
        ]);
    }

    public function searchSimilar(Request $request)
    {
        $argumentText = $request->get('text');

        // Generate embedding for query
        $embedding = app(\App\Services\OpenAIService::class)->generateEmbedding($argumentText);

        // Find similar arguments in database
        $similar = DecisionArgument::selectRaw('
                *,
                1 - (argument_embedding <=> ?::vector) as similarity
            ', [$this->toPgVectorLiteral($embedding)])
            ->where(DB::raw('1 - (argument_embedding <=> ?::vector)'), '>=', 0.80)
            ->orderByDesc('similarity')
            ->limit(20)
            ->get();

        // Calculate success rate for similar arguments
        $total = $similar->count();
        $wins = $similar->where('outcome', 'plaintiff_wins')->count();
        $successRate = $total > 0 ? ($wins / $total) * 100 : 0;

        return response()->json([
            'query' => $argumentText,
            'success_rate' => $successRate,
            'sample_count' => $total,
            'similar_arguments' => $similar,
        ]);
    }
}
```

Add to `routes/api.php`:
```php
Route::prefix('arguments')->group(function () {
    Route::get('/patterns', [ArgumentPatternController::class, 'index']);
    Route::get('/patterns/{id}', [ArgumentPatternController::class, 'show']);
    Route::post('/search-similar', [ArgumentPatternController::class, 'searchSimilar']);
});
```

---

## FEATURE 3: OUTCOME PREDICTION

**Goal:** Predict case outcomes based on facts and legal arguments

**Estimated Effort:** 32-36 hours

### 3.1 Database Schema for Predictions (2 hours)

**Task:** Store prediction metadata

**Files to create:**
- `database/migrations/2025_10_28_140000_create_outcome_predictions_table.php` (NEW)

**Schema:**
```php
Schema::create('outcome_predictions', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->text('case_facts'); // User-provided case description
    $table->string('legal_area')->nullable();
    $table->string('court_level')->nullable(); // 'municipal', 'county', 'supreme'
    $table->json('plaintiff_arguments')->nullable();
    $table->json('defendant_arguments')->nullable();
    $table->string('predicted_outcome'); // 'plaintiff_wins', 'defendant_wins', 'partial', 'uncertain'
    $table->float('confidence')->default(0); // 0-1 scale
    $table->json('similar_cases')->nullable(); // Array of similar case IDs
    $table->json('reasoning')->nullable(); // Explanation of prediction
    $table->json('winning_factors')->nullable();
    $table->json('risk_factors')->nullable();
    $table->timestamps();
});
```

---

### 3.2 Case Similarity Finder (10 hours)

**Task:** Find historically similar cases based on facts

**Files to create:**
- `app/Services/Prediction/CaseSimilarityFinder.php` (NEW)
- `app/Services/Prediction/FactExtractor.php` (NEW)

**Implementation:**

`app/Services/Prediction/FactExtractor.php`:
```php
namespace App\Services\Prediction;

use App\Services\OpenAIService;

class FactExtractor
{
    public function __construct(protected OpenAIService $openai) {}

    public function extractKeyFacts(string $caseDescription): array
    {
        $prompt = <<<PROMPT
Extract key legal facts from this case description. Return JSON:

{
  "legal_area": "labor_law|contract_law|property_law|...",
  "parties": {
    "plaintiff_type": "employee|employer|individual|company",
    "defendant_type": "..."
  },
  "key_facts": [
    "Plaintiff worked overtime without compensation",
    "No written employment contract existed"
  ],
  "amounts_involved": {
    "claim_amount": 50000,
    "currency": "EUR"
  },
  "time_factors": {
    "employment_duration_years": 5,
    "dispute_duration_months": 12
  }
}

Case description:
{$caseDescription}

Return only valid JSON.
PROMPT;

        $response = $this->openai->chatCompletion($prompt, [
            'model' => 'gpt-4o-mini',
            'response_format' => ['type' => 'json_object'],
        ]);

        return json_decode($response, true);
    }
}
```

`app/Services/Prediction/CaseSimilarityFinder.php`:
```php
namespace App\Services\Prediction;

use App\Models\CourtDecision;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\DB;

class CaseSimilarityFinder
{
    public function __construct(
        protected OpenAIService $openai,
        protected FactExtractor $factExtractor
    ) {}

    public function findSimilarCases(string $caseDescription, int $limit = 20): array
    {
        // 1. Extract key facts
        $facts = $this->factExtractor->extractKeyFacts($caseDescription);

        // 2. Generate embedding for the case description
        $embedding = $this->openai->generateEmbedding($caseDescription);

        // 3. Vector search in court_decision_documents
        $vectorLiteral = $this->toPgVectorLiteral($embedding);

        $similar = DB::table('court_decision_documents')
            ->select([
                'court_decision_documents.decision_id',
                'court_decisions.case_number',
                'court_decisions.title',
                'court_decisions.court',
                'court_decisions.decision_type',
                'court_decisions.decision_date',
                DB::raw("1 - (court_decision_documents.embedding <=> '{$vectorLiteral}'::vector) as similarity")
            ])
            ->join('court_decisions', 'court_decision_documents.decision_id', '=', 'court_decisions.id')
            ->whereRaw("1 - (court_decision_documents.embedding <=> '{$vectorLiteral}'::vector) >= ?", [0.75])
            ->orderByRaw("court_decision_documents.embedding <=> '{$vectorLiteral}'::vector")
            ->limit($limit)
            ->get();

        // 4. Detect outcomes for similar cases
        $similarWithOutcomes = $similar->map(function ($case) {
            $outcome = $this->detectOutcome($case);
            $case->outcome = $outcome;
            return $case;
        });

        return $similarWithOutcomes->toArray();
    }

    protected function detectOutcome($case): string
    {
        $type = strtolower($case->decision_type ?? '');

        if (str_contains($type, 'odbija')) return 'defendant_wins';
        if (str_contains($type, 'usvaja')) return 'plaintiff_wins';
        if (str_contains($type, 'djelomično')) return 'partial';

        return 'unknown';
    }

    protected function toPgVectorLiteral(array $vector): string
    {
        return '[' . implode(',', $vector) . ']';
    }
}
```

**Test coverage:**
- `tests/Unit/Services/Prediction/CaseSimilarityFinderTest.php` (NEW)

---

### 3.3 Outcome Predictor Service (12 hours)

**Task:** Core prediction logic using LLM + historical data

**Files to create:**
- `app/Services/Prediction/OutcomePredictor.php` (NEW)
- `app/Models/OutcomePrediction.php` (NEW)

**Implementation:**

`app/Services/Prediction/OutcomePredictor.php`:
```php
namespace App\Services\Prediction;

use App\Models\OutcomePrediction;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

class OutcomePredictor
{
    public function __construct(
        protected OpenAIService $openai,
        protected CaseSimilarityFinder $similarityFinder,
        protected FactExtractor $factExtractor
    ) {}

    public function predict(array $params): OutcomePrediction
    {
        $caseFacts = $params['case_facts'];
        $courtLevel = $params['court_level'] ?? null;
        $plaintiffArgs = $params['plaintiff_arguments'] ?? [];
        $defendantArgs = $params['defendant_arguments'] ?? [];

        // 1. Find similar historical cases
        Log::info('Finding similar cases...', ['case_facts' => $caseFacts]);
        $similarCases = $this->similarityFinder->findSimilarCases($caseFacts, 30);

        // 2. Calculate base probabilities from similar cases
        $outcomes = collect($similarCases)->pluck('outcome')->countBy();
        $total = count($similarCases);

        $plaintiffWins = $outcomes->get('plaintiff_wins', 0);
        $defendantWins = $outcomes->get('defendant_wins', 0);
        $partial = $outcomes->get('partial', 0);

        $baseProbability = [
            'plaintiff_wins' => $total > 0 ? $plaintiffWins / $total : 0.5,
            'defendant_wins' => $total > 0 ? $defendantWins / $total : 0.5,
            'partial' => $total > 0 ? $partial / $total : 0,
        ];

        Log::info('Base probabilities from similar cases', $baseProbability);

        // 3. Use LLM to analyze arguments and refine prediction
        $llmPrediction = $this->getLLMPrediction($caseFacts, $similarCases, $plaintiffArgs, $defendantArgs);

        // 4. Combine statistical + LLM insights
        $finalPrediction = $this->combinePredictions($baseProbability, $llmPrediction);

        // 5. Store prediction
        $prediction = OutcomePrediction::create([
            'case_facts' => $caseFacts,
            'legal_area' => $llmPrediction['legal_area'] ?? null,
            'court_level' => $courtLevel,
            'plaintiff_arguments' => $plaintiffArgs,
            'defendant_arguments' => $defendantArgs,
            'predicted_outcome' => $finalPrediction['outcome'],
            'confidence' => $finalPrediction['confidence'],
            'similar_cases' => array_map(fn($c) => $c->decision_id, $similarCases),
            'reasoning' => $llmPrediction['reasoning'],
            'winning_factors' => $llmPrediction['winning_factors'] ?? [],
            'risk_factors' => $llmPrediction['risk_factors'] ?? [],
        ]);

        return $prediction;
    }

    protected function getLLMPrediction(string $caseFacts, array $similarCases, array $plaintiffArgs, array $defendantArgs): array
    {
        $similarCasesText = $this->formatSimilarCases($similarCases);

        $prompt = <<<PROMPT
You are an expert Croatian legal analyst. Predict the likely outcome of this case.

**New Case Facts:**
{$caseFacts}

**Plaintiff Arguments:**
{$this->formatArguments($plaintiffArgs)}

**Defendant Arguments:**
{$this->formatArguments($defendantArgs)}

**Similar Historical Cases:**
{$similarCasesText}

Analyze and return JSON:
{
  "predicted_outcome": "plaintiff_wins|defendant_wins|partial|uncertain",
  "confidence": 0.75,
  "legal_area": "labor_law|contract_law|...",
  "reasoning": "Based on similar cases, plaintiffs typically win when... However, the defendant has a strong argument that...",
  "winning_factors": [
    "Strong documentation of overtime hours",
    "Clear violation of Labor Law Article 86"
  ],
  "risk_factors": [
    "Lack of written employment contract",
    "Statute of limitations may apply"
  ],
  "key_precedents": [
    {
      "case_number": "Rev-1234/2022",
      "relevance": "High",
      "outcome": "plaintiff_wins",
      "key_takeaway": "Supreme Court ruled that verbal contracts are binding for overtime claims"
    }
  ]
}

Return only valid JSON.
PROMPT;

        $response = $this->openai->chatCompletion($prompt, [
            'model' => 'gpt-4o',
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.3, // Lower temperature for more consistent predictions
        ]);

        return json_decode($response, true);
    }

    protected function formatSimilarCases(array $cases): string
    {
        $formatted = [];

        foreach (array_slice($cases, 0, 10) as $case) {
            $formatted[] = sprintf(
                "- %s (%s, %s): %s [Similarity: %.2f]",
                $case->case_number ?? 'N/A',
                $case->court ?? 'Unknown court',
                $case->decision_date ?? 'Unknown date',
                $case->outcome ?? 'Unknown outcome',
                $case->similarity ?? 0
            );
        }

        return implode("\n", $formatted);
    }

    protected function formatArguments(array $arguments): string
    {
        if (empty($arguments)) return 'None provided';

        return implode("\n", array_map(fn($arg, $i) => ($i + 1) . ". {$arg}", $arguments, array_keys($arguments)));
    }

    protected function combinePredictions(array $baseProbability, array $llmPrediction): array
    {
        // Weight: 60% LLM, 40% historical statistics
        $llmConfidence = $llmPrediction['confidence'];
        $historicalWeight = 0.4;
        $llmWeight = 0.6;

        $outcome = $llmPrediction['predicted_outcome'];

        // Calculate final confidence
        $historicalConfidence = $baseProbability[$outcome] ?? 0.5;
        $finalConfidence = ($llmConfidence * $llmWeight) + ($historicalConfidence * $historicalWeight);

        return [
            'outcome' => $outcome,
            'confidence' => round($finalConfidence, 2),
        ];
    }
}
```

**Test coverage:**
- `tests/Unit/Services/Prediction/OutcomePredictorTest.php` (NEW)
- `tests/Feature/Services/Prediction/OutcomePredictorIntegrationTest.php` (NEW)

---

### 3.4 Prediction API (4 hours)

**Task:** REST API for outcome predictions

**Files to modify:**
- `routes/api.php`
- `app/Http/Controllers/PredictionController.php` (NEW)

**Implementation:**

`app/Http/Controllers/PredictionController.php`:
```php
namespace App\Http\Controllers;

use App\Models\OutcomePrediction;
use App\Services\Prediction\OutcomePredictor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PredictionController extends Controller
{
    public function predict(Request $request, OutcomePredictor $predictor)
    {
        $validator = Validator::make($request->all(), [
            'case_facts' => 'required|string|min:50',
            'court_level' => 'nullable|string|in:municipal,county,supreme',
            'plaintiff_arguments' => 'nullable|array',
            'defendant_arguments' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $prediction = $predictor->predict($request->all());

            return response()->json([
                'success' => true,
                'prediction' => $prediction,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id)
    {
        $prediction = OutcomePrediction::findOrFail($id);

        return response()->json([
            'success' => true,
            'prediction' => $prediction,
        ]);
    }

    public function history(Request $request)
    {
        $predictions = OutcomePrediction::latest()
            ->paginate(20);

        return response()->json($predictions);
    }
}
```

Add to `routes/api.php`:
```php
Route::prefix('predictions')->group(function () {
    Route::post('/', [PredictionController::class, 'predict']);
    Route::get('/{id}', [PredictionController::class, 'show']);
    Route::get('/', [PredictionController::class, 'history']);
});
```

**Example API usage:**

```bash
curl -X POST http://localhost:8000/api/predictions \
  -H "Content-Type: application/json" \
  -d '{
    "case_facts": "Zaposlenik je radio 250 sati prekovremenog rada tijekom 2023. godine bez ikakve dodatne naknade. Poslodavac tvrdi da je prekovremeni rad bio dobrovoljan i da je zaposlenik bio upoznat s uvjetima.",
    "court_level": "county",
    "plaintiff_arguments": [
      "Poslodavac nije vodio evidenciju prekovremenog rada",
      "Zakon o radu jasno propisuje pravo na naknadu",
      "Postoje svjedoci koji potvrđuju prekovremeni rad"
    ],
    "defendant_arguments": [
      "Zaposlenik je bio na rukovodećoj poziciji",
      "Prekovremeni rad nije bio odobren u pisanom obliku"
    ]
  }'
```

**Response:**
```json
{
  "success": true,
  "prediction": {
    "id": "01HXXX...",
    "predicted_outcome": "plaintiff_wins",
    "confidence": 0.78,
    "reasoning": "Based on 15 similar cases, plaintiffs typically win when there is clear evidence of uncompensated overtime, especially when the employer failed to maintain proper records (required by Labor Law Article 94). The defendant's argument about managerial position is weak unless explicitly documented in the employment contract.",
    "winning_factors": [
      "Employer's failure to maintain overtime records (Labor Law violation)",
      "Witness testimony supporting overtime claims",
      "Clear legal right to compensation under Article 86"
    ],
    "risk_factors": [
      "Lack of written approval for overtime",
      "Potential statute of limitations issues (claims older than 3 years)"
    ],
    "similar_cases": ["01HYYY...", "01HZZZ..."],
    "confidence": 0.78
  }
}
```

---

### 3.5 Prediction Accuracy Tracking (4 hours)

**Task:** Track prediction accuracy when outcomes are known

**Files to modify:**
- `database/migrations/2025_10_28_140000_create_outcome_predictions_table.php` (add columns)
- `app/Services/Prediction/AccuracyTracker.php` (NEW)

**Schema additions:**
```php
// Add to outcome_predictions table:
$table->string('actual_outcome')->nullable(); // Filled in later when known
$table->boolean('prediction_correct')->nullable();
$table->timestamp('outcome_known_at')->nullable();
```

`app/Services/Prediction/AccuracyTracker.php`:
```php
namespace App\Services\Prediction;

use App\Models\OutcomePrediction;
use Illuminate\Support\Facades\DB;

class AccuracyTracker
{
    public function recordActualOutcome(string $predictionId, string $actualOutcome): void
    {
        $prediction = OutcomePrediction::findOrFail($predictionId);

        $prediction->update([
            'actual_outcome' => $actualOutcome,
            'prediction_correct' => $prediction->predicted_outcome === $actualOutcome,
            'outcome_known_at' => now(),
        ]);
    }

    public function getAccuracyStats(): array
    {
        $total = OutcomePrediction::whereNotNull('actual_outcome')->count();
        $correct = OutcomePrediction::where('prediction_correct', true)->count();

        $accuracy = $total > 0 ? ($correct / $total) * 100 : 0;

        $byConfidence = DB::table('outcome_predictions')
            ->selectRaw('
                CASE
                    WHEN confidence >= 0.8 THEN "high"
                    WHEN confidence >= 0.6 THEN "medium"
                    ELSE "low"
                END as confidence_level,
                COUNT(*) as total,
                SUM(CASE WHEN prediction_correct THEN 1 ELSE 0 END) as correct
            ')
            ->whereNotNull('actual_outcome')
            ->groupBy('confidence_level')
            ->get();

        return [
            'overall_accuracy' => round($accuracy, 2),
            'total_predictions' => $total,
            'correct_predictions' => $correct,
            'by_confidence' => $byConfidence,
        ];
    }
}
```

---

## FEATURE 4: JUDGE ANALYTICS

**Goal:** Track individual judge patterns, tendencies, and ruling statistics

**Estimated Effort:** 20-24 hours

### 4.1 Database Schema for Judge Stats (2 hours)

**Task:** Aggregate judge statistics

**Files to create:**
- `database/migrations/2025_10_28_150000_create_judge_statistics_table.php` (NEW)

**Schema:**
```php
Schema::create('judge_statistics', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('judge_name')->unique();
    $table->string('primary_court')->nullable();
    $table->integer('total_decisions')->default(0);
    $table->integer('plaintiff_wins')->default(0);
    $table->integer('defendant_wins')->default(0);
    $table->integer('partial_outcomes')->default(0);
    $table->float('plaintiff_win_rate')->default(0); // %
    $table->json('legal_areas')->nullable(); // ['labor_law' => 45, 'contract_law' => 30]
    $table->json('decision_types')->nullable(); // ['presuda' => 80, 'rješenje' => 20]
    $table->float('average_precedent_strength')->nullable(); // Avg of decisions they authored
    $table->integer('cited_by_others')->default(0); // How many times their decisions are cited
    $table->json('notable_decisions')->nullable(); // Top 5 most important decisions
    $table->timestamp('last_calculated_at')->nullable();
    $table->timestamps();
});
```

---

### 4.2 Judge Statistics Calculator (10 hours)

**Task:** Calculate comprehensive judge metrics

**Files to create:**
- `app/Services/Judge/JudgeStatisticsCalculator.php` (NEW)
- `app/Models/JudgeStatistic.php` (NEW)
- `app/Console/Commands/CalculateJudgeStatistics.php` (NEW)

**Implementation:**

`app/Services/Judge/JudgeStatisticsCalculator.php`:
```php
namespace App\Services\Judge;

use App\Models\CourtDecision;
use App\Models\JudgeStatistic;
use App\Services\Arguments\OutcomeDetector;
use App\Services\Neo4jService;
use Illuminate\Support\Facades\DB;

class JudgeStatisticsCalculator
{
    public function __construct(
        protected OutcomeDetector $outcomeDetector,
        protected Neo4jService $neo4j
    ) {}

    public function calculateAll(): void
    {
        $judges = CourtDecision::whereNotNull('judge')
            ->distinct()
            ->pluck('judge');

        foreach ($judges as $judgeName) {
            $this->calculateForJudge($judgeName);
        }
    }

    public function calculateForJudge(string $judgeName): JudgeStatistic
    {
        $decisions = CourtDecision::where('judge', $judgeName)->get();

        if ($decisions->isEmpty()) {
            throw new \Exception("No decisions found for judge: {$judgeName}");
        }

        // 1. Basic counts
        $total = $decisions->count();
        $primaryCourt = $decisions->pluck('court')->mode()[0] ?? null;

        // 2. Outcome distribution
        $outcomes = $this->calculateOutcomes($decisions);

        // 3. Legal area distribution
        $legalAreas = $this->calculateLegalAreas($decisions);

        // 4. Decision type distribution
        $decisionTypes = $decisions->pluck('decision_type')
            ->filter()
            ->countBy()
            ->toArray();

        // 5. Average precedent strength
        $avgPrecedentStrength = $decisions->where('precedent_strength_score', '>', 0)
            ->avg('precedent_strength_score');

        // 6. Citation count (how many times their decisions are cited)
        $citedByOthers = $this->calculateCitationCount($decisions);

        // 7. Notable decisions (top 5 by precedent strength)
        $notableDecisions = $decisions->where('precedent_strength_score', '>', 0)
            ->sortByDesc('precedent_strength_score')
            ->take(5)
            ->map(fn($d) => [
                'id' => $d->id,
                'case_number' => $d->case_number,
                'title' => $d->title,
                'precedent_strength' => $d->precedent_strength_score,
                'decision_date' => $d->decision_date,
            ])
            ->values()
            ->toArray();

        // 8. Store/update statistics
        $stats = JudgeStatistic::updateOrCreate(
            ['judge_name' => $judgeName],
            [
                'primary_court' => $primaryCourt,
                'total_decisions' => $total,
                'plaintiff_wins' => $outcomes['plaintiff_wins'],
                'defendant_wins' => $outcomes['defendant_wins'],
                'partial_outcomes' => $outcomes['partial'],
                'plaintiff_win_rate' => $outcomes['plaintiff_win_rate'],
                'legal_areas' => $legalAreas,
                'decision_types' => $decisionTypes,
                'average_precedent_strength' => round($avgPrecedentStrength ?? 0, 2),
                'cited_by_others' => $citedByOthers,
                'notable_decisions' => $notableDecisions,
                'last_calculated_at' => now(),
            ]
        );

        return $stats;
    }

    protected function calculateOutcomes($decisions): array
    {
        $plaintiffWins = 0;
        $defendantWins = 0;
        $partial = 0;

        foreach ($decisions as $decision) {
            $outcome = $this->outcomeDetector->detectOutcome(
                $decision->description ?? '',
                $decision
            );

            match($outcome) {
                'plaintiff_wins' => $plaintiffWins++,
                'defendant_wins' => $defendantWins++,
                'partial' => $partial++,
                default => null,
            };
        }

        $total = $plaintiffWins + $defendantWins + $partial;
        $winRate = $total > 0 ? ($plaintiffWins / $total) * 100 : 0;

        return [
            'plaintiff_wins' => $plaintiffWins,
            'defendant_wins' => $defendantWins,
            'partial' => $partial,
            'plaintiff_win_rate' => round($winRate, 2),
        ];
    }

    protected function calculateLegalAreas($decisions): array
    {
        // Infer legal area from decision text/tags
        $areas = [];

        foreach ($decisions as $decision) {
            $area = $this->inferLegalArea($decision);
            $areas[$area] = ($areas[$area] ?? 0) + 1;
        }

        arsort($areas);
        return $areas;
    }

    protected function inferLegalArea(CourtDecision $decision): string
    {
        $title = strtolower($decision->title ?? '');
        $description = strtolower($decision->description ?? '');
        $text = $title . ' ' . $description;

        if (str_contains($text, 'radni odnos') || str_contains($text, 'zakon o radu')) {
            return 'labor_law';
        }
        if (str_contains($text, 'ugovor') || str_contains($text, 'obvezno pravo')) {
            return 'contract_law';
        }
        if (str_contains($text, 'vlasništvo') || str_contains($text, 'nekretnina')) {
            return 'property_law';
        }
        if (str_contains($text, 'upravni')) {
            return 'administrative_law';
        }
        if (str_contains($text, 'kazneni')) {
            return 'criminal_law';
        }

        return 'general';
    }

    protected function calculateCitationCount($decisions): int
    {
        $total = 0;

        foreach ($decisions as $decision) {
            try {
                $count = $this->neo4j->getCitationCount($decision->id);
                $total += $count;
            } catch (\Exception $e) {
                // Neo4j might not be enabled
                continue;
            }
        }

        return $total;
    }
}
```

**Console command:**

`app/Console/Commands/CalculateJudgeStatistics.php`:
```php
namespace App\Console\Commands;

use App\Services\Judge\JudgeStatisticsCalculator;
use Illuminate\Console\Command;

class CalculateJudgeStatistics extends Command
{
    protected $signature = 'judges:calculate-stats {judge? : Specific judge name}';
    protected $description = 'Calculate statistics for judges';

    public function handle(JudgeStatisticsCalculator $calculator): int
    {
        if ($judgeName = $this->argument('judge')) {
            $this->info("Calculating stats for: {$judgeName}");
            $calculator->calculateForJudge($judgeName);
            $this->info('Done.');
        } else {
            $this->info('Calculating stats for all judges...');
            $calculator->calculateAll();
            $this->info('All judge statistics calculated.');
        }

        return 0;
    }
}
```

Add to `routes/console.php`:
```php
Schedule::command('judges:calculate-stats')->weekly();
```

---

### 4.3 Judge Analytics API (4 hours)

**Task:** API for judge statistics

**Files to modify:**
- `routes/api.php`
- `app/Http/Controllers/JudgeController.php` (NEW)

**Implementation:**

`app/Http/Controllers/JudgeController.php`:
```php
namespace App\Http\Controllers;

use App\Models\JudgeStatistic;
use App\Services\Judge\JudgeStatisticsCalculator;
use Illuminate\Http\Request;

class JudgeController extends Controller
{
    public function index(Request $request)
    {
        $query = JudgeStatistic::query();

        // Filter by court
        if ($court = $request->get('court')) {
            $query->where('primary_court', 'like', "%{$court}%");
        }

        // Sort options
        $sortBy = $request->get('sort_by', 'total_decisions');
        $sortDir = $request->get('sort_dir', 'desc');

        $query->orderBy($sortBy, $sortDir);

        $judges = $query->paginate(20);

        return response()->json($judges);
    }

    public function show(string $judgeName, JudgeStatisticsCalculator $calculator)
    {
        $stats = JudgeStatistic::where('judge_name', $judgeName)->first();

        if (!$stats) {
            // Calculate on-demand
            $stats = $calculator->calculateForJudge($judgeName);
        }

        // Get recent decisions by this judge
        $recentDecisions = \App\Models\CourtDecision::where('judge', $judgeName)
            ->orderBy('decision_date', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'statistics' => $stats,
            'recent_decisions' => $recentDecisions,
        ]);
    }

    public function compare(Request $request)
    {
        $judgeNames = $request->get('judges', []); // Array of judge names

        if (count($judgeNames) < 2) {
            return response()->json(['error' => 'Provide at least 2 judges to compare'], 400);
        }

        $stats = JudgeStatistic::whereIn('judge_name', $judgeNames)->get();

        $comparison = [
            'judges' => $stats->map(function ($judge) {
                return [
                    'name' => $judge->judge_name,
                    'court' => $judge->primary_court,
                    'total_decisions' => $judge->total_decisions,
                    'plaintiff_win_rate' => $judge->plaintiff_win_rate,
                    'avg_precedent_strength' => $judge->average_precedent_strength,
                    'cited_by_others' => $judge->cited_by_others,
                ];
            }),
            'averages' => [
                'plaintiff_win_rate' => $stats->avg('plaintiff_win_rate'),
                'avg_precedent_strength' => $stats->avg('average_precedent_strength'),
                'total_decisions' => $stats->avg('total_decisions'),
            ],
        ];

        return response()->json($comparison);
    }

    public function topJudges(Request $request)
    {
        $metric = $request->get('metric', 'average_precedent_strength');
        $limit = min((int) $request->get('limit', 10), 50);

        $judges = JudgeStatistic::query()
            ->where($metric, '>', 0)
            ->orderByDesc($metric)
            ->limit($limit)
            ->get();

        return response()->json([
            'metric' => $metric,
            'top_judges' => $judges,
        ]);
    }
}
```

Add to `routes/api.php`:
```php
Route::prefix('judges')->group(function () {
    Route::get('/', [JudgeController::class, 'index']);
    Route::get('/{judgeName}', [JudgeController::class, 'show']);
    Route::post('/compare', [JudgeController::class, 'compare']);
    Route::get('/top', [JudgeController::class, 'topJudges']);
});
```

---

### 4.4 Judge Search & Filtering (4 hours)

**Task:** Advanced judge search capabilities

**Files to modify:**
- `app/Http/Controllers/JudgeController.php` (add search method)

**Implementation:**

Add to `JudgeController.php`:
```php
public function search(Request $request)
{
    $query = JudgeStatistic::query();

    // Search by name
    if ($name = $request->get('name')) {
        $query->where('judge_name', 'like', "%{$name}%");
    }

    // Filter by win rate range
    if ($minWinRate = $request->get('min_win_rate')) {
        $query->where('plaintiff_win_rate', '>=', $minWinRate);
    }
    if ($maxWinRate = $request->get('max_win_rate')) {
        $query->where('plaintiff_win_rate', '<=', $maxWinRate);
    }

    // Filter by minimum decisions (experience)
    if ($minDecisions = $request->get('min_decisions')) {
        $query->where('total_decisions', '>=', $minDecisions);
    }

    // Filter by legal area expertise
    if ($legalArea = $request->get('legal_area')) {
        $query->whereJsonContains('legal_areas', [$legalArea]);
    }

    // Filter by precedent strength
    if ($minPrecedent = $request->get('min_precedent_strength')) {
        $query->where('average_precedent_strength', '>=', $minPrecedent);
    }

    $judges = $query->paginate(20);

    return response()->json($judges);
}
```

Add route:
```php
Route::get('/judges/search', [JudgeController::class, 'search']);
```

---

## CROSS-CUTTING TASKS

### A. Documentation (8 hours)

**Files to create/modify:**
- `docs/LEVEL_2_STRATEGIC_ADVISOR.md` (NEW - comprehensive guide)
- `docs/API_PRECEDENT_SCORING.md` (NEW)
- `docs/API_ARGUMENT_PATTERNS.md` (NEW)
- `docs/API_OUTCOME_PREDICTION.md` (NEW)
- `docs/API_JUDGE_ANALYTICS.md` (NEW)
- `README.md` (update with Level 2 features)

**Contents:**
- Feature overviews
- API endpoint documentation with examples
- Database schema diagrams
- Usage tutorials
- Interpretation guidelines (how to read scores/predictions)

---

### B. Testing Suite (12 hours)

**Files to create:**
- `tests/Feature/Precedent/PrecedentScoringTest.php`
- `tests/Feature/Arguments/ArgumentExtractionTest.php`
- `tests/Feature/Prediction/OutcomePredictionTest.php`
- `tests/Feature/Judge/JudgeAnalyticsTest.php`
- Plus ~15 unit tests for individual services

**Coverage goals:**
- Precedent scoring algorithm: 90%+
- Argument extraction: 85%+
- Outcome prediction: 80%+
- Judge statistics: 85%+

---

### C. UI Dashboard (Optional, 20-30 hours)

**If building a web interface:**

**Files to create:**
- `resources/views/precedents/index.blade.php`
- `resources/views/precedents/show.blade.php`
- `resources/views/arguments/patterns.blade.php`
- `resources/views/predictions/create.blade.php`
- `resources/views/judges/index.blade.php`
- `resources/views/judges/compare.blade.php`

**Features:**
- Precedent explorer with strength scores
- Argument pattern browser
- Interactive outcome prediction form
- Judge comparison tool
- Charts & visualizations (Chart.js or similar)

---

## IMPLEMENTATION ORDER

**Recommended phased approach:**

### Phase 1 (Weeks 1-2): Precedent Strength Scoring
1. Database migrations (3h)
2. Citation extraction (8h)
3. Neo4j citation graph (6h)
4. Strength calculator (8h)
5. Commands & API (5h)
**Total: ~30 hours**

### Phase 2 (Weeks 3-4): Argument Mapping
1. Database schema (2h)
2. Argument extractor (10h)
3. Pattern detector (8h)
4. API endpoints (4h)
**Total: ~24 hours**

### Phase 3 (Weeks 5-7): Outcome Prediction
1. Database schema (2h)
2. Similarity finder (10h)
3. Outcome predictor (12h)
4. API & tracking (8h)
**Total: ~32 hours**

### Phase 4 (Week 8): Judge Analytics
1. Database schema (2h)
2. Statistics calculator (10h)
3. API endpoints (8h)
**Total: ~20 hours**

### Phase 5 (Week 9-10): Polish & Testing
1. Documentation (8h)
2. Testing suite (12h)
**Total: ~20 hours**

---

## TOTAL EFFORT ESTIMATE

| Feature | Hours |
|---------|-------|
| Precedent Strength Scoring | 30 |
| Argument Mapping | 24 |
| Outcome Prediction | 32 |
| Judge Analytics | 20 |
| Documentation | 8 |
| Testing | 12 |
| **TOTAL** | **126 hours** |

**Timeline:**
- Full-time (40h/week): ~3 weeks
- Part-time (20h/week): ~6 weeks
- Side project (10h/week): ~12 weeks (3 months)

---

## SUCCESS METRICS

### Technical Metrics:
- ✅ Precedent scoring accuracy: 85%+ validated by legal experts
- ✅ Argument extraction F1 score: 80%+
- ✅ Outcome prediction accuracy: 70%+ (on test cases with known outcomes)
- ✅ API response time: <2s for all endpoints
- ✅ Test coverage: 85%+ overall

### Business Metrics:
- ✅ User adoption: 10+ law firms using prediction API
- ✅ Research time savings: 30%+ reduction vs manual methods
- ✅ User satisfaction: 8/10+ average rating

---

## DEPENDENCIES & PREREQUISITES

**Before starting:**
1. ✅ OpenAI API key with GPT-4 access (for argument extraction & prediction)
2. ✅ Neo4j database operational (for citation graphs)
3. ✅ At least 1,000 court decisions ingested (for meaningful statistics)
4. ✅ PostgreSQL with pgvector extension (already in place)

**External libraries (may need installation):**
```bash
composer require laudis/neo4j-php-client  # Already installed
composer require openai-php/client  # Check if already installed
```

---

## RISKS & MITIGATIONS

| Risk | Mitigation |
|------|------------|
| LLM hallucination in predictions | Ground all predictions in real cases; show confidence scores; add disclaimer |
| Low prediction accuracy initially | Start with 70% threshold; improve over time with feedback |
| High OpenAI API costs | Cache embeddings; use GPT-4o-mini for most tasks; batch processing |
| Citation extraction errors | Validate ECLI codes; manual review sample; user feedback loop |
| Judge statistics privacy concerns | Aggregate public data only; anonymize if needed; follow GDPR |

---

## NEXT STEPS

1. **Review this document** with stakeholders
2. **Prioritize features** (can implement in any order)
3. **Set up development branch** (e.g., `feature/level-2-strategic-advisor`)
4. **Start with Phase 1** (Precedent Scoring) - most foundational
5. **Iterate based on feedback** from early users

---

**Ready to begin implementation? Let me know which feature to start with!**
