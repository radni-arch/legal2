<?php

namespace App\Validators;

class KeywordsValidator
{
    /**
     * Validate a keywords JSON structure according to METHODOLOGY.md rules.
     *
     * @param array $data The parsed keywords data
     * @return array ['valid' => bool, 'errors' => string[]]
     */
    public static function validate(array $data): array
    {
        $errors = [];

        // Check for categories array
        if (!isset($data['categories']) || !is_array($data['categories'])) {
            $errors[] = "Missing or invalid 'categories' array.";
            return ['valid' => false, 'errors' => $errors];
        }

        // Validate each category
        foreach ($data['categories'] as $i => $category) {
            $catName = $category['name'] ?? "Category #{$i}";

            // Check category name
            if (empty($category['name'])) {
                $errors[] = "{$catName}: Missing 'name'.";
            }

            // Check queries array
            if (!isset($category['queries']) || !is_array($category['queries'])) {
                $errors[] = "{$catName}: Missing or invalid 'queries' array.";
                continue;
            }

            // Validate each query
            foreach ($category['queries'] as $j => $query) {
                // Handle both string queries and object queries
                $q = is_string($query) ? $query : ($query['q'] ?? $query['query'] ?? '');

                // Check for empty query
                if (empty($q)) {
                    $errors[] = "{$catName}, query #{$j}: Empty query.";
                    continue;
                }

                // Warn about OR usage (methodology requires AND only)
                if (preg_match('/\bOR\b/', $q)) {
                    $errors[] = "{$catName}, query '{$q}': Contains OR operator. Use AND per methodology.";
                }

                // Warn about too many terms (>6 usually returns 0)
                $terms = preg_split('/\s+AND\s+/', $q);
                if (count($terms) > 6) {
                    $errors[] = "{$catName}, query '{$q}': Too many terms (" . count($terms) . "). Optimal is 2-4.";
                }
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }
}
