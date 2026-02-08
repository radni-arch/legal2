<?php

namespace App\DTOs\Ekom;

class DndResponseDTO
{
    public function __construct(
        public readonly ?int $affectedCount,
        public readonly ?bool $currentState,
        public readonly ?string $message,
    ) {}

    /**
     * Create DTO from a per-predmet toggle response (bool result).
     */
    public static function fromToggleResponse(bool $result): self
    {
        return new self(
            affectedCount: null,
            currentState: $result,
            message: $result ? 'DND enabled' : 'DND disabled',
        );
    }

    /**
     * Create DTO from a general DND response (array data from API).
     */
    public static function fromGeneralResponse(array $data): self
    {
        return new self(
            affectedCount: $data['affectedCount'] ?? null,
            currentState: null,
            message: $data['message'] ?? null,
        );
    }
}
