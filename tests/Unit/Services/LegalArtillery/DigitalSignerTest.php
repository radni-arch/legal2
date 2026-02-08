<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\Services\LegalArtillery\DigitalSigner;
use Tests\TestCase;

class DigitalSignerTest extends TestCase
{
    private DigitalSigner $signer;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure config is loaded for tests
        $this->app['config']->set('digital-signature', [
            'pkcs11' => [
                'module_path' => '/usr/lib/opensc-pkcs11.so',
                'slot' => 0,
                'cert_label' => 'Signature',
                'pin' => null,
            ],
            'pdf' => [
                'signature_field_name' => 'LegalArtillerySignature',
                'signature_reason' => 'Potpisano sustavom Pravna Artiljerija',
                'signature_location' => 'Osijek, Hrvatska',
                'visible_signature' => true,
                'signature_page' => 'last',
                'signature_rect' => [50, 50, 200, 100],
            ],
            'libreoffice' => [
                'binary' => '/usr/bin/soffice',
                'timeout' => 60,
            ],
        ]);

        $this->signer = new DigitalSigner();
    }

    public function test_checks_card_reader_availability(): void
    {
        $available = $this->signer->isCardReaderAvailable();

        // In test environment without physical card reader, this returns a bool
        $this->assertIsBool($available);
    }

    public function test_card_reader_returns_false_without_hardware(): void
    {
        // Without pkcs11-tool or card reader, should return false
        $available = $this->signer->isCardReaderAvailable();

        $this->assertFalse($available);
    }

    public function test_prepares_pdf_for_signing(): void
    {
        $pdfPath = tempnam(sys_get_temp_dir(), 'test_') . '.pdf';
        file_put_contents($pdfPath, '%PDF-1.4 test content');

        try {
            $prepared = $this->signer->preparePdfForSigning($pdfPath);

            $this->assertArrayHasKey('hash', $prepared);
            $this->assertArrayHasKey('hash_algorithm', $prepared);
            $this->assertArrayHasKey('signature_field', $prepared);
            $this->assertArrayHasKey('pdf_path', $prepared);
            $this->assertArrayHasKey('file_size', $prepared);

            $this->assertEquals('SHA-256', $prepared['hash_algorithm']);
            $this->assertEquals('LegalArtillerySignature', $prepared['signature_field']);
            $this->assertEquals($pdfPath, $prepared['pdf_path']);
            $this->assertNotEmpty($prepared['hash']);
            $this->assertGreaterThan(0, $prepared['file_size']);
        } finally {
            @unlink($pdfPath);
        }
    }

    public function test_prepare_pdf_hash_is_deterministic(): void
    {
        $pdfPath = tempnam(sys_get_temp_dir(), 'test_') . '.pdf';
        file_put_contents($pdfPath, '%PDF-1.4 deterministic content');

        try {
            $first = $this->signer->preparePdfForSigning($pdfPath);
            $second = $this->signer->preparePdfForSigning($pdfPath);

            $this->assertEquals($first['hash'], $second['hash']);
        } finally {
            @unlink($pdfPath);
        }
    }

    public function test_prepare_pdf_throws_for_missing_file(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF file not found');

        $this->signer->preparePdfForSigning('/nonexistent/path/file.pdf');
    }

    public function test_converts_docx_to_pdf_method_exists(): void
    {
        $this->assertTrue(method_exists($this->signer, 'convertToPdf'));
    }

    public function test_sign_pdf_requires_pin(): void
    {
        $pdfPath = tempnam(sys_get_temp_dir(), 'test_') . '.pdf';
        file_put_contents($pdfPath, '%PDF-1.4 test content');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('PIN required for signing');

            // Config has pin = null, and we pass no pin
            $this->signer->signPdf($pdfPath);
        } finally {
            @unlink($pdfPath);
        }
    }

    public function test_sign_pdf_requires_card_reader(): void
    {
        $pdfPath = tempnam(sys_get_temp_dir(), 'test_') . '.pdf';
        file_put_contents($pdfPath, '%PDF-1.4 test content');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Card reader or card not available');

            // Provide PIN but no card reader available in test env
            $this->signer->signPdf($pdfPath, '1234');
        } finally {
            @unlink($pdfPath);
        }
    }

    public function test_get_certificate_info_without_card_returns_null(): void
    {
        // Without physical card, getCertificateInfo should return null
        $info = $this->signer->getCertificateInfo();

        $this->assertNull($info);
    }

    public function test_sign_document_method_exists(): void
    {
        $this->assertTrue(method_exists($this->signer, 'signDocument'));
    }

    public function test_config_values_are_loaded(): void
    {
        // Verify the config was loaded properly
        $config = config('digital-signature');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('pkcs11', $config);
        $this->assertArrayHasKey('pdf', $config);
        $this->assertArrayHasKey('libreoffice', $config);

        $this->assertEquals('/usr/lib/opensc-pkcs11.so', $config['pkcs11']['module_path']);
        $this->assertEquals(0, $config['pkcs11']['slot']);
        $this->assertEquals('LegalArtillerySignature', $config['pdf']['signature_field_name']);
    }
}
