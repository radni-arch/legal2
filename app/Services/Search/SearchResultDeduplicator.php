<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\Log;

/**
 * SearchResultDeduplicator
 *
 * Responsible for removing duplicate search results based on content_hash.
 * When duplicates are found, keeps the result with the highest score.
 * Extracted from UnifiedSearchService as part of Phase 2 refactoring.
 *
 * Target: 150-200 lines
 */
class SearchResultDeduplicator
{
    /**
     * Deduplicate results using multiple strategies
     *
     * @param  array  $results  Results to deduplicate
     * @param  array  $options  Deduplication options:
     *                          - 'strategy' (string): 'strict' (ID only) or 'fuzzy' (content similarity)
     *                          - 'keep_highest_score' (bool): Keep result with highest score (default: true)
     * @return array Deduplicated results
     */
    public function deduplicate(array $results, array $options = []): array
    {
        $strategy = $options['strategy'] ?? 'strict';
        $keepHighestScore = $options['keep_highest_score'] ?? true;

        if ($strategy === 'fuzzy') {
            return $this->fuzzyDeduplicate($results, $keepHighestScore);
        }

        return $this->strictDeduplicate($results, $keepHighestScore);
    }

    /**
     * Strict deduplication based on exact ID matching
     *
     * @param  array  $results  Results to deduplicate
     * @param  bool  $keepHighestScore  Keep highest scoring duplicate
     * @return array Deduplicated results
     */
    protected function strictDeduplicate(array $results, bool $keepHighestScore = true): array
    {
        $seen = [];
        $deduplicated = [];
        $removedCount = 0;

        foreach ($results as $result) {
            $id = $result['id'] ?? null;

            if (! $id) {
                // No ID, keep result
                $deduplicated[] = $result;

                continue;
            }

            if (! isset($seen[$id])) {
                // First occurrence, keep it
                $seen[$id] = count($deduplicated);
                $deduplicated[] = $result;
            } else {
                // Duplicate found
                $removedCount++;

                if ($keepHighestScore) {
                    $existingIndex = $seen[$id];
                    $existingScore = $deduplicated[$existingIndex]['score'] ?? $deduplicated[$existingIndex]['weighted_score'] ?? 0.0;
                    $currentScore = $result['score'] ?? $result['weighted_score'] ?? 0.0;

                    // Replace if current has higher score
                    if ($currentScore > $existingScore) {
                        $deduplicated[$existingIndex] = $result;
                    }
                }
            }
        }

        Log::debug('Strict deduplication completed', [
            'original_count' => count($results),
            'final_count' => count($deduplicated),
            'removed' => $removedCount,
        ]);

        return array_values($deduplicated);
    }

    /**
     * Fuzzy deduplication based on content similarity
     *
     * @param  array  $results  Results to deduplicate
     * @param  bool  $keepHighestScore  Keep highest scoring duplicate
     * @return array Deduplicated results
     */
    protected function fuzzyDeduplicate(array $results, bool $keepHighestScore = true): array
    {
        $deduplicated = [];
        $contentHashes = [];
        $removedCount = 0;

        foreach ($results as $result) {
            $content = $result['content'] ?? '';

            // Generate content hash for similarity detection
            $hash = $this->generateContentHash($content);

            if (! isset($contentHashes[$hash])) {
                // First occurrence with this content
                $contentHashes[$hash] = count($deduplicated);
                $deduplicated[] = $result;
            } else {
                // Similar content found
                $removedCount++;

                if ($keepHighestScore) {
                    $existingIndex = $contentHashes[$hash];
                    $existingScore = $deduplicated[$existingIndex]['score'] ?? $deduplicated[$existingIndex]['weighted_score'] ?? 0.0;
                    $currentScore = $result['score'] ?? $result['weighted_score'] ?? 0.0;

                    // Replace if current has higher score
                    if ($currentScore > $existingScore) {
                        $deduplicated[$existingIndex] = $result;
                    }
                }
            }
        }

        Log::debug('Fuzzy deduplication completed', [
            'original_count' => count($results),
            'final_count' => count($deduplicated),
            'removed' => $removedCount,
        ]);

        return array_values($deduplicated);
    }

    /**
     * Generate content hash for similarity detection
     *
     * Normalizes content by removing whitespace and converting to lowercase
     * before hashing to catch near-duplicates.
     *
     * @param  string  $content  Content to hash
     * @return string Content hash
     */
    protected function generateContentHash(string $content): string
    {
        // Normalize: remove extra whitespace, lowercase, trim
        $normalized = preg_replace('/\s+/', ' ', trim(mb_strtolower($content)));

        // Take first 500 chars for comparison (enough to detect duplicates)
        $sample = mb_substr($normalized, 0, 500);

        return md5($sample);
    }

