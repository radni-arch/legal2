<?php

namespace Tests\Unit\Agents;

use App\Agents\LegalArtilleryAgent;
use App\Agents\LegalArtilleryOrchestrator;
use App\DTOs\DocumentProfile;
use App\Models\DocumentGenerationRun;
use App\Models\User;
use App\Services\LegalArtillery\DigitalSigner;
use App\Services\LegalArtillery\DocxRenderer;
use App\Services\LegalArtillery\EKomunikacijaDispatcher;
use App\Services\LegalArtillery\GmailDispatcher;
use App\Services\LegalArtillery\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LegalArtilleryAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_lists_available_profiles(): void
    {
        $agent = app(LegalArtilleryAgent::class);
        $profiles = $agent->availableProfiles();

        $this->assertNotEmpty($profiles);
        $this->assertArrayHasKey('predsjednik_suda', $profiles);
        $this->assertArrayHasKey('ustavni_sud', $profiles);
    }

    public function test_agent_executes_fire_action(): void
    {
        $user = User::factory()->create();

        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->andReturn(
            json_encode(['sections' => [['key' => 'h', 'title' => 'T', 'guidance' => 'G']]]),
            'Content',
            'Polished',
            json_encode([
                'scores' => ['legal_rigor' => 80, 'persuasiveness' => 75, 'clarity' => 85, 'evidence_integration' => 70, 'formatting' => 80],
                'feedback' => ['strengths' => [], 'weaknesses' => [], 'specific_improvements' => []]
            ]),
        );
        $this->app->instance(LlmClient::class, $llm);

        $agent = app(LegalArtilleryAgent::class);
        $result = $agent->fire('predsjednik_suda', $user->id, sendEmail: false, maxIterations: 1);

        // Now returns DocumentGenerationRun with unified pattern
        $this->assertInstanceOf(DocumentGenerationRun::class, $result);
        $this->assertEquals('completed', $result->status);
        $this->assertNotNull($result->final_document);
    }

    public function test_agent_signs_document_and_stores_metadata(): void
    {
        $user = User::factory()->create();

        $docxPath = tempnam(sys_get_temp_dir(), 'legal_doc_') . '.docx';
        $signedPdfPath = tempnam(sys_get_temp_dir(), 'legal_signed_') . '.pdf';
        file_put_contents($docxPath, 'docx content');
        file_put_contents($signedPdfPath, '%PDF-1.4 signed content');

        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->andReturn(
            json_encode(['sections' => [['key' => 'h', 'title' => 'T', 'guidance' => 'G']]]),
            'Content',
            'Polished',
            json_encode([
                'scores' => ['legal_rigor' => 80, 'persuasiveness' => 75, 'clarity' => 85, 'evidence_integration' => 70, 'formatting' => 80],
                'feedback' => ['strengths' => [], 'weaknesses' => [], 'specific_improvements' => []]
            ]),
        );
        $this->app->instance(LlmClient::class, $llm);

        $renderer = Mockery::mock(DocxRenderer::class);
        $renderer->shouldReceive('render')->andReturn($docxPath);
        $this->app->instance(DocxRenderer::class, $renderer);

        $signer = Mockery::mock(DigitalSigner::class);
        $signer->shouldReceive('signDocument')
            ->with($docxPath, '1234')
            ->andReturn([
                'success' => true,
                'signed_pdf' => $signedPdfPath,
                'signature' => 'signature-base64',
                'timestamp' => now()->toIso8601String(),
                'certificate' => ['label' => 'Test Cert'],
            ]);
        $this->app->instance(DigitalSigner::class, $signer);

        try {
            $agent = app(LegalArtilleryAgent::class);
            $result = $agent->fire('predsjednik_suda', $user->id, sendEmail: false, signDigitally: true, signingPin: '1234');

            $this->assertInstanceOf(DocumentGenerationRun::class, $result);
            $this->assertSame($docxPath, $result->model_config['docx_path'] ?? null);
            $this->assertSame($signedPdfPath, $result->model_config['signed_pdf_path'] ?? null);
            $this->assertNotEmpty($result->model_config['digital_signature']['hash'] ?? null);
            $this->assertSame('SHA-256', $result->model_config['digital_signature']['hash_algorithm'] ?? null);
        } finally {
            @unlink($docxPath);
            @unlink($signedPdfPath);
        }
    }

    public function test_dispatch_uses_signed_pdf_when_available(): void
    {
        $user = User::factory()->create();

        $docxPath = tempnam(sys_get_temp_dir(), 'legal_doc_') . '.docx';
        $signedPdfPath = tempnam(sys_get_temp_dir(), 'legal_signed_') . '.pdf';
        file_put_contents($docxPath, 'docx content');
        file_put_contents($signedPdfPath, '%PDF-1.4 signed content');

        $run = DocumentGenerationRun::factory()->completed()->create([
            'document_type' => 'predsjednik_suda',
            'user_id' => $user->id,
            'approved_at' => now(),
            'approved_by' => $user->id,
            'docx_verified' => true,
            'model_config' => [
                'docx_path' => $docxPath,
                'signed_pdf_path' => $signedPdfPath,
            ],
        ]);

        $this->app['config']->set('legal-artillery.gmail.enabled', true);

        $gmail = Mockery::mock(GmailDispatcher::class);
        $gmail->shouldReceive('send')
            ->once()
            ->withArgs(function (DocumentProfile $profile, $context, string $path, ?string $toEmail, bool $asDraft) use ($signedPdfPath) {
                return $profile->key === 'predsjednik_suda'
                    && $path === $signedPdfPath
                    && $toEmail === null
                    && $asDraft === false;
            })
            ->andReturn(['status' => 'sent']);

        $agent = new LegalArtilleryAgent(
            Mockery::mock(LegalArtilleryOrchestrator::class),
            Mockery::mock(DocxRenderer::class),
            null,
            $gmail,
            null,
            null,
        );

        try {
            $agent->dispatchApproved($run->id, sendEmail: true);
        } finally {
            @unlink($docxPath);
            @unlink($signedPdfPath);
        }
    }

    public function test_dispatch_uses_signed_pdf_for_ekomunikacija_when_available(): void
    {
        $user = User::factory()->create();

        $docxPath = tempnam(sys_get_temp_dir(), 'legal_doc_') . '.docx';
        $signedPdfPath = tempnam(sys_get_temp_dir(), 'legal_signed_') . '.pdf';
        file_put_contents($docxPath, 'docx content');
        file_put_contents($signedPdfPath, '%PDF-1.4 signed content');

        $run = DocumentGenerationRun::factory()->completed()->create([
            'document_type' => 'predsjednik_suda',
            'user_id' => $user->id,
            'approved_at' => now(),
            'approved_by' => $user->id,
            'docx_verified' => true,
            'model_config' => [
                'docx_path' => $docxPath,
                'signed_pdf_path' => $signedPdfPath,
            ],
        ]);

        $ekom = Mockery::mock(EKomunikacijaDispatcher::class);
        $ekom->shouldReceive('submit')
            ->once()
            ->withArgs(function (DocumentProfile $profile, $context, string $path) use ($signedPdfPath) {
                return $profile->key === 'predsjednik_suda' && $path === $signedPdfPath;
            })
            ->andReturn(['status' => 'submitted']);

        $agent = new LegalArtilleryAgent(
            Mockery::mock(LegalArtilleryOrchestrator::class),
            Mockery::mock(DocxRenderer::class),
            null,
            null,
            $ekom,
            null,
        );

        try {
            $agent->dispatchApproved($run->id, submitEkom: true);
        } finally {
            @unlink($docxPath);
            @unlink($signedPdfPath);
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
