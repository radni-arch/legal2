<?php

namespace App\Livewire;

use App\Models\LegalCase;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Case listing page.
 *
 * Shows all LegalCase records with search, status filter, and pagination.
 */
class CaseIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = LegalCase::query()
            ->orderByDesc('updated_at');

        if ($this->search) {
            $term = '%' . $this->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('case_number', 'ilike', $term)
                    ->orWhere('title', 'ilike', $term)
                    ->orWhere('client_name', 'ilike', $term)
                    ->orWhere('opponent_name', 'ilike', $term)
                    ->orWhere('court', 'ilike', $term);
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $cases = $query->withCount(['documents', 'uploads', 'textractJobs'])->paginate(20);

        $statuses = LegalCase::select('status')
            ->distinct()
            ->whereNotNull('status')
            ->pluck('status')
            ->sort()
            ->values();

        return view('livewire.case-index', [
            'cases' => $cases,
            'statuses' => $statuses,
        ]);
    }
}
