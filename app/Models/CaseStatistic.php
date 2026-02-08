<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class CaseStatistic extends Model
{
    protected $fillable = [
        'court_id', 'judge_id', 'year', 'month', 'register',
        'total_cases', 'search_warrants', 'same_day_decisions', 'weekend_decisions',
        'rejected_requests', 'avg_processing_days', 'concentration_percent',
        'requests_by_soko', 'requests_by_local_pp', 'requests_by_dipu', 'requests_by_other'
    ];

    public function court(): BelongsTo { return $this->belongsTo(Court::class); }
    public function judge(): BelongsTo { return $this->belongsTo(Judge::class); }

    public static function recalculateForCourt(int $courtId, int $year, ?string $register = 'Pp Prz'): void
    {
        $q = CourtCase::where('court_id', $courtId)->where('year', $year);
        if ($register) $q->where('register', $register);

        $total = (clone $q)->count();
        $warrants = (clone $q)->where('is_search_warrant', true)->count();
        $sameDay = (clone $q)->where('is_search_warrant', true)->where('is_same_day', true)->count();
        $weekend = (clone $q)->where('is_search_warrant', true)->where('is_weekend', true)->count();
        $avgDays = (clone $q)->where('is_search_warrant', true)->avg('processing_days');

        static::updateOrCreate(
            ['court_id' => $courtId, 'judge_id' => null, 'year' => $year, 'month' => null, 'register' => $register],
            compact('total_cases', 'search_warrants', 'same_day_decisions', 'weekend_decisions', 'avg_processing_days') + [
                'total_cases' => $total, 'search_warrants' => $warrants,
                'same_day_decisions' => $sameDay, 'weekend_decisions' => $weekend,
                'avg_processing_days' => $avgDays,
            ]
        );

        // Per-judge stats
        $judges = CourtCase::where('court_id', $courtId)->where('year', $year)
            ->where('is_search_warrant', true)->whereNotNull('judge_id')->distinct()->pluck('judge_id');

        foreach ($judges as $judgeId) {
            $jWarrants = CourtCase::where('court_id', $courtId)->where('year', $year)
                ->where('judge_id', $judgeId)->where('is_search_warrant', true)->count();
            $concentration = $warrants > 0 ? round(100 * $jWarrants / $warrants, 2) : 0;

            static::updateOrCreate(
                ['court_id' => $courtId, 'judge_id' => $judgeId, 'year' => $year, 'month' => null, 'register' => $register],
                ['search_warrants' => $jWarrants, 'concentration_percent' => $concentration]
            );
        }
    }
}
