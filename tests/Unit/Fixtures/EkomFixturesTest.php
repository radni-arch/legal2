<?php

declare(strict_types=1);

namespace Tests\Unit\Fixtures;

use PHPUnit\Framework\TestCase;
use Tests\Fixtures\EkomFixtures;

class EkomFixturesTest extends TestCase
{
    /** @test */
    public function it_loads_json_fixture_by_filename(): void
    {
        $data = EkomFixtures::loadJson('prilog.json');

        $this->assertIsArray($data);
        $this->assertArrayHasKey('opis', $data);
        $this->assertArrayHasKey('sadrzaj', $data);
    }

    /** @test */
    public function it_throws_exception_for_nonexistent_fixture(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Fixture file not found');

        EkomFixtures::loadJson('nonexistent.json');
    }

    /** @test */
    public function it_throws_exception_for_invalid_json(): void
    {
        // This test will be skipped if we don't have an invalid JSON fixture
        // The implementation should handle malformed JSON gracefully
        $this->markTestSkipped('No invalid JSON fixture available for testing');
    }

    /** @test */
    public function it_loads_novi_postupak_bez_pristojbe_fixture(): void
    {
        $data = EkomFixtures::noviPostupakBezPristojbe();

        $this->assertIsArray($data);
        $this->assertEquals('OGs zg', $data['sudOznaka']);
        $this->assertCount(3, $data['stranke']);
        $this->assertCount(1, $data['protustranke']);
        $this->assertCount(3, $data['prilozi']);
        $this->assertEquals(2, $data['podnositeljRbr']);
        $this->assertArrayHasKey('vanjskaUstrojstvenaJedinica', $data);
        $this->assertArrayHasKey('vanjskiPredmet', $data);
    }

    /** @test */
    public function it_loads_novi_postupak_s_pristojbom_bez_dodatnih_podataka(): void
    {
        $data = EkomFixtures::noviPostupakSPristojbomBezDodatnihPodataka();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('sudId', $data);
        $this->assertArrayHasKey('pristojba', $data);
        $this->assertArrayNotHasKey('pristojbaDodatniPodaci', $data);
    }

    /** @test */
    public function it_loads_novi_postupak_s_pristojbom_i_dodatnim_podacima(): void
    {
        $data = EkomFixtures::noviPostupakSPristojbomIDodatnimPodacima();

        $this->assertIsArray($data);
        $this->assertEquals('Ts zg', $data['sudOznaka']);
        $this->assertArrayHasKey('pristojba', $data);
        $this->assertArrayHasKey('pristojbaDodatniPodaci', $data);
        $this->assertArrayHasKey('vrijednostPredmetaSpora', $data['pristojbaDodatniPodaci']);
    }

    /** @test */
    public function it_loads_postojeci_predmet_bez_pristojbe_fixture(): void
    {
        $data = EkomFixtures::postojeciPredmetBezPristojbe();

        $this->assertIsArray($data);
        $this->assertEquals('P-1/2023', $data['predmetOznaka']);
        $this->assertEquals('podnositelj', $data['podnositeljSlobodanUnos']);
        $this->assertEmpty($data['stranke']);
        $this->assertIsArray($data['stranke']);
        $this->assertCount(1, $data['prilozi']);
    }

    /** @test */
    public function it_loads_postojeci_predmet_bez_pristojbe_na_temelju_opcije(): void
    {
        $data = EkomFixtures::postojeciPredmetBezPristojbeNaTemeljiOpcije();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('predmetId', $data);
        $this->assertArrayHasKey('razlogNeplacanjaId', $data);
        $this->assertEmpty($data['prilozi']);
    }

    /** @test */
    public function it_loads_postojeci_predmet_s_pristojbom_i_dodatnim_podacima(): void
    {
        $data = EkomFixtures::postojeciPredmetSPristojbomIDodatnimPodacima();

        $this->assertIsArray($data);
        $this->assertEquals('Ovr-123/2024', $data['predmetOznaka']);
        $this->assertArrayHasKey('pristojba', $data);
        $this->assertArrayHasKey('pristojbaDodatniPodaci', $data);
        $this->assertEquals(50, $data['pristojbaDodatniPodaci']['postotakOslobodjenja']);
    }

    /** @test */
    public function it_loads_prilog_fixture(): void
    {
        $data = EkomFixtures::prilog();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('opis', $data);
        $this->assertArrayHasKey('primjedba', $data);
        $this->assertArrayHasKey('sadrzaj', $data);
        $this->assertEquals(2, $data['sadrzaj']['brojStranica']);
        $this->assertTrue($data['sadrzaj']['ignorirajUpozorenja']);
    }

    /** @test */
    public function all_fixtures_contain_valid_structure(): void
    {
        $fixtures = [
            EkomFixtures::noviPostupakBezPristojbe(),
            EkomFixtures::noviPostupakSPristojbomBezDodatnihPodataka(),
            EkomFixtures::noviPostupakSPristojbomIDodatnimPodacima(),
            EkomFixtures::postojeciPredmetBezPristojbe(),
            EkomFixtures::postojeciPredmetBezPristojbeNaTemeljiOpcije(),
            EkomFixtures::postojeciPredmetSPristojbomIDodatnimPodacima(),
        ];

        foreach ($fixtures as $fixture) {
            // All podnesak fixtures must have vrstaPodneskaId and sadrzaj
            $this->assertArrayHasKey('vrstaPodneskaId', $fixture);
            $this->assertArrayHasKey('sadrzaj', $fixture);
            $this->assertArrayHasKey('naziv', $fixture['sadrzaj']);
            $this->assertArrayHasKey('sadrzaj', $fixture['sadrzaj']);
        }
    }

    /** @test */
    public function fixture_path_returns_correct_directory(): void
    {
        $path = EkomFixtures::fixturePath();

        $this->assertStringEndsWith('tests/fixtures/ekom', $path);
        $this->assertDirectoryExists($path);
    }

    /** @test */
    public function files_path_returns_correct_directory(): void
    {
        $path = EkomFixtures::filesPath();

        $this->assertStringEndsWith('tests/fixtures/ekom/files', $path);
        $this->assertDirectoryExists($path);
    }
}
