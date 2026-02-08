<?php

namespace App\Console\Commands;

use App\Models\Court;
use App\Models\CourtCase;
use App\Models\CaseDocument;
use App\Models\Judge;
use App\Models\CaseStatistic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ShowStatistics extends Command
{
    protected $signature = 'epredmet:stats
                            {--court= : Court ID to analyze}
                            {--year= : Year to analyze (default: current)}
                            {--national : Show national statistics}
                            {--judges : Show judge breakdown}
                            {--police : Show police unit breakdown}
                            {--anomalies : Detect anomalies}';

    protected $description = 'Show statistics for Pp Prz cases (search warrants analysis)';

    public function handle(): int
    {
        $year = (int) ($this->option('year') ?? date('Y'));
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
        $sameDayDecisions = CourtCase::byCourt($court->id)->byYear($year)->searchWarrants()->sameDay()->count();
        $weekendDecisions = CourtCase::byCourt($court->id)->byYear($year)->searchWarrants()->weekend()->count();
        $avgProcessing = CourtCase::byCourt($court->id)->byYear($year)->searchWarrants()->avg('processing_days');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Pp Prz Cases', $totalCases],
                ['Search Warrants', $searchWarrants . ' (' . round(100 * $searchWarrants / max($totalCases, 1)) . '%)'],
                ['Same-Day Decisions', $sameDayDecisions . ' (' . round(100 * $sameDayDecisions / max($searchWarrants, 1)) . '%)'],
                ['Weekend Decisions', $weekendDecisions . ' (' . round(100 * $weekendDecisions / max($searchWarrants, 1)) . '%)'],
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
            ->select('judge_id', 'judge_name', DB::raw('COUNT(*) as total'), DB::raw('SUM(CASE WHEN is_same_day THEN 1 ELSE 0 END) as same_day'))
            ->groupBy('judge_id', 'judge_name')
            ->orderByDesc('total')
            ->get();

        $totalWarrants = $judges->sum('total');

        $rows = $judges->map(function ($j) use ($totalWarrants) {
            $concentration = round(100 * $j->total / max($totalWarrants, 1), 1);
            $sameDayRate = round(100 * $j->same_day / max($j->total, 1), 0);

            $flag = '';
            if ($concentration > 50) $flag = '⚠️ HIGH';
            if ($concentration > 75) $flag = '🚨 EXTREME';

            return [
                $j->judge_name ?? 'Unknown',
                $j->total,
                $concentration . '%',
                $sameDayRate . '%',
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

        $units = CaseDocument::whereHas('courtCase', function ($q) use ($court, $year) {
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

        // 2. Same-day rate
        $sameDayRate = CourtCase::byCourt($court->id)
            ->byYear($year)
            ->searchWarrants()
            ->where('is_same_day', true)
            ->count();

        if ($totalWarrants > 0) {
            $rate = round(100 * $sameDayRate / $totalWarrants);
            if ($rate > 80) {
                $anomalies[] = [
                    'Type' => 'Same-Day Rate',
                    'Value' => "{$rate}%",
                    'Details' => "{$sameDayRate}/{$totalWarrants} warrants issued same day",
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
        $sokoCount = CaseDocument::whereHas('courtCase', function ($q) use ($court, $year) {
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
                DB::raw('SUM(CASE WHEN is_same_day THEN 1 ELSE 0 END) as same_day'),
                DB::raw('AVG(processing_days) as avg_days')
            )
            ->groupBy('court_id')
            ->with('court')
            ->orderByDesc('warrants')
            ->get();

        $total = $courts->sum('warrants');

        $rows = $courts->map(function ($c) use ($total) {
            return [
                $c->court?->short_name ?? 'Unknown',
                $c->warrants,
                round(100 * $c->warrants / max($total, 1), 1) . '%',
                round(100 * $c->same_day / max($c->warrants, 1)) . '%',
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
