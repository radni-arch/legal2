<?php

namespace App\Services\Graph;

use App\Models\TextractDocument;
use Illuminate\Support\Facades\Concurrency;
use InvalidArgumentException;

class ParallelExtractionService
{
    /**
     * Maximum number of concurrent extractions
     */
    protected int $concurrencyLimit;

    /**
     * Create a new ParallelExtractionService instance
     */
    public function __construct()
    {
        $this->concurrencyLimit = config('graph.parallel.concurrency_limit', 3);
    }

    /**
     * Extract data from document using multiple extractors in parallel
     *
     * @param TextractDocument $document The document to extract from
     * @param array<string> $extractorClasses Array of fully-qualified extractor class names
     * @return array<array{extractor: string, data: array, error?: bool, message?: string}>
     * @throws InvalidArgumentException If extractor classes array is empty
     */
    public function extractInParallel(TextractDocument $document, array $extractorClasses): array
    {
        if (empty($extractorClasses)) {
            throw new InvalidArgumentException('Extractor classes array cannot be empty');
        }

        // Get document content (assuming content is stored in 'content' attribute)
        $content = $document->content ?? '';

        // Build array of closures for parallel execution
        $tasks = [];
        foreach ($extractorClasses as $extractorClass) {
            $tasks[] = function () use ($extractorClass, $content) {
                try {
                    // Check if class exists
                    if (!class_exists($extractorClass)) {
                        return [
                            'extractor' => $extractorClass,
                            'data' => [],
                            'error' => true,
                            'message' => "Extractor class not found: {$extractorClass}",
                        ];
                    }

                    // Instantiate extractor and call extract method
                    $extractor = new $extractorClass();
                    $data = $extractor->extract($content);

                    return [
                        'extractor' => $extractorClass,
                        'data' => $data,
                    ];
                } catch (\Throwable $e) {
                    // Handle any errors gracefully
                    return [
                        'extractor' => $extractorClass,
                        'data' => [],
                        'error' => true,
                        'message' => $e->getMessage(),
                    ];
                }
            };
        }

        // Execute tasks in parallel using Laravel's Concurrency facade
        // Chunk tasks to respect concurrency limit
        $chunks = array_chunk($tasks, $this->concurrencyLimit);
        $results = [];

        foreach ($chunks as $chunk) {
            $chunkResults = Concurrency::run($chunk);

            // Handle null return (can happen during testing with spies)
            if ($chunkResults === null) {
                foreach ($chunk as $task) {
                    $results[] = $task();
                }
            } else {
                $results = array_merge($results, $chunkResults);
            }
        }

        return $results;
    }

    /**
     * Set the maximum number of concurrent extractions
     *
     * @param int $limit The concurrency limit
     * @return self Fluent interface
     */
    public function setConcurrencyLimit(int $limit): self
    {
        $this->concurrencyLimit = $limit;
        return $this;
    }

    /**
     * Get the current concurrency limit
     *
     * @return int The concurrency limit
     */
    public function getConcurrencyLimit(): int
    {
        return $this->concurrencyLimit;
    }
}
