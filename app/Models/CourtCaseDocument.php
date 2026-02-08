<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtCaseDocument extends Model
{
    protected $fillable = [
        'court_case_id', 'document_type', 'document_kind', 'document_date', 'submitter',
        'submitter_decoded', 'attachments', 'is_request', 'is_decision', 'is_report',
        'police_unit_type', 'sequence'
    ];

    protected $casts = [
        'document_date' => 'datetime',
        'is_request' => 'boolean', 'is_decision' => 'boolean', 'is_report' => 'boolean',
    ];

    public const POLICE_MAPPINGS = [
        'S.O.K.O.' => 'SOKO', 'S.O.K.O.O.K.D.' => 'SOKO - Odjel KD',
        'P.U.O.B.S.K.P.S.O.K.' => 'PU OB - SKP - SOKO',
        'I.P.P.O.' => 'I. PP Osijek', 'I.P.P.O.S.I.Č.' => 'I. PP Osijek - SIČ',
        'P.P.B.M.' => 'PP Beli Manastir', 'P.P.D.M.' => 'PP Donji Miholjac',
        'P.P.B.' => 'PP Belišće', 'P.P.V.' => 'PP Valpovo', 'P.P.N.' => 'PP Našice',
        'P.P.S.' => 'PP Slatina', 'P.P.O.' => 'PP Orahovica', 'P.P.R.' => 'PP Rab',
        'D.I.P.U.O.' => 'DIPU Osijek', 'D.I.P.U.O.S.T.I.' => 'DIPU - STI',
    ];

    public function courtCase(): BelongsTo { return $this->belongsTo(CourtCase::class); }

    public function scopeRequests($q) { return $q->where('is_request', true); }
    public function scopeBySoko($q) { return $q->where('police_unit_type', 'SOKO'); }

    public static function decodeSubmitter(?string $s): ?string {
        return $s ? (self::POLICE_MAPPINGS[$s] ?? $s) : null;
    }

    public static function determinePoliceUnitType(?string $s): ?string {
        if (!$s) return null;
        $s = strtoupper($s);
        if (str_contains($s, 'S.O.K.O') || str_contains($s, 'SOKO')) return 'SOKO';
        if (str_contains($s, 'D.I.P.U') || str_contains($s, 'DIPU')) return 'DIPU';
        if (str_contains($s, 'C.U.') || str_contains($s, 'M.F.')) return 'CARINA';
        if (preg_match('/P\.P\./', $s)) return 'PP';
        if (str_contains($s, 'P.U.')) return 'PU';
        return 'OTHER';
    }

    public static function isRequestDocument(?string $k): bool {
        return $k && str_contains(strtolower($k), 'zahtjev za pretrag');
    }

    public static function isDecisionDocument(?string $k): bool {
        if (!$k) return false;
        $k = strtolower($k);
        return str_contains($k, 'naredba') || str_contains($k, 'nalog') || str_contains($k, 'rješenje');
    }

    public static function syncFromApiResponse(int $caseId, array $docs): void {
        static::where('court_case_id', $caseId)->delete();
        foreach ($docs as $i => $d) {
            $kind = $d['vrsta'] ?? null;
            $submitter = $d['podnositelj'] ?? null;
            static::create([
                'court_case_id' => $caseId,
                'document_type' => $d['tip'] ?? null, 'document_kind' => $kind,
                'document_date' => isset($d['datum']) ? Carbon::parse($d['datum']) : null,
                'submitter' => $submitter, 'submitter_decoded' => self::decodeSubmitter($submitter),
                'attachments' => $d['prilozi'] ?? null,
                'is_request' => self::isRequestDocument($kind),
                'is_decision' => self::isDecisionDocument($kind),
                'is_report' => $kind && str_contains(strtolower($kind), 'izvješće'),
                'police_unit_type' => self::determinePoliceUnitType($submitter),
                'sequence' => $i + 1,
            ]);
        }
    }
}
