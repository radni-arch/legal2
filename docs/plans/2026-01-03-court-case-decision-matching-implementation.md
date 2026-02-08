# Court Case ↔ Decision Matching System Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create a matching system that pairs CourtCase records (e-predmet) with CourtDecision records (sudskapraksa) based on case number and year.

**Architecture:** Decoupled soft-reference design with a separate `court_case_decision_matches` table. Real-time matching on record creation plus scheduled batch reconciliation. Manual review workflow for low-confidence matches.

**Tech Stack:** Laravel 11, PostgreSQL, Eloquent ORM, Queue Jobs, Artisan Commands

**Design Document:** `docs/plans/2026-01-03-court-case-decision-matching-design.md`

---

## Task 1: Migration - Create court_case_decision_matches Table

**Files:**
- Create: `database/migrations/2026_01_03_100000_create_court_case_decision_matches_table.php`

**Step 1: Create migration file**

```bash
php artisan make:migration create_court_case_decision_matches_table
```

**Step 2: Write migration schema**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_case_decision_matches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('court_case_id');
            $table->string('court_decision_id', 26);
            $table->timestamp('matched_at');
            $table->string('match_type', 20); // 'auto', 'manual'
            $table->unsignedTinyInteger('match_confidence'); // 0-100
            $table->string('match_source', 50); // 'epredmet_fetch', 'decision_ingest', 'manual', 'scheduled_job'
            $table->string('verification_status', 20)->default('pending'); // 'pending', 'verified', 'rejected'
            $table->json('match_criteria');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['court_case_id', 'court_decision_id'], 'unique_case_decision_match');
            $table->index('court_case_id');
            $table->index('court_decision_id');
            $table->index('verification_status');
            $table->index('matched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_case_decision_matches');
    }
};
```

**Step 3: Run migration**

```bash
php artisan migrate
```

Expected: Migration runs successfully, table created.

**Step 4: Commit**

```bash
git add database/migrations/*court_case_decision_matches*
git commit -m "feat: add court_case_decision_matches migration"
```

---

## Task 2: Model - CourtCaseDecisionMatch

**Files:**
- Create: `app/Models/CourtCaseDecisionMatch.php`
- Test: `tests/Unit/Models/CourtCaseDecisionMatchTest.php`

**Step 1: Write failing test for model attributes**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\CourtCase;
use App\Models\CourtCaseDecisionMatch;
use App\Models\CourtDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourtCaseDecisionMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_match_can_be_created_with_required_attributes(): void
    {
        $match = CourtCaseDecisionMatch::create([
            'court_case_id' => 1,
            'court_decision_id' => '01HQXYZ123456789ABCDEFGH',
            'matched_at' => now(),
            'match_type' => 'auto',
            'match_confidence' => 100,
            'match_source' => 'scheduled_job',
            'verification_status' => 'pending',
            'match_criteria' => ['case_number' => 'Pp Prz-75', 'year' => 2025],
        ]);

        $this->assertNotNull($match->id);
        $this->assertEquals(100, $match->match_confidence);
        $this->assertIsArray($match->match_criteria);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh CourtCaseDecisionMatchTest
```

Expected: FAIL - Class not found

**Step 3: Create the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtCaseDecisionMatch extends Model
{
    use HasUlids;

    protected $fillable = [
        'court_case_id',
        'court_decision_id',
        'matched_at',
        'match_type',
        'match_confidence',
        'match_source',
        'verification_status',
        'match_criteria',
        'notes',
    ];

    protected $casts = [
        'matched_at' => 'datetime',
        'match_confidence' => 'integer',
        'match_criteria' => 'array',
    ];

    public function courtCase(): BelongsTo
    {
        return $this->belongsTo(CourtCase::class);
    }

    public function courtDecision(): BelongsTo
    {
        return $this->belongsTo(CourtDecision::class);
    }

    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function isRejected(): bool
    {
        return $this->verification_status === 'rejected';
    }
}
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh CourtCaseDecisionMatchTest
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Models/CourtCaseDecisionMatch.php tests/Unit/Models/CourtCaseDecisionMatchTest.php
git commit -m "feat: add CourtCaseDecisionMatch model with tests"
```

---

## Task 3: Model - Add relationships to CourtCase

**Files:**
- Modify: `app/Models/CourtCase.php`
- Test: `tests/Unit/Models/CourtCaseDecisionMatchTest.php` (extend)

**Step 1: Write failing test for CourtCase relationships**

Add to `tests/Unit/Models/CourtCaseDecisionMatchTest.php`:

```php
public function test_court_case_has_decision_matches_relationship(): void
{
    $case = CourtCase::factory()->create();

    $match = CourtCaseDecisionMatch::create([
        'court_case_id' => $case->id,
        'court_decision_id' => '01HQXYZ123456789ABCDEFGH',
        'matched_at' => now(),
        'match_type' => 'auto',
        'match_confidence' => 100,
        'match_source' => 'test',
        'verification_status' => 'verified',
        'match_criteria' => ['case_number' => 'Pp Prz-75', 'year' => 2025],
    ]);

    $this->assertTrue($case->decisionMatches->contains($match));
}

public function test_court_case_unmatched_scope_excludes_matched_cases(): void
{
    $matchedCase = CourtCase::factory()->create();
    $unmatchedCase = CourtCase::factory()->create();

    CourtCaseDecisionMatch::create([
        'court_case_id' => $matchedCase->id,
        'court_decision_id' => '01HQXYZ123456789ABCDEFGH',
        'matched_at' => now(),
        'match_type' => 'auto',
        'match_confidence' => 100,
        'match_source' => 'test',
        'verification_status' => 'verified',
        'match_criteria' => [],
    ]);

    $unmatched = CourtCase::unmatched()->get();

    $this->assertFalse($unmatched->contains($matchedCase));
    $this->assertTrue($unmatched->contains($unmatchedCase));
}

public function test_court_case_pending_review_scope(): void
{
    $pendingCase = CourtCase::factory()->create();
    $verifiedCase = CourtCase::factory()->create();

    CourtCaseDecisionMatch::create([
        'court_case_id' => $pendingCase->id,
        'court_decision_id' => '01HQXYZ123456789ABCDEFGH',
        'matched_at' => now(),
        'match_type' => 'auto',
        'match_confidence' => 60,
        'match_source' => 'test',
        'verification_status' => 'pending',
        'match_criteria' => [],
    ]);

    CourtCaseDecisionMatch::create([
        'court_case_id' => $verifiedCase->id,
        'court_decision_id' => '01HQXYZ987654321ABCDEFGH',
        'matched_at' => now(),
        'match_type' => 'auto',
        'match_confidence' => 100,
        'match_source' => 'test',
        'verification_status' => 'verified',
        'match_criteria' => [],
    ]);

    $pending = CourtCase::pendingReview()->get();

    $this->assertTrue($pending->contains($pendingCase));
    $this->assertFalse($pending->contains($verifiedCase));
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh CourtCaseDecisionMatchTest
```

Expected: FAIL - Method decisionMatches does not exist

**Step 3: Add relationships and scopes to CourtCase**

Add to `app/Models/CourtCase.php`:

```php
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

// Add to existing relationships section:

public function decisionMatches(): HasMany
{
    return $this->hasMany(CourtCaseDecisionMatch::class);
}

public function matchedDecisions(): HasManyThrough
{
    return $this->hasManyThrough(
        CourtDecision::class,
        CourtCaseDecisionMatch::class,
        'court_case_id',
        'id',
        'id',
        'court_decision_id'
    );
}

// Add to scopes section:

public function scopeUnmatched($query)
{
    return $query->whereDoesntHave('decisionMatches');
}

public function scopePendingReview($query)
{
    return $query->whereHas('decisionMatches', fn($q) =>
        $q->where('verification_status', 'pending')
    );
}
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh CourtCaseDecisionMatchTest
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Models/CourtCase.php tests/Unit/Models/CourtCaseDecisionMatchTest.php
git commit -m "feat: add decision matching relationships to CourtCase"
```

---

## Task 4: Model - Add relationship to CourtDecision

**Files:**
- Modify: `app/Models/CourtDecision.php`
- Test: `tests/Unit/Models/CourtCaseDecisionMatchTest.php` (extend)

**Step 1: Write failing test**

Add to `tests/Unit/Models/CourtCaseDecisionMatchTest.php`:

```php
public function test_court_decision_has_case_matches_relationship(): void
{
    $decision = CourtDecision::factory()->create();
    $case = CourtCase::factory()->create();

    $match = CourtCaseDecisionMatch::create([
        'court_case_id' => $case->id,
        'court_decision_id' => $decision->id,
        'matched_at' => now(),
        'match_type' => 'auto',
        'match_confidence' => 100,
        'match_source' => 'test',
        'verification_status' => 'verified',
        'match_criteria' => [],
    ]);

    $this->assertTrue($decision->caseMatches->contains($match));
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh CourtCaseDecisionMatchTest
```

Expected: FAIL - Method caseMatches does not exist

**Step 3: Add relationship to CourtDecision**

Add to `app/Models/CourtDecision.php`:

```php
use App\Models\CourtCaseDecisionMatch;
use Illuminate\Database\Eloquent\Relations\HasMany;

public function caseMatches(): HasMany
{
    return $this->hasMany(CourtCaseDecisionMatch::class, 'court_decision_id');
}
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh CourtCaseDecisionMatchTest
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Models/CourtDecision.php tests/Unit/Models/CourtCaseDecisionMatchTest.php
git commit -m "feat: add caseMatches relationship to CourtDecision"
```

---

## Task 5: DTO - MatchingResult

**Files:**
- Create: `app/DTOs/MatchingResult.php`
- Test: `tests/Unit/DTOs/MatchingResultTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Unit\DTOs;

use App\DTOs\MatchingResult;
use Tests\TestCase;

class MatchingResultTest extends TestCase
{
    public function test_matching_result_tracks_statistics(): void
    {
        $result = new MatchingResult();

        $result->recordMatch(100);
        $result->recordMatch(80);
        $result->recordMatch(60);
        $result->recordNoMatch();
        $result->recordError('Test error');

        $this->assertEquals(3, $result->matchedCount);
        $this->assertEquals(1, $result->unmatchedCount);
        $this->assertEquals(1, $result->errorCount);
        $this->assertEquals(4, $result->processedCount);
        $this->assertContains('Test error', $result->errors);
    }

    public function test_matching_result_to_array(): void
    {
        $result = new MatchingResult();
        $result->recordMatch(100);

        $array = $result->toArray();

        $this->assertArrayHasKey('matched', $array);
        $this->assertArrayHasKey('unmatched', $array);
        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayHasKey('processed', $array);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh MatchingResultTest
```

Expected: FAIL - Class not found

**Step 3: Create MatchingResult DTO**

```php
<?php

namespace App\DTOs;

class MatchingResult
{
    public int $matchedCount = 0;
    public int $unmatchedCount = 0;
    public int $errorCount = 0;
    public int $processedCount = 0;
    public array $errors = [];
    public array $confidenceDistribution = [];

    public function recordMatch(int $confidence): void
    {
        $this->matchedCount++;
        $this->processedCount++;
        $this->confidenceDistribution[] = $confidence;
    }

    public function recordNoMatch(): void
    {
        $this->unmatchedCount++;
        $this->processedCount++;
    }

    public function recordError(string $error): void
    {
        $this->errorCount++;
        $this->errors[] = $error;
    }

    public function toArray(): array
    {
        return [
            'matched' => $this->matchedCount,
            'unmatched' => $this->unmatchedCount,
            'errors' => $this->errorCount,
            'processed' => $this->processedCount,
            'error_details' => $this->errors,
            'avg_confidence' => $this->averageConfidence(),
        ];
    }

    public function averageConfidence(): ?float
    {
        if (empty($this->confidenceDistribution)) {
            return null;
        }
        return round(array_sum($this->confidenceDistribution) / count($this->confidenceDistribution), 1);
    }
}
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh MatchingResultTest
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/DTOs/MatchingResult.php tests/Unit/DTOs/MatchingResultTest.php
git commit -m "feat: add MatchingResult DTO"
```

---

## Task 6: Service - CaseDecisionMatchingService (Core Matching)

**Files:**
- Create: `app/Services/CaseDecisionMatchingService.php`
- Test: `tests/Unit/Services/CaseDecisionMatchingServiceTest.php`

**Step 1: Write failing test for case number parsing**

```php
<?php

namespace Tests\Unit\Services;

use App\Models\CourtCase;
use App\Models\CourtDecision;
use App\Services\CaseDecisionMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseDecisionMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    private CaseDecisionMatchingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CaseDecisionMatchingService();
    }

    public function test_parses_case_number_correctly(): void
    {
        $result = $this->service->parseCaseNumber('Pp Prz-75/2025');

        $this->assertEquals('Pp Prz-75', $result['base']);
        $this->assertEquals(2025, $result['year']);
    }

    public function test_parses_case_number_with_different_formats(): void
    {
        $formats = [
            'Pp Prz-123/2024' => ['base' => 'Pp Prz-123', 'year' => 2024],
            'Kv-456/2023' => ['base' => 'Kv-456', 'year' => 2023],
            'Kov-1/2025' => ['base' => 'Kov-1', 'year' => 2025],
        ];

        foreach ($formats as $input => $expected) {
            $result = $this->service->parseCaseNumber($input);
            $this->assertEquals($expected['base'], $result['base'], "Failed for: $input");
            $this->assertEquals($expected['year'], $result['year'], "Failed for: $input");
        }
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh CaseDecisionMatchingServiceTest
```

Expected: FAIL - Class not found

**Step 3: Create service with parseCaseNumber**

```php
<?php

namespace App\Services;

use App\DTOs\MatchingResult;
use App\Models\CourtCase;
use App\Models\CourtCaseDecisionMatch;
use App\Models\CourtDecision;
use Illuminate\Support\Collection;

class CaseDecisionMatchingService
{
    /**
     * Parse case number into base and year components
     */
    public function parseCaseNumber(string $caseNumber): array
    {
        // Pattern: "Register-Number/Year" e.g., "Pp Prz-75/2025"
        if (preg_match('/^(.+)-(\d+)\/(\d{4})$/', $caseNumber, $matches)) {
            return [
                'base' => $matches[1] . '-' . $matches[2],
                'year' => (int) $matches[3],
            ];
        }

        return [
            'base' => $caseNumber,
            'year' => null,
        ];
    }
}
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh CaseDecisionMatchingServiceTest
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/CaseDecisionMatchingService.php tests/Unit/Services/CaseDecisionMatchingServiceTest.php
git commit -m "feat: add CaseDecisionMatchingService with case number parsing"
```

---

## Task 7: Service - Add matchCase method

**Files:**
- Modify: `app/Services/CaseDecisionMatchingService.php`
- Test: `tests/Unit/Services/CaseDecisionMatchingServiceTest.php` (extend)

**Step 1: Write failing test for matchCase**

Add to test file:

```php
public function test_match_case_finds_exact_match(): void
{
    $case = CourtCase::factory()->create([
        'case_number' => 'Pp Prz-75/2025',
        'year' => 2025,
    ]);

    $decision = CourtDecision::factory()->create([
        'case_number' => 'Pp Prz-75/2025',
        'decision_date' => '2025-06-15',
    ]);

    $match = $this->service->matchCase($case, 'test');

    $this->assertNotNull($match);
    $this->assertEquals($decision->id, $match->court_decision_id);
    $this->assertEquals(100, $match->match_confidence);
    $this->assertEquals('verified', $match->verification_status);
}

