<?php

namespace App\Console\Commands;

use App\Services\Defense\DefenseReportBuilder;
use Illuminate\Console\Command;

class RunDefenseAnalysisCommand extends Command
{
    protected $signature = 'case:defense {case_id} {--tactic= : Run specific tactic only} {--json : JSON output}';
    protected $description = 'Run defense tactic detectors on a case';

    public function handle(DefenseReportBuilder $builder): int
    {
        $caseId = $this->argument('case_id');
        $tacticFilter = $this->option('tactic');

        $this->info("Running defense analysis for case {$caseId}...");

        $report = $builder->buildReport($caseId);

        // Filter by tactic if specified
        $flags = $report->flags;
        if ($tacticFilter) {
            $flags = array_filter($flags, fn($f) => $f->tactic === $tacticFilter);
            $this->info("Filtered to tactic: {$tacticFilter}");
        }

        if ($this->option('json')) {
            $output = [
                'case_id' => $report->caseId,
                'generated_at' => $report->generatedAt->toIso8601String(),
                'processing_time' => $report->processingTime,
                'detectors_run' => $report->detectorsRun,
                'flags' => array_map(fn($f) => $f->toArray(), $flags),
                'summary' => $this->buildSummary($flags),
            ];
            $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return 0;
        }

        // Human-readable output
        $criticals = array_filter($flags, fn($f) => $f->severity === 'critical');
        $highs = array_filter($flags, fn($f) => $f->severity === 'high');
        $mediums = array_filter($flags, fn($f) => $f->severity === 'medium');
        $lows = array_filter($flags, fn($f) => $f->severity === 'low');
        $infos = array_filter($flags, fn($f) => $f->severity === 'info');

        $this->newLine();
        $this->line("=== DEFENSE ANALYSIS REPORT ===");
        $this->line("Case: {$caseId}");
        $this->line("Detectors run: " . implode(', ', $report->detectorsRun));
        $this->newLine();

        if (count($criticals) > 0) {
            $this->error("  CRITICAL: " . count($criticals));
        }
        if (count($highs) > 0) {
            $this->warn("  HIGH: " . count($highs));
        }
        if (count($mediums) > 0) {
            $this->info("  MEDIUM: " . count($mediums));
        }
        if (count($lows) > 0) {
            $this->line("  LOW: " . count($lows));
        }
        if (count($infos) > 0) {
            $this->line("  INFO: " . count($infos));
        }
        $this->newLine();

        foreach ($flags as $flag) {
            $icon = match($flag->severity) {
                'critical' => '[CRITICAL]',
                'high' => '[HIGH]',
                'medium' => '[MEDIUM]',
                'low' => '[LOW]',
                default => '[INFO]',
            };

            $color = match($flag->severity) {
                'critical' => 'red',
                'high' => 'yellow',
                'medium' => 'cyan',
                default => 'white',
            };

            $this->line("<fg={$color}>{$icon}</> [{$flag->tactic}] {$flag->title}");
            $this->line("   Pravni temelj: {$flag->legalBasis}");
            if ($flag->echrBasis) {
                $this->line("   ECHR: {$flag->echrBasis}");
            }
            $this->line("   Pouzdanost: " . round($flag->confidence * 100) . "%");
            $this->line("   Preporuka: {$flag->recommendedAction}");
            $this->newLine();
        }

        $this->info("Analysis completed in {$report->processingTime}s");
        return 0;
    }

    private function buildSummary(array $flags): array
    {
        $summary = [
            'total' => count($flags),
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'info' => 0,
            'by_tactic' => [],
        ];

        foreach ($flags as $flag) {
            $summary[$flag->severity] = ($summary[$flag->severity] ?? 0) + 1;
            $summary['by_tactic'][$flag->tactic] = ($summary['by_tactic'][$flag->tactic] ?? 0) + 1;
        }

        return $summary;
    }
}
