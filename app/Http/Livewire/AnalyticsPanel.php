<?php

namespace App\Http\Livewire;

use App\Repositories\GraphMetricsRepository;
use Livewire\Component;

class AnalyticsPanel extends Component
{
    /**
     * Selected view
     */
    public string $selectedView = 'influential';

    /**
     * Available views
     */
    public array $views = [
        'influential' => 'Influential Decisions',
        'contradictions' => 'Contradictions',
        'outliers' => 'Outliers',
        'clusters' => 'Citation Clusters',
    ];

    /**
     * Influential decisions data
     */
    public array $influentialDecisions = [];

    /**
     * Citation clusters data
     */
    public array $citationClusters = [];

    /**
     * Mount the component
     */
    public function mount(): void
    {
        $this->loadData();
    }

    /**
     * Load analytics data
     */
    public function loadData(): void
    {
        /** @var GraphMetricsRepository $metricsRepo */
        $metricsRepo = app(GraphMetricsRepository::class);

        $this->influentialDecisions = $metricsRepo->getInfluentialDecisions(20);
        $this->citationClusters = $metricsRepo->getCitationClusters(10);
    }

    /**
     * Switch view
     */
    public function switchView(string $view): void
    {
        if (array_key_exists($view, $this->views)) {
            $this->selectedView = $view;
        } else {
            $this->selectedView = 'influential';
        }
    }

    /**
     * Refresh data
     */
    public function refreshData(): void
    {
        $this->loadData();
    }

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.analytics-panel');
    }
}
