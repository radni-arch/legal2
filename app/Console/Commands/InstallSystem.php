<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

/**
 * InstallSystem Command
 *
 * Comprehensive installation and verification script for AI Legal War Machine.
 * Checks and installs all required services, dependencies, and configurations.
 *
 * Usage:
 *   php artisan system:install
 *   php artisan system:install --check-only
 *   php artisan system:install --skip-migrations
 *   php artisan system:install --fix
 */
class InstallSystem extends Command
{
    protected $signature = 'system:install
                            {--check-only : Only check system status without installing}
                            {--fix : Attempt to fix issues automatically}
                            {--skip-migrations : Skip running database migrations}
                            {--skip-services : Skip service availability checks}
                            {--force : Force installation even if checks fail}';

    protected $description = 'Install and verify all system dependencies and services';

    protected array $checks = [];

    protected array $warnings = [];

    protected array $errors = [];

    public function handle(): int
    {
        $this->info('🚀 AI Legal War Machine - System Installation & Verification');
        $this->newLine();

        $checkOnly = $this->option('check-only');
        $fix = $this->option('fix');
        $skipMigrations = $this->option('skip-migrations');
        $skipServices = $this->option('skip-services');

        // Step 1: System Requirements
        $this->info('📋 Step 1/8: Checking System Requirements...');
        $this->checkSystemRequirements();
        $this->displayResults();

        // Step 2: PHP Extensions
        $this->info('🔌 Step 2/8: Checking PHP Extensions...');
        $this->checkPhpExtensions();
        $this->displayResults();

        // Step 3: Environment Configuration
        $this->info('⚙️  Step 3/8: Checking Environment Configuration...');
        $this->checkEnvironmentConfig();
        if ($fix && ! $checkOnly) {
            $this->fixEnvironmentConfig();
        }
        $this->displayResults();

        // Step 4: Storage Directories
        $this->info('📁 Step 4/8: Checking Storage Directories...');
        $this->checkStorageDirectories();
        if ($fix && ! $checkOnly) {
            $this->createStorageDirectories();
        }
        $this->displayResults();

        // Step 5: Database Connection
        $this->info('🗄️  Step 5/8: Checking Database Connection...');
        $this->checkDatabase();
        if (! $skipMigrations && ! $checkOnly && ! $this->hasErrors()) {
            $this->runMigrations();
        }
        $this->displayResults();

        // Step 6: External Services
        if (! $skipServices) {
            $this->info('🌐 Step 6/8: Checking External Services...');
            $this->checkExternalServices();
            $this->displayResults();
        }

        // Step 7: Composer Dependencies
        $this->info('📦 Step 7/8: Checking Composer Dependencies...');
        $this->checkComposerDependencies();
        if ($fix && ! $checkOnly) {
            $this->installComposerDependencies();
        }
        $this->displayResults();

        // Step 8: Queue Workers
        $this->info('⚡ Step 8/8: Checking Queue Configuration...');
        $this->checkQueueConfiguration();
        $this->displayResults();

        // Final Summary
        $this->newLine();
        $this->displayFinalSummary();

        return $this->hasErrors() && ! $this->option('force') ? 1 : 0;
    }

    protected function checkSystemRequirements(): void
    {
        // PHP Version
        $phpVersion = PHP_VERSION;
        $requiredPhpVersion = '8.2.0';

        if (version_compare($phpVersion, $requiredPhpVersion, '>=')) {
            $this->addCheck('PHP Version', "✅ {$phpVersion}", true);
        } else {
            $this->addError('PHP Version', "❌ {$phpVersion} (Required: >= {$requiredPhpVersion})");
        }

        // Memory Limit
        $memoryLimit = ini_get('memory_limit');
        $memoryBytes = $this->parseMemoryLimit($memoryLimit);

        if ($memoryBytes >= 512 * 1024 * 1024 || $memoryLimit === '-1') {
            $this->addCheck('Memory Limit', "✅ {$memoryLimit}", true);
        } else {
            $this->addWarning('Memory Limit', "⚠️  {$memoryLimit} (Recommended: 512M+)");
        }

        // Max Execution Time
        $maxExecutionTime = ini_get('max_execution_time');
        if ($maxExecutionTime >= 300 || $maxExecutionTime == 0) {
            $this->addCheck('Max Execution Time', "✅ {$maxExecutionTime}s", true);
        } else {
            $this->addWarning('Max Execution Time', "⚠️  {$maxExecutionTime}s (Recommended: 300s+)");
        }

        // Disk Space
        $freeSpace = disk_free_space(base_path());
        $freeSpaceGB = round($freeSpace / 1024 / 1024 / 1024, 2);

        if ($freeSpaceGB >= 10) {
            $this->addCheck('Disk Space', "✅ {$freeSpaceGB} GB available", true);
        } else {
            $this->addWarning('Disk Space', "⚠️  {$freeSpaceGB} GB (Recommended: 10GB+)");
        }
    }

