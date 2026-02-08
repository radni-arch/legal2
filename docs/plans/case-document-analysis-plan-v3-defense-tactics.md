# Case Document Analysis — Plan v3: Defense Tactic Detectors

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.
> Tasks numbered 28+ continuing from v2 addendum.

**Key insight:** These detectors are **not** standalone analyzers — they're a new **Layer 6: Legal Defense Intelligence** that sits on top of everything from Tasks 1-27. They consume the outputs of CaseReferenceExtractor, DateContextExtractor, MetacaseDetector, CaseFileRegistry, and AI analysis layers. Most are surprisingly deterministic once you have structured data.

---

## Updated Architecture

```
Layers 1-5 (Tasks 1-27)
        │
        ▼  All structured outputs available
  ┌─────────────────────────────────────────────────────────────┐
  │             LAYER 6: Defense Tactic Detectors                │
  │                                                              │
  │  Tier A — Pure computation (no AI):                          │
  │    ZastaraCalculator · DefenseTimeChecker · NeBisInIdem      │
  │                                                              │
  │  Tier B — Cross-doc pattern matching (no AI):                │
  │    ChainOfCustody · FruitMapper · DisclosureChecker          │
  │                                                              │
  │  Tier C — AI-assisted (Claude API):                          │
  │    JudicialBias · Proportionality · ExpertValidator          │
  │    ConstitutionalViolationScanner                            │
  └──────────────────────┬──────────────────────────────────────┘
                         ▼
  ┌─────────────────────────────────────────────────────────────┐
  │  DefenseReport — prioritized list of flags + legal basis     │
  │  Each flag: severity · legal provision · evidence · action   │
  └─────────────────────────────────────────────────────────────┘
```

---

## Shared Infrastructure

### Task 28: DefenseFlag DTO + DefenseReport aggregator

**Files:**
- Create: `app/DTOs/Defense/DefenseFlag.php`
- Create: `app/DTOs/Defense/DefenseReport.php`
- Create: `app/Services/Defense/Contracts/DefenseTacticDetectorInterface.php`
- Create: `app/Services/Defense/DefenseReportBuilder.php`
- Create: `database/migrations/YYYY_MM_DD_create_defense_flags_table.php`

**Why:** Every detector outputs the same structure — a flag with severity, legal basis, evidence, and recommended action. The DefenseReport aggregates and prioritizes all flags for a case.

**Step 1: DefenseFlag DTO**

```php
<?php

namespace App\DTOs\Defense;

class DefenseFlag
{
    // Severity levels — maps to case impact
    public const SEVERITY_CRITICAL = 'critical';   // Potentially case-dispositive
    public const SEVERITY_HIGH = 'high';            // Strong defense angle
    public const SEVERITY_MEDIUM = 'medium';        // Worth raising
    public const SEVERITY_LOW = 'low';              // Monitor / cumulative
    public const SEVERITY_INFO = 'info';            // Informational

    public function __construct(
        public readonly string $tactic,           // 'zastara', 'ne_bis_in_idem', 'chain_of_custody', etc.
        public readonly string $severity,         // CRITICAL / HIGH / MEDIUM / LOW / INFO
        public readonly string $title,            // Human-readable short title (Croatian)
        public readonly string $description,      // What was detected (Croatian)
        public readonly string $legalBasis,       // "čl. 81. KZ", "čl. 10. st. 2. t. 4. ZKP"
        public readonly ?string $echrBasis,       // "Maresti v. Croatia (2009)", if applicable
        public readonly array $evidence,          // Document IDs + specific data supporting flag
        public readonly string $recommendedAction, // What the defense should do
        public readonly float $confidence,        // 0.0 - 1.0
        public readonly array $metadata = [],     // Detector-specific data
    ) {}

    public function toArray(): array
    {
        return [
            'tactic' => $this->tactic,
            'severity' => $this->severity,
            'title' => $this->title,
            'description' => $this->description,
            'legal_basis' => $this->legalBasis,
            'echr_basis' => $this->echrBasis,
            'evidence' => $this->evidence,
            'recommended_action' => $this->recommendedAction,
            'confidence' => $this->confidence,
            'metadata' => $this->metadata,
        ];
    }
}
```

**Step 2: Interface**

```php
<?php

namespace App\Services\Defense\Contracts;

use App\DTOs\Defense\DefenseFlag;

interface DefenseTacticDetectorInterface
{
    /** Unique tactic identifier */
    public function tactic(): string;

    /** Human label (Croatian) */
    public function label(): string;

    /** What layer data this detector needs */
    public function requires(): array; // ['case_references', 'dates_with_context', 'metacase', ...]

    /**
     * Run detection against case data.
     *
     * @param string $caseId
     * @param array $analysisData  Pre-loaded results from required analyzers
     * @return DefenseFlag[]
     */
    public function detect(string $caseId, array $analysisData): array;
}
```

**Step 3: Migration**

```php
Schema::create('defense_flags', function (Blueprint $table) {
    $table->id();
    $table->string('case_id');
    $table->string('tactic');                 // detector identifier
    $table->string('severity');               // critical/high/medium/low/info
    $table->string('title');
    $table->text('description');
    $table->string('legal_basis');
    $table->string('echr_basis')->nullable();
    $table->json('evidence');
    $table->text('recommended_action');
    $table->float('confidence');
    $table->json('metadata')->nullable();
    $table->string('status')->default('active'); // active, dismissed, used
    $table->text('dismissal_reason')->nullable();
    $table->timestamps();

    $table->index(['case_id', 'severity']);
    $table->index(['case_id', 'tactic']);
});
```

**Step 4: DefenseReportBuilder**

```php
<?php

namespace App\Services\Defense;

use App\DTOs\Defense\DefenseFlag;
use App\DTOs\Defense\DefenseReport;
use App\Models\DocumentAnalysis;
use App\Models\CaseAnalysis;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DefenseReportBuilder
{
    /** @var DefenseTacticDetectorInterface[] */
    private array $detectors = [];

    public function register(DefenseTacticDetectorInterface $detector): self
    {
        $this->detectors[$detector->tactic()] = $detector;
        return $this;
    }

    /**
     * Run all registered detectors against a case.
     * Pre-loads required analysis data to avoid N+1 queries.
     */
    public function buildReport(string $caseId): DefenseReport
    {
        $startTime = microtime(true);

        // Collect all required data types across all detectors
        $requiredTypes = collect($this->detectors)
            ->flatMap(fn($d) => $d->requires())
            ->unique()
            ->values()
            ->toArray();

        // Pre-load all needed analysis results
        $analysisData = $this->loadAnalysisData($caseId, $requiredTypes);

        // Run each detector
        $allFlags = [];
        foreach ($this->detectors as $detector) {
            try {
                $flags = $detector->detect($caseId, $analysisData);
                $allFlags = array_merge($allFlags, $flags);

                Log::info("Defense detector [{$detector->tactic()}]: " . count($flags) . " flags");
            } catch (\Throwable $e) {
                Log::error("Defense detector [{$detector->tactic()}] failed: {$e->getMessage()}");

                // Add error flag so user knows detection was incomplete
                $allFlags[] = new DefenseFlag(
                    tactic: $detector->tactic(),
                    severity: DefenseFlag::SEVERITY_INFO,
                    title: "Greška u detekciji: {$detector->label()}",
                    description: "Detektor nije mogao dovršiti analizu: {$e->getMessage()}",
                    legalBasis: '-',
                    echrBasis: null,
                    evidence: [],
                    recommendedAction: 'Ručno provjeriti.',
                    confidence: 0,
                );
            }
        }

        // Sort: critical first, then by confidence descending
        $severityOrder = [
            DefenseFlag::SEVERITY_CRITICAL => 0,
            DefenseFlag::SEVERITY_HIGH => 1,
            DefenseFlag::SEVERITY_MEDIUM => 2,
            DefenseFlag::SEVERITY_LOW => 3,
            DefenseFlag::SEVERITY_INFO => 4,
        ];

        usort($allFlags, function (DefenseFlag $a, DefenseFlag $b) use ($severityOrder) {
            $sevCmp = ($severityOrder[$a->severity] ?? 9) <=> ($severityOrder[$b->severity] ?? 9);
            if ($sevCmp !== 0) return $sevCmp;
            return $b->confidence <=> $a->confidence;
        });

        // Persist flags
        DB::table('defense_flags')->where('case_id', $caseId)->delete();
        foreach ($allFlags as $flag) {
            DB::table('defense_flags')->insert(array_merge(
                $flag->toArray(),
                [
                    'case_id' => $caseId,
                    'evidence' => json_encode($flag->evidence),
                    'metadata' => json_encode($flag->metadata),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ));
        }

        return new DefenseReport(
            caseId: $caseId,
            flags: $allFlags,
            generatedAt: now(),
            processingTime: round(microtime(true) - $startTime, 2),
            detectorsRun: array_keys($this->detectors),
        );
    }

    private function loadAnalysisData(string $caseId, array $types): array
    {
        $data = [];

        // Per-document analyses
        $docAnalyses = DocumentAnalysis::whereHas('caseDocument', function ($q) use ($caseId) {
            $q->where('case_id', $caseId);
        })
            ->whereIn('analysis_type', $types)
            ->where('status', DocumentAnalysis::STATUS_COMPLETED)
            ->with('caseDocument')
            ->get();

        foreach ($docAnalyses as $analysis) {
            $data[$analysis->analysis_type][$analysis->case_document_id] = $analysis->results;
        }

        // Case-level analyses
        $caseAnalyses = CaseAnalysis::where('case_id', $caseId)
            ->whereIn('analysis_type', $types)
            ->where('status', CaseAnalysis::STATUS_COMPLETED)
            ->get();

        foreach ($caseAnalyses as $analysis) {
            $data[$analysis->analysis_type] = $analysis->results;
        }

        // Also load specific cross-references
        if (in_array('case_reference_registry', $types)) {
            $data['case_reference_registry'] = DB::table('case_reference_registry')
                ->where('case_id', $caseId)->get()->toArray();
        }

        if (in_array('case_hierarchy', $types)) {
            $data['case_hierarchy'] = DB::table('case_hierarchy')
                ->where('case_id', $caseId)->get()->toArray();
        }

        return $data;
    }
}
```

