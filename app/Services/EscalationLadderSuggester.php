<?php

namespace App\Services;

class EscalationLadderSuggester
{
    /**
     * @var array<int, string>
     */
    private array $hierarchy;

    /**
     * @param  array<int, string>|null  $hierarchy
     */
    public function __construct(?array $hierarchy = null)
    {
        $this->hierarchy = $hierarchy ?? config('escalation-ladders.hierarchy', []);
    }

    /**
     * @return array<int, string>
     */
    public function hierarchy(): array
    {
        return $this->hierarchy;
    }

    public function suggestNext(?string $current): ?string
    {
        if ($current === null) {
            return $this->hierarchy[0] ?? null;
        }

        $index = array_search($current, $this->hierarchy, true);

        if ($index === false) {
            return $this->hierarchy[0] ?? null;
        }

        return $this->hierarchy[$index + 1] ?? null;
    }

    public function isTerminal(?string $current): bool
    {
        if ($current === null) {
            return false;
        }

        $index = array_search($current, $this->hierarchy, true);

        return $index !== false && $index === count($this->hierarchy) - 1;
    }
}
