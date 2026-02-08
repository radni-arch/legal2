#!/usr/bin/env php
<?php

/**
 * TDD Queue Component Scaffolding Script
 *
 * Adds a new component to the TDD queue and creates placeholder test files.
 * All entries are validator-compliant from creation.
 *
 * Usage:
 *   php scripts/add-queue-component.php --name <TestClass> --type <type> [options]
 *
 * Required:
 *   --name <TestClass>    Test class name (e.g., MyNewServiceTest)
 *   --type <type>         Test type: unit, feature, feature_livewire, integration,
 *                         browser_dusk, performance, snapshot
 *
 * Optional:
 *   --sprint <N>          Sprint number 1-6 (default: 1)
 *   --domain <domain>     Domain tag (default: "general")
 *   --path <path>         Custom test path (auto-generated if not provided)
 *   --related <ids>       Comma-separated related test IDs
 *   --no-test             Don't create placeholder test file
 *   --note <text>         Add note to session-state.md
 *   --dry-run             Show what would be done without making changes
 *   --help                Show this help message
 *
 * Examples:
 *   php scripts/add-queue-component.php --name VectorCacheTest --type unit --domain vector_stores
 *   php scripts/add-queue-component.php --name ChatManagerTest --type feature_livewire --sprint 2
 */

declare(strict_types=1);

// Configuration
const VALID_TYPES = [
    'unit' => 'tests/Unit',
    'feature' => 'tests/Feature',
    'feature_livewire' => 'tests/Feature/Livewire',
    'integration' => 'tests/Integration',
    'browser_dusk' => 'tests/Browser',
    'performance' => 'tests/Performance',
    'snapshot' => 'tests/Snapshots',
];

const VALID_SPRINTS = [1, 2, 3, 4, 5, 6];
const QUEUE_FILE = 'test-results/tdd-test-queue.json';
const SESSION_STATE_FILE = '.claude/session-state.md';

class ComponentScaffolder
{
    private string $name;

    private string $type;

    private int $sprint = 1;

    private string $domain = 'general';

    private ?string $customPath = null;

    private array $related = [];

    private bool $createTest = true;

    private ?string $note = null;

    private bool $dryRun = false;

    private array $errors = [];

    public function __construct(array $options)
    {
        $this->name = $options['name'] ?? '';
        $this->type = $options['type'] ?? '';
        $this->sprint = (int) ($options['sprint'] ?? 1);
        $this->domain = $options['domain'] ?? 'general';
        $this->customPath = $options['path'] ?? null;
        $this->related = ! empty($options['related'])
            ? array_map('trim', explode(',', $options['related']))
            : [];
        $this->createTest = ! ($options['no-test'] ?? false);
        $this->note = $options['note'] ?? null;
        $this->dryRun = $options['dry-run'] ?? false;
    }

    public function validate(): bool
    {
        // Name validation
        if (empty($this->name)) {
            $this->errors[] = 'Missing required: --name <TestClass>';
        } elseif (! preg_match('/^[A-Z][A-Za-z0-9]+Test$/', $this->name)) {
            $this->errors[] = "Name must be PascalCase ending in 'Test' (e.g., MyServiceTest)";
        }

        // Type validation
        if (empty($this->type)) {
            $this->errors[] = 'Missing required: --type <type>';
        } elseif (! isset(VALID_TYPES[$this->type])) {
            $this->errors[] = "Invalid type '{$this->type}'. Valid: ".implode(', ', array_keys(VALID_TYPES));
        }

        // Sprint validation
        if (! in_array($this->sprint, VALID_SPRINTS, true)) {
            $this->errors[] = "Sprint must be 1-6, got: {$this->sprint}";
        }

        // Domain validation (basic sanity check)
        if (! preg_match('/^[a-z_]+$/', $this->domain)) {
            $this->errors[] = 'Domain must be lowercase with underscores (e.g., vector_stores)';
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

        // Check queue file exists
        if (! file_exists(QUEUE_FILE)) {
            $this->errors[] = 'Queue file not found: '.QUEUE_FILE;

            return false;
        }

        // Load and parse queue
        $content = file_get_contents(QUEUE_FILE);
        $queue = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errors[] = 'Queue file is malformed JSON: '.json_last_error_msg();

            return false;
        }

        if (! isset($queue['components']) || ! is_array($queue['components'])) {
            $this->errors[] = "Queue file missing 'components' array";

            return false;
        }

        // Build component entry
        $component = $this->buildComponent();

        // Check for duplicate ID
        foreach ($queue['components'] as $existing) {
            if (($existing['id'] ?? '') === $component['id']) {
                $this->errors[] = "Component already exists: {$component['id']}";

                return false;
            }
        }

        if ($this->dryRun) {
            $this->printDryRun($component);

            return true;
        }

        // Add component to queue
        $queue['components'][] = $component;

        // Write queue file
        $jsonOptions = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
        if (file_put_contents(QUEUE_FILE, json_encode($queue, $jsonOptions)."\n") === false) {
            $this->errors[] = 'Failed to write queue file';

            return false;
        }

        echo "\033[32m✓\033[0m Added component: {$component['id']}\n";

        // Create placeholder test file
        if ($this->createTest) {
            $this->createPlaceholderTest($component);
        }

        // Add session note if requested
        if ($this->note !== null) {
            $this->addSessionNote($component);
        }

        // Validate the result
        $this->runValidator();

        return true;
    }

