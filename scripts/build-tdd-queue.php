#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * build-tdd-queue.php
 *
 * Scans tests/ recursively, indexes all *Test.php files,
 * classifies them (suite/type/domain/sprint), identifies visual hotspots
 * (Browser + Feature/Livewire), attaches lower-level "lackey" tests based on
 * name token overlap, and writes test-results/tdd-test-queue.json.
 *
 * Usage:
 *   php bin/build-tdd-queue.php
 */
$rootDir = realpath(__DIR__.'/..');
$testsDir = $rootDir.'/tests';
$outDir = $rootDir.'/test-results';
$outFile = $outDir.'/tdd-test-queue.json';

if (! is_dir($testsDir)) {
    fwrite(STDERR, "Error: tests directory not found at $testsDir\n");
    exit(1);
}
if (! is_dir($outDir) && ! mkdir($outDir, 0777, true) && ! is_dir($outDir)) {
    fwrite(STDERR, "Error: could not create directory $outDir\n");
    exit(1);
}

/**
 * Split CamelCase into tokens.
 */
function camelCaseToTokens(string $str): array
{
    if ($str === '') {
        return [];
    }
    $parts = preg_split('/(?<!^)(?=[A-Z])/', $str) ?: [];
    $tokens = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') {
            $tokens[] = strtolower($p);
        }
    }

    return array_values(array_unique($tokens));
}

/**
 * Normalize a base name by stripping generic UI suffixes.
 */
function normalizeBase(string $base): string
{
    // Remove common UI suffixes: Manager, Dashboard, Page, Viewer, Widget, Component, Panel, Timeline
    $normalized = preg_replace('/(Manager|Dashboard|Page|Viewer|Widget|Component|Panel|Timeline)$/', '', $base);

    return $normalized ?? $base;
}

/**
 * Detect top-level suite from relative path.
 */
function detectSuite(string $relPath): string
{
    $parts = explode('/', $relPath);
    if (count($parts) < 2) {
        return 'unknown';
    }

    return $parts[1]; // e.g. 'Unit', 'Feature', 'Browser', 'Integration', 'Performance'
}

/**
 * Detect type from suite + path.
 */
function detectType(string $suite, string $relPath): string
{
    $suiteLower = strtolower($suite);
    $pathLower = strtolower($relPath);

    if ($suiteLower === 'browser') {
        return 'browser_dusk';
    }
    if ($suiteLower === 'feature') {
        if (strpos($pathLower, 'feature/livewire/') !== false) {
            return 'feature_livewire';
        }

        return 'feature';
    }
    if ($suiteLower === 'integration') {
        return 'integration';
    }
    if ($suiteLower === 'unit') {
        return 'unit';
    }
    if ($suiteLower === 'performance') {
        return 'performance';
    }

    return 'other';
}

/**
 * Rough domain inference from path + class.
 */
function detectDomain(string $relPath, string $classStub): string
{
    $p = strtolower($relPath);
    $c = strtolower($classStub);

    if (strpos($p, 'textract') !== false || strpos($c, 'textract') !== false) {
        return 'textract_pipeline';
    }
    if (strpos($p, 'search') !== false || strpos($c, 'search') !== false ||
        strpos($p, 'vectorstore') !== false || strpos($c, 'vectorstore') !== false ||
        strpos($p, 'vector_store') !== false || strpos($c, 'vector_store') !== false
    ) {
        return 'search_and_vector_stores';
    }
    if (strpos($p, 'livewire') !== false &&
        (strpos($p, 'graph') !== false || strpos($p, 'timeline') !== false ||
            strpos($c, 'graph') !== false || strpos($c, 'timeline') !== false)
    ) {
        return 'graph_and_dashboards';
    }
    if (strpos($p, 'graph') !== false || strpos($c, 'graph') !== false ||
        strpos($p, 'neo4j') !== false || strpos($c, 'neo4j') !== false
    ) {
        return 'graph_and_dashboards';
    }
    if (strpos($p, 'topic') !== false || strpos($c, 'topic') !== false) {
        return 'topics_and_analytics';
    }
    if (strpos($p, 'agent') !== false || strpos($p, 'agents') !== false ||
        strpos($p, 'research') !== false || strpos($c, 'agent') !== false ||
        strpos($c, 'research') !== false
    ) {
        return 'agents_and_research';
    }
    if (strpos($p, 'security') !== false || strpos($c, 'security') !== false ||
        strpos($c, 'xss') !== false || strpos($c, 'promptinjection') !== false
    ) {
        return 'security_and_hardening';
    }
    if (strpos($p, 'workflow') !== false || strpos($c, 'workflow') !== false) {
        return 'workflows';
    }

    return 'misc';
}

