<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseParty extends Model
{
    protected $fillable = [
        'court_case_id', 'name', 'role', 'is_defendant', 'is_prosecutor',
        // Resolved fields
        'resolved_name', 'institution_type', 'resolution_confidence', 'is_institution',
    ];
    
    protected $casts = [
        'is_defendant' => 'boolean',
        'is_prosecutor' => 'boolean',
        'is_institution' => 'boolean',
    ];

    public function courtCase(): BelongsTo { return $this->belongsTo(CourtCase::class); }

    // Scopes
    public function scopeInstitutions($q) { return $q->where('is_institution', true); }
    public function scopeProsecutors($q) { return $q->where('is_prosecutor', true); }
    public function scopeDefendants($q) { return $q->where('is_defendant', true); }
    public function scopeByInstitutionType($q, string $type) { return $q->where('institution_type', $type); }
    public function scopeUnresolved($q) { return $q->whereNull('resolved_name')->where('is_institution', true); }

    public static function isDefendantRole(?string $r): bool {
        return $r && preg_match('/okrivljenik|optuženik|osumnjičenik|tuženik/i', $r);
    }

    public static function isProsecutorRole(?string $r): bool {
        return $r && preg_match('/podnositelj|tužitelj|policija/i', $r);
    }

    /**
     * Apply resolution results to this party
     */
    public function applyResolution(array $resolution): self
    {
        $this->update([
            'resolved_name' => $resolution['name'],
            'institution_type' => $resolution['type'],
            'resolution_confidence' => $resolution['confidence'],
            'is_institution' => $resolution['type'] !== 'person',
        ]);
        
        return $this;
    }

    /**
     * Get display name (resolved if available, otherwise original)
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->resolved_name ?? $this->name ?? 'Unknown';
    }

    public static function syncFromApiResponse(int $caseId, array $parties): void {
        static::where('court_case_id', $caseId)->delete();
        foreach ($parties as $p) {
            $role = $p['nazivuloge'] ?? null;
            static::create([
                'court_case_id' => $caseId, 'name' => $p['naziv'] ?? null, 'role' => $role,
                'is_defendant' => self::isDefendantRole($role),
                'is_prosecutor' => self::isProsecutorRole($role),
            ]);
        }
    }
}
