<?php

namespace App\DTOs\LegalArtillery;

/**
 * Immutable data transfer object representing a chain of legal arguments.
 *
 * An argument chain combines multiple arguments for synergistic effect,
 * tracking combined strength, vulnerabilities, and recommendations.
 */
class ArgumentChain
{
    /**
     * @param array<Argument> $arguments The arguments in this chain
     * @param int $combinedStrength Synergized strength rating from 1 to 10
     * @param array<string> $vulnerabilities Potential weak points in the chain
     * @param array<string> $recommendations Suggestions for strengthening the chain
     */
    public function __construct(
        public readonly array $arguments,
        public readonly int $combinedStrength,
        public readonly array $vulnerabilities,
        public readonly array $recommendations,
    ) {}

    /**
     * Create an ArgumentChain from an array.
     *
     * @param array{arguments: array, combined_strength: int, vulnerabilities: array<string>, recommendations: array<string>} $data
     */
    public static function fromArray(array $data): self
    {
        $arguments = array_map(
            fn(array $arg) => Argument::fromArray($arg),
            $data['arguments'] ?? [],
        );

        return new self(
            arguments: $arguments,
            combinedStrength: $data['combined_strength'] ?? 0,
            vulnerabilities: $data['vulnerabilities'] ?? [],
            recommendations: $data['recommendations'] ?? [],
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array{arguments: array, combined_strength: int, vulnerabilities: array<string>, recommendations: array<string>}
     */
    public function toArray(): array
    {
        return [
            'arguments' => array_map(fn(Argument $arg) => $arg->toArray(), $this->arguments),
            'combined_strength' => $this->combinedStrength,
            'vulnerabilities' => $this->vulnerabilities,
            'recommendations' => $this->recommendations,
        ];
    }

    /**
     * Get count of arguments in the chain.
     */
    public function count(): int
    {
        return count($this->arguments);
    }

    /**
     * Check if chain is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->arguments);
    }
}
