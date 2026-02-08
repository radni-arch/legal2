<?php

namespace App\Contracts\Services\Pdf;

/**
 * PdfArticleSplitterInterface
 *
 * Splits Croatian legal PDF documents into individual articles.
 * Provides:
 * - Article detection using "Članak N" patterns
 * - Page-based and text-based splitting modes
 * - Metadata and manifest generation
 * - Optional XMP embedding and sidecar files
 */
interface PdfArticleSplitterInterface
{
    /**
     * Split PDF into individual articles
     *
     * Detects article boundaries in Croatian legal documents by finding
     * "Članak N" patterns, then splits the PDF into separate files per article.
     *
     * Modes:
     * - 'pages': Extract page ranges as separate PDFs
     * - 'render': Extract text and render as HTML PDFs with search tags
     *
     * Options:
     * - only_numbers: Array of article numbers to extract (e.g., ['1', '2a', '15'])
     * - sidecar: Write .attrs.json metadata files alongside PDFs
     * - embed_xmp: Embed metadata into PDF XMP (requires exiftool)
     * - extra_attrs: Additional key-value attributes to include
     * - dry: Dry run - detect articles without creating files
     *
     * @param  string  $pdfPath  Path to source PDF
     * @param  string  $outDir  Output directory for article PDFs
     * @param  string  $mode  Split mode ('pages' or 'render')
     * @param  string|null  $lawTitle  Law title for metadata
     * @param  string|null  $eli  European Legislation Identifier
     * @param  string|null  $pubDate  Publication date (ISO 8601)
     * @param  int  $startPage  First page to analyze (default: 1)
     * @param  array  $opts  Additional options (only_numbers, sidecar, embed_xmp, extra_attrs, dry)
     * @return array Manifest with article metadata (number, pages, file paths, sha256)
     */
    public function split(
        string $pdfPath,
        string $outDir,
        string $mode = 'pages',
        ?string $lawTitle = null,
        ?string $eli = null,
        ?string $pubDate = null,
        int $startPage = 1,
        array $opts = []
    ): array;
}
