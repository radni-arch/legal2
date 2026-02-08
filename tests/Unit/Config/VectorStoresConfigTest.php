<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

class VectorStoresConfigTest extends TestCase
{
    /** @test */
    public function it_has_all_required_top_level_sections(): void
    {
        $config = config('vector-stores');

        $this->assertArrayHasKey('stores', $config);
        $this->assertArrayHasKey('default_store', $config);
        $this->assertArrayHasKey('catalog_dir', $config);
    }

    /** @test */
    public function it_has_case_files_store_configuration(): void
    {
        $caseFiles = config('vector-stores.stores.case_files');

        $this->assertArrayHasKey('id', $caseFiles);
        $this->assertArrayHasKey('name', $caseFiles);
        $this->assertNotEmpty($caseFiles['id']);
        $this->assertNotEmpty($caseFiles['name']);
    }

    /** @test */
    public function it_has_laws_store_configuration(): void
    {
        $laws = config('vector-stores.stores.laws');

        $this->assertArrayHasKey('id', $laws);
        $this->assertArrayHasKey('name', $laws);
        $this->assertNotEmpty($laws['id']);
        $this->assertNotEmpty($laws['name']);
    }

    /** @test */
    public function it_has_correct_default_values(): void
    {
        $this->assertEquals(
            'vs_68c89c6bb90081918cf07e4441f58ecc',
            config('vector-stores.stores.case_files.id')
        );
        $this->assertEquals(
            'vs_68c89c812d408191add94d47a2b749e8',
            config('vector-stores.stores.laws.id')
        );
        $this->assertEquals(
            'vs_68c89c6bb90081918cf07e4441f58ecc',
            config('vector-stores.default_store')
        );
        $this->assertEquals(
            storage_path('app/catalog'),
            config('vector-stores.catalog_dir')
        );
    }

    /** @test */
    public function stores_values_are_correct_types(): void
    {
        $this->assertIsString(config('vector-stores.stores.case_files.id'));
        $this->assertIsString(config('vector-stores.stores.case_files.name'));
        $this->assertIsString(config('vector-stores.stores.laws.id'));
        $this->assertIsString(config('vector-stores.stores.laws.name'));
        $this->assertIsString(config('vector-stores.default_store'));
        $this->assertIsString(config('vector-stores.catalog_dir'));
    }

    /** @test */
    public function default_store_matches_case_files_id(): void
    {
        $this->assertEquals(
            config('vector-stores.stores.case_files.id'),
            config('vector-stores.default_store')
        );
    }
}
