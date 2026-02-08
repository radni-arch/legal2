<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

/**
 * ApiRotatorConfigTest
 *
 * Verifies the api_rotator configuration file structure and values.
 */
class ApiRotatorConfigTest extends TestCase
{
    public function test_config_file_exists_and_is_loadable(): void
    {
        $config = config('api_rotator');

        $this->assertNotNull($config, 'api_rotator config should be loadable');
        $this->assertIsArray($config);
    }

    public function test_config_has_proactive_threshold(): void
    {
        $threshold = config('api_rotator.proactive_threshold');

        $this->assertNotNull($threshold);
        $this->assertIsFloat($threshold);
        $this->assertGreaterThan(0, $threshold);
        $this->assertLessThanOrEqual(1, $threshold);
    }

    public function test_config_has_max_retries(): void
    {
        $maxRetries = config('api_rotator.max_retries');

        $this->assertNotNull($maxRetries);
        $this->assertIsInt($maxRetries);
        $this->assertGreaterThan(0, $maxRetries);
    }

    public function test_config_has_base_retry_delay(): void
    {
        $retryDelay = config('api_rotator.base_retry_delay');

        $this->assertNotNull($retryDelay);
        $this->assertIsInt($retryDelay);
        $this->assertGreaterThan(0, $retryDelay);
    }

    public function test_config_has_providers_section(): void
    {
        $providers = config('api_rotator.providers');

        $this->assertNotNull($providers);
        $this->assertIsArray($providers);
    }

    public function test_gemini_provider_config_exists(): void
    {
        $gemini = config('api_rotator.providers.gemini');

        $this->assertNotNull($gemini);
        $this->assertArrayHasKey('models', $gemini);
        $this->assertArrayHasKey('reset_timezone', $gemini);
        $this->assertEquals('America/Los_Angeles', $gemini['reset_timezone']);
    }

    public function test_mistral_provider_config_exists(): void
    {
        $mistral = config('api_rotator.providers.mistral');

        $this->assertNotNull($mistral);
        $this->assertArrayHasKey('models', $mistral);
        $this->assertArrayHasKey('reset_timezone', $mistral);
    }

    public function test_openrouter_provider_config_exists(): void
    {
        $openrouter = config('api_rotator.providers.openrouter');

        $this->assertNotNull($openrouter);
        $this->assertArrayHasKey('models', $openrouter);
        $this->assertArrayHasKey('reset_timezone', $openrouter);
    }

    public function test_gemini_model_has_required_limits(): void
    {
        $models = config('api_rotator.providers.gemini.models');

        $this->assertNotEmpty($models);

        foreach ($models as $modelName => $limits) {
            $this->assertArrayHasKey('rpm', $limits, "Model {$modelName} should have rpm");
            $this->assertArrayHasKey('rpd', $limits, "Model {$modelName} should have rpd");
            $this->assertArrayHasKey('tpm', $limits, "Model {$modelName} should have tpm");
            $this->assertArrayHasKey('supports_pdf', $limits, "Model {$modelName} should have supports_pdf");
            $this->assertArrayHasKey('supports_vision', $limits, "Model {$modelName} should have supports_vision");
        }
    }
}
