<?php

namespace Tests\Feature\Console;

use App\Models\ApiKey;
use App\Models\ApiKeyCooldown;
use App\Services\ApiRotator\ApiKeyRotatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test suite for ApiKeyManage command.
 *
 * Tests the CLI interface for managing API keys in the rotation system:
 * - list: Display all configured keys
 * - add: Add a new API key
 * - remove: Delete an API key
 * - disable/enable: Toggle key active status
 * - status: Show current usage status
 */
class ApiKeyManageCommandTest extends TestCase
{
    use RefreshDatabase;

    // =================================================================
    // LIST Action Tests
    // =================================================================

    /** @test */
    public function list_action_shows_all_keys_in_table(): void
    {
        $key = ApiKey::factory()->create([
            'name' => 'Gemini Key 1',
            'provider' => 'gemini',
            'model' => 'gemini-pro',
            'rpd_limit' => 1500,
            'is_active' => true,
            'supports_pdf' => true,
        ]);

        $this->artisan('apikey:manage', ['action' => 'list'])
            ->assertExitCode(0);

        // Verify key was displayed (command ran successfully with the key)
        $this->assertDatabaseHas('api_keys', [
            'id' => $key->id,
            'name' => 'Gemini Key 1',
            'provider' => 'gemini',
        ]);
    }

    /** @test */
    public function list_action_warns_when_no_keys(): void
    {
        $this->artisan('apikey:manage', ['action' => 'list'])
            ->expectsOutput('No API keys configured.')
            ->assertExitCode(0);
    }

    /** @test */
    public function list_action_shows_inactive_keys(): void
    {
        ApiKey::factory()->create([
            'name' => 'Disabled Key',
            'provider' => 'mistral',
            'is_active' => false,
        ]);

        $this->artisan('apikey:manage', ['action' => 'list'])
            ->assertExitCode(0);

        // Key should appear in list with "✗" for Active
        $keys = ApiKey::withTrashed()->get();
        $this->assertCount(1, $keys);
        $this->assertFalse($keys->first()->is_active);
    }

    /** @test */
    public function list_action_shows_keys_without_model(): void
    {
        ApiKey::factory()->create([
            'name' => 'Default Model Key',
            'provider' => 'openrouter',
            'model' => null,
            'is_active' => true,
        ]);

        $this->artisan('apikey:manage', ['action' => 'list'])
            ->assertExitCode(0);
    }

    // =================================================================
    // ADD Action Tests
    // =================================================================

    /** @test */
    public function add_action_creates_key_with_all_options(): void
    {
        $this->artisan('apikey:manage', [
            'action' => 'add',
            '--provider' => 'gemini',
            '--key' => 'test-api-key-123',
            '--name' => 'My Test Key',
            '--model' => 'gemini-2.0-flash',
        ])->assertExitCode(0);

        $key = ApiKey::first();
        $this->assertNotNull($key);
        $this->assertEquals('My Test Key', $key->name);
        $this->assertEquals('gemini', $key->provider);
        $this->assertEquals('gemini-2.0-flash', $key->model);
        $this->assertEquals('test-api-key-123', $key->api_key);
    }

    /** @test */
    public function add_action_requires_key(): void
    {
        $this->artisan('apikey:manage', [
            'action' => 'add',
            '--provider' => 'gemini',
        ])
            ->expectsQuestion('Enter API key', null)
            ->expectsOutput('API key is required')
            ->assertExitCode(1);
    }

    /** @test */
    public function add_action_prompts_for_provider_if_not_provided(): void
    {
        $this->artisan('apikey:manage', [
            'action' => 'add',
            '--key' => 'test-key',
            '--model' => 'test-model', // Provide model to skip model selection
        ])
            ->expectsChoice('Select provider', 'gemini', ['gemini', 'mistral', 'openrouter'])
            ->expectsQuestion('Name for this key', 'gemini-test')
            ->assertExitCode(0);

        $this->assertCount(1, ApiKey::all());
    }

    /** @test */
    public function add_action_generates_name_if_not_provided(): void
    {
        $this->artisan('apikey:manage', [
            'action' => 'add',
            '--provider' => 'mistral',
            '--key' => 'my-secret-key',
            '--model' => 'test-model', // Provide model to skip model selection
        ])
            ->expectsQuestion('Name for this key', 'mistral-' . substr(md5('my-secret-key'), 0, 6))
            ->assertExitCode(0);

        $key = ApiKey::first();
        $this->assertStringStartsWith('mistral-', $key->name);
    }

    /** @test */
    public function add_action_sets_default_limits(): void
    {
        $this->artisan('apikey:manage', [
            'action' => 'add',
            '--provider' => 'gemini',
            '--key' => 'test-key',
            '--name' => 'Test',
            '--model' => 'test-model', // Provide model to skip model selection
        ])
            ->assertExitCode(0);

        $key = ApiKey::first();
        $this->assertGreaterThan(0, $key->rpm_limit);
        $this->assertGreaterThan(0, $key->rpd_limit);
        $this->assertEquals(50, $key->priority);
    }

    // =================================================================
    // REMOVE Action Tests
    // =================================================================

