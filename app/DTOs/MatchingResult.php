<?php

namespace App\DTOs;

class MatchingResult
{
    public int $matchedCount = 0;
    public int $unmatchedCount = 0;
    public int $errorCount = 0;
    public int $processedCount = 0;
    public array $errors = [];
    public array $confidenceDistribution = [];

    public function recordMatch(int $confidence): void
    {
        $this->matchedCount++;
        $this->processedCount++;
        $this->confidenceDistribution[] = $confidence;
    }

    public function recordNoMatch(): void
    {
        $this->unmatchedCount++;
        $this->processedCount++;
    }

    public function recordError(string $error): void
    {
        $this->errorCount++;
        $this->errors[] = $error;
    }

    public function toArray(): array
    {
        return [
            'matched' => $this->matchedCount,
            'unmatched' => $this->unmatchedCount,
            'errors' => $this->errorCount,
            'processed' => $this->processedCount,
            'error_details' => $this->errors,
            'avg_confidence' => $this->averageConfidence(),
        ];
    }

    public function averageConfidence(): ?float
    {
        if (empty($this->confidenceDistribution)) {
            return null;
        }
        return round(array_sum($this->confidenceDistribution) / count($this->confidenceDistribution), 1);
    }
}
