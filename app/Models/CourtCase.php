<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Carbon\Carbon;

class CourtCase extends Model
{
    use HasFactory;
    protected $fillable = [
        'external_id', 'case_number', 'register', 'number', 'year', 'court_id', 'judge_id',
        'judge_name', 'case_type', 'decision_type', 'register_name',
        'date_filed', 'date_assigned', 'date_decision', 'date_dispatched', 'date_final',
        'date_enforceable', 'date_archived', 'date_appeal', 'date_retention', 'date_process_start',
        'processing_days', 'is_same_day', 'is_search_warrant', 'is_weekend',
        'case_at_higher_court', 'case_outside_court', 'wrongly_registered', 'permanent_service',
        'raw_data', 'last_synced_at', 'api_last_update',
        // Validation fields
        'is_confirmed_warrant', 'warrant_confidence', 'warrant_validation_score',
        'warrant_validation_reasons', 'warrant_type', 'validated_at',
    ];

    protected $casts = [
        'external_id' => 'integer', 'number' => 'integer', 'year' => 'integer',
        'date_filed' => 'datetime', 'date_assigned' => 'datetime', 'date_decision' => 'datetime',
        'date_dispatched' => 'datetime', 'date_final' => 'datetime', 'date_enforceable' => 'datetime',
        'date_archived' => 'datetime', 'date_appeal' => 'datetime', 'date_retention' => 'datetime',
        'date_process_start' => 'datetime', 'processing_days' => 'integer',
        'is_same_day' => 'boolean', 'is_search_warrant' => 'boolean', 'is_weekend' => 'boolean',
        'raw_data' => 'array', 'last_synced_at' => 'datetime', 'api_last_update' => 'datetime',
        // Validation casts
        'is_confirmed_warrant' => 'boolean',
        'warrant_validation_score' => 'integer',
        'warrant_validation_reasons' => 'array',
        'validated_at' => 'datetime',
    ];

    // Relationships
    public function court(): BelongsTo { return $this->belongsTo(Court::class); }
    public function judge(): BelongsTo { return $this->belongsTo(Judge::class); }
    public function documents(): HasMany { return $this->hasMany(CourtCaseDocument::class); }
    public function parties(): HasMany { return $this->hasMany(CaseParty::class); }
    public function hearings(): HasMany { return $this->hasMany(CaseHearing::class); }

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

    // Scopes
    public function scopeSearchWarrants($q) { return $q->where('is_search_warrant', true); }
    public function scopeConfirmedWarrants($q) { return $q->where('is_confirmed_warrant', true); }
    public function scopeHighConfidence($q) { return $q->where('warrant_confidence', 'high'); }
    public function scopeByYear($q, int $y) { return $q->where('year', $y); }
    public function scopeByRegister($q, string $r) { return $q->where('register', $r); }
    public function scopeByCourt($q, int $id) { return $q->where('court_id', $id); }
    public function scopeByJudge($q, int $id) { return $q->where('judge_id', $id); }
    public function scopeSameDay($q) { return $q->where('is_same_day', true); }
    public function scopeWeekend($q) { return $q->where('is_weekend', true); }
    public function scopeByWarrantType($q, string $type) { return $q->where('warrant_type', $type); }
    public function scopeUnvalidated($q) { return $q->whereNull('validated_at'); }

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

    // Static helpers
    public static function isSearchWarrantDecision(?string $type): bool {
        return $type && in_array($type, ['Naredba', 'Nalog - pretrage', 'Nalog-pretrage']);
    }

    public static function parseDate(?string $s): ?Carbon {
        return $s ? Carbon::parse($s) : null;
    }

    /**
     * Apply validation results to this case
     */
    public function applyValidation(array $validation, ?string $warrantType = null): self
    {
        $this->update([
            'is_confirmed_warrant' => $validation['is_warrant'],
            'warrant_confidence' => $validation['confidence'],
            'warrant_validation_score' => $validation['score'],
            'warrant_validation_reasons' => $validation['reasons'],
            'warrant_type' => $warrantType,
            'validated_at' => now(),
        ]);

        return $this;
    }

    /**
     * Heuristika: datum podnošenja (filed/request) uzmi iz prvog PS pismena.
     * Preferiraj "Zahtjev" (ali ignoriraj "Zahtjev za uvid u spis" kao request).
     */
    private static function extractFiledDateFromPismena(array $pismena): ?Carbon
    {
        $zahtjevi = [];
        $psAny = [];

        foreach ($pismena as $p) {
            $tip = strtoupper((string)($p['tip'] ?? ''));
            $vrsta = (string)($p['vrsta'] ?? '');
            $datum = self::parseDate($p['datum'] ?? null);

            if (!$datum) continue;
            if ($tip !== 'PS') continue;

            $psAny[] = $datum;

            // Preferiraj "Zahtjev", ali ne "Zahtjev za uvid u spis"
            if (preg_match('/zahtjev/i', $vrsta) && !preg_match('/uvid\s+u\s+spis/i', $vrsta)) {
                $zahtjevi[] = $datum;
            }
        }

        $pick = $zahtjevi ?: $psAny;
        if (!$pick) return null;

        usort($pick, fn($a, $b) => $a->getTimestamp() <=> $b->getTimestamp());
        return $pick[0];
    }