    private function buildComponent(): array
    {
        $testPath = $this->customPath ?? $this->inferTestPath();
        $idPrefix = $this->getIdPrefix();

        return [
            'id' => "{$idPrefix}:{$this->name}",
            'sprint' => $this->sprint,
            'domain' => $this->domain,
            'type' => $this->type,
            'test_class' => $this->name,
            'test_path' => $testPath,
            'status' => 'todo',
            'iterations' => 0,
            'related_tests' => $this->related,
        ];
    }

    private function getIdPrefix(): string
    {
        // Map type to id prefix
        return match ($this->type) {
            'feature_livewire' => 'livewire',
            'browser_dusk' => 'browser',
            default => $this->type,
        };
    }

    private function inferTestPath(): string
    {
        $baseDir = VALID_TYPES[$this->type];

        // For certain types, add subdirectory based on domain
        $subDir = match ($this->type) {
            'unit' => $this->inferUnitSubdir(),
            'feature' => '',
            'integration' => '',
            default => '',
        };

        $path = $baseDir;
        if (! empty($subDir)) {
            $path .= '/'.$subDir;
        }

        return $path.'/'.$this->name.'.php';
    }

    private function inferUnitSubdir(): string
    {
        // Infer subdirectory from domain
        return match ($this->domain) {
            'vector_stores' => 'Services',
            'graph_and_neo4j' => 'Services/Graph',
            'search_services' => 'Services/Search',
            'textract_pipeline' => 'Services/Textract',
            'agents_and_research' => 'Agents',
            'legal_reasoning' => 'Services/Legal',
            'openai_and_llm' => 'Services/AI',
            default => '',
        };
    }

    private function createPlaceholderTest(array $component): void
    {
        $path = $component['test_path'];
        $dir = dirname($path);

        // Create directory if needed
        if (! is_dir($dir)) {
            if (! mkdir($dir, 0755, true)) {
                echo "\033[33m⚠\033[0m Could not create directory: {$dir}\n";

                return;
            }
        }

        // Check if file already exists
        if (file_exists($path)) {
            echo "\033[34mℹ\033[0m Test file already exists: {$path}\n";

            return;
        }

        // Generate placeholder content
        $namespace = $this->inferNamespace($path);
        $content = $this->generateTestContent($component, $namespace);

        if (file_put_contents($path, $content) === false) {
            echo "\033[33m⚠\033[0m Could not create test file: {$path}\n";

            return;
        }

        echo "\033[32m✓\033[0m Created placeholder test: {$path}\n";
    }

    private function inferNamespace(string $path): string
    {
        // Convert path to namespace
        // tests/Unit/Services/FooTest.php -> Tests\Unit\Services
        $dir = dirname($path);
        $parts = explode('/', $dir);

        // Capitalize each part
        $parts = array_map(function ($part) {
            return ucfirst($part);
        }, $parts);

        return implode('\\', $parts);
    }

    private function generateTestContent(array $component, string $namespace): string
    {
        $className = $component['test_class'];
        $baseClass = $this->getBaseTestClass();
        $useStatement = $this->getUseStatement();

        return <<<PHP
<?php

namespace {$namespace};

{$useStatement}

/**
 * Placeholder test for {$className}
 *
 * Component ID: {$component['id']}
 * Domain: {$component['domain']}
 * Sprint: {$component['sprint']}
 *
 * @see documentation/queue-breadcrumbs.md for TDD workflow
 */
class {$className} extends {$baseClass}
{
    /**
     * @test
     * @todo Implement first test case
     */
    public function it_exists(): void
    {
        \$this->markTestIncomplete('Placeholder - implement TDD slices');
    }

    // Add test methods following RED → GREEN → REFACTOR cycle
    // Use: ./scripts/run-focused-tests.sh {$className}
}

PHP;
    }

    private function getBaseTestClass(): string
    {
        return match ($this->type) {
            'browser_dusk' => 'DuskTestCase',
            default => 'TestCase',
        };
    }