**Step 5: ServiceProvider registration**

```php
// In AppServiceProvider::boot() or a dedicated DefenseServiceProvider

$this->app->singleton(DefenseReportBuilder::class, function ($app) {
    return (new DefenseReportBuilder())
        ->register(new ZastaraCalculator())
        ->register(new DefenseTimeAdequacyChecker())
        ->register(new NeBisInIdemDetector())
        ->register(new ChainOfCustodyAnalyzer())
        ->register(new FruitOfPoisonousTreeMapper())
        ->register(new ProsecutorialDisclosureChecker())
        ->register(new JudicialBiasDetector())
        ->register(new ProportionalityChecker())
        ->register(new ExpertWitnessValidator())
        ->register(new ConstitutionalViolationScanner());
});
```

---

## Tier A: Pure Computation (no AI needed)

### Task 29: ZastaraCalculator

**Files:**
- Create: `app/Services/Defense/Detectors/ZastaraCalculator.php`

**Depends on:** DateContextExtractor (Task 22), EntityExtractor (Task 6)

**How it works:** Extracts the charged offense article from documents (EntityExtractor already captures law references like "čl. 190. KZ"), maps it to a penalty range, then computes both relative and absolute zastara deadlines from the offense date. Pure arithmetic — no AI needed.

```php
<?php

namespace App\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;
use Carbon\Carbon;

class ZastaraCalculator implements DefenseTacticDetectorInterface
{
    /**
     * Penalty ranges for common offenses under KZ/11.
     * Maps article number → [min_years, max_years, description_hr]
     *
     * In production: load from database or config. This covers the most common.
     */
    private const OFFENSE_PENALTIES = [
        // Droge — čl. 190. KZ
        190 => [
            1 => ['min' => 1, 'max' => 3, 'desc' => 'Neovlaštena proizvodnja i promet drogama (st. 1)'],
            2 => ['min' => 3, 'max' => 15, 'desc' => 'Neovlaštena proizvodnja i promet drogama (st. 2 - veća količina)'],
            3 => ['min' => 1, 'max' => 12, 'desc' => 'Neovlaštena proizvodnja i promet drogama (st. 3 - organizirano)'],
            4 => ['min' => 0, 'max' => 3, 'desc' => 'Neovlašteno posjedovanje droga (st. 4)'],
        ],
        // Teška tjelesna ozljeda — čl. 118. KZ
        118 => [
            1 => ['min' => 0.5, 'max' => 5, 'desc' => 'Teška tjelesna ozljeda (st. 1)'],
            2 => ['min' => 1, 'max' => 8, 'desc' => 'Teška tjelesna ozljeda (st. 2 - kvalificirani)'],
        ],
        // Oružje — čl. 331. KZ
        331 => [
            1 => ['min' => 0, 'max' => 3, 'desc' => 'Nedozvoljeno posjedovanje oružja (st. 1)'],
        ],
        // Krađa — čl. 228. KZ
        228 => [
            1 => ['min' => 0, 'max' => 3, 'desc' => 'Krađa (st. 1)'],
        ],
        // Prijevara — čl. 236. KZ
        236 => [
            1 => ['min' => 0, 'max' => 3, 'desc' => 'Prijevara (st. 1)'],
            2 => ['min' => 1, 'max' => 8, 'desc' => 'Prijevara (st. 2 - veća vrijednost)'],
        ],
    ];

    /**
     * KZ/11 čl. 81. — Limitation periods based on max penalty.
     */
    private const ZASTARA_TABLE = [
        ['max_penalty_years' => 999, 'min_penalty_years' => 15, 'relative' => 40, 'label' => 'dugotrajni zatvor'],
        ['max_penalty_years' => 15,  'min_penalty_years' => 10, 'relative' => 25, 'label' => '10-15 godina'],
        ['max_penalty_years' => 10,  'min_penalty_years' => 5,  'relative' => 20, 'label' => '5-10 godina'],
        ['max_penalty_years' => 5,   'min_penalty_years' => 3,  'relative' => 15, 'label' => '3-5 godina'],
        ['max_penalty_years' => 3,   'min_penalty_years' => 1,  'relative' => 10, 'label' => '1-3 godine'],
        ['max_penalty_years' => 1,   'min_penalty_years' => 0,  'relative' =>  6, 'label' => 'do 1 godine / novčana'],
    ];

    /**
     * Offenses with NO limitation — čl. 81. st. 2. KZ
     */
    private const NO_LIMITATION_ARTICLES = [88, 89, 90, 91, 92, 93, 97, 99, 352, 353];

    public function tactic(): string { return 'zastara'; }
    public function label(): string { return 'Zastara kaznenog progona'; }
    public function requires(): array
    {
        return ['dates_with_context', 'case_references', 'entities'];
    }

    public function detect(string $caseId, array $analysisData): array
    {
        $flags = [];

        // Step 1: Find the charged offense article
        $chargedOffense = $this->findChargedOffense($analysisData);
        if (!$chargedOffense) {
            return [new DefenseFlag(
                tactic: 'zastara',
                severity: DefenseFlag::SEVERITY_INFO,
                title: 'Kazneno djelo nije identificirano',
                description: 'Sustav nije mogao automatski odrediti koje kazneno djelo se stavlja na teret. Potrebna ručna provjera zastare.',
                legalBasis: 'čl. 81. KZ',
                echrBasis: null,
                evidence: [],
                recommendedAction: 'Ručno unijeti članak KZ i datum počinjenja za izračun zastare.',
                confidence: 0,
            )];
        }

        // Step 2: Find offense date(s)
        $offenseDate = $this->findOffenseDate($analysisData);
        if (!$offenseDate) {
            return [new DefenseFlag(
                tactic: 'zastara',
                severity: DefenseFlag::SEVERITY_INFO,
                title: 'Datum počinjenja nije utvrđen',
                description: "Kazneno djelo: čl. {$chargedOffense['article']}. st. {$chargedOffense['paragraph']}. KZ — ali datum počinjenja nije pronađen u dokumentima.",
                legalBasis: 'čl. 81. KZ',
                echrBasis: null,
                evidence: [],
                recommendedAction: 'Ručno unijeti datum počinjenja.',
                confidence: 0,
            )];
        }

        // Step 3: Check no-limitation offenses
        if (in_array($chargedOffense['article'], self::NO_LIMITATION_ARTICLES)) {
            return [new DefenseFlag(
                tactic: 'zastara',
                severity: DefenseFlag::SEVERITY_INFO,
                title: 'Kazneno djelo bez zastare',
                description: "Čl. {$chargedOffense['article']}. KZ ne zastarijeva (čl. 81. st. 2. KZ).",
                legalBasis: 'čl. 81. st. 2. KZ',
                echrBasis: null,
                evidence: [],
                recommendedAction: 'Zastara nije primjenjiva na ovo kazneno djelo.',
                confidence: 1.0,
            )];
        }

        // Step 4: Compute zastara periods
        $penalty = self::OFFENSE_PENALTIES[$chargedOffense['article']][$chargedOffense['paragraph']] ?? null;
        if (!$penalty) {
            return [new DefenseFlag(
                tactic: 'zastara',
                severity: DefenseFlag::SEVERITY_INFO,
                title: 'Nepoznata kazna za članak',
                description: "Čl. {$chargedOffense['article']}. st. {$chargedOffense['paragraph']}. KZ — raspon kazne nije u bazi. Potrebna ručna provjera.",
                legalBasis: 'čl. 81. KZ',
                echrBasis: null,
                evidence: [],
                recommendedAction: 'Dodati raspon kazne za ovaj članak u konfiguraciju.',
                confidence: 0,
            )];
        }

        $zastaraInfo = $this->computeZastara($penalty['max'], $offenseDate);
        $now = Carbon::now();

        // Step 5: Generate flags based on zastara status

        // CRITICAL: Absolute zastara exceeded
        if ($now->greaterThan($zastaraInfo['absolute_deadline'])) {
            $flags[] = new DefenseFlag(
                tactic: 'zastara',
                severity: DefenseFlag::SEVERITY_CRITICAL,
                title: 'APSOLUTNA ZASTARA ISTEKLA',
                description: "Kazneno djelo čl. {$chargedOffense['article']}. st. {$chargedOffense['paragraph']}. KZ "
                    . "({$penalty['desc']}) počinjeno {$offenseDate->format('d.m.Y.')} — "
                    . "apsolutna zastara istekla {$zastaraInfo['absolute_deadline']->format('d.m.Y.')} "
                    . "(rok: {$zastaraInfo['absolute_years']} godina). "
                    . "Kazneni progon je nedopušten.",
                legalBasis: "čl. 81. st. 4. KZ, čl. 82. KZ",
                echrBasis: null,
                evidence: [
                    'offense_date' => $offenseDate->toDateString(),
                    'charged_article' => "čl. {$chargedOffense['article']}. st. {$chargedOffense['paragraph']}.",
                    'max_penalty_years' => $penalty['max'],
                    'relative_years' => $zastaraInfo['relative_years'],
                    'absolute_years' => $zastaraInfo['absolute_years'],
                    'absolute_deadline' => $zastaraInfo['absolute_deadline']->toDateString(),
                ],
                recommendedAction: "Podnijeti prigovor o zastari kaznenog progona. "
                    . "Apsolutna zastara ne može biti produžena nikakvim prekidima (čl. 82. st. 3. KZ). "
                    . "Postupak se mora obustaviti.",
                confidence: 0.95,
            );
        }
        // CRITICAL: Relative zastara exceeded (if no interruptions found)
        elseif ($now->greaterThan($zastaraInfo['relative_deadline'])) {
            $flags[] = new DefenseFlag(
                tactic: 'zastara',
                severity: DefenseFlag::SEVERITY_HIGH,
                title: 'Relativna zastara možda istekla',
                description: "Relativni rok zastare ({$zastaraInfo['relative_years']} god.) istekao "
                    . "{$zastaraInfo['relative_deadline']->format('d.m.Y.')} — ali svaka postupovna radnja prekida zastaru "
                    . "i rok počinje teći iznova. Potrebno provjeriti prekide.",
                legalBasis: "čl. 81. KZ, čl. 82. st. 1-2. KZ",
                echrBasis: null,
                evidence: [
                    'offense_date' => $offenseDate->toDateString(),
                    'relative_deadline' => $zastaraInfo['relative_deadline']->toDateString(),
                    'absolute_deadline' => $zastaraInfo['absolute_deadline']->toDateString(),
                ],
                recommendedAction: "Utvrditi sve radnje koje prekidaju zastaru (kaznena prijava, nalog za istragu, "
                    . "optužnica, pozivi). Izračunati teku li novi rokovi prema posljednjem prekidu.",
                confidence: 0.7,
            );
        }
        // WARNING: Approaching zastara
        else {
            $monthsToAbsolute = $now->diffInMonths($zastaraInfo['absolute_deadline']);
            $monthsToRelative = $now->diffInMonths($zastaraInfo['relative_deadline']);

            if ($monthsToAbsolute <= 12) {
                $flags[] = new DefenseFlag(
                    tactic: 'zastara',
                    severity: DefenseFlag::SEVERITY_MEDIUM,
                    title: "Apsolutna zastara za {$monthsToAbsolute} mjeseci",
                    description: "Apsolutna zastara istječe {$zastaraInfo['absolute_deadline']->format('d.m.Y.')} "
                        . "— preostalo {$monthsToAbsolute} mjeseci. Razmotriti taktiku odugovlačenja.",
                    legalBasis: "čl. 81. st. 4. KZ",
                    echrBasis: null,
                    evidence: [
                        'absolute_deadline' => $zastaraInfo['absolute_deadline']->toDateString(),
                        'months_remaining' => $monthsToAbsolute,
                    ],
                    recommendedAction: "Zastara se približava. Razmotriti legitimne procesne mogućnosti "
                        . "za produženje trajanja postupka (zahtjevi za dopunu dokaznog postupka, "
                        . "žalbe na procesna rješenja, izuzeće suca).",
                    confidence: 0.9,
                );
            }

            // Also report: INFO with full calculation
            $flags[] = new DefenseFlag(
                tactic: 'zastara',
                severity: DefenseFlag::SEVERITY_INFO,
                title: "Zastara: izračun za čl. {$chargedOffense['article']}. KZ",
                description: "{$penalty['desc']} — kazna do {$penalty['max']} god. zatvora. "
                    . "Relativna zastara: {$zastaraInfo['relative_years']} god. (do {$zastaraInfo['relative_deadline']->format('d.m.Y.')}). "
                    . "Apsolutna zastara: {$zastaraInfo['absolute_years']} god. (do {$zastaraInfo['absolute_deadline']->format('d.m.Y.')}).",
                legalBasis: "čl. 81. KZ",
                echrBasis: null,
                evidence: $zastaraInfo,
                recommendedAction: "Pratiti rokove zastare.",
                confidence: 0.9,
            );
        }

        return $flags;
    }

    private function computeZastara(float $maxPenalty, Carbon $offenseDate): array
    {
        $relativeYears = 6; // Default for fine-only

        foreach (self::ZASTARA_TABLE as $row) {
            if ($maxPenalty > $row['min_penalty_years'] && $maxPenalty <= $row['max_penalty_years']) {
                $relativeYears = $row['relative'];
                break;
            }
        }

        // Special: if maxPenalty == 15, it falls in the 10-15 bracket = 25 years
        if ($maxPenalty >= 15) {
            $relativeYears = 40; // dugotrajni zatvor
        }

        $absoluteYears = $relativeYears * 2; // čl. 82. st. 3. KZ

        return [
            'offense_date' => $offenseDate->toDateString(),
            'max_penalty_years' => $maxPenalty,
            'relative_years' => $relativeYears,
            'absolute_years' => $absoluteYears,
            'relative_deadline' => $offenseDate->copy()->addYears($relativeYears)->toDateString(),
            'absolute_deadline' => $offenseDate->copy()->addYears($absoluteYears),
        ];
    }

    /**
     * Find charged offense from entity extraction results.
     * Looks for patterns like "čl. 190. st. 2. KZ" in indictment/criminal complaint.
     */
    private function findChargedOffense(array $analysisData): ?array
    {
        // Prioritize documents that are indictments or criminal complaints
        foreach ($analysisData['entities'] ?? [] as $docId => $entities) {
            $lawRefs = $entities['law_references'] ?? [];
            foreach ($lawRefs as $ref) {
                // Match "čl. NNN. st. N. KZ" pattern
                if (preg_match('/čl(?:ank[aue]|\.)\s*(\d+)\.\s*(?:st(?:av[ackeu]*|\.)?\s*(\d+)\.)?/ui', $ref, $m)) {
                    $article = (int)$m[1];
                    $paragraph = (int)($m[2] ?? 1);

                    if (isset(self::OFFENSE_PENALTIES[$article])) {
                        return ['article' => $article, 'paragraph' => $paragraph];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Find offense date — look for dates classified as 'zapljena', 'uhicenje',
     * or earliest event in the timeline.
     */
    private function findOffenseDate(array $analysisData): ?Carbon
    {
        $candidates = [];

        foreach ($analysisData['dates_with_context'] ?? [] as $docId => $dateData) {
            foreach ($dateData['dates'] ?? [] as $d) {
                $eventType = $d['event_type'] ?? null;

                // Offense-indicating event types get priority
                if (in_array($eventType, ['zapljena', 'uhicenje', 'pretraga'])) {
                    $candidates[] = ['date' => $d['date'], 'priority' => 1];
                } elseif ($eventType === 'prijava') {
                    $candidates[] = ['date' => $d['date'], 'priority' => 2];
                } elseif (!empty($d['date'])) {
                    $candidates[] = ['date' => $d['date'], 'priority' => 3];
                }
            }
        }

        if (empty($candidates)) return null;

        // Sort: priority first, then earliest date
        usort($candidates, function ($a, $b) {
            $pCmp = $a['priority'] <=> $b['priority'];
            return $pCmp !== 0 ? $pCmp : $a['date'] <=> $b['date'];
        });

        return Carbon::parse($candidates[0]['date']);
    }
}
```