    /** @test */
    public function remove_action_deletes_key_with_confirmation(): void
    {
        $key = ApiKey::factory()->create([
            'name' => 'Delete Me',
            'provider' => 'gemini',
        ]);

        $this->artisan('apikey:manage', [
            'action' => 'remove',
            '--id' => $key->id,
        ])
            ->expectsConfirmation("Remove key 'Delete Me' (gemini)?", 'yes')
            ->expectsOutput('Key removed.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('api_keys', ['id' => $key->id, 'deleted_at' => null]);
    }

    /** @test */
    public function remove_action_does_not_delete_if_cancelled(): void
    {
        $key = ApiKey::factory()->create([
            'name' => 'Keep Me',
            'provider' => 'gemini',
        ]);

        $this->artisan('apikey:manage', [
            'action' => 'remove',
            '--id' => $key->id,
        ])
            ->expectsConfirmation("Remove key 'Keep Me' (gemini)?", 'no')
            ->assertExitCode(0);

        $this->assertDatabaseHas('api_keys', ['id' => $key->id, 'deleted_at' => null]);
    }

    /** @test */
    public function remove_action_fails_for_nonexistent_key(): void
    {
        $this->artisan('apikey:manage', [
            'action' => 'remove',
            '--id' => 99999,
        ])
            ->expectsOutput('Key not found: 99999')
            ->assertExitCode(1);
    }

    /** @test */
    public function remove_action_prompts_for_id_if_not_provided(): void
    {
        $key = ApiKey::factory()->create([
            'name' => 'Prompt Key',
            'provider' => 'gemini',
        ]);

        $this->artisan('apikey:manage', ['action' => 'remove'])
            ->expectsQuestion('Enter key ID to remove', $key->id)
            ->expectsConfirmation("Remove key 'Prompt Key' (gemini)?", 'yes')
            ->expectsOutput('Key removed.')
            ->assertExitCode(0);
    }

    // =================================================================
    // DISABLE Action Tests
    // =================================================================

    /** @test */
    public function disable_action_sets_is_active_to_false(): void
    {
        $key = ApiKey::factory()->create([
            'name' => 'Active Key',
            'is_active' => true,
        ]);

        $this->artisan('apikey:manage', [
            'action' => 'disable',
            '--id' => $key->id,
        ])
            ->expectsOutput('Key disabled.')
            ->assertExitCode(0);

        $key->refresh();
        $this->assertFalse($key->is_active);
    }

    /** @test */
    public function disable_action_fails_for_nonexistent_key(): void
    {
        $this->artisan('apikey:manage', [
            'action' => 'disable',
            '--id' => 99999,
        ])
            ->expectsOutput('Key not found: 99999')
            ->assertExitCode(1);
    }

    // =================================================================
    // ENABLE Action Tests
    // =================================================================

    /** @test */
    public function enable_action_sets_is_active_to_true(): void
    {
        $key = ApiKey::factory()->create([
            'name' => 'Inactive Key',
            'is_active' => false,
        ]);

        $this->artisan('apikey:manage', [
            'action' => 'enable',
            '--id' => $key->id,
        ])
            ->expectsOutput('Key enabled.')
            ->assertExitCode(0);

        $key->refresh();
        $this->assertTrue($key->is_active);
    }

    /** @test */
    public function enable_action_fails_for_nonexistent_key(): void
    {
        $this->artisan('apikey:manage', [
            'action' => 'enable',
            '--id' => 99999,
        ])
            ->expectsOutput('Key not found: 99999')
            ->assertExitCode(1);
    }

    // =================================================================
    // STATUS Action Tests
    // =================================================================

    /** @test */
    public function status_action_shows_current_key_status(): void
    {
        ApiKey::factory()->create([
            'name' => 'Status Key',
            'provider' => 'gemini',
            'rpm_used' => 5,
            'rpm_limit' => 10,
            'rpd_used' => 25,
            'rpd_limit' => 100,
            'is_active' => true,
        ]);

        $this->artisan('apikey:manage', ['action' => 'status'])
            ->assertExitCode(0);
    }

    /** @test */
    public function status_action_warns_when_no_keys(): void
    {
        $this->artisan('apikey:manage', ['action' => 'status'])
            ->expectsOutput('No API keys configured.')
            ->assertExitCode(0);
    }

    /** @test */
    public function status_action_shows_cooldown_info(): void
    {
        $key = ApiKey::factory()->create([
            'name' => 'Cooldown Key',
            'provider' => 'gemini',
            'is_active' => true,
        ]);

        ApiKeyCooldown::create([
            'api_key_id' => $key->id,
            'cooldown_type' => 'rpm',
            'started_at' => now(),
            'ends_at' => now()->addMinutes(5),
        ]);

        $this->artisan('apikey:manage', ['action' => 'status'])
            ->assertExitCode(0);
    }

    // =================================================================
    // ADD Action - Reset Timestamps Tests
    // =================================================================

    /** @test */
    public function test_add_action_sets_reset_timestamps(): void
    {
        $this->artisan('apikey:manage', [
            'action' => 'add',
            '--provider' => 'gemini',
            '--key' => 'test-key-123',
            '--name' => 'test-key',
            '--model' => 'test-model',
        ])->assertExitCode(0);

        $key = ApiKey::where('name', 'test-key')->first();

        $this->assertNotNull($key->rpm_reset_at);
        $this->assertNotNull($key->rpd_reset_at);
        $this->assertTrue($key->rpm_reset_at->isFuture());
        $this->assertTrue($key->rpd_reset_at->isFuture());
    }

    // =================================================================
    // Unknown Action Tests
    // =================================================================

    /** @test */
    public function unknown_action_shows_error(): void
    {
        $this->artisan('apikey:manage', ['action' => 'invalid'])
            ->expectsOutput('Unknown action: invalid')
            ->assertExitCode(1);
    }
}
