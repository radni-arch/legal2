<?php

namespace Tests\Feature\Livewire\LegalArtillery;

use App\Livewire\LegalArtillery\NewGeneration;
use App\Models\Evidence;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tests for NewGeneration Livewire component evidence ID validation.
 *
 * Verifies that user-supplied evidence IDs are validated against the database
 * before being passed to the generation pipeline, matching the API path behavior
 * defined in GenerateDocumentRequest.
 */
class NewGenerationEvidenceValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_rejects_nonexistent_evidence_ids(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(NewGeneration::class)
            ->set('selectedProfile', 'prituzba')
            ->set('evidenceIds', 'EV-FAKE-999, EV-FAKE-888')
            ->call('startGeneration')
            ->assertHasErrors(['evidenceIds']);
    }

    public function test_rejects_mix_of_valid_and_invalid_evidence_ids(): void
    {
        $user = User::factory()->create();
        $legalCase = LegalCase::factory()->create(['user_id' => $user->id]);
        $evidence = Evidence::factory()->create(['case_id' => $legalCase->id]);

        Livewire::actingAs($user)
            ->test(NewGeneration::class)
            ->set('selectedProfile', 'prituzba')
            ->set('caseId', $legalCase->id)
            ->set('evidenceIds', $evidence->id . ', NONEXISTENT-ID')
            ->call('startGeneration')
            ->assertHasErrors(['evidenceIds']);
    }

    public function test_accepts_valid_evidence_ids(): void
    {
        $user = User::factory()->create();
        $legalCase = LegalCase::factory()->create(['user_id' => $user->id]);
        $evidence1 = Evidence::factory()->create(['case_id' => $legalCase->id]);
        $evidence2 = Evidence::factory()->create(['case_id' => $legalCase->id]);

        Livewire::actingAs($user)
            ->test(NewGeneration::class)
            ->set('selectedProfile', 'prituzba')
            ->set('caseId', $legalCase->id)
            ->set('evidenceIds', $evidence1->id . ', ' . $evidence2->id)
            ->call('startGeneration')
            ->assertHasNoErrors(['evidenceIds']);
    }

    public function test_accepts_empty_evidence_ids(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(NewGeneration::class)
            ->set('selectedProfile', 'prituzba')
            ->set('evidenceIds', '')
            ->call('startGeneration')
            ->assertHasNoErrors(['evidenceIds']);
    }

    public function test_rejects_evidence_ids_not_belonging_to_selected_case(): void
    {
        $user = User::factory()->create();
        $caseA = LegalCase::factory()->create(['user_id' => $user->id]);
        $caseB = LegalCase::factory()->create(['user_id' => $user->id]);
        $evidenceFromCaseB = Evidence::factory()->create(['case_id' => $caseB->id]);

        Livewire::actingAs($user)
            ->test(NewGeneration::class)
            ->set('selectedProfile', 'prituzba')
            ->set('caseId', $caseA->id)
            ->set('evidenceIds', $evidenceFromCaseB->id)
            ->call('startGeneration')
            ->assertHasErrors(['evidenceIds']);
    }
}