---

### Task 30: DefenseTimeAdequacyChecker

**Files:**
- Create: `app/Services/Defense/Detectors/DefenseTimeAdequacyChecker.php`

**Depends on:** DateContextExtractor (Task 22)

**How it works:** Pure timestamp arithmetic. Extracts procedural milestone dates (indictment delivery, hearing date, evidence disclosure, detention start) and checks gaps against ECHR minimums.

```php
<?php

namespace App\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;
use Carbon\Carbon;

class DefenseTimeAdequacyChecker implements DefenseTacticDetectorInterface
{
    /**
     * Minimum time gaps (in days) between procedural events.
     * Based on ZKP provisions and ECHR case law.
     */
    private const TIME_THRESHOLDS = [
        // [event_a_type, event_b_type, min_days, severity, description_hr]
        ['dostava', 'rociste', 8, 'high', 'Dostava optužnice → ročište (min. 8 dana, čl. 374. ZKP)'],
        ['uhicenje', 'ispitivanje', 2, 'critical', 'Uhićenje → sudsko ispitivanje (max 48h, čl. 112. ZKP)'],
        ['nalog_izdavanje', 'pretraga', 0, 'critical', 'Nalog mora PRETHODITI pretrazi (čl. 246. ZKP)'],
        ['prijava', 'dostava', 15, 'medium', 'Kaznena prijava → dostava obrani (čl. 341. ZKP)'],
    ];

    /**
     * ECHR reasonable time benchmarks (Kirinčić v. Croatia).
     */
    private const ECHR_REASONABLE_TIME = [
        'warning_years' => 5,
        'violation_years' => 10,
        'clear_violation_years' => 15,
    ];

    public function tactic(): string { return 'defense_time_adequacy'; }
    public function label(): string { return 'Pripremljenost obrane / razumni rok'; }
    public function requires(): array { return ['dates_with_context']; }

    public function detect(string $caseId, array $analysisData): array
    {
        $flags = [];

        // Collect all dates with event types across all documents
        $events = [];
        foreach ($analysisData['dates_with_context'] ?? [] as $docId => $dateData) {
            foreach ($dateData['dates'] ?? [] as $d) {
                if (!empty($d['date']) && !empty($d['event_type'])) {
                    $events[] = [
                        'date' => $d['date'],
                        'time' => $d['time'] ?? null,
                        'type' => $d['event_type'],
                        'context' => $d['context'] ?? '',
                        'doc_id' => $docId,
                    ];
                }
            }
        }

        // Sort chronologically
        usort($events, fn($a, $b) => $a['date'] <=> $b['date']);

        // Check sequential time gaps
        foreach (self::TIME_THRESHOLDS as [$typeA, $typeB, $minDays, $severity, $desc]) {
            $eventsA = array_filter($events, fn($e) => $e['type'] === $typeA);
            $eventsB = array_filter($events, fn($e) => $e['type'] === $typeB);

            foreach ($eventsA as $a) {
                foreach ($eventsB as $b) {
                    $dateA = Carbon::parse($a['date']);
                    $dateB = Carbon::parse($b['date']);
                    $gap = $dateA->diffInDays($dateB, false); // signed

                    // Special: nalog must PRECEDE pretraga
                    if ($typeA === 'nalog_izdavanje' && $typeB === 'pretraga') {
                        if ($gap < 0) {
                            // Search happened BEFORE warrant — critical violation
                            $flags[] = new DefenseFlag(
                                tactic: 'defense_time_adequacy',
                                severity: DefenseFlag::SEVERITY_CRITICAL,
                                title: 'PRETRAGA PRIJE NALOGA',
                                description: "Pretraga provedena {$b['date']} ali nalog izdan {$a['date']} "
                                    . "— pretraga prethodi nalogu za " . abs($gap) . " dana!",
                                legalBasis: 'čl. 246. ZKP, čl. 10. st. 2. ZKP',
                                echrBasis: null,
                                evidence: [
                                    'warrant_date' => $a['date'],
                                    'search_date' => $b['date'],
                                    'gap_days' => $gap,
                                    'warrant_doc' => $a['doc_id'],
                                    'search_doc' => $b['doc_id'],
                                ],
                                recommendedAction: 'Zahtijevati izdvajanje svih dokaza proizašlih iz pretrage '
                                    . 'provedene bez valjanog naloga (čl. 10. st. 2. t. 1. ZKP).',
                                confidence: 0.95,
                            );
                        }
                        continue;
                    }

                    // Check minimum gaps
                    if ($gap >= 0 && $gap < $minDays) {
                        $flags[] = new DefenseFlag(
                            tactic: 'defense_time_adequacy',
                            severity: $severity,
                            title: "Nedovoljan rok: {$desc}",
                            description: "{$typeA} ({$a['date']}) → {$typeB} ({$b['date']}) = {$gap} dana. "
                                . "Minimum: {$minDays} dana.",
                            legalBasis: $desc,
                            echrBasis: 'Dvorski v. Croatia [GC] (2015)',
                            evidence: [
                                'event_a' => $a,
                                'event_b' => $b,
                                'gap_days' => $gap,
                                'minimum_days' => $minDays,
                            ],
                            recommendedAction: 'Istaknuti povredu prava na pripremu obrane. '
                                . 'Zahtijevati odgodu i/ili poništenje radnji provedenih bez '
                                . 'dovoljnog vremena za pripremu.',
                            confidence: 0.85,
                        );
                    }
                }
            }
        }

        // Check total proceeding duration (ECHR reasonable time)
        if (!empty($events)) {
            $earliest = Carbon::parse($events[0]['date']);
            $latest = Carbon::parse(end($events)['date']);
            $totalYears = $earliest->diffInYears($latest);

            if ($totalYears >= self::ECHR_REASONABLE_TIME['clear_violation_years']) {
                $flags[] = new DefenseFlag(
                    tactic: 'defense_time_adequacy',
                    severity: DefenseFlag::SEVERITY_HIGH,
                    title: "Postupak traje {$totalYears} godina — očita povreda razumnog roka",
                    description: "Trajanje postupka ({$totalYears} god.) prelazi ECHR prag od "
                        . self::ECHR_REASONABLE_TIME['clear_violation_years'] . " godina "
                        . "(Kirinčić i dr. v. Hrvatske, 2020).",
                    legalBasis: 'čl. 29. Ustav RH',
                    echrBasis: 'Kirinčić i dr. v. Hrvatske (2020), čl. 6. ECHR',
                    evidence: ['earliest' => $earliest->toDateString(), 'latest' => $latest->toDateString()],
                    recommendedAction: 'Podnijeti zahtjev za zaštitu prava na suđenje u razumnom roku '
                        . '(čl. 63.-70. Zakona o sudovima). Razmotriti ustavnu tužbu.',
                    confidence: 0.9,
                );
            } elseif ($totalYears >= self::ECHR_REASONABLE_TIME['warning_years']) {
                $flags[] = new DefenseFlag(
                    tactic: 'defense_time_adequacy',
                    severity: DefenseFlag::SEVERITY_MEDIUM,
                    title: "Postupak traje {$totalYears} godina — približava se povredi razumnog roka",
                    description: "ECHR prag upozorenja dosegnut.",
                    legalBasis: 'čl. 29. Ustav RH',
                    echrBasis: 'čl. 6. ECHR',
                    evidence: ['total_years' => $totalYears],
                    recommendedAction: 'Dokumentirati sve periode neaktivnosti suda za eventualnu pritužbu.',
                    confidence: 0.8,
                );
            }
        }

        return $flags;
    }
}
```

