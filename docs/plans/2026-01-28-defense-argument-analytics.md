# Plan: Defense Argument Analytics for E-predmet Widget

**Date:** 2026-01-28
**Branch:** `claude/improve-epredmet-widget-sJl8d`
**Scope:** Add 6 new defense-strengthening analytics sections to EpredmetWidget

---

## Overview

Add powerful defense argument analytics to the E-predmet widget Analytics tab. Each section produces data that can directly support defense arguments in court, targeting systemic patterns of insufficient judicial oversight in search warrant issuance.

---

## Architecture

All changes go into two files:
- **Backend:** `app/Http/Livewire/EpredmetWidget.php` — new analytics queries inside `loadAnalytics()`
- **Frontend:** `resources/views/livewire/partials/epredmet-analytics.blade.php` — new tables, alerts, and D3.js charts

New analytics data keys added to `$this->analytics[]`:
- `judge_profiles` — Judge rubber-stamp profiles
- `workload_impossibility` — Judge daily workload analysis
- `repeat_targets` — Repeat targeting of same parties
- `police_approval_rate` — Per-unit approval rates
- `proportionality` — Warrant severity vs. sanction mismatch
- `document_completeness` — Missing document detection

New chart data keys added to `$this->chartData[]`:
- `judge_profile_bar` — Judge approval rates bar chart
- `police_approval_bar` — Police unit approval rate bar chart

---

## Tasks

### Task 1: Judge Rubber-Stamp Profiles (Backend)

**File:** `app/Http/Livewire/EpredmetWidget.php`
**Location:** Inside `loadAnalytics()`, after section 10 (processing_distribution), before `buildChartData()`

**What:** Query per-judge aggregate stats combining warrants issued, same-day %, zero-day %, rejection count, average processing days. Flag judges with >50 warrants + >90% same-day + 0 rejections as "RUBBER STAMP".

**Code to add in `loadAnalytics()` (after section 10, before `$this->chartData = ...`):**

```php
// 11. Judge Rubber-Stamp Profiles
$judgeProfiles = $this->caseQuery($year)
    ->where('is_search_warrant', true)
    ->whereNotNull('judge_id')
    ->select(
        'judge_id',
        'judge_name',
        'court_id',
        DB::raw('COUNT(*) as total_warrants'),
        DB::raw('SUM(CASE WHEN processing_days = 0 OR is_same_day THEN 1 ELSE 0 END) as same_day'),
        DB::raw('SUM(CASE WHEN processing_days = 0 THEN 1 ELSE 0 END) as zero_day'),
        DB::raw("SUM(CASE WHEN decision_type LIKE '%odbij%' OR decision_type LIKE '%odbac%' THEN 1 ELSE 0 END) as rejections"),
        DB::raw('AVG(processing_days) as avg_days'),
        DB::raw('MIN(CASE WHEN is_search_warrant THEN date_decision END) as first_warrant'),
        DB::raw('MAX(CASE WHEN is_search_warrant THEN date_decision END) as last_warrant')
    )
    ->groupBy('judge_id', 'judge_name', 'court_id')
    ->havingRaw('COUNT(*) >= ?', [10])
    ->orderByDesc('total_warrants')
    ->limit(15)
    ->get()
    ->map(function ($row) {
        $court = Court::find($row->court_id);
        $sameDayPct = $row->total_warrants > 0 ? round(100 * $row->same_day / $row->total_warrants, 0) : 0;
        $zeroDayPct = $row->total_warrants > 0 ? round(100 * $row->zero_day / $row->total_warrants, 0) : 0;
        $isRubberStamp = $row->total_warrants >= 50 && $sameDayPct >= 90 && (int) $row->rejections === 0;

        return [
            'judge' => $row->judge_name ?? 'N/A',
            'court' => $court?->short_name ?? 'N/A',
            'total_warrants' => (int) $row->total_warrants,
            'same_day_pct' => $sameDayPct,
            'zero_day_pct' => $zeroDayPct,
            'rejections' => (int) $row->rejections,
            'avg_days' => round($row->avg_days ?? 0, 2),
            'first_warrant' => $row->first_warrant,
            'last_warrant' => $row->last_warrant,
            'is_rubber_stamp' => $isRubberStamp,
        ];
    })->toArray();

$analytics['judge_profiles'] = $judgeProfiles;
```

---

### Task 2: Judge Workload Impossibility (Backend)