    /**
     * Get deduplication statistics
     *
     * @param  array  $originalResults  Original results before deduplication
     * @param  array  $deduplicatedResults  Results after deduplication
     * @return array Statistics about deduplication
     */
    public function getStatistics(array $originalResults, array $deduplicatedResults): array
    {
        $originalCount = count($originalResults);
        $finalCount = count($deduplicatedResults);
        $removedCount = $originalCount - $finalCount;

        return [
            'original_count' => $originalCount,
            'final_count' => $finalCount,
            'removed_count' => $removedCount,
            'removal_percentage' => $originalCount > 0 ? round(($removedCount / $originalCount) * 100, 2) : 0,
        ];
    }

    /**
     * Deduplicate results and return statistics
     *
     * Same as deduplicate() but also returns statistics about the deduplication process.
     * Useful for debugging and understanding how many duplicates were removed.
     *
     * @param  array  $results  Array of search results
     * @return array Array with 'results' and 'stats' keys
     */
    public function deduplicateWithStats(array $results): array
    {
        $inputCount = count($results);

        $deduplicated = $this->deduplicate($results);

        $outputCount = count($deduplicated);

        return [
            'results' => $deduplicated,
            'stats' => [
                'total_input' => $inputCount,
                'total_output' => $outputCount,
                'duplicates_removed' => $inputCount - $outputCount,
                'deduplication_rate' => $inputCount > 0
                    ? round(($inputCount - $outputCount) / $inputCount * 100, 2)
                    : 0,
            ],
        ];
    }

    /**
     * Count duplicates without deduplicating
     *
     * Analyzes the results to count how many duplicates exist without
     * actually performing the deduplication. Useful for statistics.
     *
     * @param  array  $results  Array of search results
     * @return array Statistics about duplicates
     */
    public function countDuplicates(array $results): array
    {
        $hashCounts = [];
        $withoutHash = 0;

        foreach ($results as $result) {
            $contentHash = $result['metadata']['content_hash'] ?? null;

            if (! $contentHash) {
                $withoutHash++;
            } else {
                $hashCounts[$contentHash] = ($hashCounts[$contentHash] ?? 0) + 1;
            }
        }

        $uniqueHashes = count($hashCounts);
        $duplicateHashes = count(array_filter($hashCounts, fn ($count) => $count > 1));
        $totalDuplicates = array_sum($hashCounts) - $uniqueHashes;

        return [
            'total_results' => count($results),
            'unique_hashes' => $uniqueHashes,
            'duplicate_hashes' => $duplicateHashes,
            'total_duplicates' => $totalDuplicates,
            'results_without_hash' => $withoutHash,
            'expected_output_count' => $uniqueHashes + $withoutHash,
        ];
    }

    /**
     * Get duplicate groups
     *
     * Groups results by content_hash to show which results are duplicates of each other.
     * Useful for debugging and understanding the duplication patterns.
     *
     * @param  array  $results  Array of search results
     * @return array Array of duplicate groups (only includes groups with 2+ results)
     */
    public function getDuplicateGroups(array $results): array
    {
        $groups = [];

        foreach ($results as $result) {
            $contentHash = $result['metadata']['content_hash'] ?? null;

            if ($contentHash) {
                $groups[$contentHash][] = $result;
            }
        }

        // Filter to only groups with duplicates (2+ items)
        return array_filter($groups, fn ($group) => count($group) > 1);
    }

    /**
     * Validate that results have the expected structure for deduplication
     *
     * Checks that results have the required fields (score, metadata) for deduplication.
     * Does not fail on missing content_hash (those results are kept as-is).
     *
     * @param  array  $results  Array of search results
     * @return bool True if structure is valid
     */
    public function validateResultsStructure(array $results): bool
    {
        foreach ($results as $result) {
            // Must be an array
            if (! is_array($result)) {
                return false;
            }

            // Must have score field
            if (! isset($result['score']) || ! is_numeric($result['score'])) {
                return false;
            }

            // If metadata exists, it must be an array
            if (isset($result['metadata']) && ! is_array($result['metadata'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if deduplication is needed
     *
     * Quick check to determine if there are any duplicates without performing
     * the full deduplication. Returns false if all results are unique or don't
     * have content_hash.
     *
     * @param  array  $results  Array of search results
     * @return bool True if duplicates exist
     */
    public function hasDuplicates(array $results): bool
    {
        $seenHashes = [];

        foreach ($results as $result) {
            $contentHash = $result['metadata']['content_hash'] ?? null;

            if ($contentHash) {
                if (isset($seenHashes[$contentHash])) {
                    return true; // Found a duplicate
                }
                $seenHashes[$contentHash] = true;
            }
        }

        return false; // No duplicates found
    }
}
