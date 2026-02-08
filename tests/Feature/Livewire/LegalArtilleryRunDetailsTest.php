<?php

namespace Tests\Feature\Livewire;

use App\Agents\Contracts\LegalArtilleryAgentContract;
use App\Livewire\LegalArtillery\RunDetails;
use App\Models\DocumentGenerationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class LegalArtilleryRunDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_approval_section_for_completed_run(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'final_document' => 'Test content',
            'user_id' => $user->id,
        ]);

        Livewire::test(RunDetails::class, ['runId' => $run->id])
            ->assertSee('Odobrenje i slanje')
            ->assertSee('Ceka odobrenje')
            ->assertSee('Odobri dokument');
    }

    public function test_hides_approval_section_for_non_completed_run(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'failed',
            'user_id' => $user->id,
        ]);

        Livewire::test(RunDetails::class, ['runId' => $run->id])
            ->assertDontSee('Odobrenje i slanje');
    }

    public function test_shows_approved_badge_when_approved(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'final_document' => 'Test',
            'user_id' => $user->id,
            'approved_at' => now(),
            'approved_by' => $user->id,
        ]);

        Livewire::test(RunDetails::class, ['runId' => $run->id])
            ->assertSee('Odobreno');
    }

    public function test_toggle_approval_form(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'final_document' => 'Test',
            'user_id' => $user->id,
        ]);

        Livewire::test(RunDetails::class, ['runId' => $run->id])
            ->assertDontSee('Potvrdi odobrenje')
            ->call('toggleApprovalForm')
            ->assertSee('Potvrdi odobrenje');
    }

    public function test_approve_run_calls_agent(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'final_document' => 'Test content',
            'user_id' => $user->id,
        ]);

        $mock = Mockery::mock(LegalArtilleryAgentContract::class);
        $mock->shouldReceive('approveRun')
            ->once()
            ->with($run->id, $user->id, 'Test notes')
            ->andReturn($run);
        $this->app->instance(LegalArtilleryAgentContract::class, $mock);

        Livewire::test(RunDetails::class, ['runId' => $run->id])
            ->set('approvalNotes', 'Test notes')
            ->call('approveRun')
            ->assertHasNoErrors();
    }

    public function test_dispatch_button_disabled_without_approval(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'final_document' => 'Test',
            'user_id' => $user->id,
        ]);

        // Should show "Odobri dokument" not "Posalji dokument"
        Livewire::test(RunDetails::class, ['runId' => $run->id])
            ->assertSee('Odobri dokument')
            ->assertDontSee('dispatch');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
