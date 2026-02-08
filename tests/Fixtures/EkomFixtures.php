<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use InvalidArgumentException;
use JsonException;

/**
 * Helper class for loading e-Komunikacija test fixtures.
 *
 * Provides convenient methods for loading JSON fixtures representing
 * various podnesak (submission) scenarios for e-Komunikacija API testing.
 */
class EkomFixtures
{
    /**
     * Base path to ekom fixtures directory.
     */
    public static function fixturePath(): string
    {
        return dirname(__DIR__) . '/fixtures/ekom';
    }

    /**
     * Path to test files directory.
     */
    public static function filesPath(): string
    {
        return self::fixturePath() . '/files';
    }

    /**
     * Load a JSON fixture file by name.
     *
     * @param string $name Filename (e.g., 'prilog.json')
     * @return array Decoded JSON data
     * @throws InvalidArgumentException If file not found or JSON is invalid
     */
    public static function loadJson(string $name): array
    {
        $path = self::fixturePath() . '/' . $name;

        if (!file_exists($path)) {
            throw new InvalidArgumentException("Fixture file not found: {$name}");
        }

        $content = file_get_contents($path);

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException("Invalid JSON in fixture {$name}: {$e->getMessage()}");
        }

        return $data;
    }

    /**
     * Load new proceeding without fee fixture.
     *
     * Scenario: 3 stranke (one with predstavljaniSubjekt), 1 protustranka, 3 prilozi
     * Uses sudOznaka, podnositeljRbr, vanjskaUstrojstvenaJedinica, vanjskiPredmet
     */
    public static function noviPostupakBezPristojbe(): array
    {
        return self::loadJson('podnesak-novi_postupak-bez_pristojbe.json');
    }

    /**
     * Load new proceeding with fee but without additional fee data fixture.
     *
     * Scenario: Basic fee information, uses sudId
     */
    public static function noviPostupakSPristojbomBezDodatnihPodataka(): array
    {
        return self::loadJson('podnesak-novi_postupak-s_pristojbom_bez_dodatnih_podataka_pristojbe.json');
    }

    /**
     * Load new proceeding with fee and additional fee data fixture.
     *
     * Scenario: Full fee data including vrijednostPredmetaSpora, labor case
     */
    public static function noviPostupakSPristojbomIDodatnimPodacima(): array
    {
        return self::loadJson('podnesak-novi_postupak-s_pristojbom_i_dodatnim_podacima_pristojbe.json');
    }

    /**
     * Load existing case without fee fixture.
     *
     * Scenario: Minimal payload using predmetOznaka, podnositeljSlobodanUnos
     */
    public static function postojeciPredmetBezPristojbe(): array
    {
        return self::loadJson('podnesak-postojeci_predmet-bez_pristojbe.json');
    }

    /**
     * Load existing case without fee based on non-payment option fixture.
     *
     * Scenario: Uses predmetId, razlogNeplacanjaId for fee exemption
     */
    public static function postojeciPredmetBezPristojbeNaTemeljiOpcije(): array
    {
        return self::loadJson('podnesak-postojeci_predmet-bez_pristojbe_na_temelju_opcije.json');
    }

    /**
     * Load existing case with fee and additional fee data fixture.
     *
     * Scenario: Partial exemption with osnovaOslobodjenjaId and postotakOslobodjenja
     */
    public static function postojeciPredmetSPristojbomIDodatnimPodacima(): array
    {
        return self::loadJson('podnesak-postojeci_predmet-s_pristojbom_i_dodatnim_podacima_pristojbe.json');
    }

    /**
     * Load standalone attachment fixture.
     *
     * Scenario: Prilog with opis, primjedba, and sadrzaj including ignorirajUpozorenja
     */
    public static function prilog(): array
    {
        return self::loadJson('prilog.json');
    }

    /**
     * Get all podnesak fixtures.
     *
     * @return array<string, array> Associative array of fixture name => data
     */
    public static function allPodnesci(): array
    {
        return [
            'novi_postupak_bez_pristojbe' => self::noviPostupakBezPristojbe(),
            'novi_postupak_s_pristojbom_bez_dodatnih_podataka' => self::noviPostupakSPristojbomBezDodatnihPodataka(),
            'novi_postupak_s_pristojbom_i_dodatnim_podacima' => self::noviPostupakSPristojbomIDodatnimPodacima(),
            'postojeci_predmet_bez_pristojbe' => self::postojeciPredmetBezPristojbe(),
            'postojeci_predmet_bez_pristojbe_na_temelju_opcije' => self::postojeciPredmetBezPristojbeNaTemeljiOpcije(),
            'postojeci_predmet_s_pristojbom_i_dodatnim_podacima' => self::postojeciPredmetSPristojbomIDodatnimPodacima(),
        ];
    }

    /**
     * Create a minimal valid podnesak payload for testing.
     *
     * @param bool $newProceeding True for new proceeding, false for existing case
     */
    public static function minimalPodnesak(bool $newProceeding = true): array
    {
        $base = [
            'vrstaPodneskaId' => 1,
            'sadrzaj' => [
                'naziv' => 'test.pdf',
                'sadrzaj' => base64_encode('test content'),
                'brojStranica' => 1,
                'ignorirajUpozorenja' => false,
            ],
        ];

        if ($newProceeding) {
            return array_merge($base, [
                'sudOznaka' => 'OGs zg',
                'vrstaPostupkaId' => 1,
                'ulogaPodnositeljaId' => 1,
                'stranke' => [
                    [
                        'tip' => 'FIZICKA_OSOBA',
                        'ime' => 'Test',
                        'prezime' => 'Korisnik',
                        'oib' => '12345678901',
                        'ulogaId' => 1,
                    ],
                ],
                'protustranke' => [],
                'prilozi' => [],
            ]);
        }

        return array_merge($base, [
            'predmetOznaka' => 'P-1/2024',
            'stranke' => [],
            'protustranke' => [],
            'prilozi' => [],
        ]);
    }
}
