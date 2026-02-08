<?php

namespace Tests\Feature\Livewire\LegalArtillery;

use App\Livewire\LegalArtillery\NewGeneration;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tests for NewGeneration Livewire component case_id support (Sprint 6).
 *
 * Verifies:
 * - case_id is an optional nullable string property
 * - Component renders without case_id (backward compatible)
 * - Component accepts case_id as a mount parameter
 */
class NewGenerationCaseIdTest extends TestCase
{
    public function test_component_has_case_id_property(): void
    {
        $component = new NewGeneration();

        $this->assertNull($component->caseId);
    }

    public function test_component_renders_without_case_id(): void
    {
        Livewire::test(NewGeneration::class)
            ->assertStatus(200);
    }

    public function test_component_accepts_case_id_as_property(): void
    {
        $testCaseId = 'test-case-123';

        Livewire::test(NewGeneration::class, ['caseId' => $testCaseId])
            ->assertSet('caseId', $testCaseId)
            ->assertStatus(200);
    }
}
