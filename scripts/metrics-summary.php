#!/usr/bin/env php
<?php
/**
 * Metrics Summary Tool
 *
 * Analyzes TDD autoloop metrics for trend visibility.
 * Tracks: failure rate, durations, retry frequency, component hotspots.
 *
 * Usage:
 *   php scripts/metrics-summary.php --since 7d
 *   php scripts/metrics-summary.php --since 24h --component "unit:SomeTest"
 *   php scripts/metrics-summary.php --json
 *
 * Options:
 *   --since <period>      Filter by time period (7d, 24h, 2025-01-01)
 *   --component <id>      Filter by component ID (supports partial match)
 *   --domain <name>       Filter by domain
 *   --type <type>         Filter by test type (unit, feature, integration)
 *   --json                Output as JSON instead of formatted text
 *   --hotspots [N]        Show top N failure hotspots (default: 10)
 *   --help                Show this help
 */

define('METRICS_FILE', __DIR__ . '/../test-results/metrics/autoloop.jsonl');

// Parse command line arguments
$options = [
    'since' => null,
    'component' => null,
    'domain' => null,
    'type' => null,
    'json' => false,
    'hotspots' => 10,
    'help' => false,
];

$args = array_slice($argv, 1);
for ($i = 0; $i < count($args); $i++) {
    $arg = $args[$i];
    switch ($arg) {
        case '--since':
            $options['since'] = $args[++$i] ?? null;
            break;
        case '--component':
            $options['component'] = $args[++$i] ?? null;
            break;
        case '--domain':
            $options['domain'] = $args[++$i] ?? null;
            break;
        case '--type':
            $options['type'] = $args[++$i] ?? null;
            break;
        case '--json':
            $options['json'] = true;
            break;
        case '--hotspots':
            $options['hotspots'] = (int)($args[++$i] ?? 10);
            break;
        case '--help':
        case '-h':
            $options['help'] = true;
            break;
    }
}

if ($options['help']) {
    echo <<<HELP
Metrics Summary Tool

Usage:
  php scripts/metrics-summary.php [options]

Options:
  --since <period>      Filter by time period
                        Examples: 7d (7 days), 24h (24 hours), 2025-01-01
  --component <id>      Filter by component ID (partial match supported)
  --domain <name>       Filter by domain name
  --type <type>         Filter by test type (unit, feature, integration)
  --json                Output as JSON instead of formatted text
  --hotspots [N]        Show top N failure hotspots (default: 10)
  --help, -h            Show this help

Examples:
  php scripts/metrics-summary.php --since 7d
  php scripts/metrics-summary.php --since 24h --component "SomeTest"
  php scripts/metrics-summary.php --domain search_services --json
  php scripts/metrics-summary.php --hotspots 5

Output includes:
  - Total runs, passes, failures
  - Failure rate percentage
  - Average duration
  - Retry statistics
  - Component hotspots (most failures)
  - Trend over time (by day)

HELP;
    exit(0);
}

/**
 * Parse relative time period to DateTime
 */
function parseSince(?string $since): ?DateTime {
    if ($since === null) {
        return null;
    }

    // Parse relative periods
    if (preg_match('/^(\d+)d$/', $since, $matches)) {
        return (new DateTime())->modify("-{$matches[1]} days");
    }
    if (preg_match('/^(\d+)h$/', $since, $matches)) {
        return (new DateTime())->modify("-{$matches[1]} hours");
    }
    if (preg_match('/^(\d+)w$/', $since, $matches)) {
        return (new DateTime())->modify("-{$matches[1]} weeks");
    }

    // Try parsing as date
    try {
        return new DateTime($since);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Load and filter metrics from JSONL file
 */
function loadMetrics(array $options): array {
    if (!file_exists(METRICS_FILE)) {
        return [];
    }

    $sinceDate = parseSince($options['since']);
    $metrics = [];

    $handle = fopen(METRICS_FILE, 'r');
    if (!$handle) {
        return [];
    }

    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }

        $entry = json_decode($line, true);
        if (!$entry) {
            continue;
        }

        // Apply filters
        if ($sinceDate !== null) {
            $entryDate = new DateTime($entry['timestamp'] ?? 'now');
            if ($entryDate < $sinceDate) {
                continue;
            }
        }

        if ($options['component'] !== null) {
            $componentId = $entry['component_id'] ?? '';
            $componentName = $entry['component_name'] ?? '';
            if (stripos($componentId, $options['component']) === false &&
                stripos($componentName, $options['component']) === false) {
                continue;
            }
        }

        if ($options['domain'] !== null) {
            $domain = $entry['domain'] ?? 'unknown';
            if (stripos($domain, $options['domain']) === false) {
                continue;
            }
        }

        if ($options['type'] !== null) {
            $type = $entry['test_type'] ?? 'unknown';
            if (stripos($type, $options['type']) === false) {
                continue;
            }
        }

        $metrics[] = $entry;
    }

    fclose($handle);
    return $metrics;
}

