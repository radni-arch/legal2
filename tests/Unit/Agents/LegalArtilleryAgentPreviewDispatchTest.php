<?php

namespace Tests\Unit\Agents;

use App\Agents\LegalArtilleryAgent;
use App\Agents\LegalArtilleryOrchestrator;
use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Models\DocumentGenerationRun;
use App\Models\User;
use App\Services\LegalArtillery\DocxRenderer;
use App\Services\LegalArtillery\GmailDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LegalArtilleryAgentPreviewDispatchTest extends TestCase
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

    public function test_preview_forces_draft_when_no_recipient_available(): void
    {
        $user = User::factory()->create();

        $run = DocumentGenerationRun::factory()->completed()->create([
            'document_type' => 'predsjednik_suda',
            'user_id' => $user->id,
            'approved_at' => now(),
            'approved_by' => $user->id,
            'docx_verified' => true,
            'model_config' => [
                'docx_path' => $this->docxPath,
            ],
        ]);

        $this->app['config']->set('legal-artillery.gmail.enabled', true);

        $gmail = Mockery::mock(GmailDispatcher::class);
        $gmail->shouldReceive('preview')
            ->once()
            ->andReturn([
                'to' => '',  // No recipient resolved
                'subject' => 'Test Subject',
                'body' => 'Test body',
                'attachment' => basename($this->docxPath),
                'attachment_path' => $this->docxPath,
                'attachment_exists' => true,
                'from' => 'sender@example.com',
                'cc' => null,
            ]);

        $agent = new LegalArtilleryAgent(
            Mockery::mock(LegalArtilleryOrchestrator::class),
            Mockery::mock(DocxRenderer::class),
            null,
            $gmail,
            null,
            null,
        );

        // Caller passes asDraft: false, but no recipient is available
        $preview = $agent->previewDispatch(
            $run->id,
            sendEmail: true,
            asDraft: false,
            toEmail: null,
        );

        // Preview should force as_draft to true since no recipient is available,
        // mirroring what GmailDispatcher::send() does
        $this->assertTrue(
            $preview['email']['as_draft'],
            'Preview should force as_draft=true when no recipient is available'
        );
    }

    public function test_preview_respects_caller_draft_flag_when_recipient_present(): void
    {
        $user = User::factory()->create();

        $run = DocumentGenerationRun::factory()->completed()->create([
            'document_type' => 'predsjednik_suda',
            'user_id' => $user->id,
            'approved_at' => now(),
            'approved_by' => $user->id,
            'docx_verified' => true,
            'model_config' => [
                'docx_path' => $this->docxPath,
            ],
        ]);

        $this->app['config']->set('legal-artillery.gmail.enabled', true);

        $gmail = Mockery::mock(GmailDispatcher::class);
        $gmail->shouldReceive('preview')
            ->once()
            ->andReturn([
                'to' => 'recipient@court.hr',
                'subject' => 'Test Subject',
                'body' => 'Test body',
                'attachment' => basename($this->docxPath),
                'attachment_path' => $this->docxPath,
                'attachment_exists' => true,
                'from' => 'sender@example.com',
                'cc' => null,
            ]);

        $agent = new LegalArtilleryAgent(
            Mockery::mock(LegalArtilleryOrchestrator::class),
            Mockery::mock(DocxRenderer::class),
            null,
            $gmail,
            null,
            null,
        );

        // Caller passes asDraft: false and there IS a recipient
        $preview = $agent->previewDispatch(
            $run->id,
            sendEmail: true,
            asDraft: false,
            toEmail: 'recipient@court.hr',
        );

        // Preview should respect caller's flag since a recipient is present
        $this->assertFalse(
            $preview['email']['as_draft'],
            'Preview should respect asDraft=false when recipient is available'
        );
    }

    public function test_preview_shows_recipient_missing_warning_when_forced_to_draft(): void
    {
        $user = User::factory()->create();

        $run = DocumentGenerationRun::factory()->completed()->create([
            'document_type' => 'predsjednik_suda',
            'user_id' => $user->id,
            'approved_at' => now(),
            'approved_by' => $user->id,
            'docx_verified' => true,
            'model_config' => [
                'docx_path' => $this->docxPath,
            ],
        ]);

        $this->app['config']->set('legal-artillery.gmail.enabled', true);

        $gmail = Mockery::mock(GmailDispatcher::class);
        $gmail->shouldReceive('preview')
            ->once()
            ->andReturn([
                'to' => '',
                'subject' => 'Test Subject',
                'body' => 'Test body',
                'attachment' => basename($this->docxPath),
                'attachment_path' => $this->docxPath,
                'attachment_exists' => true,
                'from' => 'sender@example.com',
                'cc' => null,
            ]);

        $agent = new LegalArtilleryAgent(
            Mockery::mock(LegalArtilleryOrchestrator::class),
            Mockery::mock(DocxRenderer::class),
            null,
            $gmail,
            null,
            null,
        );

        $preview = $agent->previewDispatch(
            $run->id,
            sendEmail: true,
            asDraft: false,
            toEmail: null,
        );

        // Should include a warning that recipient is missing
        $this->assertArrayHasKey('recipient_missing', $preview['email']);
        $this->assertTrue($preview['email']['recipient_missing']);
    }
}
