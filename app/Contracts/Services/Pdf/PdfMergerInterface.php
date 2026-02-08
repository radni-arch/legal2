<?php

namespace App\Contracts\Services\Pdf;

/**
 * PdfMergerInterface
 *
 * Merges multiple PDF files into a single document.
 * Handles:
 * - Multiple PDF file merging
 * - Memory management for large files
 * - Automatic directory creation
 */
interface PdfMergerInterface
{
    /**
     * Merge multiple PDFs into a single PDF
     *
     * Takes an array of PDF file paths and combines them into a single
     * PDF document at the destination path. Preserves page sizes and
     * orientations from source documents.
     *
     * Features:
     * - Automatic directory creation for destination
     * - Memory limit management (512M during operation)
     * - Skip non-existent or invalid PDFs
     * - Cleanup and garbage collection after merge
     *
     * @param  array<int,string>  $pdfPaths  Array of source PDF file paths
     * @param  string  $destPath  Destination path for merged PDF
     * @return string Path to the merged PDF file
     */
    public function merge(array $pdfPaths, string $destPath): string;
}
