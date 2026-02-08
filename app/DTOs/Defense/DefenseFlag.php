<?php

namespace App\DTOs\Defense;

class DefenseFlag
{
    // Severity levels - maps to case impact
    public const SEVERITY_CRITICAL = 'critical';   // Potentially case-dispositive
    public const SEVERITY_HIGH = 'high';            // Strong defense angle
    public const SEVERITY_MEDIUM = 'medium';        // Worth raising
    public const SEVERITY_LOW = 'low';              // Monitor / cumulative
    public const SEVERITY_INFO = 'info';            // Informational

    public function __construct(
        public readonly string $tactic,           // 'zastara', 'ne_bis_in_idem', 'chain_of_custody', etc.
        public readonly string $severity,         // CRITICAL / HIGH / MEDIUM / LOW / INFO
        public readonly string $title,            // Human-readable short title (Croatian)
        public readonly string $description,      // What was detected (Croatian)
        public readonly string $legalBasis,       // "cl. 81. KZ", "cl. 10. st. 2. t. 4. ZKP"
        public readonly ?string $echrBasis,       // "Maresti v. Croatia (2009)", if applicable
        public readonly array $evidence,          // Document IDs + specific data supporting flag
        public readonly string $recommendedAction, // What the defense should do
        public readonly float $confidence,        // 0.0 - 1.0
        public readonly array $metadata = [],     // Detector-specific data
    ) {}

    public function toArray(): array
    {
        return [
            'tactic' => $this->tactic,
            'severity' => $this->severity,
            'title' => $this->title,
            'description' => $this->description,
            'legal_basis' => $this->legalBasis,
            'echr_basis' => $this->echrBasis,
            'evidence' => $this->evidence,
            'recommended_action' => $this->recommendedAction,
            'confidence' => $this->confidence,
            'metadata' => $this->metadata,
        ];
    }
}
