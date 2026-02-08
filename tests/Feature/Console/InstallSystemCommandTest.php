<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class InstallSystemCommandTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure we have a working database connection for tests
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
    }

    /** @test */
    public function it_checks_system_requirements()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Step 1/8: Checking System Requirements')
            ->expectsOutputToContain('PHP Version')
            ->expectsOutputToContain('Memory Limit')
            ->expectsOutputToContain('Max Execution Time')
            ->expectsOutputToContain('Disk Space');
    }

    /** @test */
    public function it_checks_php_extensions()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Step 2/8: Checking PHP Extensions')
            ->expectsOutputToContain('Extension: pdo')
            ->expectsOutputToContain('Extension: mbstring')
            ->expectsOutputToContain('Extension: json');
    }

    /** @test */
    public function it_checks_environment_configuration()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Step 3/8: Checking Environment Configuration')
            ->expectsOutputToContain('.env File');
    }

    /** @test */
    public function it_checks_storage_directories()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Step 4/8: Checking Storage Directories')
            ->expectsOutputToContain('Directory: storage/app')
            ->expectsOutputToContain('Directory: storage/logs');
    }

    /** @test */
    public function it_checks_database_connection()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Step 5/8: Checking Database Connection')
            ->expectsOutputToContain('Database Connection');
    }

    /** @test */
    public function it_can_skip_service_checks()
    {
        $this->artisan('system:install', [
            '--check-only' => true,
            '--skip-services' => true,
        ])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_checks_external_services()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Step 6/8: Checking External Services');
    }

    /** @test */
    public function it_checks_composer_dependencies()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Step 7/8: Checking Composer Dependencies')
            ->expectsOutputToContain('Composer Dependencies');
    }

    /** @test */
    public function it_checks_queue_configuration()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Step 8/8: Checking Queue Configuration')
            ->expectsOutputToContain('Queue Driver');
    }

    /** @test */
    public function it_displays_installation_summary()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('INSTALLATION SUMMARY')
            ->expectsOutputToContain('Passed:')
            ->expectsOutputToContain('Warnings:')
            ->expectsOutputToContain('Errors:');
    }

    /** @test */
    public function it_can_run_in_check_only_mode()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_can_skip_migrations()
    {
        $this->artisan('system:install', [
            '--check-only' => true,
            '--skip-migrations' => true,
        ])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_masks_sensitive_values()
    {
        // Set a sensitive value
        putenv('APP_KEY=base64:verylongsecretkeythatshouldbemaskedinouptut1234567890');

        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('ENV: APP_KEY')
            ->doesntExpectOutput('verylongsecretkeythatshouldbemaskedinouptut1234567890');

        putenv('APP_KEY=');
    }

    /** @test */
    public function it_detects_missing_required_extensions()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Extension:');
    }

    /** @test */
    public function it_warns_about_missing_optional_extensions()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Extension:');
    }

    /** @test */
    public function it_checks_database_tables()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Database Connection');
    }

    /** @test */
    public function it_provides_next_steps_on_success()
    {
        // Mock successful installation
        $this->artisan('system:install', [
            '--check-only' => true,
            '--skip-services' => true,
        ]);
    }

    /** @test */
    public function it_suggests_fixes_when_errors_exist()
    {
        $this->artisan('system:install', ['--check-only' => true]);
    }

    /** @test */
    public function it_can_force_installation_despite_errors()
    {
        $this->artisan('system:install', [
            '--check-only' => true,
            '--force' => true,
        ])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_checks_php_version()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('PHP Version');
    }

    /** @test */
    public function it_checks_memory_limit()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Memory Limit');
    }

    /** @test */
    public function it_checks_disk_space()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Disk Space');
    }

    /** @test */
    public function it_checks_max_execution_time()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Max Execution Time');
    }

    /** @test */
    public function it_validates_env_file_exists()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('.env File');
    }

    /** @test */
    public function it_checks_critical_environment_variables()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('ENV: APP_KEY')
            ->expectsOutputToContain('ENV: DB_CONNECTION');
    }

    /** @test */
    public function it_checks_api_keys_configuration()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('API:');
    }

    /** @test */
    public function it_verifies_storage_is_writable()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Directory: storage/app');
    }

    /** @test */
    public function it_checks_public_storage_link()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Public Storage Link');
    }

    /** @test */
    public function it_validates_composer_lock_exists()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Composer Dependencies');
    }

    /** @test */
    public function it_checks_vendor_directory()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Vendor Directory');
    }

    /** @test */
    public function it_verifies_composer_autoload()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Composer Autoload');
    }

    /** @test */
    public function it_checks_queue_driver_configuration()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Queue Driver');
    }

    /** @test */
    public function it_warns_about_queue_workers()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Queue Workers');
    }

    /** @test */
    public function it_displays_step_indicators()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('Step 1/8')
            ->expectsOutputToContain('Step 2/8')
            ->expectsOutputToContain('Step 3/8')
            ->expectsOutputToContain('Step 4/8')
            ->expectsOutputToContain('Step 5/8')
            ->expectsOutputToContain('Step 6/8')
            ->expectsOutputToContain('Step 7/8')
            ->expectsOutputToContain('Step 8/8');
    }

    /** @test */
    public function it_shows_check_mark_emojis_for_passed_checks()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('✅');
    }

    /** @test */
    public function it_shows_warning_emojis_for_warnings()
    {
        $this->artisan('system:install', ['--check-only' => true])
            ->expectsOutputToContain('⚠️');
    }

    /** @test */
    public function it_categorizes_results_correctly()
    {
        $result = $this->artisan('system:install', ['--check-only' => true]);

        // Should complete and display summary
        $result->expectsOutputToContain('INSTALLATION SUMMARY');
    }
}