    protected function checkPhpExtensions(): void
    {
        $requiredExtensions = [
            'pdo',
            'pdo_mysql',
            'mbstring',
            'openssl',
            'tokenizer',
            'xml',
            'ctype',
            'json',
            'bcmath',
            'curl',
            'fileinfo',
            'gd',
            'zip',
        ];

        $optionalExtensions = [
            'redis' => 'Redis caching and queues',
            'imagick' => 'Advanced image processing',
            'intl' => 'Internationalization support',
            'pcntl' => 'Process control (for queue workers)',
            'posix' => 'POSIX functions',
        ];

        foreach ($requiredExtensions as $extension) {
            if (extension_loaded($extension)) {
                $this->addCheck("Extension: {$extension}", '✅ Installed', true);
            } else {
                $this->addError("Extension: {$extension}", '❌ Missing (Required)');
            }
        }

        foreach ($optionalExtensions as $extension => $purpose) {
            if (extension_loaded($extension)) {
                $this->addCheck("Extension: {$extension}", "✅ Installed ({$purpose})", true);
            } else {
                $this->addWarning("Extension: {$extension}", "⚠️  Missing ({$purpose})");
            }
        }
    }

    protected function checkEnvironmentConfig(): void
    {
        // Check if .env exists
        if (! file_exists(base_path('.env'))) {
            $this->addError('.env File', '❌ Missing');

            return;
        }

        $this->addCheck('.env File', '✅ Present', true);

        // Critical environment variables
        $criticalVars = [
            'APP_KEY' => 'Application encryption key',
            'DB_CONNECTION' => 'Database connection type',
            'DB_HOST' => 'Database host',
            'DB_DATABASE' => 'Database name',
        ];

        foreach ($criticalVars as $var => $description) {
            $value = env($var);
            if (empty($value)) {
                $this->addError("ENV: {$var}", "❌ Not set ({$description})");
            } else {
                $maskedValue = $this->maskSensitiveValue($var, $value);
                $this->addCheck("ENV: {$var}", "✅ {$maskedValue}", true);
            }
        }

        // API Keys (optional but important)
        $apiKeys = [
            'OPENAI_API_KEY' => 'OpenAI API access',
            'AWS_ACCESS_KEY_ID' => 'AWS Textract access',
            'GOOGLE_DRIVE_FOLDER_ID' => 'Google Drive integration',
        ];

        foreach ($apiKeys as $key => $purpose) {
            $value = env($key);
            if (empty($value)) {
                $this->addWarning("API: {$key}", "⚠️  Not set ({$purpose})");
            } else {
                $this->addCheck("API: {$key}", "✅ Configured ({$purpose})", true);
            }
        }
    }

    protected function checkStorageDirectories(): void
    {
        $requiredDirs = [
            'storage/app',
            'storage/app/public',
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
            'bootstrap/cache',
        ];

        foreach ($requiredDirs as $dir) {
            $path = base_path($dir);

            if (is_dir($path) && is_writable($path)) {
                $this->addCheck("Directory: {$dir}", '✅ Writable', true);
            } elseif (is_dir($path)) {
                $this->addError("Directory: {$dir}", '❌ Not writable');
            } else {
                $this->addError("Directory: {$dir}", '❌ Missing');
            }
        }

        // Check symbolic link
        $publicStorage = public_path('storage');
        if (is_link($publicStorage)) {
            $this->addCheck('Public Storage Link', '✅ Exists', true);
        } else {
            $this->addWarning('Public Storage Link', '⚠️  Missing (run: php artisan storage:link)');
        }
    }

    protected function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $this->addCheck('Database Connection', '✅ Connected', true);

