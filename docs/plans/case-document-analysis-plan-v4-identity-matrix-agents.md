# Case Document Analysis — Plan v4: Document Identity Matrix + Vendor-Independent AI Agent

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.
> Tasks numbered 38+ continuing from v3.

---

## Feature 1: Document Identity Matrix — Multi-Dimensional Document Tracking

### The Problem

A single Croatian legal document carries **multiple identification layers simultaneously**:

```
┌─────────────────────────────────────────────────────────────┐
│  PHYSICAL PAPER: "Rješenje o pretrazi stana"                │
│                                                              │
│  LAYER 1 — Case/Metacase:  Pp Prz-74/2025-3                │
│            ↳ Case: Pp Prz-74/2025                           │
│            ↳ Suffix: -3 (third document in this case)       │
│                                                              │
│  LAYER 2 — Administrative:  KLASA: UP/I-034-02/25-01/5     │
│                              URBROJ: 2158-64-16-01-25-3     │
│                                                              │
│  LAYER 3 — Internal:  Broj: 511-07-11-K-51/2025            │
│                                                              │
│  LAYER 4 — Derived:   Date: 15.01.2025.                    │
│                        Issuer: Općinski sud u Osijeku        │
│                        Type: rješenje                        │
└─────────────────────────────────────────────────────────────┘
```

These layers cross-reference each other:
- The case suffix `-3` tells us this is the **3rd paper** in Pp Prz-74/2025
- If we have `-1` and `-3` but not `-2`, that's a **missing document**
- The URBROJ suffix `-3` often (but not always) matches the case suffix
- KLASA + URBROJ together uniquely identify an administrative act
- The same document might be referenced by ANY of these identifiers in other documents

**Goal:** Build a unified identity matrix that tracks documents across all dimensions, detects sequence gaps in suffixes, and renders a visual "case file completeness" view.

---

### How Croatian Document Numbering Works

**Case number suffixes:** `Pp Prz-74/2025-N`
- `-1` = first document (usually the request/prijedlog)
- `-2` = court decision (rješenje)
- `-3` = confirmation of delivery / execution report
- `-4`, `-5` = subsequent actions, appeals, corrections
- The sequence is **strictly sequential** — if `-3` exists, `-1` and `-2` MUST exist

**URBROJ suffixes:** `2158-64-16-01-25-N`
- Similar sequential numbering within an institution's registry
- The prefix identifies the institution (2158 = Osijek area court)
- Each administrative act increments the suffix

**KLASA:** `UP/I-034-02/25-01/5`
- Identifies the **type of matter** (034-02 = criminal law category)
- `/25-01/5` = year 2025, sequential number 5
- KLASA stays the same across all documents in the same administrative matter
- Multiple documents share the same KLASA but have different URBROJs

**Key insight:** KLASA groups documents, URBROJ identifies individual documents. Case number + suffix identifies documents within a judicial case. A single physical paper may carry ALL three.

---

### Task 38: DocumentIdentity model + migration

**Files:**
- Create: `database/migrations/YYYY_MM_DD_create_document_identities_table.php`
- Create: `app/Models/DocumentIdentity.php`

**Step 1: Migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_identities', function (Blueprint $table) {
            $table->id();
            $table->string('case_id');

            // Link to actual uploaded document (nullable — for missing docs)
            $table->unsignedBigInteger('case_document_id')->nullable();

            // === LAYER 1: Case/Metacase Identity ===
            $table->string('case_number')->nullable();        // "Pp Prz-74/2025"
            $table->string('case_prefix')->nullable();        // "Pp Prz"
            $table->integer('case_seq_number')->nullable();   // 74
            $table->integer('case_year')->nullable();          // 2025
            $table->integer('case_suffix')->nullable();        // 3 (from "-3")
            $table->string('case_number_full')->nullable();   // "Pp Prz-74/2025-3"

            // === LAYER 2: Administrative Identity ===
            $table->string('klasa')->nullable();               // "UP/I-034-02/25-01/5"
            $table->string('urbroj')->nullable();              // "2158-64-16-01-25-3"
            $table->string('urbroj_institution_code')->nullable(); // "2158" (parsed)
            $table->integer('urbroj_suffix')->nullable();      // 3 (parsed from end)

            // === LAYER 3: Internal Number ===
            $table->string('broj')->nullable();                // "511-07-11-K-51/2025"
            $table->string('broj_type')->nullable();           // "policijski", "drzavno_odvjetnistvo"

            // === LAYER 4: Derived Metadata ===
            $table->date('document_date')->nullable();         // Date found in/on the document
            $table->string('document_type')->nullable();       // "rjesenje", "zapisnik", "naredba", etc.
            $table->string('issuing_institution')->nullable(); // "Općinski sud u Osijeku"
            $table->string('metacase_role')->nullable();       // "search_warrant", "detention", etc.

            // === Status ===
            $table->string('presence_status');                  // "present", "missing", "partial"
            // present  = we have the actual file
            // missing  = referenced in other docs but not in our file
            // partial  = OCR failed or file corrupted

            // How was this identity discovered?
            $table->string('discovery_source');                 // "extraction", "inference", "manual"
            // extraction = found identifiers on this document
            // inference  = gap in sequence implies existence
            // manual     = user added it

            // References: which documents mention this identity
            $table->json('referenced_in_documents')->nullable(); // [doc_id, doc_id, ...]
            $table->integer('reference_count')->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['case_id', 'presence_status']);
            $table->index(['case_id', 'case_number']);
            $table->index(['case_id', 'klasa']);
            $table->index(['case_number', 'case_suffix']);

            // A document can only have one identity row per case_number_full
            $table->unique(['case_id', 'case_number_full'], 'doc_identity_case_unique');

            $table->foreign('case_document_id')
                ->references('id')->on('case_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_identities');
    }
};
```

**Step 2: Model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentIdentity extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'referenced_in_documents' => 'array',
        'document_date' => 'date',
        'case_suffix' => 'integer',
        'urbroj_suffix' => 'integer',
        'reference_count' => 'integer',
    ];

    public const STATUS_PRESENT = 'present';
    public const STATUS_MISSING = 'missing';
    public const STATUS_PARTIAL = 'partial';

    public const SOURCE_EXTRACTION = 'extraction';
    public const SOURCE_INFERENCE = 'inference';
    public const SOURCE_MANUAL = 'manual';

    public function caseDocument(): BelongsTo
    {
        return $this->belongsTo(CaseDocument::class);
    }

    // Scopes
    public function scopePresent($query) { return $query->where('presence_status', self::STATUS_PRESENT); }
    public function scopeMissing($query) { return $query->where('presence_status', self::STATUS_MISSING); }

    public function scopeForCaseNumber($query, string $caseNumber)
    {
        return $query->where('case_number', $caseNumber);
    }

    public function scopeForKlasa($query, string $klasa)
    {
        return $query->where('klasa', $klasa);
    }

    /**
     * Parse a full case number like "Pp Prz-74/2025-3" into components.
     */
    public static function parseCaseNumberFull(string $full): ?array
    {
        // Pattern: "Prefix-Seq/Year-Suffix" or "Prefix-Seq/Year"
        // Prefix can have spaces: "Pp Prz", "Kv II", "I Kž"
        $pattern = '/^((?:Pp\s+Prz|Pp\s+J|Kv\s+II|Kis-DO|KP-DO|I\s+Kž|[A-Za-zŽžĆćČčŠšĐđ]+))-(\d+)\/(\d{2,4})(?:-(\d+))?$/u';

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
        if (count($parts) < 3) return null;

        return [
            'institution_code' => $parts[0],  // "2158", "511"
            'full' => $clean,
            'suffix' => (int)end($parts),     // Last number
            'parts' => $parts,
        ];
    }
}
```

---

### Task 39: DocumentIdentityBuilder — Assembles identities from extraction results

**Files:**
- Create: `app/Services/Analysis/CaseLevel/DocumentIdentityBuilder.php`

**How it works:** Runs after CaseReferenceExtractor (Task 21) completes for all documents. For each document:
1. Takes the CaseReferenceExtractor output
2. Identifies which references are the document's **OWN** identifiers (position < 500 chars = header)
3. Creates a DocumentIdentity row linking case_number ↔ KLASA ↔ URBROJ
4. Detects suffix sequence gaps → creates "missing" identity rows
5. Cross-references: if doc A mentions "Pp Prz-74/2025-2" but no doc has that as its own, mark missing