---

### Task 31: NeBisInIdemDetector

**Files:**
- Create: `app/Services/Defense/Detectors/NeBisInIdemDetector.php`

**Depends on:** MetacaseDetector (Task 24), CaseReferenceExtractor (Task 21), EntityExtractor (Task 6)

**How it works:** The MetacaseDetector already identifies when Pp Prz (prekršajni) and K- (kazneni) cases coexist. This detector checks whether the facts overlap — using extracted OIBs, dates, and locations to determine if the same conduct is being prosecuted twice.

```php
<?php

namespace App\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;

class NeBisInIdemDetector implements DefenseTacticDetectorInterface
{
    public function tactic(): string { return 'ne_bis_in_idem'; }
    public function label(): string { return 'Ne bis in idem'; }
    public function requires(): array
    {
        return ['case_hierarchy', 'case_references', 'dates_with_context', 'entities'];
    }

    public function detect(string $caseId, array $analysisData): array
    {
        $flags = [];
        $hierarchy = $analysisData['case_hierarchy'] ?? [];

        if (empty($hierarchy)) return $flags;

        // Find all prekršajni satellite cases
        $prekrsajniSatellites = array_filter(
            $hierarchy,
            fn($h) => in_array($h->satellite_case_type ?? $h['satellite_case_type'] ?? '', ['Pp Prz', 'Pp J', 'Pn'])
        );

        if (empty($prekrsajniSatellites)) return $flags;

        foreach ($prekrsajniSatellites as $satellite) {
            $satCase = $satellite->satellite_case_number ?? $satellite['satellite_case_number'];
            $mainCase = $satellite->main_case_number ?? $satellite['main_case_number'];
            $relationship = $satellite->relationship ?? $satellite['relationship'];

            // If the prekršajni case exists alongside a kazneni case
            // for the same defendant (same OIB / name), flag ne bis in idem
            $flags[] = new DefenseFlag(
                tactic: 'ne_bis_in_idem',
                severity: DefenseFlag::SEVERITY_HIGH,
                title: "Ne bis in idem: {$satCase} ↔ {$mainCase}",
                description: "Prekršajni predmet {$satCase} ({$relationship}) vodi se "
                    . "paralelno s kaznenim predmetom {$mainCase}. "
                    . "Ako se činjenični opisi podudaraju, moguća je povreda načela ne bis in idem "
                    . "(Maresti v. Hrvatske, 2009). "
                    . "Prema čl. 10. Prekršajnog zakona, pokretanje kaznenog postupka "
                    . "za djelo koje obuhvaća prekršaj isključuje prekršajni progon.",
                legalBasis: 'čl. 31. Ustav RH, čl. 12. ZKP, čl. 10. Prekršajni zakon',
                echrBasis: 'Maresti v. Hrvatske (2009), Zolotukhin v. Russia [GC] (2009)',
                evidence: [
                    'prekrsajni_case' => $satCase,
                    'kazneni_case' => $mainCase,
                    'relationship' => $relationship,
                    'co_occurring_documents' => $satellite->evidence ?? $satellite['evidence'] ?? [],
                ],
                recommendedAction: "1. Pribaviti pravomoćnu odluku prekršajnog suda u predmetu {$satCase}.\n"
                    . "2. Usporediti činjenični opis prekršaja s činjeničnim opisom optužnice.\n"
                    . "3. Ako se radi o istim činjenicama (Zolotukhin test: 'identical facts or facts "
                    . "which are substantially the same'), istaknuti prigovor ne bis in idem.\n"
                    . "4. Pozvati se na VSRH Kzz 7/11-3, VSRH III Kr 214/09-9, VSRH I Kž 403/10-5.",
                confidence: 0.75, // Needs manual verification of činjenični opis
            );
        }

        return $flags;
    }
}
```