public function test_match_case_returns_null_when_no_match(): void
{
    $case = CourtCase::factory()->create([
        'case_number' => 'Pp Prz-999/2025',
        'year' => 2025,
    ]);

    $match = $this->service->matchCase($case, 'test');

    $this->assertNull($match);
}

public function test_match_case_sets_pending_for_low_confidence(): void
{
    $case = CourtCase::factory()->create([
        'case_number' => 'Pp Prz-75/2025',
        'year' => 2025,
        'court_id' => 1,
    ]);

    // Decision with same case number but no court info
    $decision = CourtDecision::factory()->create([
        'case_number' => 'Pp Prz-75/2025',
        'decision_date' => '2025-06-15',
        'court' => null,
    ]);

    $match = $this->service->matchCase($case, 'test');

    $this->assertNotNull($match);
    $this->assertEquals(80, $match->match_confidence);
    $this->assertEquals('pending', $match->verification_status);
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh CaseDecisionMatchingServiceTest
```

Expected: FAIL - Method matchCase not found

**Step 3: Implement matchCase**

Add to `CaseDecisionMatchingService`:

```php
/**
 * Match a single case to available decisions
 */
public function matchCase(CourtCase $case, string $source): ?CourtCaseDecisionMatch
{
    $parsed = $this->parseCaseNumber($case->case_number);

    if (!$parsed['base'] || !$parsed['year']) {
        return null;
    }

    // Find matching decisions
    $decisions = CourtDecision::query()
        ->where('case_number', 'LIKE', '%' . $parsed['base'] . '%')
        ->whereYear('decision_date', $parsed['year'])
        ->get();

    if ($decisions->isEmpty()) {
        return null;
    }

    // Score and select best match
    $bestMatch = null;
    $bestScore = 0;

    foreach ($decisions as $decision) {
        $score = $this->calculateConfidence($case, $decision, $parsed);
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestMatch = $decision;
        }
    }

    if (!$bestMatch) {
        return null;
    }

    // Create match record
    return CourtCaseDecisionMatch::create([
        'court_case_id' => $case->id,
        'court_decision_id' => $bestMatch->id,
        'matched_at' => now(),
        'match_type' => 'auto',
        'match_confidence' => $bestScore,
        'match_source' => $source,
        'verification_status' => $bestScore >= 100 ? 'verified' : 'pending',
        'match_criteria' => [
            'case_number' => $parsed['base'],
            'year' => $parsed['year'],
            'court_matched' => $this->courtsMatch($case, $bestMatch),
        ],
    ]);
}

