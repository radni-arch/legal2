<?php

namespace Tests\Unit\Services\LegalArtillery;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TemplateLintTest extends TestCase
{
    public function test_templates_are_valid_and_have_required_render_keys(): void
    {
        $templateDir = resource_path('legal-artillery/templates');
        $files = File::allFiles($templateDir);

        $this->assertNotEmpty($files, 'No JSON templates found.');

        $requiredKeys = [
            'template_key',
            'name',
            'court_types',
            'formatting',
            'header',
            'footer',
        ];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            $decoded = json_decode($content, true);

            $this->assertIsArray($decoded, "Invalid JSON in {$file->getPathname()}");

            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $decoded, "Missing {$key} in {$file->getPathname()}");
            }
        }
    }
}