---

## Tier B: Cross-Document Pattern Matching (no AI needed)

### Task 32: ChainOfCustodyAnalyzer

**Files:**
- Create: `app/Services/Defense/Detectors/ChainOfCustodyAnalyzer.php`

**Depends on:** DateContextExtractor (Task 22), EntityExtractor (Task 6), CaseFileRegistry (Task 23)

**How it works:** Tracks physical evidence items across documents. If an item appears in a search record (zapljena) but has no corresponding transfer record, no lab analysis receipt, or a time gap > 48 hours between documents, it flags a chain of custody break.

```php
<?php

namespace App\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;
use Carbon\Carbon;

class ChainOfCustodyAnalyzer implements DefenseTacticDetectorInterface
{
    public function tactic(): string { return 'chain_of_custody'; }
    public function label(): string { return 'Lanac čuvanja dokaza'; }
    public function requires(): array
    {
        return ['dates_with_context', 'entities', 'case_reference_registry'];
    }

    public function detect(string $caseId, array $analysisData): array
    {
        $flags = [];

        // Step 1: Build evidence item timeline
        // Track items mentioned across documents: seizure → storage → lab → court
        $evidenceEvents = [];

        foreach ($analysisData['dates_with_context'] ?? [] as $docId => $dateData) {
            foreach ($dateData['dates'] ?? [] as $d) {
                if (in_array($d['event_type'] ?? '', ['zapljena', 'vještačenje'])) {
                    $evidenceEvents[] = [
                        'date' => $d['date'],
                        'type' => $d['event_type'],
                        'context' => $d['context'] ?? '',
                        'doc_id' => $docId,
                    ];
                }
            }
        }

        // Sort chronologically
        usort($evidenceEvents, fn($a, $b) => $a['date'] <=> $b['date']);

        // Step 2: Check for time gaps between seizure and analysis
        $seizures = array_filter($evidenceEvents, fn($e) => $e['type'] === 'zapljena');
        $analyses = array_filter($evidenceEvents, fn($e) => $e['type'] === 'vještačenje');

        foreach ($seizures as $seizure) {
            $seizureDate = Carbon::parse($seizure['date']);
            $foundAnalysis = false;

            foreach ($analyses as $analysis) {
                $analysisDate = Carbon::parse($analysis['date']);
                $gap = $seizureDate->diffInDays($analysisDate);

                if ($gap > 0) {
                    $foundAnalysis = true;

                    if ($gap > 90) {
                        $flags[] = new DefenseFlag(
                            tactic: 'chain_of_custody',
                            severity: DefenseFlag::SEVERITY_MEDIUM,
                            title: "Dugačak interval zapljena → vještačenje ({$gap} dana)",
                            description: "Između zapljene ({$seizure['date']}) i vještačenja ({$analysis['date']}) "
                                . "prošlo je {$gap} dana. Nedostaju dokumenti o čuvanju, "
                                . "prijenosu i integritetu dokaza u tom periodu.",
                            legalBasis: 'čl. 250. ZKP, čl. 261-262. ZKP',
                            echrBasis: null,
                            evidence: [
                                'seizure_date' => $seizure['date'],
                                'seizure_doc' => $seizure['doc_id'],
                                'analysis_date' => $analysis['date'],
                                'analysis_doc' => $analysis['doc_id'],
                                'gap_days' => $gap,
                            ],
                            recommendedAction: "Zatražiti potpunu dokumentaciju o lancu čuvanja: "
                                . "tko je preuzeo predmete, gdje su čuvani, tko im je pristupao. "
                                . "Osporiti autentičnost dokaza ako dokumentacija ne postoji.",
                            confidence: 0.7,
                        );
                    }
                    break;
                }
            }

            if (!$foundAnalysis && !empty($analyses)) {
                $flags[] = new DefenseFlag(
                    tactic: 'chain_of_custody',
                    severity: DefenseFlag::SEVERITY_MEDIUM,
                    title: "Zaplijena bez vidljivog vještačenja",
                    description: "Zapljena dokumentirana ({$seizure['date']}) ali nema vidljivog "
                        . "vještačenja u spisu — ili nedostaje nalaz vještaka, ili zaplijenjeni "
                        . "predmeti nikada nisu analizirani.",
                    legalBasis: 'čl. 250. ZKP',
                    echrBasis: null,
                    evidence: ['seizure_date' => $seizure['date'], 'seizure_doc' => $seizure['doc_id']],
                    recommendedAction: "Provjeriti je li nalaz vještaka dostavljen obrani. "
                        . "Ako nedostaje, zatražiti ga od tužiteljstva (čl. 184. ZKP).",
                    confidence: 0.6,
                );
            }
        }

        // Step 3: Check for missing solenitetni svjedoci (witness signatures on search records)
        foreach ($analysisData['entities'] ?? [] as $docId => $entities) {
            $persons = $entities['persons'] ?? [];
            $evidence = $entities['evidence'] ?? [];

            // If document is a search record but has < 2 witnesses
            $isSearchRecord = false;
            foreach ($evidence as $ev) {
                if (preg_match('/Zapisnik\s+o\s+pretraz/ui', $ev)) {
                    $isSearchRecord = true;
                    break;
                }
            }

            if ($isSearchRecord) {
                $witnessCount = 0;
                foreach ($persons as $p) {
                    if (preg_match('/svjedok/ui', $p)) $witnessCount++;
                }

                if ($witnessCount < 2) {
                    $flags[] = new DefenseFlag(
                        tactic: 'chain_of_custody',
                        severity: DefenseFlag::SEVERITY_HIGH,
                        title: "Manje od 2 svjedoka na zapisniku o pretrazi",
                        description: "Zapisnik o pretrazi (dokument {$docId}) navodi {$witnessCount} svjedoka. "
                            . "Čl. 246. st. 7. ZKP zahtijeva najmanje 2 punoljetna svjedoka (solenitetni svjedoci).",
                        legalBasis: 'čl. 246. st. 7. ZKP',
                        echrBasis: null,
                        evidence: ['doc_id' => $docId, 'witness_count' => $witnessCount],
                        recommendedAction: "Ako nisu bila prisutna najmanje 2 punoljetna svjedoka, "
                            . "zapisnik o pretrazi je nezakonit i svi pronađeni dokazi su nezakoniti "
                            . "(čl. 10. st. 2. t. 1. ZKP).",
                        confidence: 0.65, // Witness names might not be extracted perfectly
                    );
                }
            }
        }

        return $flags;
    }
}
```

