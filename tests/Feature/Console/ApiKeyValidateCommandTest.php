<?php

namespace Tests\Feature\Console;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Test suite for apikey:validate command.
 *
 * Tests the CLI interface for validating API keys by making test requests
 * to their respective provider endpoints:
 * - Reports valid keys with VALID status
 * - Reports invalid keys (401) with INVALID status
 * - Filters by --id option
 * - Filters by --provider option
 */
class ApiKeyValidateCommandTest extends TestCase
{
    use RefreshDatabase;

    // =================================================================
    // Valid Key Tests
    // =================================================================

    /** @test */
    public function test_validate_command_reports_valid_key(): void
    {
        Http::fake();

        ApiKey::factory()->create([
            'provider' => 'gemini',
            'is_active' => true,
            'name' => 'test-key',
        ]);

        // Exit code 0 means all keys are valid
        $this->artisan('apikey:validate')
            ->assertExitCode(0);

        // Verify HTTP request was made
        Http::assertSentCount(1);
    }

    // =================================================================
    // Invalid Key Tests
    // =================================================================

    /** @test */
    public function test_validate_command_reports_invalid_key(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => ['message' => 'API key not valid'],
            ], 401),
        ]);

        ApiKey::factory()->create([
            'provider' => 'gemini',
            'is_active' => true,
            'name' => 'bad-key',
        ]);

        // Exit code 1 means at least one key is invalid
        $this->artisan('apikey:validate')
            ->assertExitCode(1);

        // Verify HTTP request was made
        Http::assertSentCount(1);
    }

    // =================================================================
    // Filter by ID Tests
    // =================================================================

    /** @test */
    public function test_validate_specific_key_by_id(): void
    {
        Http::fake([
            '*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'ok']]]]]], 200),
        ]);

        $key = ApiKey::factory()->create(['provider' => 'gemini', 'is_active' => true]);
        ApiKey::factory()->create(['provider' => 'mistral', 'is_active' => true]);

        $this->artisan('apikey:validate', ['--id' => $key->id])
            ->assertExitCode(0);

        // Should only validate one key
        Http::assertSentCount(1);
    }

    // =================================================================
    // Filter by Provider Tests
    // =================================================================

    /** @test */
    public function test_validate_filters_by_provider(): void
    {
        Http::fake([
            '*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'ok']]]]]], 200),
        ]);

        ApiKey::factory()->create(['provider' => 'gemini', 'is_active' => true, 'name' => 'gemini-key']);
        ApiKey::factory()->create(['provider' => 'mistral', 'is_active' => true, 'name' => 'mistral-key']);
        ApiKey::factory()->create(['provider' => 'gemini', 'is_active' => true, 'name' => 'gemini-key-2']);

        $this->artisan('apikey:validate', ['--provider' => 'gemini'])
            ->assertExitCode(0);

        // Should only validate 2 gemini keys
        Http::assertSentCount(2);
    }

    // =================================================================
    // No Keys Tests
    // =================================================================

    /** @test */
    public function test_validate_warns_when_no_keys(): void
    {
        $this->artisan('apikey:validate')
            ->expectsOutput('No keys found to validate.')
            ->assertExitCode(0);
    }

    // =================================================================
    // Provider-Specific Validation Tests
    // =================================================================

    /** @test */
    public function test_validate_mistral_key(): void
    {
        Http::fake();

        ApiKey::factory()->create([
            'provider' => 'mistral',
            'is_active' => true,
            'name' => 'mistral-test',
        ]);

        // Run the command and capture output
        $this->artisan('apikey:validate')
            ->assertExitCode(0);

        // If we get here, the command ran with exit code 0, which means validation passed
    }

    /** @test */
    public function test_validate_openrouter_key(): void
    {
        Http::fake();

        ApiKey::factory()->create([
            'provider' => 'openrouter',
            'is_active' => true,
            'name' => 'openrouter-test',
        ]);

        // Run the command and verify exit code 0 (all keys valid)
        $this->artisan('apikey:validate')
            ->assertExitCode(0);
    }
}
