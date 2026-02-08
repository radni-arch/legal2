<?php

// scripts/generate-tdd-visual-groups.php
//
// Walks tests/ tree, detects "visual" hotspots (Browser + Feature/Livewire tests),
// finds their lower-level "lackeys" (unit, integration, other feature tests),
// and emits a JSON file test-results/tdd-visual-groups.json with:
//  - groups: per-UI-component grouping
//  - sequence: flattened recommended TDD order across all groups
//
// Usage:
//   php scripts/generate-tdd-visual-groups.php

declare(strict_types=1);

$root = realpath(__DIR__.'/../tests');
if (! $root) {
    fwrite(STDERR, "Could not locate tests/ directory.\n");
    exit(1);
}

$ds = DIRECTORY_SEPARATOR;
$tests = [];

// 1. Collect all *Test.php files with basic metadata
$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($rii as $file) {
    /** @var SplFileInfo $file */
    if (! $file->isFile()) {
        continue;
    }

    $filename = $file->getFilename();
    if (! preg_match('/Test\.php$/', $filename)) {
        continue; // skip non-test php files
    }

    $fullPath = $file->getPathname();
    // Make path relative, starting with "tests/..."
    $relativePath = 'tests'.$ds.ltrim(str_replace($root.$ds, '', $fullPath), $ds);

    // Class and base names
    $className = preg_replace('/\.php$/', '', $filename);           // e.g. VectorStoreManagerTest
    $baseName = preg_replace('/Test$/', '', $className);           // e.g. VectorStoreManager
    $normalizedBase = strtolower($baseName);                        // e.g. vectorstoremanager

    // Classify by directory
    $type = 'other';
    $relUnix = str_replace($ds, '/', $relativePath); // normalize for matching

    if (str_starts_with($relUnix, 'tests/Browser/')) {
        $type = 'browser';
    } elseif (str_starts_with($relUnix, 'tests/Feature/Livewire/')) {
        $type = 'feature_livewire';
    } elseif (str_starts_with($relUnix, 'tests/Feature/')) {
        $type = 'feature';
    } elseif (str_starts_with($relUnix, 'tests/Integration/')) {
        $type = 'integration';
    } elseif (str_starts_with($relUnix, 'tests/Unit/')) {
        $type = 'unit';
    }

    $tests[] = [
        'path' => $relUnix,
        'type' => $type,
        'className' => $className,
        'baseName' => $baseName,
        'normalized' => $normalizedBase,
    ];
}

// 2. Identify "visual hotspots": Browser + Feature/Livewire tests
$groups = []; // keyed by normalized base name

foreach ($tests as $test) {
    if (! in_array($test['type'], ['browser', 'feature_livewire'], true)) {
        continue;
    }

    $id = $test['normalized']; // e.g. "vectorstoremanager"

    if (! isset($groups[$id])) {
        $groups[$id] = [
            'id' => $id,
            'name' => $test['baseName'],  // e.g. VectorStoreManager
            'visual_tests' => [],
            'supporting_tests' => [],
        ];
    }

    $groups[$id]['visual_tests'][] = [
        'path' => $test['path'],
        'type' => $test['type'],      // browser | feature_livewire
        'className' => $test['className'],
    ];
}

// 3. Attach "lackeys": unit / integration / other feature tests whose name contains the visual base
foreach ($groups as $id => &$group) {
    $needle = $id; // normalized base name, e.g. "vectorstoremanager"

    foreach ($tests as $test) {
        // skip visual tests; they're already recorded
        if (in_array($test['type'], ['browser', 'feature_livewire'], true)) {
            continue;
        }

        // simple heuristic: normalized name contains the visual base
        // e.g. "vectorstoremanagementintegration" contains "vectorstoremanag"?
        // this is conservative; we can loosen if needed.
        if (strpos($test['normalized'], $needle) !== false) {
            $group['supporting_tests'][] = [
                'path' => $test['path'],
                'type' => $test['type'],      // unit | integration | feature | other
                'className' => $test['className'],
            ];
        }
    }
}
unset($group); // break reference

// 4. Build a flattened "sequence" in recommended TDD order per group
//
// For each visual group, we recommend working bottom-up:
//  - unit
//  - integration
//  - feature (non-Livewire)
//  - feature_livewire (visual but PHP-level)
//  - browser (full Dusk visual)
//
// The sequence items are what your TDD autoloop agent will actually iterate over.
$sequence = [];

$typeOrder = [
    'unit' => 10,
    'integration' => 20,
    'feature' => 30,
    'feature_livewire' => 40,
    'browser' => 50,
    'other' => 60,
];

foreach ($groups as $groupId => $group) {
    // Supporting tests first
    $items = [];

    foreach ($group['supporting_tests'] as $t) {
        $items[] = [
            'group_id' => $groupId,
            'group_name' => $group['name'],
            'role' => 'supporting',
            'type' => $t['type'],
            'test_class' => $t['className'],
            'test_path' => $t['path'],
            'weight' => $typeOrder[$t['type']] ?? 100,
        ];
    }

    // Then visual tests (feature_livewire + browser)
    foreach ($group['visual_tests'] as $t) {
        $items[] = [
            'group_id' => $groupId,
            'group_name' => $group['name'],
            'role' => 'visual_hotspot',
            'type' => $t['type'],
            'test_class' => $t['className'],
            'test_path' => $t['path'],
            'weight' => $typeOrder[$t['type']] ?? 100,
        ];
    }

    // Sort items by weight (type order), then by class name
    usort($items, function (array $a, array $b): int {
        if ($a['weight'] === $b['weight']) {
            return strcmp($a['test_class'], $b['test_class']);
        }

        return $a['weight'] <=> $b['weight'];
    });

    foreach ($items as $item) {
        unset($item['weight']);
        $sequence[] = $item;
    }
}

// 5. Emit JSON file
$outDir = realpath(__DIR__.'/..').$ds.'test-results';
if (! is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

$outPath = $outDir.$ds.'tdd-visual-groups.json';

$output = [
    'generated_at' => date(DATE_ATOM),
    'tests_root' => 'tests',
    'summary' => [
        'total_tests' => count($tests),
        'visual_groups' => count($groups),
        'sequence_items' => count($sequence),
    ],
    'groups' => array_values($groups),
    'sequence' => $sequence,
];

file_put_contents($outPath, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Wrote visual TDD groups and sequence to:\n  {$outPath}\n";
echo 'Groups: '.count($groups).' | Sequence items: '.count($sequence)."\n";