---

### Task 33: FruitOfPoisonousTreeMapper

**Files:**
- Create: `app/Services/Defense/Detectors/FruitOfPoisonousTreeMapper.php`

**Depends on:** DateContextExtractor (Task 22), existing AI ContradictionDetector (Task 15)

**How it works:** When any evidence is flagged for exclusion (by other detectors or manually marked), this builds a dependency tree: what other evidence was discovered BECAUSE of the tainted evidence? Uses temporal ordering + context analysis.

```php
<?php

namespace App\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;
use Carbon\Carbon;

class FruitOfPoisonousTreeMapper implements DefenseTacticDetectorInterface
{
    public function tactic(): string { return 'fruit_of_poisonous_tree'; }
    public function label(): string { return 'Plodovi otrovnog drveta (čl. 10. st. 2. t. 4. ZKP)'; }
    public function requires(): array
    {
        return ['dates_with_context', 'entities', 'case_references'];
    }

    public function detect(string $caseId, array $analysisData): array
    {
        $flags = [];

        // Step 1: Identify potentially tainted primary evidence
        // Load any existing defense flags for this case that mark evidence as excludable
        $existingFlags = \DB::table('defense_flags')
            ->where('case_id', $caseId)
            ->whereIn('tactic', ['chain_of_custody', 'defense_time_adequacy'])
            ->where('severity', DefenseFlag::SEVERITY_CRITICAL)
            ->get();

        if ($existingFlags->isEmpty()) {
            // No primary evidence exclusion found — check if search warrant itself is challenged
            // Look for the pretraga → nalog timing violation
            $searchFlags = \DB::table('defense_flags')
                ->where('case_id', $caseId)
                ->where('title', 'LIKE', '%PRETRAGA PRIJE NALOGA%')
                ->exists();

            if (!$searchFlags) {
                return []; // No tainted evidence to cascade from
            }
        }

        // Step 2: Build temporal evidence chain
        // All evidence/actions that occurred AFTER the tainted event
        $allEvents = [];
        foreach ($analysisData['dates_with_context'] ?? [] as $docId => $dateData) {
            foreach ($dateData['dates'] ?? [] as $d) {
                if (!empty($d['date'])) {
                    $allEvents[] = array_merge($d, ['doc_id' => $docId]);
                }
            }
        }

        usort($allEvents, fn($a, $b) => $a['date'] <=> $b['date']);

        // Find tainted event date (earliest critical evidence exclusion)
        $taintedDate = null;
        foreach ($existingFlags as $f) {
            $evidence = json_decode($f->evidence, true);
            $date = $evidence['search_date'] ?? $evidence['seizure_date'] ?? null;
            if ($date && (!$taintedDate || $date < $taintedDate)) {
                $taintedDate = $date;
            }
        }

        if (!$taintedDate) return $flags;

        // Step 3: Everything discovered AFTER tainted evidence is potentially derivative
        $derivativeEvents = array_filter($allEvents, function ($e) use ($taintedDate) {
            return $e['date'] > $taintedDate
                && in_array($e['event_type'] ?? '', [
                    'zapljena', 'ispitivanje', 'vještačenje', 'nalog_izdavanje', 'pretraga'
                ]);
        });

        if (!empty($derivativeEvents)) {
            $eventDescriptions = array_map(function ($e) {
                $type = $e['event_type'] ?? 'nepoznato';
                return "{$type} ({$e['date']}, dok. {$e['doc_id']})";
            }, $derivativeEvents);

            $flags[] = new DefenseFlag(
                tactic: 'fruit_of_poisonous_tree',
                severity: DefenseFlag::SEVERITY_HIGH,
                title: 'Mogući plodovi otrovnog drveta — ' . count($derivativeEvents) . ' radnji',
                description: "Nakon nezakonitog dokaza ({$taintedDate}) provedeno je "
                    . count($derivativeEvents) . " radnji koje su potencijalno derivativni dokazi:\n"
                    . implode("\n", array_slice($eventDescriptions, 0, 10)),
                legalBasis: 'čl. 10. st. 2. t. 4. ZKP',
                echrBasis: 'Gäfgen v. Germany [GC] (2010)',
                evidence: [
                    'tainted_date' => $taintedDate,
                    'derivative_events' => array_slice($derivativeEvents, 0, 20),
                    'total_derivative' => count($derivativeEvents),
                ],
                recommendedAction: "Zahtijevati izdvajanje SVIH dokaza za koje se saznalo iz nezakonitih "
                    . "dokaza (čl. 10. st. 2. t. 4. ZKP). U hrvatskom pravu NE POSTOJI iznimka "
                    . "dobre vjere (good faith exception). Kaskadni učinak je obvezan, ne diskrecijski.\n\n"
                    . "Jedina iznimka: čl. 10. st. 3. ZKP — za djela u nadležnosti županijskog suda "
                    . "koja su 'osobito teška', interes progona može prevagnuti. Ali teret dokaza "
                    . "je na tužiteljstvu.",
                confidence: 0.65, // Temporal proximity ≠ causal connection — needs manual review
            );
        }

        return $flags;
    }
}
```

