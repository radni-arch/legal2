# Plan: E-predmet Widget Power-Up — 6 Assessment Improvements

**Date:** 2026-01-28
**Branch:** `claude/improve-epredmet-widget-sJl8d`
**Scope:** Implement all 6 suggestions from assessment: temporal anomaly detection, per-case argument card, comparison mode, argument strength scoring, extract analytics to service, and export

---

## Prioritized Execution Order

Given the widget is already 1,365 lines in the backend and 1,103 in the analytics blade, we focus on features that deliver the most **actionable defense value** while keeping changes manageable.

**Deferred to future session:** Extract analytics to service class (refactor), PDF/CSV export (requires library install). These are important but don't add new analytical capability.

---

## Batch 1: Temporal Anomaly Detection + Monthly Trend

### Task 1A: Monthly Trend Backend

**File:** `app/Http/Livewire/EpredmetWidget.php`
**Location:** Inside `loadAnalytics()`, after section 16 (document_completeness), before `buildChartData()`

Query monthly warrant counts per court for the selected year range. Compute month-over-month % change and flag anomalous months (>50% increase from previous month or >2x the rolling 3-month average).

```php
// 17. Monthly Trend with Anomaly Detection
$monthlyRaw = $this->caseQuery($year)
    ->where('is_search_warrant', true)
    ->whereNotNull('date_decision')
    ->select(
        DB::raw("DATE_FORMAT(date_decision, '%Y-%m') as month"),
        DB::raw('COUNT(*) as warrants'),
        DB::raw('SUM(CASE WHEN is_same_day THEN 1 ELSE 0 END) as same_day'),
        DB::raw('SUM(CASE WHEN is_weekend THEN 1 ELSE 0 END) as weekend')
    )
    ->groupBy('month')
    ->orderBy('month')
    ->get()
    ->toArray();

// Compute month-over-month changes and rolling average anomalies
$monthly = [];
foreach ($monthlyRaw as $i => $m) {
    $prev = $i > 0 ? $monthlyRaw[$i - 1]['warrants'] : null;
    $mom_change = $prev ? round(100 * ($m['warrants'] - $prev) / max($prev, 1), 0) : null;

    // Rolling 3-month average
    $rollingSlice = array_slice($monthlyRaw, max(0, $i - 3), min($i, 3));
    $rollingAvg = count($rollingSlice) > 0 ? array_sum(array_column($rollingSlice, 'warrants')) / count($rollingSlice) : $m['warrants'];
    $vsRolling = $rollingAvg > 0 ? round($m['warrants'] / $rollingAvg, 2) : 1;

    $isSpike = ($mom_change !== null && $mom_change > 50) || $vsRolling > 2;
    $isDrop = ($mom_change !== null && $mom_change < -40);

    $monthly[] = [
        'month' => $m['month'],
        'warrants' => (int) $m['warrants'],
        'same_day' => (int) $m['same_day'],
        'weekend' => (int) $m['weekend'],
        'same_day_pct' => $m['warrants'] > 0 ? round(100 * $m['same_day'] / $m['warrants'], 0) : 0,
        'mom_change' => $mom_change,
        'vs_rolling_avg' => $vsRolling,
        'is_spike' => $isSpike,
        'is_drop' => $isDrop,
    ];
}

$analytics['monthly_trend'] = $monthly;
```

Also add per-court monthly breakdown for the top 5 courts:

```php
// 17b. Per-Court Monthly Anomalies
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
            DB::raw("DATE_FORMAT(date_decision, '%Y-%m') as month"),
            DB::raw('COUNT(*) as warrants')
        )
        ->groupBy('month')
        ->orderBy('month')
        ->get()
        ->toArray();

    $spikes = [];
    foreach ($rows as $i => $r) {
        $prev = $i > 0 ? $rows[$i - 1]['warrants'] : null;
        $change = $prev ? round(100 * ($r['warrants'] - $prev) / max($prev, 1), 0) : null;
        if ($change !== null && $change > 100) {
            $spikes[] = ['month' => $r['month'], 'warrants' => $r['warrants'], 'change' => $change];
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
```

---

### Task 1B: Monthly Trend Chart Data

**File:** `app/Http/Livewire/EpredmetWidget.php`
**Location:** Inside `buildChartData()`, add chart 9

```php
// Chart 9: Monthly trend with anomalies
$monthlyData = $analytics['monthly_trend'] ?? [];
$charts['monthly_trend'] = [
    'labels' => array_column($monthlyData, 'month'),
    'warrants' => array_column($monthlyData, 'warrants'),
    'same_day_pct' => array_column($monthlyData, 'same_day_pct'),
    'is_spike' => array_column($monthlyData, 'is_spike'),
    'is_drop' => array_column($monthlyData, 'is_drop'),
];
```

---

### Task 1C: Monthly Trend Frontend

**File:** `resources/views/livewire/partials/epredmet-analytics.blade.php`
**Location:** After Document Completeness section, before closing `</div>` of defense analytics

