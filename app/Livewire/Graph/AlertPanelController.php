<?php

namespace App\Livewire\Graph;

use App\Services\Graph\ContradictionRadarService;
use App\Services\Graph\ResearchSessionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Alert Panel Controller for Phase 3: Contradiction Radar.
 *
 * Displays proactive alerts about contradictions, superseded laws,
 * and outdated citations during legal research sessions.
 */
class AlertPanelController extends Component
{
    public array $alerts = [];

    public bool $isOpen = false;

    public function mount(): void
    {
        $this->loadAlerts();
    }

    #[On('alerts-updated')]
    public function refreshAlerts(array $alerts = []): void
    {
        $this->alerts = array_filter($alerts, fn ($a) => ! ($a['dismissed'] ?? false));
    }

    public function loadAlerts(): void
    {
        if (! Auth::check()) {
            $this->alerts = [];

            return;
        }

        $sessionService = app(ResearchSessionService::class);
        $session = $sessionService->getCurrentSession();
        $this->alerts = array_filter(
            $session->alerts ?? [],
            fn ($a) => ! ($a['dismissed'] ?? false)
        );
    }

    public function dismissAlert(string $alertId): void
    {
        if (! Auth::check()) {
            return;
        }

        $sessionService = app(ResearchSessionService::class);
        $radarService = app(ContradictionRadarService::class);

        $session = $sessionService->getCurrentSession();
        $radarService->dismissAlert($session, $alertId);

        $this->loadAlerts();
    }

    public function scanAllNodes(): void
    {
        if (! Auth::check()) {
            return;
        }

        $sessionService = app(ResearchSessionService::class);
        $radarService = app(ContradictionRadarService::class);

        $session = $sessionService->getCurrentSession();
        $radarService->scanSession($session);

        $this->loadAlerts();
    }

    public function toggle(): void
    {
        $this->isOpen = ! $this->isOpen;
    }

    public function render()
    {
        return view('livewire.graph.alert-panel');
    }
}