/**
 * Map (type, domain) to sprint number.
 */
function assignSprint(string $type, string $domain): int
{
    switch ($domain) {
        case 'textract_pipeline':
        case 'search_and_vector_stores':
            if (in_array($type, ['unit', 'integration'], true)) {
                return 1;
            }
            if (in_array($type, ['feature', 'feature_livewire'], true)) {
                return 2;
            }
            if ($type === 'browser_dusk') {
                return 4;
            }

            return 3;

        case 'graph_and_dashboards':
            if (in_array($type, ['unit', 'integration'], true)) {
                return 2;
            }
            if (in_array($type, ['feature', 'feature_livewire'], true)) {
                return 2;
            }
            if ($type === 'browser_dusk') {
                return 4;
            }

            return 3;

        case 'agents_and_research':
            if (in_array($type, ['unit', 'integration', 'feature'], true)) {
                return 3;
            }
            if ($type === 'browser_dusk') {
                return 5;
            }

            return 4;

        case 'topics_and_analytics':
            if (in_array($type, ['unit', 'integration'], true)) {
                return 3;
            }
            if (in_array($type, ['feature', 'feature_livewire'], true)) {
                return 3;
            }
            if ($type === 'browser_dusk') {
                return 4;
            }

            return 3;

        case 'security_and_hardening':
            return 5;

        case 'workflows':
            if (in_array($type, ['integration', 'feature'], true)) {
                return 4;
            }
            if ($type === 'browser_dusk') {
                return 4;
            }

            return 4;

        default: // misc
            if ($type === 'unit') {
                return 3;
            }
            if (in_array($type, ['feature', 'integration'], true)) {
                return 3;
            }
            if ($type === 'browser_dusk') {
                return 4;
            }
            if ($type === 'performance') {
                return 5;
            }

            return 3;
    }
}

/**
 * Compute a simple dependency map: for each hotspot, which tests are "lackeys".
 *
 * @param  array<int,array>  $tests
 * @return array<string,string[]> map hotspotIndex => list of dependent test_class names
 */
function computeDependencies(array $tests): array
{
    $deps = [];

    // Precompute tokens per test.
    $tokensPerIndex = [];
    foreach ($tests as $i => $t) {
        $tokensPerIndex[$i] = $t['tokens'];
    }

    foreach ($tests as $i => $hot) {
        $type = $hot['type'];
        if (! in_array($type, ['browser_dusk', 'feature_livewire'], true)) {
            continue; // only treat these as hotspots
        }

        $hotTokens = $tokensPerIndex[$i];
        if (empty($hotTokens)) {
            continue;
        }

        $hotBaseLower = strtolower($hot['base']);
        $deps[$i] = [];

        foreach ($tests as $j => $cand) {
            if ($i === $j) {
                continue;
            }

            $candType = $cand['type'];

            // Don't treat other hotspots as lackeys; we want lower levels.
            if (in_array($candType, ['browser_dusk', 'feature_livewire'], true)) {
                continue;
            }

            $candTokens = $tokensPerIndex[$j];

            // Name-based hard link: substring in either direction.
            $candBaseLower = strtolower($cand['base']);
            $nameLinked = (strpos($candBaseLower, $hotBaseLower) !== false ||
                strpos($hotBaseLower, $candBaseLower) !== false);

            // Token overlap.
            $intersect = array_intersect($hotTokens, $candTokens);
            $score = count($intersect);

            if ($nameLinked || $score >= 1) {
                $deps[$i][] = $cand['test_class'];
            }
        }

        // Deduplicate
        $deps[$i] = array_values(array_unique($deps[$i]));
    }

    return $deps;
}