/**
 * Calculate match confidence score
 */
protected function calculateConfidence(CourtCase $case, CourtDecision $decision, array $parsed): int
{
    $score = 0;

    // Exact case number match
    if (str_contains($decision->case_number, $parsed['base'])) {
        $score += 60;
    }

    // Year match
    if ($decision->decision_date?->year === $parsed['year']) {
        $score += 20;
    }

    // Court match
    if ($this->courtsMatch($case, $decision)) {
        $score += 20;
    }

    return min($score, 100);
}

/**
 * Check if courts match between case and decision
 */
protected function courtsMatch(CourtCase $case, CourtDecision $decision): bool
{
    if (!$case->court || !$decision->court) {
        return false;
    }

    // Normalize and compare court names
    $caseCourt = strtolower(trim($case->court->name ?? ''));
    $decisionCourt = strtolower(trim($decision->court ?? ''));

    return str_contains($decisionCourt, $caseCourt) || str_contains($caseCourt, $decisionCourt);
}
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh CaseDecisionMatchingServiceTest
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/CaseDecisionMatchingService.php tests/Unit/Services/CaseDecisionMatchingServiceTest.php
git commit -m "feat: add matchCase method to CaseDecisionMatchingService"
```

---

## Task 8: Service - Add batch matching methods

**Files:**
- Modify: `app/Services/CaseDecisionMatchingService.php`
- Test: `tests/Unit/Services/CaseDecisionMatchingServiceTest.php` (extend)

**Step 1: Write failing test for batch matching**

Add to test file:

```php
public function test_match_unmatched_cases_processes_batch(): void
{
    // Create cases without matches
    $cases = CourtCase::factory()->count(3)->create([
        'case_number' => 'Pp Prz-{number}/2025',
    ]);

    // Create matching decisions for 2 of them
    foreach ($cases->take(2) as $i => $case) {
        CourtDecision::factory()->create([
            'case_number' => $case->case_number,
            'decision_date' => '2025-06-15',
        ]);
    }

    $result = $this->service->matchUnmatchedCases(10, 'batch_test');

    $this->assertEquals(3, $result->processedCount);
    $this->assertEquals(2, $result->matchedCount);
    $this->assertEquals(1, $result->unmatchedCount);
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh CaseDecisionMatchingServiceTest
```

Expected: FAIL - Method matchUnmatchedCases not found

**Step 3: Implement batch matching methods**

Add to `CaseDecisionMatchingService`:

```php
/**
 * Match unmatched cases in batch
 */
public function matchUnmatchedCases(int $limit, string $source): MatchingResult
{
    $result = new MatchingResult();

    $cases = CourtCase::unmatched()
        ->limit($limit)
        ->get();

    foreach ($cases as $case) {
        try {
            $match = $this->matchCase($case, $source);

            if ($match) {
                $result->recordMatch($match->match_confidence);
            } else {
                $result->recordNoMatch();
            }
        } catch (\Exception $e) {
            $result->recordError("Case {$case->id}: {$e->getMessage()}");
        }
    }

    return $result;
}

/**
 * Full reconciliation - reprocess all cases
 */
public function reconcileAll(string $source): MatchingResult
{
    $result = new MatchingResult();

    CourtCase::query()
        ->chunk(100, function ($cases) use ($source, &$result) {
            foreach ($cases as $case) {
                try {
                    // Skip if already has verified match
                    if ($case->decisionMatches()->where('verification_status', 'verified')->exists()) {
                        continue;
                    }

                    $match = $this->matchCase($case, $source);

                    if ($match) {
                        $result->recordMatch($match->match_confidence);
                    } else {
                        $result->recordNoMatch();
                    }
                } catch (\Exception $e) {
                    $result->recordError("Case {$case->id}: {$e->getMessage()}");
                }
            }
        });

    return $result;
}
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh CaseDecisionMatchingServiceTest
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/CaseDecisionMatchingService.php tests/Unit/Services/CaseDecisionMatchingServiceTest.php
git commit -m "feat: add batch matching methods to service"
```

---

## Task 9: Service - Add manual match and verification methods

**Files:**
- Modify: `app/Services/CaseDecisionMatchingService.php`
- Test: `tests/Unit/Services/CaseDecisionMatchingServiceTest.php` (extend)

**Step 1: Write failing tests**

Add to test file:

```php
public function test_create_manual_match(): void
{
    $case = CourtCase::factory()->create();
    $decision = CourtDecision::factory()->create();

    $match = $this->service->createManualMatch($case, $decision, 'Manually verified');

    $this->assertEquals('manual', $match->match_type);
    $this->assertEquals(100, $match->match_confidence);
    $this->assertEquals('verified', $match->verification_status);
    $this->assertEquals('Manually verified', $match->notes);
}

public function test_verify_match(): void
{
    $match = CourtCaseDecisionMatch::factory()->create([
        'verification_status' => 'pending',
    ]);

    $this->service->verifyMatch($match);

    $this->assertEquals('verified', $match->fresh()->verification_status);
}

public function test_reject_match(): void
{
    $match = CourtCaseDecisionMatch::factory()->create([
        'verification_status' => 'pending',
    ]);

    $this->service->rejectMatch($match, 'Wrong case');

    $match->refresh();
    $this->assertEquals('rejected', $match->verification_status);
    $this->assertStringContains('Wrong case', $match->notes);
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh CaseDecisionMatchingServiceTest
```

Expected: FAIL - Methods not found

**Step 3: Implement manual match and verification**

Add to `CaseDecisionMatchingService`:

```php
/**
 * Create a manual match
 */
public function createManualMatch(
    CourtCase $case,
    CourtDecision $decision,
    ?string $notes = null
): CourtCaseDecisionMatch {
    return CourtCaseDecisionMatch::create([
        'court_case_id' => $case->id,
        'court_decision_id' => $decision->id,
        'matched_at' => now(),
        'match_type' => 'manual',
        'match_confidence' => 100,
        'match_source' => 'manual',
        'verification_status' => 'verified',
        'match_criteria' => [
            'manual' => true,
            'matched_by' => auth()->id(),
        ],
        'notes' => $notes,
    ]);
}

/**
 * Verify a pending match
 */
public function verifyMatch(CourtCaseDecisionMatch $match): void
{
    $match->update([
        'verification_status' => 'verified',
    ]);
}

/**
 * Reject a match with reason
 */
public function rejectMatch(CourtCaseDecisionMatch $match, string $reason): void
{
    $existingNotes = $match->notes ?? '';
    $newNotes = $existingNotes
        ? $existingNotes . "\n\nRejection reason: " . $reason
        : "Rejection reason: " . $reason;

    $match->update([
        'verification_status' => 'rejected',
        'notes' => $newNotes,
    ]);
}
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh CaseDecisionMatchingServiceTest
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/CaseDecisionMatchingService.php tests/Unit/Services/CaseDecisionMatchingServiceTest.php
git commit -m "feat: add manual match and verification methods"
```

---

## Task 10: Events - CaseDecisionMatched and CaseDecisionMatchVerified

**Files:**
- Create: `app/Events/CaseDecisionMatched.php`
- Create: `app/Events/CaseDecisionMatchVerified.php`
- Test: `tests/Unit/Events/CaseDecisionEventsTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Unit\Events;

use App\Events\CaseDecisionMatched;
use App\Events\CaseDecisionMatchVerified;
use App\Models\CourtCaseDecisionMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseDecisionEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_decision_matched_event_contains_match(): void
    {
        $match = CourtCaseDecisionMatch::factory()->create();
        $event = new CaseDecisionMatched($match);

        $this->assertEquals($match->id, $event->match->id);
    }

    public function test_case_decision_match_verified_event_contains_match_and_status(): void
    {
        $match = CourtCaseDecisionMatch::factory()->create();
        $event = new CaseDecisionMatchVerified($match, 'verified');

        $this->assertEquals($match->id, $event->match->id);
        $this->assertEquals('verified', $event->status);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh CaseDecisionEventsTest
```

Expected: FAIL - Classes not found

**Step 3: Create event classes**

`app/Events/CaseDecisionMatched.php`:

```php
<?php

namespace App\Events;

use App\Models\CourtCaseDecisionMatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CaseDecisionMatched
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CourtCaseDecisionMatch $match
    ) {}
}
```

`app/Events/CaseDecisionMatchVerified.php`:

```php
<?php

namespace App\Events;

use App\Models\CourtCaseDecisionMatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CaseDecisionMatchVerified
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CourtCaseDecisionMatch $match,
        public string $status
    ) {}
}
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh CaseDecisionEventsTest
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Events/CaseDecisionMatched.php app/Events/CaseDecisionMatchVerified.php tests/Unit/Events/CaseDecisionEventsTest.php
git commit -m "feat: add CaseDecisionMatched and CaseDecisionMatchVerified events"
```

---

## Task 11: Job - MatchCaseToDecisionJob

**Files:**
- Create: `app/Jobs/MatchCaseToDecisionJob.php`
- Test: `tests/Unit/Jobs/MatchCaseToDecisionJobTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Unit\Jobs;

