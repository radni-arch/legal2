<?php

namespace App\Services\Textract;

use App\Contracts\Services\Textract\TableExtractorServiceInterface;
use App\Models\TextractJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Table Extractor Service
 *
 * Extracts structured table data from AWS Textract results
 */
class TableExtractorService implements TableExtractorServiceInterface
{
    /**
     * Extract tables from Textract job
     *
     * @return array Array of extracted tables
     */
    public function extractTables(TextractJob $job): array
    {
        $textractData = $this->loadTextractData($job);

        if (empty($textractData)) {
            return [];
        }

        return $this->parseTablesFromBlocks($textractData['Blocks'] ?? []);
    }

    /**
     * Parse tables from Textract blocks
     */
    protected function parseTablesFromBlocks(array $blocks): array
    {
        // Build block map for quick lookup
        $blockMap = [];
        foreach ($blocks as $block) {
            $blockMap[$block['Id']] = $block;
        }

        // Find all TABLE blocks
        $tables = [];
        foreach ($blocks as $block) {
            if ($block['BlockType'] === 'TABLE') {
                $table = $this->extractTable($block, $blockMap);
                if (! empty($table)) {
                    $tables[] = $table;
                }
            }
        }

        return $tables;
    }

    /**
     * Extract a single table
     */
    protected function extractTable(array $tableBlock, array $blockMap): array
    {
        $table = [
            'id' => $tableBlock['Id'],
            'confidence' => $tableBlock['Confidence'] ?? 0,
            'rows' => [],
            'metadata' => [
                'row_count' => 0,
                'column_count' => 0,
                'page' => $tableBlock['Page'] ?? null,
            ],
        ];

        // Get relationships
        $relationships = $tableBlock['Relationships'] ?? [];

        foreach ($relationships as $relationship) {
            if ($relationship['Type'] === 'CHILD') {
                $cells = [];

                // Get all CELL blocks
                foreach ($relationship['Ids'] as $cellId) {
                    if (isset($blockMap[$cellId]) && $blockMap[$cellId]['BlockType'] === 'CELL') {
                        $cells[] = $blockMap[$cellId];
                    }
                }

                // Organize cells into rows
                $table['rows'] = $this->organizeCellsIntoRows($cells, $blockMap);
            }
        }

        // Calculate metadata
        $table['metadata']['row_count'] = count($table['rows']);
        $table['metadata']['column_count'] = $this->getMaxColumnCount($table['rows']);

        // Convert to structured data
        $table['structured_data'] = $this->convertToStructuredData($table['rows']);

        return $table;
    }

    /**
     * Organize cells into rows
     */
    protected function organizeCellsIntoRows(array $cells, array $blockMap): array
    {
        $rows = [];

        foreach ($cells as $cell) {
            $rowIndex = $cell['RowIndex'] ?? 0;
            $columnIndex = $cell['ColumnIndex'] ?? 0;

            if (! isset($rows[$rowIndex])) {
                $rows[$rowIndex] = [];
            }

            $cellData = [
                'column_index' => $columnIndex,
                'text' => $this->getCellText($cell, $blockMap),
                'confidence' => $cell['Confidence'] ?? 0,
                'is_header' => ($cell['EntityTypes'] ?? []) === ['COLUMN_HEADER'],
                'row_span' => $cell['RowSpan'] ?? 1,
                'column_span' => $cell['ColumnSpan'] ?? 1,
            ];

            $rows[$rowIndex][$columnIndex] = $cellData;
        }

        // Sort rows by index
        ksort($rows);

        // Sort columns within each row
        foreach ($rows as &$row) {
            ksort($row);
        }

        return array_values($rows);
    }

    /**
     * Get text from a cell
     */
    protected function getCellText(array $cell, array $blockMap): string
    {
        $text = '';

        $relationships = $cell['Relationships'] ?? [];
        foreach ($relationships as $relationship) {
            if ($relationship['Type'] === 'CHILD') {
                foreach ($relationship['Ids'] as $wordId) {
                    if (isset($blockMap[$wordId]) && $blockMap[$wordId]['BlockType'] === 'WORD') {
                        $text .= ($blockMap[$wordId]['Text'] ?? '').' ';
                    }
                }
            }
        }

        return trim($text);
    }

    /**
     * Get maximum column count across all rows
     */
    protected function getMaxColumnCount(array $rows): int
    {
        $maxColumns = 0;

        foreach ($rows as $row) {
            $columnCount = count($row);
            if ($columnCount > $maxColumns) {
                $maxColumns = $columnCount;
            }
        }

        return $maxColumns;
    }

