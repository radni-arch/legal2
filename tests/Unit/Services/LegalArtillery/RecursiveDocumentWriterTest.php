<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Services\LegalArtillery\LlmClient;
use App\Services\LegalArtillery\RecursiveDocumentWriter;
use Mockery;
use Tests\TestCase;

class RecursiveDocumentWriterTest extends TestCase
{
    public function test_generates_outline_from_profile(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();
        $llm = Mockery::mock(LlmClient::class);

        $llm->shouldReceive('generate')
            ->once()
            ->withArgs(fn($system, $prompt, $maxTokens = null) =>
                str_contains($prompt, 'predsjednik') &&
                str_contains(strtolower($prompt), 'outline')
            )
            ->andReturn(json_encode([
                'sections' => [
                    ['key' => 'heading', 'title' => 'Zaglavlje', 'guidance' => 'Predmet, posiljalac, primatelj'],
                    ['key' => 'facts', 'title' => 'Cinjenice', 'guidance' => 'Kronologija pretrage i zahtjeva'],
                    ['key' => 'legal', 'title' => 'Pravni temelj', 'guidance' => 'PZ cl.150 st.4, Ustav cl.18'],
                    ['key' => 'request', 'title' => 'Zahtjev', 'guidance' => 'Formalno rjesenje s poukom'],
                ],
            ]));

        $writer = new RecursiveDocumentWriter($llm);
        $outline = $writer->generateOutline($profile, $context);

        $this->assertCount(4, $outline['sections']);
        $this->assertEquals('heading', $outline['sections'][0]['key']);
    }

    public function test_generates_section_content(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();
        $llm = Mockery::mock(LlmClient::class);

        $section = [
            'key' => 'legal_arguments',
            'title' => 'Pravni argumenti',
            'guidance' => 'PZ cl.150 st.1 i st.4',
        ];

        $llm->shouldReceive('generate')
            ->once()
            ->andReturn('Temeljem clanka 150. stavak 4. Prekrsajnog zakona...');

        $writer = new RecursiveDocumentWriter($llm);
        $content = $writer->generateSection($profile, $context, $section);

        $this->assertStringContainsString('clanka 150', $content);
    }

    public function test_recursive_full_generation(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();
        $llm = Mockery::mock(LlmClient::class);

        // First call: outline (with maxTokens=2048)
        $llm->shouldReceive('generate')
            ->once()
            ->withArgs(fn($s, $p, $maxTokens = null) => str_contains(strtolower($p), 'outline'))
            ->andReturn(json_encode([
                'sections' => [
                    ['key' => 'heading', 'title' => 'Zaglavlje', 'guidance' => 'Header'],
                    ['key' => 'body', 'title' => 'Tijelo', 'guidance' => 'Main body'],
                ],
            ]));

        // Subsequent calls: one per section (2 args each)
        $llm->shouldReceive('generate')
            ->times(2)
            ->andReturn('Generirani sadrzaj sekcije.');

        // Final call: polish/review (2 args)
        $llm->shouldReceive('generate')
            ->once()
            ->withArgs(fn($s, $p, $maxTokens = null) => str_contains(strtolower($p), 'review') || str_contains(strtolower($p), 'poliraj'))
            ->andReturn('Poliran finalni dokument.');

        $writer = new RecursiveDocumentWriter($llm);
        $result = $writer->generate($profile, $context);

        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('sections', $result);
        $this->assertArrayHasKey('profile_key', $result);
        $this->assertNotEmpty($result['content']);
    }

    public function test_handles_malformed_json_outline(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();
        $llm = Mockery::mock(LlmClient::class);

        $llm->shouldReceive('generate')
            ->once()
            ->andReturn('This is not valid JSON');

        $writer = new RecursiveDocumentWriter($llm);
        $outline = $writer->generateOutline($profile, $context);

        // Should return empty sections array, not crash
        $this->assertArrayHasKey('sections', $outline);
        $this->assertEmpty($outline['sections']);
    }

    public function test_handles_json_in_markdown_code_block(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();
        $llm = Mockery::mock(LlmClient::class);

        $llm->shouldReceive('generate')
            ->once()
            ->andReturn("```json\n{\"sections\": [{\"key\": \"test\", \"title\": \"Test\", \"guidance\": \"Test guidance\"}]}\n```");

        $writer = new RecursiveDocumentWriter($llm);
        $outline = $writer->generateOutline($profile, $context);

        $this->assertCount(1, $outline['sections']);
        $this->assertEquals('test', $outline['sections'][0]['key']);
    }

    public function test_handles_empty_additional_context(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();
        $llm = Mockery::mock(LlmClient::class);

        $llm->shouldReceive('generate')
            ->andReturn(json_encode(['sections' => []]));

        $writer = new RecursiveDocumentWriter($llm);
        $outline = $writer->generateOutline($profile, $context, []);

        $this->assertArrayHasKey('sections', $outline);
    }

    public function test_logs_warning_for_malformed_json(): void
    {
        $profile = DocumentProfile::fromConfig('predsjednik_suda');
        $context = CaseContext::fromConfig();
        $llm = Mockery::mock(LlmClient::class);

        $llm->shouldReceive('generate')
            ->once()
            ->andReturn('This is definitely not valid JSON {{{');

        \Illuminate\Support\Facades\Log::shouldReceive('info')
            ->zeroOrMoreTimes();

        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Failed to parse outline JSON') &&
                       isset($context['error']) &&
                       isset($context['response_preview']);
            });

        $writer = new RecursiveDocumentWriter($llm);
        $outline = $writer->generateOutline($profile, $context);

        $this->assertArrayHasKey('sections', $outline);
        $this->assertEmpty($outline['sections']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