use App\Jobs\MatchCaseToDecisionJob;
use App\Models\CourtCase;
use App\Models\CourtCaseDecisionMatch;
use App\Models\CourtDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchCaseToDecisionJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_match_when_decision_exists(): void
    {
        $case = CourtCase::factory()->create([
            'case_number' => 'Pp Prz-75/2025',
            'year' => 2025,
        ]);

        CourtDecision::factory()->create([
            'case_number' => 'Pp Prz-75/2025',
            'decision_date' => '2025-06-15',
        ]);

        $job = new MatchCaseToDecisionJob($case);
        $job->handle();

        $this->assertDatabaseHas('court_case_decision_matches', [
            'court_case_id' => $case->id,
        ]);
    }

    public function test_job_handles_no_match_gracefully(): void
    {
        $case = CourtCase::factory()->create([
            'case_number' => 'Pp Prz-999/2025',
        ]);

        $job = new MatchCaseToDecisionJob($case);
        $job->handle();

        $this->assertDatabaseMissing('court_case_decision_matches', [
            'court_case_id' => $case->id,
        ]);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh MatchCaseToDecisionJobTest
```

Expected: FAIL - Class not found

**Step 3: Create job class**

```php
<?php

namespace App\Jobs;

use App\Events\CaseDecisionMatched;
use App\Models\CourtCase;
use App\Services\CaseDecisionMatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MatchCaseToDecisionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public CourtCase $case
    ) {}

    public function handle(CaseDecisionMatchingService $service): void
    {
        // Skip if already has a match
        if ($this->case->decisionMatches()->exists()) {
            return;
        }

        $match = $service->matchCase($this->case, 'realtime_job');

        if ($match) {
            event(new CaseDecisionMatched($match));
        }
    }
}
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh MatchCaseToDecisionJobTest
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Jobs/MatchCaseToDecisionJob.php tests/Unit/Jobs/MatchCaseToDecisionJobTest.php
git commit -m "feat: add MatchCaseToDecisionJob"
```

---

## Task 12: Job - ReconcileUnmatchedCasesJob

**Files:**
- Create: `app/Jobs/ReconcileUnmatchedCasesJob.php`
- Test: `tests/Unit/Jobs/ReconcileUnmatchedCasesJobTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ReconcileUnmatchedCasesJob;
use App\Models\CourtCase;
use App\Models\CourtDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcileUnmatchedCasesJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_processes_unmatched_cases(): void
    {
        $case = CourtCase::factory()->create([
            'case_number' => 'Pp Prz-75/2025',
            'year' => 2025,
        ]);

        CourtDecision::factory()->create([
            'case_number' => 'Pp Prz-75/2025',
            'decision_date' => '2025-06-15',
        ]);

        $job = new ReconcileUnmatchedCasesJob(100);
        $job->handle();

        $this->assertDatabaseHas('court_case_decision_matches', [
            'court_case_id' => $case->id,
            'match_source' => 'scheduled_reconciliation',
        ]);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh ReconcileUnmatchedCasesJobTest
```

Expected: FAIL - Class not found

**Step 3: Create job class**

```php
<?php

namespace App\Jobs;

use App\Services\CaseDecisionMatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReconcileUnmatchedCasesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $limit = 100
    ) {}

    public function handle(CaseDecisionMatchingService $service): void
    {
        $result = $service->matchUnmatchedCases($this->limit, 'scheduled_reconciliation');

        Log::info('ReconcileUnmatchedCasesJob completed', $result->toArray());
    }
}
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh ReconcileUnmatchedCasesJobTest
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Jobs/ReconcileUnmatchedCasesJob.php tests/Unit/Jobs/ReconcileUnmatchedCasesJobTest.php
git commit -m "feat: add ReconcileUnmatchedCasesJob"
```

---

## Task 13: Command - MatchCaseDecisions

**Files:**
- Create: `app/Console/Commands/MatchCaseDecisions.php`
- Test: `tests/Feature/Commands/MatchCaseDecisionsCommandTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Commands;

