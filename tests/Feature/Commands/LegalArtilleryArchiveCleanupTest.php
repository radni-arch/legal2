<?php

namespace Tests\Feature\Commands;

use App\Models\DocumentContext;
use App\Models\DocumentGenerationRun;
use App\Models\DocumentIteration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class LegalArtilleryArchiveCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_archive_and_cleanup_respect_retention_settings(): void
    {
        $archivePath = storage_path('app/legal-artillery/test-archive');
        File::deleteDirectory($archivePath);

        Config::set('legal-artillery.retention.archive_days', 10);
        Config::set('legal-artillery.retention.cleanup_failed_days', 7);
        Config::set('legal-artillery.retention.archive_path', $archivePath);

        $oldCompleted = DocumentGenerationRun::factory()->completed()->create([
            'created_at' => now()->subDays(20),
            'final_document' => 'Archived document content',
        ]);
        $recentCompleted = DocumentGenerationRun::factory()->completed()->create([
            'created_at' => now()->subDays(5),
            'final_document' => 'Recent document content',
        ]);

        $docxPath = storage_path('app/legal-artillery/'.Str::uuid().'.docx');
        File::ensureDirectoryExists(dirname($docxPath));
        File::put($docxPath, 'docx');

        $oldFailed = DocumentGenerationRun::factory()->create([
            'status' => 'failed',
            'created_at' => now()->subDays(9),
            'model_config' => ['docx_path' => $docxPath],
        ]);
        $recentFailed = DocumentGenerationRun::factory()->create([
            'status' => 'failed',
            'created_at' => now()->subDays(2),
        ]);

        $iteration = DocumentIteration::factory()->create([
            'generation_run_id' => $oldFailed->id,
        ]);
        $context = DocumentContext::factory()->create([
            'generation_run_id' => $oldFailed->id,
        ]);

        $this->artisan('legal:archive-cleanup')->assertExitCode(0);

        $this->assertNull($oldCompleted->fresh()->final_document);
        $this->assertFileExists($archivePath.'/'.$oldCompleted->id.'.json');
        $this->assertSame('Recent document content', $recentCompleted->fresh()->final_document);

        $this->assertDatabaseMissing('document_generation_runs', ['id' => $oldFailed->id]);
        $this->assertDatabaseHas('document_generation_runs', ['id' => $recentFailed->id]);
        $this->assertDatabaseMissing('document_iterations', ['id' => $iteration->id]);
        $this->assertDatabaseMissing('document_contexts', ['id' => $context->id]);
        $this->assertFileDoesNotExist($docxPath);

        File::deleteDirectory($archivePath);
    }
}