```php
<?php

namespace App\Services\Analysis\CaseLevel;

use App\Models\DocumentIdentity;
use App\Models\DocumentAnalysis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentIdentityBuilder
{
    /**
     * Build the complete identity matrix for a case.
     */
    public function build(string $caseId): array
    {
        $startTime = microtime(true);

        // Phase 1: Load all extraction results
        $analyses = DocumentAnalysis::whereHas('caseDocument', function ($q) use ($caseId) {
            $q->where('case_id', $caseId);
        })
            ->where('analysis_type', 'case_references')
            ->where('status', DocumentAnalysis::STATUS_COMPLETED)
            ->with('caseDocument')
            ->get();

        // Phase 2: Build identity rows for PRESENT documents
        $identities = [];      // case_number_full → DocumentIdentity data
        $allMentioned = [];    // All case_number_fulls mentioned anywhere

        foreach ($analyses as $analysis) {
            $docId = $analysis->case_document_id;
            $results = $analysis->results;

            // Find this document's OWN identifiers (in header, position < 500)
            $ownCaseNumbers = [];
            $ownKlasa = null;
            $ownUrbroj = null;
            $ownBroj = null;
            $ownBrojType = null;

            // Case numbers in header
            foreach ($results['case_numbers'] ?? [] as $ref) {
                if (($ref['position'] ?? 999) < 500) {
                    $parsed = DocumentIdentity::parseCaseNumberFull($ref['value']);
                    if ($parsed) {
                        $ownCaseNumbers[] = $parsed;
                    }
                }
                // Track ALL mentions
                $allMentioned[] = $ref['value'];
            }

            // KLASA in header
            foreach ($results['klasa'] ?? [] as $ref) {
                if (($ref['position'] ?? 999) < 500) {
                    $ownKlasa = $ref['value'];
                }
            }

            // URBROJ in header
            foreach ($results['urbroj'] ?? [] as $ref) {
                if (($ref['position'] ?? 999) < 500) {
                    $ownUrbroj = $ref['value'];
                }
            }

            // Broj in header
            foreach ($results['broj'] ?? [] as $ref) {
                if (($ref['position'] ?? 999) < 500) {
                    $ownBroj = $ref['value'];
                    $ownBrojType = $ref['sub_type'] ?? null;
                }
            }

            // KLASA/URBROJ pairs
            $pairs = $results['klasa_urbroj_pairs'] ?? [];
            if (!$ownKlasa && !empty($pairs)) {
                $ownKlasa = array_key_first($pairs);
                $ownUrbroj = $ownUrbroj ?? $pairs[$ownKlasa] ?? null;
            }

            // Find document date from DateContextExtractor
            $docDate = $this->findDocumentDate($docId, $caseId);

            // Create identity for each own case number
            if (!empty($ownCaseNumbers)) {
                foreach ($ownCaseNumbers as $parsed) {
                    $key = $parsed['full'];
                    $urbrojParsed = $ownUrbroj ? DocumentIdentity::parseUrbroj($ownUrbroj) : null;

                    $identities[$key] = [
                        'case_id' => $caseId,
                        'case_document_id' => $docId,
                        'case_number' => $parsed['case_number'],
                        'case_prefix' => $parsed['prefix'],
                        'case_seq_number' => $parsed['seq_number'],
                        'case_year' => $parsed['year'],
                        'case_suffix' => $parsed['suffix'],
                        'case_number_full' => $parsed['full'],
                        'klasa' => $ownKlasa,
                        'urbroj' => $ownUrbroj,
                        'urbroj_institution_code' => $urbrojParsed['institution_code'] ?? null,
                        'urbroj_suffix' => $urbrojParsed['suffix'] ?? null,
                        'broj' => $ownBroj,
                        'broj_type' => $ownBrojType,
                        'document_date' => $docDate,
                        'document_type' => null, // Set by AI layer later
                        'issuing_institution' => $this->inferInstitution($urbrojParsed['institution_code'] ?? null),
                        'metacase_role' => $this->inferRole($parsed['prefix']),
                        'presence_status' => DocumentIdentity::STATUS_PRESENT,
                        'discovery_source' => DocumentIdentity::SOURCE_EXTRACTION,
                        'referenced_in_documents' => [$docId],
                        'reference_count' => 1,
                    ];
                }
            } elseif ($ownKlasa || $ownUrbroj || $ownBroj) {
                // Document has KLASA/URBROJ but no case number in header
                // Use KLASA or URBROJ as the key
                $key = $ownKlasa ?? $ownUrbroj ?? $ownBroj;
                $urbrojParsed = $ownUrbroj ? DocumentIdentity::parseUrbroj($ownUrbroj) : null;

                $identities["admin:{$key}"] = [
                    'case_id' => $caseId,
                    'case_document_id' => $docId,
                    'case_number' => null,
                    'case_prefix' => null,
                    'case_seq_number' => null,
                    'case_year' => null,
                    'case_suffix' => null,
                    'case_number_full' => null,
                    'klasa' => $ownKlasa,
                    'urbroj' => $ownUrbroj,
                    'urbroj_institution_code' => $urbrojParsed['institution_code'] ?? null,
                    'urbroj_suffix' => $urbrojParsed['suffix'] ?? null,
                    'broj' => $ownBroj,
                    'broj_type' => $ownBrojType,
                    'document_date' => $docDate,
                    'document_type' => null,
                    'issuing_institution' => $this->inferInstitution($urbrojParsed['institution_code'] ?? null),
                    'metacase_role' => null,
                    'presence_status' => DocumentIdentity::STATUS_PRESENT,
                    'discovery_source' => DocumentIdentity::SOURCE_EXTRACTION,
                    'referenced_in_documents' => [$docId],
                    'reference_count' => 1,
                ];
            }
        }

        // Phase 3: Track where each case number is mentioned (cross-referencing)
        foreach ($analyses as $analysis) {
            $docId = $analysis->case_document_id;
            $results = $analysis->results;

            foreach ($results['case_numbers'] ?? [] as $ref) {
                $value = $ref['value'];
                if (isset($identities[$value])) {
                    $refs = $identities[$value]['referenced_in_documents'];
                    if (!in_array($docId, $refs)) {
                        $identities[$value]['referenced_in_documents'][] = $docId;
                        $identities[$value]['reference_count']++;
                    }
                }
            }
        }

        // Phase 4: Detect suffix sequence gaps → infer MISSING documents
        $missingInferred = $this->inferMissingFromSuffixGaps($identities, $caseId);

        // Phase 5: Detect referenced-but-not-present documents
        $missingReferenced = $this->inferMissingFromReferences($identities, $allMentioned, $caseId);

        $allIdentities = array_merge(
            array_values($identities),
            $missingInferred,
            $missingReferenced
        );

        // Phase 6: Persist
        DB::table('document_identities')->where('case_id', $caseId)->delete();
        foreach ($allIdentities as $row) {
            $row['referenced_in_documents'] = json_encode($row['referenced_in_documents'] ?? []);
            $row['created_at'] = now();
            $row['updated_at'] = now();
            DB::table('document_identities')->insert($row);
        }

        // Phase 7: Build summary
        $present = array_filter($allIdentities, fn($i) => $i['presence_status'] === 'present');
        $missing = array_filter($allIdentities, fn($i) => $i['presence_status'] === 'missing');

        // Group by case number (without suffix)
        $byCaseNumber = [];
        foreach ($allIdentities as $i) {
            $cn = $i['case_number'] ?? 'bez broja';
            $byCaseNumber[$cn][] = $i;
        }

        return [
            'total_identities' => count($allIdentities),
            'present' => count($present),
            'missing' => count($missing),
            'by_case_number' => $this->buildCaseNumberMatrix($byCaseNumber),
            'by_klasa' => $this->buildKlasaMatrix($allIdentities),
            'processing_time_seconds' => round(microtime(true) - $startTime, 4),
        ];
    }

    /**
     * Detect gaps in suffix sequences.
     *
     * If we have Pp Prz-74/2025-1 and Pp Prz-74/2025-4,
     * then -2 and -3 are MISSING.
     */
    private function inferMissingFromSuffixGaps(array $identities, string $caseId): array
    {
        $missing = [];

        // Group by case_number (without suffix)
        $byCaseNumber = [];
        foreach ($identities as $id) {
            if (!$id['case_number'] || $id['case_suffix'] === null) continue;
            $byCaseNumber[$id['case_number']][] = $id;
        }

        foreach ($byCaseNumber as $caseNumber => $docs) {
            $suffixes = array_map(fn($d) => $d['case_suffix'], $docs);
            sort($suffixes);

            if (empty($suffixes)) continue;

            $maxSuffix = max($suffixes);
            $minSuffix = min(1, min($suffixes)); // Sequences start at 1

            // Find gaps
            for ($s = $minSuffix; $s <= $maxSuffix; $s++) {
                if (!in_array($s, $suffixes)) {
                    // Infer the full case number
                    $firstDoc = $docs[0];
                    $fullNumber = "{$caseNumber}-{$s}";

                    $missing[] = [
                        'case_id' => $caseId,
                        'case_document_id' => null,
                        'case_number' => $caseNumber,
                        'case_prefix' => $firstDoc['case_prefix'],
                        'case_seq_number' => $firstDoc['case_seq_number'],
                        'case_year' => $firstDoc['case_year'],
                        'case_suffix' => $s,
                        'case_number_full' => $fullNumber,
                        'klasa' => $firstDoc['klasa'], // Same KLASA likely
                        'urbroj' => null,
                        'urbroj_institution_code' => $firstDoc['urbroj_institution_code'],
                        'urbroj_suffix' => null,
                        'broj' => null,
                        'broj_type' => null,
                        'document_date' => null,
                        'document_type' => $this->guessDocTypeFromSuffix($s, $firstDoc['case_prefix']),
                        'issuing_institution' => $firstDoc['issuing_institution'],
                        'metacase_role' => $firstDoc['metacase_role'],
                        'presence_status' => DocumentIdentity::STATUS_MISSING,
                        'discovery_source' => DocumentIdentity::SOURCE_INFERENCE,
                        'referenced_in_documents' => [],
                        'reference_count' => 0,
                        'notes' => "Zaključeno iz praznine u sekvenci: imamo sufiks "
                            . implode(', ', $suffixes) . " ali nedostaje -{$s}",
                    ];
                }
            }
        }

        return $missing;
    }

    /**
     * Detect case numbers referenced in body text but not present as any document's own header.
     */
    private function inferMissingFromReferences(array $identities, array $allMentioned, string $caseId): array
    {
        $missing = [];
        $presentKeys = array_keys($identities);

        $mentionedUnique = array_unique($allMentioned);

        foreach ($mentionedUnique as $mentioned) {
            // Skip if already present
            if (isset($identities[$mentioned])) continue;

            $parsed = DocumentIdentity::parseCaseNumberFull($mentioned);
            if (!$parsed) continue;

            // Skip if same case_number exists (just different suffix)
            $caseNumberExists = false;
            foreach ($identities as $id) {
                if ($id['case_number'] === $parsed['case_number']) {
                    $caseNumberExists = true;
                    break;
                }
            }

            // Only add if the case number itself is completely absent
            // (suffix gaps are handled by inferMissingFromSuffixGaps)
            if (!$caseNumberExists) {
                $missing[] = [
                    'case_id' => $caseId,
                    'case_document_id' => null,
                    'case_number' => $parsed['case_number'],
                    'case_prefix' => $parsed['prefix'],
                    'case_seq_number' => $parsed['seq_number'],
                    'case_year' => $parsed['year'],
                    'case_suffix' => $parsed['suffix'],
                    'case_number_full' => $parsed['full'],
                    'klasa' => null,
                    'urbroj' => null,
                    'urbroj_institution_code' => null,
                    'urbroj_suffix' => null,
                    'broj' => null,
                    'broj_type' => null,
                    'document_date' => null,
                    'document_type' => null,
                    'issuing_institution' => null,
                    'metacase_role' => $this->inferRole($parsed['prefix']),
                    'presence_status' => DocumentIdentity::STATUS_MISSING,
                    'discovery_source' => DocumentIdentity::SOURCE_INFERENCE,
                    'referenced_in_documents' => [],
                    'reference_count' => 0,
                    'notes' => "Spominje se u drugim dokumentima ali nema izvornog dokumenta u spisu.",
                ];
            }
        }

        return $missing;
    }

    /**
     * Guess what type of document a suffix number represents.
     * This is heuristic based on typical Croatian court filing patterns.
     */
    private function guessDocTypeFromSuffix(int $suffix, ?string $prefix): ?string
    {
        if ($prefix === 'Pp Prz') {
            return match ($suffix) {
                1 => 'prijedlog za pretragu (tužiteljstvo)',
                2 => 'rješenje o pretrazi (sud)',
                3 => 'potvrda o izvršenju pretrage',
                4 => 'žalba ili dopuna',
                default => null,
            };
        }

        if ($prefix === 'Kv') {
            return match ($suffix) {
                1 => 'prijedlog za pritvor (tužiteljstvo)',
                2 => 'rješenje o pritvoru (sud)',
                3 => 'žalba obrane',
                4 => 'odluka o žalbi',
                default => null,
            };
        }

        return match ($suffix) {
            1 => 'inicijalni akt',
            2 => 'odluka',
            default => null,
        };
    }

    private function inferInstitution(?string $code): ?string
    {
        if (!$code) return null;

        return match ($code) {
            '511' => 'MUP — Policija',
            '2158' => 'Sud (Osijek područje)',
            '2168' => 'Sud (Zagreb područje)',
            '2170' => 'Sud (Split područje)',
            '2181' => 'Sud (Rijeka područje)',
            default => "Institucija (kod: {$code})",
        };
    }

    private function inferRole(?string $prefix): ?string
    {
        if (!$prefix) return null;

        return match (preg_replace('/\s+/', ' ', $prefix)) {
            'K' => 'main_criminal',
            'KO' => 'main_criminal',
            'Pp Prz' => 'search_warrant',
            'Kv' => 'detention',
            'Kv II' => 'detention_appeal',
            'Kis' => 'investigative_action',
            'KIR' => 'investigation_opening',
            'Kž' => 'appeal',
            'KP', 'KP-DO' => 'prosecution',
            'DO' => 'prosecution_case',
            default => 'related',
        };
    }

    private function findDocumentDate(int $docId, string $caseId): ?string
    {
        $dateAnalysis = DocumentAnalysis::where('case_document_id', $docId)
            ->where('analysis_type', 'dates_with_context')
            ->where('status', DocumentAnalysis::STATUS_COMPLETED)
            ->first();

        if (!$dateAnalysis) return null;

        // Take the first date found (usually in header = document date)
        $dates = $dateAnalysis->results['dates'] ?? [];
        foreach ($dates as $d) {
            if (($d['position'] ?? 999) < 500 && !empty($d['date'])) {
                return $d['date'];
            }
        }

        return $dates[0]['date'] ?? null;
    }

    private function buildCaseNumberMatrix(array $byCaseNumber): array
    {
        $matrix = [];

        foreach ($byCaseNumber as $caseNumber => $docs) {
            $suffixes = [];
            foreach ($docs as $d) {
                $s = $d['case_suffix'] ?? 0;
                $suffixes[$s] = [
                    'status' => $d['presence_status'],
                    'klasa' => $d['klasa'],
                    'urbroj' => $d['urbroj'],
                    'date' => $d['document_date'],
                    'type' => $d['document_type'],
                    'doc_id' => $d['case_document_id'],
                ];
            }

            ksort($suffixes);

            $matrix[$caseNumber] = [
                'prefix' => $docs[0]['case_prefix'] ?? null,
                'role' => $docs[0]['metacase_role'] ?? null,
                'institution' => $docs[0]['issuing_institution'] ?? null,
                'total_documents' => count($suffixes),
                'present' => count(array_filter($suffixes, fn($s) => $s['status'] === 'present')),
                'missing' => count(array_filter($suffixes, fn($s) => $s['status'] === 'missing')),
                'suffixes' => $suffixes,
            ];
        }

        return $matrix;
    }

    private function buildKlasaMatrix(array $allIdentities): array
    {
        $byKlasa = [];
        foreach ($allIdentities as $i) {
            if (!$i['klasa']) continue;
            $byKlasa[$i['klasa']][] = [
                'case_number_full' => $i['case_number_full'],
                'urbroj' => $i['urbroj'],
                'status' => $i['presence_status'],
                'date' => $i['document_date'],
            ];
        }
        return $byKlasa;
    }
}
```