---

### Task 34: ProsecutorialDisclosureChecker

**Files:**
- Create: `app/Services/Defense/Detectors/ProsecutorialDisclosureChecker.php`

**Depends on:** CaseFileRegistry (Task 23), EntityExtractor (Task 6)

**How it works:** Cross-references what's MENTIONED in documents against what's PRESENT in the case file (from CaseFileRegistry). If a police report mentions a witness who never appears in the disclosure, or references forensic results that aren't in the file, that's a disclosure gap.

```php
<?php

namespace App\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;

class ProsecutorialDisclosureChecker implements DefenseTacticDetectorInterface
{
    public function tactic(): string { return 'prosecutorial_disclosure'; }
    public function label(): string { return 'Obveza razotkrivanja dokaza (čl. 9./184. ZKP)'; }
    public function requires(): array
    {
        return ['case_reference_registry', 'entities'];
    }

    public function detect(string $caseId, array $analysisData): array
    {
        $flags = [];

        // Step 1: Check CaseFileRegistry for missing references
        $registry = $analysisData['case_reference_registry'] ?? [];
        $missingRefs = array_filter($registry, function ($r) {
            $status = is_object($r) ? $r->status : $r['status'];
            return $status === 'missing';
        });

        if (count($missingRefs) > 0) {
            $missingList = array_map(function ($r) {
                $type = is_object($r) ? $r->reference_type : $r['reference_type'];
                $value = is_object($r) ? $r->reference_value : $r['reference_value'];
                return "{$type}: {$value}";
            }, $missingRefs);

            $flags[] = new DefenseFlag(
                tactic: 'prosecutorial_disclosure',
                severity: count($missingRefs) > 3 ? DefenseFlag::SEVERITY_HIGH : DefenseFlag::SEVERITY_MEDIUM,
                title: count($missingRefs) . ' referenci bez izvornog dokumenta u spisu',
                description: "Sljedeće reference se spominju u dokumentima ali izvorni dokumenti "
                    . "nisu pronađeni u spisu:\n" . implode("\n", array_slice($missingList, 0, 15)),
                legalBasis: 'čl. 9. ZKP, čl. 184. ZKP',
                echrBasis: 'Matanović v. Hrvatske (2017)',
                evidence: [
                    'missing_count' => count($missingRefs),
                    'missing_references' => array_slice($missingList, 0, 30),
                ],
                recommendedAction: "Podnijeti zahtjev za uvid u spis (čl. 184. ZKP) i zatražiti "
                    . "dostavu svih nedostajućih dokumenata. Ako tužiteljstvo ne dostavi, "
                    . "istaknuti povredu čl. 9. ZKP (obveza prikupljanja dokaza o krivnji "
                    . "i nedužnosti s jednakom pažnjom).",
                confidence: 0.8,
            );
        }

        // Step 2: Check if all referenced witnesses appear in evidence list
        $allWitnesses = [];
        $allEvidencePersons = [];

        foreach ($analysisData['entities'] ?? [] as $docId => $entities) {
            foreach ($entities['persons'] ?? [] as $person) {
                if (preg_match('/svjedok/ui', $person)) {
                    $allWitnesses[] = ['person' => $person, 'doc_id' => $docId];
                }
            }
        }

        // If witnesses mentioned in police reports but not in the indictment witness list
        // This is a simplified check — in production, use NLP for name matching
        if (count($allWitnesses) > 0) {
            $uniqueWitnesses = count(array_unique(array_column($allWitnesses, 'person')));
            $flags[] = new DefenseFlag(
                tactic: 'prosecutorial_disclosure',
                severity: DefenseFlag::SEVERITY_INFO,
                title: "{$uniqueWitnesses} svjedoka identificirano u spisu",
                description: "Sustav je identificirao {$uniqueWitnesses} jedinstvenih referenci "
                    . "na svjedoke. Potrebno ručno provjeriti jesu li svi predloženi kao svjedoci obrane/optužbe.",
                legalBasis: 'čl. 184. ZKP, čl. 9. ZKP',
                echrBasis: null,
                evidence: ['witness_count' => $uniqueWitnesses],
                recommendedAction: "Usporediti sa svjedočkom listom optužnice.",
                confidence: 0.5,
            );
        }

        return $flags;
    }
}
```

---

## Tier C: AI-Assisted Detectors (Claude API)

### Task 35: JudicialBiasDetector

**Files:**
- Create: `app/Services/Defense/Detectors/JudicialBiasDetector.php`

**Depends on:** EntityExtractor, AI Summary/KeyFacts analyzers (Tasks 13-14), ClaudeAnalysisService (Task 12)

**How it works:** Two-phase detection:
1. **Statistical** (no AI): Count defense motion denial rate from extracted rješenja
2. **Text similarity** (AI): Compare prosecution submission text against court ruling text — if > 70% similar, flag potential copy-paste reasoning

```php
<?php

namespace App\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;
use App\Services\Analysis\AI\ClaudeAnalysisService;

class JudicialBiasDetector implements DefenseTacticDetectorInterface
{
    public function __construct(
        private ClaudeAnalysisService $claude,
    ) {}

    public function tactic(): string { return 'judicial_bias'; }
    public function label(): string { return 'Nepristranost suca (čl. 32. ZKP)'; }
    public function requires(): array
    {
        return ['entities', 'ai_summary', 'ai_key_facts'];
    }

    public function detect(string $caseId, array $analysisData): array
    {
        $flags = [];

        // Phase 1: Statistical — defense motion denial rate
        // Count rješenja that reference "odbija se" vs "usvaja se" for defense requests
        $denials = 0;
        $approvals = 0;

        foreach ($analysisData['ai_key_facts'] ?? [] as $docId => $facts) {
            foreach ($facts['key_facts'] ?? [] as $fact) {
                $text = mb_strtolower($fact['claim'] ?? '');
                if (preg_match('/prijedlog\s+(obrane|branitelja|okrivljenika)/u', $text)) {
                    if (preg_match('/odbij[ae]/u', $text)) $denials++;
                    if (preg_match('/usvaj[ae]/u', $text)) $approvals++;
                }
            }
        }

        $total = $denials + $approvals;
        if ($total >= 3) {
            $denialRate = round(($denials / $total) * 100);

            if ($denialRate >= 85) {
                $flags[] = new DefenseFlag(
                    tactic: 'judicial_bias',
                    severity: DefenseFlag::SEVERITY_MEDIUM,
                    title: "Odbijeno {$denialRate}% prijedloga obrane ({$denials}/{$total})",
                    description: "Od {$total} identificiranih prijedloga obrane, sud je odbio {$denials} "
                        . "({$denialRate}%). Ovaj omjer može ukazivati na pristranost.",
                    legalBasis: 'čl. 32. st. 2. ZKP — izuzeće suca',
                    echrBasis: 'Piersack v. Belgium (1982), Mežnarić v. Hrvatske (2005)',
                    evidence: [
                        'denials' => $denials,
                        'approvals' => $approvals,
                        'total' => $total,
                        'denial_rate' => $denialRate,
                    ],
                    recommendedAction: "Razmotriti zahtjev za izuzeće suca (čl. 34. ZKP). "
                        . "Dokumentirati svaki odbijeni prijedlog s obrazloženjem. "
                        . "ECHR test: 'objectively justified doubts about impartiality'.",
                    confidence: 0.6, // Statistics alone don't prove bias
                );
            }
        }

        // Phase 2: AI text similarity — prosecution submission vs court ruling
        // This is dispatched as a separate Claude API call
        $prosecutionTexts = [];
        $courtRulingTexts = [];

        foreach ($analysisData['ai_summary'] ?? [] as $docId => $summary) {
            $docType = $summary['document_type'] ?? '';
            if (in_array($docType, ['optuznica', 'prijedlog_tuzilastva', 'kaznena_prijava'])) {
                $prosecutionTexts[$docId] = $summary['summary'] ?? '';
            }
            if (in_array($docType, ['presuda', 'rjesenje'])) {
                $courtRulingTexts[$docId] = $summary['summary'] ?? '';
            }
        }

        if (!empty($prosecutionTexts) && !empty($courtRulingTexts)) {
            // Use Claude to compare reasoning similarity
            $prompt = "Usporedi obrazloženja sljedećih dokumenata i ocijeni koliko je "
                . "sud kopirao argumentaciju tužiteljstva vs. proveo vlastitu analizu.\n\n"
                . "TUŽITELJSTVO:\n" . implode("\n---\n", $prosecutionTexts)
                . "\n\nSUD:\n" . implode("\n---\n", $courtRulingTexts)
                . "\n\nOdgovori u JSON formatu: "
                . '{"similarity_percent": N, "copied_sections": ["..."], "independent_reasoning": ["..."]}';

            try {
                $result = $this->claude->analyze($prompt, 'judicial_bias_comparison');

                $similarity = $result['similarity_percent'] ?? 0;

                if ($similarity >= 70) {
                    $flags[] = new DefenseFlag(
                        tactic: 'judicial_bias',
                        severity: $similarity >= 85 ? DefenseFlag::SEVERITY_HIGH : DefenseFlag::SEVERITY_MEDIUM,
                        title: "Obrazloženje suda {$similarity}% slično tužiteljstvu",
                        description: "AI analiza pokazuje {$similarity}% sličnost između obrazloženja "
                            . "tužiteljstva i sudskog obrazloženja. Čl. 468. ZKP zahtijeva da presuda "
                            . "ima određeno (jasno) i potpuno (kompletno) obrazloženje.",
                        legalBasis: 'čl. 468. ZKP, čl. 32. ZKP',
                        echrBasis: 'García Ruiz v. Spain [GC] (1999)',
                        evidence: [
                            'similarity_percent' => $similarity,
                            'copied_sections' => $result['copied_sections'] ?? [],
                            'independent_reasoning' => $result['independent_reasoning'] ?? [],
                        ],
                        recommendedAction: "Istaknuti bitnu povredu odredaba kaznenog postupka u žalbi "
                            . "(čl. 468. st. 1. t. 11. ZKP — presuda nema razloga ili razlozi nisu jasni). "
                            . "Sud je dužan provesti vlastitu ocjenu dokaza, a ne preuzeti "
                            . "argumentaciju tužiteljstva.",
                        confidence: 0.7,
                    );
                }
            } catch (\Throwable $e) {
                // AI comparison failed — log but don't block
            }
        }

        return $flags;
    }
}
```

