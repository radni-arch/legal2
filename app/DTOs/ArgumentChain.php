<?php

namespace App\DTOs;

/**
 * Immutable data transfer object representing a chain of legal arguments.
 *
 * Each chain represents a logical progression of arguments that builds
 * to a "killer" conclusion (e.g., "exhaustion impossibility", "fruit of poisoned tree").
 * Chains are associated with specific document profiles.
 */
class ArgumentChain
{
    /**
     * @param string $name Unique identifier for the chain (e.g., 'exhaustion_impossibility')
     * @param string $displayName Human-readable name for display
     * @param array $steps Array of argument steps, each with 'label' and 'argument' keys
     * @param string $killerSummary The knockout conclusion of the chain
     * @param array $profileKeys Profile keys this chain applies to
     */
    public function __construct(
        public readonly string $name,
        public readonly string $displayName,
        public readonly array $steps,
        public readonly string $killerSummary,
        public readonly array $profileKeys,
    ) {}

    /**
     * Create an ArgumentChain from an array (e.g., from config/database).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            displayName: $data['display_name'],
            steps: $data['steps'],
            killerSummary: $data['killer_summary'],
            profileKeys: $data['profile_keys'],
        );
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'display_name' => $this->displayName,
            'steps' => $this->steps,
            'killer_summary' => $this->killerSummary,
            'profile_keys' => $this->profileKeys,
        ];
    }

    /**
     * Check if this chain applies to a given document profile.
     */
    public function appliesToProfile(string $profileKey): bool
    {
        return in_array($profileKey, $this->profileKeys, true);
    }

    /**
     * Format the argument chain for LLM injection.
     *
     * Returns a formatted string suitable for inclusion in an LLM prompt,
     * showing the logical progression from premises to killer conclusion.
     */
    public function formatForLLM(): string
    {
        $lines = [];
        $lines[] = "## LANAC ARGUMENATA: {$this->displayName}";
        $lines[] = "";

        foreach ($this->steps as $index => $step) {
            $stepNumber = $index + 1;
            $lines[] = "**{$step['label']}**: {$step['argument']}";

            if (isset($step['provisions'])) {
                $lines[] = "  Odredbe: " . implode(', ', $step['provisions']);
            }
            if (isset($step['precedent'])) {
                $lines[] = "  Presuda: {$step['precedent']}";
            }
        }

        $lines[] = "";
        $lines[] = "ZAKLJUCAK: {$this->killerSummary}";
        $lines[] = "";

        return implode("\n", $lines);
    }
}
