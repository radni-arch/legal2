<?php

namespace App\Services\Analysis;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;
use App\Services\Analysis\Analyzers\KeywordAnalyzer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentAnalysisPipeline
{
    /**
     * Registry of analyzers per layer.
     * Order matters - analyzers run sequentially within a layer.
     */
    private function getAnalyzersForLayer(string $layer): array
    {
        return match ($layer) {
            DocumentAnalysis::LAYER_EXTRACTION => $this->getExtractionLayerAnalyzers(),
            DocumentAnalysis::LAYER_PATTERN => [
                // Sprint 2: CrossDocReferenceAnalyzer, DateClusterAnalyzer
            ],
            DocumentAnalysis::LAYER_AI_BASIC => [
                // Sprint 3: TimelineAnalyzer, SummaryAnalyzer, KeyFactsAnalyzer
            ],
            DocumentAnalysis::LAYER_AI_DEEP => [
                // Sprint 4: ContradictionAnalyzer, StrategyAnalyzer, RiskAnalyzer
            ],
            default => [],
        };
    }

    /**
     * Get analyzers for the extraction layer.
     * Only include analyzers that exist.
     */
    private function getExtractionLayerAnalyzers(): array
    {
        $analyzers = [];

        // Add DocumentStatisticsAnalyzer if it exists
        if (class_exists(\App\Services\Analysis\Analyzers\DocumentStatisticsAnalyzer::class)) {
            $analyzers[] = new \App\Services\Analysis\Analyzers\DocumentStatisticsAnalyzer();
        }

        // Add KeywordAnalyzer
        $analyzers[] = new KeywordAnalyzer();

        // Add DateExtractor if it exists
        if (class_exists(\App\Services\Analysis\Analyzers\DateExtractor::class)) {
            $analyzers[] = new \App\Services\Analysis\Analyzers\DateExtractor();
        }

        // Add EntityExtractor if it exists
        if (class_exists(\App\Services\Analysis\Analyzers\EntityExtractor::class)) {
            $analyzers[] = new \App\Services\Analysis\Analyzers\EntityExtractor();
        }

        return $analyzers;
    }

    /**
     * Run all analyzers for a given layer on a document.
     */
    public function runLayer(CaseDocument $document, string $layer): array
    {
        $text = $this->getDocumentText($document);

        if (empty($text)) {
            Log::warning("DocumentAnalysisPipeline: No text for document {$document->id}");
            return [];
        }

        $analyzers = $this->getAnalyzersForLayer($layer);
        $results = [];

        foreach ($analyzers as $analyzer) {
            /** @var DocumentAnalyzerInterface $analyzer */
            try {
                $results[] = $this->runAnalyzer($analyzer, $document, $text);
            } catch (\Throwable $e) {
                Log::error("Analyzer {$analyzer->type()} failed for document {$document->id}: {$e->getMessage()}");

                // Record failure but continue with other analyzers
                $analysis = $this->getOrCreateAnalysis($document, $analyzer);
                $analysis->markFailed($e->getMessage());
                $results[] = $analysis;
            }
        }

        return $results;
    }

    /**
     * Run a single analyzer and persist results.
     */
    private function runAnalyzer(
        DocumentAnalyzerInterface $analyzer,
        CaseDocument $document,
        string $text
    ): DocumentAnalysis {
        $analysis = $this->getOrCreateAnalysis($document, $analyzer);
        $analysis->markProcessing();

        $result = $analyzer->analyze($document, $text);

        $analysis->markCompleted(
            $result['results'],
            $result['metadata']
        );

        Log::info("Analysis completed: {$analyzer->type()} for document {$document->id}", [
            'processing_time' => $result['metadata']['processing_time_seconds'] ?? null,
        ]);

        return $analysis;
    }

    private function getOrCreateAnalysis(
        CaseDocument $document,
        DocumentAnalyzerInterface $analyzer
    ): DocumentAnalysis {
        return DB::transaction(function () use ($document, $analyzer) {
            $existing = DocumentAnalysis::where([
                'case_document_id' => $document->id,
                'analysis_type' => $analyzer->type(),
                'version' => 1,
            ])->lockForUpdate()->first();

            if ($existing) {
                return $existing;
            }

            return DocumentAnalysis::create([
                'case_document_id' => $document->id,
                'analysis_type' => $analyzer->type(),
                'version' => 1,
                'analysis_layer' => $analyzer->layer(),
                'status' => DocumentAnalysis::STATUS_PENDING,
            ]);
        });
    }

    private function getDocumentText(CaseDocument $document): string
    {
        // Adapt this to your actual text storage:
        // Option A: extracted_content on the document itself
        // Option B: from related TextractJob
        // Option C: concatenate chunks from case_document_chunks

        if (!empty($document->content)) {
            return $document->content;
        }

        // Fallback: concatenate chunks if they exist
        try {
            if ($document->chunks && $document->chunks->isNotEmpty()) {
                return $document->chunks->pluck('content')->implode("\n\n");
            }
        } catch (\Throwable $e) {
            Log::debug("Could not load chunks for document {$document->id}: {$e->getMessage()}");
        }

        // Fallback: from related TextractJob (gracefully handle missing relationship)
        try {
            if ($document->textractJob && !empty($document->textractJob->extracted_content)) {
                return $document->textractJob->extracted_content;
            }
        } catch (\Throwable $e) {
            Log::debug("Could not load textractJob for document {$document->id}: {$e->getMessage()}");
        }

        return '';
    }
}
