<?php

namespace Tests\Feature\Commands\LegalArtillery;

use App\Models\DocumentGenerationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveCleanupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_delete(): void
    {
        $user = User::factory()->create();
        DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'failed',
            'user_id' => $user->id,
            'created_at' => now()->subDays(60),
        ]);

        $this->artisan('legal:archive-cleanup', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertDatabaseCount('document_generation_runs', 1);
    }

    public function test_command_runs_successfully(): void
    {
        $this->artisan('legal:archive-cleanup', ['--dry-run' => true])
            ->assertExitCode(0);
    }
}
