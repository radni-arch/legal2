<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DocumentIdentity Model
 *
 * Sprint 7 - Task 38: Multi-dimensional document tracking
 *
 * Tracks Croatian legal documents across multiple identification layers:
 * - Case/Metacase identity (Pp Prz-74/2025-3)
 * - Administrative identity (KLASA, URBROJ)
 * - Internal number (Broj)
 * - Derived metadata (date, type, institution, role)
 *
 * @property int $id
 * @property string $case_id
 * @property int|null $case_document_id
 * @property string|null $case_number
 * @property string|null $case_prefix
 * @property int|null $case_seq_number
 * @property int|null $case_year
 * @property int|null $case_suffix
 * @property string|null $case_number_full
 * @property string|null $klasa
 * @property string|null $urbroj
 * @property string|null $urbroj_institution_code
 * @property int|null $urbroj_suffix
 * @property string|null $broj
 * @property string|null $broj_type
 * @property \Carbon\Carbon|null $document_date
 * @property string|null $document_type
 * @property string|null $issuing_institution
 * @property string|null $metacase_role
 * @property string $presence_status
 * @property string $discovery_source
 * @property array|null $referenced_in_documents
 * @property int $reference_count
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class DocumentIdentity extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'referenced_in_documents' => 'array',
        'document_date' => 'date',
        'case_suffix' => 'integer',
        'case_seq_number' => 'integer',
        'case_year' => 'integer',
        'urbroj_suffix' => 'integer',
        'reference_count' => 'integer',
    ];

    // Presence status constants
    public const STATUS_PRESENT = 'present';
    public const STATUS_MISSING = 'missing';
    public const STATUS_PARTIAL = 'partial';

    // Discovery source constants
    public const SOURCE_EXTRACTION = 'extraction';
    public const SOURCE_INFERENCE = 'inference';
    public const SOURCE_MANUAL = 'manual';

    /**
     * Get the associated case document.
     */
    public function caseDocument(): BelongsTo
    {
        return $this->belongsTo(CaseDocument::class);
    }

    // === Scopes ===

    /**
     * Scope to filter present documents.
     */
    public function scopePresent($query)
    {
        return $query->where('presence_status', self::STATUS_PRESENT);
    }

    /**
     * Scope to filter missing documents.
     */
    public function scopeMissing($query)
    {
        return $query->where('presence_status', self::STATUS_MISSING);
    }

    /**
     * Scope to filter by case number.
     */
    public function scopeForCaseNumber($query, string $caseNumber)
    {
        return $query->where('case_number', $caseNumber);
    }

    /**
     * Scope to filter by KLASA.
     */
    public function scopeForKlasa($query, string $klasa)
    {
        return $query->where('klasa', $klasa);
    }

    // === Static Helper Methods ===

    /**
     * Parse a full case number like "Pp Prz-74/2025-3" into components.
     */
    public static function parseCaseNumberFull(string $full): ?array
    {
        // Pattern: "Prefix-Seq/Year-Suffix" or "Prefix-Seq/Year"
        // Prefix can have spaces: "Pp Prz", "Kv II", "I Kz"
        $pattern = '/^((?:Pp\s+Prz|Pp\s+J|Kv\s+II|Kis-DO|KP-DO|I\s+Kz|[A-Za-zZzCcCcSsGgDd]+))-(\d+)\/(\d{2,4})(?:-(\d+))?$/u';

        if (!preg_match($pattern, trim($full), $m)) {
            return null;
        }

        $year = strlen($m[3]) === 2 ? (int)('20' . $m[3]) : (int)$m[3];

        return [
            'prefix' => trim($m[1]),
            'seq_number' => (int)$m[2],
            'year' => $year,
            'suffix' => isset($m[4]) ? (int)$m[4] : null,
            'case_number' => trim($m[1]) . '-' . $m[2] . '/' . $m[3], // Without suffix
            'full' => trim($full),
        ];
    }

    /**
     * Parse URBROJ like "2158-64-16-01-25-3" to extract institution code and suffix.
     */
    public static function parseUrbroj(string $urbroj): ?array
    {
        // Clean prefix
        $clean = preg_replace('/^U\s*R\s*B\s*R\s*O\s*J\s*:\s*/ui', '', $urbroj);
        $clean = preg_replace('/^Ur\.?\s*br(?:oj)?\.?\s*:\s*/ui', '', $clean);
        $clean = trim($clean);

        // Split by dash
        $parts = preg_split('/[-]/', $clean);
        if (count($parts) < 3) {
            return null;
        }

        return [
            'institution_code' => $parts[0],  // "2158", "511"
            'full' => $clean,
            'suffix' => (int)end($parts),     // Last number
            'parts' => $parts,
        ];
    }
}