    // Factory method from API response
    public static function createFromApiResponse(array $data, int $courtId): self
    {
        $caseNumber = $data['oznakaBroj'] ?? '';
        preg_match('/^(.+)-(\d+)\/(\d+)$/', $caseNumber, $m);
        $register = $m[1] ?? 'Pp Prz';
        $number = (int)($m[2] ?? 0);
        $year = (int)($m[3] ?? date('Y'));

        $judgeName = $data['sudac'] ?? null;
        $judgeId = $judgeName ? Judge::findOrCreateByName($judgeName, $courtId)->id : null;

        $dateAssigned = self::parseDate($data['datumDodjele'] ?? null);

        $pismenaRaw = $data['pismena'] ?? [];
        $dateFiled = self::extractFiledDateFromPismena($pismenaRaw)
            ?? self::parseDate($data['datumPocetkaProcesa'] ?? null)
            ?? self::parseDate($data['datumOsnivanja'] ?? null)
            ?? $dateAssigned;

        $dateDecision = self::parseDate($data['datumDonosenjaOdluke'] ?? null)
            ?? self::parseDate($data['datumOtpreme'] ?? null);

        // Calculate derived fields
        $startDate = $dateFiled ?? $dateAssigned;

        $processingDaysSigned = ($startDate && $dateDecision)
            ? $startDate->copy()->startOfDay()->diffInDays($dateDecision->copy()->startOfDay(), false)
            : null;

// Ne spremaj negativno u processing_days (radije null)
        $processingDays = ($processingDaysSigned !== null && $processingDaysSigned >= 0)
            ? $processingDaysSigned
            : null;

        $isSameDay = ($processingDaysSigned === 0);
        $isWeekend = $dateDecision?->isWeekend() ?? false;
        $isSearchWarrant = self::isSearchWarrantDecision($data['vrstaOdluke'] ?? null);

        $case = static::updateOrCreate(
            ['court_id' => $courtId, 'case_number' => $caseNumber],
            [
                'external_id' => $data['id'] ?? null,
                'register' => $register, 'number' => $number, 'year' => $year,
                'judge_id' => $judgeId, 'judge_name' => $judgeName,
                'case_type' => $data['vrstaPredmeta'] ?? null,
                'decision_type' => $data['vrstaOdluke'] ?? null,
                'register_name' => $data['upisnikNaziv'] ?? null,
                'date_filed' => $dateFiled,
                'date_assigned' => $dateAssigned,
                'date_decision' => $dateDecision,
                'date_dispatched' => self::parseDate($data['datumOtpreme'] ?? null),
                'date_final' => self::parseDate($data['datumPravomocnosti'] ?? null),
                'date_enforceable' => self::parseDate($data['datumOvrsnosti'] ?? null),
                'date_archived' => self::parseDate($data['datumArhiviranja'] ?? null),
                'date_appeal' => self::parseDate($data['datumZalbe'] ?? null),
                'date_retention' => self::parseDate($data['datumRokaCuvanja'] ?? null),
                'date_process_start' => self::parseDate($data['datumPocetkaProcesa'] ?? null),
                'processing_days' => $processingDays,
                'is_same_day' => $isSameDay, 'is_search_warrant' => $isSearchWarrant, 'is_weekend' => $isWeekend,
                'case_at_higher_court' => $data['spisNaVisemSudu'] ?? null,
                'case_outside_court' => $data['spisIzvanSuda'] ?? null,
                'wrongly_registered' => $data['pogresnoUpisani'] ?? null,
                'permanent_service' => $data['stalnaSluzba'] ?? null,
                'raw_data' => $data,
                'api_last_update' => self::parseDate($data['lastUpdateTime'] ?? null),
                'last_synced_at' => now(),
            ]
        );

        // Sync related data
        if (!empty($data['pismena'])) CourtCaseDocument::syncFromApiResponse($case->id, $data['pismena']);
        if (!empty($data['stranke'])) CaseParty::syncFromApiResponse($case->id, $data['stranke']);
        if (!empty($data['rocista'])) CaseHearing::syncFromApiResponse($case->id, $data['rocista']);

        return $case;
    }
}