**File:** `app/Http/Livewire/EpredmetWidget.php`
**Location:** Inside `loadAnalytics()`, after Task 1's code

**What:** Find days where a judge issued an impossible number of warrants (5+ in one day). If a judge issues 10+ warrants in a single day, individual assessment is physically impossible. ECHR Article 8 requires individualized judicial review.

```php
// 12. Judge Workload Impossibility
$workloadData = $this->caseQuery($year)
    ->where('is_search_warrant', true)
    ->whereNotNull('judge_id')
    ->whereNotNull('date_decision')
    ->select(
        'judge_id',
        'judge_name',
        'court_id',
        DB::raw('DATE(date_decision) as decision_date'),
        DB::raw('COUNT(*) as warrants_on_day')
    )
    ->groupBy('judge_id', 'judge_name', 'court_id', DB::raw('DATE(date_decision)'))
    ->havingRaw('COUNT(*) >= ?', [5])
    ->orderByDesc('warrants_on_day')
    ->limit(20)
    ->get()
    ->map(function ($row) {
        $court = Court::find($row->court_id);
        return [
            'judge' => $row->judge_name ?? 'N/A',
            'court' => $court?->short_name ?? 'N/A',
            'date' => $row->decision_date,
            'warrants_on_day' => (int) $row->warrants_on_day,
            'is_impossible' => $row->warrants_on_day >= 10,
        ];
    })->toArray();

$analytics['workload_impossibility'] = $workloadData;
```

---

### Task 3: Repeat Targeting Analysis (Backend)

**File:** `app/Http/Livewire/EpredmetWidget.php`
**Location:** Inside `loadAnalytics()`, after Task 2's code

**What:** Detect defendants/parties appearing in multiple search warrant cases. Repeated targeting of the same individual suggests harassment or disproportionate state action.

```php
// 13. Repeat Targeting
$repeatTargets = CaseParty::whereHas('courtCase', function ($q) use ($year) {
        $q->where('is_search_warrant', true);
        if ($year > 0) {
            $q->where('year', $year);
        }
    })
    ->where('is_defendant', true)
    ->whereNotNull('name')
    ->where('name', '!=', '')
    ->select('name', DB::raw('COUNT(DISTINCT court_case_id) as case_count'))
    ->groupBy('name')
    ->havingRaw('COUNT(DISTINCT court_case_id) >= ?', [2])
    ->orderByDesc('case_count')
    ->limit(15)
    ->get()
    ->map(fn ($row) => [
        'name' => $row->name,
        'case_count' => (int) $row->case_count,
        'is_excessive' => $row->case_count >= 3,
    ])->toArray();

$analytics['repeat_targets'] = $repeatTargets;
```

---

### Task 4: Police Unit Approval Rate (Backend)

**File:** `app/Http/Livewire/EpredmetWidget.php`
**Location:** Inside `loadAnalytics()`, after Task 3's code

**What:** Cross-reference police unit request submissions against approval/rejection outcomes. If SOKO has 100% approval while other units have lower rates, it suggests preferential treatment or lack of scrutiny for organized crime unit requests.

```php
// 14. Police Unit Approval Rate
$policeApproval = DB::table('court_case_documents as d')
    ->join('court_cases as c', 'c.id', '=', 'd.court_case_id')
    ->where('c.is_search_warrant', true)
    ->where('d.is_request', true)
    ->whereNotNull('d.police_unit_type')
    ->when($year > 0, fn ($q) => $q->where('c.year', $year))
    ->select(
        'd.police_unit_type',
        DB::raw('COUNT(DISTINCT c.id) as total_requests'),
        DB::raw("SUM(CASE WHEN c.decision_type IN ('Naredba', 'Nalog - pretrage', 'Nalog-pretrage') THEN 1 ELSE 0 END) as approved"),
        DB::raw("SUM(CASE WHEN c.decision_type LIKE '%odbij%' OR c.decision_type LIKE '%odbac%' THEN 1 ELSE 0 END) as rejected"),
        DB::raw('AVG(c.processing_days) as avg_processing'),
        DB::raw('SUM(CASE WHEN c.processing_days = 0 OR c.is_same_day THEN 1 ELSE 0 END) as same_day')
    )
    ->groupBy('d.police_unit_type')
    ->havingRaw('COUNT(DISTINCT c.id) >= ?', [5])
    ->orderByDesc('total_requests')
    ->get()
    ->map(fn ($row) => [
        'unit' => $row->police_unit_type,
        'total_requests' => (int) $row->total_requests,
        'approved' => (int) $row->approved,
        'rejected' => (int) $row->rejected,
        'approval_pct' => $row->total_requests > 0 ? round(100 * $row->approved / $row->total_requests, 1) : 0,
        'same_day_pct' => $row->total_requests > 0 ? round(100 * $row->same_day / $row->total_requests, 0) : 0,
        'avg_processing' => round($row->avg_processing ?? 0, 1),
        'is_100pct' => (int) $row->rejected === 0,
        'is_soko' => $row->police_unit_type === 'SOKO',
    ])->toArray();

$analytics['police_approval_rate'] = $policeApproval;
```