---

### Task 40: CaseFileCompletenessView — Livewire component

**Files:**
- Create: `app/Livewire/CaseFileCompleteness.php`
- Create: `resources/views/livewire/case-file-completeness.blade.php`

**Step 1: Livewire component**

```php
<?php

namespace App\Livewire;

use App\Models\DocumentIdentity;
use Livewire\Component;

class CaseFileCompleteness extends Component
{
    public string $caseId;
    public string $viewMode = 'matrix'; // 'matrix', 'timeline', 'list'
    public string $filterStatus = 'all'; // 'all', 'present', 'missing'

    public function mount(string $caseId): void
    {
        $this->caseId = $caseId;
    }

    public function render()
    {
        $identities = DocumentIdentity::where('case_id', $this->caseId)
            ->when($this->filterStatus !== 'all', function ($q) {
                $q->where('presence_status', $this->filterStatus);
            })
            ->orderBy('case_number')
            ->orderBy('case_suffix')
            ->get();

        // Group by case_number for matrix view
        $matrix = $identities->groupBy('case_number')->map(function ($group, $caseNumber) {
            $sorted = $group->sortBy('case_suffix');
            $maxSuffix = $sorted->max('case_suffix') ?? 0;

            return [
                'case_number' => $caseNumber,
                'prefix' => $group->first()->case_prefix,
                'role' => $group->first()->metacase_role,
                'role_label' => $this->roleLabel($group->first()->metacase_role),
                'institution' => $group->first()->issuing_institution,
                'max_suffix' => $maxSuffix,
                'documents' => $sorted->keyBy('case_suffix'),
                'present_count' => $group->where('presence_status', 'present')->count(),
                'missing_count' => $group->where('presence_status', 'missing')->count(),
                'total_count' => $group->count(),
                'completeness' => $group->count() > 0
                    ? round(($group->where('presence_status', 'present')->count() / $group->count()) * 100)
                    : 0,
            ];
        })->sortBy(fn($g) => $this->roleSortOrder($g['role']));

        // Group by KLASA for KLASA view
        $byKlasa = $identities->whereNotNull('klasa')
            ->groupBy('klasa')
            ->map(function ($group, $klasa) {
                return [
                    'klasa' => $klasa,
                    'documents' => $group->sortBy('document_date'),
                    'present' => $group->where('presence_status', 'present')->count(),
                    'missing' => $group->where('presence_status', 'missing')->count(),
                ];
            });

        // Summary stats
        $stats = [
            'total' => $identities->count(),
            'present' => $identities->where('presence_status', 'present')->count(),
            'missing' => $identities->where('presence_status', 'missing')->count(),
            'case_numbers' => $identities->whereNotNull('case_number')->pluck('case_number')->unique()->count(),
            'klasa_count' => $identities->whereNotNull('klasa')->pluck('klasa')->unique()->count(),
        ];

        return view('livewire.case-file-completeness', [
            'matrix' => $matrix,
            'byKlasa' => $byKlasa,
            'stats' => $stats,
        ]);
    }

    private function roleLabel(?string $role): string
    {
        return match ($role) {
            'main_criminal' => '⚖️ Glavni predmet',
            'search_warrant' => '🔍 Pretraga',
            'detention' => '🔒 Pritvor',
            'detention_appeal' => '🔒 Žalba na pritvor',
            'investigation_opening' => '📋 Otvaranje istrage',
            'investigative_action' => '📋 Istražne radnje',
            'appeal' => '📤 Žalba',
            'prosecution' => '🏛️ Državno odvjetništvo',
            'prosecution_case' => '🏛️ DO predmet',
            default => '📄 Ostalo',
        };
    }

    private function roleSortOrder(?string $role): int
    {
        return match ($role) {
            'main_criminal' => 0,
            'prosecution', 'prosecution_case' => 1,
            'investigation_opening' => 2,
            'investigative_action' => 3,
            'search_warrant' => 4,
            'detention' => 5,
            'detention_appeal' => 6,
            'appeal' => 7,
            default => 99,
        };
    }
}
```

