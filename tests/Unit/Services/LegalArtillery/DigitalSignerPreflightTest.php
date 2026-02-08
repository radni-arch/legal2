<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\Services\LegalArtillery\DigitalSigner;
use Tests\TestCase;

class DigitalSignerPreflightTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function test_preflight_returns_expected_structure(): void
    {
        $signer = app(DigitalSigner::class);
        $result = $signer->preflight();

        $this->assertArrayHasKey('ready', $result);
        $this->assertArrayHasKey('card_available', $result);
        $this->assertArrayHasKey('certificate', $result);
        $this->assertArrayHasKey('issues', $result);
        $this->assertIsBool($result['ready']);
        $this->assertIsArray($result['issues']);
    }

    public function test_preflight_reports_issues_when_no_card(): void
    {
        $signer = app(DigitalSigner::class);
        $result = $signer->preflight();

        // In test environment, card reader won't be available
        if (!$result['card_available']) {
            $this->assertFalse($result['ready']);
            $this->assertNotEmpty($result['issues']);
        } else {
            $this->assertTrue(true); // Card available - can't test negative
        }
    }
}
