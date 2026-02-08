<?php

namespace Tests\Support\Extractors;

/**
 * Mock extractor that simulates slow extraction work
 */
class MockSlowExtractor4
{
    public function extract(string $content): array
    {
        // Simulate extraction work (200ms)
        usleep(200000);

        return [
            'extractor' => self::class,
            'items' => ['item1', 'item2', 'item3', 'item4'],
        ];
    }
}
