<?php

namespace App\DTOs\Analysis;

class CaseReferenceCollection
{
    /** @var CaseReference[] */
    public array $klasa = [];

    /** @var CaseReference[] */
    public array $urbroj = [];

    /** @var CaseReference[] */
    public array $broj = [];

    /** @var CaseReference[] */
    public array $caseNumbers = [];

    /** @var array<string, string> KLASA -> URBROJ pairings found in same document */
    public array $klasaUrbrojPairs = [];

    public function all(): array
    {
        return array_merge($this->klasa, $this->urbroj, $this->broj, $this->caseNumbers);
    }

    public function uniqueValues(): array
    {
        return [
            'klasa' => array_unique(array_map(fn($r) => $r->value, $this->klasa)),
            'urbroj' => array_unique(array_map(fn($r) => $r->value, $this->urbroj)),
            'broj' => array_unique(array_map(fn($r) => $r->value, $this->broj)),
            'case_numbers' => array_unique(array_map(fn($r) => $r->value, $this->caseNumbers)),
        ];
    }

    public function caseNumbersByType(): array
    {
        $grouped = [];
        foreach ($this->caseNumbers as $ref) {
            $grouped[$ref->subType ?? 'unknown'][] = $ref->value;
        }
        return array_map('array_unique', $grouped);
    }

    public function toArray(): array
    {
        return [
            'klasa' => array_map(fn($r) => $r->toArray(), $this->klasa),
            'urbroj' => array_map(fn($r) => $r->toArray(), $this->urbroj),
            'broj' => array_map(fn($r) => $r->toArray(), $this->broj),
            'case_numbers' => array_map(fn($r) => $r->toArray(), $this->caseNumbers),
            'klasa_urbroj_pairs' => $this->klasaUrbrojPairs,
            'unique_values' => $this->uniqueValues(),
            'case_numbers_by_type' => $this->caseNumbersByType(),
        ];
    }
}
