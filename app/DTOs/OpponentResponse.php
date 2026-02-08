<?php

namespace App\DTOs;

class OpponentResponse
{
    public function __construct(
        public readonly string $summary,
        public readonly array $keyArguments,
        public readonly array $weaknesses,
        public readonly array $recommendedCounters,
        public readonly ?string $originalFile = null,
        public readonly ?string $sourceUrl = null,
        public readonly ?string $parsedAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            summary: $data['summary'],
            keyArguments: $data['key_arguments'] ?? [],
            weaknesses: $data['weaknesses'] ?? [],
            recommendedCounters: $data['recommended_counters'] ?? [],
            originalFile: $data['original_file'] ?? null,
            sourceUrl: $data['source_url'] ?? null,
            parsedAt: $data['parsed_at'] ?? now()->toIso8601String(),
        );
    }

    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'key_arguments' => $this->keyArguments,
            'weaknesses' => $this->weaknesses,
            'recommended_counters' => $this->recommendedCounters,
            'original_file' => $this->originalFile,
            'source_url' => $this->sourceUrl,
            'parsed_at' => $this->parsedAt,
        ];
    }
}
