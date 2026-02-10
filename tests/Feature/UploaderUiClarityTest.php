<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SOT-015: Verify uploader UI shows clear ingest pipeline explanation.
 */
class UploaderUiClarityTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploader_page_shows_pipeline_explanation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/uploader');

        $response->assertStatus(200);
        $response->assertSee('Document Uploader');
        $response->assertSee('What happens after upload');
        $response->assertSee('IngestRun');
        $response->assertSee('OCR extraction');
        $response->assertSee('Metadata extraction');
        $response->assertSee('Case-level analysis');
    }

    public function test_uploader_page_shows_supported_formats(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/uploader');

        $response->assertStatus(200);
        $response->assertSee('PDF, DOCX, images');
        $response->assertSee('5MB per chunk');
    }
}
