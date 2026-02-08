<?php

namespace App\Http\Livewire;

use App\Jobs\EkomSyncAllJob;
use App\Models\EkomOtpravak;
use App\Models\EkomPodnesak;
use App\Models\EkomPredmet;
use Livewire\Component;

/**
 * E-Komunikacije Sync Status Component
 *
 * Displays synchronization status and statistics for E-Komunikacije data.
 * Shows counts and last sync times for predmeti, podnesci, and otpravci.
 * Allows manual triggering of the sync job.
 */
class EkomSyncStatus extends Component
{
    /**
     * Sync statistics for all entity types
     */
    public array $stats = [];

    /**
     * Status message for user feedback
     */
    public ?string $statusMessage = null;

    /**
     * Component initialization
     */
    public function mount(): void
    {
        $this->loadStats();
    }

    /**
     * Load sync statistics from database
     */
    public function loadStats(): void
    {
        $this->stats = [
            'predmeti_count' => EkomPredmet::count(),
            'podnesci_count' => EkomPodnesak::count(),
            'otpravci_count' => EkomOtpravak::count(),
            'predmeti_last_sync' => EkomPredmet::max('last_synced_at'),
            'podnesci_last_sync' => EkomPodnesak::max('last_synced_at'),
            'otpravci_last_sync' => EkomOtpravak::max('last_synced_at'),
        ];
    }

    /**
     * Trigger a manual sync job
     */
    public function triggerSync(): void
    {
        try {
            EkomSyncAllJob::dispatch();
            $this->statusMessage = 'Sync job dispatched successfully';
            $this->dispatch('success', message: $this->statusMessage);
        } catch (\Throwable $e) {
            $this->statusMessage = 'Failed to dispatch sync: ' . $e->getMessage();
            $this->dispatch('error', message: $this->statusMessage);
        }
    }

    /**
     * Refresh statistics by reloading from database
     */
    public function refreshStats(): void
    {
        $this->loadStats();
    }

    /**
     * Render the component view
     */
    public function render()
    {
        return view('livewire.ekom-sync-status')
            ->layout('layouts.app', ['title' => 'Sync Status - E-Komunikacije']);
    }
}
