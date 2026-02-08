<?php

namespace App\DTOs\Analysis;

class CaseReference
{
    public function __construct(
        public readonly string $type,       // 'klasa', 'urbroj', 'broj', 'case_number'
        public readonly string $value,      // Normalized value
        public readonly string $rawMatch,   // Original text as found
        public readonly ?string $subType,   // For case_numbers: 'kazneni', 'prekrsajni', 'dorh', 'gradanski', 'upravni'
        public readonly ?string $context,   // +-150 chars surrounding text
        public readonly int $position,      // Character offset in document
        public readonly int $mentions,      // How many times found (set during dedup)
        public readonly ?string $pairedWith = null, // KLASA <-> URBROJ pairing
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'value' => $this->value,
            'raw_match' => $this->rawMatch,
            'sub_type' => $this->subType,
            'context' => $this->context,
            'position' => $this->position,
            'mentions' => $this->mentions,
            'paired_with' => $this->pairedWith,
        ];
    }
}
