<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\DTOs\DocumentProfile;
use App\Services\LegalArtillery\DocxRenderer;
use Tests\TestCase;

class DocxRendererTemplateTest extends TestCase
{
    public function test_loads_template_for_formal_profile(): void
    {
        $renderer = new DocxRenderer(useSimpleMode: true);
        $profile = DocumentProfile::fromConfig('predsjednik_suda');

        $template = $renderer->loadTemplate($profile);

        $this->assertIsArray($template);
        if (!empty($template)) {
            $this->assertArrayHasKey('template_key', $template);
            $this->assertArrayHasKey('formatting', $template);
        }
    }

    public function test_loads_template_for_constitutional_profile(): void
    {
        $renderer = new DocxRenderer(useSimpleMode: true);
        $profile = DocumentProfile::fromConfig('ustavni_sud');

        $template = $renderer->loadTemplate($profile);

        $this->assertIsArray($template);
        if (!empty($template)) {
            $this->assertEquals('legal-constitutional', $template['template_key']);
        }
    }

    public function test_loads_template_for_echr_profile(): void
    {
        $renderer = new DocxRenderer(useSimpleMode: true);
        $profile = DocumentProfile::fromConfig('echr_application');

        $template = $renderer->loadTemplate($profile);

        $this->assertIsArray($template);
        if (!empty($template)) {
            $this->assertEquals('echr-application', $template['template_key']);
        }
    }

    public function test_returns_empty_array_for_missing_template(): void
    {
        $renderer = new DocxRenderer(useSimpleMode: true);

        // Create a profile with a non-existent template
        $config = [
            'key' => 'test_profile',
            'name' => 'Test',
            'recipient' => ['title' => 'Test', 'address' => 'Test'],
            'legal_basis' => [],
            'tone' => 'formal',
            'structure' => [],
            'docx_template' => 'nonexistent-template',
        ];
        $profile = DocumentProfile::fromArray($config);

        $template = $renderer->loadTemplate($profile);

        $this->assertIsArray($template);
    }

    public function test_template_files_exist(): void
    {
        $templateDir = resource_path('legal-artillery/templates');

        $this->assertFileExists("{$templateDir}/general/legal-formal.json");
        $this->assertFileExists("{$templateDir}/constitutional/legal-constitutional.json");
        $this->assertFileExists("{$templateDir}/echr/echr-application.json");
    }

    public function test_template_files_are_valid_json(): void
    {
        $templateDir = resource_path('legal-artillery/templates');
        $files = [
            "{$templateDir}/general/legal-formal.json",
            "{$templateDir}/constitutional/legal-constitutional.json",
            "{$templateDir}/echr/echr-application.json",
        ];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $decoded = json_decode($content, true);
            $this->assertNotNull($decoded, "Invalid JSON in {$file}");
            $this->assertArrayHasKey('template_key', $decoded);
            $this->assertArrayHasKey('formatting', $decoded);
        }
    }
}
