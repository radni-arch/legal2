<?php

namespace Tests\Feature\Api;

use App\Agents\LegalArtilleryAgent;
use App\Agents\LegalArtilleryOrchestrator;
use App\Models\DocumentGenerationRun;
use App\Models\User;
use App\Services\LegalArtillery\DocxRenderer;
use App\Services\LegalArtillery\ResponseHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Tests for document generation grounding enforcement (Sprint 2C).
 *
 * Verifies:
 * - approveRun() blocks approval when citation coverage is below threshold
 * - approveRun() succeeds when citation coverage meets threshold
 * - Citation map is stored in model_config after approval check
 * - Config threshold is respected
 */
class DocumentGenerationGroundingTest extends TestCase
{
    use RefreshDatabase;

    private LegalArtilleryAgent $agent;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $orchestrator = Mockery::mock(LegalArtilleryOrchestrator::class);
        $renderer = Mockery::mock(DocxRenderer::class);

        $this->agent = new LegalArtilleryAgent(
            orchestrator: $orchestrator,
            renderer: $renderer,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // Grounding enforcement tests
    // =========================================================================

    public function test_approve_run_blocks_when_citation_coverage_below_threshold(): void
    {
        // Document with mostly ungrounded paragraphs (1 out of 4 = 25% < 50% threshold)
        $content = implode("\n\n", [
            "Uvodni paragraf bez pravnih referenci.",
            "Drugi paragraf takodjer bez referenci.",
            "Treci paragraf nema citate.",
            "Jedini paragraf s citatom: cl.150 st.1 PZ.",
        ]);

        $run = DocumentGenerationRun::factory()->create([
            'status' => 'completed',
            'final_document' => $content,
            'user_id' => $this->user->id,
            'model_config' => ['docx_path' => '/tmp/fake.docx'],
        ]);

        // Set threshold to 50%
        config(['legal-artillery.grounding.min_citation_coverage' => 0.5]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('citation coverage');

        $this->agent->approveRun($run->id, $this->user->id);
    }

    public function test_approve_run_succeeds_when_citation_coverage_meets_threshold(): void
    {
        // Document with good coverage (3 out of 4 = 75% > 50% threshold)
        $content = implode("\n\n", [
            "Sukladno cl.150 st.1 PZ, podnositelj zahtijeva uvid.",
            "Prema ZKP cl.10 st.2, dokazi su nezakoniti.",
            "Takodjer, cl.18 Ustava RH jamci pravo na zalbu.",
            "Zakljucno, trazimo postupanje.",
        ]);

        $run = DocumentGenerationRun::factory()->create([
            'status' => 'completed',
            'final_document' => $content,
            'user_id' => $this->user->id,
            'model_config' => ['docx_path' => '/tmp/fake.docx'],
        ]);

        config(['legal-artillery.grounding.min_citation_coverage' => 0.5]);

        // Should not throw - coverage is above threshold
        $result = $this->agent->approveRun($run->id, $this->user->id);

        $this->assertInstanceOf(DocumentGenerationRun::class, $result);
    }

    public function test_approve_run_stores_paragraph_citations_in_model_config(): void
    {
        // Document with good coverage
        $content = implode("\n\n", [
            "Sukladno cl.150 st.1 PZ, podnositelj zahtijeva.",
            "Prema ZKP cl.10 st.2 toc.2, dokazi su nezakoniti.",
        ]);

        $run = DocumentGenerationRun::factory()->create([
            'status' => 'completed',
            'final_document' => $content,
            'user_id' => $this->user->id,
            'model_config' => ['docx_path' => '/tmp/fake.docx'],
        ]);

        config(['legal-artillery.grounding.min_citation_coverage' => 0.5]);

        $this->agent->approveRun($run->id, $this->user->id);

        $freshRun = $run->fresh();
        $this->assertArrayHasKey('paragraph_citations', $freshRun->model_config);
    }

    public function test_approve_run_respects_custom_threshold(): void
    {
        // Document with 50% coverage
        $content = implode("\n\n", [
            "Sukladno cl.150 st.1 PZ, podnositelj zahtijeva uvid.",
            "Bez citata paragraf.",
        ]);

        $run = DocumentGenerationRun::factory()->create([
            'status' => 'completed',
            'final_document' => $content,
            'user_id' => $this->user->id,
            'model_config' => ['docx_path' => '/tmp/fake.docx'],
        ]);

        // Set threshold higher than coverage (80% > 50%)
        config(['legal-artillery.grounding.min_citation_coverage' => 0.8]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('citation coverage');

        $this->agent->approveRun($run->id, $this->user->id);
    }

    public function test_approve_run_still_checks_status_before_grounding(): void
    {
        $run = DocumentGenerationRun::factory()->create([
            'status' => 'running', // Not completed
            'final_document' => null,
            'user_id' => $this->user->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot approve run with status');

        $this->agent->approveRun($run->id, $this->user->id);
    }

    public function test_approve_run_still_checks_final_document_before_grounding(): void
    {
        $run = DocumentGenerationRun::factory()->create([
            'status' => 'completed',
            'final_document' => null,
            'user_id' => $this->user->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot approve run without final document');

        $this->agent->approveRun($run->id, $this->user->id);
    }

    public function test_approve_run_with_zero_threshold_always_passes(): void
    {
        $content = "Bez ikakvih pravnih citata.";

        $run = DocumentGenerationRun::factory()->create([
            'status' => 'completed',
            'final_document' => $content,
            'user_id' => $this->user->id,
            'model_config' => ['docx_path' => '/tmp/fake.docx'],
        ]);

        // Threshold 0 means no enforcement
        config(['legal-artillery.grounding.min_citation_coverage' => 0]);

        $result = $this->agent->approveRun($run->id, $this->user->id);

        $this->assertInstanceOf(DocumentGenerationRun::class, $result);
    }
}
