<?php

namespace App\Console\Commands;

use App\Models\Court;
use App\Models\CourtCase;
use App\Models\CaseDocument;
use App\Models\CourtCaseDocument;
use App\Models\Judge;
use App\Models\CaseStatistic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ShowStatistics2 extends Command
{
    protected $signature = 'epredmet:stats2
                            {--court= : Court ID to analyze}
                            {--year= : Year to analyze (default: current)}
                            {--national : Show national statistics}
                            {--judges : Show judge breakdown}
                            {--police : Show police unit breakdown}
                            {--anomalies : Detect anomalies}';

    protected $description = 'Show statistics for Pp Prz cases (search warrants analysis)';

    public function handle(): int
    {
        $year = (int) ($this->option('year') ?? 2025);
        $courtId = $this->option('court');

        if ($this->option('national')) {
            return $this->showNationalStats($year);
        }

        if (!$courtId) {
            $this->error('Please specify --court=<id> or use --national');
            return self::FAILURE;
        }

        $court = Court::where('external_id', $courtId)->first();
        if (!$court) {
            $this->error("Court ID {$courtId} not found.");
            return self::FAILURE;
        }

        $this->showCourtStats($court, $year);

        if ($this->option('judges')) {
            $this->showJudgeBreakdown($court, $year);
        }

        if ($this->option('police')) {
            $this->showPoliceBreakdown($court, $year);
        }

        if ($this->option('anomalies')) {
            $this->detectAnomalies($court, $year);
        }

        return self::SUCCESS;
    }

    protected function showCourtStats(Court $court, int $year): void
    {
        $this->info("=== {$court->name} - {$year} ===");
        $this->newLine();

        $totalCases = CourtCase::byCourt($court->id)->byYear($year)->count();
        $searchWarrants = CourtCase::byCourt($court->id)->byYear($year)->searchWarrants()->count();

        // Some datasets might not backfill is_same_day, but do have processing_days.
        // Treat a case as same-day if processing_days === 0, falling back to is_same_day.
        $sameDayDecisions = CourtCase::byCourt($court->id)
            ->byYear($year)
            ->searchWarrants()
            ->where(function ($q) {
                $q->where('processing_days', 0)
                    ->orWhere('is_same_day', true);
            })
            ->count();

        $weekendDecisions = CourtCase::byCourt($court->id)->byYear($year)->searchWarrants()->weekend()->count();
        $avgProcessing = CourtCase::byCourt($court->id)->byYear($year)->searchWarrants()->avg('processing_days');

        $warrantShare = $totalCases > 0
            ? (round(100 * $searchWarrants / $totalCases) . '%')
            : 'N/A';

        $sameDayShare = $searchWarrants > 0
            ? (round(100 * $sameDayDecisions / $searchWarrants) . '%')
            : 'N/A';

        $weekendShare = $searchWarrants > 0
            ? (round(100 * $weekendDecisions / $searchWarrants) . '%')
            : 'N/A';

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Pp Prz Cases', $totalCases],
                ['Search Warrants', $searchWarrants . ' (' . $warrantShare . ')'],
                ['Same-Day Decisions', $sameDayDecisions . ' (' . $sameDayShare . ')'],
                ['Weekend Decisions', $weekendDecisions . ' (' . $weekendShare . ')'],
                ['Avg Processing Days', round($avgProcessing ?? 0, 2)],
                ['Weekly Rate', round($searchWarrants / 52, 1) . ' warrants/week'],
            ]
        );
    }

    protected function showJudgeBreakdown(Court $court, int $year): void
    {
        $this->newLine();
        $this->info("=== Judge Breakdown ===");

        $judges = CourtCase::byCourt($court->id)
            ->byYear($year)
            ->searchWarrants()
            ->select(
                'judge_id',
                'judge_name',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN processing_days = 0 OR is_same_day THEN 1 ELSE 0 END) as same_day')
            )
            ->groupBy('judge_id', 'judge_name')
            ->orderByDesc('total')
            ->get();

        $totalWarrants = (int) $judges->sum('total');

        $rows = $judges->map(function ($j) use ($totalWarrants) {
            $concentration = $totalWarrants > 0
                ? (round(100 * $j->total / $totalWarrants, 1) . '%')
                : 'N/A';

            $sameDayRate = $j->total > 0
                ? (round(100 * $j->same_day / $j->total, 0) . '%')
                : 'N/A';

            $flag = '';
            if ($totalWarrants > 0) {
                $conc = 100 * $j->total / $totalWarrants;
                if ($conc > 50) $flag = '⚠️ HIGH';
                if ($conc > 75) $flag = '🚨 EXTREME';
            }

            return [
                $j->judge_name ?? 'Unknown',
                $j->total,
                $concentration,
                $sameDayRate,
                $flag,
            ];
        });

        $this->table(
            ['Judge', 'Warrants', 'Concentration', 'Same-Day %', 'Flag'],
            $rows
        );
    }

    protected function showPoliceBreakdown(Court $court, int $year): void
    {
        $this->newLine();
        $this->info("=== Police Unit Breakdown ===");

        $units = CourtCaseDocument::whereHas('courtCase', function ($q) use ($court, $year) {
                $q->where('court_id', $court->id)
                    ->where('year', $year)
                    ->where('is_search_warrant', true);
            })
            ->where('is_request', true)
            ->select('police_unit_type', DB::raw('COUNT(*) as total'))
            ->groupBy('police_unit_type')
            ->orderByDesc('total')
            ->get();

        $this->table(
            ['Police Unit Type', 'Requests'],
            $units->map(fn($u) => [$u->police_unit_type ?? 'Unknown', $u->total])
        );

        // SOKO detail
        $sokoCount = $units->where('police_unit_type', 'SOKO')->sum('total');
        if ($sokoCount > 0) {
            $this->warn("⚠️ SOKO submitted {$sokoCount} requests - should use criminal procedure!");
        }
    }

    protected function detectAnomalies(Court $court, int $year): void
    {
        $this->newLine();
        $this->info("=== Anomaly Detection ===");

        $anomalies = [];

        // 1. Judge concentration
        $topJudge = CourtCase::byCourt($court->id)
            ->byYear($year)
            ->searchWarrants()
            ->select('judge_name', DB::raw('COUNT(*) as total'))
            ->groupBy('judge_name')
            ->orderByDesc('total')
            ->first();

        $totalWarrants = CourtCase::byCourt($court->id)->byYear($year)->searchWarrants()->count();

        if ($topJudge && $totalWarrants > 0) {
            $concentration = round(100 * $topJudge->total / $totalWarrants, 1);
            if ($concentration > 50) {
                $anomalies[] = [
                    'Type' => 'Judge Concentration',
                    'Value' => "{$concentration}%",
                    'Details' => "{$topJudge->judge_name} handles {$topJudge->total}/{$totalWarrants} warrants",
                    'Severity' => $concentration > 75 ? '🚨 CRITICAL' : '⚠️ HIGH',
                ];
            }
        }

        // 2. Same-day rate (same definition as other stats: processing_days = 0 OR is_same_day)
        $sameDayCount = CourtCase::byCourt($court->id)
            ->byYear($year)
            ->searchWarrants()
            ->where(function ($q) {
                $q->where('processing_days', 0)
                    ->orWhere('is_same_day', true);
            })
            ->count();

        if ($totalWarrants > 0) {
            $rate = round(100 * $sameDayCount / $totalWarrants);
            if ($rate > 80) {
                $anomalies[] = [
                    'Type' => 'Same-Day Rate',
                    'Value' => "{$rate}%",
                    'Details' => "{$sameDayCount}/{$totalWarrants} warrants issued same day",
                    'Severity' => $rate > 95 ? '🚨 CRITICAL' : '⚠️ HIGH',
                ];
            }
        }

        // 3. Weekend decisions
        $weekendRate = CourtCase::byCourt($court->id)
            ->byYear($year)
            ->searchWarrants()
            ->where('is_weekend', true)
            ->count();

        if ($totalWarrants > 0 && $weekendRate > 0) {
            $rate = round(100 * $weekendRate / $totalWarrants);
            if ($rate > 15) {
                $anomalies[] = [
                    'Type' => 'Weekend Decisions',
                    'Value' => "{$rate}%",
                    'Details' => "{$weekendRate} warrants issued on weekends",
                    'Severity' => '⚠️ SUSPICIOUS',
                ];
            }
        }

        // 4. SOKO using misdemeanor path
        $sokoCount = CourtCaseDocument::whereHas('courtCase', function ($q) use ($court, $year) {
                $q->where('court_id', $court->id)
                    ->where('year', $year)
                    ->where('is_search_warrant', true);
            })
            ->where('police_unit_type', 'SOKO')
            ->where('is_request', true)
            ->count();

        if ($sokoCount > 0) {
            $anomalies[] = [
                'Type' => 'SOKO via Misdemeanor',
                'Value' => "{$sokoCount} cases",
                'Details' => 'Organized crime unit using misdemeanor procedure',
                'Severity' => '🚨 CRITICAL',
            ];
        }

        if (empty($anomalies)) {
            $this->info('✅ No significant anomalies detected.');
        } else {
            $this->table(
                ['Anomaly Type', 'Value', 'Details', 'Severity'],
                $anomalies
            );
        }
    }

    protected function showNationalStats(int $year): int
    {
        $this->info("=== National Statistics - {$year} ===");
        $this->newLine();

        $courts = CourtCase::where('year', $year)
            ->where('is_search_warrant', true)
            ->select(
                'court_id',
                DB::raw('COUNT(*) as warrants'),
                DB::raw('SUM(CASE WHEN processing_days = 0 OR is_same_day THEN 1 ELSE 0 END) as same_day'),
                DB::raw('AVG(processing_days) as avg_days')
            )
            ->groupBy('court_id')
            ->with('court')
            ->orderByDesc('warrants')
            ->get();

        $total = $courts->sum('warrants');

        $rows = $courts->map(function ($c) use ($total) {
            $shareOfTotal = $total > 0
                ? (round(100 * $c->warrants / $total, 1) . '%')
                : 'N/A';

            $sameDayPct = $c->warrants > 0
                ? (round(100 * $c->same_day / $c->warrants) . '%')
                : 'N/A';

            return [
                $c->court?->short_name ?? 'Unknown',
                $c->warrants,
                $shareOfTotal,
                $sameDayPct,
                round($c->avg_days ?? 0, 1),
            ];
        })->take(20);

        $this->table(
            ['Court', 'Warrants', '% of Total', 'Same-Day %', 'Avg Days'],
            $rows
        );

        $this->newLine();
        $this->info("Total search warrants in {$year}: {$total}");
        $this->info("Weekly average: " . round($total / 52, 1));
        $this->info("Daily average: " . round($total / 365, 2));

        return Command::SUCCESS;
    }
}
