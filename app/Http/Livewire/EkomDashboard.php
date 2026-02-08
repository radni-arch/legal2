<?php

namespace App\Http\Livewire;

use App\Models\EkomOtpravak;
use App\Models\EkomPodnesak;
use App\Models\EkomPredmet;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

/**
 * E-Komunikacije Dashboard Component
 *
 * Main dashboard for the E-Komunikacije interface showing:
 * - Statistics for predmeti (cases), podnesci (submissions), and otpravci (dispatches)
 * - Recent activity feed
 * - Quick navigation and action buttons
 */
class EkomDashboard extends Component
{
    /**
     * Dashboard statistics
     */
    public array $stats = [
        'predmeti_count' => 0,
        'podnesci_count' => 0,
        'otpravci_count' => 0,
        'pending_otpravci' => 0,
        'draft_podnesci' => 0,
        'last_sync' => null,
    ];

    /**
     * Recent activity items
     */
    public array $recentActivity = [];

    /**
     * Action status for user feedback
     */
    public ?string $actionStatus = null;

    /**
     * Action message for user feedback
     */
    public ?string $actionMessage = null;

    /**
     * Component initialization
     */
    public function mount(): void
    {
        $this->loadStats();
        $this->loadRecentActivity();
    }

    /**
     * Load dashboard statistics from database with caching
     */
    public function loadStats(): void
    {
        $this->stats = Cache::remember('ekom_dashboard_stats', 60, function () {
            return [
                'predmeti_count' => EkomPredmet::count(),
                'podnesci_count' => EkomPodnesak::count(),
                'otpravci_count' => EkomOtpravak::count(),
                'pending_otpravci' => EkomOtpravak::whereNull('vrijeme_potvrde_primitka')->count(),
                'draft_podnesci' => EkomPodnesak::where('status', 'kreiran')->count(),
                'last_sync' => EkomPredmet::max('last_synced_at'),
            ];
        });
    }

    /**
     * Load recent activity from all entity types
     */
    public function loadRecentActivity(): void
    {
        $recentPredmeti = EkomPredmet::whereNotNull('last_synced_at')
            ->orderByDesc('last_synced_at')
            ->take(5)
            ->get()
            ->map(fn ($p) => [
                'type' => 'predmet',
                'icon' => 'folder',
                'title' => $p->oznaka,
                'subtitle' => 'Case synced',
                'timestamp' => $p->last_synced_at,
                'url' => route('ekom.predmeti.show', $p->remote_id),
            ]);

        $recentPodnesci = EkomPodnesak::whereNotNull('last_synced_at')
            ->orderByDesc('last_synced_at')
            ->take(5)
            ->get()
            ->map(fn ($p) => [
                'type' => 'podnesak',
                'icon' => 'upload',
                'title' => "Podnesak #{$p->remote_id}",
                'subtitle' => $p->status,
                'timestamp' => $p->last_synced_at,
                'url' => route('ekom.podnesci'),
            ]);

        $recentOtpravci = EkomOtpravak::whereNotNull('last_synced_at')
            ->orderByDesc('last_synced_at')
            ->take(5)
            ->get()
            ->map(fn ($o) => [
                'type' => 'otpravak',
                'icon' => 'download',
                'title' => "Otpravak #{$o->remote_id}",
                'subtitle' => $o->status,
                'timestamp' => $o->last_synced_at,
                'url' => route('ekom.otpravci'),
            ]);

        $this->recentActivity = collect()
            ->merge($recentPredmeti)
            ->merge($recentPodnesci)
            ->merge($recentOtpravci)
            ->sortByDesc('timestamp')
            ->take(10)
            ->values()
            ->toArray();
    }

    /**
     * Trigger a full sync of all E-Komunikacije data
     */
    public function triggerFullSync(): void
    {
        try {
            // Check if EkomSyncAllJob exists before dispatching
            if (class_exists(\App\Jobs\EkomSyncAllJob::class)) {
                \App\Jobs\EkomSyncAllJob::dispatch();
                $this->actionStatus = 'success';
                $this->actionMessage = 'Full sync job dispatched. Check Sync Status for progress.';
            } else {
                $this->actionStatus = 'warning';
                $this->actionMessage = 'Sync job not available. Please configure the sync service.';
            }
        } catch (\Throwable $e) {
            $this->actionStatus = 'error';
            $this->actionMessage = 'Failed to dispatch sync: ' . $e->getMessage();
        }
    }

    /**
     * Refresh statistics by clearing cache and reloading
     */
    public function refreshStats(): void
    {
        Cache::forget('ekom_dashboard_stats');
        $this->loadStats();
        $this->loadRecentActivity();
    }

    /**
     * Render the dashboard view
     */
    public function render()
    {
        return view('livewire.ekom-dashboard')
            ->layout('layouts.app', ['title' => 'E-Komunikacije Dashboard']);
    }
}
