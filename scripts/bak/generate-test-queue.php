<?php

/**
 * Script: generate-test-queue.php
 * -------------------------------
 * Scans the 'tests' directory and generates a JSON structure grouping all tests
 * by feature, with hierarchy from unit to browser tests.
 *
 * Usage:
 *   php scripts/generate-test-queue.php > test-queue.json
 */

// 1. Gather all test files
$testDir = __DIR__.'/../tests';  // Adjust path if needed
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testDir));
$testFiles = [];
foreach ($iterator as $file) {
    if ($file->isFile() && preg_match('/Test\.php$/', $file->getFilename())) {
        $path = str_replace('\\', '/', $file->getPathname());
        // Normalize path (optional: remove project base path)
        $testFiles[] = $path;
    }
}

// 2. Categorize test files by type
$testsByType = [
    'browser_tests' => [],  // Laravel Dusk tests (Browser)
    'livewire_tests' => [],  // Livewire component tests (subset of Feature)
    'feature_tests' => [],  // Other feature tests (HTTP controllers, API, console)
    'integration_tests' => [], // Integration tests
    'unit_tests' => [],  // Unit tests
    'other_tests' => [],   // Any tests not in above categories (e.g., Performance, Snapshots)
];
foreach ($testFiles as $filePath) {
    $relativePath = str_replace(rtrim($testDir, '/').'/', '', $filePath);
    $className = basename($filePath, '.php');
    if (strpos($relativePath, 'tests/Browser/') === 0) {
        $testsByType['browser_tests'][$className] = $relativePath;
    } elseif (strpos($relativePath, 'tests/Feature/Livewire/') === 0) {
        $testsByType['livewire_tests'][$className] = $relativePath;
    } elseif (strpos($relativePath, 'tests/Feature/') === 0) {
        $testsByType['feature_tests'][$className] = $relativePath;
    } elseif (strpos($relativePath, 'tests/Integration/') === 0) {
        $testsByType['integration_tests'][$className] = $relativePath;
    } elseif (strpos($relativePath, 'tests/Unit/') === 0) {
        $testsByType['unit_tests'][$className] = $relativePath;
    } else {
        $testsByType['other_tests'][$className] = $relativePath;
    }
}

// 3. Define feature grouping patterns
$featureClusters = [
    'GraphVisualization' => ['GraphViewer', 'GraphDashboard', 'GraphViewerMetrics', 'GraphViewerConcurrency'],
    'TextractProcessing' => ['TextractManager', 'TextractPipeline', 'Textract', 'TextractPdf'],  // covers Textract tests
    'DecisionDiscovery' => ['DecisionDiscovery', 'OdlukeSearchAgent'],  // DecisionDiscovery & related Odluke tests
    'VectorStoreManagement' => ['VectorStoreManager', 'OpenAIVectorManager', 'VectorStoreManagement'],  // vector store UI & integration
    'Collaboration' => ['Collaboration', 'AgentCollaboration', 'MultiUserCollaboration'],
    'Timeline' => ['Timeline', 'ParallelTimeline', 'ComparativeTimeline', 'GupTimeline', 'ChronologyBuilder'],
    'LawIngestion' => ['IngestedLawsManager', 'LawDownload', 'LawImport', 'LawsIngest'],
    'UnifiedSearch' => ['UnifiedSearch', 'HomeSearch', 'SearchPipeline'],
    'LegalPlayground' => ['LegalPlayground', 'ReasoningChain'],
    'FeedbackLearning' => ['FeedbackDashboard', 'LearningOpportunity', 'FeedbackLoop', 'LearningOpportunityManager'],
    'Misconduct' => ['Misconduct'],       // Misconduct dashboard & module
    'Eoglasna' => ['Eoglasna'],         // Eoglasna monitoring
    'Epredmet' => ['Epredmet'],         // e-Predmet widget
    // Add more clusters as needed for other feature prefixes
];

// 4. Initialize cluster groups structure
$queue = [];
foreach ($featureClusters as $clusterId => $patterns) {
    $queue[$clusterId] = [
        'id' => $clusterId,
        'unit_tests' => [],
        'integration_tests' => [],
        'feature_tests' => [],
        'livewire_tests' => [],
        'browser_tests' => [],
    ];
}

// 5. Assign tests to clusters based on patterns
$assignedTests = [];
foreach ($featureClusters as $clusterId => $patterns) {
    foreach ($patterns as $pattern) {
        // Build a regex to match classes containing the pattern (case-sensitive substring match)
        $regex = '/'.preg_quote($pattern, '/').'/';
        foreach ($testsByType as $type => $tests) {
            foreach ($tests as $className => $path) {
                if (isset($assignedTests[$className])) {
                    continue;
                }  // skip already assigned
                if (preg_match($regex, $className)) {
                    // Assign to this cluster under appropriate category
                    if (isset($queue[$clusterId])) {
                        if ($type === 'browser_tests') {
                            $queue[$clusterId]['browser_tests'][] = $className;
                        } elseif ($type === 'livewire_tests') {
                            $queue[$clusterId]['livewire_tests'][] = $className;
                        } elseif ($type === 'feature_tests') {
                            $queue[$clusterId]['feature_tests'][] = $className;
                        } elseif ($type === 'integration_tests') {
                            $queue[$clusterId]['integration_tests'][] = $className;
                        } elseif ($type === 'unit_tests') {
                            $queue[$clusterId]['unit_tests'][] = $className;
                        } else {
                            $queue[$clusterId]['feature_tests'][] = $className;  // treat 'other' as feature by default
                        }
                        $assignedTests[$className] = true;
                    }
                }
            }
        }
    }
}

// 6. Any unassigned tests become their own cluster entry
foreach ($testsByType as $type => $tests) {
    foreach ($tests as $className => $path) {
        if (isset($assignedTests[$className])) {
            continue;
        }
        // Create a new cluster for this standalone test
        $clusterId = $className;  // using class name (without 'Test') might be more readable, but className includes 'Test'
        $clusterId = preg_replace('/Test$/', '', $clusterId);  // remove trailing "Test"
        if (! isset($queue[$clusterId])) {
            $queue[$clusterId] = [
                'id' => $clusterId,
                'unit_tests' => [],
                'integration_tests' => [],
                'feature_tests' => [],
                'livewire_tests' => [],
                'browser_tests' => [],
            ];
        }
        // Assign this test into its category in its own cluster
        if ($type === 'browser_tests') {
            $queue[$clusterId]['browser_tests'][] = $className;
        } elseif ($type === 'livewire_tests') {
            $queue[$clusterId]['livewire_tests'][] = $className;
        } elseif ($type === 'feature_tests') {
            $queue[$clusterId]['feature_tests'][] = $className;
        } elseif ($type === 'integration_tests') {
            $queue[$clusterId]['integration_tests'][] = $className;
        } elseif ($type === 'unit_tests') {
            $queue[$clusterId]['unit_tests'][] = $className;
        } else {
            $queue[$clusterId]['feature_tests'][] = $className;
        }
        $assignedTests[$className] = true;
    }
}

// 7. Sort tests within each category and sort clusters by id
foreach ($queue as $clusterId => &$cluster) {
    sort($cluster['unit_tests']);
    sort($cluster['integration_tests']);
    sort($cluster['feature_tests']);
    sort($cluster['livewire_tests']);
    sort($cluster['browser_tests']);
}
unset($cluster);
ksort($queue);

// 8. Output as JSON
echo json_encode(array_values($queue), JSON_PRETTY_PRINT);