/**
 * Calculate summary statistics
 */
function calculateSummary(array $metrics): array {
    $total = count($metrics);
    if ($total === 0) {
        return [
            'total_runs' => 0,
            'passes' => 0,
            'failures' => 0,
            'errors' => 0,
            'skips' => 0,
            'failure_rate' => 0,
            'avg_duration' => 0,
            'total_duration' => 0,
            'total_retries' => 0,
            'runs_with_retries' => 0,
            'retry_rate' => 0,
        ];
    }

    $passes = 0;
    $failures = 0;
    $errors = 0;
    $skips = 0;
    $totalDuration = 0;
    $totalRetries = 0;
    $runsWithRetries = 0;

    foreach ($metrics as $entry) {
        $outcome = $entry['outcome'] ?? 'unknown';
        switch ($outcome) {
            case 'pass':
                $passes++;
                break;
            case 'fail':
                $failures++;
                break;
            case 'error':
                $errors++;
                break;
            case 'skip':
                $skips++;
                break;
        }

        $totalDuration += $entry['duration_seconds'] ?? 0;

        $retryCount = $entry['retry_count'] ?? 0;
        $totalRetries += $retryCount;
        if ($retryCount > 0) {
            $runsWithRetries++;
        }
    }

    return [
        'total_runs' => $total,
        'passes' => $passes,
        'failures' => $failures,
        'errors' => $errors,
        'skips' => $skips,
        'failure_rate' => round(($failures + $errors) / $total * 100, 2),
        'avg_duration' => round($totalDuration / $total, 2),
        'total_duration' => $totalDuration,
        'total_retries' => $totalRetries,
        'runs_with_retries' => $runsWithRetries,
        'retry_rate' => round($runsWithRetries / $total * 100, 2),
    ];
}

/**
 * Find component hotspots (most failures)
 */
function findHotspots(array $metrics, int $limit): array {
    $componentFailures = [];

    foreach ($metrics as $entry) {
        $outcome = $entry['outcome'] ?? 'unknown';
        if ($outcome === 'fail' || $outcome === 'error') {
            $componentId = $entry['component_id'] ?? 'unknown';
            if (!isset($componentFailures[$componentId])) {
                $componentFailures[$componentId] = [
                    'component_id' => $componentId,
                    'failures' => 0,
                    'total_runs' => 0,
                    'last_failure' => null,
                ];
            }
            $componentFailures[$componentId]['failures']++;
            $componentFailures[$componentId]['last_failure'] = $entry['timestamp'] ?? null;
        }
    }

    // Count total runs per component
    foreach ($metrics as $entry) {
        $componentId = $entry['component_id'] ?? 'unknown';
        if (isset($componentFailures[$componentId])) {
            $componentFailures[$componentId]['total_runs']++;
        }
    }

    // Calculate failure rate per component
    foreach ($componentFailures as &$component) {
        $component['failure_rate'] = $component['total_runs'] > 0
            ? round($component['failures'] / $component['total_runs'] * 100, 2)
            : 0;
    }

    // Sort by failure count descending
    usort($componentFailures, fn($a, $b) => $b['failures'] - $a['failures']);

    return array_slice($componentFailures, 0, $limit);
}

/**
 * Calculate trend by day
 */
function calculateTrend(array $metrics): array {
    $byDay = [];

    foreach ($metrics as $entry) {
        $date = $entry['date'] ?? date('Y-m-d');
        if (!isset($byDay[$date])) {
            $byDay[$date] = ['runs' => 0, 'passes' => 0, 'failures' => 0];
        }
        $byDay[$date]['runs']++;

        $outcome = $entry['outcome'] ?? 'unknown';
        if ($outcome === 'pass') {
            $byDay[$date]['passes']++;
        } elseif ($outcome === 'fail' || $outcome === 'error') {
            $byDay[$date]['failures']++;
        }
    }

    // Sort by date
    ksort($byDay);

    // Calculate failure rate per day
    $trend = [];
    foreach ($byDay as $date => $stats) {
        $trend[] = [
            'date' => $date,
            'runs' => $stats['runs'],
            'passes' => $stats['passes'],
            'failures' => $stats['failures'],
            'failure_rate' => $stats['runs'] > 0
                ? round($stats['failures'] / $stats['runs'] * 100, 2)
                : 0,
        ];
    }

    return $trend;
}

/**
 * Find retry reasons distribution
 */
function retryReasons(array $metrics): array {
    $reasons = [];

    foreach ($metrics as $entry) {
        $retryCount = $entry['retry_count'] ?? 0;
        if ($retryCount > 0) {
            $reason = $entry['retry_reason'] ?? 'unknown';
            if ($reason === '') {
                $reason = 'unknown';
            }
            if (!isset($reasons[$reason])) {
                $reasons[$reason] = 0;
            }
            $reasons[$reason] += $retryCount;
        }
    }

    arsort($reasons);
    return $reasons;
}

