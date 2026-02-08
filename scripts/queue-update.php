#!/usr/bin/env php
<?php

/**
 * Safe Queue Update Helper
 *
 * Atomic updates to TDD queue with file locking and validation.
 * Only allows modifications to status and iterations fields.
 *
 * Usage:
 *   php scripts/queue-update.php --id <component-id> --status <status>
 *   php scripts/queue-update.php --id <component-id> --iterations <n>
 *   php scripts/queue-update.php --id <component-id> --increment-iterations
 *
 * Options:
 *   --id <id>              Component ID to update (required)
 *   --status <status>      Set status (todo, in_progress, done)
 *   --iterations <n>       Set iterations to specific value
 *   --increment-iterations Increment iterations by 1
 *   --file <path>          Queue file path (default: test-results/tdd-test-queue.json)
 *   --dry-run              Show changes without writing
 *   --no-validate          Skip post-write validation (not recommended)
 *   --help                 Show help
 *
 * Examples:
 *   php scripts/queue-update.php --id unit:FooTest --status in_progress
 *   php scripts/queue-update.php --id unit:FooTest --increment-iterations
 *   php scripts/queue-update.php --id unit:FooTest --status done --iterations 3
 */

declare(strict_types=1);

const VALID_STATUSES = ['todo', 'in_progress', 'done'];
const DEFAULT_QUEUE_FILE = 'test-results/tdd-test-queue.json';
const LOCK_TIMEOUT_SECONDS = 10;

class QueueUpdater
{
    private string $queueFile;

    private string $componentId;

    private ?string $newStatus = null;

    private ?int $newIterations = null;

    private bool $incrementIterations = false;

    private bool $dryRun = false;

    private bool $validate = true;

    private array $errors = [];

    private ?string $backupContent = null;

    public function __construct(array $options)
    {
        $this->queueFile = $options['file'] ?? DEFAULT_QUEUE_FILE;
        $this->componentId = $options['id'] ?? '';
        $this->newStatus = $options['status'] ?? null;
        $this->newIterations = isset($options['iterations']) ? (int) $options['iterations'] : null;
        $this->incrementIterations = $options['increment-iterations'] ?? false;
        $this->dryRun = $options['dry-run'] ?? false;
        $this->validate = ! ($options['no-validate'] ?? false);
    }

    public function validate(): bool
    {
        if (empty($this->componentId)) {
            $this->errors[] = 'Missing required: --id <component-id>';
        }

        if ($this->newStatus === null && $this->newIterations === null && ! $this->incrementIterations) {
            $this->errors[] = 'Must specify at least one of: --status, --iterations, --increment-iterations';
        }

        if ($this->newStatus !== null && ! in_array($this->newStatus, VALID_STATUSES, true)) {
            $this->errors[] = "Invalid status '{$this->newStatus}'. Valid: ".implode(', ', VALID_STATUSES);
        }

        if ($this->newIterations !== null && $this->newIterations < 0) {
            $this->errors[] = 'Iterations must be >= 0';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function run(): bool
    {
        if (! $this->validate()) {
            return false;
        }

        // Check file exists
        if (! file_exists($this->queueFile)) {
            $this->errors[] = "Queue file not found: {$this->queueFile}";

            return false;
        }

        // Acquire file lock
        $lockFile = $this->queueFile.'.lock';
        $lockHandle = $this->acquireLock($lockFile);
        if ($lockHandle === false) {
            $this->errors[] = 'Could not acquire file lock (timeout after '.LOCK_TIMEOUT_SECONDS.'s)';

            return false;
        }

        try {
            $result = $this->performUpdate();
        } finally {
            $this->releaseLock($lockHandle, $lockFile);
        }

        return $result;
    }

    private function acquireLock(string $lockFile): mixed
    {
        $handle = fopen($lockFile, 'c');
        if ($handle === false) {
            return false;
        }

        $startTime = time();
        while (! flock($handle, LOCK_EX | LOCK_NB)) {
            if (time() - $startTime > LOCK_TIMEOUT_SECONDS) {
                fclose($handle);

                return false;
            }
            usleep(100000); // 100ms
        }

        return $handle;
    }

    private function releaseLock(mixed $handle, string $lockFile): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
        @unlink($lockFile);
    }

    private function performUpdate(): bool
    {
        // Read and backup original content
        $this->backupContent = file_get_contents($this->queueFile);
        if ($this->backupContent === false) {
            $this->errors[] = 'Could not read queue file';

            return false;
        }

        // Parse JSON
        $queue = json_decode($this->backupContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errors[] = 'Queue file is malformed JSON: '.json_last_error_msg();

            return false;
        }

        if (! isset($queue['components']) || ! is_array($queue['components'])) {
            $this->errors[] = "Queue file missing 'components' array";

            return false;
        }

        // Find component
        $componentIndex = $this->findComponentIndex($queue['components']);
        if ($componentIndex === null) {
            $this->errors[] = "Component not found: {$this->componentId}";

            return false;
        }

        $component = &$queue['components'][$componentIndex];
        $changes = [];

        // Apply status change
        if ($this->newStatus !== null) {
            $oldStatus = $component['status'] ?? 'unknown';
            if ($oldStatus !== $this->newStatus) {
                $changes[] = "status: {$oldStatus} → {$this->newStatus}";
                $component['status'] = $this->newStatus;
            }
        }

        // Apply iterations change
        $oldIterations = $component['iterations'] ?? 0;
        if ($this->incrementIterations) {
            $component['iterations'] = $oldIterations + 1;
            $changes[] = "iterations: {$oldIterations} → {$component['iterations']}";
        } elseif ($this->newIterations !== null && $this->newIterations !== $oldIterations) {
            $component['iterations'] = $this->newIterations;
            $changes[] = "iterations: {$oldIterations} → {$this->newIterations}";
        }

        if (empty($changes)) {
            echo "\033[34mℹ\033[0m No changes needed for {$this->componentId}\n";

            return true;
        }

        if ($this->dryRun) {
            $this->printDryRun($changes);

            return true;
        }

        // Write updated JSON
        $jsonOptions = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $newContent = json_encode($queue, $jsonOptions);

        // Ensure file ends with newline for consistency
        if (! str_ends_with($newContent, "\n")) {
            $newContent .= "\n";
        }

        // Atomic write: write to temp file, then rename
        $tempFile = $this->queueFile.'.tmp.'.getmypid();
        if (file_put_contents($tempFile, $newContent) === false) {
            $this->errors[] = 'Could not write temporary file';
            @unlink($tempFile);

            return false;
        }

        if (! rename($tempFile, $this->queueFile)) {
            $this->errors[] = 'Could not atomically rename temp file';
            @unlink($tempFile);

            return false;
        }

        echo "\033[32m✓\033[0m Updated {$this->componentId}:\n";
        foreach ($changes as $change) {
            echo "    {$change}\n";
        }

        // Run validator
        if ($this->validate) {
            if (! $this->runValidator()) {
                // Rollback
                echo "\033[31m✗\033[0m Validation failed, rolling back...\n";
                file_put_contents($this->queueFile, $this->backupContent);

                return false;
            }
            echo "\033[32m✓\033[0m Validation passed\n";
        }

        return true;
    }

    private function findComponentIndex(array $components): ?int
    {
        foreach ($components as $index => $component) {
            if (($component['id'] ?? '') === $this->componentId) {
                return $index;
            }
        }

        return null;
    }

    private function runValidator(): bool
    {
        $validator = dirname($this->queueFile).'/../scripts/validate-test-queue.php';
        if (! file_exists($validator)) {
            $validator = 'scripts/validate-test-queue.php';
        }

        if (! file_exists($validator)) {
            echo "\033[33m⚠\033[0m Validator not found, skipping validation\n";

            return true;
        }

        $output = [];
        $returnCode = 0;
        exec("php {$validator} --file ".escapeshellarg($this->queueFile).' --strict 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            foreach ($output as $line) {
                echo "  {$line}\n";
            }

            return false;
        }

        return true;
    }

