<?php

namespace App\Http\Livewire;

use App\Contracts\External\EkomServiceInterface;
use App\Models\EkomPredmet;
use Livewire\Component;

/**
 * E-Komunikacije Case Detail Component
 *
 * Displays detailed information about a single predmet (case) from EKOM.
 *
 * Features:
 * - Display predmet details (oznaka, status, court info, timestamps)
 * - Show raw JSON data from API
 * - Toggle DND (Do Not Disturb) for the case
 * - Refresh data from API
 * - Download documents action
 */
class EkomPredmetDetail extends Component
{
    /**
     * The remote ID used to look up the predmet
     */
    public string $remoteId;

    /**
     * The loaded predmet model
     */
    public ?EkomPredmet $predmet = null;

    /**
     * EKOM service for API operations
     */
    protected EkomServiceInterface $ekomService;

    /**
     * Boot method for dependency injection
     */
    public function boot(EkomServiceInterface $ekomService): void
    {
        $this->ekomService = $ekomService;
    }

    /**
     * Mount the component and load the predmet
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function mount(string $remoteId): void
    {
        $this->remoteId = $remoteId;
        $this->loadPredmet();
    }

    /**
     * Load the predmet from database
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    protected function loadPredmet(): void
    {
        $this->predmet = EkomPredmet::where('remote_id', $this->remoteId)->first();

        if (! $this->predmet) {
            abort(404, 'Predmet not found');
        }
    }

    /**
     * Toggle Do Not Disturb for this predmet
     */
    public function toggleDnd(): void
    {
        try {
            if ($this->predmet->do_not_disturb) {
                // Turn off DND
                $this->ekomService->turnOffDndPredmet($this->predmet->id);
                $this->predmet->update(['do_not_disturb' => false]);
                $this->dispatch('success', message: 'DND disabled for this case');
            } else {
                // Turn on DND
                $this->ekomService->turnOnDndPredmet($this->predmet->id);
                $this->predmet->update(['do_not_disturb' => true]);
                $this->dispatch('success', message: 'DND enabled for this case');
            }
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Failed to toggle DND: '.$e->getMessage());
        }
    }

    /**
     * Refresh predmet data from API
     */
    public function refreshFromApi(): void
    {
        try {
            $this->ekomService->syncPredmeti([
                'remoteId' => $this->remoteId,
            ], 1);

            // Reload the predmet from database
            $this->predmet->refresh();

            $this->dispatch('success', message: 'Case data refreshed from API');
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Failed to refresh from API: '.$e->getMessage());
        }
    }

    /**
     * Initiate document download for this predmet
     */
    public function downloadDocuments(): void
    {
        try {
            // Log the download request - actual download may be handled via queue or redirect
            \Log::info('Document download requested for predmet', [
                'remote_id' => $this->remoteId,
                'predmet_id' => $this->predmet->id,
            ]);

            $this->dispatch('success', message: 'Document download initiated');
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Failed to download documents: '.$e->getMessage());
        }
    }

    /**
     * Get the court name from data
     */
    public function getCourtNameProperty(): ?string
    {
        return $this->predmet->data['sudNaziv'] ?? null;
    }

    /**
     * Get formatted JSON data for display
     */
    public function getFormattedJsonProperty(): string
    {
        return json_encode($this->predmet->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.ekom-predmet-detail')
            ->layout('layouts.app', ['title' => 'Case Detail - E-Komunikacije']);
    }
}
