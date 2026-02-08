# Court Case ↔ Court Decision Matching System Design

**Date:** 2026-01-03
**Status:** Approved
**Author:** Claude (Brainstorming Session)

---

## Problem Statement

CourtCase records (from e-predmet API) contain structured metadata for legal cases (especially "Pp Prz" search warrant cases) but lack actual decision text content. CourtDecision records (from sudskapraksa.hr) contain the full decision text with embeddings. These need to be paired so that data-scarce e-predmet cases gain access to actual content.

---

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Matching criteria | Case number (base + year) | Direct match possible; e.g., "Pp Prz-75" + "2025" |
| Relationship model | Soft reference | Keep systems decoupled, no FK enforcement |
| Enrichment approach | Separate matching table | Auditable, stores match metadata |
| When to match | Combination | Real-time for new records + scheduled reconciliation |
| Unmatched handling | Manual review list | Generate reports for human review |

---

## Data Model

### New Table: `court_case_decision_matches`

```sql
CREATE TABLE court_case_decision_matches (
    id VARCHAR(26) PRIMARY KEY,              -- ULID
    court_case_id BIGINT NOT NULL,           -- References court_cases.id
    court_decision_id VARCHAR(26) NOT NULL,  -- References court_decisions.id
    matched_at TIMESTAMP NOT NULL,
    match_type VARCHAR(20) NOT NULL,         -- 'auto', 'manual'
    match_confidence INTEGER NOT NULL,       -- 0-100
    match_source VARCHAR(50) NOT NULL,       -- 'epredmet_fetch', 'decision_ingest', 'manual', 'scheduled_job'
    verification_status VARCHAR(20) NOT NULL DEFAULT 'pending', -- 'pending', 'verified', 'rejected'
    match_criteria JSON NOT NULL,            -- {"case_number": "Pp Prz-75", "year": 2025, "court": "matched"}
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,

    UNIQUE (court_case_id, court_decision_id),
    INDEX idx_court_case_id (court_case_id),
    INDEX idx_court_decision_id (court_decision_id),
    INDEX idx_verification_status (verification_status),
    INDEX idx_matched_at (matched_at)
);
```

### New Model: `CourtCaseDecisionMatch`

```php
class CourtCaseDecisionMatch extends Model
{
    protected $fillable = [
        'court_case_id', 'court_decision_id', 'matched_at', 'match_type',
        'match_confidence', 'match_source', 'verification_status',
        'match_criteria', 'notes',
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
}
```

### Model Modifications

**CourtCase.php:**
```php
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

**CourtDecision.php:**
```php
public function caseMatches(): HasMany
{
    return $this->hasMany(CourtCaseDecisionMatch::class);
}
```

---

## Matching Service

### `CaseDecisionMatchingService`

```php
class CaseDecisionMatchingService
{
    /**
     * Match a single case to decisions
     */
    public function matchCase(CourtCase $case): ?CourtCaseDecisionMatch;

    /**
     * Match a decision to cases (reverse lookup)
     */
    public function matchDecision(CourtDecision $decision): Collection;

    /**
     * Process unmatched cases in batch
     */
    public function matchUnmatchedCases(int $limit = 100): MatchingResult;

    /**
     * Full reconciliation of all records
     */
    public function reconcileAll(): MatchingResult;

    /**
     * Create a manual match with notes
     */
    public function createManualMatch(
        CourtCase $case,
        CourtDecision $decision,
        ?string $notes = null
    ): CourtCaseDecisionMatch;

    /**
     * Verify a pending match
     */
    public function verifyMatch(CourtCaseDecisionMatch $match): void;

    /**
     * Reject a match with reason
     */
    public function rejectMatch(CourtCaseDecisionMatch $match, string $reason): void;
}
```

### Matching Algorithm

1. **Parse case number** from CourtCase:
   - Extract base: `Pp Prz-75` (from `Pp Prz-75/2025`)
   - Extract year: `2025`

2. **Query CourtDecision** where:
   - `case_number LIKE '%Pp Prz-75%'` AND `YEAR(decision_date) = 2025`
   - OR parse from ECLI if available

3. **Score match confidence:**
   - **100**: Exact case_number + year + same court
   - **80**: Exact case_number + year, different/null court
   - **60**: Partial match (needs review)

4. **Create match record:**
   - `verification_status = 'verified'` if confidence = 100
   - `verification_status = 'pending'` if confidence < 100

---

## Commands & Jobs

### Artisan Command: `cases:match-decisions`

```bash
php artisan cases:match-decisions
    --court=           # Limit to specific court ID
    --year=            # Limit to specific year
    --unmatched-only   # Only process cases without matches
    --dry-run          # Report without saving
    --limit=100        # Batch size
```

### Artisan Command: `cases:unmatched-report`

```bash
php artisan cases:unmatched-report
    --court=           # Filter by court
    --year=            # Filter by year
    --format=table     # table|csv|json
    --output=          # File path for csv/json export
```

### Queue Jobs

**MatchCaseToDecisionJob:**
- Matches a single case to decisions
- Dispatched when: CourtCase created/updated, CourtDecision created

**ReconcileUnmatchedCasesJob:**
- Processes unmatched cases in batches
- Scheduled: daily (configurable)

### Events

- `CaseDecisionMatched` - Fired when match created
- `CaseDecisionMatchVerified` - Fired when match verified/rejected

---

## Integration Points

### FetchPpPrzCases Command

After saving each case, dispatch matching job:

```php
// In FetchPpPrzCases::handle()
$case = CourtCase::createFromApiResponse($caseData, $court->id);
dispatch(new MatchCaseToDecisionJob($case));
```

### Scheduler

```php
// In Console/Kernel.php
$schedule->job(new ReconcileUnmatchedCasesJob)
    ->daily()
    ->at('03:00');
```

---

## File Structure

### New Files

```
database/migrations/
└── 2026_01_03_000000_create_court_case_decision_matches_table.php

app/Models/
└── CourtCaseDecisionMatch.php

app/Services/
└── CaseDecisionMatchingService.php

app/Jobs/
├── MatchCaseToDecisionJob.php
└── ReconcileUnmatchedCasesJob.php

app/Events/
├── CaseDecisionMatched.php
└── CaseDecisionMatchVerified.php

app/Console/Commands/
├── MatchCaseDecisions.php
└── UnmatchedCasesReport.php

app/DTOs/
└── MatchingResult.php
```

### Modified Files

```
app/Models/CourtCase.php
├── Add decisionMatches() relationship
├── Add matchedDecisions() relationship
├── Add scopeUnmatched()
└── Add scopePendingReview()

app/Models/CourtDecision.php
└── Add caseMatches() relationship

app/Console/Commands/FetchPpPrzCases.php
└── Dispatch MatchCaseToDecisionJob after save

app/Console/Kernel.php
└── Schedule ReconcileUnmatchedCasesJob
```

---

## Testing Strategy

1. **Unit Tests:**
   - `CaseDecisionMatchingServiceTest` - matching algorithm, scoring
   - `CourtCaseDecisionMatchTest` - model relationships, scopes

2. **Feature Tests:**
   - `MatchCaseDecisionsCommandTest` - command options, output
   - `UnmatchedCasesReportCommandTest` - report generation

3. **Integration Tests:**
   - End-to-end matching flow
   - Job dispatching and processing

---

## Future Considerations (Out of Scope)

- Livewire dashboard for manual matching UI
- Bulk verify/reject actions
- AI-assisted fuzzy matching for edge cases
- Match quality analytics
