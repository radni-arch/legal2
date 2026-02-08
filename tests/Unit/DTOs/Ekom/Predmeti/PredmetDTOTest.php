<?php

namespace Tests\Unit\DTOs\Ekom\Predmeti;

use App\DTOs\Ekom\PaginatedResponse;
use App\DTOs\Ekom\Predmeti\DokumentDTO;
use App\DTOs\Ekom\Predmeti\PagedPredmetDTO;
use App\DTOs\Ekom\Predmeti\PredmetDTO;
use App\DTOs\Ekom\Predmeti\SudskaRadnjaDTO;
use App\DTOs\Ekom\Predmeti\SudionikDTO;
use App\DTOs\Ekom\Predmeti\SudDTO;
use App\DTOs\Ekom\Predmeti\VezaPredmetaDTO;
use App\DTOs\Ekom\Predmeti\VrstaDTO;
use PHPUnit\Framework\TestCase;

class PredmetDTOTest extends TestCase
{
    private function fullApiResponse(): array
    {
        return [
            'id' => 123,
            'status' => 'U_RADU',
            'oznaka' => 'P-123/2024',
            'sud' => [
                'id' => 10,
                'naziv' => 'Opcinski sud u Zagrebu',
                'oznaka' => 'OSZG',
                'vrsta' => [
                    'id' => 1,
                    'naziv' => 'Opcinski sud',
                ],
            ],
            'pisarnica' => 'Pisarnica 1',
            'referada' => 'Referada A',
            'vrsta' => [
                'id' => 5,
                'naziv' => 'Parnicni postupak',
                'oznaka' => 'P',
            ],
            'sudionici' => [
                [
                    'id' => 201,
                    'ime' => 'Ivan',
                    'prezime' => 'Horvat',
                    'naziv' => null,
                    'oib' => '12345678901',
                    'uloga' => 'TUZITELJ',
                    'tip' => 'FIZICKA_OSOBA',
                ],
                [
                    'id' => 202,
                    'ime' => null,
                    'prezime' => null,
                    'naziv' => 'Firma d.o.o.',
                    'oib' => '98765432109',
                    'uloga' => 'TUZENIK',
                    'tip' => 'PRAVNA_OSOBA',
                ],
            ],
            'sudskeRadnje' => [
                [
                    'id' => 301,
                    'vrsta' => 'ROCISTE',
                    'status' => 'ZAKAZANO',
                    'datum' => '2024-06-15',
                    'opis' => 'Pripremno rociste',
                ],
            ],
            'dokumenti' => [
                [
                    'id' => 401,
                    'naziv' => 'Tuzba.pdf',
                    'tip' => 'PDF',
                    'datum' => '2024-01-10',
                    'velicinaBajtovi' => 102400,
                ],
            ],
            'vezePredmeta' => [
                [
                    'id' => 501,
                    'oznaka' => 'P-456/2024',
                    'sud' => 'Opcinski sud u Zagrebu',
                    'vrstaVeze' => 'SPOJENI',
                ],
            ],
            'doNotDisturb' => true,
        ];
    }

    private function minimalApiResponse(): array
    {
        return [
            'id' => 1,
            'status' => 'ARHIVIRAN',
            'oznaka' => 'X-1/2024',
            'sud' => null,
            'pisarnica' => null,
            'referada' => null,
            'vrsta' => null,
            'sudionici' => [],
            'sudskeRadnje' => [],
            'dokumenti' => [],
            'vezePredmeta' => [],
            'doNotDisturb' => false,
        ];
    }

    // ---------- PredmetDTO Tests ----------

