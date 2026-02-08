<?php

namespace Tests\Unit\TestData\Providers;

use Tests\TestCase;
use Tests\TestData\Providers\RegionalDataProvider;

class RegionalDataProviderTest extends TestCase
{
    private RegionalDataProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new RegionalDataProvider;
    }

    public function test_can_get_all_regions(): void
    {
        $regions = $this->provider->getAllRegions();

        $this->assertGreaterThanOrEqual(5, count($regions));
        $this->assertContains('Osijek-Baranja', $regions);
        $this->assertContains('Zagreb', $regions);
        $this->assertContains('Split-Dalmatia', $regions);
        $this->assertContains('Zadar', $regions);
        $this->assertContains('Rijeka', $regions);
    }

    public function test_can_get_region_data_for_osijek(): void
    {
        $data = $this->provider->getRegionData('Osijek-Baranja');

        $this->assertIsArray($data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('county', $data);
        $this->assertArrayHasKey('courts', $data);
        $this->assertArrayHasKey('prosecutors', $data);
        $this->assertArrayHasKey('population', $data);
        $this->assertEquals('Osijek-Baranja', $data['name']);
        $this->assertIsArray($data['courts']);
        $this->assertGreaterThan(0, count($data['courts']));
    }

    public function test_can_get_region_data_for_zagreb(): void
    {
        $data = $this->provider->getRegionData('Zagreb');

        $this->assertIsArray($data);
        $this->assertEquals('Zagreb', $data['name']);
        $this->assertArrayHasKey('courts', $data);
        $this->assertArrayHasKey('prosecutors', $data);
        $this->assertGreaterThan(0, count($data['courts']));
    }

    public function test_can_get_region_data_for_split(): void
    {
        $data = $this->provider->getRegionData('Split-Dalmatia');

        $this->assertIsArray($data);
        $this->assertEquals('Split-Dalmatia', $data['name']);
        $this->assertArrayHasKey('courts', $data);
        $this->assertArrayHasKey('prosecutors', $data);
    }

    public function test_can_get_regional_courts_for_osijek(): void
    {
        $courts = $this->provider->getRegionalCourts('Osijek-Baranja');

        $this->assertIsArray($courts);
        $this->assertGreaterThan(0, count($courts));
        $this->assertContains('Županijski sud u Osijeku', $courts);
        $this->assertContains('Općinski sud u Osijeku', $courts);
    }

    public function test_can_get_regional_courts_for_zagreb(): void
    {
        $courts = $this->provider->getRegionalCourts('Zagreb');

        $this->assertIsArray($courts);
        $this->assertGreaterThan(0, count($courts));
        $this->assertContains('Vrhovni sud Republike Hrvatske', $courts);
        $this->assertContains('Županijski sud u Zagrebu', $courts);
    }

    public function test_can_get_regional_prosecutors_for_osijek(): void
    {
        $prosecutors = $this->provider->getRegionalProsecutors('Osijek-Baranja');

        $this->assertIsArray($prosecutors);
        $this->assertGreaterThan(0, count($prosecutors));
        $this->assertContains('Županijsko državno odvjetništvo u Osijeku', $prosecutors);
    }

    public function test_can_get_regional_prosecutors_for_zagreb(): void
    {
        $prosecutors = $this->provider->getRegionalProsecutors('Zagreb');

        $this->assertIsArray($prosecutors);
        $this->assertGreaterThan(0, count($prosecutors));
        $this->assertContains('Državno odvjetništvo Republike Hrvatske', $prosecutors);
        $this->assertContains('Županijsko državno odvjetništvo u Zagrebu', $prosecutors);
    }

    public function test_region_data_contains_population_info(): void
    {
        $data = $this->provider->getRegionData('Osijek-Baranja');

        $this->assertArrayHasKey('population', $data);
        $this->assertIsInt($data['population']);
        $this->assertGreaterThan(0, $data['population']);
    }

    public function test_all_regions_have_complete_data(): void
    {
        $regions = $this->provider->getAllRegions();

        foreach ($regions as $regionName) {
            $data = $this->provider->getRegionData($regionName);

            $this->assertArrayHasKey('name', $data);
            $this->assertArrayHasKey('county', $data);
            $this->assertArrayHasKey('courts', $data);
            $this->assertArrayHasKey('prosecutors', $data);
            $this->assertArrayHasKey('population', $data);
            $this->assertNotEmpty($data['name']);
            $this->assertNotEmpty($data['courts']);
            $this->assertNotEmpty($data['prosecutors']);
            $this->assertGreaterThan(0, $data['population']);
        }
    }

    public function test_throws_exception_for_unknown_region(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->provider->getRegionData('NonexistentRegion');
    }

    public function test_region_data_uses_croatian_names(): void
    {
        $regions = $this->provider->getAllRegions();

        foreach ($regions as $regionName) {
            $data = $this->provider->getRegionData($regionName);

            // Check that court and prosecutor names are in Croatian
            foreach ($data['courts'] as $court) {
                $this->assertIsString($court);
                $this->assertTrue(
                    str_contains($court, 'sud') || str_contains($court, 'Sud'),
                    "Court name should contain 'sud': {$court}"
                );
            }

            foreach ($data['prosecutors'] as $prosecutor) {
                $this->assertIsString($prosecutor);
                $this->assertTrue(
                    str_contains($prosecutor, 'odvjetništvo') || str_contains($prosecutor, 'Odvjetništvo'),
                    "Prosecutor name should contain 'odvjetništvo': {$prosecutor}"
                );
            }
        }
    }
}