---

### Task 36: ProportionalityChecker + ExpertWitnessValidator + ConstitutionalViolationScanner

**Files:**
- Create: `app/Services/Defense/Detectors/ProportionalityChecker.php`
- Create: `app/Services/Defense/Detectors/ExpertWitnessValidator.php`
- Create: `app/Services/Defense/Detectors/ConstitutionalViolationScanner.php`

These three follow the same pattern — each implements `DefenseTacticDetectorInterface`, consumes existing analysis outputs, and returns `DefenseFlag[]`. I'll sketch the core logic briefly:

**ProportionalityChecker:**
- Compares charged offense severity (from ZastaraCalculator's penalty lookup) against investigative measures used
- If charged offense < 10 years max penalty but surveillance was used → flag (čl. 332. ZKP)
- If charged offense is possession-level but home search was conducted → flag proportionality
- `confidence: 0.7` — needs legal judgment

**ExpertWitnessValidator:**
- Extracts vještak names and institution references from EntityExtractor
- Cross-references against known lab names (Centar za forenzična ispitivanja "Ivan Vučetić")
- Checks for ISO 17025 methodology mentions in expert reports
- Flags if expert report lacks: method description, measurement uncertainty, accreditation reference
- `confidence: 0.5` — many details need manual verification

**ConstitutionalViolationScanner:**
- AI-powered: sends full case timeline + key facts to Claude with a prompt asking for constitutional/ECHR violation patterns
- Checks for Dvorski pattern (lawyer blocked at station), Matanović pattern (surveillance not disclosed), Kirinčić pattern (excessive duration)
- Outputs specific constitutional article + ECHR precedent for each finding
- `confidence: varies` — AI determines per-finding

---

## Task 37: Artisan command + pipeline integration

**Files:**
- Create: `app/Console/Commands/RunDefenseAnalysisCommand.php`
- Modify: `app/Services/Analysis/DocumentAnalysisPipeline.php`

```php
<?php

namespace App\Console\Commands;

use App\Services\Defense\DefenseReportBuilder;
use Illuminate\Console\Command;

class RunDefenseAnalysisCommand extends Command
{
    protected $signature = 'case:defense {case_id} {--tactic= : Run specific tactic only} {--json : JSON output}';
    protected $description = 'Run defense tactic detectors on a case';

    public function handle(DefenseReportBuilder $builder): int
    {
        $caseId = $this->argument('case_id');

        $this->info("Running defense analysis for case {$caseId}...");
        $report = $builder->buildReport($caseId);

        if ($this->option('json')) {
            $this->line(json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return 0;
        }

        // Human-readable output
        $criticals = array_filter($report->flags, fn($f) => $f->severity === 'critical');
        $highs = array_filter($report->flags, fn($f) => $f->severity === 'high');
        $mediums = array_filter($report->flags, fn($f) => $f->severity === 'medium');

        $this->newLine();
        $this->error("  🔴 CRITICAL: " . count($criticals));
        $this->warn("  🟠 HIGH: " . count($highs));
        $this->info("  🟡 MEDIUM: " . count($mediums));
        $this->newLine();

        foreach ($report->flags as $flag) {
            $icon = match($flag->severity) {
                'critical' => '🔴',
                'high' => '🟠',
                'medium' => '🟡',
                'low' => '🔵',
                default => 'ℹ️',
            };

            $this->line("{$icon} [{$flag->tactic}] {$flag->title}");
            $this->line("   Pravni temelj: {$flag->legalBasis}");
            if ($flag->echrBasis) $this->line("   ECHR: {$flag->echrBasis}");
            $this->line("   Pouzdanost: " . round($flag->confidence * 100) . "%");
            $this->line("   Preporuka: {$flag->recommendedAction}");
            $this->newLine();
        }

        $this->info("Analysis completed in {$report->processingTime}s");
        return 0;
    }
}
```

**Pipeline integration — auto-trigger after Layer 4 completes:**

```php
// In DocumentAnalysisPipeline, after case-level AI analysis completes:
if ($allLayersCompleted) {
    RunDefenseAnalysisJob::dispatch($caseId)
        ->onQueue('analysis')
        ->delay(now()->addSeconds(10));
}
```

---

## Updated Sprint Roadmap (Full)

| Sprint | Tasks | Focus | Cost |
|--------|-------|-------|------|
| Sprint 1 | 1-9 | Foundation + basic extraction | $0 |
| Sprint 1.5 | 21-23 | Unified refs + file registry + enhanced dates | $0 |
| Sprint 2 | 10-11, 24 | Cross-doc patterns + metacase | $0 |
| Sprint 3 | 12-14 | AI per-document (Claude API) | ~$0.01-0.05/doc |
| Sprint 3.5 | 25-27 | Claude Code CLI agent | ~$0.05-0.20/session |
| Sprint 4 | 15-17 | AI deep analysis | ~$0.10-0.50/case |
| **Sprint 5** | **28-31** | **Defense Tier A: zastara, time, ne bis in idem** | **$0** |
| **Sprint 5.5** | **32-34** | **Defense Tier B: custody, fruit, disclosure** | **$0** |
| **Sprint 6** | **35-36** | **Defense Tier C: bias, proportionality, constitutional** | **~$0.05-0.15/case** |
| **Sprint 6.5** | **37** | **Pipeline integration + CLI** | **$0** |
| Sprint 7 | 18-20 | UI dashboard + Neo4j sync | $0 |

**Total tasks: 37** | Estimated: 6-8 weeks | Defense detectors: $0 for Tiers A+B, minimal AI cost for Tier C
