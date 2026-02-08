<?php

namespace Tests\Unit\Legal;

use App\Services\Legal\LegalCitationGuard;
use PHPUnit\Framework\TestCase;

class LegalCitationGuardTest extends TestCase
{
    /** @test */
    public function it_detects_narodne_novine_and_article_citations(): void
    {
        $text = 'Prema NN 35/05 Članak 1045. i ZKP Čl. 222 dokaz je nezakonit.';
        $this->assertTrue(LegalCitationGuard::hasCitations($text));
    }

    /** @test */
    public function it_detects_case_number_citations(): void
    {
        $text = 'U predmetu Gž-1234/2023 sud je odlučio drugačije.';
        $this->assertTrue(LegalCitationGuard::hasCitations($text));
    }

    /** @test */
    public function it_detects_ecli_citations(): void
    {
        $text = 'Relevantno: ECLI:HR:VSRH:2024:001';
        $this->assertTrue(LegalCitationGuard::hasCitations($text));
    }

    /** @test */
    public function it_returns_false_for_plain_text_without_citations(): void
    {
        $text = 'Ovo je opći opis problema bez ikakvih referenci.';
        $this->assertFalse(LegalCitationGuard::hasCitations($text));
    }

    /** @test */
    public function it_throws_when_asserting_citations_and_none_present(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        LegalCitationGuard::assertHasCitations('No citations here');
    }
}
