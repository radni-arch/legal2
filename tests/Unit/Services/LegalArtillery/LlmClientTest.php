<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\Models\ApiKey;
use App\Services\LegalArtillery\LlmClient;
use Illuminate\Support\Facades\Config;
use Prism\Prism\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Usage;
use Tests\TestCase;

class LlmClientTest extends TestCase
{
    public function test_creates_from_config(): void
    {
        $client = LlmClient::fromConfig();

        $this->assertInstanceOf(LlmClient::class, $client);
    }

    public function test_generates_response_via_prism(): void
    {
        Prism::fake([
            TextResponseFake::make()
                ->withText('Generated response')
                ->withUsage(new Usage(100, 50)),
        ]);

        $client = new LlmClient(
            provider: 'anthropic',
            model: 'claude-sonnet-4-20250514',
            maxTokens: 1024,
        );

        $response = $client->generate('System prompt', 'User prompt');

        $this->assertEquals('Generated response', $response);
    }

    public function test_throws_on_invalid_provider(): void
    {
        $client = new LlmClient(
            provider: 'invalid_provider',
            model: 'claude-sonnet-4-20250514',
            maxTokens: 1024,
        );

        $this->expectException(\ValueError::class);

        $client->generate('System', 'User');
    }

    public function test_uses_custom_max_tokens(): void
    {
        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('OK')
                ->withUsage(new Usage(10, 5)),
        ]);

        $client = new LlmClient(
            provider: 'anthropic',
            model: 'claude-sonnet-4-20250514',
            maxTokens: 1024,
        );

        $client->generate('System', 'User', maxTokens: 512);

        $fake->assertCallCount(1);
    }

    public function test_resolves_api_key_from_database(): void
    {
        // Create a DB key for anthropic
        $dbKey = ApiKey::create([
            'name' => 'test-anthropic-key',
            'provider' => 'anthropic',
            'api_key' => 'sk-ant-db-test-key-12345',
            'model' => 'claude-sonnet-4-20250514',
            'is_active' => true,
            'rpm_limit' => 100,
            'rpd_limit' => 1000,
            'tpm_limit' => 500000,
            'priority' => 80,
        ]);

        // Clear ENV key so we can verify DB key is used
        Config::set('prism.providers.anthropic.api_key', '');

        Prism::fake([
            TextResponseFake::make()
                ->withText('DB key response')
                ->withUsage(new Usage(50, 30)),
        ]);

        $client = new LlmClient(
            provider: 'anthropic',
            model: 'claude-sonnet-4-20250514',
            maxTokens: 1024,
        );

        $response = $client->generate('System', 'User');

        $this->assertEquals('DB key response', $response);
        // Verify DB key was injected into Prism config
        $this->assertEquals('sk-ant-db-test-key-12345', Config::get('prism.providers.anthropic.api_key'));

        // Clean up
        $dbKey->forceDelete();
    }

    public function test_falls_back_to_env_when_no_db_key(): void
    {
        // Ensure no DB keys for this provider
        ApiKey::where('provider', 'anthropic')->delete();

        // Set ENV key
        Config::set('prism.providers.anthropic.api_key', 'sk-ant-env-key-99999');

        Prism::fake([
            TextResponseFake::make()
                ->withText('ENV key response')
                ->withUsage(new Usage(50, 30)),
        ]);

        $client = new LlmClient(
            provider: 'anthropic',
            model: 'claude-sonnet-4-20250514',
            maxTokens: 1024,
        );

        $response = $client->generate('System', 'User');

        $this->assertEquals('ENV key response', $response);
        // ENV key should still be there (not overwritten)
        $this->assertEquals('sk-ant-env-key-99999', Config::get('prism.providers.anthropic.api_key'));
    }

    public function test_db_key_takes_priority_over_env_key(): void
    {
        // Set ENV key
        Config::set('prism.providers.anthropic.api_key', 'sk-ant-env-fallback');

        // Create a DB key with higher priority
        $dbKey = ApiKey::create([
            'name' => 'priority-test-key',
            'provider' => 'anthropic',
            'api_key' => 'sk-ant-db-priority-key',
            'model' => 'claude-sonnet-4-20250514',
            'is_active' => true,
            'rpm_limit' => 100,
            'rpd_limit' => 1000,
            'tpm_limit' => 500000,
            'priority' => 90,
        ]);

        Prism::fake([
            TextResponseFake::make()
                ->withText('Priority response')
                ->withUsage(new Usage(50, 30)),
        ]);

        $client = new LlmClient(
            provider: 'anthropic',
            model: 'claude-sonnet-4-20250514',
            maxTokens: 1024,
        );

        $client->generate('System', 'User');

        // DB key should override ENV key
        $this->assertEquals('sk-ant-db-priority-key', Config::get('prism.providers.anthropic.api_key'));

        // Clean up
        $dbKey->forceDelete();
    }

    public function test_skips_inactive_db_keys(): void
    {
        // Ensure no other anthropic keys
        ApiKey::where('provider', 'anthropic')->delete();

        // Create an inactive DB key
        $inactiveKey = ApiKey::create([
            'name' => 'inactive-key',
            'provider' => 'anthropic',
            'api_key' => 'sk-ant-inactive-key',
            'model' => 'claude-sonnet-4-20250514',
            'is_active' => false,
            'rpm_limit' => 100,
            'rpd_limit' => 1000,
            'tpm_limit' => 500000,
            'priority' => 90,
        ]);

        Config::set('prism.providers.anthropic.api_key', 'sk-ant-env-active');

        Prism::fake([
            TextResponseFake::make()
                ->withText('Active key response')
                ->withUsage(new Usage(50, 30)),
        ]);

        $client = new LlmClient(
            provider: 'anthropic',
            model: 'claude-sonnet-4-20250514',
            maxTokens: 1024,
        );

        $client->generate('System', 'User');

        // ENV key should be used since DB key is inactive
        $this->assertEquals('sk-ant-env-active', Config::get('prism.providers.anthropic.api_key'));

        // Clean up
        $inactiveKey->forceDelete();
    }

    public function test_skips_exhausted_db_keys(): void
    {
        // Ensure no other anthropic keys
        ApiKey::where('provider', 'anthropic')->delete();

        // Create a DB key with exhausted RPM quota
        $exhaustedKey = ApiKey::create([
            'name' => 'exhausted-key',
            'provider' => 'anthropic',
            'api_key' => 'sk-ant-exhausted-key',
            'model' => 'claude-sonnet-4-20250514',
            'is_active' => true,
            'rpm_limit' => 10,
            'rpd_limit' => 100,
            'tpm_limit' => 500000,
            'rpm_used' => 10, // At RPM limit
            'rpd_used' => 0,
            'priority' => 90,
        ]);

        Config::set('prism.providers.anthropic.api_key', 'sk-ant-env-fallback');

        Prism::fake([
            TextResponseFake::make()
                ->withText('Fallback response')
                ->withUsage(new Usage(50, 30)),
        ]);

        $client = new LlmClient(
            provider: 'anthropic',
            model: 'claude-sonnet-4-20250514',
            maxTokens: 1024,
        );

        $client->generate('System', 'User');

        // ENV key should be used since DB key is exhausted
        $this->assertEquals('sk-ant-env-fallback', Config::get('prism.providers.anthropic.api_key'));

        // Clean up
        $exhaustedKey->forceDelete();
    }
}
