<?php

namespace App\Console\Commands;

use App\Models\Court;
use App\Models\CourtCase;
use App\Models\CaseDocument;
use App\Models\CourtCaseDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Dashboard extends Command
{
    protected $signature = 'epredmet:dashboard
                            {--year= : Year to analyze (default: current)}
                            {--court= : Specific court ID (optional)}
                            {--export= : Export to file (csv/json)}';

    protected $description = 'Show TOP 10 statistics dashboard for detecting misdemeanor search warrant abuse';

    protected int $year;
    protected ?int $courtId = null;
    protected ?string $courtExternalId = null;
    protected ?string $courtName = null;
    protected array $stats = [];

    public function handle(): int
    {
        $this->year = (int) ($this->option('year') ?? 2025);

        // Court option uses external_id (official ID), not internal id
        if ($courtOption = $this->option('court')) {
            $court = Court::where('external_id', $courtOption)->first();
            if (!$court) {
                $this->error("Sud s external_id '{$courtOption}' nije pronađen!");
                return self::FAILURE;
            }
            $this->courtId = $court->id; // Store internal id for queries
            $this->courtExternalId = $court->external_id;
            $this->courtName = $court->name;
        }

        $this->printHeader();

        // Collect all statistics
        $this->stat1_NationalVolume();
        $this->stat2_RubberStampIndex();
        $this->stat3_JudgeConcentration();
        $this->stat4_SokoMisdemeanorPath();
        $this->stat5_RegionalDisproportion();
        $this->stat6_WeekendWarrants();
        $this->stat7_ProcessingTime();
        $this->stat8_RejectionRate();
        $this->stat9_YearlyTrend();
        $this->stat10_ProportionalityIndex();

        $this->printSummary();

        // Export if requested
        if ($export = $this->option('export')) {
            $this->exportStats($export);
        }

        return self::SUCCESS;
    }

    protected function printHeader(): void
    {
        $scope = $this->courtId
            ? "{$this->courtName} ({$this->courtExternalId})"
            : 'HRVATSKA (svi sudovi)';

        $this->newLine();
        $this->line('╔══════════════════════════════════════════════════════════════════════════════╗');
        $this->line('║        TOP 10 STATISTIKA - ANALIZA ZLOUPORABE PREKRŠAJNIH PRETRESA          ║');
        $this->line('╠══════════════════════════════════════════════════════════════════════════════╣');
        $this->line(sprintf('║  Godina: %-4d                                                                ║', $this->year));
        $this->line(sprintf('║  Opseg:  %-67s ║', $scope));
        $this->line('╚══════════════════════════════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    /**
     * STAT 1: National Volume - How many home searches per year?
     */
    protected function stat1_NationalVolume(): void
    {
        $this->info('━━━ 1. NACIONALNI VOLUMEN ━━━');

        $base = CourtCase::where('year', $this->year)
            ->when($this->courtId, fn($q) => $q->where('court_id', $this->courtId));

        $warrantsQuery = (clone $base)->where('is_search_warrant', true);

        $totalCases = (clone $base)->count();
        $totalWarrants = (clone $warrantsQuery)->count();

        $daily = round($totalWarrants / 365, 1);
        $weekly = round($totalWarrants / 52, 1);

        $population = $this->courtId
            ? (Court::find($this->courtId)?->population ?? 0)
            : 3870000;
        $per100k = $population > 0 ? round(100000 * $totalWarrants / $population, 1) : 0;

        $this->table(
            ['Metrika', 'Svi predmeti', 'Pretresi', 'Komentar'],
            [
                ['Ukupno', number_format($totalCases), number_format($totalWarrants), $this->year],
                ['Dnevno (pretresi)', '-', $daily, 'pretresa/dan'],
                ['Tjedno (pretresi)', '-', $weekly, 'pretresa/tjedan'],
                ['Per 100k stanovnika (pretresi)', '-', $per100k, $population > 0 ? "populacija: " . number_format($population) : 'N/A'],
            ]
        );

        $this->stats['national_volume'] = [
            'total_cases' => $totalCases,
            'total_warrants' => $totalWarrants,
            'daily' => $daily,
            'weekly' => $weekly,
            'per100k' => $per100k,
        ];

        if ($totalWarrants > 1000 && !$this->courtId) {
            $this->warn("⚠️  {$totalWarrants} pretresa doma godišnje za PREKRŠAJE koji se kažnjavaju globom!");
        }

        $this->newLine();
    }

    /**
     * STAT 2: Rubber Stamp Index - Same-day approval rate
     */
    protected function stat2_RubberStampIndex(): void
    {
        $this->info('━━━ 2. "RUBBER STAMP" INDEX (odobrenje istog dana) ━━━');

        $results = CourtCase::where('year', $this->year)
            ->when($this->courtId, fn($q) => $q->where('court_id', $this->courtId))
            ->select(
                'court_id',
                DB::raw('COUNT(*) AS total_cases'),
                DB::raw('SUM(CASE WHEN is_search_warrant THEN 1 ELSE 0 END) AS total_warrants'),
                DB::raw('SUM(CASE WHEN is_search_warrant AND (processing_days = 0 OR is_same_day) THEN 1 ELSE 0 END) as same_day_warrants')
            )
            ->groupBy('court_id')
            ->havingRaw('SUM(CASE WHEN is_search_warrant THEN 1 ELSE 0 END) >= ?', [5])
            ->get()
            ->map(function ($row) {
                $court = Court::find($row->court_id);
                $pct = $row->total_warrants > 0 ? round(100 * $row->same_day_warrants / $row->total_warrants, 0) : 0;
                return [
                    'court' => $court?->short_name ?? 'N/A',
                    'total_cases' => (int) $row->total_cases,
                    'total_warrants' => (int) $row->total_warrants,
                    'same_day_warrants' => (int) $row->same_day_warrants,
                    'pct' => $pct,
                    'flag' => $pct >= 95 ? '🚨 KRITIČNO' : ($pct >= 80 ? '⚠️ VISOKO' : ''),
                ];
            })->sortByDesc('pct')->take(10);

        $this->table(
            ['Sud', 'Svi predmeti', 'Pretresa', 'Isti dan', 'Isti dan %', 'Status'],
            $results->map(fn($r) => [$r['court'], $r['total_cases'], $r['total_warrants'], $r['same_day_warrants'], $r['pct'] . '%', $r['flag']])
        );

        $avgPct = $results->avg('pct');
        $this->stats['rubber_stamp'] = [
            'avg_pct' => round($avgPct, 1),
            'courts_above_90' => $results->where('pct', '>=', 90)->count(),
        ];

        if ($avgPct > 80) {
            $this->warn("⚠️  Prosječno {$avgPct}% naredbi izdano istog dana = minimalna sudska kontrola!");
        }

        $this->newLine();
    }

    /**
     * STAT 3: Judge Concentration (HHI Index)
     */
    protected function stat3_JudgeConcentration(): void
    {
        $this->info('━━━ 3. KONCENTRACIJA SUDACA (Herfindahl-Hirschman Index) ━━━');

        // Get judge distribution per court
        $judgeData = CourtCase::where('year', $this->year)
            ->whereNotNull('judge_id')
            ->when($this->courtId, fn($q) => $q->where('court_id', $this->courtId))
            ->select(
                'court_id',
                'judge_id',
                DB::raw('COUNT(*) as cnt_cases'),
                DB::raw('SUM(CASE WHEN is_search_warrant THEN 1 ELSE 0 END) as cnt_warrants')
            )
            ->groupBy('court_id', 'judge_id')
            ->get()
            ->groupBy('court_id');

        $results = collect();
        foreach ($judgeData as $courtId => $judges) {
            $totalWarrants = (int) $judges->sum('cnt_warrants');
            if ($totalWarrants < 5) continue;

            $court = Court::find($courtId);

            // Calculate percentages and HHI based on warrants
            $percentages = $judges->map(fn($j) => 100 * $j->cnt_warrants / $totalWarrants);
            $hhi = $percentages->sum(fn($p) => pow($p, 2));
            $maxPct = $percentages->max();
            $topJudge = $judges->sortByDesc('cnt_warrants')->first();

            $results->push([
                'court' => $court?->short_name ?? 'N/A',
                'judges' => $judges->count(),
                'total_cases' => (int) $judges->sum('cnt_cases'),
                'total_warrants' => $totalWarrants,
                'max_pct' => round($maxPct, 0),
                'top_judge' => CourtCase::where('judge_id', $topJudge->judge_id)->value('judge_name') ?? 'N/A',
                'hhi' => round($hhi, 0),
                'flag' => $hhi > 5000 ? '🚨 EKSTREMNO' : ($hhi > 2500 ? '⚠️ VISOKO' : ''),
            ]);
        }

        $results = $results->sortByDesc('hhi')->take(10);

        $this->table(
            ['Sud', 'Sudaca', 'Svi predmeti', 'Pretresa', 'Max %', 'Top sudac', 'HHI', 'Status'],
            $results->map(fn($r) => [
                $r['court'],
                $r['judges'],
                $r['total_cases'],
                $r['total_warrants'],
                $r['max_pct'] . '%',
                substr($r['top_judge'], 0, 20),
                $r['hhi'],
                $r['flag'],
            ])
        );

        $this->line('  HHI: <2500=normalno, 2500-5000=koncentrirano, >5000=ekstremno');
        $this->line('  (Za 6 sudaca, normalan HHI je ~1667)');

        $this->stats['judge_concentration'] = [
            'max_hhi' => $results->max('hhi'),
            'courts_above_2500' => $results->where('hhi', '>', 2500)->count(),
        ];

        $this->newLine();
    }

    /**
     * STAT 4: SOKO using misdemeanor path
     */
    protected function stat4_SokoMisdemeanorPath(): void
    {
        $this->info('━━━ 4. SOKO KROZ PREKRŠAJNI PUT (organizirani kriminal → prekršaj?) ━━━');

        $query = CourtCaseDocument::whereHas('courtCase', function ($q) {
            $q->where('is_search_warrant', true)->where('year', $this->year);
            if ($this->courtId) {
                $q->where('court_id', $this->courtId);
            }
        })
            ->where('is_request', true)
            ->select('police_unit_type', DB::raw('COUNT(DISTINCT court_case_id) as cases'))
            ->groupBy('police_unit_type')
            ->orderByDesc('cases')
            ->get();

        $total = $query->sum('cases');

        $this->table(
            ['Policijska jedinica', 'Zahtjeva', '%', 'Komentar'],
            $query->map(function ($row) use ($total) {
                $pct = $total > 0 ? round(100 * $row->cases / $total, 1) : 0;
                $comment = match($row->police_unit_type) {
                    'SOKO' => '⚠️ Trebali bi koristiti KAZNENI postupak!',
                    'PP' => 'Lokalne policijske postaje',
                    'DIPU' => 'Dežurna interventna',
                    'CARINA' => 'Carinska uprava',
                    default => '',
                };
                return [$row->police_unit_type ?? 'Nepoznato', $row->cases, $pct . '%', $comment];
            })
        );

        $sokoCount = $query->where('police_unit_type', 'SOKO')->first()?->cases ?? 0;
        $this->stats['soko_misdemeanor'] = ['count' => $sokoCount, 'pct' => $total > 0 ? round(100 * $sokoCount / $total, 1) : 0];

        if ($sokoCount > 0) {
            $this->error("🚨 SOKO (Služba za organizirani kriminal) podnijela {$sokoCount} zahtjeva kroz PREKRŠAJNI postupak!");
            $this->line('   SOKO istražuje organizirani kriminal - trebali bi koristiti suca istrage (Kir), ne prekršajnog suca.');
        }

        $this->newLine();
    }

    /**
     * STAT 5: Regional Disproportion
     */
    protected function stat5_RegionalDisproportion(): void
    {
        $this->info('━━━ 5. REGIONALNA DISPROPORCIJA (per capita) ━━━');

        $results = CourtCase::where('year', $this->year)
            ->when($this->courtId, fn($q) => $q->where('court_id', $this->courtId))
            ->select(
                'court_id',
                DB::raw('COUNT(*) as total_cases'),
                DB::raw('SUM(CASE WHEN is_search_warrant THEN 1 ELSE 0 END) as warrants')
            )
            ->groupBy('court_id')
            ->get()
            ->map(function ($row) {
                $court = Court::where('id', $row->court_id)->first();
                $pop = $court?->population ?? 0;
                $per100k = $pop > 0 ? round(100000 * $row->warrants / $pop, 1) : 0;
                return [
                    'court' => $court?->short_name ?? 'N/A',
                    'county' => $court?->county ?? 'N/A',
                    'total_cases' => (int) $row->total_cases,
                    'warrants' => (int) $row->warrants,
                    'population' => $pop,
                    'per100k' => $per100k,
                ];
            })
            ->filter(fn($r) => $r['population'] > 0)
            ->sortByDesc('per100k')
            ->take(10);

        $nationalPer100k = 32.9; // Izračunato ranije: 1272/3.87M * 100k

        $this->table(
            ['Sud', 'Županija', 'Svi predmeti', 'Pretresa', 'Populacija', 'Per 100k', 'vs HR'],
            $results->map(fn($r) => [
                $r['court'],
                substr($r['county'], 0, 15),
                $r['total_cases'],
                $r['warrants'],
                number_format($r['population']),
                $r['per100k'],
                round($r['per100k'] / $nationalPer100k, 1) . 'x'
            ])
        );

        $maxPer100k = $results->max('per100k');
        $minPer100k = $results->min('per100k');

        $this->stats['regional_disproportion'] = [
            'max_per100k' => $maxPer100k,
            'min_per100k' => $minPer100k,
            'ratio' => $minPer100k > 0 ? round($maxPer100k / $minPer100k, 1) : null,
        ];

        if ($maxPer100k > 50) {
            $this->warn("⚠️  Maksimalna stopa: {$maxPer100k}/100k - značajno iznad nacionalnog prosjeka ({$nationalPer100k}/100k)");
        }

        $this->newLine();
    }

    /**
     * STAT 6: Weekend Warrants
     */
    protected function stat6_WeekendWarrants(): void
    {
        $this->info('━━━ 6. VIKEND NAREDBE (sumnjiva hitnost?) ━━━');

        $results = CourtCase::where('year', $this->year)
            ->when($this->courtId, fn($q) => $q->where('court_id', $this->courtId))
            ->select(
                'court_id',
                DB::raw('COUNT(*) as total_cases'),
                DB::raw('SUM(CASE WHEN is_search_warrant THEN 1 ELSE 0 END) as total_warrants'),
                DB::raw('SUM(CASE WHEN is_search_warrant AND is_weekend THEN 1 ELSE 0 END) as weekend')
            )
            ->groupBy('court_id')
            ->havingRaw('SUM(CASE WHEN is_search_warrant AND is_weekend THEN 1 ELSE 0 END) > ?', [0])
            ->get()
            ->map(function ($row) {
                $court = Court::find($row->court_id);
                $pct = $row->total_warrants > 0 ? round(100 * $row->weekend / $row->total_warrants, 0) : 0;
                return [
                    'court' => $court?->short_name ?? 'N/A',
                    'total_cases' => (int) $row->total_cases,
                    'total_warrants' => (int) $row->total_warrants,
                    'weekend' => (int) $row->weekend,
                    'pct' => $pct,
                    'flag' => $pct > 30 ? '⚠️' : '',
                ];
            })->sortByDesc('weekend')->take(10);

        $this->table(
            ['Sud', 'Svi predmeti', 'Pretresa', 'Vikend', '% (od pretresa)', ''],
            $results->map(fn($r) => [$r['court'], $r['total_cases'], $r['total_warrants'], $r['weekend'], $r['pct'] . '%', $r['flag']])
        );

        $this->line('  Očekivano: ~29% (2/7 dana). Značajno više može ukazivati na izbjegavanje nadzora.');

        $this->stats['weekend_warrants'] = [
            'total_weekend' => $results->sum('weekend'),
            'avg_pct' => round($results->avg('pct'), 1),
        ];

        $this->newLine();
    }

    /**
     * STAT 7: Processing Time per Judge
     */
    protected function stat7_ProcessingTime(): void
    {
        $this->info('━━━ 7. VRIJEME OBRADE PO SUCU ━━━');

        $results = CourtCase::where('year', $this->year)
            ->whereNotNull('judge_id')
            ->where('is_search_warrant', true)
            ->when($this->courtId, fn($q) => $q->where('court_id', $this->courtId))
            ->select(
                'judge_id',
                'judge_name',
                'court_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('AVG(processing_days) as avg_days'),
                DB::raw('SUM(CASE WHEN processing_days = 0 THEN 1 ELSE 0 END) as zero_days')
            )
            ->groupBy('judge_id', 'judge_name', 'court_id')
            ->havingRaw('COUNT(*) >= ?', [5])
            ->orderBy('avg_days')
            ->get()
            ->take(15)
            ->map(function ($row) {
                $court = Court::find($row->court_id);
                $zeroPct = $row->total > 0 ? round(100 * $row->zero_days / $row->total, 0) : 0;
                return [
                    'judge' => $row->judge_name,
                    'court' => $court?->short_name ?? 'N/A',
                    'total' => $row->total,
                    'avg_days' => round($row->avg_days ?? 0, 2),
                    'zero_days' => $row->zero_days,
                    'zero_pct' => $zeroPct,
                    'flag' => $row->avg_days == 0 ? '🚨' : ($row->avg_days < 1 ? '⚠️' : ''),
                ];
            });

        $this->table(
            ['Sudac', 'Sud', 'Pretresa', 'Avg dana', '0 dana', '% nula', ''],
            $results->map(fn($r) => [
                substr($r['judge'], 0, 20), $r['court'], $r['total'],
                $r['avg_days'], $r['zero_days'], $r['zero_pct'] . '%', $r['flag']
            ])
        );

        $this->stats['processing_time'] = [
            'judges_with_zero_avg' => $results->where('avg_days', 0)->count(),
            'overall_avg' => round($results->avg('avg_days'), 2),
        ];

        $this->newLine();
    }

    /**
     * STAT 8: Rejection Rate (approximate)
     */
    protected function stat8_RejectionRate(): void
    {
        $this->info('━━━ 8. STOPA ODBIJANJA ZAHTJEVA ━━━');

        $results = CourtCase::where('year', $this->year)
            ->where('register', 'Pp Prz')
            ->when($this->courtId, fn($q) => $q->where('court_id', $this->courtId))
            ->select(
                'court_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN decision_type IN ('Naredba', 'Nalog - pretrage') THEN 1 ELSE 0 END) as approved"),
                DB::raw("SUM(CASE WHEN decision_type LIKE '%odbij%' OR decision_type LIKE '%odbac%' THEN 1 ELSE 0 END) as rejected")
            )
            ->groupBy('court_id')
            ->havingRaw('COUNT(*) >= ?', [10])
            ->get()
            ->map(function ($row) {
                $court = Court::find($row->court_id);
                $rejPct = $row->total > 0 ? round(100 * $row->rejected / $row->total, 1) : 0;
                $appPct = $row->total > 0 ? round(100 * $row->approved / $row->total, 1) : 0;

                $warrantShare = $row->total > 0
                    ? (round(100 * $row->approved / $row->total, 1) . '%')
                    : 'N/A';

                return [
                    'court' => $court?->short_name ?? 'N/A',
                    'total' => (int) $row->total,
                    'approved' => (int) $row->approved,
                    'rejected' => (int) $row->rejected,
                    'app_pct' => $appPct,
                    'rej_pct' => $rejPct,
                    'warrant_share' => $warrantShare,
                    'flag' => $rejPct == 0 ? '🚨 0%' : '',
                ];
            })->sortBy('rej_pct')->take(10);

        $this->table(
            ['Sud', 'Svi zahtjevi', 'Pretresa', 'Udio pretresa', 'Odbijeno', 'Odbijeno %', ''],
            $results->map(fn($r) => [
                $r['court'], $r['total'], $r['approved'], $r['warrant_share'], $r['rejected'],
                $r['rej_pct'] . '%', $r['flag']
            ])
        );

        $this->line('  Očekivano: 5-10% odbijanja za smislenu sudsku kontrolu.');
        $this->line('  NAPOMENA: Ovo je aproksimacija - pravi podaci zahtijevaju pristup zapisnicima.');

        $this->stats['rejection_rate'] = [
            'courts_with_zero' => $results->where('rej_pct', 0)->count(),
            'avg_rejection' => round($results->avg('rej_pct'), 1),
        ];

        $this->newLine();
    }

    /**
     * STAT 9: Yearly Trend
     */
    protected function stat9_YearlyTrend(): void
    {
        $this->info('━━━ 9. TREND KROZ GODINE ━━━');

        // Always show a fixed trend window; do NOT constrain by --year.
        $years = [2023, 2024, 2025];

        $results = CourtCase::query()
            ->whereIn('year', $years)
            ->when($this->courtId, fn($q) => $q->where('court_id', $this->courtId))
            ->select(
                'year',
                DB::raw('COUNT(*) as total_cases'),
                DB::raw('SUM(CASE WHEN is_search_warrant THEN 1 ELSE 0 END) as total_warrants'),
                DB::raw('SUM(CASE WHEN is_search_warrant AND (processing_days = 0 OR is_same_day) THEN 1 ELSE 0 END) as same_day'),
                DB::raw('COUNT(DISTINCT judge_id) as judges'),
                DB::raw('AVG(processing_days) as avg_days')
            )
            ->groupBy('year')
            ->orderBy('year')
            ->get();

        $this->table(
            ['Godina', 'Svi predmeti', 'Pretresa', 'Isti dan %', 'Sudaca', 'Avg dana', 'Promjena'],
            $results->map(function ($row, $index) use ($results) {
                $sameDayPct = $row->total_warrants > 0 ? round(100 * $row->same_day / $row->total_warrants, 0) : 0;
                $prev = $index > 0 ? $results[$index - 1]->total_warrants : null;
                $change = $prev ? round(100 * ($row->total_warrants - $prev) / $prev, 0) . '%' : '-';
                $arrow = $prev ? ($row->total_warrants > $prev ? '↑' : ($row->total_warrants < $prev ? '↓' : '→')) : '';
                return [
                    $row->year,
                    $row->total_cases,
                    $row->total_warrants,
                    $sameDayPct . '%',
                    $row->judges,
                    round($row->avg_days ?? 0, 1),
                    $arrow . ' ' . $change,
                ];
            })
        );

        $first = $results->first();
        $last = $results->last();
        if ($first && $last && $first->total_warrants > 0) {
            $overallChange = round(100 * ($last->total_warrants - $first->total_warrants) / $first->total_warrants, 0);
            $this->stats['yearly_trend'] = [
                'change_pct' => $overallChange,
                'direction' => $overallChange > 10 ? 'RAST' : ($overallChange < -10 ? 'PAD' : 'STABILNO'),
            ];
        }

        $this->newLine();
    }

    /**
     * STAT 10: Proportionality Index
     */
    protected function stat10_ProportionalityIndex(): void
    {
        $this->info('━━━ 10. INDEKS PROPORCIONALNOSTI ━━━');

        $this->table(
            ['Mjera', 'Invazivnost', 'Sankcija', 'Proporcionalnost'],
            [
                ['PRETRES DOMA', '████████████ MAX', 'Za prekršaj', '❌ NEPROPORCIONALNO'],
                ['Prebrza vožnja 50+ km/h', '░░░░░░░░░░░░ MIN', '660-2.650 EUR', '✓ Proporcionalno'],
                ['Vožnja pod alkoholom', '░░░░░░░░░░░░ MIN', '660-2.650 EUR', '✓ Proporcionalno'],
                ['Posjedovanje droge', '████████████ MAX', '660-2.650 EUR', '❌ ???'],
            ]
        );

        $this->newLine();
        $this->line('  ┌─────────────────────────────────────────────────────────────────────┐');
        $this->line('  │  KLJUČNO PITANJE:                                                   │');
        $this->line('  │                                                                     │');
        $this->line('  │  Ako je posjedovanje droge TOLIKO OPASNO da opravdava              │');
        $this->line('  │  pretres doma (ustavno zaštićeno pravo)...                         │');
        $this->line('  │                                                                     │');
        $this->line('  │  → ZAŠTO JE ONDA SAMO PREKRŠAJ?                                    │');
        $this->line('  │                                                                     │');
        $this->line('  │  Ako je to SAMO PREKRŠAJ (kao prebrza vožnja)...                   │');
        $this->line('  │                                                                     │');
        $this->line('  │  → ZAŠTO ONDA PRETRES DOMA?                                        │');
        $this->line('  │                                                                     │');
        $this->line('  │  NE MOŽE BITI OBOJE ISTOVREMENO.                                   │');
        $this->line('  └─────────────────────────────────────────────────────────────────────┘');

        $this->stats['proportionality'] = [
            'issue' => 'MAX invazivnost za MIN sankciju',
        ];

        $this->newLine();
    }

    /**
     * Print summary dashboard
     */
    protected function printSummary(): void
    {
        $this->line('╔══════════════════════════════════════════════════════════════════════════════╗');
        $this->line('║                              SAŽETAK NALAZA                                  ║');
        $this->line('╠══════════════════════════════════════════════════════════════════════════════╣');

        // Volume
        $vol = $this->stats['national_volume'] ?? [];
        $this->line(sprintf('║  1. Nacionalni volumen:     %s pretresa/god  │  %.1f dnevno            ║',
            str_pad(number_format($vol['total'] ?? 0), 6, ' ', STR_PAD_LEFT),
            $vol['daily'] ?? 0
        ));

        // Rubber stamp
        $rs = $this->stats['rubber_stamp'] ?? [];
        $this->line(sprintf('║  2. Rubber stamp rate:      %s%% isti dan                                ║',
            str_pad($rs['avg_pct'] ?? 'N/A', 3, ' ', STR_PAD_LEFT)
        ));

        // Concentration
        $jc = $this->stats['judge_concentration'] ?? [];
        $this->line(sprintf('║  3. Max HHI koncentracija:  %s (>2500 = visoko)                       ║',
            str_pad($jc['max_hhi'] ?? 'N/A', 4, ' ', STR_PAD_LEFT)
        ));

        // SOKO
        $soko = $this->stats['soko_misdemeanor'] ?? [];
        $this->line(sprintf('║  4. SOKO kroz prekršaj:     %s slučajeva                                 ║',
            str_pad($soko['count'] ?? 0, 3, ' ', STR_PAD_LEFT)
        ));

        // Regional
        $reg = $this->stats['regional_disproportion'] ?? [];
        $this->line(sprintf('║  5. Max per capita:         %s/100k                                     ║',
            str_pad($reg['max_per100k'] ?? 'N/A', 4, ' ', STR_PAD_LEFT)
        ));

        // Weekend
        $we = $this->stats['weekend_warrants'] ?? [];
        $this->line(sprintf('║  6. Vikend odluke:          %s ukupno                                    ║',
            str_pad($we['total_weekend'] ?? 0, 3, ' ', STR_PAD_LEFT)
        ));

        // Processing
        $pt = $this->stats['processing_time'] ?? [];
        $this->line(sprintf('║  7. Avg vrijeme obrade:     %.1f dana                                     ║',
            $pt['overall_avg'] ?? 0
        ));

        // Rejection
        $rj = $this->stats['rejection_rate'] ?? [];
        $this->line(sprintf('║  8. Sudova s 0%% odbijanja:  %s                                           ║',
            str_pad($rj['courts_with_zero'] ?? 0, 2, ' ', STR_PAD_LEFT)
        ));

        // Trend
        $tr = $this->stats['yearly_trend'] ?? [];
        $this->line(sprintf('║  9. Trend 2023→2025:        %s                                       ║',
            str_pad($tr['direction'] ?? 'N/A', 10, ' ', STR_PAD_LEFT)
        ));

        // Proportionality
        $this->line('║ 10. Proporcionalnost:       PRETRES za GLOBU = NEPROPORCIONALNO          ║');

        $this->line('╚══════════════════════════════════════════════════════════════════════════════╝');
    }

    /**
     * Export stats to file
     */
    protected function exportStats(string $format): void
    {
        $filename = "epredmet_dashboard_{$this->year}_" . date('Y-m-d_H-i-s');

        if ($format === 'json') {
            $path = storage_path("app/{$filename}.json");
            file_put_contents($path, json_encode($this->stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } elseif ($format === 'csv') {
            $path = storage_path("app/{$filename}.csv");
            $fp = fopen($path, 'w');
            fputcsv($fp, ['Statistika', 'Vrijednost']);
            foreach ($this->stats as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $k => $v) {
                        fputcsv($fp, ["{$key}.{$k}", $v]);
                    }
                } else {
                    fputcsv($fp, [$key, $value]);
                }
            }
            fclose($fp);
        }

        $this->info("Statistika exportana u: {$path}");
    }
}
