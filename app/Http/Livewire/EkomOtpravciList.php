<?php

namespace App\Http\Livewire;

use App\Contracts\External\EkomServiceInterface;
use App\Models\EkomOtpravak;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * E-Komunikacije Dispatches List Component
 *
 * Displays a paginated, filterable list of EKOM dispatches (otpravci).
 *
 * Features:
 * - Filter by pending (unconfirmed receipt)
 * - Filter by status
 * - Confirm receipt action
 * - Sortable columns
 * - Pagination with configurable page size
 * - URL query string binding for shareable links
 */
class EkomOtpravciList extends Component
{
    use WithPagination;

    /**
     * Search query for filtering by remote_id
     */
    public string $search = '';

    /**
     * Status filter value
     */
    public string $statusFilter = '';

    /**
     * Filter for pending only (unconfirmed receipt)
     */
    public bool $pendingOnly = false;

    /**
     * URL filter parameter (converted to pendingOnly)
     */
    #[Url(as: 'filter')]
    public string $filter = '';

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
        return ['' => 'All Statuses'] + EkomOtpravak::STATUSES;
    }

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
        'pendingOnly' => ['except' => false],
        'sortField' => ['except' => 'last_synced_at'],
        'sortDirection' => ['except' => 'desc'],
        'perPage' => ['except' => 20],
    ];

    /**
     * Mount the component and process URL filter parameter
     */
    public function mount(): void
    {
        // Handle ?filter=pending URL parameter
        if ($this->filter === 'pending') {
            $this->pendingOnly = true;
        }
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
     * Reset pagination when pending filter changes
     */
    public function updatingPendingOnly(): void
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
        $allowedFields = ['remote_id', 'status', 'predmet_remote_id', 'vrijeme_slanja_sa_suda', 'last_synced_at'];
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
     * Confirm receipt of a dispatch
     *
     * @param  int  $id  Database ID of the otpravak
     */
    public function confirmReceipt(int $id): void
    {
        $otpravak = EkomOtpravak::findOrFail($id);

        // Already confirmed
        if ($otpravak->vrijeme_potvrde_primitka !== null) {
            $this->dispatch('toast', type: 'warning', message: 'Receipt already confirmed.');

            return;
        }

        try {
            // Extract numeric ID from remote_id (e.g., 'RO123456' -> 123456)
            $numericId = (int) preg_replace('/[^0-9]/', '', $otpravak->remote_id);

            // Call the EKOM service to confirm receipt
            $service = app(EkomServiceInterface::class);
            $service->potvrdiPrimitakOtpravka($numericId);

            // Update local model
            $otpravak->update([
                'vrijeme_potvrde_primitka' => now(),
            ]);

            $this->dispatch('toast', type: 'success', message: 'Receipt confirmed successfully.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Failed to confirm receipt: '.$e->getMessage());
        }
    }

    /**
     * Get the query for otpravci
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getOtpravciQuery()
    {
        return EkomOtpravak::query()
            ->when($this->search, function ($query) {
                $query->where('remote_id', 'like', '%'.$this->search.'%');
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->pendingOnly, function ($query) {
                $query->whereNull('vrijeme_potvrde_primitka');
            })
            ->orderBy($this->sortField, $this->sortDirection);
    }

    /**
     * Render the component
     */
    public function render()
    {
        $otpravci = $this->getOtpravciQuery()->paginate($this->perPage);

        return view('livewire.ekom-otpravci-list', [
            'otpravci' => $otpravci,
            'statusOptions' => $this->statusOptions,
        ])->layout('layouts.app', ['title' => 'Dispatches - E-Komunikacije']);
    }
}