**Step 2: Blade template — Matrix view**

```blade
<div class="space-y-6">
    {{-- Summary Bar --}}
    <div class="grid grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
            <div class="text-2xl font-bold">{{ $stats['total'] }}</div>
            <div class="text-sm text-gray-500">Ukupno dokumenata</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
            <div class="text-2xl font-bold text-green-600">{{ $stats['present'] }}</div>
            <div class="text-sm text-gray-500">Prisutni u spisu</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
            <div class="text-2xl font-bold text-red-600">{{ $stats['missing'] }}</div>
            <div class="text-sm text-gray-500">Nedostaju</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
            <div class="text-2xl font-bold">{{ $stats['case_numbers'] }}</div>
            <div class="text-sm text-gray-500">Predmeta / pod-predmeta</div>
        </div>
    </div>

    {{-- View Toggle --}}
    <div class="flex gap-2">
        <button wire:click="$set('viewMode', 'matrix')"
                class="px-4 py-2 rounded {{ $viewMode === 'matrix' ? 'bg-blue-600 text-white' : 'bg-gray-200' }}">
            Matrica
        </button>
        <button wire:click="$set('viewMode', 'list')"
                class="px-4 py-2 rounded {{ $viewMode === 'list' ? 'bg-blue-600 text-white' : 'bg-gray-200' }}">
            Lista
        </button>
        <button wire:click="$set('viewMode', 'klasa')"
                class="px-4 py-2 rounded {{ $viewMode === 'klasa' ? 'bg-blue-600 text-white' : 'bg-gray-200' }}">
            Po KLASA
        </button>

        <div class="ml-auto flex gap-2">
            <button wire:click="$set('filterStatus', 'all')"
                    class="px-3 py-1 text-sm rounded {{ $filterStatus === 'all' ? 'bg-gray-800 text-white' : 'bg-gray-200' }}">
                Svi
            </button>
            <button wire:click="$set('filterStatus', 'missing')"
                    class="px-3 py-1 text-sm rounded {{ $filterStatus === 'missing' ? 'bg-red-600 text-white' : 'bg-gray-200' }}">
                Samo nedostajući
            </button>
        </div>
    </div>

    {{-- MATRIX VIEW --}}
    @if ($viewMode === 'matrix')
    <div class="space-y-4">
        @foreach ($matrix as $group)
        <div class="bg-white rounded-lg shadow overflow-hidden">
            {{-- Case Number Header --}}
            <div class="px-4 py-3 border-b flex items-center justify-between
                {{ $group['missing_count'] > 0 ? 'bg-red-50' : 'bg-green-50' }}">
                <div>
                    <span class="font-mono font-bold text-lg">{{ $group['case_number'] ?? 'Bez broja predmeta' }}</span>
                    <span class="ml-2 text-sm">{{ $group['role_label'] }}</span>
                    @if ($group['institution'])
                        <span class="ml-2 text-sm text-gray-500">— {{ $group['institution'] }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-sm">
                        <span class="text-green-600 font-medium">{{ $group['present_count'] }}</span> /
                        <span class="text-gray-600">{{ $group['total_count'] }}</span>
                    </div>
                    <div class="w-24 bg-gray-200 rounded-full h-2">
                        <div class="h-2 rounded-full {{ $group['completeness'] === 100 ? 'bg-green-500' : 'bg-yellow-500' }}"
                             style="width: {{ $group['completeness'] }}%"></div>
                    </div>
                    <span class="text-sm font-medium">{{ $group['completeness'] }}%</span>
                </div>
            </div>

            {{-- Document Suffix Grid --}}
            <div class="px-4 py-3">
                <div class="flex flex-wrap gap-2">
                    @for ($s = 1; $s <= max($group['max_suffix'], 1); $s++)
                        @php
                            $doc = $group['documents'][$s] ?? null;
                            $isPresent = $doc && $doc->presence_status === 'present';
                            $isMissing = $doc && $doc->presence_status === 'missing';
                        @endphp

                        <div class="relative group">
                            <div class="w-16 h-16 rounded-lg border-2 flex flex-col items-center justify-center cursor-pointer
                                {{ $isPresent ? 'border-green-400 bg-green-50 hover:bg-green-100' : '' }}
                                {{ $isMissing ? 'border-red-400 bg-red-50 hover:bg-red-100 border-dashed' : '' }}
                                {{ !$doc ? 'border-gray-200 bg-gray-50' : '' }}">

                                <span class="font-mono font-bold text-sm">-{{ $s }}</span>

                                @if ($isPresent)
                                    <span class="text-green-600 text-xs">✓</span>
                                @elseif ($isMissing)
                                    <span class="text-red-600 text-xs">✗</span>
                                @endif
                            </div>

                            {{-- Hover tooltip --}}
                            @if ($doc)
                            <div class="absolute z-10 bottom-full left-1/2 -translate-x-1/2 mb-2
                                        hidden group-hover:block w-72 p-3 bg-gray-900 text-white text-xs rounded-lg shadow-xl">
                                <div class="font-bold mb-1">
                                    {{ $group['case_number'] }}-{{ $s }}
                                </div>

                                @if ($doc->klasa)
                                    <div>KLASA: {{ $doc->klasa }}</div>
                                @endif
                                @if ($doc->urbroj)
                                    <div>URBROJ: {{ $doc->urbroj }}</div>
                                @endif
                                @if ($doc->document_date)
                                    <div>Datum: {{ $doc->document_date->format('d.m.Y.') }}</div>
                                @endif
                                @if ($doc->document_type)
                                    <div>Tip: {{ $doc->document_type }}</div>
                                @endif

                                @if ($isMissing)
                                    <div class="mt-1 text-red-300 font-medium">
                                        ⚠ {{ $doc->notes ?? 'Dokument nedostaje u spisu' }}
                                    </div>
                                @endif

                                @if ($doc->reference_count > 0)
                                    <div class="mt-1 text-gray-400">
                                        Referenciran u {{ $doc->reference_count }} dokumentu/a
                                    </div>
                                @endif
                            </div>
                            @endif
                        </div>
                    @endfor
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- KLASA VIEW --}}
    @if ($viewMode === 'klasa')
    <div class="space-y-3">
        @foreach ($byKlasa as $klasaGroup)
        <div class="bg-white rounded-lg shadow p-4">
            <div class="font-mono font-bold mb-2">{{ $klasaGroup['klasa'] }}</div>
            <div class="flex gap-2 flex-wrap">
                @foreach ($klasaGroup['documents'] as $doc)
                    <div class="px-3 py-2 rounded border text-sm
                        {{ $doc->presence_status === 'present' ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50 border-dashed' }}">
                        <div class="font-mono text-xs">{{ $doc->urbroj ?? '?' }}</div>
                        <div class="text-xs text-gray-500">{{ $doc->case_number_full ?? '-' }}</div>
                        @if ($doc->document_date)
                            <div class="text-xs text-gray-400">{{ $doc->document_date->format('d.m.Y.') }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- LIST VIEW --}}
    @if ($viewMode === 'list')
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left">Status</th>
                    <th class="px-3 py-2 text-left">Broj predmeta</th>
                    <th class="px-3 py-2 text-left">KLASA</th>
                    <th class="px-3 py-2 text-left">URBROJ</th>
                    <th class="px-3 py-2 text-left">Datum</th>
                    <th class="px-3 py-2 text-left">Tip</th>
                    <th class="px-3 py-2 text-left">Institucija</th>
                    <th class="px-3 py-2 text-left">Izvor</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($matrix as $group)
                    @foreach ($group['documents'] as $suffix => $doc)
                    <tr class="border-t {{ $doc->presence_status === 'missing' ? 'bg-red-50' : 'hover:bg-gray-50' }}">
                        <td class="px-3 py-2">
                            @if ($doc->presence_status === 'present')
                                <span class="inline-block w-3 h-3 bg-green-500 rounded-full" title="Prisutan"></span>
                            @else
                                <span class="inline-block w-3 h-3 bg-red-500 rounded-full" title="Nedostaje"></span>
                            @endif
                        </td>
                        <td class="px-3 py-2 font-mono">{{ $doc->case_number_full ?? '-' }}</td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $doc->klasa ?? '-' }}</td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $doc->urbroj ?? '-' }}</td>
                        <td class="px-3 py-2">{{ $doc->document_date?->format('d.m.Y.') ?? '-' }}</td>
                        <td class="px-3 py-2 text-xs">{{ $doc->document_type ?? '-' }}</td>
                        <td class="px-3 py-2 text-xs">{{ $doc->issuing_institution ?? '-' }}</td>
                        <td class="px-3 py-2 text-xs text-gray-400">{{ $doc->discovery_source }}</td>
                    </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
```

