<?php

namespace App\Http\Livewire;

use App\Models\AgentCollaboration;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Collaboration Dashboard Component
 *
 * Displays an overview of multi-agent collaborations with statistics,
 * paginated list of recent collaborations, and detailed modal view
 * showing agent executions and results.
 *
 * Features:
 * - Real-time statistics (total, completed, in-progress, failed)
 * - Paginated collaboration list with status badges
 * - Detailed modal view with agent execution timeline
 * - Cost and token usage tracking
 * - Executive summary display for completed collaborations
 *
 * @property AgentCollaboration|null $selectedCollaboration Currently selected collaboration for details view
 * @property bool $showDetails Whether the details modal is visible
 */
class CollaborationDashboard extends Component
{
    use WithPagination;

    /**
     * Currently selected collaboration for details modal
     *
     * @var AgentCollaboration|null
     */
    public $selectedCollaboration = null;

    /**
     * Controls visibility of details modal
     *
     * @var bool
     */
    public $showDetails = false;

    /**
     * Livewire event listeners
     *
     * @var array<string, string>
     */
    protected $listeners = ['refreshCollaborations' => '$refresh'];

    /**
     * Open details modal for a specific collaboration
     *
     * Validates the collaboration ID format (UUID) before querying
     * to prevent PostgreSQL errors. Loads collaboration with
     * all related agent executions.
     *
     * @param  string  $collaborationId  UUID of the collaboration to view
     * @return void
     */
    public function viewDetails($collaborationId)
    {
        // Validate UUID format to prevent PostgreSQL errors
        if (! is_string($collaborationId) || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $collaborationId)) {
            $this->selectedCollaboration = null;
            $this->showDetails = true;

            return;
        }

        $this->selectedCollaboration = AgentCollaboration::with('executions')
            ->find($collaborationId);
        $this->showDetails = true;
    }

    /**
     * Close the details modal
     *
     * Resets the selected collaboration and hides the modal.
     *
     * @return void
     */
    public function closeDetails()
    {
        $this->showDetails = false;
        $this->selectedCollaboration = null;
    }

    /**
     * Render the component
     *
     * Loads paginated collaborations (20 per page) and calculates
     * comprehensive statistics including:
     * - Total, completed, in-progress, and failed counts
     * - Average duration for completed collaborations
     * - Total token usage and cost across all collaborations
     * - Recent activity (last 7 days)
     *
     * Optimized to use a single SQL query for statistics instead of 7 separate queries
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        // Single optimized query for all statistics using raw SQL
        $stats = DB::table('agent_collaborations')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN started_at >= ? THEN 1 ELSE 0 END) as recent_7_days,
                AVG(CASE WHEN status = ? THEN duration_seconds END) as avg_duration,
                SUM(tokens_used) as total_tokens,
                SUM(cost_spent) as total_cost
            ', [
                'completed',
                'in_progress',
                'failed',
                now()->subDays(7),
                'completed',
            ])
            ->first();

        // Optimized pagination query with selective column loading
        $collaborations = AgentCollaboration::query()
            ->select([
                'id',
                'session_id',
                'problem_type',
                'problem_statement',
                'status',
                'agents_involved',
                'total_steps',
                'completed_steps',
                'tokens_used',
                'cost_spent',
                'duration_seconds',
                'started_at',
                'completed_at',
            ])
            ->with(['executions' => function ($query) {
                $query->select([
                    'id',
                    'collaboration_id',
                    'agent_name',
                    'status',
                    'execution_order',
                    'tokens_used',
                    'cost_spent',
                    'duration_ms',
                ]);
            }])
            ->orderBy('started_at', 'desc')
            ->paginate(20);

        return view('livewire.collaboration-dashboard', [
            'collaborations' => $collaborations,
            'stats' => [
                'total' => $stats->total ?? 0,
                'completed' => $stats->completed ?? 0,
                'in_progress' => $stats->in_progress ?? 0,
                'failed' => $stats->failed ?? 0,
                'recent_7_days' => $stats->recent_7_days ?? 0,
                'avg_duration' => $stats->avg_duration ? (float) $stats->avg_duration : null,
                'total_tokens' => $stats->total_tokens ?? 0,
                'total_cost' => $stats->total_cost ?? 0,
            ],
        ])->layout('layouts.app', ['title' => 'Collaborations']);
    }
}