/**
 * Format duration for display
 */
function formatDuration(int $seconds): string {
    if ($seconds < 60) {
        return "{$seconds}s";
    }
    if ($seconds < 3600) {
        $min = floor($seconds / 60);
        $sec = $seconds % 60;
        return "{$min}m {$sec}s";
    }
    $hours = floor($seconds / 3600);
    $min = floor(($seconds % 3600) / 60);
    return "{$hours}h {$min}m";
}

/**
 * Print formatted output
 */
function printFormatted(array $summary, array $hotspots, array $trend, array $retryReasons, array $options): void {
    $since = $options['since'] ?? 'all time';

    echo "═══════════════════════════════════════════════════════════════\n";
    echo "                    METRICS SUMMARY\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "Period: $since\n";
    if ($options['component']) {
        echo "Component filter: {$options['component']}\n";
    }
    if ($options['domain']) {
        echo "Domain filter: {$options['domain']}\n";
    }
    echo "\n";

    // Overall stats
    echo "─────────────────────────────────────────────────────────────────\n";
    echo "                      OVERALL STATS\n";
    echo "─────────────────────────────────────────────────────────────────\n";
    printf("  Total runs:       %d\n", $summary['total_runs']);
    printf("  Passes:           %d (%.1f%%)\n", $summary['passes'],
        $summary['total_runs'] > 0 ? $summary['passes'] / $summary['total_runs'] * 100 : 0);
    printf("  Failures:         %d (%.1f%%)\n", $summary['failures'], $summary['failure_rate']);
    printf("  Errors:           %d\n", $summary['errors']);
    echo "\n";
    printf("  Avg duration:     %s\n", formatDuration((int)$summary['avg_duration']));
    printf("  Total duration:   %s\n", formatDuration($summary['total_duration']));
    echo "\n";
    printf("  Total retries:    %d\n", $summary['total_retries']);
    printf("  Runs w/ retries:  %d (%.1f%%)\n", $summary['runs_with_retries'], $summary['retry_rate']);
    echo "\n";

    // Hotspots
    if (!empty($hotspots)) {
        echo "─────────────────────────────────────────────────────────────────\n";
        echo "                    FAILURE HOTSPOTS\n";
        echo "─────────────────────────────────────────────────────────────────\n";
        foreach ($hotspots as $i => $hs) {
            printf("  %2d. %-40s %d failures (%.1f%%)\n",
                $i + 1,
                substr($hs['component_id'], 0, 40),
                $hs['failures'],
                $hs['failure_rate']
            );
        }
        echo "\n";
    }

    // Retry reasons
    if (!empty($retryReasons)) {
        echo "─────────────────────────────────────────────────────────────────\n";
        echo "                    RETRY REASONS\n";
        echo "─────────────────────────────────────────────────────────────────\n";
        foreach ($retryReasons as $reason => $count) {
            printf("  %-45s %d\n", substr($reason, 0, 45), $count);
        }
        echo "\n";
    }

    // Trend
    if (!empty($trend) && count($trend) > 1) {
        echo "─────────────────────────────────────────────────────────────────\n";
        echo "                    DAILY TREND\n";
        echo "─────────────────────────────────────────────────────────────────\n";
        echo "  Date          Runs    Pass    Fail    Rate\n";
        foreach (array_slice($trend, -7) as $day) { // Last 7 days
            printf("  %-12s  %4d    %4d    %4d    %.1f%%\n",
                $day['date'],
                $day['runs'],
                $day['passes'],
                $day['failures'],
                $day['failure_rate']
            );
        }
        echo "\n";
    }

    echo "═══════════════════════════════════════════════════════════════\n";
}

// Main execution
$metrics = loadMetrics($options);

if (empty($metrics)) {
    if ($options['json']) {
        echo json_encode(['error' => 'No metrics data found', 'file' => METRICS_FILE]) . "\n";
    } else {
        echo "No metrics data found.\n";
        echo "Metrics file: " . METRICS_FILE . "\n";
        echo "Run some tests with ./scripts/run-focused-tests.sh to generate metrics.\n";
    }
    exit(0);
}

$summary = calculateSummary($metrics);
$hotspots = findHotspots($metrics, $options['hotspots']);
$trend = calculateTrend($metrics);
$retryReasonsData = retryReasons($metrics);

if ($options['json']) {
    echo json_encode([
        'period' => $options['since'] ?? 'all',
        'filters' => array_filter([
            'component' => $options['component'],
            'domain' => $options['domain'],
            'type' => $options['type'],
        ]),
        'summary' => $summary,
        'hotspots' => $hotspots,
        'trend' => $trend,
        'retry_reasons' => $retryReasonsData,
    ], JSON_PRETTY_PRINT) . "\n";
} else {
    printFormatted($summary, $hotspots, $trend, $retryReasonsData, $options);
}