---

### Task 41: Artisan command for document identity report

**Files:**
- Create: `app/Console/Commands/CaseFileCompletenessCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Services\Analysis\CaseLevel\DocumentIdentityBuilder;
use Illuminate\Console\Command;

class CaseFileCompletenessCommand extends Command
{
    protected $signature = 'case:completeness {case_id} {--rebuild} {--json}';
    protected $description = 'Show case file completeness — which documents are present vs missing';

    public function handle(DocumentIdentityBuilder $builder): int
    {
        $caseId = $this->argument('case_id');

        if ($this->option('rebuild')) {
            $this->info('Rebuilding document identity matrix...');
        }

        $result = $builder->build($caseId);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return 0;
        }

        // Human-readable output
        $this->newLine();
        $this->info("📁 Completeness report: {$caseId}");
        $this->info("   Present: {$result['present']}  |  Missing: {$result['missing']}  |  Total: {$result['total_identities']}");
        $this->newLine();

        foreach ($result['by_case_number'] as $cn => $group) {
            $bar = '';
            foreach ($group['suffixes'] as $s => $info) {
                $bar .= $info['status'] === 'present' ? '🟩' : '🟥';
            }

            $completeness = $group['present'] . '/' . $group['total_documents'];
            $prefix = str_pad($cn, 25);
            $role = str_pad($group['role'] ?? '', 20);

            $this->line("  {$prefix} {$role} {$bar}  ({$completeness})");

            // Show missing details
            foreach ($group['suffixes'] as $s => $info) {
                if ($info['status'] === 'missing') {
                    $type = $info['type'] ?? 'nepoznato';
                    $this->warn("    ⚠ -{$s} nedostaje — mogući tip: {$type}");
                }
            }
        }

        $this->newLine();
        $this->info("Done in {$result['processing_time_seconds']}s");

        return 0;
    }
}
```

**Example output:**
```
📁 Completeness report: case-2025-001
   Present: 8  |  Missing: 3  |  Total: 11

  K-123/2025                main_criminal        🟩🟩🟩  (3/3)
  Pp Prz-74/2025            search_warrant       🟩🟥🟩  (2/3)
    ⚠ -2 nedostaje — mogući tip: rješenje o pretrazi (sud)
  Kv-89/2025                detention            🟩🟩🟥🟥 (2/4)
    ⚠ -3 nedostaje — mogući tip: žalba obrane
    ⚠ -4 nedostaje — mogući tip: odluka o žalbi
  KP-DO-321/2025            prosecution          🟩🟩  (2/2)
```

---

## Feature 2: Vendor-Independent AI CLI Agent

### The Problem

The current ClaudeCodeAgent (Task 25) is hardcoded to the `claude` binary. But the landscape of CLI-based AI agents is expanding:

| Agent | Binary | Key Flags | Strengths |
|-------|--------|-----------|-----------|
| Claude Code | `claude` | `--print`, `--max-turns`, `--allowedTools` | Best reasoning, native tool use |
| Gemini CLI | `gemini` | `--model`, `--prompt` | Google ecosystem, large context |
| Qwen CLI | `qwen` | Various | Open-weight, self-hostable |
| Amp CLI | `amp` | TBD | Multi-model orchestration |
| Aider | `aider` | `--yes`, `--message` | Git-aware, code-focused |
| Codex CLI | `codex` | `--model`, `--prompt` | OpenAI models |

The abstraction should:
1. Define a common interface for all agents
2. Handle each agent's specific CLI flags, output format, and error handling
3. Be selectable via config (`AI_AGENT_DRIVER=claude`)
4. Allow fallback chains (`try claude, if unavailable use gemini`)
5. Normalize output to a common JSON structure

---

### Task 42: AgentInterface + AgentResult DTO

**Files:**
- Create: `app/Services/Agent/Contracts/AgentInterface.php`
- Create: `app/Services/Agent/Contracts/AgentCapability.php`
- Create: `app/DTOs/Agent/AgentResult.php`
- Create: `app/DTOs/Agent/AgentSession.php`

```php
<?php

namespace App\Services\Agent\Contracts;

use App\DTOs\Agent\AgentResult;
use App\DTOs\Agent\AgentSession;

interface AgentInterface
{
    /**
     * Unique driver identifier.
     */
    public function driver(): string;

    /**
     * Human-readable name.
     */
    public function name(): string;

    /**
     * Check if the agent binary is available on this system.
     */
    public function isAvailable(): bool;

    /**
     * What capabilities this agent supports.
     *
     * @return AgentCapability[]
     */
    public function capabilities(): array;

    /**
     * Run a prompt with file context.
     *
     * @param string   $prompt     The task description
     * @param string[] $filePaths  Files to make available
     * @param array    $options    Driver-specific options
     */
    public function run(string $prompt, array $filePaths = [], array $options = []): AgentResult;

    /**
     * Run a multi-phase analysis (per-document → cross-document).
     * Default implementation calls run() twice; drivers can override.
     */
    public function bulkAnalysis(string $caseId, array $filePaths, array $phases): array;
}
```

```php
<?php

namespace App\Services\Agent\Contracts;

enum AgentCapability: string
{
    case FILE_READ = 'file_read';       // Can read files from disk
    case FILE_WRITE = 'file_write';     // Can write output files
    case BASH = 'bash';                 // Can execute bash commands
    case WEB_SEARCH = 'web_search';     // Can search the web
    case MULTI_TURN = 'multi_turn';     // Supports multi-turn conversation
    case EXTENDED_THINKING = 'extended_thinking'; // Extended reasoning mode
    case STREAMING = 'streaming';        // Streams output
    case JSON_OUTPUT = 'json_output';    // Can output structured JSON
}
```

```php
<?php

namespace App\DTOs\Agent;

class AgentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $driver,           // 'claude', 'gemini', etc.
        public readonly ?array $results,          // Parsed JSON output
        public readonly string $rawOutput,        // Raw stdout
        public readonly string $rawError,         // Raw stderr
        public readonly int $exitCode,
        public readonly float $elapsedSeconds,
        public readonly string $sessionId,
        public readonly ?string $outputFile,      // Path to JSON output file
        public readonly array $metadata = [],     // Driver-specific metadata
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'driver' => $this->driver,
            'results' => $this->results,
            'exit_code' => $this->exitCode,
            'elapsed_seconds' => $this->elapsedSeconds,
            'session_id' => $this->sessionId,
            'output_file' => $this->outputFile,
            'metadata' => $this->metadata,
        ];
    }
}
```

```php
<?php

namespace App\DTOs\Agent;

class AgentSession
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $workDir,
        public readonly string $outputFile,
        public readonly array $symlinkMap,      // original_path → session_path
    ) {}
}
```

---

### Task 43: BaseAgent — shared session management

**Files:**
- Create: `app/Services/Agent/BaseAgent.php`

This handles the common boilerplate: creating session directories, symlinking files, parsing JSON output, cleanup. Each driver only implements the CLI invocation specifics.

