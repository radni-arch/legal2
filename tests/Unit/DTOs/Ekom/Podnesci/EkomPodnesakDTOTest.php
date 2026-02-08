<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs\Ekom\Podnesci;

use App\DTOs\Ekom\Podnesci\AdresaDTO;
use App\DTOs\Ekom\Podnesci\EkomPodnesakDTO;
use App\DTOs\Ekom\Podnesci\PrilogDTO;
use App\DTOs\Ekom\Podnesci\PristojbaDTO;
use App\DTOs\Ekom\Podnesci\StrankaDTO;
use PHPUnit\Framework\TestCase;

class EkomPodnesakDTOTest extends TestCase
{
    private function fullApiResponse(): array
    {
        return [
            'id' => 42,
            'status' => 'NACRT',
            'sudId' => 10,
            'sudNaziv' => 'Opcinski sud u Zagrebu',
            'predmetId' => 100,
            'predmetOznaka' => 'P-123/2025',
            'vrstaPostupkaId' => 5,
            'vrstaPodneskaId' => 7,
            'vrstaPodneskaOznaka' => 'TUZBA',
            'stranke' => [
                [
                    'id' => 1,
                    'tip' => 'FIZICKA_OSOBA',
                    'oib' => '12345678901',
                    'ime' => 'Ivan',
                    'prezime' => 'Horvat',
                    'naziv' => null,
                    'ulogaId' => 3,
                    'ulogaNaziv' => 'Tuzitelj',
                    'adresa' => [
                        'drzavaId' => 1,
                        'drzavaNaziv' => 'Hrvatska',
                        'zupanijaId' => 21,
                        'opcinaId' => 133,
                        'naseljeId' => 500,
                        'postanskiBroj' => '10000',
                        'ulicaIKucniBroj' => 'Ilica 1',
                    ],
                ],
            ],
            'protustranke' => [
                [
                    'id' => 2,
                    'tip' => 'PRAVNA_OSOBA',
                    'oib' => '98765432109',
                    'ime' => null,
                    'prezime' => null,
                    'naziv' => 'Firma d.o.o.',
                    'ulogaId' => 4,
                    'ulogaNaziv' => 'Tuzenik',
                    'adresa' => null,
                ],
            ],
            'prilozi' => [
                [
                    'id' => 10,
                    'naziv' => 'tuzba.pdf',
                    'opis' => 'Glavna tuzba',
                    'primjedba' => null,
                    'velicinaBajtovi' => 102400,
                ],
            ],
            'pristojba' => [
                'vrsta' => 'SUDSKA',
                'iznos' => 100.00,
                'placeno' => 0.00,
                'ostatak' => 100.00,
                'valuta' => 'EUR',
                'detaljiIzracuna' => ['base' => 100],
                'razlogNeplacanjaId' => null,
                'postotakOslobodjenja' => null,
                'osnovaOslobodjenjaId' => null,
            ],
            'prosljedjivanja' => [
                ['sudId' => 11, 'datum' => '2025-01-01'],
            ],
            'vrijemeKreiranja' => '2025-06-01T10:00:00',
            'vrijemeSlanja' => null,
        ];
    }

    public function test_from_api_response_with_full_data(): void
    {
        $dto = EkomPodnesakDTO::fromApiResponse($this->fullApiResponse());

        $this->assertSame(42, $dto->id);
        $this->assertSame('NACRT', $dto->status);
        $this->assertSame(10, $dto->sudId);
        $this->assertSame('Opcinski sud u Zagrebu', $dto->sudNaziv);
        $this->assertSame(100, $dto->predmetId);
        $this->assertSame('P-123/2025', $dto->predmetOznaka);
        $this->assertSame(5, $dto->vrstaPostupkaId);
        $this->assertSame(7, $dto->vrstaPodneskaId);
        $this->assertSame('TUZBA', $dto->vrstaPodneskaOznaka);
        $this->assertSame('2025-06-01T10:00:00', $dto->vrijemeKreiranja);
        $this->assertNull($dto->vrijemeSlanja);
    }

    public function test_is_draft_returns_true_for_nacrt(): void
    {
        $dto = EkomPodnesakDTO::fromApiResponse($this->fullApiResponse());

        $this->assertTrue($dto->isDraft());
        $this->assertFalse($dto->isSent());
    }

    public function test_is_sent_returns_true_for_poslan(): void
    {
        $data = $this->fullApiResponse();
        $data['status'] = 'POSLAN';
        $data['vrijemeSlanja'] = '2025-06-02T12:00:00';

        $dto = EkomPodnesakDTO::fromApiResponse($data);

        $this->assertTrue($dto->isSent());
        $this->assertFalse($dto->isDraft());
    }

    public function test_stranke_are_properly_hydrated(): void
    {
        $dto = EkomPodnesakDTO::fromApiResponse($this->fullApiResponse());

        $this->assertCount(1, $dto->stranke);
        $stranka = $dto->stranke[0];
        $this->assertInstanceOf(StrankaDTO::class, $stranka);
        $this->assertSame(1, $stranka->id);
        $this->assertSame('FIZICKA_OSOBA', $stranka->tip);
        $this->assertSame('12345678901', $stranka->oib);
        $this->assertSame('Ivan', $stranka->ime);
        $this->assertSame('Horvat', $stranka->prezime);
        $this->assertTrue($stranka->isFizickaOsoba());

        // Nested address
        $this->assertInstanceOf(AdresaDTO::class, $stranka->adresa);
        $this->assertSame(1, $stranka->adresa->drzavaId);
        $this->assertSame('Hrvatska', $stranka->adresa->drzavaNaziv);
        $this->assertSame('10000', $stranka->adresa->postanskiBroj);
        $this->assertSame('Ilica 1', $stranka->adresa->ulicaIKucniBroj);
    }