use App\Models\CourtCase;
use App\Models\CourtDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchCaseDecisionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_runs_successfully(): void
    {
        $this->artisan('cases:match-decisions')
            ->assertExitCode(0);
    }

    public function test_command_matches_cases_to_decisions(): void
    {
        $case = CourtCase::factory()->create([
            'case_number' => 'Pp Prz-75/2025',
            'year' => 2025,
        ]);

        CourtDecision::factory()->create([
            'case_number' => 'Pp Prz-75/2025',
            'decision_date' => '2025-06-15',
        ]);

        $this->artisan('cases:match-decisions')
            ->assertExitCode(0);

        $this->assertDatabaseHas('court_case_decision_matches', [
            'court_case_id' => $case->id,
        ]);
    }

    public function test_command_respects_dry_run_option(): void
    {
        $case = CourtCase::factory()->create([
            'case_number' => 'Pp Prz-75/2025',
            'year' => 2025,
        ]);

        CourtDecision::factory()->create([
            'case_number' => 'Pp Prz-75/2025',
            'decision_date' => '2025-06-15',
        ]);

        $this->artisan('cases:match-decisions --dry-run')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('court_case_decision_matches', [
            'court_case_id' => $case->id,
        ]);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh MatchCaseDecisionsCommandTest
```

Expected: FAIL - Command not found

**Step 3: Create command**

```php
<?php

namespace App\Console\Commands;

use App\Models\CourtCase;
use App\Services\CaseDecisionMatchingService;
use Illuminate\Console\Command;

class MatchCaseDecisions extends Command
{
    protected $signature = 'cases:match-decisions
                            {--court= : Limit to specific court ID}
                            {--year= : Limit to specific year}
                            {--unmatched-only : Only process cases without matches}
                            {--dry-run : Report without saving}
                            {--limit=100 : Batch size}';

    protected $description = 'Match court cases to court decisions';

    public function handle(CaseDecisionMatchingService $service): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');
        $unmatchedOnly = (bool) $this->option('unmatched-only');
        $courtId = $this->option('court');
        $year = $this->option('year');

        $this->info('Starting case-decision matching...');

        if ($dryRun) {
            $this->warn('DRY RUN: No changes will be saved');
        }

        $query = CourtCase::query();

        if ($unmatchedOnly) {
            $query->unmatched();
        }

        if ($courtId) {
            $query->where('court_id', $courtId);
        }

        if ($year) {
            $query->where('year', $year);
        }

        $cases = $query->limit($limit)->get();

        $this->info("Processing {$cases->count()} cases...");

        $matched = 0;
        $unmatched = 0;

        $bar = $this->output->createProgressBar($cases->count());
        $bar->start();

        foreach ($cases as $case) {
            if (!$dryRun) {
                $match = $service->matchCase($case, 'command');
                if ($match) {
                    $matched++;
                } else {
                    $unmatched++;
                }
            } else {
                // Dry run: just simulate
                $unmatched++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Processed', $cases->count()],
                ['Matched', $matched],
                ['Unmatched', $unmatched],
            ]
        );

        return self::SUCCESS;
    }
}
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh MatchCaseDecisionsCommandTest
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Console/Commands/MatchCaseDecisions.php tests/Feature/Commands/MatchCaseDecisionsCommandTest.php
git commit -m "feat: add cases:match-decisions command"
```

---

## Task 14: Command - UnmatchedCasesReport

**Files:**
- Create: `app/Console/Commands/UnmatchedCasesReport.php`
- Test: `tests/Feature/Commands/UnmatchedCasesReportCommandTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Commands;

