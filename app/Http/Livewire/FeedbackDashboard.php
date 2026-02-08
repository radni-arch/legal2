<?php

namespace App\Http\Livewire;

use App\Models\LearningOpportunity;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Feedback Dashboard Livewire Component
 *
 * Sprint 5.2: Human Feedback Integration
 *
 * Displays statistics and metrics for learning opportunities and feedback.
 * Learning opportunities are low-confidence AI outputs that need human review
 * for active learning and model improvement.
 *
 * @property int $totalOpportunities Total count of all learning opportunities
 * @property int $pendingOpportunities Count of learning opportunities awaiting review
 * @property int $reviewedOpportunities Count of reviewed learning opportunities
 * @property float $completionRate Percentage of reviewed opportunities (0-100)
 * @property array $typeBreakdown Count of pending opportunities by type
 * @property float $averageConfidence Average confidence score across all opportunities
 * @property array $recentActivity Last 5 reviewed opportunities with reviewer info
 */
class FeedbackDashboard extends Component
{
    /**
     * Total count of all learning opportunities
     */
    public int $totalOpportunities = 0;

    /**
     * Count of learning opportunities awaiting review
     */
    public int $pendingOpportunities = 0;

    /**
     * Count of reviewed learning opportunities
     */
    public int $reviewedOpportunities = 0;

    /**
     * Percentage of reviewed opportunities (0-100)
     */
    public float $completionRate = 0.0;

    /**
     * Count of pending opportunities grouped by type
     *
     * @var array<string, int>
     */
    public array $typeBreakdown = [];

    /**
     * Average confidence score across all opportunities
     */
    public float $averageConfidence = 0.0;

    /**
     * Last 5 reviewed opportunities with reviewer info
     *
     * @var array<int, array{id: int, type: string, reviewed_at: string|null, reviewed_by: string}>
     */
    public array $recentActivity = [];

    /**
     * Livewire event listeners
     *
     * @var array<string, string>
     */
    protected $listeners = ['feedbackSubmitted' => 'refreshStats'];

    /**
     * Mount the component and initialize statistics
     *
     * Called when the Livewire component is first loaded. Initializes all
     * statistics by calling refreshStats().
     */
    public function mount(): void
    {
        $this->refreshStats();
    }

    /**
     * Refresh all dashboard statistics
     *
     * Recalculates all statistics from the database:
     * - Total, pending, and reviewed opportunity counts
     * - Completion rate percentage
     * - Breakdown of pending opportunities by type
     * - Average confidence score across all opportunities
     * - Recent activity (last 5 reviewed opportunities)
     *
     * Can be triggered manually via the "Refresh Statistics" button or
     * automatically via the 'feedbackSubmitted' Livewire event.
     */
    public function refreshStats(): void
    {
        // Total count
        $this->totalOpportunities = LearningOpportunity::count();

        // Pending count
        $this->pendingOpportunities = LearningOpportunity::pending()->count();

        // Reviewed count
        $this->reviewedOpportunities = LearningOpportunity::reviewed()->count();

        // Completion rate
        $this->completionRate = $this->totalOpportunities > 0
            ? round(($this->reviewedOpportunities / $this->totalOpportunities) * 100, 1)
            : 0.0;

        // Breakdown by type
        $this->typeBreakdown = LearningOpportunity::select('opportunity_type', DB::raw('count(*) as count'))
            ->where('status', 'pending')
            ->groupBy('opportunity_type')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->opportunity_type => $item->count])
            ->toArray();

        // Average confidence
        $avgConfidence = LearningOpportunity::avg('confidence_score');
        $this->averageConfidence = $avgConfidence ? round($avgConfidence, 2) : 0.0;

        // Recent activity (last 5 reviewed)
        $this->recentActivity = LearningOpportunity::reviewed()
            ->with('reviewer')
            ->orderBy('reviewed_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($opp) => [
                'id' => $opp->id,
                'type' => $opp->opportunity_type,
                'reviewed_at' => $opp->reviewed_at?->diffForHumans(),
                'reviewed_by' => $opp->reviewer?->name ?? 'Unknown',
            ])
            ->toArray();
    }

    /**
     * Render the component view
     *
     * Returns the Livewire component view with all current statistics.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('livewire.feedback-dashboard');
    }
}
