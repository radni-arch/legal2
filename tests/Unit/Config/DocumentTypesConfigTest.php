<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

class DocumentTypesConfigTest extends TestCase
{
    public function test_document_types_config_exists(): void
    {
        $config = config('documents.types');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('suppression_motion', $config);
    }

    public function test_suppression_motion_config_has_required_fields(): void
    {
        $config = config('documents.types.suppression_motion');

        $this->assertArrayHasKey('display_name', $config);
        $this->assertArrayHasKey('category', $config);
        $this->assertArrayHasKey('required_context', $config);
        $this->assertArrayHasKey('default_structure', $config);
        $this->assertEquals('Prijedlog za isključenje dokaza', $config['display_name']);
        $this->assertEquals('court_motion', $config['category']);
    }

    public function test_all_document_types_have_required_fields(): void
    {
        $types = config('documents.types');

        foreach ($types as $key => $config) {
            $this->assertArrayHasKey('display_name', $config, "Document type {$key} missing display_name");
            $this->assertArrayHasKey('category', $config, "Document type {$key} missing category");
            $this->assertArrayHasKey('default_structure', $config, "Document type {$key} missing default_structure");
        }
    }
}