---

### Task 5: Proportionality Index (Backend)

**File:** `app/Http/Livewire/EpredmetWidget.php`
**Location:** Inside `loadAnalytics()`, after Task 4's code

**What:** The proportionality argument: home search warrants (maximum invasiveness) are issued under Pp Prz (misdemeanor register), where maximum sanctions are typically fines. This is a direct ECHR Article 8 proportionality violation - using the most invasive investigative measure for the least severe offenses.

```php
// 15. Proportionality Index
$totalPpPrz = $this->caseQuery($year)->where('register', 'Pp Prz')->count();
$warrantsInPpPrz = $this->caseQuery($year)->where('register', 'Pp Prz')
    ->where('is_search_warrant', true)->count();
$warrantsPct = $totalPpPrz > 0 ? round(100 * $warrantsInPpPrz / $totalPpPrz, 1) : 0;

// Cases filed on weekends or with same-day processing in misdemeanor
$urgentMisdemeanor = $this->caseQuery($year)->where('register', 'Pp Prz')
    ->where('is_search_warrant', true)
    ->where(function ($q) {
        $q->where('is_weekend', true)->orWhere('is_same_day', true);
    })->count();

$analytics['proportionality'] = [
    'total_misdemeanor' => $totalPpPrz,
    'warrants_in_misdemeanor' => $warrantsInPpPrz,
    'warrants_pct' => $warrantsPct,
    'urgent_count' => $urgentMisdemeanor,
    'urgent_pct' => $warrantsInPpPrz > 0 ? round(100 * $urgentMisdemeanor / $warrantsInPpPrz, 1) : 0,
];
```

---

### Task 6: Document Completeness Score (Backend)

**File:** `app/Http/Livewire/EpredmetWidget.php`
**Location:** Inside `loadAnalytics()`, after Task 5's code

**What:** For search warrant cases, check if expected documents exist: a "Zahtjev" (request) should precede a "Naredba" (order). Cases with orders but no requests indicate procedural violations. Also detect cases with no documents at all.

```php
// 16. Document Completeness
$warrantCaseIds = $this->caseQuery($year)
    ->where('is_search_warrant', true)
    ->pluck('id');

$totalWarrantCases = $warrantCaseIds->count();

$casesWithRequest = CourtCaseDocument::whereIn('court_case_id', $warrantCaseIds)
    ->where('is_request', true)
    ->distinct('court_case_id')
    ->count('court_case_id');

$casesWithDecision = CourtCaseDocument::whereIn('court_case_id', $warrantCaseIds)
    ->where('is_decision', true)
    ->distinct('court_case_id')
    ->count('court_case_id');

$casesWithNoDocs = $totalWarrantCases - CourtCaseDocument::whereIn('court_case_id', $warrantCaseIds)
    ->distinct('court_case_id')
    ->count('court_case_id');

$decisionNoRequest = CourtCaseDocument::whereIn('court_case_id', $warrantCaseIds)
    ->where('is_decision', true)
    ->whereNotIn('court_case_id', function ($q) use ($warrantCaseIds) {
        $q->select('court_case_id')
            ->from('court_case_documents')
            ->whereIn('court_case_id', $warrantCaseIds)
            ->where('is_request', true);
    })
    ->distinct('court_case_id')
    ->count('court_case_id');

$analytics['document_completeness'] = [
    'total_warrant_cases' => $totalWarrantCases,
    'with_request' => $casesWithRequest,
    'with_decision' => $casesWithDecision,
    'no_documents' => $casesWithNoDocs,
    'decision_no_request' => $decisionNoRequest,
    'request_pct' => $totalWarrantCases > 0 ? round(100 * $casesWithRequest / $totalWarrantCases, 1) : 0,
    'decision_pct' => $totalWarrantCases > 0 ? round(100 * $casesWithDecision / $totalWarrantCases, 1) : 0,
    'completeness_pct' => $totalWarrantCases > 0
        ? round(100 * min($casesWithRequest, $casesWithDecision) / $totalWarrantCases, 1) : 0,
];
```