    public function test_predmet_dto_from_full_api_response(): void
    {
        $data = $this->fullApiResponse();
        $dto = PredmetDTO::fromApiResponse($data);

        $this->assertSame(123, $dto->id);
        $this->assertSame('U_RADU', $dto->status);
        $this->assertSame('P-123/2024', $dto->oznaka);
        $this->assertSame('Pisarnica 1', $dto->pisarnica);
        $this->assertSame('Referada A', $dto->referada);
        $this->assertTrue($dto->doNotDisturb);

        // Sud
        $this->assertInstanceOf(SudDTO::class, $dto->sud);
        $this->assertSame(10, $dto->sud->id);
        $this->assertSame('Opcinski sud u Zagrebu', $dto->sud->naziv);
        $this->assertSame('OSZG', $dto->sud->oznaka);
        $this->assertNotNull($dto->sud->vrsta);
        $this->assertSame(1, $dto->sud->vrsta['id']);

        // Vrsta
        $this->assertInstanceOf(VrstaDTO::class, $dto->vrsta);
        $this->assertSame(5, $dto->vrsta->id);
        $this->assertSame('Parnicni postupak', $dto->vrsta->naziv);
        $this->assertSame('P', $dto->vrsta->oznaka);

        // Sudionici
        $this->assertCount(2, $dto->sudionici);
        $this->assertInstanceOf(SudionikDTO::class, $dto->sudionici[0]);
        $this->assertSame('Ivan', $dto->sudionici[0]->ime);
        $this->assertSame('Horvat', $dto->sudionici[0]->prezime);
        $this->assertSame('TUZITELJ', $dto->sudionici[0]->uloga);

        // Sudske radnje
        $this->assertCount(1, $dto->sudskeRadnje);
        $this->assertInstanceOf(SudskaRadnjaDTO::class, $dto->sudskeRadnje[0]);
        $this->assertSame('ROCISTE', $dto->sudskeRadnje[0]->vrsta);

        // Dokumenti
        $this->assertCount(1, $dto->dokumenti);
        $this->assertInstanceOf(DokumentDTO::class, $dto->dokumenti[0]);
        $this->assertSame('Tuzba.pdf', $dto->dokumenti[0]->naziv);
        $this->assertSame(102400, $dto->dokumenti[0]->velicinaBajtovi);

        // Veze predmeta
        $this->assertCount(1, $dto->vezePredmeta);
        $this->assertInstanceOf(VezaPredmetaDTO::class, $dto->vezePredmeta[0]);
        $this->assertSame('SPOJENI', $dto->vezePredmeta[0]->vrstaVeze);
    }

    public function test_predmet_dto_from_minimal_api_response(): void
    {
        $data = $this->minimalApiResponse();
        $dto = PredmetDTO::fromApiResponse($data);

        $this->assertSame(1, $dto->id);
        $this->assertSame('ARHIVIRAN', $dto->status);
        $this->assertSame('X-1/2024', $dto->oznaka);
        $this->assertNull($dto->sud);
        $this->assertNull($dto->pisarnica);
        $this->assertNull($dto->referada);
        $this->assertNull($dto->vrsta);
        $this->assertEmpty($dto->sudionici);
        $this->assertEmpty($dto->sudskeRadnje);
        $this->assertEmpty($dto->dokumenti);
        $this->assertEmpty($dto->vezePredmeta);
        $this->assertFalse($dto->doNotDisturb);
    }

    public function test_nested_dtos_are_properly_hydrated(): void
    {
        $data = $this->fullApiResponse();
        $dto = PredmetDTO::fromApiResponse($data);

        // SudionikDTO fields
        $sudionik = $dto->sudionici[1];
        $this->assertSame(202, $sudionik->id);
        $this->assertNull($sudionik->ime);
        $this->assertNull($sudionik->prezime);
        $this->assertSame('Firma d.o.o.', $sudionik->naziv);
        $this->assertSame('98765432109', $sudionik->oib);
        $this->assertSame('TUZENIK', $sudionik->uloga);
        $this->assertSame('PRAVNA_OSOBA', $sudionik->tip);

        // SudskaRadnjaDTO fields
        $radnja = $dto->sudskeRadnje[0];
        $this->assertSame(301, $radnja->id);
        $this->assertSame('ZAKAZANO', $radnja->status);
        $this->assertSame('2024-06-15', $radnja->datum);
        $this->assertSame('Pripremno rociste', $radnja->opis);

        // DokumentDTO fields
        $dokument = $dto->dokumenti[0];
        $this->assertSame(401, $dokument->id);
        $this->assertSame('PDF', $dokument->tip);
        $this->assertSame('2024-01-10', $dokument->datum);

        // VezaPredmetaDTO fields
        $veza = $dto->vezePredmeta[0];
        $this->assertSame(501, $veza->id);
        $this->assertSame('P-456/2024', $veza->oznaka);
        $this->assertSame('Opcinski sud u Zagrebu', $veza->sud);
    }

