#!/usr/bin/env php
<?php

/**
 * TDD Test Queue Generator
 *
 * Scans the tests/ directory recursively, classifies all test files,
 * identifies hierarchical relationships (visual → livewire → integration → unit),
 * and generates a structured tdd-test-queue.json for AI TDD agents.
 *
 * Usage: php generate-tdd-queue.php [tests_directory] [output_file]
 *
 * Default:
 *   tests_directory = ./tests
 *   output_file = ./test-results/tdd-test-queue.json
 */

declare(strict_types=1);

// Configuration
$testsDir = $argv[1] ?? './tests';
$outputFile = $argv[2] ?? './test-results/tdd-test-queue.json';

// Ensure output directory exists
$outputDir = dirname($outputFile);
if (! is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

/**
 * Test type classification based on path
 */
function classifyTestType(string $relativePath): string
{
    // Order matters - more specific patterns first
    if (preg_match('#^Browser/#', $relativePath)) {
        return 'browser_dusk';
    }
    if (preg_match('#^Feature/Livewire/#', $relativePath)) {
        return 'feature_livewire';
    }
    if (preg_match('#^Feature/#', $relativePath)) {
        return 'feature';
    }
    if (preg_match('#^Integration/#', $relativePath)) {
        return 'integration';
    }
    if (preg_match('#^Unit/#', $relativePath)) {
        return 'unit';
    }
    if (preg_match('#^Performance/#', $relativePath)) {
        return 'performance';
    }
    if (preg_match('#^Snapshots/#', $relativePath)) {
        return 'snapshot';
    }

    return 'other';
}

/**
 * Extract domain from path and filename patterns
 */
function extractDomain(string $relativePath, string $filename): string
{
    $pathLower = strtolower($relativePath);
    $fileLower = strtolower($filename);

    // Textract-related
    if (str_contains($pathLower, 'textract') || str_contains($fileLower, 'textract')) {
        return 'textract_pipeline';
    }

    // Graph/Neo4j related
    if (str_contains($pathLower, 'graph') || str_contains($fileLower, 'graph') ||
        str_contains($pathLower, 'neo4j') || str_contains($fileLower, 'neo4j')) {
        return 'graph_and_neo4j';
    }

    // Search related
    if (str_contains($fileLower, 'search') || str_contains($pathLower, '/search/')) {
        return 'search_services';
    }

    // Vector store related
    if (str_contains($fileLower, 'vector') || str_contains($fileLower, 'embedding')) {
        return 'vector_stores';
    }

    // Agent/Research related
    if (str_contains($pathLower, 'agent') || str_contains($fileLower, 'agent') ||
        str_contains($fileLower, 'research') || str_contains($pathLower, 'research')) {
        return 'agents_and_research';
    }

    // Decision discovery
    if (str_contains($fileLower, 'decision') || str_contains($fileLower, 'odluke')) {
        return 'decision_discovery';
    }

    // Timeline related
    if (str_contains($fileLower, 'timeline')) {
        return 'timeline_views';
    }

    // Dashboard/Viewer related
    if (str_contains($fileLower, 'dashboard') || str_contains($fileLower, 'viewer') ||
        str_contains($fileLower, 'panel')) {
        return 'dashboards_and_viewers';
    }

    // Manager components
    if (str_contains($fileLower, 'manager')) {
        return 'manager_components';
    }

    // OpenAI/LLM related
    if (str_contains($fileLower, 'openai') || str_contains($fileLower, 'llm') ||
        str_contains($fileLower, 'chatbot') || str_contains($fileLower, 'rag')) {
        return 'openai_and_llm';
    }

    // Legal reasoning
    if (str_contains($pathLower, 'legalreasoning') || str_contains($fileLower, 'legal') ||
        str_contains($fileLower, 'citation') || str_contains($fileLower, 'law')) {
        return 'legal_reasoning';
    }

    // Security
    if (str_contains($pathLower, 'security') || str_contains($fileLower, 'security') ||
        str_contains($fileLower, 'honeypot') || str_contains($fileLower, 'xss') ||
        str_contains($fileLower, 'injection')) {
        return 'security';
    }

    // Authentication
    if (str_contains($fileLower, 'auth') || str_contains($fileLower, 'login')) {
        return 'authentication';
    }

    // MCP related
    if (str_contains($pathLower, 'mcp') || str_contains($fileLower, 'mcp')) {
        return 'mcp_integration';
    }

    // Evidence related
    if (str_contains($fileLower, 'evidence')) {
        return 'evidence_management';
    }

    // Collaboration
    if (str_contains($fileLower, 'collaboration')) {
        return 'collaboration';
    }

    // E-komunikacija / E-oglasna
    if (str_contains($fileLower, 'ekom') || str_contains($fileLower, 'eoglasna') ||
        str_contains($fileLower, 'epredmet')) {
        return 'court_integration';
    }

    // Console commands
    if (str_contains($pathLower, 'console') || str_contains($pathLower, 'commands')) {
        return 'console_commands';
    }

    // API related
    if (str_contains($pathLower, '/api/') || str_contains($pathLower, '/api.')) {
        return 'api_endpoints';
    }

    // Jobs
    if (str_contains($pathLower, 'jobs/') || str_contains($pathLower, '/jobs')) {
        return 'background_jobs';
    }

    // Models
    if (str_contains($pathLower, 'models/')) {
        return 'models';
    }

    // Services (generic)
    if (str_contains($pathLower, 'services/')) {
        return 'services';
    }

    // Components (Blade)
    if (str_contains($pathLower, 'components/')) {
        return 'blade_components';
    }

    // Policies
    if (str_contains($pathLower, 'policies/')) {
        return 'authorization';
    }

    // Requests/Validation
    if (str_contains($pathLower, 'requests/')) {
        return 'request_validation';
    }

    return 'general';
}

/**
 * Extract test class name from filename
 */
function extractTestClass(string $filename): string
{
    return str_replace('.php', '', $filename);
}

/**
 * Generate a unique ID for a test
 */
function generateTestId(string $type, string $domain, string $testClass): string
{
    // Shorten the type for ID
    $typeShort = match ($type) {
        'browser_dusk' => 'browser',
        'feature_livewire' => 'livewire',
        'feature' => 'feature',
        'integration' => 'integration',
        'unit' => 'unit',
        'performance' => 'perf',
        'snapshot' => 'snapshot',
        default => 'other'
    };

    return "{$typeShort}:{$testClass}";
}

/**
 * Assign sprint based on type and domain priority
 */
function assignSprint(string $type, string $domain): int
{
    // Sprint 1: Core backend (unit tests for critical services)
    if ($type === 'unit' && in_array($domain, [
        'textract_pipeline',
        'search_services',
        'vector_stores',
        'graph_and_neo4j',
    ])) {
        return 1;
    }

    // Sprint 2: Feature/Livewire manager components
    if ($type === 'feature_livewire' && in_array($domain, [
        'manager_components',
        'vector_stores',
        'textract_pipeline',
    ])) {
        return 2;
    }

    // Sprint 2: Core feature tests
    if ($type === 'feature' && in_array($domain, [
        'textract_pipeline',
        'search_services',
    ])) {
        return 2;
    }

    // Sprint 3: Integration tests
    if ($type === 'integration') {
        return 3;
    }

    // Sprint 3: Agent and research
    if (in_array($domain, ['agents_and_research', 'decision_discovery'])) {
        return 3;
    }

    // Sprint 4: Livewire dashboards and viewers
    if ($type === 'feature_livewire' && in_array($domain, [
        'dashboards_and_viewers',
        'timeline_views',
    ])) {
        return 4;
    }

    // Sprint 4: Browser/Dusk tests for critical flows
    if ($type === 'browser_dusk' && in_array($domain, [
        'manager_components',
        'textract_pipeline',
        'search_services',
    ])) {
        return 4;
    }

    // Sprint 5: Security and auth
    if (in_array($domain, ['security', 'authentication'])) {
        return 5;
    }

    // Sprint 5: Remaining browser tests
    if ($type === 'browser_dusk') {
        return 5;
    }

    // Sprint 6: Performance and remaining
    if ($type === 'performance') {
        return 6;
    }

    // Default: Sprint 4 for remaining feature tests
    if ($type === 'feature') {
        return 4;
    }

    // Default: Sprint 3 for remaining unit tests
    if ($type === 'unit') {
        return 3;
    }

    return 6; // Catch-all
}

/**
 * Extract meaningful keywords from test class name
 */
function extractKeywords(string $testClass): array
{
    // Remove common suffixes
    $name = preg_replace('/(Test|Service|Controller|Manager|Component|Dashboard|Viewer|Panel|Page|Widget)$/', '', $testClass);

    // Split by camel case
    $parts = preg_split('/(?=[A-Z])/', $name, -1, PREG_SPLIT_NO_EMPTY);

    // Filter out very short parts and common prefixes
    $keywords = array_filter($parts, fn ($p) => strlen($p) > 2 && ! in_array(strtolower($p), ['test', 'the', 'and', 'for']));

    return array_map('strtolower', $keywords);
}

/**
 * Calculate keyword overlap score between two test names
 */
function keywordOverlapScore(array $keywords1, array $keywords2): float
{
    if (empty($keywords1) || empty($keywords2)) {
        return 0.0;
    }

    $intersection = array_intersect($keywords1, $keywords2);
    $union = array_unique(array_merge($keywords1, $keywords2));

    return count($intersection) / count($union);
}

/**
 * Find related tests (build parent-child relationships)
 *
 * Hierarchy: Browser (visual) → Livewire → Feature → Integration → Unit
 *
 * For each "higher level" test (browser, livewire), we find supporting tests
 * at lower levels that cover related functionality.
 */
function findRelatedTests(array $testEntry, array $allTests): array
{
    $related = [];
    $testClass = $testEntry['test_class'];
    $type = $testEntry['type'];
    $domain = $testEntry['domain'];

    // Extract base name without "Test" suffix
    $baseName = preg_replace('/Test$/', '', $testClass);
    $keywords = extractKeywords($testClass);

    // Define the hierarchy - higher level tests should find lower level related tests
    $typeHierarchy = [
        'browser_dusk' => ['feature_livewire', 'feature', 'integration', 'unit'],
        'feature_livewire' => ['feature', 'integration', 'unit'],
        'feature' => ['integration', 'unit'],
        'integration' => ['unit'],
        'unit' => [], // Unit tests don't have children
    ];

    $targetTypes = $typeHierarchy[$type] ?? [];

    if (empty($targetTypes)) {
        return [];
    }

    $candidates = [];

    foreach ($allTests as $other) {
        if ($other['id'] === $testEntry['id']) {
            continue;
        }

        // Only consider tests at lower levels in the hierarchy
        if (! in_array($other['type'], $targetTypes)) {
            continue;
        }

        $otherBaseName = preg_replace('/Test$/', '', $other['test_class']);
        $otherKeywords = extractKeywords($other['test_class']);

        $score = 0.0;

        // 1. Exact base name match (highest priority)
        if (strtolower($baseName) === strtolower($otherBaseName)) {
            $score = 1.0;
        }
        // 2. Base name contains other or vice versa
        elseif (str_contains(strtolower($baseName), strtolower($otherBaseName)) ||
                str_contains(strtolower($otherBaseName), strtolower($baseName))) {
            $score = 0.8;
        }
        // 3. Same domain with keyword overlap
        elseif ($other['domain'] === $domain) {
            $keywordScore = keywordOverlapScore($keywords, $otherKeywords);
            if ($keywordScore > 0.3) {
                $score = 0.5 + ($keywordScore * 0.3);
            }
        }
        // 4. Different domain but high keyword overlap
        else {
            $keywordScore = keywordOverlapScore($keywords, $otherKeywords);
            if ($keywordScore > 0.5) {
                $score = $keywordScore * 0.5;
            }
        }

        if ($score > 0.3) {
            $candidates[] = [
                'id' => $other['id'],
                'score' => $score,
                'type' => $other['type'],
            ];
        }
    }

    // Sort by score (descending), then by type hierarchy (prefer closer levels)
    usort($candidates, function ($a, $b) use ($targetTypes) {
        // First by score
        if ($a['score'] !== $b['score']) {
            return $b['score'] <=> $a['score'];
        }
        // Then by type hierarchy position (prefer closer related types)
        $aPos = array_search($a['type'], $targetTypes);
        $bPos = array_search($b['type'], $targetTypes);

        return $aPos <=> $bPos;
    });

    // Return top 15 related tests, grouped by type for clarity
    $result = [];
    $byType = [];

    foreach ($candidates as $candidate) {
        $byType[$candidate['type']][] = $candidate['id'];
    }

    // Take up to 5 from each type, prioritizing closer levels
    foreach ($targetTypes as $targetType) {
        if (isset($byType[$targetType])) {
            $result = array_merge($result, array_slice($byType[$targetType], 0, 5));
        }
    }

    return array_slice($result, 0, 15);
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================

echo "TDD Test Queue Generator\n";
echo "========================\n\n";

// Verify tests directory exists
if (! is_dir($testsDir)) {
    echo "ERROR: Tests directory not found: {$testsDir}\n";
    exit(1);
}

$testsDir = realpath($testsDir);
echo "Scanning: {$testsDir}\n\n";

// Collect all PHP test files
$allFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($testsDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $fullPath = $file->getPathname();
        $relativePath = str_replace($testsDir.'/', '', $fullPath);
        $filename = $file->getFilename();

        // Skip non-test files
        if (! str_ends_with($filename, 'Test.php') &&
            ! str_ends_with($filename, 'TestCase.php') &&
            $filename !== 'TestCase.php' &&
            $filename !== 'DuskTestCase.php' &&
            $filename !== 'CreatesApplication.php' &&
            $filename !== 'UsesTestDatabase.php') {

            // Skip helper files, concerns, fixtures, etc.
            if (str_contains($relativePath, 'Concerns/') ||
                str_contains($relativePath, 'Fixtures/') ||
                str_contains($relativePath, 'TestData/') ||
                str_contains($relativePath, 'Doubles/') ||
                str_contains($relativePath, 'Pages/') ||
                str_contains($relativePath, 'Builders/') ||
                str_contains($relativePath, 'Generators/') ||
                str_contains($relativePath, 'Providers/') ||
                str_contains($relativePath, 'Validators/')) {
                continue;
            }
        }

        // Only include actual test files
        if (str_ends_with($filename, 'Test.php')) {
            $allFiles[] = [
                'full_path' => $fullPath,
                'relative_path' => $relativePath,
                'filename' => $filename,
            ];
        }
    }
}

echo 'Found '.count($allFiles)." test files\n\n";

// Process and classify all tests
$tests = [];
$typeStats = [];
$domainStats = [];

foreach ($allFiles as $file) {
    $relativePath = $file['relative_path'];
    $filename = $file['filename'];

    $type = classifyTestType($relativePath);
    $domain = extractDomain($relativePath, $filename);
    $testClass = extractTestClass($filename);
    $id = generateTestId($type, $domain, $testClass);
    $sprint = assignSprint($type, $domain);

    // Count stats
    $typeStats[$type] = ($typeStats[$type] ?? 0) + 1;
    $domainStats[$domain] = ($domainStats[$domain] ?? 0) + 1;

    $tests[] = [
        'id' => $id,
        'sprint' => $sprint,
        'domain' => $domain,
        'type' => $type,
        'test_class' => $testClass,
        'test_path' => 'tests/'.$relativePath,
        'status' => 'todo',
        'iterations' => 0,
        'related_tests' => [], // Will be filled later
    ];
}

// Sort by sprint, then by type priority, then alphabetically
usort($tests, function ($a, $b) {
    // Sprint first
    if ($a['sprint'] !== $b['sprint']) {
        return $a['sprint'] <=> $b['sprint'];
    }

    // Type priority: browser → livewire → feature → integration → unit
    $typePriority = [
        'browser_dusk' => 1,
        'feature_livewire' => 2,
        'feature' => 3,
        'integration' => 4,
        'unit' => 5,
        'performance' => 6,
        'snapshot' => 7,
        'other' => 8,
    ];

    $aPriority = $typePriority[$a['type']] ?? 99;
    $bPriority = $typePriority[$b['type']] ?? 99;

    if ($aPriority !== $bPriority) {
        return $aPriority <=> $bPriority;
    }

    // Domain alphabetically
    if ($a['domain'] !== $b['domain']) {
        return $a['domain'] <=> $b['domain'];
    }

    // Test class alphabetically
    return $a['test_class'] <=> $b['test_class'];
});

// Build related tests relationships (for visual tests)
echo "Building test relationships...\n";
foreach ($tests as &$test) {
    if (in_array($test['type'], ['browser_dusk', 'feature_livewire'])) {
        $test['related_tests'] = findRelatedTests($test, $tests);
    }
}
unset($test);

// Generate sprint summaries
$sprintSummaries = [];
foreach ($tests as $test) {
    $sprint = $test['sprint'];
    if (! isset($sprintSummaries[$sprint])) {
        $sprintSummaries[$sprint] = [
            'total' => 0,
            'types' => [],
            'domains' => [],
        ];
    }
    $sprintSummaries[$sprint]['total']++;
    $sprintSummaries[$sprint]['types'][$test['type']] = ($sprintSummaries[$sprint]['types'][$test['type']] ?? 0) + 1;
    $sprintSummaries[$sprint]['domains'][$test['domain']] = ($sprintSummaries[$sprint]['domains'][$test['domain']] ?? 0) + 1;
}

// Build output structure
$output = [
    'generated_at' => date('c'),
    'total_tests' => count($tests),
    'stats' => [
        'by_type' => $typeStats,
        'by_domain' => $domainStats,
        'by_sprint' => array_map(fn ($s) => $s['total'], $sprintSummaries),
    ],
    'sprint_guide' => [
        1 => 'Core backend: Textract, Search, Vector stores, Graph (Unit tests)',
        2 => 'Feature/Livewire managers: VectorStoreManager, TextractManager, etc.',
        3 => 'Integration tests + Agents/Research pipelines',
        4 => 'Dashboards, Viewers, Timelines (Livewire + Browser)',
        5 => 'Security, Authentication, remaining Browser tests',
        6 => 'Performance, Snapshots, catch-all',
    ],
    'visual_groupings' => buildVisualGroupings($tests),
    'components' => $tests,
    'meta' => [
        'notes' => 'Auto-generated TDD queue. Agent should filter by sprint, pick components with status=todo, run TDD slices using ./scripts/run-focused-tests.sh <test_class>, then update status/iterations.',
        'test_command' => './scripts/run-focused-tests.sh <test_class>',
        'status_values' => ['todo', 'in_progress', 'done'],
        'hierarchy' => 'browser_dusk → feature_livewire → feature → integration → unit',
    ],
];

/**
 * Build visual groupings - Browser and Livewire tests as entry points with their chains
 */
function buildVisualGroupings(array $tests): array
{
    $groupings = [];

    // Index tests by ID for quick lookup
    $testsById = [];
    foreach ($tests as $test) {
        $testsById[$test['id']] = $test;
    }

    // Find all browser tests - these are the top-level visual entry points
    $browserTests = array_filter($tests, fn ($t) => $t['type'] === 'browser_dusk');

    foreach ($browserTests as $browser) {
        $chain = [
            'entry_point' => [
                'id' => $browser['id'],
                'test_class' => $browser['test_class'],
                'test_path' => $browser['test_path'],
                'domain' => $browser['domain'],
                'sprint' => $browser['sprint'],
            ],
            'supporting_tests' => [
                'livewire' => [],
                'feature' => [],
                'integration' => [],
                'unit' => [],
            ],
        ];

        // Collect related tests organized by type
        foreach ($browser['related_tests'] as $relatedId) {
            if (! isset($testsById[$relatedId])) {
                continue;
            }

            $related = $testsById[$relatedId];
            $category = match ($related['type']) {
                'feature_livewire' => 'livewire',
                'feature' => 'feature',
                'integration' => 'integration',
                'unit' => 'unit',
                default => null,
            };

            if ($category !== null) {
                $chain['supporting_tests'][$category][] = [
                    'id' => $related['id'],
                    'test_class' => $related['test_class'],
                    'test_path' => $related['test_path'],
                ];
            }
        }

        // Also check if there are Livewire tests with the same base name that have their own chains
        foreach ($browser['related_tests'] as $relatedId) {
            if (! isset($testsById[$relatedId])) {
                continue;
            }
            $related = $testsById[$relatedId];

            if ($related['type'] === 'feature_livewire' && ! empty($related['related_tests'])) {
                // Add the Livewire test's supporting tests to our chain
                foreach ($related['related_tests'] as $subRelatedId) {
                    if (! isset($testsById[$subRelatedId])) {
                        continue;
                    }
                    $subRelated = $testsById[$subRelatedId];

                    $category = match ($subRelated['type']) {
                        'integration' => 'integration',
                        'unit' => 'unit',
                        default => null,
                    };

                    if ($category !== null) {
                        // Avoid duplicates
                        $existing = array_column($chain['supporting_tests'][$category], 'id');
                        if (! in_array($subRelated['id'], $existing)) {
                            $chain['supporting_tests'][$category][] = [
                                'id' => $subRelated['id'],
                                'test_class' => $subRelated['test_class'],
                                'test_path' => $subRelated['test_path'],
                            ];
                        }
                    }
                }
            }
        }

        $groupings[] = $chain;
    }

    // Also add Livewire tests that don't have a corresponding Browser test
    $browserBaseNames = array_map(
        fn ($t) => strtolower(preg_replace('/Test$/', '', $t['test_class'])),
        $browserTests
    );

    $livewireTests = array_filter($tests, fn ($t) => $t['type'] === 'feature_livewire');

    foreach ($livewireTests as $livewire) {
        $baseName = strtolower(preg_replace('/Test$/', '', $livewire['test_class']));

        // Skip if there's a corresponding browser test
        if (in_array($baseName, $browserBaseNames)) {
            continue;
        }

        $chain = [
            'entry_point' => [
                'id' => $livewire['id'],
                'test_class' => $livewire['test_class'],
                'test_path' => $livewire['test_path'],
                'domain' => $livewire['domain'],
                'sprint' => $livewire['sprint'],
                'type' => 'livewire_only', // Mark that this doesn't have a browser test
            ],
            'supporting_tests' => [
                'livewire' => [],
                'feature' => [],
                'integration' => [],
                'unit' => [],
            ],
        ];

        foreach ($livewire['related_tests'] as $relatedId) {
            if (! isset($testsById[$relatedId])) {
                continue;
            }

            $related = $testsById[$relatedId];
            $category = match ($related['type']) {
                'feature' => 'feature',
                'integration' => 'integration',
                'unit' => 'unit',
                default => null,
            };

            if ($category !== null) {
                $chain['supporting_tests'][$category][] = [
                    'id' => $related['id'],
                    'test_class' => $related['test_class'],
                    'test_path' => $related['test_path'],
                ];
            }
        }

        $groupings[] = $chain;
    }

    return $groupings;
}

// Write JSON output
$json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
file_put_contents($outputFile, $json);

echo "\n";
echo "Statistics:\n";
echo "-----------\n";
echo 'Total test files: '.count($tests)."\n\n";

echo "By Type:\n";
arsort($typeStats);
foreach ($typeStats as $type => $count) {
    printf("  %-20s %d\n", $type, $count);
}

echo "\nBy Domain:\n";
arsort($domainStats);
foreach ($domainStats as $domain => $count) {
    printf("  %-25s %d\n", $domain, $count);
}

echo "\nBy Sprint:\n";
ksort($sprintSummaries);
foreach ($sprintSummaries as $sprint => $summary) {
    echo "  Sprint {$sprint}: {$summary['total']} tests\n";
}

echo "\n";
echo "Output written to: {$outputFile}\n";
echo "\n";
echo "Next steps:\n";
echo "1. Copy this script to your repo: scripts/generate-tdd-queue.php\n";
echo "2. Run: php scripts/generate-tdd-queue.php tests test-results/tdd-test-queue.json\n";
echo "3. Use the multi-component autoloop command with your TDD agent\n";
