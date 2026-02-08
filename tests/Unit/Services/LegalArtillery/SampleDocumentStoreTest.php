<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\Services\LegalArtillery\SampleDocumentStore;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SampleDocumentStoreTest extends TestCase
{
    private string $samplesDir;
    private SampleDocumentStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Use the actual samples directory
        $this->samplesDir = resource_path('legal-artillery/samples');
        $this->store = new SampleDocumentStore($this->samplesDir);
    }

    /**
     * @test
     */
    public function getSample_returns_content_for_existing_profile(): void
    {
        // The predsjednik_suda sample should exist
        $content = $this->store->getSample('predsjednik_suda');

        $this->assertNotNull($content);
        $this->assertIsString($content);
        $this->assertNotEmpty($content);
        // Sample should contain relevant legal document content
        $this->assertStringContainsString('predsjedni', strtolower($content));
    }

    /**
     * @test
     */
    public function getSample_returns_null_for_missing_profile(): void
    {
        $content = $this->store->getSample('nonexistent_profile');

        $this->assertNull($content);
    }

    /**
     * @test
     */
    public function getSamplePath_returns_path_for_existing_sample(): void
    {
        $path = $this->store->getSamplePath('predsjednik_suda');

        $this->assertNotNull($path);
        $this->assertFileExists($path);
        $this->assertStringEndsWith('predsjednik_suda.md', $path);
    }

    /**
     * @test
     */
    public function getSamplePath_returns_null_for_missing_sample(): void
    {
        $path = $this->store->getSamplePath('nonexistent_profile');

        $this->assertNull($path);
    }

    /**
     * @test
     */
    public function hasSample_returns_true_for_existing_sample(): void
    {
        $result = $this->store->hasSample('predsjednik_suda');

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function hasSample_returns_false_for_missing_sample(): void
    {
        $result = $this->store->hasSample('nonexistent_profile');

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function getAllSamples_returns_all_available_samples(): void
    {
        $samples = $this->store->getAllSamples();

        $this->assertIsArray($samples);
        $this->assertNotEmpty($samples);
        // Should contain at least predsjednik_suda and ustavni_sud
        $this->assertArrayHasKey('predsjednik_suda', $samples);
        $this->assertArrayHasKey('ustavni_sud', $samples);
        // Each entry should be a string (the content)
        foreach ($samples as $key => $content) {
            $this->assertIsString($key);
            $this->assertIsString($content);
            $this->assertNotEmpty($content);
        }
    }

    /**
     * @test
     */
    public function getAllSamples_returns_empty_array_when_no_samples_exist(): void
    {
        // Use a temp directory with no samples
        $emptyDir = sys_get_temp_dir() . '/empty_samples_' . uniqid();
        File::ensureDirectoryExists($emptyDir);

        $emptyStore = new SampleDocumentStore($emptyDir);
        $samples = $emptyStore->getAllSamples();

        $this->assertIsArray($samples);
        $this->assertEmpty($samples);

        // Cleanup
        File::deleteDirectory($emptyDir);
    }

    /**
     * @test
     */
    public function ustavni_sud_sample_contains_constitutional_content(): void
    {
        $content = $this->store->getSample('ustavni_sud');

        $this->assertNotNull($content);
        $this->assertIsString($content);
        // Should contain references to constitutional court
        $lowerContent = strtolower($content);
        $this->assertTrue(
            str_contains($lowerContent, 'ustav') || str_contains($lowerContent, 'constitutional'),
            'Ustavni sud sample should contain constitutional references'
        );
    }

    /**
     * @test
     */
    public function default_samples_directory_is_used_when_none_specified(): void
    {
        // Create store without specifying directory
        $store = new SampleDocumentStore();

        // It should still work with the default path
        $this->assertTrue($store->hasSample('predsjednik_suda'));
    }
}