```php
<?php

namespace App\Services\Agent;

use App\DTOs\Agent\AgentResult;
use App\DTOs\Agent\AgentSession;
use App\Services\Agent\Contracts\AgentInterface;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

abstract class BaseAgent implements AgentInterface
{
    protected string $workBaseDir;
    protected string $outputBaseDir;
    protected int $timeout;

    public function __construct()
    {
        $this->workBaseDir = config("agents.drivers.{$this->driver()}.work_dir",
            storage_path('app/agent-sessions'));
        $this->outputBaseDir = config("agents.drivers.{$this->driver()}.output_dir",
            storage_path('app/agent-output'));
        $this->timeout = config("agents.drivers.{$this->driver()}.timeout", 600);

        if (!is_dir($this->workBaseDir)) mkdir($this->workBaseDir, 0755, true);
        if (!is_dir($this->outputBaseDir)) mkdir($this->outputBaseDir, 0755, true);
    }

    /**
     * Each driver implements this: build the CLI command array.
     *
     * @param string       $prompt      Fully assembled prompt
     * @param AgentSession $session     Session with workDir, outputFile
     * @param array        $options     Driver-specific options
     * @return array       Command parts for Process::run()
     */
    abstract protected function buildCommand(string $prompt, AgentSession $session, array $options): array;

    /**
     * Each driver implements this: parse raw output into structured results.
     * Some agents write to a file, some output to stdout.
     */
    abstract protected function parseOutput(string $rawOutput, AgentSession $session): ?array;

    /**
     * Get the binary path for this driver.
     */
    abstract protected function binary(): string;

    /**
     * Environment variables for the process.
     */
    protected function environment(array $options): array
    {
        return [];
    }

    public function isAvailable(): bool
    {
        $binary = $this->binary();
        $result = Process::run("which {$binary} 2>/dev/null");
        return $result->exitCode() === 0;
    }

    public function run(string $prompt, array $filePaths = [], array $options = []): AgentResult
    {
        $session = $this->createSession($filePaths);

        // Inject standard context into prompt
        $fullPrompt = $this->assemblePrompt($prompt, $session, $options);

        $command = $this->buildCommand($fullPrompt, $session, $options);

        Log::info("Agent [{$this->driver()}] starting session {$session->sessionId}", [
            'file_count' => count($filePaths),
            'prompt_length' => strlen($fullPrompt),
        ]);

        $startTime = microtime(true);

        $result = Process::timeout($this->timeout)
            ->path($session->workDir)
            ->env($this->environment($options))
            ->run($command);

        $elapsed = round(microtime(true) - $startTime, 2);

        $rawOutput = $result->output();
        $rawError = $result->errorOutput();
        $exitCode = $result->exitCode();

        // Try to parse output
        $parsed = null;
        if ($exitCode === 0) {
            // First try: agent wrote a JSON file
            if (file_exists($session->outputFile)) {
                $json = file_get_contents($session->outputFile);
                $parsed = json_decode($json, true);
            }

            // Second try: parse from stdout
            if (!$parsed) {
                $parsed = $this->parseOutput($rawOutput, $session);
            }
        }

        Log::info("Agent [{$this->driver()}] session {$session->sessionId} completed", [
            'exit_code' => $exitCode,
            'elapsed' => $elapsed,
            'output_parsed' => $parsed !== null,
        ]);

        $this->cleanupSession($session);

        return new AgentResult(
            success: $exitCode === 0 && $parsed !== null,
            driver: $this->driver(),
            results: $parsed,
            rawOutput: $rawOutput,
            rawError: $rawError,
            exitCode: $exitCode,
            elapsedSeconds: $elapsed,
            sessionId: $session->sessionId,
            outputFile: file_exists($session->outputFile) ? $session->outputFile : null,
            metadata: [
                'command' => implode(' ', $command),
                'binary' => $this->binary(),
            ],
        );
    }

    public function bulkAnalysis(string $caseId, array $filePaths, array $phases): array
    {
        $results = [];

        foreach ($phases as $phaseName => $phasePrompt) {
            // Inject previous phase results into prompt if available
            $previousResults = collect($results)
                ->filter(fn($r) => $r->success)
                ->map(fn($r) => json_encode($r->results, JSON_UNESCAPED_UNICODE))
                ->implode("\n\n---\n\n");

            $fullPrompt = $phasePrompt;
            if ($previousResults) {
                $fullPrompt .= "\n\nPrevious analysis results:\n{$previousResults}";
            }

            $results[$phaseName] = $this->run($fullPrompt, $filePaths, [
                'case_id' => $caseId,
                'phase' => $phaseName,
            ]);
        }

        return $results;
    }

    // === Session Management ===

    protected function createSession(array $filePaths): AgentSession
    {
        $sessionId = Str::uuid()->toString();
        $workDir = "{$this->workBaseDir}/{$sessionId}";
        $outputFile = "{$this->outputBaseDir}/{$sessionId}.json";

        mkdir($workDir, 0755, true);

        $symlinkMap = [];
        foreach ($filePaths as $path) {
            if (file_exists($path)) {
                $basename = basename($path);
                $target = "{$workDir}/{$basename}";
                symlink($path, $target);
                $symlinkMap[$path] = $target;
            }
        }

        // Also symlink extract_references.sh if available
        $scriptPath = base_path('scripts/extract_references.sh');
        if (file_exists($scriptPath)) {
            symlink($scriptPath, "{$workDir}/extract_references.sh");
        }

        return new AgentSession(
            sessionId: $sessionId,
            workDir: $workDir,
            outputFile: $outputFile,
            symlinkMap: $symlinkMap,
        );
    }

    protected function assemblePrompt(string $prompt, AgentSession $session, array $options): string
    {
        $systemPreamble = config("agents.drivers.{$this->driver()}.system_prompt", '');

        $fileList = array_map('basename', array_keys($session->symlinkMap));
        $fileSection = !empty($fileList)
            ? "\n\nFiles available in working directory:\n" . implode("\n", $fileList)
            : '';

        $outputInstruction = "\n\nWrite your output as JSON to: {$session->outputFile}";

        return $systemPreamble . "\n\n" . $prompt . $fileSection . $outputInstruction;
    }

    protected function cleanupSession(AgentSession $session): void
    {
        $files = glob("{$session->workDir}/*");
        foreach ($files as $file) {
            if (is_link($file)) unlink($file);
        }
        @rmdir($session->workDir);
    }
}
```

---

### Task 44: Driver implementations

**Files:**
- Create: `app/Services/Agent/Drivers/ClaudeCodeDriver.php`
- Create: `app/Services/Agent/Drivers/GeminiCliDriver.php`
- Create: `app/Services/Agent/Drivers/AiderDriver.php`
- Create: `app/Services/Agent/Drivers/GenericCliDriver.php`

**Claude Code Driver:**

```php
<?php

namespace App\Services\Agent\Drivers;

use App\DTOs\Agent\AgentSession;
use App\Services\Agent\BaseAgent;
use App\Services\Agent\Contracts\AgentCapability;

class ClaudeCodeDriver extends BaseAgent
{
    public function driver(): string { return 'claude'; }
    public function name(): string { return 'Claude Code'; }

    public function capabilities(): array
    {
        return [
            AgentCapability::FILE_READ,
            AgentCapability::FILE_WRITE,
            AgentCapability::BASH,
            AgentCapability::MULTI_TURN,
            AgentCapability::EXTENDED_THINKING,
            AgentCapability::JSON_OUTPUT,
        ];
    }

    protected function binary(): string
    {
        return config('agents.drivers.claude.binary', 'claude');
    }

    protected function buildCommand(string $prompt, AgentSession $session, array $options): array
    {
        $cmd = [
            $this->binary(),
            '--print',
            '--output-format', 'json',
            '--max-turns', (string)config('agents.drivers.claude.max_turns', 25),
        ];

        $allowedTools = config('agents.drivers.claude.allowed_tools', ['bash', 'file_read', 'file_write']);
        if (!empty($allowedTools)) {
            $cmd[] = '--allowedTools';
            $cmd[] = implode(',', $allowedTools);
        }

        $model = config('agents.drivers.claude.model');
        if ($model) {
            $cmd[] = '--model';
            $cmd[] = $model;
        }

        $cmd[] = '-p';
        $cmd[] = $prompt;

        return $cmd;
    }

    protected function environment(array $options): array
    {
        $env = [];
        $apiKey = config('agents.drivers.claude.api_key');
        if ($apiKey) {
            $env['ANTHROPIC_API_KEY'] = $apiKey;
        }
        return $env;
    }

    protected function parseOutput(string $rawOutput, AgentSession $session): ?array
    {
        // Claude Code --output-format json wraps response in JSON
        $decoded = json_decode($rawOutput, true);
        if ($decoded) {
            // Extract text content from Claude's response format
            $text = collect($decoded['content'] ?? [$decoded])
                ->where('type', 'text')
                ->pluck('text')
                ->implode("\n");

            // Try to find JSON within the text
            return $this->extractJson($text);
        }

        return $this->extractJson($rawOutput);
    }

    private function extractJson(string $text): ?array
    {
        // Try full text as JSON
        $decoded = json_decode($text, true);
        if ($decoded) return $decoded;

        // Try extracting JSON from markdown code blocks
        if (preg_match('/```(?:json)?\s*\n(.*?)\n```/s', $text, $m)) {
            $decoded = json_decode($m[1], true);
            if ($decoded) return $decoded;
        }

        // Try finding JSON object/array in text
        if (preg_match('/(\{[\s\S]*\}|\[[\s\S]*\])/', $text, $m)) {
            $decoded = json_decode($m[1], true);
            if ($decoded) return $decoded;
        }

        return null;
    }
}
```

**Gemini CLI Driver:**

```php
<?php

namespace App\Services\Agent\Drivers;

use App\DTOs\Agent\AgentSession;
use App\Services\Agent\BaseAgent;
use App\Services\Agent\Contracts\AgentCapability;

class GeminiCliDriver extends BaseAgent
{
    public function driver(): string { return 'gemini'; }
    public function name(): string { return 'Gemini CLI'; }

    public function capabilities(): array
    {
        return [
            AgentCapability::FILE_READ,
            AgentCapability::FILE_WRITE,
            AgentCapability::BASH,
            AgentCapability::WEB_SEARCH,
            AgentCapability::JSON_OUTPUT,
        ];
    }

    protected function binary(): string
    {
        return config('agents.drivers.gemini.binary', 'gemini');
    }

    protected function buildCommand(string $prompt, AgentSession $session, array $options): array
    {
        $cmd = [$this->binary()];

        $model = config('agents.drivers.gemini.model');
        if ($model) {
            $cmd[] = '--model';
            $cmd[] = $model;
        }

        // Gemini CLI uses --prompt for non-interactive mode
        $cmd[] = '--prompt';
        $cmd[] = $prompt;

        // If sandbox mode is available
        if (config('agents.drivers.gemini.sandbox', false)) {
            $cmd[] = '--sandbox';
        }

        return $cmd;
    }

    protected function environment(array $options): array
    {
        $env = [];
        $apiKey = config('agents.drivers.gemini.api_key');
        if ($apiKey) {
            $env['GOOGLE_API_KEY'] = $apiKey;
        }
        return $env;
    }

    protected function parseOutput(string $rawOutput, AgentSession $session): ?array
    {
        // Gemini CLI output format — adapt based on actual CLI behavior
        $decoded = json_decode($rawOutput, true);
        if ($decoded) return $decoded;

        // Fallback: search for JSON in output
        if (preg_match('/(\{[\s\S]*\})/s', $rawOutput, $m)) {
            return json_decode($m[1], true);
        }

        return null;
    }
}
```

