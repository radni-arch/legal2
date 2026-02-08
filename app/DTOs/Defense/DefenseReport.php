<?php

namespace App\DTOs\Defense;

use Carbon\Carbon;

class DefenseReport
{
    /**
     * @param string $caseId
     * @param DefenseFlag[] $flags
     * @param Carbon $generatedAt
     * @param float $processingTime
     * @param array $detectorsRun
     */
    public function __construct(
        public readonly string $caseId,
        public readonly array $flags,
        public readonly Carbon $generatedAt,
        public readonly float $processingTime,
        public readonly array $detectorsRun,
    ) {}

    public function toArray(): array
    {
        return [
            'case_id' => $this->caseId,
            'flags' => array_map(fn(DefenseFlag $flag) => $flag->toArray(), $this->flags),
            'generated_at' => $this->generatedAt->format('Y-m-d H:i:s'),
            'processing_time' => $this->processingTime,
            'detectors_run' => $this->detectorsRun,
            'summary' => $this->generateSummary(),
        ];
    }

    private function generateSummary(): array
    {
        $counts = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'info' => 0,
        ];

        foreach ($this->flags as $flag) {
            if (isset($counts[$flag->severity])) {
                $counts[$flag->severity]++;
            }
        }

        return [
            'total_flags' => count($this->flags),
            'critical_count' => $counts['critical'],
            'high_count' => $counts['high'],
            'medium_count' => $counts['medium'],
            'low_count' => $counts['low'],
            'info_count' => $counts['info'],
        ];
    }
}
