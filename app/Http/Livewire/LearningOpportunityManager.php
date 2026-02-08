<?php

namespace App\Http\Livewire;

use App\Models\LearningOpportunity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * Learning Opportunity Manager Livewire Component
 *
 * Sprint 5.2: Human Feedback Integration
 *
 * Displays pending learning opportunities and allows attorneys to submit feedback.
 * Tracks low-confidence AI outputs that need human review for active learning
 * and model improvement.
 *
 * @property int|null $selectedOpportunityId Currently selected opportunity ID for feedback
 * @property array $feedbackData Feedback data submitted by user
 * @property string $filterType Filter by opportunity type (decision_discovery, precedent_analysis)
 */
class LearningOpportunityManager extends Component
{
    /**
     * Currently selected opportunity ID for feedback
     */
    public ?int $selectedOpportunityId = null;

    /**
     * Feedback data submitted by user
     */
    public array $feedbackData = [];

    /**
     * Filter by opportunity type
     */
    public string $filterType = '';

    /**
     * Validation rules
     */
    protected $rules = [
        'feedbackData' => 'required|array|min:1',
    ];

    /**
     * Event listeners
     */
    protected $listeners = ['refreshOpportunities' => '$refresh'];

    /**
     * Select an opportunity for feedback
     *
     * @param  int  $opportunityId  The opportunity ID to select
     */
    public function selectOpportunity(int $opportunityId): void
    {
        $this->selectedOpportunityId = $opportunityId;
        $this->feedbackData = [];
    }

    /**
     * Submit feedback for selected opportunity
     *
     * Validates feedback data, marks opportunity as reviewed,
     * and emits event for other components.
     */
    public function submitFeedback(): void
    {
        $this->validate();

        if (! $this->selectedOpportunityId) {
            $this->addError('selectedOpportunityId', 'No opportunity selected');

            return;
        }

        $opportunity = LearningOpportunity::find($this->selectedOpportunityId);

        if (! $opportunity) {
            $this->addError('selectedOpportunityId', 'Opportunity not found');

            return;
        }

        if ($opportunity->status === 'reviewed') {
            $this->addError('selectedOpportunityId', 'This opportunity has already been reviewed');

            return;
        }

        try {
            // Mark as reviewed
            $opportunity->markAsReviewed(
                userId: Auth::id(),
                humanLabel: $this->feedbackData
            );

            Log::info('Feedback submitted via Livewire', [
                'opportunity_id' => $opportunity->id,
                'user_id' => Auth::id(),
            ]);

            // Emit event for other components
            $this->emit('feedbackSubmitted', $opportunity->id);

            // Clear selection
            $this->selectedOpportunityId = null;
            $this->feedbackData = [];

            // Show success message
            session()->flash('message', 'Feedback submitted successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to submit feedback via Livewire', [
                'opportunity_id' => $this->selectedOpportunityId,
                'error' => $e->getMessage(),
            ]);

            $this->addError('submitFeedback', 'Failed to submit feedback: '.$e->getMessage());
        }
    }

    /**
     * Cancel feedback form
     *
     * Clears selected opportunity and feedback data.
     */
    public function cancelFeedback(): void
    {
        $this->selectedOpportunityId = null;
        $this->feedbackData = [];
    }

    /**
     * Render the component
     *
     * Fetches pending learning opportunities, applies type filter if set,
     * and orders by lowest confidence first.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        $query = LearningOpportunity::pending()
            ->lowestConfidenceFirst();

        // Apply type filter
        if ($this->filterType) {
            $query->ofType($this->filterType);
        }

        $opportunities = $query->get();

        return view('livewire.learning-opportunity-manager', [
            'opportunities' => $opportunities,
        ]);
    }
}
