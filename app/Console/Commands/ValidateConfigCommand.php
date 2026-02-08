<?php

namespace App\Console\Commands;

use App\Services\ConfigValidator;
use Illuminate\Console\Command;

class ValidateConfigCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'config:validate
                          {--strict : Fail on warnings as well as errors}
                          {--json : Output results as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate application configuration for required settings and security issues';

    /**
     * Execute the console command.
     */
    public function handle(ConfigValidator $validator): int
    {
        $this->info('Validating application configuration...');
        $this->newLine();

        // Run validation
        $result = $validator->validate();
        $errors = $result['errors'];
        $warnings = $result['warnings'];

        // Output as JSON if requested
        if ($this->option('json')) {
            $this->line(json_encode([
                'passed' => empty($errors),
                'errors' => $errors,
                'warnings' => $warnings,
                'environment' => app()->environment(),
                'timestamp' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT));

            return empty($errors) ? 0 : 1;
        }

        // Display environment info
        $this->displayEnvironmentInfo();
        $this->newLine();

        // Display errors
        if (! empty($errors)) {
            $this->error('✗ Configuration Errors ('.count($errors).')');
            $this->newLine();

            foreach ($errors as $error) {
                $this->line('  <fg=red>⨯</> '.$error);
            }

            $this->newLine();
        }

        // Display warnings
        if (! empty($warnings)) {
            $this->warn('⚠ Configuration Warnings ('.count($warnings).')');
            $this->newLine();

            foreach ($warnings as $warning) {
                $this->line('  <fg=yellow>!</> '.$warning);
            }

            $this->newLine();
        }

        // Display summary
        if (empty($errors) && empty($warnings)) {
            $this->info('✓ Configuration validation passed!');
            $this->newLine();
            $this->line('  All required settings are properly configured.');
            $this->line('  No security issues detected.');

            return 0;
        }

        // Summary statistics
        $this->displaySummary($errors, $warnings);

        // Determine exit code
        $strict = $this->option('strict');
        $hasIssues = ! empty($errors) || ($strict && ! empty($warnings));

        if ($hasIssues) {
            $this->newLine();
            $this->error('Configuration validation failed!');

            if ($strict && ! empty($warnings)) {
                $this->line('  Running in strict mode: warnings are treated as errors.');
            }
        } else {
            $this->newLine();
            $this->info('Configuration validation passed with warnings.');
            $this->line('  Fix warnings for optimal security and performance.');
        }

        return $hasIssues ? 1 : 0;
    }

    /**
     * Display environment information.
     */
    protected function displayEnvironmentInfo(): void
    {
        $this->components->twoColumnDetail(
            'Environment',
            $this->getEnvironmentLabel(app()->environment())
        );

        $this->components->twoColumnDetail(
            'Debug Mode',
            config('app.debug') ? '<fg=red>ENABLED</>' : '<fg=green>DISABLED</>'
        );

        $this->components->twoColumnDetail(
            'App URL',
            config('app.url')
        );

        $this->components->twoColumnDetail(
            'Database',
            config('database.default')
        );

        $this->components->twoColumnDetail(
            'Cache',
            config('cache.default')
        );

        $this->components->twoColumnDetail(
            'Queue',
            config('queue.default')
        );

        $this->components->twoColumnDetail(
            'Mail',
            config('mail.default')
        );

        $this->components->twoColumnDetail(
            'Session',
            config('session.driver')
        );
    }

    /**
     * Get colored environment label.
     */
    protected function getEnvironmentLabel(string $env): string
    {
        return match ($env) {
            'production' => '<fg=red;options=bold>PRODUCTION</>',
            'staging' => '<fg=yellow;options=bold>STAGING</>',
            'local', 'development' => '<fg=green;options=bold>DEVELOPMENT</>',
            default => strtoupper($env),
        };
    }

    /**
     * Display validation summary.
     */
    protected function displaySummary(array $errors, array $warnings): void
    {
        $this->newLine();
        $this->line('<fg=gray>───────────────────────────────────────────────────</>');

        $errorCount = count($errors);
        $warningCount = count($warnings);

        if ($errorCount > 0) {
            $this->line('  <fg=red>⨯ '.$errorCount.' '.str('error')->plural($errorCount).'</>');
        }

        if ($warningCount > 0) {
            $this->line('  <fg=yellow>! '.$warningCount.' '.str('warning')->plural($warningCount).'</>');
        }

        $this->line('<fg=gray>───────────────────────────────────────────────────</>');
    }

    /**
     * Display quick fixes for common issues.
     */
    protected function displayQuickFixes(array $errors): void
    {
        $fixes = [
            'APP_KEY' => 'Run: php artisan key:generate',
            'APP_DEBUG' => 'Set: APP_DEBUG=false',
            'NEO4J_PASSWORD' => 'Generate strong password and update NEO4J_PASSWORD',
            'REDIS_PASSWORD' => 'Set strong password in REDIS_PASSWORD',
            'MCP_API_TOKEN' => 'Generate token: openssl rand -hex 32',
        ];

        $suggestions = [];

        foreach ($errors as $error) {
            foreach ($fixes as $key => $fix) {
                if (str_contains($error, $key)) {
                    $suggestions[$key] = $fix;
                }
            }
        }

        if (! empty($suggestions)) {
            $this->newLine();
            $this->info('Quick Fixes:');
            $this->newLine();

            foreach ($suggestions as $key => $fix) {
                $this->line("  <fg=cyan>{$key}:</> {$fix}");
            }
        }
    }
}
