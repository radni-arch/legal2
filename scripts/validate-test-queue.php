#!/usr/bin/env php
<?php

/**
 * TDD Queue Schema Validator
 *
 * Validates test-results/tdd-test-queue.json structure and enforces
 * allowed mutations per the breadcrumb convention (S2-QUEUE-02).
 *
 * Usage:
 *   php scripts/validate-test-queue.php [options]
 *
 * Options:
 *   --file <path>    Path to queue file (default: test-results/tdd-test-queue.json)
 *   --strict         Treat warnings as errors
 *   --diff-check     Validate only changed components (compares with git HEAD)
 *   --help           Show this help message
 *
 * Exit codes:
 *   0 = valid
 *   1 = validation errors
 *   2 = file not found or parse error
 */

declare(strict_types=1);

// Configuration
const VALID_STATUSES = ['todo', 'in_progress', 'done'];
const VALID_TYPES = ['unit', 'feature', 'feature_livewire', 'integration', 'browser_dusk', 'performance', 'snapshot'];
const VALID_SPRINTS = [1, 2, 3, 4, 5, 6];

// Status transition rules
const VALID_TRANSITIONS = [
    'todo' => ['in_progress'],
    'in_progress' => ['done', 'in_progress'], // can stay in_progress
    'done' => ['in_progress'], // can reopen, but not back to todo
];

// Fields that may be modified
const MUTABLE_FIELDS = ['status', 'iterations'];

// Fields that must not change
const IMMUTABLE_FIELDS = ['id', 'sprint', 'domain', 'type', 'test_class', 'test_path'];

class QueueValidator
{
    private array $errors = [];

    private array $warnings = [];

    private bool $strict = false;

    private bool $diffCheck = false;

    private string $filePath;

    public function __construct(string $filePath, bool $strict = false, bool $diffCheck = false)
    {
        $this->filePath = $filePath;
        $this->strict = $strict;
        $this->diffCheck = $diffCheck;
    }

    public function validate(): bool
    {
        // Check file exists
        if (! file_exists($this->filePath)) {
            $this->error("File not found: {$this->filePath}");

            return false;
        }

        // Parse JSON
        $content = file_get_contents($this->filePath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON: '.json_last_error_msg());

            return false;
        }

        // Validate structure
        $this->validateStructure($data);

        // Validate components
        if (isset($data['components']) && is_array($data['components'])) {
            foreach ($data['components'] as $index => $component) {
                $this->validateComponent($component, $index);
            }
        }

        // If diff-check, validate transitions
        if ($this->diffCheck) {
            $this->validateDiffChanges($data);
        }

        return $this->isValid();
    }

    private function validateStructure(array $data): void
    {
        // Required top-level keys
        $requiredKeys = ['components'];
        foreach ($requiredKeys as $key) {
            if (! isset($data[$key])) {
                $this->error("Missing required key: \$.{$key}");
            }
        }

        // Components must be array
        if (isset($data['components']) && ! is_array($data['components'])) {
            $this->error('$.components must be an array');
        }

        // Protected sections should not be manually edited (warning only)
        $protectedSections = ['stats', 'visual_groupings', 'sprint_guide', 'meta'];
        // We can't detect edits here without diff, so just note they exist
    }