**Generic CLI Driver (catch-all for any agent):**

```php
<?php

namespace App\Services\Agent\Drivers;

use App\DTOs\Agent\AgentSession;
use App\Services\Agent\BaseAgent;
use App\Services\Agent\Contracts\AgentCapability;

class GenericCliDriver extends BaseAgent
{
    /**
     * Generic driver that can wrap ANY CLI agent binary
     * by configuring the command template in config.
     *
     * Config example for Amp:
     *   'amp' => [
     *       'binary' => 'amp',
     *       'command_template' => '{binary} --prompt {prompt_file} --output {output_file}',
     *       'capabilities' => ['file_read', 'file_write', 'bash'],
     *   ]
     *
     * Config example for Qwen:
     *   'qwen' => [
     *       'binary' => 'qwen-agent',
     *       'command_template' => '{binary} run --input {prompt_file} --output-json {output_file}',
     *       'capabilities' => ['file_read', 'bash'],
     *       'env' => ['DASHSCOPE_API_KEY' => '...'],
     *   ]
     */

    private string $driverName;

    public function __construct(string $driverName = 'generic')
    {
        $this->driverName = $driverName;
        parent::__construct();
    }

    public function driver(): string { return $this->driverName; }
    public function name(): string { return config("agents.drivers.{$this->driverName}.name", $this->driverName); }

    public function capabilities(): array
    {
        $caps = config("agents.drivers.{$this->driverName}.capabilities", []);
        return array_map(fn($c) => AgentCapability::from($c), $caps);
    }

    protected function binary(): string
    {
        return config("agents.drivers.{$this->driverName}.binary", $this->driverName);
    }

    protected function buildCommand(string $prompt, AgentSession $session, array $options): array
    {
        $template = config("agents.drivers.{$this->driverName}.command_template");

        if (!$template) {
            // Fallback: simple binary + prompt approach
            return [$this->binary(), $prompt];
        }

        // Write prompt to temp file (some CLIs prefer file input over inline)
        $promptFile = "{$session->workDir}/.prompt.txt";
        file_put_contents($promptFile, $prompt);

        // Replace template placeholders
        $command = str_replace(
            ['{binary}', '{prompt}', '{prompt_file}', '{output_file}', '{work_dir}'],
            [$this->binary(), escapeshellarg($prompt), $promptFile, $session->outputFile, $session->workDir],
            $template
        );

        // Split into array for Process::run()
        // This is simplified — production should handle quoting properly
        return explode(' ', $command);
    }

    protected function environment(array $options): array
    {
        return config("agents.drivers.{$this->driverName}.env", []);
    }

    protected function parseOutput(string $rawOutput, AgentSession $session): ?array
    {
        // Generic: try output file first, then stdout
        if (file_exists($session->outputFile)) {
            $decoded = json_decode(file_get_contents($session->outputFile), true);
            if ($decoded) return $decoded;
        }

        // Try extracting JSON from anywhere in stdout
        if (preg_match('/(\{[\s\S]*\}|\[[\s\S]*\])/s', $rawOutput, $m)) {
            $decoded = json_decode($m[1], true);
            if ($decoded) return $decoded;
        }

        return ['raw_text' => $rawOutput];
    }
}
```

---

### Task 45: AgentManager — factory + fallback chain

**Files:**
- Create: `app/Services/Agent/AgentManager.php`
- Create: `config/agents.php`

**Config:**

```php
<?php
// config/agents.php

return [
    // Default driver. Can be overridden per-call.
    'default' => env('AI_AGENT_DRIVER', 'claude'),

    // Fallback chain: if primary is unavailable, try these in order
    'fallback_chain' => explode(',', env('AI_AGENT_FALLBACK', 'claude,gemini')),

    // Global settings
    'work_dir' => storage_path('app/agent-sessions'),
    'output_dir' => storage_path('app/agent-output'),
    'timeout' => env('AI_AGENT_TIMEOUT', 600),

    // System prompt shared across all drivers
    'legal_analysis_prompt' => <<<'PROMPT'
You are a Croatian legal document analyst. You have access to case files on disk.
Your task: {TASK_DESCRIPTION}
Working directory: {WORK_DIR}
Output your results as JSON to: {OUTPUT_FILE}

Rules:
- All analysis must reference specific documents by filename
- Dates must be in ISO format (YYYY-MM-DD)
- Include confidence scores for every assertion
- Write Croatian descriptions, but JSON keys in English
- If you find contradictions, flag them with severity: high/medium/low

You also have access to extract_references.sh — a bash script that extracts
KLASA, URBROJ, case numbers, dates, persons, substances, and more from PDF files.
Run it first: ./extract_references.sh --json document.pdf
PROMPT,

    // Per-driver configuration
    'drivers' => [
        'claude' => [
            'binary' => env('CLAUDE_CODE_BINARY', 'claude'),
            'model' => env('CLAUDE_CODE_MODEL', 'claude-sonnet-4-5-20250929'),
            'api_key' => env('ANTHROPIC_API_KEY'),
            'max_turns' => env('CLAUDE_CODE_MAX_TURNS', 25),
            'allowed_tools' => ['bash', 'file_read', 'file_write'],
            'timeout' => env('CLAUDE_CODE_TIMEOUT', 600),
            'work_dir' => storage_path('app/agent-sessions/claude'),
            'output_dir' => storage_path('app/agent-output/claude'),
            'system_prompt' => '', // Uses global prompt
        ],

        'gemini' => [
            'binary' => env('GEMINI_CLI_BINARY', 'gemini'),
            'model' => env('GEMINI_CLI_MODEL', 'gemini-2.5-pro'),
            'api_key' => env('GOOGLE_API_KEY'),
            'sandbox' => true,
            'timeout' => env('GEMINI_CLI_TIMEOUT', 600),
            'work_dir' => storage_path('app/agent-sessions/gemini'),
            'output_dir' => storage_path('app/agent-output/gemini'),
            'system_prompt' => '',
        ],

        'aider' => [
            'binary' => env('AIDER_BINARY', 'aider'),
            'model' => env('AIDER_MODEL', 'claude-sonnet-4-5-20250929'),
            'command_template' => '{binary} --yes --no-git --message {prompt_file}',
            'capabilities' => ['file_read', 'file_write', 'bash'],
            'timeout' => 300,
            'work_dir' => storage_path('app/agent-sessions/aider'),
            'output_dir' => storage_path('app/agent-output/aider'),
        ],

        // Example: completely custom agent
        'amp' => [
            'name' => 'Amp Agent',
            'binary' => env('AMP_BINARY', 'amp'),
            'command_template' => '{binary} --non-interactive --prompt-file {prompt_file}',
            'capabilities' => ['file_read', 'file_write', 'bash'],
            'env' => [
                'AMP_API_KEY' => env('AMP_API_KEY'),
            ],
            'timeout' => 600,
            'work_dir' => storage_path('app/agent-sessions/amp'),
            'output_dir' => storage_path('app/agent-output/amp'),
        ],

        'qwen' => [
            'name' => 'Qwen Agent',
            'binary' => env('QWEN_BINARY', 'qwen-agent'),
            'command_template' => '{binary} run --input {prompt_file} --output-json {output_file}',
            'capabilities' => ['file_read', 'bash'],
            'env' => [
                'DASHSCOPE_API_KEY' => env('DASHSCOPE_API_KEY'),
            ],
            'timeout' => 600,
            'work_dir' => storage_path('app/agent-sessions/qwen'),
            'output_dir' => storage_path('app/agent-output/qwen'),
        ],
    ],
];
```

**AgentManager:**

