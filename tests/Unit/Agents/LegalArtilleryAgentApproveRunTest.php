<?php

namespace Tests\Unit\Agents;

use App\Agents\LegalArtilleryAgent;
use App\Agents\LegalArtilleryOrchestrator;
use App\Models\DocumentGenerationRun;
use App\Models\User;
use App\Services\LegalArtillery\DocxRenderer;
use App\Services\LegalArtillery\ResponseHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LegalArtilleryAgentApproveRunTest extends TestCase
{
    use RefreshDatabase;

    private string $docxPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->docxPath = tempnam(sys_get_temp_dir(), 'legal_doc_') . '.docx';
        file_put_contents($this->docxPath, 'docx content');
    }

    protected function tearDown(): void
    {
        @unlink($this->docxPath);
        Mockery::close();
        parent::tearDown();
    }

    public function test_approve_succeeds_with_stored_checks_when_config_unavailable(): void
    {
        $user = User::factory()->create();

        // Document with citations so citation mapping passes
        $document = "Legal argument citing Art. 8 ECHR and Zakon o kaznenom postupku čl. 245.\n\n"
            . "Further analysis under Ustav RH čl. 35. [CITATION_NOT_REQUIRED]";

        $run = DocumentGenerationRun::factory()->completed()->create([
            'document_type' => 'predsjednik_suda',
            'user_id' => $user->id,
            'final_document' => $document,
            'docx_verified' => true,
            'model_config' => [
                'docx_path' => $this->docxPath,
                // Stored passing quality gate result
                'quality_score' => 85.0,
                'blockers' => [],
                // Stored passing completeness check
                'completeness_check' => [
                    'complete' => true,
                    'missing_sections' => [],
                ],
            ],
        ]);

        // Nuke the case_context config to simulate approval-only environment
        $this->app['config']->set('legal-artillery.case_context', null);

        // Mock ResponseHandler for citation mapping (doesn't need config)
        $responseHandler = Mockery::mock(ResponseHandler::class);
        $responseHandler->shouldReceive('mapCitations')
            ->andReturn([
                0 => ['echr' => ['Art. 8 ECHR'], 'zkp' => ['čl. 245']],
                1 => ['ustav' => ['čl. 35']],
            ]);
        $this->app->instance(ResponseHandler::class, $responseHandler);

        $agent = new LegalArtilleryAgent(
            Mockery::mock(LegalArtilleryOrchestrator::class),
            Mockery::mock(DocxRenderer::class),
            null,  // no validator
            null,
            null,
            null,
        );

        // Should NOT throw despite missing config — stored checks are sufficient
        $result = $agent->approveRun($run->id, $user->id, 'Approved with stored checks');

        $this->assertNotNull($result->approved_at);
        $this->assertEquals($user->id, $result->approved_by);
    }

    public function test_approve_throws_clear_error_when_config_unavailable_and_no_stored_checks(): void
    {
        $user = User::factory()->create();

        $run = DocumentGenerationRun::factory()->completed()->create([
            'document_type' => 'predsjednik_suda',
            'user_id' => $user->id,
            'docx_verified' => true,
            'model_config' => [
                'docx_path' => $this->docxPath,
                // No stored quality or completeness checks
            ],
        ]);

        // Nuke the case_context config
        $this->app['config']->set('legal-artillery.case_context', null);

        $agent = new LegalArtilleryAgent(
            Mockery::mock(LegalArtilleryOrchestrator::class),
            Mockery::mock(DocxRenderer::class),
            null,
            null,
            null,
            null,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('case context configuration');

        $agent->approveRun($run->id, $user->id);
    }
}
