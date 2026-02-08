<?php

namespace Tests\Unit\DTOs;

use App\DTOs\DocumentProfile;
use Tests\TestCase;

class DocumentProfileTest extends TestCase
{
    public function test_creates_profile_from_config_array(): void
    {
        $config = [
            'key' => 'predsjednik_suda',
            'name' => 'Zahtjev predsjedniku suda',
            'recipient' => [
                'title' => 'Predsjednica Opccinskog suda u Osijeku',
                'address' => 'Europska avenija 7, 31000 Osijek',
                'email' => null,
            ],
            'legal_basis' => ['PZ cl.150 st.4', 'Ustav cl.18'],
            'tone' => 'formal_assertive',
            'structure' => [
                'heading',
                'identification',
                'facts',
                'legal_arguments',
                'requests',
                'signature',
            ],
            'docx_template' => 'legal-formal',
            'requires_attachments' => false,
        ];

        $profile = DocumentProfile::fromArray($config);

        $this->assertEquals('predsjednik_suda', $profile->key);
        $this->assertEquals('Zahtjev predsjedniku suda', $profile->name);
        $this->assertEquals('formal_assertive', $profile->tone);
        $this->assertCount(2, $profile->legalBasis);
        $this->assertCount(6, $profile->structure);
    }

    public function test_loads_profile_from_config_by_key(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');

        $this->assertInstanceOf(DocumentProfile::class, $profile);
        $this->assertEquals('predsjednik_suda', $profile->key);
    }

    public function test_throws_on_unknown_profile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DocumentProfile::fromConfig('nepostojeci_profil');
    }

    public function test_all_profiles_returns_collection(): void
    {
        $profiles = DocumentProfile::all();

        $this->assertNotEmpty($profiles);
        $this->assertContainsOnlyInstancesOf(DocumentProfile::class, $profiles);
    }
}