```php
<?php

namespace App\Services\Agent;

use App\DTOs\Agent\AgentResult;
use App\Services\Agent\Contracts\AgentInterface;
use App\Services\Agent\Contracts\AgentCapability;
use App\Services\Agent\Drivers\ClaudeCodeDriver;
use App\Services\Agent\Drivers\GeminiCliDriver;
use App\Services\Agent\Drivers\GenericCliDriver;
use Illuminate\Support\Facades\Log;

class AgentManager
{
    /** @var array<string, AgentInterface> */
    private array $drivers = [];

    public function __construct()
    {
        // Register built-in drivers
        $this->register('claude', new ClaudeCodeDriver());
        $this->register('gemini', new GeminiCliDriver());

        // Register any generic drivers from config
        foreach (config('agents.drivers', []) as $name => $driverConfig) {
            if (!isset($this->drivers[$name]) && isset($driverConfig['command_template'])) {
                $this->register($name, new GenericCliDriver($name));
            }
        }
    }

    public function register(string $name, AgentInterface $driver): self
    {
        $this->drivers[$name] = $driver;
        return $this;
    }

    /**
     * Get a specific driver.
     */
    public function driver(?string $name = null): AgentInterface
    {
        $name = $name ?? config('agents.default', 'claude');

        if (!isset($this->drivers[$name])) {
            throw new \RuntimeException("Agent driver [{$name}] not registered.");
        }

        return $this->drivers[$name];
    }

    /**
     * Run with automatic fallback chain.
     * Tries each driver in the fallback chain until one succeeds.
     */
    public function runWithFallback(string $prompt, array $filePaths = [], array $options = []): AgentResult
    {
        $chain = config('agents.fallback_chain', ['claude']);
        $lastResult = null;
        $attempted = [];

        foreach ($chain as $driverName) {
            $driverName = trim($driverName);

            if (!isset($this->drivers[$driverName])) {
                Log::debug("Agent [{$driverName}] not registered, skipping");
                continue;
            }

            $driver = $this->drivers[$driverName];

            if (!$driver->isAvailable()) {
                Log::info("Agent [{$driverName}] binary not available, trying next");
                $attempted[] = $driverName;
                continue;
            }

            Log::info("Agent [{$driverName}] attempting analysis");
            $attempted[] = $driverName;

            $lastResult = $driver->run($prompt, $filePaths, $options);

            if ($lastResult->success) {
                Log::info("Agent [{$driverName}] succeeded");
                return $lastResult;
            }

            Log::warning("Agent [{$driverName}] failed (exit {$lastResult->exitCode}), trying next");
        }

        // All failed — return the last result with extra metadata
        return new AgentResult(
            success: false,
            driver: 'fallback_exhausted',
            results: null,
            rawOutput: $lastResult?->rawOutput ?? '',
            rawError: "All agents in fallback chain failed. Attempted: " . implode(', ', $attempted),
            exitCode: $lastResult?->exitCode ?? 1,
            elapsedSeconds: 0,
            sessionId: 'none',
            outputFile: null,
            metadata: ['attempted_drivers' => $attempted],
        );
    }

    /**
     * Find the best available driver for a required capability set.
     */
    public function bestDriverFor(array $requiredCapabilities): ?AgentInterface
    {
        foreach ($this->drivers as $driver) {
            if (!$driver->isAvailable()) continue;

            $driverCaps = array_map(fn($c) => $c->value, $driver->capabilities());
            $required = array_map(fn($c) => $c instanceof AgentCapability ? $c->value : $c, $requiredCapabilities);

            if (empty(array_diff($required, $driverCaps))) {
                return $driver;
            }
        }

        return null;
    }

    /**
     * List all registered drivers and their availability.
     */
    public function status(): array
    {
        $status = [];
        foreach ($this->drivers as $name => $driver) {
            $status[$name] = [
                'name' => $driver->name(),
                'available' => $driver->isAvailable(),
                'capabilities' => array_map(fn($c) => $c->value, $driver->capabilities()),
            ];
        }
        return $status;
    }
}
```

---

### Task 46: ServiceProvider + Artisan commands

**Files:**
- Create: `app/Providers/AgentServiceProvider.php`
- Create: `app/Console/Commands/AgentStatusCommand.php`
- Modify: `app/Console/Commands/AnalyzeCaseWithClaudeCodeCommand.php` → rename to `AnalyzeCaseWithAgentCommand.php`

**ServiceProvider:**

```php
<?php

namespace App\Providers;

use App\Services\Agent\AgentManager;
use Illuminate\Support\ServiceProvider;

class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AgentManager::class);

        // Bind the default driver for DI convenience
        $this->app->bind(
            \App\Services\Agent\Contracts\AgentInterface::class,
            fn($app) => $app->make(AgentManager::class)->driver()
        );
    }

    public function boot(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/agents.php', 'agents'
        );
    }
}
```

**Status command:**

```php
<?php

namespace App\Console\Commands;

use App\Services\Agent\AgentManager;
use Illuminate\Console\Command;

class AgentStatusCommand extends Command
{
    protected $signature = 'agent:status';
    protected $description = 'Show available AI agent drivers and their status';

    public function handle(AgentManager $manager): int
    {
        $status = $manager->status();
        $default = config('agents.default');
        $chain = config('agents.fallback_chain', []);

        $this->info('AI Agent Drivers:');
        $this->newLine();

        foreach ($status as $name => $info) {
            $available = $info['available'] ? '✅' : '❌';
            $isDefault = $name === $default ? ' (DEFAULT)' : '';
            $inChain = in_array($name, $chain) ? ' [fallback]' : '';
            $caps = implode(', ', $info['capabilities']);

            $this->line("  {$available} {$name}{$isDefault}{$inChain}");
            $this->line("     Name: {$info['name']}");
            $this->line("     Capabilities: {$caps}");
            $this->newLine();
        }

        $this->info('Fallback chain: ' . implode(' → ', $chain));

        return 0;
    }
}
```

**Updated analysis command (vendor-independent):**

```php
<?php

namespace App\Console\Commands;

use App\Services\Agent\AgentManager;
use App\Models\CaseDocument;
use Illuminate\Console\Command;

class AnalyzeCaseWithAgentCommand extends Command
{
    protected $signature = 'case:agent-analyze
        {case_id : The case identifier}
        {--driver= : Specific driver (claude, gemini, amp...)}
        {--fallback : Use fallback chain}
        {--sync : Run synchronously}';

    protected $description = 'Run AI agent analysis on case documents';

    public function handle(AgentManager $manager): int
    {
        $caseId = $this->argument('case_id');
        $driverName = $this->option('driver');

        $documents = CaseDocument::where('case_id', $caseId)->get();
        if ($documents->isEmpty()) {
            $this->error("No documents for case {$caseId}");
            return 1;
        }

        $filePaths = $documents->map(fn($d) => storage_path("app/case-documents/{$d->filename}"))
            ->filter(fn($p) => file_exists($p))
            ->values()
            ->toArray();

        $this->info("Files: " . count($filePaths) . " accessible");

        $prompt = "Analyze all documents in the working directory for Croatian criminal case {$caseId}.";

        if ($this->option('fallback')) {
            $this->info('Using fallback chain...');
            $result = $manager->runWithFallback($prompt, $filePaths, ['case_id' => $caseId]);
        } else {
            $driver = $manager->driver($driverName);
            $this->info("Driver: {$driver->name()} ({$driver->driver()})");

            if (!$driver->isAvailable()) {
                $this->error("Driver [{$driver->driver()}] binary not found!");
                return 1;
            }

            $result = $driver->run($prompt, $filePaths, ['case_id' => $caseId]);
        }

        $status = $result->success ? '✅' : '❌';
        $this->line("{$status} Driver: {$result->driver} | Time: {$result->elapsedSeconds}s | Exit: {$result->exitCode}");

        if ($result->success && $result->results) {
            $this->info('Results written to: ' . ($result->outputFile ?? 'stdout'));
        } else {
            $this->warn($result->rawError ?: 'No output produced');
        }

        return $result->success ? 0 : 1;
    }
}
```

---

## Updated Sprint Roadmap

| Sprint | Tasks | Focus | Cost |
|--------|-------|-------|------|
| Sprint 1 | 1-9 | Foundation + basic extraction | $0 |
| Sprint 1.5 | 21-23 | Unified refs + file registry + enhanced dates | $0 |
| Sprint 2 | 10-11, 24 | Cross-doc patterns + metacase | $0 |
| Sprint 3 | 12-14 | AI per-document (Claude API) | ~$0.01-0.05/doc |
| ~~Sprint 3.5~~ | ~~25-27~~ | ~~Claude Code CLI agent~~ | ~~replaced by Sprint 8~~ |
| Sprint 4 | 15-17 | AI deep analysis | ~$0.10-0.50/case |
| Sprint 5 | 28-31 | Defense Tier A: zastara, time, ne bis in idem | $0 |
| Sprint 5.5 | 32-34 | Defense Tier B: custody, fruit, disclosure | $0 |
| Sprint 6 | 35-36 | Defense Tier C: bias, proportionality, constitutional | ~$0.05-0.15/case |
| Sprint 6.5 | 37 | Defense pipeline integration + CLI | $0 |
| **Sprint 7** | **38-41** | **Document Identity Matrix + completeness UI** | **$0** |
| **Sprint 8** | **42-46** | **Vendor-independent AI agent framework** | **$0** |
| Sprint 9 | 18-20 | UI dashboard + Neo4j sync | $0 |

**Total tasks: 46** | Estimated: 8-10 weeks

---

## .env additions

```bash
# Agent framework
AI_AGENT_DRIVER=claude
AI_AGENT_FALLBACK=claude,gemini
AI_AGENT_TIMEOUT=600

# Claude Code
CLAUDE_CODE_BINARY=/usr/local/bin/claude
CLAUDE_CODE_MODEL=claude-sonnet-4-5-20250929
CLAUDE_CODE_MAX_TURNS=25

# Gemini CLI (optional)
GEMINI_CLI_BINARY=gemini
GEMINI_CLI_MODEL=gemini-2.5-pro
GOOGLE_API_KEY=

# Amp (optional)
AMP_BINARY=amp
AMP_API_KEY=

# Qwen (optional)
QWEN_BINARY=qwen-agent
DASHSCOPE_API_KEY=
```