    private function validateComponent(array $component, int $index): void
    {
        $path = "$.components[{$index}]";

        // Required fields
        $requiredFields = ['id', 'sprint', 'domain', 'type', 'test_class', 'test_path', 'status', 'iterations'];
        foreach ($requiredFields as $field) {
            if (! isset($component[$field])) {
                $this->error("{$path}.{$field} is required");
            }
        }

        // Validate id format: type:ClassName
        if (isset($component['id'])) {
            if (! preg_match('/^[a-z_]+:[A-Za-z0-9_]+$/', $component['id'])) {
                $this->error("{$path}.id must match pattern 'type:ClassName', got: {$component['id']}");
            }
        }

        // Validate status
        if (isset($component['status'])) {
            if (! in_array($component['status'], VALID_STATUSES, true)) {
                $this->error("{$path}.status must be one of: ".implode(', ', VALID_STATUSES).", got: {$component['status']}");
            }
        }

        // Validate type
        if (isset($component['type'])) {
            if (! in_array($component['type'], VALID_TYPES, true)) {
                $this->error("{$path}.type must be one of: ".implode(', ', VALID_TYPES).", got: {$component['type']}");
            }
        }

        // Validate sprint
        if (isset($component['sprint'])) {
            if (! is_int($component['sprint']) || ! in_array($component['sprint'], VALID_SPRINTS, true)) {
                $this->error("{$path}.sprint must be integer 1-6, got: ".json_encode($component['sprint']));
            }
        }

        // Validate iterations (must be non-negative integer)
        if (isset($component['iterations'])) {
            if (! is_int($component['iterations']) || $component['iterations'] < 0) {
                $this->error("{$path}.iterations must be integer >= 0, got: ".json_encode($component['iterations']));
            }
        }

        // Validate related_tests is array if present
        if (isset($component['related_tests']) && ! is_array($component['related_tests'])) {
            $this->error("{$path}.related_tests must be an array");
        }
    }

    private function validateDiffChanges(array $currentData): void
    {
        // Get previous version from git
        $previousContent = $this->getGitHeadContent();
        if ($previousContent === null) {
            $this->warn('Could not get previous version from git, skipping diff validation');

            return;
        }

        $previousData = json_decode($previousContent, true);
        if ($previousData === null) {
            $this->warn('Previous version is not valid JSON, skipping diff validation');

            return;
        }

        // Index components by ID for comparison
        $previousComponents = $this->indexById($previousData['components'] ?? []);
        $currentComponents = $this->indexById($currentData['components'] ?? []);

        // Check for modified components
        foreach ($currentComponents as $id => $current) {
            if (! isset($previousComponents[$id])) {
                // New component - allowed
                continue;
            }

            $previous = $previousComponents[$id];
            $this->validateComponentChanges($id, $previous, $current);
        }

        // Check for deleted components (warning)
        foreach ($previousComponents as $id => $previous) {
            if (! isset($currentComponents[$id])) {
                $this->warn("Component deleted: {$id}");
            }
        }

        // Check protected sections for changes
        $protectedSections = ['stats', 'visual_groupings', 'sprint_guide', 'meta'];
        foreach ($protectedSections as $section) {
            $prevSection = $previousData[$section] ?? null;
            $currSection = $currentData[$section] ?? null;
            if (json_encode($prevSection) !== json_encode($currSection)) {
                $this->error("$.{$section} must not be modified");
            }
        }
    }

    private function validateComponentChanges(string $id, array $previous, array $current): void
    {
        // Check immutable fields
        foreach (IMMUTABLE_FIELDS as $field) {
            $prevVal = $previous[$field] ?? null;
            $currVal = $current[$field] ?? null;
            if ($prevVal !== $currVal) {
                $this->error("Component '{$id}': field '{$field}' is immutable, cannot change from ".
                    json_encode($prevVal).' to '.json_encode($currVal));
            }
        }

        // Validate status transition
        $prevStatus = $previous['status'] ?? null;
        $currStatus = $current['status'] ?? null;
        if ($prevStatus !== null && $currStatus !== null && $prevStatus !== $currStatus) {
            $allowedTransitions = VALID_TRANSITIONS[$prevStatus] ?? [];
            if (! in_array($currStatus, $allowedTransitions, true)) {
                $this->error("Component '{$id}': invalid status transition '{$prevStatus}' -> '{$currStatus}'. ".
                    'Allowed: '.implode(', ', $allowedTransitions));
            }
        }

        // Validate iterations can only increase
        $prevIter = $previous['iterations'] ?? 0;
        $currIter = $current['iterations'] ?? 0;
        if ($currIter < $prevIter) {
            $this->error("Component '{$id}': iterations cannot decrease from {$prevIter} to {$currIter}");
        }
    }

