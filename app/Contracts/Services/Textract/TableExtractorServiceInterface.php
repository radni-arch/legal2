<?php

namespace App\Contracts\Services\Textract;

use App\Models\TextractJob;

/**
 * TableExtractorServiceInterface
 *
 * Extracts structured table data from AWS Textract results.
 * Provides:
 * - Table extraction from Textract blocks
 * - Export to CSV and JSON formats
 * - Table statistics and metadata
 */
interface TableExtractorServiceInterface
{
    /**
     * Extract tables from Textract job results
     *
     * Parses Textract JSON blocks to extract all tables with their
     * structure, cells, and metadata. Returns array of table objects
     * with rows, columns, confidence scores, and structured data.
     *
     * Each table contains:
     * - id: Table block ID
     * - confidence: Detection confidence
     * - rows: Array of rows with cells
     * - metadata: Row count, column count, page number
     * - structured_data: Array of row objects with headers as keys
     *
     * @param  TextractJob  $job  Textract job with completed analysis
     * @return array Array of extracted tables
     */
    public function extractTables(TextractJob $job): array;

    /**
     * Export table to CSV format
     *
     * Converts a table structure to CSV string with proper escaping.
     *
     * @param  array  $table  Table structure from extractTables()
     * @return string CSV representation of the table
     */
    public function exportTableToCsv(array $table): string;

    /**
     * Export table to JSON format
     *
     * Converts table to pretty-printed JSON with Unicode support.
     *
     * @param  array  $table  Table structure from extractTables()
     * @return string JSON representation of structured_data
     */
    public function exportTableToJson(array $table): string;

    /**
     * Get table statistics and metadata
     *
     * Returns comprehensive statistics about a table:
     * - row_count: Number of rows
     * - column_count: Number of columns
     * - total_cells: Total cell count
     * - confidence: Overall confidence score
     * - page: Page number where table appears
     * - has_headers: Whether table has header row
     *
     * @param  array  $table  Table structure from extractTables()
     * @return array Table statistics
     */
    public function getTableStatistics(array $table): array;
}