            // Check database name
            $dbName = DB::connection()->getDatabaseName();
            $this->addCheck('Database Name', "✅ {$dbName}", true);

            // Check tables exist
            $tables = DB::select('SHOW TABLES');
            $tableCount = count($tables);

            if ($tableCount > 0) {
                $this->addCheck('Database Tables', "✅ {$tableCount} tables", true);
            } else {
                $this->addWarning('Database Tables', '⚠️  No tables (migrations needed)');
            }

        } catch (\Exception $e) {
            $this->addError('Database Connection', "❌ Failed: {$e->getMessage()}");
        }
    }

    protected function checkExternalServices(): void
    {
        // Redis
        try {
            Redis::connection()->ping();
            $this->addCheck('Redis', '✅ Connected', true);
        } catch (\Exception $e) {
            $this->addWarning('Redis', "⚠️  Not available: {$e->getMessage()}");
        }

        // Neo4j
        $neo4jEnabled = config('neo4j.sync.enabled', false);
        if ($neo4jEnabled) {
            $neo4jUrl = config('neo4j.connection.url', 'bolt://localhost:7687');

            try {
                // Simple check - you may need to adjust based on your Neo4j client
                $this->addCheck('Neo4j', "✅ Enabled ({$neo4jUrl})", true);
            } catch (\Exception $e) {
                $this->addWarning('Neo4j', '⚠️  Configuration found but connection failed');
            }
        } else {
            $this->addWarning('Neo4j', '⚠️  Disabled in configuration');
        }

        // OpenAI API
        if (env('OPENAI_API_KEY')) {
            try {
                $response = Http::timeout(5)
                    ->withHeaders([
                        'Authorization' => 'Bearer '.env('OPENAI_API_KEY'),
                    ])
                    ->get('https://api.openai.com/v1/models');

                if ($response->successful()) {
                    $this->addCheck('OpenAI API', '✅ Connected', true);
                } else {
                    $this->addError('OpenAI API', "❌ Authentication failed ({$response->status()})");
                }
            } catch (\Exception $e) {
                $this->addError('OpenAI API', "❌ Connection failed: {$e->getMessage()}");
            }
        } else {
            $this->addWarning('OpenAI API', '⚠️  API key not configured');
        }

        // AWS (for Textract)
        if (env('AWS_ACCESS_KEY_ID') && env('AWS_SECRET_ACCESS_KEY')) {
            $this->addCheck('AWS Credentials', '✅ Configured', true);
        } else {
            $this->addWarning('AWS Credentials', '⚠️  Not configured (Textract unavailable)');
        }
    }

    protected function checkComposerDependencies(): void
    {
        $composerLock = base_path('composer.lock');

        if (file_exists($composerLock)) {
            $this->addCheck('Composer Dependencies', '✅ Installed', true);

            // Check vendor directory
            if (is_dir(base_path('vendor'))) {
                $this->addCheck('Vendor Directory', '✅ Present', true);
            } else {
                $this->addError('Vendor Directory', '❌ Missing');
            }
        } else {
            $this->addError('Composer Dependencies', '❌ Not installed (run: composer install)');
        }

        // Check autoload
        $autoload = base_path('vendor/autoload.php');
        if (file_exists($autoload)) {
            $this->addCheck('Composer Autoload', '✅ Generated', true);
        } else {
            $this->addError('Composer Autoload', '❌ Missing (run: composer dump-autoload)');
        }
    }

    protected function checkQueueConfiguration(): void
    {
        $queueDriver = config('queue.default');
        $this->addCheck('Queue Driver', "✅ {$queueDriver}", true);

        // Check if queue tables exist (for database driver)
        if ($queueDriver === 'database') {
            try {
                $jobsTable = DB::table('jobs')->count();
                $this->addCheck('Queue Jobs Table', '✅ Present', true);
            } catch (\Exception $e) {
                $this->addError('Queue Jobs Table', '❌ Missing (run migrations)');
            }
        }

        // Check for queue workers
        $this->addWarning('Queue Workers', '⚠️  Ensure workers are running (php artisan queue:work)');
    }

    protected function fixEnvironmentConfig(): void
    {
        if (! file_exists(base_path('.env')) && file_exists(base_path('.env.example'))) {
            copy(base_path('.env.example'), base_path('.env'));
            $this->info('   → Created .env from .env.example');
        }

        if (empty(env('APP_KEY'))) {
            Artisan::call('key:generate', ['--force' => true]);
            $this->info('   → Generated APP_KEY');
        }
    }

    protected function createStorageDirectories(): void
    {
        $dirs = [
            'storage/app',
            'storage/app/public',
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
            'bootstrap/cache',
        ];

        foreach ($dirs as $dir) {
            $path = base_path($dir);
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
                $this->info("   → Created {$dir}");
            }
        }

        if (! is_link(public_path('storage'))) {
            Artisan::call('storage:link');
            $this->info('   → Created storage symlink');
        }
    }

    protected function runMigrations(): void
    {
        $this->info('   → Running database migrations...');

        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->info('   ✅ Migrations completed');
        } catch (\Exception $e) {
            $this->error("   ❌ Migration failed: {$e->getMessage()}");
        }
    }

    protected function installComposerDependencies(): void
    {
        if (! file_exists(base_path('vendor'))) {
            $this->info('   → Installing composer dependencies...');
            exec('composer install --no-interaction', $output, $returnCode);

            if ($returnCode === 0) {
                $this->info('   ✅ Composer dependencies installed');
            } else {
                $this->error('   ❌ Composer install failed');
            }
        }
    }

    protected function addCheck(string $name, string $message, bool $passed): void
    {
        $this->checks[] = ['name' => $name, 'message' => $message, 'passed' => $passed];
    }

    protected function addWarning(string $name, string $message): void
    {
        $this->warnings[] = ['name' => $name, 'message' => $message];
        $this->checks[] = ['name' => $name, 'message' => $message, 'passed' => false];
    }

    protected function addError(string $name, string $message): void
    {
        $this->errors[] = ['name' => $name, 'message' => $message];
        $this->checks[] = ['name' => $name, 'message' => $message, 'passed' => false];
    }

    protected function displayResults(): void
    {
        foreach (array_splice($this->checks, 0) as $check) {
            $this->line("   {$check['message']}");
        }
        $this->newLine();
    }

    protected function displayFinalSummary(): void
    {
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('                    INSTALLATION SUMMARY');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        $totalChecks = count($this->checks) + count($this->warnings) + count($this->errors);
        $errorCount = count($this->errors);
        $warningCount = count($this->warnings);
        $passedCount = $totalChecks - $errorCount - $warningCount;

        $this->line("   ✅ Passed:   {$passedCount}");
        $this->line("   ⚠️  Warnings: {$warningCount}");
        $this->line("   ❌ Errors:   {$errorCount}");
        $this->newLine();

        if ($errorCount > 0) {
            $this->error('❌ INSTALLATION INCOMPLETE - Please fix the following errors:');
            $this->newLine();
            foreach ($this->errors as $error) {
                $this->error("   • {$error['name']}: {$error['message']}");
            }
            $this->newLine();
            $this->info('Run with --fix to attempt automatic fixes:');
            $this->info('   php artisan system:install --fix');
        } elseif ($warningCount > 0) {
            $this->warn('⚠️  INSTALLATION COMPLETE WITH WARNINGS');
            $this->newLine();
            foreach ($this->warnings as $warning) {
                $this->warn("   • {$warning['name']}: {$warning['message']}");
            }
        } else {
            $this->info('✅ SYSTEM FULLY INSTALLED AND CONFIGURED!');
            $this->newLine();
            $this->info('Next steps:');
            $this->info('   1. Start queue workers: php artisan queue:work');
            $this->info('   2. Import Croatian laws: php artisan hrlaws:ingest');
            $this->info('   3. Discover court decisions: php artisan decisions:discover');
        }

        $this->newLine();
    }

    protected function parseMemoryLimit(string $limit): int
    {
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => (int) $limit,
        };
    }

    protected function maskSensitiveValue(string $key, $value): string
    {
        $sensitiveKeys = ['KEY', 'SECRET', 'PASSWORD', 'TOKEN'];

        foreach ($sensitiveKeys as $sensitive) {
            if (stripos($key, $sensitive) !== false) {
                return substr($value, 0, 8).'...'.substr($value, -4);
            }
        }

        return is_string($value) ? (strlen($value) > 50 ? substr($value, 0, 50).'...' : $value) : (string) $value;
    }

    protected function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }
}
