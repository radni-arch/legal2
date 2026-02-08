<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class CaseHearing extends Model
{
    protected $fillable = [
        'court_case_id', 'action_type', 'planned_start', 'planned_end',
        'actual_start', 'actual_end', 'room_code', 'room_name',
        'postponement', 'was_postponed', 'duration_minutes'
    ];

    protected $casts = [
        'planned_start' => 'datetime', 'planned_end' => 'datetime',
        'actual_start' => 'datetime', 'actual_end' => 'datetime',
        'was_postponed' => 'boolean', 'duration_minutes' => 'integer',
    ];

    public function courtCase(): BelongsTo { return $this->belongsTo(CourtCase::class); }

    public static function syncFromApiResponse(int $caseId, array $hearings): void {
        static::where('court_case_id', $caseId)->delete();
        foreach ($hearings as $h) {
            $start = isset($h['stPocetak']) ? Carbon::parse($h['stPocetak']) : null;
            $end = isset($h['stZavrsetak']) ? Carbon::parse($h['stZavrsetak']) : null;
            static::create([
                'court_case_id' => $caseId,
                'action_type' => $h['vrstaRadnje'] ?? null,
                'planned_start' => isset($h['plPocetak']) ? Carbon::parse($h['plPocetak']) : null,
                'planned_end' => isset($h['plZavrsetak']) ? Carbon::parse($h['plZavrsetak']) : null,
                'actual_start' => $start, 'actual_end' => $end,
                'room_code' => $h['sobaoznaka'] ?? null, 'room_name' => $h['sobanaziv'] ?? null,
                'postponement' => $h['odgoda'] ?? null,
                'was_postponed' => !empty($h['odgoda']),
                'duration_minutes' => ($start && $end) ? $start->diffInMinutes($end) : null,
            ]);
        }
    }
}
