<?php

namespace Tests\Unit\Research;

use App\Services\Research\CitationGuard;
use Tests\TestCase;

class CitationGuardTest extends TestCase
{
    /** @test */
    public function it_detects_common_croatian_citation_patterns(): void
    {
        $text = <<<TXT
U postupku je došlo do bitne povrede odredaba kaznenog postupka.
Pozivamo se na ZKP Čl. 220 i Čl. 222, te na NN 53/91.
Predmet: Gž-1234/2023.
ECLI:HR:VSRH:2024:001.
TXT;

        $this->assertTrue(CitationGuard::hasCitations($text));

        $extracted = CitationGuard::extract($text);

        $this->assertNotEmpty($extracted);
        $this->assertContains('NN 53/91', $extracted);
        $this->assertContains('Gž-1234/2023', $extracted);
        $this->assertTrue((bool) array_filter($extracted, fn ($c) => str_contains($c, 'Čl. 220') || str_contains($c, 'čl. 220') || str_contains($c, 'članak 220')));
        $this->assertTrue((bool) array_filter($extracted, fn ($c) => str_starts_with($c, 'ECLI:')));
    }

    /** @test */
    public function it_returns_false_for_plain_text_without_citations(): void
    {
        $text = 'Ovo je opis situacije bez ikakvih citata ili brojeva predmeta.';

        $this->assertFalse(CitationGuard::hasCitations($text));
        $this->assertSame([], CitationGuard::extract($text));
    }

    /** @test */
    public function assert_has_citations_throws_when_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CitationGuard::assertHasCitations('nema citata', 'agent_answer');
    }
}
