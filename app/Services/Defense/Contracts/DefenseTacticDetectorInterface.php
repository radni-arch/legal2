<?php

namespace App\Services\Defense\Contracts;

use App\DTOs\Defense\DefenseFlag;

interface DefenseTacticDetectorInterface
{
    /** Unique tactic identifier */
    public function tactic(): string;

    /** Human label (Croatian) */
    public function label(): string;

    /** What layer data this detector needs */
    public function requires(): array; // ['case_references', 'dates_with_context', 'metacase', ...]

    /**
     * Run detection against case data.
     *
     * @param string $caseId
     * @param array $analysisData  Pre-loaded results from required analyzers
     * @return DefenseFlag[]
     */
    public function detect(string $caseId, array $analysisData): array;
}
