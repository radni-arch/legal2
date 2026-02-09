<?php

namespace App\Services;

use App\Contracts\Ingest\CaseIngestPipelineInterface;
use App\Exceptions\IngestException;
use App\Services\Ocr\HrLanguageNormalizer;
use App\Services\Ocr\OcrQualityAnalyzer;
use Illuminate\Support\Facades\Log;

/**
 * CaseIngestPipeline
 *
 * Orchestrates the full case document ingestion flow:
 * - OCR quality validation
 * - Language normalization (Croatian)
 * - Text chunking
 * - Embedding generation with quality gates
 *
 * Hardened with comprehensive error handling and performance monitoring
 */
class CaseIngestPipeline implements CaseIngestPipelineInterface
{
    public function __construct(
        protected OcrQualityAnalyzer $qualityAnalyzer,
        protected HrLanguageNormalizer $normalizer,
        protected CaseVectorStoreService $vectorStore
    ) {}

    /**
     * Process a case document through the full ingestion pipeline.
     *
     * @param  string  $caseId  ULID of the legal case
     * @param  string  $docId  Document identifier
     * @param  string  $rawText  OCR-extracted text
     * @param  array  $ocrBlocks  Optional Textract blocks for quality analysis
     * @param  array  $options  Configuration options
     * @return array Pipeline result
     *
     * @throws IngestException
     */
    public function ingest(
        string $caseId,
        string $docId,
        string $rawText,
        array $ocrBlocks = [],
        array $options = []
    ): array {

        $startTime = microtime(true);

        Log::info('Starting case document ingestion', [
            'case_id' => $caseId,
            'doc_id' => $docId,
            'text_length' => strlen($rawText),
            'has_ocr_blocks' => ! empty($ocrBlocks),
            'options' => $options,
        ]);

        $result = [
            'status' => 'pending',
            'quality_check' => null,
            'normalized' => false,
            'chunked' => false,
            'embedded' => false,
            'needs_review' => false,
            'error' => null,
            'timing' => [],
        ];

        try {
            // Step 1: OCR Quality Analysis
            $qualityStartTime = microtime(true);
            Log::info('Starting OCR quality analysis', [
                'case_id' => $caseId,
                'doc_id' => $docId,
            ]);

            try {
                $qualityResult = $this->analyzeQuality($ocrBlocks, $rawText, $options);
                $result['quality_check'] = $qualityResult;
                $result['timing']['quality_check'] = microtime(true) - $qualityStartTime;

                Log::info('OCR quality analysis completed', [
                    'case_id' => $caseId,
                    'doc_id' => $docId,
                    'confidence' => $qualityResult['confidence'],
                    'coverage' => $qualityResult['coverage'],
                    'duration_ms' => round($result['timing']['quality_check'] * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $result['timing']['quality_check'] = microtime(true) - $qualityStartTime;

                Log::error('OCR quality analysis failed', [
                    'case_id' => $caseId,
                    'doc_id' => $docId,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($result['timing']['quality_check'] * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new IngestException(
                    "OCR quality analysis failed for case {$caseId}, document {$docId}: {$e->getMessage()}",
                    IngestException::QUALITY_CHECK_FAILED,
                    $e
                );
            }

            // Extract and validate thresholds
            $minConfidence = (float) ($options['min_confidence'] ?? config('ocr.quality.min_confidence', 0.82));
            $minCoverage = (float) ($options['min_coverage'] ?? config('ocr.quality.min_coverage', 0.75));
            $maxLowConfPages = (int) ($options['max_low_confidence_pages'] ?? config('ocr.quality.max_low_confidence_pages', 3));

            $failedChecks = [];
            if ($qualityResult['confidence'] < $minConfidence) {
                $failedChecks[] = sprintf(
                    'Low confidence: %.2f%% (threshold: %.2f%%)',
                    $qualityResult['confidence'] * 100,
                    $minConfidence * 100
                );
            }

            if ($qualityResult['coverage'] < $minCoverage) {
                $failedChecks[] = sprintf(
                    'Low coverage: %.2f%% (threshold: %.2f%%)',
                    $qualityResult['coverage'] * 100,
                    $minCoverage * 100
                );
            }

            if (($qualityResult['low_confidence_pages'] ?? 0) > $maxLowConfPages) {
                $failedChecks[] = sprintf(
                    'Too many low-confidence pages: %d (threshold: %d)',
                    $qualityResult['low_confidence_pages'],
                    $maxLowConfPages
                );
            }

            if (! empty($failedChecks)) {
                Log::warning('OCR quality below threshold', [
                    'case_id' => $caseId,
                    'doc_id' => $docId,
                    'confidence' => $qualityResult['confidence'],
                    'coverage' => $qualityResult['coverage'],
                    'low_confidence_pages' => $qualityResult['low_confidence_pages'] ?? 0,
                    'failed_checks' => $failedChecks,
                    'thresholds' => [
                        'min_confidence' => $minConfidence,
                        'min_coverage' => $minCoverage,
                        'max_low_confidence_pages' => $maxLowConfPages,
                    ],
                ]);

                $result['status'] = 'quality_check_failed';
                $result['needs_review'] = true;
                $result['review_reasons'] = $failedChecks;

                // Block embedding if skip_embedding_on_low_quality is true
                if ($options['skip_embedding_on_low_quality'] ?? config('ocr.quality.skip_embedding_on_low_quality', false)) {
                    Log::info('Skipping embedding due to low OCR quality', [
                        'case_id' => $caseId,
                        'doc_id' => $docId,
                        'failed_checks' => $failedChecks,
                    ]);

                    $result['timing']['total'] = microtime(true) - $startTime;

                    return $result;
                }
            }

            // Step 2: Language Normalization
            $normStartTime = microtime(true);
            Log::info('Starting text normalization', [
                'case_id' => $caseId,
                'doc_id' => $docId,
                'language' => $options['language'] ?? 'hr',
            ]);

            try {
                $normalizedText = $this->normalizeText($rawText, $options);
                $result['normalized'] = true;
                $result['timing']['normalization'] = microtime(true) - $normStartTime;

                Log::info('Text normalization completed', [
                    'case_id' => $caseId,
                    'doc_id' => $docId,
                    'original_length' => strlen($rawText),
                    'normalized_length' => strlen($normalizedText),
                    'duration_ms' => round($result['timing']['normalization'] * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $result['timing']['normalization'] = microtime(true) - $normStartTime;

                Log::error('Text normalization failed', [
                    'case_id' => $caseId,
                    'doc_id' => $docId,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($result['timing']['normalization'] * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new IngestException(
                    "Text normalization failed for case {$caseId}, document {$docId}: {$e->getMessage()}",
                    IngestException::NORMALIZATION_FAILED,
                    $e
                );
            }

            // Step 3: Chunking
            $chunkStartTime = microtime(true);
            $chunkSize = (int) ($options['chunk_size'] ?? 1200);
            $chunkOverlap = (int) ($options['overlap'] ?? 150);

            Log::info('Starting text chunking', [
                'case_id' => $caseId,
                'doc_id' => $docId,
                'chunk_size' => $chunkSize,
                'overlap' => $chunkOverlap,
            ]);

            try {
                $chunks = $this->chunkText($normalizedText, $chunkSize, $chunkOverlap);
                $result['chunked'] = true;
                $result['chunk_count'] = count($chunks);
                $result['timing']['chunking'] = microtime(true) - $chunkStartTime;

                Log::info('Text chunking completed', [
                    'case_id' => $caseId,
                    'doc_id' => $docId,
                    'chunk_count' => count($chunks),
                    'duration_ms' => round($result['timing']['chunking'] * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $result['timing']['chunking'] = microtime(true) - $chunkStartTime;

                Log::error('Text chunking failed', [
                    'case_id' => $caseId,
                    'doc_id' => $docId,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'chunk_size' => $chunkSize,
                    'overlap' => $chunkOverlap,
                    'duration_ms' => round($result['timing']['chunking'] * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new IngestException(
                    "Text chunking failed for case {$caseId}, document {$docId}: {$e->getMessage()}",
                    IngestException::CHUNK_GENERATION_FAILED,
                    $e
                );
            }

            if (empty($chunks)) {
                $result['timing']['total'] = microtime(true) - $startTime;

                Log::warning('No chunks generated from text', [
                    'case_id' => $caseId,
                    'doc_id' => $docId,
                    'text_length' => strlen($rawText),
                    'normalized_length' => strlen($normalizedText),
                    'duration_ms' => round($result['timing']['total'] * 1000, 2),
                ]);

                $result['status'] = 'no_content';

                throw new IngestException(
                    "No content chunks generated for case {$caseId}, document {$docId}",
                    IngestException::NO_CONTENT_ERROR
                );
            }

            // Step 4: Prepare documents for embedding
            $prepStartTime = microtime(true);
            Log::info('Preparing documents for embedding', [
                'case_id' => $caseId,
                'doc_id' => $docId,
                'chunk_count' => count($chunks),
            ]);

            $docs = [];
            foreach ($chunks as $i => $chunkText) {
                $docs[] = [
                    'content' => $chunkText,
                    'chunk_index' => $i,
                    'metadata' => [
                        'quality_check' => $qualityResult,
                        'normalized' => true,
                        'chunk_size' => $chunkSize,
                        'overlap' => $chunkOverlap,
                    ],
                    'actual' => $options['metadata'] ?? null,
                ];
            }

            $result['timing']['document_preparation'] = microtime(true) - $prepStartTime;

            // Step 5: Ingest into vector store
            $embedStartTime = microtime(true);
            Log::info('Starting vector store ingestion', [
                'case_id' => $caseId,
                'doc_id' => $docId,
                'document_count' => count($docs),
            ]);

            try {
                $ingestResult = $this->vectorStore->ingest($caseId, $docId, $docs, $options);
                $result['embedded'] = true;
                $result['ingest_result'] = $ingestResult;
                $result['status'] = 'completed';
                $result['timing']['vector_store_ingestion'] = microtime(true) - $embedStartTime;

                Log::info('Vector store ingestion completed', [
                    'case_id' => $caseId,
                    'doc_id' => $docId,
                    'inserted' => $ingestResult['inserted'] ?? 0,
                    'duration_ms' => round($result['timing']['vector_store_ingestion'] * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $result['timing']['vector_store_ingestion'] = microtime(true) - $embedStartTime;

                Log::error('Vector store ingestion failed', [
                    'case_id' => $caseId,
                    'doc_id' => $docId,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'document_count' => count($docs),
                    'duration_ms' => round($result['timing']['vector_store_ingestion'] * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new IngestException(
                    "Vector store ingestion failed for case {$caseId}, document {$docId}: {$e->getMessage()}",
                    IngestException::VECTOR_STORE_FAILED,
                    $e
                );
            }

            $result['timing']['total'] = microtime(true) - $startTime;

            Log::info('Case document ingestion completed successfully', [
                'case_id' => $caseId,
                'doc_id' => $docId,
                'chunks' => count($chunks),
                'inserted' => $ingestResult['inserted'] ?? 0,
                'quality' => $qualityResult['confidence'],
                'total_duration_ms' => round($result['timing']['total'] * 1000, 2),
                'timing_breakdown' => array_map(
                    fn ($time) => round($time * 1000, 2).'ms',
                    $result['timing']
                ),
            ]);

        } catch (IngestException $e) {
            // Re-throw custom IngestException (already logged)
            $result['status'] = 'error';
            $result['error'] = $e->getMessage();
            $result['error_code'] = $e->getCode();
            $result['timing']['total'] = microtime(true) - $startTime;

            throw $e;
        } catch (\Throwable $e) {
            $result['timing']['total'] = microtime(true) - $startTime;

            Log::error('Case ingestion pipeline failed with unexpected error', [
                'case_id' => $caseId,
                'doc_id' => $docId,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_duration_ms' => round($result['timing']['total'] * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            $result['status'] = 'error';
            $result['error'] = $e->getMessage();

            throw new IngestException(
                "Unexpected error in case ingestion pipeline for case {$caseId}, document {$docId}: {$e->getMessage()}",
                IngestException::UNEXPECTED_ERROR,
                $e
            );
        }

        return $result;
    }

    /**
     * Analyze OCR quality from Textract blocks or raw text.
     *
     * @throws \Exception
     */
    protected function analyzeQuality(array $blocks, string $text, array $options): array
    {
        if (! empty($blocks)) {
            return $this->qualityAnalyzer->analyzeFromBlocks($blocks);
        }

        // Fallback: estimate quality from text characteristics
        return $this->qualityAnalyzer->estimateFromText($text);
    }

    /**
     * Normalize text for Croatian language.
     *
     * @throws \Exception
     */
    protected function normalizeText(string $text, array $options): string
    {
        $language = $options['language'] ?? 'hr';

        if ($language === 'hr' || $language === 'hr_HR') {
            return $this->normalizer->normalize($text);
        }

        // For other languages, just basic cleanup
        return $this->basicCleanup($text);
    }

    /**
     * Basic text cleanup for non-Croatian languages.
     */
    protected function basicCleanup(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        // Normalize line breaks
        $text = preg_replace('/\r\n|\r/', "\n", $text);
        // Remove excessive blank lines
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Chunk text using sliding window with overlap.
     * Smart splitting tries to break at sentence boundaries.
     *
     * @throws \Exception
     */
    protected function chunkText(string $text, int $size, int $overlap): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        if ($size <= 0) {
            return [$text];
        }

        $chunks = [];
        $start = 0;
        $len = mb_strlen($text, 'UTF-8');

        while ($start < $len) {
            $end = min($len, $start + $size);
            $chunk = mb_substr($text, $start, $end - $start, 'UTF-8');

            // Try to break at sentence boundary if not at end
            if ($end < $len && $end - $start >= 50) {
                $chunk = $this->breakAtSentence($chunk);
            }

            $chunks[] = trim($chunk);

            if ($end >= $len) {
                break;
            }

            // Calculate next start based on actual chunk length (after breaking)
            $actualChunkLen = mb_strlen($chunk, 'UTF-8');
            $nextStart = $start + $actualChunkLen - $overlap;

            // Ensure we always advance at least 1 character to avoid infinite loops
            $start = max($start + 1, $nextStart);
        }

        return array_filter($chunks, fn ($c) => trim($c) !== '');
    }

    /**
     * Try to break chunk at the last sentence boundary.
     */
    protected function breakAtSentence(string $chunk): string
    {
        // Look for sentence endings in the last 20% of the chunk
        $chunkLen = mb_strlen($chunk, 'UTF-8');
        $minKeep = (int) ($chunkLen * 0.8);

        // Croatian and common sentence endings
        $patterns = [
            '/([.!?])\s+(?=[A-ZČĆŽŠĐ])/u',  // Period/exclamation/question followed by capital
            '/([.!?])\n/u',                   // Sentence end at line break
            '/\n\n/u',                        // Paragraph break
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $chunk, $matches, PREG_OFFSET_CAPTURE)) {
                // Find the last match that's after minKeep (convert byte offset to char position)
                $matches = $matches[0];
                for ($i = count($matches) - 1; $i >= 0; $i--) {
                    // Convert byte offset to character position
                    $charPos = mb_strlen(substr($chunk, 0, $matches[$i][1]), 'UTF-8');
                    if ($charPos >= $minKeep) {
                        // Include the matched punctuation and whitespace
                        $matchLen = mb_strlen($matches[$i][0], 'UTF-8');

                        return mb_substr($chunk, 0, $charPos + $matchLen, 'UTF-8');
                    }
                }
            }
        }

        // No good break point found, try word boundary
        if (preg_match('/\s+(?=\S)/u', mb_substr($chunk, $minKeep, null, 'UTF-8'), $matches, PREG_OFFSET_CAPTURE)) {
            return mb_substr($chunk, 0, $minKeep + $matches[0][1], 'UTF-8');
        }

        // Give up, return as-is
        return $chunk;
    }

    /**
     * Check if document needs re-OCR based on quality metrics.
     */
    public function needsReOcr(array $qualityMetrics, array $options = []): bool
    {
        $minConfidence = (float) ($options['min_confidence'] ?? config('ocr.quality.min_confidence', 0.82));
        $minCoverage = (float) ($options['min_coverage'] ?? config('ocr.quality.min_coverage', 0.75));
        $maxLowConfPages = (int) ($options['max_low_confidence_pages'] ?? config('ocr.quality.max_low_confidence_pages', 3));

        if ($qualityMetrics['confidence'] < $minConfidence) {
            return true;
        }

        if ($qualityMetrics['coverage'] < $minCoverage) {
            return true;
        }

        if (($qualityMetrics['low_confidence_pages'] ?? 0) > $maxLowConfPages) {
            return true;
        }

        return false;
    }
}
