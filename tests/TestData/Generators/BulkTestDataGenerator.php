<?php

namespace Tests\TestData\Generators;

use App\Models\CaseDocument;
use App\Models\LegalCase;
use Illuminate\Support\Collection;

/**
 * Generates large datasets efficiently for performance testing
 */
class BulkTestDataGenerator
{
    /**
     * Track generated IDs for cleanup
     */
    private array $generatedIds = [];

    /**
     * Progress callback for large batches
     */
    private ?\Closure $progressCallback = null;

    /**
     * Set progress callback
     *
     * @param  callable  $callback  Function called with (current, total)
     */
    public function setProgressCallback(callable $callback): self
    {
        $this->progressCallback = $callback;

        return $this;
    }

    /**
     * Generate bulk legal cases
     *
     * @param  int  $count  Number of cases to generate
     * @param  string|null  $type  Case type (criminal, civil, etc.)
     * @return Collection Collection of LegalCase models
     */
    public function generateCases(int $count, ?string $type = null): Collection
    {
        // Use factory batch creation (not loops)
        $factory = LegalCase::factory()->count($count);

        // Apply type-specific factory state if provided
        if ($type === 'criminal') {
            $factory = $factory->criminal();
        } elseif ($type === 'civil') {
            $factory = $factory->civil();
        }

        // Create all cases in one batch
        $cases = $factory->create();

        // Track for cleanup
        if (! isset($this->generatedIds['cases'])) {
            $this->generatedIds['cases'] = [];
        }
        $this->generatedIds['cases'] = array_merge(
            $this->generatedIds['cases'],
            $cases->pluck('id')->toArray()
        );

        // Call progress callback if set
        if ($this->progressCallback) {
            ($this->progressCallback)($count, $count);
        }

        return $cases;
    }

    /**
     * Generate bulk case documents
     *
     * @param  int  $count  Number of documents to generate
     * @param  int|null  $caseId  Optional case ID to associate documents with
     * @return Collection Collection of CaseDocument models
     */
    public function generateDocuments(int $count, ?int $caseId = null): Collection
    {
        $factory = CaseDocument::factory()->count($count);

        // Associate with specific case if provided
        if ($caseId) {
            $case = LegalCase::find($caseId);
            $factory = $factory->for($case);
        }

        // Create all documents in one batch
        $documents = $factory->create();

        // Track for cleanup
        if (! isset($this->generatedIds['documents'])) {
            $this->generatedIds['documents'] = [];
        }
        $this->generatedIds['documents'] = array_merge(
            $this->generatedIds['documents'],
            $documents->pluck('id')->toArray()
        );

        // Call progress callback if set
        if ($this->progressCallback) {
            ($this->progressCallback)($count, $count);
        }

        return $documents;
    }

    /**
     * Generate batch of mixed data based on configuration
     *
     * @param  array  $config  Configuration array with keys: cases, documents, case_type, etc.
     * @return array Associative array with generated data
     */
    public function generateBatch(array $config): array
    {
        $result = [];

        // Generate cases if specified
        if (isset($config['cases'])) {
            $caseType = $config['case_type'] ?? null;
            $result['cases'] = $this->generateCases($config['cases'], $caseType);
        }

        // Generate documents if specified
        if (isset($config['documents'])) {
            $caseId = $config['case_id'] ?? null;
            $result['documents'] = $this->generateDocuments($config['documents'], $caseId);
        }

        return $result;
    }

    /**
     * Cleanup all generated data
     */
    public function cleanup(): void
    {
        // Delete in reverse order (children first, then parents)
        if (isset($this->generatedIds['documents']) && ! empty($this->generatedIds['documents'])) {
            CaseDocument::whereIn('id', $this->generatedIds['documents'])->delete();
        }

        if (isset($this->generatedIds['cases']) && ! empty($this->generatedIds['cases'])) {
            LegalCase::whereIn('id', $this->generatedIds['cases'])->delete();
        }

        // Reset tracking
        $this->generatedIds = [];
    }

    /**
     * Get count of tracked entities
     *
     * @param  string  $type  Entity type (cases, documents, etc.)
     * @return int Count of tracked entities
     */
    public function getTrackedCount(string $type): int
    {
        return count($this->generatedIds[$type] ?? []);
    }
}
