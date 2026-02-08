<?php

namespace App\Http\Livewire;

use App\Models\EkomPodnesak;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * E-Komunikacije Submissions List Component
 *
 * Displays a paginated, searchable, filterable list of EKOM submissions (podnesci).
 *
 * Features:
 * - Search by remote_id
 * - Filter by status (kreiran, poslan, zaprimljen, u_obradi)
 * - Sortable columns (remote_id, status, last_synced_at)
 * - Pagination with configurable page size
 * - URL query string binding for shareable links
 */
class EkomPodnesciList extends Component
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
        return ['' => 'All Statuses'] + EkomPodnesak::STATUSES;
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
        'sortField' => ['except' => 'last_synced_at'],
        'sortDirection' => ['except' => 'desc'],
        'perPage' => ['except' => 20],
    ];

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
        $allowedFields = ['remote_id', 'status', 'last_synced_at'];
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
     * Get the query for podnesci
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getPodnesciQuery()
    {
        return EkomPodnesak::query()
            ->when($this->search, function ($query) {
                $query->where('remote_id', 'like', '%'.$this->search.'%');
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->orderBy($this->sortField, $this->sortDirection);
    }

    /**
     * Render the component
     */
    public function render()
    {
        $podnesci = $this->getPodnesciQuery()->paginate($this->perPage);

        return view('livewire.ekom-podnesci-list', [
            'podnesci' => $podnesci,
            'statusOptions' => $this->statusOptions,
        ])->layout('layouts.app', ['title' => 'Submissions - E-Komunikacije']);
    }
}
