<?php

namespace Tests\Unit\LegalCitations;

use App\Services\LegalCitations\CitationGuard;
use Tests\TestCase;

class CitationGuardTest extends TestCase
{
    /** @test */
    public function it_detects_presence_of_citations(): void
    {
        $text = 'Prema ZKP čl. 222 potrebno je izdvojiti nezakonite dokaze. NN 94/14. Gž-1234/2023.';

        $this->assertTrue(CitationGuard::hasAnyCitation($text));
        $this->assertFalse(CitationGuard::hasAnyCitation('Ovo je opći tekst bez citata.'));
    }

    /** @test */
    public function it_extracts_identifiers_in_stable_order(): void
    {
        $text = 'ECLI:HR:VSRH:2024:001 i Gž-1234/2023 te NN 94/14 i opet Gž-1234/2023.';

        $ids = CitationGuard::extractIdentifiers($text);

        $this->assertEquals([
            'ECLI:HR:VSRH:2024:001',
            'Gž-1234/2023',
            'NN 94/14',
        ], $ids);
    }

    /** @test */
    public function it_flags_unknown_identifiers_not_in_retrieved_set(): void
    {
        $text = 'Pozivanje na ECLI:HR:VSRH:2024:001 i Gž-9999/2024.';

        $known = ['ECLI:HR:VSRH:2024:001'];

        $result = CitationGuard::validateIdentifiersAgainstKnownSet($text, $known);

        $this->assertFalse($result['ok']);
        $this->assertEquals(['ECLI:HR:VSRH:2024:001', 'Gž-9999/2024'], $result['found']);
        $this->assertEquals(['Gž-9999/2024'], $result['unknown']);
    }
}
