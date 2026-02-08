<?php

namespace App\Http\Livewire;

use App\Contracts\External\EkomServiceInterface;
use App\Models\EkomPredmet;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * E-Komunikacije Cases List Component
 *
 * Displays a paginated, searchable, filterable list of EKOM cases (predmeti).
 *
 * Features:
 * - Search by oznaka (case number)
 * - Filter by status
 * - Filter by DND (Do Not Disturb) state
 * - Sortable columns (oznaka, status, last_synced_at)
 * - Pagination with configurable page size
 * - Toggle DND directly from list via service
 * - URL query string binding for shareable links
 */
class EkomPredmetiList extends Component
{
    use WithPagination;

    /**
     * Search query for filtering by oznaka
     */
    public string $search = '';

    /**
     * Status filter value
     */
    public string $statusFilter = '';

    /**
     * DND filter: '', 'enabled', 'disabled'
     */
    public string $dndFilter = '';

    /**
     * Items per page
     */
    public int $perPage = 20;

    /**
     * Current sort field
     */
    public string $sortField = 'last_synced_at';

    /**
     * Current sort direction
     */
    public string $sortDirection = 'desc';

    /**
     * Get available status options for filtering
     *
     * @return array<string, string>
     */
    public function getStatusOptionsProperty(): array
    {
        return ['' => 'All Statuses'] + EkomPredmet::STATUSES;
    }

    /**
     * Available DND filter options
     */
    public array $dndOptions = [
        '' => 'All',
        'enabled' => 'DND Enabled',
        'disabled' => 'DND Disabled',
    ];

    /**
     * Available page size options
     */
    public array $perPageOptions = [10, 20, 50, 100];

    /**
     * Tailwind pagination theme
     */
    protected $paginationTheme = 'tailwind';

    /**
     * Query string bindings for shareable URLs
     */
    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'dndFilter' => ['except' => ''],
        'sortField' => ['except' => 'last_synced_at'],
        'sortDirection' => ['except' => 'desc'],
        'perPage' => ['except' => 20],
    ];

    /**
     * EKOM service for DND operations
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
     * Reset pagination when search changes
     */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when status filter changes
     */
    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when DND filter changes
     */
    public function updatingDndFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when per page changes
     */
    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    /**
     * Sort by specified column
     *
     * Toggles direction if same column is clicked twice.
     *
     * @param  string  $field  Field name to sort by
     */
    public function sortBy(string $field): void
    {
        // Only allow sorting by valid fields
        $allowedFields = ['oznaka', 'status', 'last_synced_at'];
        if (! in_array($field, $allowedFields)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    /**
     * Toggle Do Not Disturb for a predmet
     *
     * @param  int  $predmetId  The predmet ID
     */
    public function toggleDnd(int $predmetId): void
    {
        try {
            $predmet = EkomPredmet::findOrFail($predmetId);

            if ($predmet->do_not_disturb) {
                // Turn off DND
                $this->ekomService->turnOffDndPredmet($predmetId);
                $predmet->update(['do_not_disturb' => false]);
                $this->dispatch('success', message: 'DND disabled for ' . $predmet->oznaka);
            } else {
                // Turn on DND
                $this->ekomService->turnOnDndPredmet($predmetId);
                $predmet->update(['do_not_disturb' => true]);
                $this->dispatch('success', message: 'DND enabled for ' . $predmet->oznaka);
            }
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to toggle DND: ' . $e->getMessage());
        }
    }

    /**
     * Get the query for predmeti
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getPredmetiQuery()
    {
        return EkomPredmet::query()
            ->when($this->search, function ($query) {
                $query->where('oznaka', 'like', '%'.$this->search.'%');
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->dndFilter, function ($query) {
                if ($this->dndFilter === 'enabled') {
                    $query->where('do_not_disturb', true);
                } elseif ($this->dndFilter === 'disabled') {
                    $query->where('do_not_disturb', false);
                }
            })
            ->orderBy($this->sortField, $this->sortDirection);
    }

    /**
     * Render the component
     */
    public function render()
    {
        $predmeti = $this->getPredmetiQuery()->paginate($this->perPage);

        return view('livewire.ekom-predmeti-list', [
            'predmeti' => $predmeti,
            'statusOptions' => $this->statusOptions,
        ])->layout('layouts.app', ['title' => 'Cases - E-Komunikacije']);
    }
}