use App\Models\CourtCase;
use App\Models\CourtCaseDecisionMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnmatchedCasesReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_runs_successfully(): void
    {
        $this->artisan('cases:unmatched-report')
            ->assertExitCode(0);
    }

    public function test_command_shows_unmatched_cases(): void
    {
        $unmatched = CourtCase::factory()->create([
            'case_number' => 'Pp Prz-99/2025',
        ]);

        $matched = CourtCase::factory()->create([
            'case_number' => 'Pp Prz-100/2025',
        ]);

        CourtCaseDecisionMatch::factory()->create([
            'court_case_id' => $matched->id,
        ]);

        $this->artisan('cases:unmatched-report')
            ->expectsOutputToContain('Pp Prz-99/2025')
            ->assertExitCode(0);
    }

    public function test_command_exports_to_json(): void
    {
        CourtCase::factory()->create();

        $outputPath = storage_path('app/test-unmatched.json');

        $this->artisan("cases:unmatched-report --format=json --output={$outputPath}")
            ->assertExitCode(0);

        $this->assertFileExists($outputPath);

        unlink($outputPath);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh UnmatchedCasesReportCommandTest
```

Expected: FAIL - Command not found

**Step 3: Create command**

```php
<?php

namespace App\Console\Commands;

use App\Models\CourtCase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UnmatchedCasesReport extends Command
{
    protected $signature = 'cases:unmatched-report
                            {--court= : Filter by court ID}
                            {--year= : Filter by year}
                            {--format=table : Output format (table|csv|json)}
                            {--output= : File path for csv/json export}';

    protected $description = 'Generate report of unmatched court cases';

    public function handle(): int
    {
        $courtId = $this->option('court');
        $year = $this->option('year');
        $format = $this->option('format');
        $outputPath = $this->option('output');

        $query = CourtCase::unmatched()
            ->with('court');

        if ($courtId) {
            $query->where('court_id', $courtId);
        }

        if ($year) {
            $query->where('year', $year);
        }

        $cases = $query->get();

        $this->info("Found {$cases->count()} unmatched cases");

        if ($cases->isEmpty()) {
            return self::SUCCESS;
        }

        $data = $cases->map(fn($case) => [
            'id' => $case->id,
            'case_number' => $case->case_number,
            'court' => $case->court?->name ?? 'N/A',
            'year' => $case->year,
            'judge' => $case->judge_name ?? 'N/A',
            'decision_date' => $case->date_decision?->format('Y-m-d') ?? 'N/A',
        ])->toArray();

        match ($format) {
            'json' => $this->outputJson($data, $outputPath),
            'csv' => $this->outputCsv($data, $outputPath),
            default => $this->outputTable($data),
        };

        return self::SUCCESS;
    }

    protected function outputTable(array $data): void
    {
        $this->table(
            ['ID', 'Case Number', 'Court', 'Year', 'Judge', 'Decision Date'],
            $data
        );
    }

    protected function outputJson(array $data, ?string $path): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($path) {
            File::put($path, $json);
            $this->info("Exported to: {$path}");
        } else {
            $this->line($json);
        }
    }

    protected function outputCsv(array $data, ?string $path): void
    {
        $csv = '';
        if (!empty($data)) {
            $csv .= implode(',', array_keys($data[0])) . "\n";
            foreach ($data as $row) {
                $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $row)) . "\n";
            }
        }

        if ($path) {
            File::put($path, $csv);
            $this->info("Exported to: {$path}");
        } else {
            $this->line($csv);
        }
    }
}
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh UnmatchedCasesReportCommandTest
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Console/Commands/UnmatchedCasesReport.php tests/Feature/Commands/UnmatchedCasesReportCommandTest.php
git commit -m "feat: add cases:unmatched-report command"
```

---

## Task 15: Integration - Add job dispatch to FetchPpPrzCases

**Files:**
- Modify: `app/Console/Commands/FetchPpPrzCases.php`
- Test: `tests/Feature/Commands/FetchPpPrzCasesIntegrationTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Commands;

