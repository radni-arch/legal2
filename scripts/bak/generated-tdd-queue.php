<?php

$root = realpath(__DIR__.'/../tests');
if ($root === false) {
    fwrite(STDERR, "Could not locate tests directory.\n");
    exit(1);
}

$dirIter = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
$iter = new RecursiveIteratorIterator($dirIter);

$components = [];

foreach ($iter as $file) {
    if (! $file->isFile()) {
        continue;
    }

    $path = $file->getPathname();

    // Only consider *Test.php files
    if (! preg_match('/Test\.php$/', $path)) {
        continue;
    }

    // Convert absolute path to repo-relative (starting at tests/)
    $relative = str_replace($root.DIRECTORY_SEPARATOR, '', $path);
    $segments = explode(DIRECTORY_SEPARATOR, $relative);
    if (count($segments) === 0) {
        continue;
    }

    $top = strtolower($segments[0]); // Unit, Feature, Integration, Browser, etc. -> lowercased

    // Infer type and domain from directory structure
    $type = $top; // basic default: unit, feature, integration, browser
    $domain = $top;

    // Example refinements (you can edit these as needed):
    if ($top === 'feature' && isset($segments[1]) && strtolower($segments[1]) === 'livewire') {
        $type = 'feature_livewire';
        $domain = 'livewire';
    }

    // Class name & id
    $testClass = basename($relative, '.php');
    $id = $type.':'.$testClass;

    $components[] = [
        'id' => $id,
        'sprint' => 99,                  // default: unassigned; set manually for high-priority components
        'domain' => $domain,
        'type' => $type,
        'test_class' => $testClass,
        'test_path' => 'tests/'.str_replace(DIRECTORY_SEPARATOR, '/', $relative),
        'status' => 'todo',
        'iterations' => 0,
    ];
}

$out = [
    'components' => $components,
    'meta' => [
        'generated_at' => gmdate('c'),
        'notes' => 'Auto-generated TDD queue. Set proper sprint/domain/type/status for prioritized components. This file is intended as a starting point; you may copy or merge entries into tdd-test-queue.json.',
    ],
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
