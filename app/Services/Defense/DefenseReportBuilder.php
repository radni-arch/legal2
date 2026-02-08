<?php

namespace App\Services\Defense;

use App\DTOs\Defense\DefenseFlag;
use App\DTOs\Defense\DefenseReport;
use App\Models\DocumentAnalysis;
use App\Models\CaseAnalysis;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DefenseReportBuilder
{
    /** @var DefenseTacticDetectorInterface[] */
    private array $detectors = [];

    public function register(DefenseTacticDetectorInterface $detector): self
    {
        $this->detectors[$detector->tactic()] = $detector;
        return $this;
    }

    /**
     * Run all registered detectors against a case.
     * Pre-loads required analysis data to avoid N+1 queries.
     */
    public function buildReport(string $caseId): DefenseReport
    {
        $startTime = microtime(true);

        // Collect all required data types across all detectors
        $requiredTypes = collect($this->detectors)
            ->flatMap(fn($d) => $d->requires())
            ->unique()
            ->values()
            ->toArray();

        // Pre-load all needed analysis results
        $analysisData = $this->loadAnalysisData($caseId, $requiredTypes);

        // Run each detector
        $allFlags = [];
        foreach ($this->detectors as $detector) {
            try {
                $flags = $detector->detect($caseId, $analysisData);
                $allFlags = array_merge($allFlags, $flags);

                Log::info("Defense detector [{$detector->tactic()}]: " . count($flags) . " flags");
            } catch (\Throwable $e) {
                Log::error("Defense detector [{$detector->tactic()}] failed: {$e->getMessage()}");

                // Add error flag so user knows detection was incomplete
                $allFlags[] = new DefenseFlag(
                    tactic: $detector->tactic(),
                    severity: DefenseFlag::SEVERITY_INFO,
                    title: "Greska u detekciji: {$detector->label()}",
                    description: "Detektor nije mogao dovrsiti analizu: {$e->getMessage()}",
                    legalBasis: '-',
                    echrBasis: null,
                    evidence: [],
                    recommendedAction: 'Rucno provjeriti.',
                    confidence: 0,
                );
            }
        }

        // Sort: critical first, then by confidence descending
        $severityOrder = [
            DefenseFlag::SEVERITY_CRITICAL => 0,
            DefenseFlag::SEVERITY_HIGH => 1,
            DefenseFlag::SEVERITY_MEDIUM => 2,
            DefenseFlag::SEVERITY_LOW => 3,
            DefenseFlag::SEVERITY_INFO => 4,
        ];

        usort($allFlags, function (DefenseFlag $a, DefenseFlag $b) use ($severityOrder) {
            $sevCmp = ($severityOrder[$a->severity] ?? 9) <=> ($severityOrder[$b->severity] ?? 9);
            if ($sevCmp !== 0) return $sevCmp;
            return $b->confidence <=> $a->confidence;
        });

        // Persist flags atomically to avoid inconsistent state
        DB::transaction(function () use ($caseId, $allFlags) {
            DB::table('defense_flags')->where('case_id', $caseId)->delete();
            foreach ($allFlags as $flag) {
                DB::table('defense_flags')->insert(array_merge(
                    $flag->toArray(),
                    [
                        'case_id' => $caseId,
                        'evidence' => json_encode($flag->evidence),
                        'metadata' => json_encode($flag->metadata),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                ));
            }
        });

        return new DefenseReport(
            caseId: $caseId,
            flags: $allFlags,
            generatedAt: now(),
            processingTime: round(microtime(true) - $startTime, 2),
            detectorsRun: array_keys($this->detectors),
        );
    }

    private function loadAnalysisData(string $caseId, array $types): array
    {
        $data = [];

        if (empty($types)) {
            return $data;
        }

        // Per-document analyses
        $docAnalyses = DocumentAnalysis::whereHas('caseDocument', function ($q) use ($caseId) {
            $q->where('case_id', $caseId);
        })
            ->whereIn('analysis_type', $types)
            ->where('status', DocumentAnalysis::STATUS_COMPLETED)
            ->with('caseDocument')
            ->get();

        foreach ($docAnalyses as $analysis) {
            $data[$analysis->analysis_type][$analysis->case_document_id] = $analysis->results;
        }

        // Case-level analyses
        $caseAnalyses = CaseAnalysis::where('case_id', $caseId)
            ->whereIn('analysis_type', $types)
            ->where('status', CaseAnalysis::STATUS_COMPLETED)
            ->get();

        foreach ($caseAnalyses as $analysis) {
            $data[$analysis->analysis_type] = $analysis->results;
        }

        // Also load specific cross-references
        if (in_array('case_reference_registry', $types) && DB::getSchemaBuilder()->hasTable('case_reference_registry')) {
            $data['case_reference_registry'] = DB::table('case_reference_registry')
                ->where('case_id', $caseId)->get()->toArray();
        }

        if (in_array('case_hierarchy', $types) && DB::getSchemaBuilder()->hasTable('case_hierarchy')) {
            $data['case_hierarchy'] = DB::table('case_hierarchy')
                ->where('case_id', $caseId)->get()->toArray();
        }

        return $data;
    }
}