Add:
1. Monthly trend D3.js chart container (full-width, 280px tall) — bar chart with spike bars colored red, drops green, normal blue. Same-day% overlay line.
2. Monthly trend table with MoM change column, vs rolling avg, SPIKE/DROP badges
3. Per-court spike alerts table

---

### Task 1D: Monthly Trend D3.js Chart

**File:** `resources/views/livewire/partials/epredmet-analytics.blade.php`
**Location:** In `<script>` block, add `renderMonthlyTrendChart(data)` method

Features:
- Bar chart with months on x-axis
- Spike bars red, drop bars green, normal blue
- Same-day % line overlay on right y-axis
- Anomaly markers (red triangle above spike bars)
- Tooltips showing month, count, MoM change

---

## Batch 2: Per-Case Argument Card

### Task 2A: Per-Case Argument Generator (Backend)

**File:** `app/Http/Livewire/EpredmetWidget.php`
**Location:** New public method `generateCaseArguments(int $caseId)`

When user clicks a case in the Lookup tab, generate a defense argument card:

```php
public function generateCaseArguments(int $caseId): void
{
    $case = CourtCase::with(['court', 'documents', 'parties'])->find($caseId);
    if (!$case) return;

    $arguments = [];
    $court = $case->court;
    $judge = $case->judge_name;

    // 1. Judge profile argument
    $judgeStats = CourtCase::where('judge_id', $case->judge_id)
        ->where('is_search_warrant', true)
        ->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_same_day THEN 1 ELSE 0 END) as same_day, ...')
        ->first();
    // Build argument text + strength score

    // 2. Court rejection rate
    // 3. Processing time
    // 4. Same-day approval
    // 5. SOKO check
    // 6. Proportionality (misdemeanor + home search)
    // 7. Document completeness for this case
    // 8. Repeat targeting check for defendant
    // 9. Weekend warrant check
    // 10. National comparison (this court vs national average)

    $this->caseArguments = $arguments;
}
```

Add new property: `public array $caseArguments = [];`

---

### Task 2B: Per-Case Argument Card (Frontend)

**File:** `resources/views/livewire/partials/epredmet-lookup.blade.php`
**Location:** After case data display, add "Generate Defense Arguments" button and card

Shows: argument list with strength badges (STRONG/MODERATE/WEAK), each with one-line legal basis, specific data point, and national comparison.

---

## Batch 3: Court/Judge Comparison Mode

### Task 3A: Comparison Backend

**File:** `app/Http/Livewire/EpredmetWidget.php`
**Location:** New method `loadComparison()`

New properties: `$compareCourtA`, `$compareCourtB`, `$comparisonData`

Computes side-by-side: warrants, same-day%, rejection%, HHI, per-capita, avg processing days, top judge profile for each court. Plus national average as baseline.

---

### Task 3B: Comparison Frontend

**File:** `resources/views/livewire/partials/epredmet-analytics.blade.php`
**Location:** New section at top of defense analytics, with two court dropdowns

Side-by-side card layout. D3.js radar chart comparing metrics. Delta column showing which court deviates more from national average.

---

## Batch 4: Argument Strength Scoring

### Task 4A: Scoring Backend

**File:** `app/Http/Livewire/EpredmetWidget.php`
**Location:** New method `computeArgumentStrength()` called at end of `loadAnalytics()`

For each analytics section, compute 0-100 strength score based on deviation from expected norms:

| Metric | Expected | Scoring |
|--------|----------|---------|
| Same-day % | 50-70% | >90% = 100, >80% = 75, >70% = 50 |
| Rejection rate | 5-10% | 0% = 100, <2% = 75, <5% = 50 |
| HHI | <2500 | >5000 = 100, >3500 = 75, >2500 = 50 |
| Processing days | 2-5 days | <0.5 = 100, <1 = 75, <2 = 50 |
| Proportionality | N/A | warrants_pct > 50% = 100 |
| Document completeness | >90% | <50% = 100, <70% = 75, <90% = 50 |
| Workload impossibility | 0 days | any = 100 per occurrence |
| Repeat targeting | 0 repeats | any = 75 per occurrence |

Produces `$analytics['argument_scores']` array and overall composite score.

---

### Task 4B: Scoring Frontend

**File:** `resources/views/livewire/partials/epredmet-analytics.blade.php`
**Location:** Right after "Defense Argument Analytics" header, before alerts

Display: Overall defense strength meter (0-100 gauge), individual scores per argument type as horizontal bars, ranked from strongest to weakest. Color coding: 75-100 STRONG (green), 50-74 MODERATE (yellow), <50 WEAK (gray).

---

## Execution Plan

**Batch 1** (Tasks 1A-1D): Monthly trend + anomaly detection — PRIORITY
**Batch 2** (Tasks 2A-2B): Per-case argument card
**Batch 3** (Tasks 3A-3B): Court comparison mode
**Batch 4** (Tasks 4A-4B): Argument strength scoring

Each batch: implement backend → update chartData → add frontend → commit.
