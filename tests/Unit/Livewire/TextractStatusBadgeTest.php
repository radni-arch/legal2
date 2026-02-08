<?php

namespace Tests\Unit\Livewire;

use App\Http\Livewire\Components\TextractStatusBadge;
use App\Models\TextractJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TextractStatusBadgeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_shows_green_for_completed_ocr_status(): void
    {
        $job = TextractJob::factory()->completed()->create();

        $component = Livewire::test(TextractStatusBadge::class, ['job' => $job]);
        $this->assertEquals('bg-green-500', $component->get('ocrStatusColor'));
    }

    /** @test */
    public function it_shows_red_for_failed_ocr_status(): void
    {
        $job = TextractJob::factory()->failed()->create();

        $component = Livewire::test(TextractStatusBadge::class, ['job' => $job]);
        $this->assertEquals('bg-red-500', $component->get('ocrStatusColor'));
    }

    /** @test */
    public function it_shows_yellow_for_processing_ocr_status(): void
    {
        $job = TextractJob::factory()->create(['status' => 'processing']);

        $component = Livewire::test(TextractStatusBadge::class, ['job' => $job]);
        $this->assertEquals('bg-yellow-500 animate-pulse', $component->get('ocrStatusColor'));
    }

    /** @test */
    public function it_shows_gray_for_queued_ocr_status(): void
    {
        $job = TextractJob::factory()->queued()->create();

        $component = Livewire::test(TextractStatusBadge::class, ['job' => $job]);
        $this->assertEquals('bg-gray-400', $component->get('ocrStatusColor'));
    }

    /** @test */
    public function it_shows_green_for_synced_embedding_status(): void
    {
        $job = TextractJob::factory()->completed()->embeddingsSynced()->create();

        $component = Livewire::test(TextractStatusBadge::class, ['job' => $job]);
        $this->assertEquals('bg-green-500', $component->get('embeddingStatusColor'));
    }

    /** @test */
    public function it_shows_red_for_failed_embedding_status(): void
    {
        $job = TextractJob::factory()->create(['embedding_status' => 'failed']);

        $component = Livewire::test(TextractStatusBadge::class, ['job' => $job]);
        $this->assertEquals('bg-red-500', $component->get('embeddingStatusColor'));
    }

    /** @test */
    public function it_shows_orange_for_blocked_embedding_status(): void
    {
        $job = TextractJob::factory()->create(['embedding_status' => 'blocked']);

        $component = Livewire::test(TextractStatusBadge::class, ['job' => $job]);
        $this->assertEquals('bg-orange-500', $component->get('embeddingStatusColor'));
    }

    /** @test */
    public function it_shows_green_for_synced_graph_status(): void
    {
        $job = TextractJob::factory()->create([
            'graph_sync_status' => 'synced',
            'graph_synced_at' => now(),
        ]);

        $component = Livewire::test(TextractStatusBadge::class, ['job' => $job]);
        $this->assertEquals('bg-green-500', $component->get('graphStatusColor'));
    }

    /** @test */
    public function it_shows_orange_for_blocked_graph_status(): void
    {
        $job = TextractJob::factory()->create(['graph_sync_status' => 'blocked']);

        $component = Livewire::test(TextractStatusBadge::class, ['job' => $job]);
        $this->assertEquals('bg-orange-500', $component->get('graphStatusColor'));
    }

    /** @test */
    public function it_returns_correct_ocr_tooltip_for_failed(): void
    {
        $job = TextractJob::factory()->failed()->create(['error' => 'AWS timeout']);

        $component = Livewire::test(TextractStatusBadge::class, ['job' => $job]);
        $this->assertEquals('Failed: AWS timeout', $component->get('ocrTooltip'));
    }

    /** @test */
    public function it_returns_correct_embedding_tooltip_with_timestamp(): void
    {
        $syncedAt = now();
        $job = TextractJob::factory()->completed()->embeddingsSynced()->create([
            'embedding_synced_at' => $syncedAt,
        ]);

        $component = Livewire::test(TextractStatusBadge::class, ['job' => $job]);
        $this->assertStringContains('Embeddings synced at', $component->get('embeddingTooltip'));
    }

    /** @test */
    public function it_returns_correct_graph_tooltip_for_blocked(): void
    {
        $job = TextractJob::factory()->create(['graph_sync_status' => 'blocked']);

        $component = Livewire::test(TextractStatusBadge::class, ['job' => $job]);
        $this->assertEquals('Blocked: waiting for embeddings', $component->get('graphTooltip'));
    }

    /** @test */
    public function it_renders_all_three_badges(): void
    {
        $job = TextractJob::factory()->completed()->create();

        Livewire::test(TextractStatusBadge::class, ['job' => $job])
            ->assertSee('OCR')
            ->assertSee('Embed')
            ->assertSee('Graph');
    }

    /**
     * Helper for string contains assertion.
     */
    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '$haystack' contains '$needle'"
        );
    }
}