    // ---------- PagedPredmetDTO Tests ----------

    public function test_paged_predmet_dto_from_api_response(): void
    {
        $data = [
            'id' => 123,
            'oznaka' => 'P-123/2024',
            'status' => 'U_RADU',
            'sud' => [
                'id' => 10,
                'naziv' => 'Opcinski sud u Zagrebu',
            ],
            'doNotDisturb' => false,
        ];

        $dto = PagedPredmetDTO::fromApiResponse($data);

        $this->assertSame(123, $dto->id);
        $this->assertSame('P-123/2024', $dto->oznaka);
        $this->assertSame('U_RADU', $dto->status);
        $this->assertSame('Opcinski sud u Zagrebu', $dto->sudNaziv);
        $this->assertSame(10, $dto->sudId);
        $this->assertFalse($dto->doNotDisturb);
    }

    public function test_paged_predmet_dto_with_no_sud(): void
    {
        $data = [
            'id' => 1,
            'oznaka' => 'X-1/2024',
            'status' => 'ARHIVIRAN',
            'sud' => null,
            'doNotDisturb' => true,
        ];

        $dto = PagedPredmetDTO::fromApiResponse($data);

        $this->assertNull($dto->sudNaziv);
        $this->assertNull($dto->sudId);
        $this->assertTrue($dto->doNotDisturb);
    }

    // ---------- PaginatedResponse Tests ----------

    public function test_paginated_response_from_api_response(): void
    {
        $apiData = [
            'content' => [
                ['id' => 1, 'oznaka' => 'A', 'status' => 'U_RADU', 'sud' => null, 'doNotDisturb' => false],
                ['id' => 2, 'oznaka' => 'B', 'status' => 'ARHIVIRAN', 'sud' => null, 'doNotDisturb' => true],
            ],
            'page' => 0,
            'size' => 50,
            'totalElements' => 2,
            'totalPages' => 1,
            'first' => true,
            'last' => true,
        ];

        $response = PaginatedResponse::fromApiResponse(
            $apiData,
            fn (array $item) => PagedPredmetDTO::fromApiResponse($item)
        );

        $this->assertSame(0, $response->page);
        $this->assertSame(50, $response->size);
        $this->assertSame(2, $response->totalElements);
        $this->assertSame(1, $response->totalPages);
        $this->assertTrue($response->first);
        $this->assertTrue($response->last);
        $this->assertCount(2, $response->content);
        $this->assertInstanceOf(PagedPredmetDTO::class, $response->content[0]);
    }

    // ---------- SudDTO Tests ----------

    public function test_sud_dto_from_api_response(): void
    {
        $data = [
            'id' => 10,
            'naziv' => 'Opcinski sud u Zagrebu',
            'oznaka' => 'OSZG',
            'vrsta' => ['id' => 1, 'naziv' => 'Opcinski sud'],
        ];

        $dto = SudDTO::fromApiResponse($data);

        $this->assertSame(10, $dto->id);
        $this->assertSame('Opcinski sud u Zagrebu', $dto->naziv);
        $this->assertSame('OSZG', $dto->oznaka);
        $this->assertSame(['id' => 1, 'naziv' => 'Opcinski sud'], $dto->vrsta);
    }

    public function test_sud_dto_with_null_vrsta(): void
    {
        $data = [
            'id' => 10,
            'naziv' => 'Sud',
            'oznaka' => 'S',
            'vrsta' => null,
        ];

        $dto = SudDTO::fromApiResponse($data);
        $this->assertNull($dto->vrsta);
    }
}