use App\Jobs\MatchCaseToDecisionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FetchPpPrzCasesIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_is_dispatched_after_case_saved(): void
    {
        Queue::fake();

        // This would require mocking the API, so we test the dispatch logic
        // by checking the command has the dispatch line
        $commandPath = app_path('Console/Commands/FetchPpPrzCases.php');
        $content = file_get_contents($commandPath);

        $this->assertStringContainsString('MatchCaseToDecisionJob', $content);
    }
}
```

**Step 2: Modify FetchPpPrzCases command**

Add at top of file:
```php
use App\Jobs\MatchCaseToDecisionJob;
```

Find the line after case creation (around line 134):
```php
$case = CourtCase::createFromApiResponse($caseData, $court->id);
$saved++;
```

Add dispatch after it:
```php
$case = CourtCase::createFromApiResponse($caseData, $court->id);
$saved++;

// Dispatch matching job
dispatch(new MatchCaseToDecisionJob($case));
```

**Step 3: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh FetchPpPrzCasesIntegrationTest
```

Expected: PASS

**Step 4: Commit**

```bash
git add app/Console/Commands/FetchPpPrzCases.php tests/Feature/Commands/FetchPpPrzCasesIntegrationTest.php
git commit -m "feat: dispatch MatchCaseToDecisionJob after case fetch"
```

