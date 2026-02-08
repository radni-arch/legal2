<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Services\LegalArtillery\GmailDispatcher;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Mockery;
use Tests\TestCase;

class GmailDispatcherTest extends TestCase
{
    public function test_builds_mime_message_with_attachment(): void
    {
        $dispatcher = new GmailDispatcher();

        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();

        $to = 'test@example.com';
        $subject = 'Test Subject';
        $body = 'Test body text';
        $attachmentPath = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($attachmentPath, 'dummy docx content');

        $mime = $dispatcher->buildMimeMessage($to, $subject, $body, $attachmentPath);

        $this->assertStringContainsString('To: test@example.com', $mime);
        $this->assertStringContainsString('Subject:', $mime);
        $this->assertStringContainsString('Content-Disposition: attachment', $mime);

        unlink($attachmentPath);
    }

    public function test_resolves_email_subject_from_profile(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();

        $dispatcher = new GmailDispatcher();
        $subject = $dispatcher->resolveSubject($profile, $context);

        $this->assertStringContainsString('Pp Prz-74/2025', $subject);
    }

    public function test_sanitizes_header_values(): void
    {
        $dispatcher = new GmailDispatcher();

        $mime = $dispatcher->buildMimeMessage(
            "test@example.com\r\nBcc: attacker@evil.com",
            "Subject\r\nX-Injected: header",
            'Body',
            null
        );

        // CRLF injection should be blocked - no separate Bcc header line should exist
        // The \r\n is stripped, so the malicious text is concatenated but not a new header
        $this->assertDoesNotMatchRegularExpression('/\r\nBcc:/i', $mime);
        $this->assertDoesNotMatchRegularExpression('/\r\nX-Injected:/i', $mime);

        // Verify the To header exists but malicious newlines are stripped
        $this->assertStringContainsString('To: test@example.com', $mime);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
