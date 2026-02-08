<?php

namespace Tests\Feature\Livewire;

use App\Agents\Contracts\LegalArtilleryAgentContract;
use App\Livewire\LegalArtillery\Dashboard;
use App\Livewire\LegalArtillery\GenerationMonitor;
use App\Livewire\LegalArtillery\NewGeneration;
use App\Livewire\LegalArtillery\RunDetails;
use App\Models\DocumentGenerationRun;
use App\Models\DocumentIteration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class LegalArtilleryDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ── Dashboard Component ───────────────────────────────────────────

    public function test_dashboard_renders(): void
    {
        $this->actingAs($this->user)
            ->get('/legal-artillery')
            ->assertOk()
            ->assertSee('Pravna Artiljerija');
    }

    public function test_dashboard_requires_auth(): void
    {
        $this->get('/legal-artillery')
            ->assertRedirect('/login');
    }

    public function test_dashboard_lists_runs(): void
    {
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $this->user->id,
            'final_score' => 85.5,
            'total_iterations' => 3,
        ]);

        Livewire::actingAs($this->user)
            ->test(Dashboard::class)
            ->assertSee('predsjednik_suda')
            ->assertSee('85.5');
    }

    public function test_dashboard_only_shows_own_runs(): void
    {
        $otherUser = User::factory()->create();
        DocumentGenerationRun::create([
            'document_type' => 'other_user_run',
            'status' => 'completed',
            'user_id' => $otherUser->id,
            'final_score' => 90.0,
            'total_iterations' => 2,
        ]);

        Livewire::actingAs($this->user)
            ->test(Dashboard::class)
            ->assertDontSee('other_user_run');
    }

    public function test_dashboard_filters_by_status(): void
    {
        DocumentGenerationRun::create([
            'document_type' => 'completed_run',
            'status' => 'completed',
            'user_id' => $this->user->id,
            'final_score' => 85.0,
            'total_iterations' => 3,
        ]);
        DocumentGenerationRun::create([
            'document_type' => 'running_run',
            'status' => 'running',
            'user_id' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(Dashboard::class)
            ->set('statusFilter', 'completed')
            ->assertSee('completed_run')
            ->assertDontSee('running_run');
    }

    public function test_dashboard_filters_by_profile(): void
    {
        DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $this->user->id,
            'final_score' => 85.0,
            'total_iterations' => 3,
        ]);
        DocumentGenerationRun::create([
            'document_type' => 'ustavni_sud',
            'status' => 'completed',
            'user_id' => $this->user->id,
            'final_score' => 90.0,
            'total_iterations' => 2,
        ]);

        // Without filter, both runs appear
        $component = Livewire::actingAs($this->user)
            ->test(Dashboard::class)
            ->assertSee('predsjednik_suda')
            ->assertSee('90.0');

        // With filter, only matching run appears (85.0 score for predsjednik_suda)
        $component->set('profileFilter', 'predsjednik_suda')
            ->assertSee('85.0')
            ->assertDontSee('90.0');
    }

    public function test_dashboard_shows_stats(): void
    {
        DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $this->user->id,
            'final_score' => 80.0,
            'total_iterations' => 3,
        ]);

        Livewire::actingAs($this->user)
            ->test(Dashboard::class)
            ->assertSee('80.0');
    }

    // ── NewGeneration Component ───────────────────────────────────────

    public function test_new_generation_route_renders(): void
    {
        $this->actingAs($this->user)
            ->get('/legal-artillery/new')
            ->assertOk()
            ->assertSee('Nova Paljba');
    }

    public function test_new_generation_requires_auth(): void
    {
        $this->get('/legal-artillery/new')
            ->assertRedirect('/login');
    }

    public function test_new_generation_lists_profiles(): void
    {
        Livewire::actingAs($this->user)
            ->test(NewGeneration::class)
            ->assertSee('Zahtjev predsjedniku suda')
            ->assertSee('Ustavna tuzba');
    }

    public function test_can_start_generation(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        Livewire::actingAs($this->user)
            ->test(NewGeneration::class)
            ->set('selectedProfile', 'predsjednik_suda')
            ->set('maxIterations', 3)
            ->call('startGeneration')
            ->assertRedirect();

        // Verify run was created with pending status
        $this->assertDatabaseHas('document_generation_runs', [
            'document_type' => 'predsjednik_suda',
            'status' => 'pending',
            'user_id' => $this->user->id,
        ]);

        // Verify job was dispatched
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\GenerateLegalDocumentJob::class);
    }

    public function test_start_generation_validates_profile(): void
    {
        Livewire::actingAs($this->user)
            ->test(NewGeneration::class)
            ->set('selectedProfile', '')
            ->call('startGeneration')
            ->assertHasErrors(['selectedProfile']);
    }

    public function test_start_generation_validates_max_iterations(): void
    {
        Livewire::actingAs($this->user)
            ->test(NewGeneration::class)
            ->set('selectedProfile', 'predsjednik_suda')
            ->set('maxIterations', 15)
            ->call('startGeneration')
            ->assertHasErrors(['maxIterations']);
    }

    // ── RunDetails Component ──────────────────────────────────────────

    public function test_run_details_route_renders(): void
    {
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $this->user->id,
            'final_score' => 85.0,
            'total_iterations' => 3,
        ]);

        $this->actingAs($this->user)
            ->get("/legal-artillery/run/{$run->id}")
            ->assertOk()
            ->assertSee('predsjednik_suda');
    }

    public function test_run_details_requires_auth(): void
    {
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $this->user->id,
            'final_score' => 85.0,
            'total_iterations' => 3,
        ]);

        $this->get("/legal-artillery/run/{$run->id}")
            ->assertRedirect('/login');
    }

    public function test_run_details_shows_run_info(): void
    {
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $this->user->id,
            'final_score' => 92.5,
            'total_iterations' => 4,
            'stopped_reason' => 'converged',
        ]);

        Livewire::actingAs($this->user)
            ->test(RunDetails::class, ['runId' => $run->id])
            ->assertSee('predsjednik_suda')
            ->assertSee('92.5')
            ->assertSee('converged');
    }

    public function test_run_details_shows_iterations(): void
    {
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $this->user->id,
            'final_score' => 85.0,
            'total_iterations' => 2,
        ]);

        DocumentIteration::create([
            'generation_run_id' => $run->id,
            'iteration_number' => 1,
            'phase' => 'critic',
            'document_version' => 'Version 1 content',
            'weighted_score' => 75.0,
            'created_at' => now(),
        ]);

        DocumentIteration::create([
            'generation_run_id' => $run->id,
            'iteration_number' => 2,
            'phase' => 'critic',
            'document_version' => 'Version 2 content',
            'weighted_score' => 85.0,
            'created_at' => now(),
        ]);

        Livewire::actingAs($this->user)
            ->test(RunDetails::class, ['runId' => $run->id])
            ->assertSee('75')
            ->assertSee('85');
    }

    public function test_run_details_forbids_other_users_run(): void
    {
        $otherUser = User::factory()->create();
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $otherUser->id,
            'final_score' => 85.0,
            'total_iterations' => 3,
        ]);

        $this->actingAs($this->user)
            ->get("/legal-artillery/run/{$run->id}")
            ->assertStatus(403);
    }

    // ── GenerationMonitor Component ───────────────────────────────────

    public function test_generation_monitor_renders(): void
    {
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'running',
            'user_id' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(GenerationMonitor::class, ['runId' => $run->id])
            ->assertStatus(200)
            ->assertSee('predsjednik_suda');
    }

    public function test_generation_monitor_shows_status(): void
    {
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'running',
            'user_id' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(GenerationMonitor::class, ['runId' => $run->id])
            ->assertSee('running');
    }

    public function test_generation_monitor_updates_on_poll(): void
    {
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'running',
            'user_id' => $this->user->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(GenerationMonitor::class, ['runId' => $run->id])
            ->assertSee('running');

        // Update the run status
        $run->update(['status' => 'completed', 'final_score' => 88.0, 'total_iterations' => 3]);

        // Call refresh (simulating poll)
        $component->call('checkStatus')
            ->assertSee('completed');
    }
}