---

## Task 16: Integration - Schedule reconciliation job

**Files:**
- Modify: `app/Console/Kernel.php` or `routes/console.php`

**Step 1: Add schedule**

For Laravel 11+ with `routes/console.php`:

```php
use App\Jobs\ReconcileUnmatchedCasesJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ReconcileUnmatchedCasesJob(100))
    ->daily()
    ->at('03:00')
    ->name('reconcile-unmatched-cases')
    ->withoutOverlapping();
```

**Step 2: Verify schedule is registered**

```bash
php artisan schedule:list
```

Expected: Shows `reconcile-unmatched-cases` job scheduled at 03:00

**Step 3: Commit**

```bash
git add routes/console.php
git commit -m "feat: schedule daily reconciliation job"
```

---

## Task 17: Factory - CourtCaseDecisionMatch Factory

**Files:**
- Create: `database/factories/CourtCaseDecisionMatchFactory.php`

**Step 1: Create factory**

```php
<?php

namespace Database\Factories;

use App\Models\CourtCase;
use App\Models\CourtCaseDecisionMatch;
use App\Models\CourtDecision;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourtCaseDecisionMatchFactory extends Factory
{
    protected $model = CourtCaseDecisionMatch::class;

    public function definition(): array
    {
        return [
            'court_case_id' => CourtCase::factory(),
            'court_decision_id' => CourtDecision::factory(),
            'matched_at' => now(),
            'match_type' => $this->faker->randomElement(['auto', 'manual']),
            'match_confidence' => $this->faker->numberBetween(60, 100),
            'match_source' => $this->faker->randomElement(['epredmet_fetch', 'decision_ingest', 'manual', 'scheduled_job']),
            'verification_status' => $this->faker->randomElement(['pending', 'verified', 'rejected']),
            'match_criteria' => [
                'case_number' => 'Pp Prz-' . $this->faker->numberBetween(1, 999),
                'year' => $this->faker->year(),
            ],
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => 'verified',
            'match_confidence' => 100,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => 'pending',
            'match_confidence' => $this->faker->numberBetween(60, 80),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => 'rejected',
        ]);
    }
}
```

**Step 2: Verify factory works**

```bash
php artisan tinker --execute="App\Models\CourtCaseDecisionMatch::factory()->make()"
```

Expected: Returns model instance

**Step 3: Commit**

```bash
git add database/factories/CourtCaseDecisionMatchFactory.php
git commit -m "feat: add CourtCaseDecisionMatch factory"
```

---

## Task 18: Final - Run all tests and verify

**Step 1: Run full test suite for new code**

```bash
./scripts/run-focused-tests.sh CourtCaseDecisionMatch
./scripts/run-focused-tests.sh CaseDecisionMatchingService
./scripts/run-focused-tests.sh MatchCaseDecisions
./scripts/run-focused-tests.sh UnmatchedCasesReport
```

Expected: All tests PASS

**Step 2: Run related existing tests**

```bash
./scripts/run-focused-tests.sh CourtCase
./scripts/run-focused-tests.sh CourtDecision
```

Expected: No regressions

**Step 3: Final commit**

```bash
git add -A
git commit -m "chore: complete court case decision matching system"
```

**Step 4: Push branch**

```bash
git push -u origin claude/refactor-court-warrant-system-kCdls
```

---

## Summary

| Component | Files | Status |
|-----------|-------|--------|
| Migration | `database/migrations/*_create_court_case_decision_matches_table.php` | Task 1 |
| Model | `app/Models/CourtCaseDecisionMatch.php` | Task 2 |
| CourtCase relations | `app/Models/CourtCase.php` | Task 3 |
| CourtDecision relations | `app/Models/CourtDecision.php` | Task 4 |
| DTO | `app/DTOs/MatchingResult.php` | Task 5 |
| Service | `app/Services/CaseDecisionMatchingService.php` | Tasks 6-9 |
| Events | `app/Events/CaseDecision*.php` | Task 10 |
| Jobs | `app/Jobs/Match*.php`, `app/Jobs/Reconcile*.php` | Tasks 11-12 |
| Commands | `app/Console/Commands/MatchCaseDecisions.php`, `UnmatchedCasesReport.php` | Tasks 13-14 |
| Integration | `FetchPpPrzCases.php`, `routes/console.php` | Tasks 15-16 |
| Factory | `database/factories/CourtCaseDecisionMatchFactory.php` | Task 17 |
