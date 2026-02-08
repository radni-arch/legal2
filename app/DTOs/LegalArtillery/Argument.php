<?php

namespace App\DTOs\LegalArtillery;

/**
 * Immutable data transfer object representing a legal argument.
 *
 * Arguments are building blocks for legal documents, combining provisions
 * and precedents to support a specific type of legal position.
 */
class Argument
{
    /**
     * @param string $type The argument type (e.g., 'constitutional_violation', 'procedural_irregularity')
     * @param array<string> $provisions List of legal provisions supporting this argument
     * @param array<string> $precedents List of legal precedents (case citations) supporting this argument
     * @param int $strength Argument strength rating from 1 (weak) to 10 (devastating)
     * @param string $text The formatted argument text
     */
    public function __construct(
        public readonly string $type,
        public readonly array $provisions,
        public readonly array $precedents,
        public readonly int $strength,
        public readonly string $text,
    ) {}

    /**
     * Create an Argument from an array.
     *
     * @param array{type: string, provisions: array<string>, precedents: array<string>, strength: int, text: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            provisions: $data['provisions'] ?? [],
            precedents: $data['precedents'] ?? [],
            strength: $data['strength'] ?? 5,
            text: $data['text'] ?? '',
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array{type: string, provisions: array<string>, precedents: array<string>, strength: int, text: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'provisions' => $this->provisions,
            'precedents' => $this->precedents,
            'strength' => $this->strength,
            'text' => $this->text,
        ];
    }
}