/**
 * Build full index of tests.
 *
 * @return array<int,array>
 */
function buildTestIndex(string $rootDir, string $testsDir): array
{
    $tests = [];

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($testsDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($it as $fileInfo) {
        /** @var SplFileInfo $fileInfo */
        if (! $fileInfo->isFile()) {
            continue;
        }
        $filename = $fileInfo->getFilename();
        if (! str_ends_with($filename, 'Test.php')) {
            continue;
        }

        $fullPath = $fileInfo->getRealPath();
        if ($fullPath === false) {
            continue;
        }

        // normalize slashes and get relative path from repo root
        $fullPathNorm = str_replace('\\', '/', $fullPath);
        $rootNorm = str_replace('\\', '/', $rootDir);
        if (! str_starts_with($fullPathNorm, $rootNorm)) {
            continue;
        }
        $relPath = ltrim(substr($fullPathNorm, strlen($rootNorm)), '/');

        $classStub = substr($filename, 0, -4); // remove .php
        $base = preg_replace('/Test$/', '', $classStub) ?? $classStub;
        $normalizedBase = normalizeBase($base);
        $tokens = camelCaseToTokens($normalizedBase);

        $suite = detectSuite($relPath);
        $type = detectType($suite, $relPath);
        $domain = detectDomain($relPath, $classStub);
        $sprint = assignSprint($type, $domain);

        $tests[] = [
            'test_class' => $classStub,
            'test_path' => $relPath,
            'suite' => $suite,
            'type' => $type,
            'domain' => $domain,
            'sprint' => $sprint,
            'base' => $base,
            'normalized_base' => $normalizedBase,
            'tokens' => $tokens,
        ];
    }

    return $tests;
}

// -----------------------------------------------------------------------------
// Main
// -----------------------------------------------------------------------------

echo "Scanning tests under: $testsDir\n";
$tests = buildTestIndex($rootDir, $testsDir);
$total = count($tests);
echo "Discovered $total test files.\n";

if ($total === 0) {
    fwrite(STDERR, "No *Test.php files found under tests/; aborting.\n");
    exit(1);
}

// Compute dependencies for hotspots.
echo "Computing hotspot dependencies...\n";
$depsMap = computeDependencies($tests);

// Build queue entries.
$queueComponents = [];
foreach ($tests as $idx => $t) {
    $id = $t['type'].':'.$t['base'];

    $entry = [
        'id' => $id,
        'sprint' => $t['sprint'],
        'domain' => $t['domain'],
        'type' => $t['type'],
        'test_class' => $t['test_class'],
        'test_path' => $t['test_path'],
        'status' => 'todo',
        'iterations' => 0,
    ];

    if (isset($depsMap[$idx]) && ! empty($depsMap[$idx])) {
        $entry['dependencies'] = $depsMap[$idx];
    }

    $queueComponents[] = $entry;
}

$queue = [
    'components' => $queueComponents,
    'meta' => [
        'generated_at' => gmdate('c'),
        'notes' => 'AI-driven TDD queue generated by bin/build-tdd-queue.php; every test file is present. '.
            'Hotspots (browser_dusk + feature_livewire) include "dependencies" listing lower-level tests '.
            'whose names/tokens overlap.',
    ],
];

$json = json_encode($queue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fwrite(STDERR, 'Error: failed to encode JSON: '.json_last_error_msg()."\n");
    exit(1);
}

if (file_put_contents($outFile, $json) === false) {
    fwrite(STDERR, "Error: failed to write $outFile\n");
    exit(1);
}

echo "Wrote queue to: $outFile\n";
echo "Done.\n";
