<?php

namespace Tests\Unit\Legal;

use App\Services\LegalCitations\CitationGuard;
use Tests\TestCase;

class CitationGuardTest extends TestCase
{
    public function test_has_any_citation_detects_common_patterns(): void
    {
        $withCitations = 'Pozivamo se na ZKP čl. 222 i Ustav RH čl. 34. Također NN 35/05 te ECLI:HR:VSRH:2024:001.';
        $this->assertTrue(CitationGuard::hasAnyCitation($withCitations));

        $withoutCitations = 'Ovo je općenita analiza bez ikakvih citata ili brojeva predmeta.';
        $this->assertFalse(CitationGuard::hasAnyCitation($withoutCitations));
    }

    public function test_validate_source_identifiers_flags_unknown_ids(): void
    {
        $answer = 'Relevantna odluka je ECLI:HR:VSRH:2024:001. Također navodimo ECLI:HR:FAKE:2024:999.';

        $allowed = [
            'ECLI:HR:VSRH:2024:001',
        ];

        $result = CitationGuard::validateSourceIdentifiers($answer, $allowed);

        $this->assertFalse($result['ok']);
        $this->assertContains('ECLI:HR:FAKE:2024:999', $result['missing']);
        $this->assertContains('ECLI:HR:VSRH:2024:001', $result['found']);
    }

    public function test_validate_source_identifiers_passes_when_all_ids_known(): void
    {
        $answer = 'Odluka: ECLI:HR:VSRH:2024:001 i broj predmeta K-123/2024.';

        $allowed = [
            'ECLI:HR:VSRH:2024:001',
            'K-123/2024',
        ];

        $result = CitationGuard::validateSourceIdentifiers($answer, $allowed);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['missing']);
    }
}
