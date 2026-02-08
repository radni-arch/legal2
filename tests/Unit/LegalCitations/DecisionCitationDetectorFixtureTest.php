<?php

namespace Tests\Unit\LegalCitations;

use App\Services\LegalCitations\StatuteCitationDetector;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Fixture-based proof: statute citation detection works on a real Croatian decision excerpt.
 *
 * Why this matters:
 * - Your downstream reasoning/search expects the system to detect citations in decision texts,
 *   e.g. KZ čl. 110, ZKP čl. 474 etc. If detection regresses, everything built on citations fails.
 */
class DecisionCitationDetectorFixtureTest extends TestCase
{
    public function test_detects_key_statute_citations_in_sample_decision_text(): void
    {
        $text = File::get(base_path('tests/Fixtures/decisions/ikz-8-2023-7.txt'));

        $detector = new StatuteCitationDetector;
        $hits = $detector->detect($text);

        $canon = array_values(array_filter(array_map(
            fn ($h) => $h['canonical'] ?? null,
            $hits
        )));

        // Canonical format is: "KZ:čl.110" or "ZKP:čl.474 st.1" per StatuteCitationDetector::buildCanonical
        // fileciteturn15file4
        $this->assertContains('KZ:čl.110', $canon);
        $this->assertContains('KZ:čl.34', $canon);
        $this->assertContains('ZKP:čl.474 st.1', $canon);
        $this->assertContains('ZKP:čl.468 st.1 t.11', $canon);
    }
}
