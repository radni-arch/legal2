<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UploaderPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function uploader_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get('/uploader');

        $response->assertStatus(200);
        $response->assertSee('Chunked File Uploader');
    }

    /** @test */
    public function uploader_page_contains_pipeline_stage_information(): void
    {
        $response = $this->actingAs($this->user)->get('/uploader');

        $response->assertStatus(200);
        // Must describe the pipeline stages clearly
        $response->assertSee('OCR', false);
        $response->assertSee('Embedding', false);
        $response->assertSee('Analysis', false);
    }

    /** @test */
    public function uploader_page_explains_automatic_processing(): void
    {
        $response = $this->actingAs($this->user)->get('/uploader');

        $response->assertStatus(200);
        // Must communicate that uploads trigger the full ingest pipeline
        $response->assertSee('automatically processed', false);
        $response->assertSee('ingest pipeline', false);
    }

    /** @test */
    public function uploader_page_shows_pipeline_flow_indicator(): void
    {
        $response = $this->actingAs($this->user)->get('/uploader');

        $response->assertStatus(200);
        // The page must have a visual pipeline flow indicator with stage names
        $response->assertSee('Text Extraction', false);
        $response->assertSee('Vector Embedding', false);
        $response->assertSee('Document Analysis', false);
    }

    /** @test */
    public function uploader_page_has_ingest_status_area(): void
    {
        $response = $this->actingAs($this->user)->get('/uploader');

        $response->assertStatus(200);
        // There should be a status area for post-upload ingest tracking
        $response->assertSee('ingest-status-area', false);
    }

    /** @test */
    public function uploader_page_requires_authentication(): void
    {
        $response = $this->get('/uploader');

        $response->assertRedirect('/login');
    }
}
