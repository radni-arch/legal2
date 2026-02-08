<?php

namespace Tests\Unit\Services\Ocr;

use App\Services\Ocr\OcrmypdfService;
use Tests\TestCase;

class OcrmypdfServiceTest extends TestCase
{
    private OcrmypdfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OcrmypdfService();
    }

    /** @test */
    public function it_builds_correct_command_with_defaults(): void
    {
        $cmd = $this->service->buildCommand('/tmp/input.pdf', '/tmp/output.pdf');

        $this->assertContains('ocrmypdf', $cmd);
        $this->assertContains('--language', $cmd);
        $this->assertContains('hrv+eng', $cmd);
        $this->assertContains('--deskew', $cmd);
        $this->assertContains('/tmp/input.pdf', $cmd);
        $this->assertContains('/tmp/output.pdf', $cmd);
    }

    /** @test */
    public function it_builds_command_with_sidecar_text_extraction(): void
    {
        $cmd = $this->service->buildCommand(
            '/tmp/input.pdf',
            '/tmp/output.pdf',
            ['sidecar' => '/tmp/output.txt']
        );

        $this->assertContains('--sidecar', $cmd);
        $this->assertContains('/tmp/output.txt', $cmd);
    }

    /** @test */
    public function it_builds_command_with_force_ocr(): void
    {
        $cmd = $this->service->buildCommand(
            '/tmp/input.pdf',
            '/tmp/output.pdf',
            ['force_ocr' => true]
        );

        $this->assertContains('--force-ocr', $cmd);
    }

    /** @test */
    public function it_builds_command_with_skip_text(): void
    {
        $cmd = $this->service->buildCommand(
            '/tmp/input.pdf',
            '/tmp/output.pdf',
            ['skip_text' => true]
        );

        $this->assertContains('--skip-text', $cmd);
    }

    /** @test */
    public function it_reports_availability_correctly(): void
    {
        $available = $this->service->isAvailable();
        $this->assertIsBool($available);
    }

    /** @test */
    public function it_returns_error_result_for_missing_file(): void
    {
        $result = $this->service->process('/nonexistent/file.pdf');

        $this->assertEquals('error', $result['status']);
        $this->assertNotEmpty($result['error']);
        $this->assertEquals('ocrmypdf', $result['engine']);
    }

    /** @test */
    public function it_includes_clean_and_denoise_options(): void
    {
        $cmd = $this->service->buildCommand(
            '/tmp/input.pdf',
            '/tmp/output.pdf',
            ['clean' => true]
        );

        $this->assertContains('--clean', $cmd);
    }

    /** @test */
    public function it_respects_max_pages_config(): void
    {
        config(['ocr.ocrmypdf.max_pages' => 50]);
        $service = new OcrmypdfService();
        $cmd = $service->buildCommand('/tmp/input.pdf', '/tmp/output.pdf');

        $this->assertContains('--pages', $cmd);
        $this->assertContains('1-50', $cmd);
    }
}