---

### Task 7: Update `buildChartData()` (Backend)

**File:** `app/Http/Livewire/EpredmetWidget.php`
**Location:** Inside `buildChartData()` method, add after existing chart 6 (hhi_bar)

```php
// Chart 7: Judge profile bar (top judges by warrants with same-day% color coding)
$profiles = $analytics['judge_profiles'] ?? [];
$charts['judge_profile_bar'] = [
    'labels' => array_column($profiles, 'judge'),
    'warrants' => array_column($profiles, 'total_warrants'),
    'same_day_pct' => array_column($profiles, 'same_day_pct'),
    'is_rubber_stamp' => array_column($profiles, 'is_rubber_stamp'),
];

// Chart 8: Police unit approval rate
$policeData = $analytics['police_approval_rate'] ?? [];
$charts['police_approval_bar'] = [
    'labels' => array_column($policeData, 'unit'),
    'approval_pct' => array_column($policeData, 'approval_pct'),
    'same_day_pct' => array_column($policeData, 'same_day_pct'),
];
```

---

### Task 8: Frontend — New Analytics Sections (Blade)

**File:** `resources/views/livewire/partials/epredmet-analytics.blade.php`
**Location:** After existing section "10. Processing distribution table" (before the closing `@endif`/`</div>` that wraps `wire:loading.class`)

Add the following sections in order:

1. **Defense Arguments Header** — Visual separator with shield icon
2. **Proportionality Alert** — Red alert box about misdemeanor + home search mismatch
3. **Judge Rubber-Stamp Profiles Table** — With RUBBER STAMP badges, chart container
4. **Judge Workload Impossibility Table** — With IMPOSSIBLE badges
5. **Repeat Targeting Table** — With EXCESSIVE badges
6. **Police Unit Approval Rate Table** — With 100% APPROVED badges, chart container
7. **Document Completeness Summary** — Cards with counts + completeness bar

---

### Task 9: Frontend — New D3.js Chart Renderers (JavaScript)

**File:** `resources/views/livewire/partials/epredmet-analytics.blade.php`
**Location:** Inside the `<script>` block, in `epredmetCharts()` object

Add to `renderAllCharts()`:
```javascript
this.renderJudgeProfileChart(data.judge_profile_bar);
this.renderPoliceApprovalChart(data.police_approval_bar);
```

Add two new methods:
- `renderJudgeProfileChart(data)` — Horizontal bar chart, bars colored red for rubber-stamp judges
- `renderPoliceApprovalChart(data)` — Grouped bar chart showing approval% and same-day% per unit

---

### Task 10: Frontend — New Alert Badges

**File:** `resources/views/livewire/partials/epredmet-analytics.blade.php`
**Location:** In the Alerts section (after existing alerts)

Add alerts for:
- Rubber-stamp judges detected (count of flagged judges)
- Workload impossibility detected (days with 10+ warrants)
- Document completeness issues (decision without request count)

---

## Execution Order

Tasks 1-6 are independent backend queries — can be implemented in parallel.
Task 7 depends on Tasks 1, 4.
Tasks 8-10 depend on all backend tasks.

**Batch 1 (parallel):** Tasks 1-6 (backend analytics)
**Batch 2 (parallel):** Tasks 7 + 8 + 9 + 10 (chart data + frontend)
**Batch 3:** Commit and push

---

## Defense Argument Summary

| Analytics Section | Legal Argument |
|---|---|
| Judge Rubber-Stamp Profile | Art. 8 ECHR: No individualized judicial assessment |
| Workload Impossibility | Art. 8 ECHR: Physical impossibility of individual review |
| Repeat Targeting | Art. 8 ECHR: Disproportionate/harassment targeting |
| Police Approval Rate | Art. 6 ECHR: Unequal treatment / preferential access |
| Proportionality Index | Art. 8 ECHR: Maximum invasiveness for minimum offence |
| Document Completeness | Procedural violation: Order without formal request |