    public function test_protustranke_are_properly_hydrated(): void
    {
        $dto = EkomPodnesakDTO::fromApiResponse($this->fullApiResponse());

        $this->assertCount(1, $dto->protustranke);
        $protustranka = $dto->protustranke[0];
        $this->assertInstanceOf(StrankaDTO::class, $protustranka);
        $this->assertSame('PRAVNA_OSOBA', $protustranka->tip);
        $this->assertSame('Firma d.o.o.', $protustranka->naziv);
        $this->assertTrue($protustranka->isPravnaOsoba());
        $this->assertNull($protustranka->adresa);
    }

    public function test_prilozi_are_properly_hydrated(): void
    {
        $dto = EkomPodnesakDTO::fromApiResponse($this->fullApiResponse());

        $this->assertCount(1, $dto->prilozi);
        $prilog = $dto->prilozi[0];
        $this->assertInstanceOf(PrilogDTO::class, $prilog);
        $this->assertSame(10, $prilog->id);
        $this->assertSame('tuzba.pdf', $prilog->naziv);
        $this->assertSame('Glavna tuzba', $prilog->opis);
        $this->assertSame(102400, $prilog->velicinaBajtovi);
    }

    public function test_pristojba_is_properly_hydrated(): void
    {
        $dto = EkomPodnesakDTO::fromApiResponse($this->fullApiResponse());

        $this->assertInstanceOf(PristojbaDTO::class, $dto->pristojba);
        $this->assertSame('SUDSKA', $dto->pristojba->vrsta);
        $this->assertSame(100.00, $dto->pristojba->iznos);
        $this->assertSame(0.00, $dto->pristojba->placeno);
        $this->assertSame(100.00, $dto->pristojba->ostatak);
        $this->assertSame('EUR', $dto->pristojba->valuta);
        $this->assertTrue($dto->pristojba->hasOutstandingBalance());
        $this->assertFalse($dto->pristojba->isFullyExempt());
    }

    public function test_prosljedjivanja_are_preserved(): void
    {
        $dto = EkomPodnesakDTO::fromApiResponse($this->fullApiResponse());

        $this->assertCount(1, $dto->prosljedjivanja);
        $this->assertSame(11, $dto->prosljedjivanja[0]['sudId']);
    }

    public function test_from_api_response_with_minimal_data(): void
    {
        $dto = EkomPodnesakDTO::fromApiResponse([
            'id' => 1,
            'status' => 'NACRT',
        ]);

        $this->assertSame(1, $dto->id);
        $this->assertSame('NACRT', $dto->status);
        $this->assertNull($dto->sudId);
        $this->assertNull($dto->pristojba);
        $this->assertEmpty($dto->stranke);
        $this->assertEmpty($dto->protustranke);
        $this->assertEmpty($dto->prilozi);
        $this->assertEmpty($dto->prosljedjivanja);
    }

    public function test_pristojba_fully_exempt(): void
    {
        $pristojba = PristojbaDTO::fromApiResponse([
            'vrsta' => 'SUDSKA',
            'iznos' => 100.00,
            'placeno' => 0.00,
            'ostatak' => 0.00,
            'valuta' => 'EUR',
            'postotakOslobodjenja' => 100.0,
            'osnovaOslobodjenjaId' => 5,
        ]);

        $this->assertFalse($pristojba->hasOutstandingBalance());
        $this->assertTrue($pristojba->isFullyExempt());
    }

    public function test_status_validacije_potpisa_is_hydrated(): void
    {
        $data = $this->fullApiResponse();
        $data['statusValidacijePotpisa'] = 'PRIHVATLJIV';

        $dto = EkomPodnesakDTO::fromApiResponse($data);

        $this->assertSame('PRIHVATLJIV', $dto->statusValidacijePotpisa);
    }

    public function test_status_validacije_potpisa_defaults_to_null(): void
    {
        $dto = EkomPodnesakDTO::fromApiResponse(['id' => 1, 'status' => 'NACRT']);

        $this->assertNull($dto->statusValidacijePotpisa);
    }

    public function test_sadrzaj_is_hydrated(): void
    {
        $data = $this->fullApiResponse();
        $data['sadrzaj'] = ['naziv' => 'dokument.pdf', 'velicinaBajtovi' => 1024];

        $dto = EkomPodnesakDTO::fromApiResponse($data);

        $this->assertSame(['naziv' => 'dokument.pdf', 'velicinaBajtovi' => 1024], $dto->sadrzaj);
    }

    public function test_sadrzaj_defaults_to_null(): void
    {
        $dto = EkomPodnesakDTO::fromApiResponse(['id' => 1, 'status' => 'NACRT']);

        $this->assertNull($dto->sadrzaj);
    }

    public function test_has_valid_signature_returns_true_for_prihvatljiv(): void
    {
        $data = $this->fullApiResponse();
        $data['statusValidacijePotpisa'] = 'PRIHVATLJIV';

        $dto = EkomPodnesakDTO::fromApiResponse($data);

        $this->assertTrue($dto->hasValidSignature());
    }

    public function test_has_valid_signature_returns_false_for_neprihvatljiv(): void
    {
        $data = $this->fullApiResponse();
        $data['statusValidacijePotpisa'] = 'NEPRIHVATLJIV';

        $dto = EkomPodnesakDTO::fromApiResponse($data);

        $this->assertFalse($dto->hasValidSignature());
    }

    public function test_has_valid_signature_returns_false_when_null(): void
    {
        $data = $this->fullApiResponse();
        $data['statusValidacijePotpisa'] = null;

        $dto = EkomPodnesakDTO::fromApiResponse($data);

        $this->assertFalse($dto->hasValidSignature());
    }
}
