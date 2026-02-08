<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\Models\DocumentGenerationRun;
use App\Models\User;
use App\Services\HrLegalCitationsDetector;
use App\Services\LegalArtillery\LlmClient;
use App\Services\LegalArtillery\ResponseHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Tests for ResponseHandler citation mapping (Sprint 2B).
 *
 * Verifies:
 * - mapCitations() extracts citations from each paragraph
 * - Returns a map of paragraph_index => citations
 * - Stores citation map in DocumentGenerationRun::model_config under 'paragraph_citations'
 * - Handles empty content and content without citations
 * - Handles multi-paragraph content with mixed citation presence
 */
class ResponseHandlerCitationMapTest extends TestCase
{
    use RefreshDatabase;

    private ResponseHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $llmClient = Mockery::mock(LlmClient::class);
        $this->handler = new ResponseHandler($llmClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // mapCitations() return structure tests
    // =========================================================================

    public function test_map_citations_returns_array(): void
    {
        $content = "Ovo je tekst bez citata.\n\nDrugi paragraf.";

        $result = $this->handler->mapCitations($content);

        $this->assertIsArray($result);
    }

    public function test_map_citations_returns_paragraph_indexed_map(): void
    {
        $content = "Sukladno cl.150 st.1 Prekrsajnog zakona (NN 107/07), podnositelj ima pravo.\n\nDrugi paragraf bez citata.";

        $result = $this->handler->mapCitations($content);

        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey(1, $result);
    }

    public function test_map_citations_detects_statute_citations(): void
    {
        $content = "Temeljem cl.150 st.1 PZ podnositelj zahtijeva uvid u spis.";

        $result = $this->handler->mapCitations($content);

        // Paragraph 0 should have at least one citation detected
        $this->assertNotEmpty($result[0], 'Expected citations in paragraph with statute reference');
    }

    public function test_map_citations_handles_empty_content(): void
    {
        $result = $this->handler->mapCitations('');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_map_citations_handles_single_paragraph(): void
    {
        $content = "Jednostavan paragraf s referencom na ZKP cl.10 st.2 toc.2.";

        $result = $this->handler->mapCitations($content);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey(0, $result);
    }

    public function test_map_citations_multi_paragraph_mixed_citations(): void
    {
        $content = implode("\n\n", [
            "Uvod bez pravnih referenci.", // no citations
            "Prema cl.150 st.1 PZ i cl.18 Ustava RH, prava su povrijedena.", // statutes
            "Takodjer, sukladno ZKP cl.184 st.5, obrana ima pravo na uvid.", // statutes
            "Zakljucno, trazimo postupanje.", // no citations
        ]);

        $result = $this->handler->mapCitations($content);

        $this->assertCount(4, $result);
        // Paragraphs 1 and 2 should have citations
        $this->assertNotEmpty($result[1], 'Paragraph 1 should have citations');
        $this->assertNotEmpty($result[2], 'Paragraph 2 should have citations');
    }

    public function test_map_citations_empty_paragraphs_in_citations_map(): void
    {
        $content = "Paragraf bez citata.";

        $result = $this->handler->mapCitations($content);

        $this->assertArrayHasKey(0, $result);
        // Empty citations are represented as an empty array for that paragraph
        // The test checks whether detecting 0 citations returns an empty array
        $this->assertIsArray($result[0]);
    }

    // =========================================================================
    // Citation detection accuracy tests
    // =========================================================================

    public function test_map_citations_detects_case_numbers(): void
    {
        $content = "Kako je utvrdeno u presudi U-III-3071/2006 Ustavnog suda.";

        $result = $this->handler->mapCitations($content);

        $this->assertNotEmpty($result[0], 'Expected citations for paragraph with case number');
        $hasCases = false;
        foreach ($result[0] as $type => $citations) {
            if ($type === 'cases' && !empty($citations)) {
                $hasCases = true;
            }
        }
        $this->assertTrue($hasCases, 'Expected case number detection');
    }

    public function test_map_citations_detects_nn_references(): void
    {
        $content = "Zakon objavljen u Narodnim novinama (NN 107/07, 39/13).";

        $result = $this->handler->mapCitations($content);

        $this->assertNotEmpty($result[0], 'Expected citations for paragraph with NN reference');
    }

    // =========================================================================
    // Storage in DocumentGenerationRun tests
    // =========================================================================

    public function test_map_citations_can_be_stored_in_generation_run(): void
    {
        $user = User::factory()->create();
        $run = DocumentGenerationRun::factory()->create([
            'status' => 'completed',
            'final_document' => "Temeljem cl.150 PZ.\n\nBez citata.\n\nPrema ZKP cl.10.",
            'user_id' => $user->id,
            'model_config' => ['critic_model' => 'test'],
        ]);

        $citationMap = $this->handler->mapCitations($run->final_document);

        // Store in model_config under 'paragraph_citations'
        $run->update([
            'model_config' => array_merge($run->model_config ?? [], [
                'paragraph_citations' => $citationMap,
            ]),
        ]);

        $freshRun = $run->fresh();
        $this->assertArrayHasKey('paragraph_citations', $freshRun->model_config);
        $this->assertIsArray($freshRun->model_config['paragraph_citations']);
    }

    // =========================================================================
    // Coverage calculation tests
    // =========================================================================

    public function test_citation_coverage_can_be_calculated_from_map(): void
    {
        $content = implode("\n\n", [
            "Sukladno cl.150 PZ, podnositelj zahtijeva.", // has citation
            "Jednostavan tekst bez citata.", // no citation
            "Prema ZKP cl.10 st.2 toc.2, dokazi su nezakoniti.", // has citation
            "Drugi jednostavan tekst.", // no citation
        ]);

        $citationMap = $this->handler->mapCitations($content);

        // Calculate coverage: paragraphs with any citations / total paragraphs
        $totalParagraphs = count($citationMap);
        $paragraphsWithCitations = 0;
        foreach ($citationMap as $citations) {
            $hasAnyCitation = false;
            foreach ($citations as $type => $detected) {
                if (!empty($detected)) {
                    $hasAnyCitation = true;
                    break;
                }
            }
            if ($hasAnyCitation) {
                $paragraphsWithCitations++;
            }
        }

        $coverage = $totalParagraphs > 0 ? $paragraphsWithCitations / $totalParagraphs : 0;

        $this->assertEquals(4, $totalParagraphs);
        $this->assertEquals(2, $paragraphsWithCitations);
        $this->assertEquals(0.5, $coverage);
    }
}
