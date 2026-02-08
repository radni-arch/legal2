<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\Services\LegalArtillery\PiiRedactor;
use Tests\TestCase;

class PiiRedactorTest extends TestCase
{
    private PiiRedactor $redactor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->redactor = new PiiRedactor();
    }

    public function test_redacts_oib(): void
    {
        $text = 'OIB: 12345678901, pozivni broj';
        $redacted = $this->redactor->redact($text);
        $this->assertStringContainsString('[OIB_REDACTED]', $redacted);
        $this->assertStringNotContainsString('12345678901', $redacted);
    }

    public function test_redacts_email(): void
    {
        $text = 'Kontakt: test@example.com za info';
        $redacted = $this->redactor->redact($text);
        $this->assertStringContainsString('[EMAIL_REDACTED]', $redacted);
        $this->assertStringNotContainsString('test@example.com', $redacted);
    }

    public function test_redacts_phone(): void
    {
        $text = 'Telefon: +385 91 123 4567';
        $redacted = $this->redactor->redact($text);
        $this->assertStringContainsString('[PHONE_REDACTED]', $redacted);
    }

    public function test_redacts_iban(): void
    {
        $text = 'IBAN: HR1234567890123456789';
        $redacted = $this->redactor->redact($text);
        $this->assertStringContainsString('[IBAN_REDACTED]', $redacted);
    }

    public function test_preserves_non_pii_text(): void
    {
        $text = 'Prema clanku 150. stavku 4. ZKP-a, pretraga stana...';
        $redacted = $this->redactor->redact($text);
        $this->assertEquals($text, $redacted);
    }

    public function test_redacts_array_recursively(): void
    {
        $data = [
            'name' => 'Test',
            'email' => 'test@example.com',
            'nested' => [
                'oib' => 'OIB: 12345678901',
            ],
        ];

        $redacted = $this->redactor->redactArray($data);

        $this->assertEquals('Test', $redacted['name']);
        $this->assertStringContainsString('[EMAIL_REDACTED]', $redacted['email']);
        $this->assertStringContainsString('[OIB_REDACTED]', $redacted['nested']['oib']);
    }

    public function test_contains_pii_detects_email(): void
    {
        $this->assertTrue($this->redactor->containsPii('Contact: user@domain.com'));
        $this->assertFalse($this->redactor->containsPii('No PII here'));
    }

    public function test_count_pii_counts_instances(): void
    {
        $text = 'OIB: 12345678901, email: a@b.com, tel: +385 91 123 4567';
        $count = $this->redactor->countPii($text);
        $this->assertGreaterThanOrEqual(3, $count);
    }
}
