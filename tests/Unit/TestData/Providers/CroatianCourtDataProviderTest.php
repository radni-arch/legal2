<?php

namespace Tests\Unit\TestData\Providers;

use Tests\TestCase;
use Tests\TestData\Providers\CroatianCourtDataProvider;

class CroatianCourtDataProviderTest extends TestCase
{
    private CroatianCourtDataProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new CroatianCourtDataProvider;
    }

    public function test_can_get_supreme_court(): void
    {
        $courts = $this->provider->getCourtsByLevel('supreme');

        $this->assertCount(1, $courts);
        $this->assertEquals('Vrhovni sud Republike Hrvatske', $courts[0]['name']);
        $this->assertEquals('supreme', $courts[0]['level']);
        $this->assertEquals('Zagreb', $courts[0]['region']);
        $this->assertArrayHasKey('address', $courts[0]);
    }

    public function test_can_get_high_courts(): void
    {
        $courts = $this->provider->getCourtsByLevel('high');

        $this->assertGreaterThanOrEqual(1, count($courts));
        $this->assertContains('Visoki kazneni sud Republike Hrvatske', array_column($courts, 'name'));
    }

    public function test_can_get_county_courts(): void
    {
        $courts = $this->provider->getCourtsByLevel('county');

        $this->assertGreaterThanOrEqual(3, count($courts));
        $this->assertContains('Županijski sud u Osijeku', array_column($courts, 'name'));
        $this->assertContains('Županijski sud u Zagrebu', array_column($courts, 'name'));
        $this->assertContains('Županijski sud u Splitu', array_column($courts, 'name'));
    }

    public function test_can_get_municipal_courts(): void
    {
        $courts = $this->provider->getCourtsByLevel('municipal');

        $this->assertGreaterThanOrEqual(1, count($courts));
        $this->assertContains('Općinski sud u Osijeku', array_column($courts, 'name'));
    }

    public function test_can_get_courts_by_region_osijek(): void
    {
        $courts = $this->provider->getCourtsByRegion('Osijek');

        $this->assertGreaterThan(0, count($courts));
        foreach ($courts as $court) {
            $this->assertStringContainsStringIgnoringCase('Osijek', $court['region'].$court['name']);
        }
    }

    public function test_can_get_courts_by_region_zagreb(): void
    {
        $courts = $this->provider->getCourtsByRegion('Zagreb');

        $this->assertGreaterThan(0, count($courts));
        $this->assertContains('Vrhovni sud Republike Hrvatske', array_column($courts, 'name'));
    }

    public function test_can_get_random_court_without_level(): void
    {
        $court = $this->provider->getRandomCourt();

        $this->assertIsArray($court);
        $this->assertArrayHasKey('name', $court);
        $this->assertArrayHasKey('level', $court);
        $this->assertArrayHasKey('region', $court);
        $this->assertArrayHasKey('address', $court);
    }

    public function test_can_get_random_court_with_level(): void
    {
        $court = $this->provider->getRandomCourt('supreme');

        $this->assertIsArray($court);
        $this->assertEquals('supreme', $court['level']);
        $this->assertEquals('Vrhovni sud Republike Hrvatske', $court['name']);
    }

    public function test_can_get_all_courts(): void
    {
        $courts = $this->provider->getAllCourts();

        $this->assertGreaterThanOrEqual(6, count($courts));
        $this->assertContains('Vrhovni sud Republike Hrvatske', array_column($courts, 'name'));
        $this->assertContains('Visoki kazneni sud Republike Hrvatske', array_column($courts, 'name'));
        $this->assertContains('Županijski sud u Osijeku', array_column($courts, 'name'));
    }

    public function test_all_courts_have_required_fields(): void
    {
        $courts = $this->provider->getAllCourts();

        foreach ($courts as $court) {
            $this->assertArrayHasKey('name', $court);
            $this->assertArrayHasKey('level', $court);
            $this->assertArrayHasKey('region', $court);
            $this->assertArrayHasKey('address', $court);
            $this->assertNotEmpty($court['name']);
            $this->assertNotEmpty($court['level']);
            $this->assertNotEmpty($court['region']);
            $this->assertNotEmpty($court['address']);
        }
    }
}