    private function printDryRun(array $changes): void
    {
        echo "\033[34m═══ Dry Run ═══\033[0m\n\n";
        echo "Would update {$this->componentId}:\n";
        foreach ($changes as $change) {
            echo "    {$change}\n";
        }
        echo "\n\033[34mRun without --dry-run to apply changes.\033[0m\n";
    }
}

// CLI handling
function showHelp(): void
{
    echo <<<'HELP'
Safe Queue Update Helper

Atomic updates to TDD queue with file locking and validation.
Only allows modifications to status and iterations fields.

Usage:
  php scripts/queue-update.php --id <component-id> --status <status>
  php scripts/queue-update.php --id <component-id> --iterations <n>
  php scripts/queue-update.php --id <component-id> --increment-iterations

Options:
  --id <id>              Component ID to update (required)
  --status <status>      Set status (todo, in_progress, done)
  --iterations <n>       Set iterations to specific value
  --increment-iterations Increment iterations by 1
  --file <path>          Queue file path (default: test-results/tdd-test-queue.json)
  --dry-run              Show changes without writing
  --no-validate          Skip post-write validation (not recommended)
  --help                 Show help

Examples:
  php scripts/queue-update.php --id unit:FooTest --status in_progress
  php scripts/queue-update.php --id unit:FooTest --increment-iterations
  php scripts/queue-update.php --id unit:FooTest --status done --iterations 3

Features:
  - Atomic writes (temp file + rename)
  - File locking to prevent concurrent writes
  - Automatic validation after write
  - Rollback on validation failure
  - Dry-run mode for preview

HELP;
}

function parseArgs(array $argv): array
{
    $options = [];
    $i = 1;

    while ($i < count($argv)) {
        $arg = $argv[$i];

        switch ($arg) {
            case '--id':
                $options['id'] = $argv[++$i] ?? '';
                break;
            case '--status':
                $options['status'] = $argv[++$i] ?? '';
                break;
            case '--iterations':
                $options['iterations'] = $argv[++$i] ?? 0;
                break;
            case '--increment-iterations':
                $options['increment-iterations'] = true;
                break;
            case '--file':
                $options['file'] = $argv[++$i] ?? '';
                break;
            case '--dry-run':
                $options['dry-run'] = true;
                break;
            case '--no-validate':
                $options['no-validate'] = true;
                break;
            case '--help':
            case '-h':
                showHelp();
                exit(0);
        }
        $i++;
    }

    return $options;
}

function main(): int
{
    global $argv;

    if (count($argv) < 2) {
        showHelp();

        return 1;
    }

    $options = parseArgs($argv);
    $updater = new QueueUpdater($options);

    if (! $updater->run()) {
        echo "\033[31mErrors:\033[0m\n";
        foreach ($updater->getErrors() as $error) {
            echo "  \033[31m✗\033[0m {$error}\n";
        }

        return 1;
    }

    return 0;
}

exit(main());
