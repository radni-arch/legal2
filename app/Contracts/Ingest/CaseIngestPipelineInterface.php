<?php

namespace App\Contracts\Ingest;

/**
 * Case Ingest Pipeline Interface
 *
 * Defines the contract for ingesting case documents into vector storage
 * with quality assessment and OCR processing.
 */
interface CaseIngestPipelineInterface
{
    /**
     * Ingest a case document
     *
     * @param  string  $caseId  ULID of the legal case
     * @param  string  $docId  Document identifier
     * @param  string  $rawText  OCR-extracted text
     * @param  array  $ocrBlocks  Optional Textract blocks for quality analysis
     * @param  array  $options  Configuration options
     * @return array Pipeline result
     */
    public function ingest(
        string $caseId,
        string $docId,
        string $rawText,
        array $ocrBlocks = [],
        array $options = []
    ): array;

    /**
     * Check if document needs re-OCR based on quality metrics
     *
     * @param  array  $qualityMetrics  OCR quality metrics
     * @param  array  $options  Check options
     * @return bool True if re-OCR is needed
     */
    public function needsReOcr(array $qualityMetrics, array $options = []): bool;
}