    /**
     * Convert rows to structured data with headers
     */
    protected function convertToStructuredData(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        // Check if first row is header
        $firstRow = $rows[0];
        $isHeaderRow = false;
        foreach ($firstRow as $cell) {
            if ($cell['is_header'] ?? false) {
                $isHeaderRow = true;
                break;
            }
        }

        $structuredData = [];

        if ($isHeaderRow) {
            // Use first row as headers
            $headers = [];
            foreach ($firstRow as $cell) {
                $headers[] = $cell['text'];
            }

            // Convert data rows
            for ($i = 1; $i < count($rows); $i++) {
                $dataRow = [];
                foreach ($rows[$i] as $columnIndex => $cell) {
                    $header = $headers[$columnIndex] ?? 'Column_'.($columnIndex + 1);
                    $dataRow[$header] = $cell['text'];
                }
                $structuredData[] = $dataRow;
            }
        } else {
            // No headers, use indexed columns
            foreach ($rows as $row) {
                $dataRow = [];
                foreach ($row as $columnIndex => $cell) {
                    $dataRow['Column_'.($columnIndex + 1)] = $cell['text'];
                }
                $structuredData[] = $dataRow;
            }
        }

        return $structuredData;
    }

    /**
     * Load Textract data from S3 or local storage
     */
    protected function loadTextractData(TextractJob $job): ?array
    {
        $metadata = $job->metadata ?? [];

        // Try loading from S3 using metadata key
        if (isset($metadata['s3_json_key'])) {
            try {
                $s3 = Storage::disk('s3');
                $json = $s3->get($metadata['s3_json_key']);
                $data = json_decode($json, true);

                if ($data) {
                    Log::info('TableExtractorService - Loaded from S3', [
                        'job_id' => $job->id,
                        's3_key' => $metadata['s3_json_key'],
                        'blocks_count' => count($data['Blocks'] ?? []),
                    ]);

                    return $data;
                }
            } catch (\Exception $e) {
                Log::warning('TableExtractorService - S3 load failed, trying local', [
                    'job_id' => $job->id,
                    's3_key' => $metadata['s3_json_key'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Try loading from local storage
        if (isset($metadata['local_json_path'])) {
            try {
                $local = Storage::disk('local');
                $json = $local->get($metadata['local_json_path']);
                $data = json_decode($json, true);

                if ($data) {
                    Log::info('TableExtractorService - Loaded from local storage', [
                        'job_id' => $job->id,
                        'local_path' => $metadata['local_json_path'],
                    ]);

                    return $data;
                }
            } catch (\Exception $e) {
                Log::warning('TableExtractorService - Local load failed', [
                    'job_id' => $job->id,
                    'local_path' => $metadata['local_json_path'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback: try constructing the S3 key from drive_file_id
        if ($job->drive_file_id) {
            try {
                $jsonPrefix = trim(env('S3_JSON_PREFIX', 'textract/json'), '/');
                $s3Key = $jsonPrefix.'/'.$job->drive_file_id.'.json';

                $s3 = Storage::disk('s3');
                $json = $s3->get($s3Key);
                $data = json_decode($json, true);

                if ($data) {
                    Log::info('TableExtractorService - Loaded from S3 (reconstructed key)', [
                        'job_id' => $job->id,
                        's3_key' => $s3Key,
                    ]);

                    return $data;
                }
            } catch (\Exception $e) {
                Log::warning('TableExtractorService - S3 load with reconstructed key failed', [
                    'job_id' => $job->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::error('TableExtractorService - No Textract data found', [
            'job_id' => $job->id,
            'has_s3_json_key' => isset($metadata['s3_json_key']),
            'has_local_json_path' => isset($metadata['local_json_path']),
            'has_drive_file_id' => ! empty($job->drive_file_id),
        ]);

        return null;
    }

    /**
     * Export table to CSV format
     */
    public function exportTableToCsv(array $table): string
    {
        $csv = '';

        foreach ($table['rows'] as $row) {
            $rowData = [];
            foreach ($row as $cell) {
                $rowData[] = '"'.str_replace('"', '""', $cell['text']).'"';
            }
            $csv .= implode(',', $rowData)."\n";
        }

        return $csv;
    }

    /**
     * Export table to JSON format
     */
    public function exportTableToJson(array $table): string
    {
        return json_encode($table['structured_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get table statistics
     */
    public function getTableStatistics(array $table): array
    {
        return [
            'row_count' => $table['metadata']['row_count'],
            'column_count' => $table['metadata']['column_count'],
            'total_cells' => $table['metadata']['row_count'] * $table['metadata']['column_count'],
            'confidence' => $table['confidence'],
            'page' => $table['metadata']['page'],
            'has_headers' => $this->hasHeaders($table),
        ];
    }

    /**
     * Check if table has headers
     */
    protected function hasHeaders(array $table): bool
    {
        if (empty($table['rows'])) {
            return false;
        }

        $firstRow = $table['rows'][0];
        foreach ($firstRow as $cell) {
            if ($cell['is_header'] ?? false) {
                return true;
            }
        }

        return false;
    }
}
