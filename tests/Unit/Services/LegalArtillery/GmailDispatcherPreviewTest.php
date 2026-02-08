<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Services\LegalArtillery\GmailDispatcher;
use Tests\TestCase;

class GmailDispatcherPreviewTest extends TestCase
{
    public function test_preview_returns_payload_without_sending(): void
    {
        $dispatcher = new GmailDispatcher();
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'dummy');

        $preview = $dispatcher->preview($profile, $context, $tmpFile);

        $this->assertArrayHasKey('to', $preview);
        $this->assertArrayHasKey('subject', $preview);
        $this->assertArrayHasKey('body', $preview);
        $this->assertArrayHasKey('attachment', $preview);
        $this->assertArrayHasKey('attachment_exists', $preview);
        $this->assertTrue($preview['attachment_exists']);

        unlink($tmpFile);
    }

    public function test_preview_detects_missing_attachment(): void
    {
        $dispatcher = new GmailDispatcher();
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();

        $preview = $dispatcher->preview($profile, $context, '/nonexistent/file.docx');

        $this->assertFalse($preview['attachment_exists']);
    }
}
