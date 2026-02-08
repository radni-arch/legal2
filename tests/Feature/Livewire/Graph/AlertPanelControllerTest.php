<?php

namespace Tests\Feature\Livewire\Graph;

use App\Livewire\Graph\AlertPanelController;
use App\Models\ResearchSession;
use App\Models\User;
use App\Services\Graph\ContradictionRadarService;
use App\Services\Graph\ResearchSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class AlertPanelControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_renders_alerts_for_current_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = ResearchSession::factory()->create([
            'user_id' => $user->id,
            'last_activity_at' => now(),
            'alerts' => [
                [
                    'id' => 'alert-1',
                    'type' => 'direct_contradiction',
                    'severity' => 'critical',
                    'message' => 'Test alert message',
                    'dismissed' => false,
                ],
            ],
        ]);

        $mockSessionService = Mockery::mock(ResearchSessionService::class);
        $mockSessionService->shouldReceive('getCurrentSession')
            ->andReturn($session);

        $this->app->instance(ResearchSessionService::class, $mockSessionService);

        Livewire::test(AlertPanelController::class)
            ->assertSee('Test alert message')
            ->assertSee('CRITICAL');
    }

    /** @test */
    public function it_shows_empty_state_when_no_alerts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = ResearchSession::factory()->create([
            'user_id' => $user->id,
            'last_activity_at' => now(),
            'alerts' => [],
        ]);

        $mockSessionService = Mockery::mock(ResearchSessionService::class);
        $mockSessionService->shouldReceive('getCurrentSession')
            ->andReturn($session);

        $this->app->instance(ResearchSessionService::class, $mockSessionService);

        Livewire::test(AlertPanelController::class)
            ->assertSee('No alerts');
    }

    /** @test */
    public function it_dismisses_alert_on_click(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = ResearchSession::factory()->create([
            'user_id' => $user->id,
            'last_activity_at' => now(),
            'alerts' => [
                ['id' => 'alert-1', 'type' => 'direct_contradiction', 'severity' => 'critical', 'message' => 'Test', 'dismissed' => false],
            ],
        ]);

        $mockSessionService = Mockery::mock(ResearchSessionService::class);
        $mockSessionService->shouldReceive('getCurrentSession')
            ->andReturn($session);

        $mockRadar = Mockery::mock(ContradictionRadarService::class);
        $mockRadar->shouldReceive('dismissAlert')
            ->once()
            ->with($session, 'alert-1');

        $this->app->instance(ResearchSessionService::class, $mockSessionService);
        $this->app->instance(ContradictionRadarService::class, $mockRadar);

        Livewire::test(AlertPanelController::class)
            ->call('dismissAlert', 'alert-1');

        // Mockery expectation verifies dismissAlert was called
        $this->assertTrue(true);
    }

    /** @test */
    public function it_triggers_full_scan_on_button_click(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = ResearchSession::factory()->create([
            'user_id' => $user->id,
            'last_activity_at' => now(),
            'alerts' => [],
        ]);

        $mockSessionService = Mockery::mock(ResearchSessionService::class);
        $mockSessionService->shouldReceive('getCurrentSession')
            ->andReturn($session);

        $mockRadar = Mockery::mock(ContradictionRadarService::class);
        $mockRadar->shouldReceive('scanSession')
            ->once()
            ->with($session);

        $this->app->instance(ResearchSessionService::class, $mockSessionService);
        $this->app->instance(ContradictionRadarService::class, $mockRadar);

        Livewire::test(AlertPanelController::class)
            ->call('scanAllNodes');

        // Mockery expectation verifies scanSession was called
        $this->assertTrue(true);
    }

    /** @test */
    public function it_filters_out_dismissed_alerts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = ResearchSession::factory()->create([
            'user_id' => $user->id,
            'last_activity_at' => now(),
            'alerts' => [
                ['id' => 'alert-1', 'type' => 'direct_contradiction', 'severity' => 'critical', 'message' => 'Active alert', 'dismissed' => false],
                ['id' => 'alert-2', 'type' => 'superseded_law', 'severity' => 'caution', 'message' => 'Dismissed alert', 'dismissed' => true],
            ],
        ]);

        $mockSessionService = Mockery::mock(ResearchSessionService::class);
        $mockSessionService->shouldReceive('getCurrentSession')
            ->andReturn($session);

        $this->app->instance(ResearchSessionService::class, $mockSessionService);

        Livewire::test(AlertPanelController::class)
            ->assertSee('Active alert')
            ->assertDontSee('Dismissed alert');
    }
}
