<?php

namespace App\Livewire\LegalArtillery;

use App\DTOs\DocumentProfile;
use App\Models\DocumentGenerationRun;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Legal Artillery Dashboard Component
 *
 * Lists all DocumentGenerationRun records for the current user.
 * Supports filtering by status and document profile, with pagination.
 * Displays aggregate statistics with a single optimized query.
 */
class Dashboard extends Component
{
    use WithPagination;

    public string $statusFilter = '';

    public string $profileFilter = '';

    protected $listeners = ['generation-completed' => '$refresh'];

    public function render()
    {
        $query = DocumentGenerationRun::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at');

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->profileFilter) {
            $query->where('document_type', $this->profileFilter);
        }

        return view('livewire.legal-artillery.dashboard', [
            'runs' => $query->paginate(15),
            'profiles' => DocumentProfile::all(),
            'stats' => $this->getStats(),
        ]);
    }

    protected function getStats(): array
    {
        $userId = Auth::id();

        $stats = DocumentGenerationRun::where('user_id', $userId)
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN status IN ('running', 'pending') THEN 1 END) as running,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
                AVG(CASE WHEN status = 'completed' THEN final_score END) as avg_score,
                AVG(CASE WHEN status = 'completed' THEN total_iterations END) as avg_iterations
            ")
            ->first();

        return [
            'total' => (int) $stats->total,
            'completed' => (int) $stats->completed,
            'running' => (int) $stats->running,
            'failed' => (int) $stats->failed,
            'avg_score' => $stats->avg_score,
            'avg_iterations' => $stats->avg_iterations,
        ];
    }
}