    private function getUseStatement(): string
    {
        return match ($this->type) {
            'browser_dusk' => 'use Tests\DuskTestCase;',
            default => 'use Tests\TestCase;',
        };
    }

    private function addSessionNote(array $component): void
    {
        if (! file_exists(SESSION_STATE_FILE)) {
            echo "\033[33m⚠\033[0m Session state file not found: ".SESSION_STATE_FILE."\n";

            return;
        }

        $timestamp = date('Y-m-d H:i');
        $note = <<<MD

## {$timestamp}
- Sprint: S{$component['sprint']}
- Components touched: [{$component['id']}]
- Last test command: n/a (new component)
- Status: Scaffolded new component. {$this->note}
- Blockers: none
- Next steps:
  - Implement first TDD slice
  - Run: ./scripts/run-focused-tests.sh {$component['test_class']}
- Autoloop notes: n/a

MD;

        if (file_put_contents(SESSION_STATE_FILE, $note, FILE_APPEND) === false) {
            echo "\033[33m⚠\033[0m Could not append to session state file\n";

            return;
        }

        echo "\033[32m✓\033[0m Added session note to ".SESSION_STATE_FILE."\n";
    }

    private function runValidator(): void
    {
        $validator = 'scripts/validate-test-queue.php';
        if (! file_exists($validator)) {
            return;
        }

        $output = [];
        $returnCode = 0;
        exec("php {$validator} --strict 2>&1", $output, $returnCode);

        if ($returnCode === 0) {
            echo "\033[32m✓\033[0m Validator passed\n";
        } else {
            echo "\033[33m⚠\033[0m Validator reported issues:\n";
            foreach ($output as $line) {
                echo "  {$line}\n";
            }
        }
    }

    private function printDryRun(array $component): void
    {
        echo "\033[34m═══ Dry Run ═══\033[0m\n\n";

        echo "Would add component:\n";
        echo json_encode($component, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n\n";

        if ($this->createTest) {
            echo "Would create test file:\n";
            echo "  {$component['test_path']}\n\n";
        }

        if ($this->note !== null) {
            echo "Would add session note:\n";
            echo "  {$this->note}\n\n";
        }

        echo "\033[34mRun without --dry-run to apply changes.\033[0m\n";
    }
}

// CLI handling
function showHelp(): void
{
    echo <<<'HELP'
TDD Queue Component Scaffolding Script

Usage:
  php scripts/add-queue-component.php --name <TestClass> --type <type> [options]

Required:
  --name <TestClass>    Test class name (e.g., MyNewServiceTest)
  --type <type>         Test type: unit, feature, feature_livewire, integration,
                        browser_dusk, performance, snapshot

Optional:
  --sprint <N>          Sprint number 1-6 (default: 1)
  --domain <domain>     Domain tag (default: "general")
  --path <path>         Custom test path (auto-generated if not provided)
  --related <ids>       Comma-separated related test IDs
  --no-test             Don't create placeholder test file
  --note <text>         Add note to session-state.md
  --dry-run             Show what would be done without making changes
  --help                Show this help message

Examples:
  php scripts/add-queue-component.php --name VectorCacheTest --type unit --domain vector_stores
  php scripts/add-queue-component.php --name ChatManagerTest --type feature_livewire --sprint 2
  php scripts/add-queue-component.php --name NewFeatureTest --type feature --dry-run

HELP;
}

function parseArgs(array $argv): array
{
    $options = [];
    $i = 1;

    while ($i < count($argv)) {
        $arg = $argv[$i];

        switch ($arg) {
            case '--name':
                $options['name'] = $argv[++$i] ?? '';
                break;
            case '--type':
                $options['type'] = $argv[++$i] ?? '';
                break;
            case '--sprint':
                $options['sprint'] = $argv[++$i] ?? 1;
                break;
            case '--domain':
                $options['domain'] = $argv[++$i] ?? 'general';
                break;
            case '--path':
                $options['path'] = $argv[++$i] ?? null;
                break;
            case '--related':
                $options['related'] = $argv[++$i] ?? '';
                break;
            case '--note':
                $options['note'] = $argv[++$i] ?? '';
                break;
            case '--no-test':
                $options['no-test'] = true;
                break;
            case '--dry-run':
                $options['dry-run'] = true;
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
    $scaffolder = new ComponentScaffolder($options);

    if (! $scaffolder->run()) {
        echo "\033[31mErrors:\033[0m\n";
        foreach ($scaffolder->getErrors() as $error) {
            echo "  \033[31m✗\033[0m {$error}\n";
        }

        return 1;
    }

    return 0;
}

exit(main());