    private function getGitHeadContent(): ?string
    {
        $relativePath = $this->getRelativePath();
        $output = [];
        $returnCode = 0;

        exec("git show HEAD:{$relativePath} 2>/dev/null", $output, $returnCode);

        if ($returnCode !== 0) {
            return null;
        }

        return implode("\n", $output);
    }

    private function getRelativePath(): string
    {
        // Get path relative to git root
        $gitRoot = trim(shell_exec('git rev-parse --show-toplevel 2>/dev/null') ?? '');
        if (empty($gitRoot)) {
            return $this->filePath;
        }

        $absolutePath = realpath($this->filePath) ?: $this->filePath;
        if (str_starts_with($absolutePath, $gitRoot)) {
            return substr($absolutePath, strlen($gitRoot) + 1);
        }

        return $this->filePath;
    }

    private function indexById(array $components): array
    {
        $indexed = [];
        foreach ($components as $component) {
            if (isset($component['id'])) {
                $indexed[$component['id']] = $component;
            }
        }

        return $indexed;
    }

    private function error(string $message): void
    {
        $this->errors[] = $message;
    }

    private function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function isValid(): bool
    {
        if ($this->strict) {
            return empty($this->errors) && empty($this->warnings);
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function printResults(): void
    {
        if (! empty($this->errors)) {
            echo "\033[31mErrors:\033[0m\n";
            foreach ($this->errors as $error) {
                echo "  \033[31m✗\033[0m {$error}\n";
            }
        }

        if (! empty($this->warnings)) {
            echo "\033[33mWarnings:\033[0m\n";
            foreach ($this->warnings as $warning) {
                echo "  \033[33m⚠\033[0m {$warning}\n";
            }
        }

        if ($this->isValid()) {
            echo "\033[32m✓ Queue file is valid\033[0m\n";
        } else {
            $errorCount = count($this->errors);
            $warnCount = count($this->warnings);
            echo "\n\033[31m✗ Validation failed: {$errorCount} error(s)";
            if ($warnCount > 0) {
                echo ", {$warnCount} warning(s)";
            }
            echo "\033[0m\n";
        }
    }
}

// CLI handling
function showHelp(): void
{
    echo <<<'HELP'
TDD Queue Schema Validator

Usage:
  php scripts/validate-test-queue.php [options]

Options:
  --file <path>    Path to queue file (default: test-results/tdd-test-queue.json)
  --strict         Treat warnings as errors
  --diff-check     Validate only changed components (compares with git HEAD)
  --help           Show this help message

Rules enforced:
  - JSON must be well-formed
  - Required fields: id, sprint, domain, type, test_class, test_path, status, iterations
  - status must be: todo, in_progress, done
  - iterations must be integer >= 0
  - sprint must be integer 1-6
  - type must be: unit, feature, feature_livewire, integration, browser_dusk, performance, snapshot

With --diff-check:
  - Immutable fields cannot change: id, sprint, domain, type, test_class, test_path
  - Valid status transitions: todo -> in_progress -> done
  - iterations can only increase
  - Protected sections cannot be modified: stats, visual_groupings, sprint_guide, meta

Exit codes:
  0 = valid
  1 = validation errors
  2 = file not found or parse error

HELP;
}

function main(): int
{
    global $argv;

    // Parse arguments
    $filePath = 'test-results/tdd-test-queue.json';
    $strict = false;
    $diffCheck = false;

    $i = 1;
    while ($i < count($argv)) {
        switch ($argv[$i]) {
            case '--file':
                $filePath = $argv[++$i] ?? '';
                break;
            case '--strict':
                $strict = true;
                break;
            case '--diff-check':
                $diffCheck = true;
                break;
            case '--help':
            case '-h':
                showHelp();

                return 0;
            default:
                if (str_starts_with($argv[$i], '-')) {
                    echo "Unknown option: {$argv[$i]}\n";

                    return 2;
                }
        }
        $i++;
    }

    // Run validation
    $validator = new QueueValidator($filePath, $strict, $diffCheck);
    $valid = $validator->validate();
    $validator->printResults();

    return $valid ? 0 : 1;
}

exit(main());
