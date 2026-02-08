<?php

namespace Tests\Support\Extractors;

/**
 * Mock extractor that simulates slow extraction work
 */
class MockSlowExtractor6
{
    public function extract(string $content): array
    {
        // Simulate extraction work (200ms)
        usleep(200000);

        return [
            'extractor' => self::class,
            'items' => ['item1'],
        ];
    }
}
