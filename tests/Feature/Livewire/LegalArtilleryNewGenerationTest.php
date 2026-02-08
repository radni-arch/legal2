<?php

namespace Tests\Feature\Livewire;

use App\Jobs\GenerateLegalDocumentJob;
use App\Livewire\LegalArtillery\NewGeneration;
use App\Models\DocumentGenerationRun;
use App\Models\Evidence;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class LegalArtilleryNewGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_generation_passes_case_and_evidence_ids(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $case = LegalCase::factory()->create();
        $evidence = Evidence::factory()->create(['case_id' => $case->id]);

        Livewire::actingAs($user)
            ->test(NewGeneration::class)
            ->set('selectedProfile', 'predsjednik_suda')
            ->set('maxIterations', 3)
            ->set('caseId', (string) $case->id)
            ->set('evidenceIds', (string) $evidence->id)
            ->call('startGeneration')
            ->assertRedirect();

        $run = DocumentGenerationRun::firstOrFail();

        $this->assertSame((string) $case->id, (string) $run->case_id);
        $this->assertSame('predsjednik_suda', $run->document_type);

        Queue::assertPushed(GenerateLegalDocumentJob::class, function (GenerateLegalDocumentJob $job) use ($run, $case, $evidence) {
            return $job->runId === $run->id
                && $job->profileKey === 'predsjednik_suda'
                && $job->maxIterations === 3
                && $job->caseId === (string) $case->id
                && $job->evidenceIds === [(string) $evidence->id];
        });
    }
}
