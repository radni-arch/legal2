<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Services\LegalArtillery\DocxRenderer;
use Tests\TestCase;

class DocxRendererTest extends TestCase
{
    public function test_renders_docx_from_generation_result(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();

        $generationResult = [
            'profile_key' => 'predsjednik_suda',
            'profile_name' => 'Zahtjev predsjedniku suda za uvid u spis',
            'content' => "ZAHTJEV ZA UVID U SPIS\n\nTemeljem clanka 150...",
            'sections' => [
                ['key' => 'heading', 'title' => 'Zaglavlje', 'content' => 'Zahtjev...'],
                ['key' => 'body', 'title' => 'Tijelo', 'content' => 'Temeljem...'],
            ],
            'generated_at' => now()->toIso8601String(),
        ];

        $renderer = new DocxRenderer();
        $path = $renderer->render($profile, $context, $generationResult);

        $this->assertFileExists($path);
        $this->assertStringEndsWith('.docx', $path);

        // Cleanup
        unlink($path);
    }
}
