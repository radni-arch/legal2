<?php

namespace App\Http\Livewire;

use Livewire\Component;

class GraphDashboard extends Component
{
    /**
     * Active panel identifier
     */
    public string $activePanel = 'explorer';

    /**
     * Available panels
     */
    public array $panels = [
        'explorer' => 'Explorer',
        'llm_brain' => 'LLM Brain',
        'analytics' => 'Analytics',
        'temporal' => 'Temporal',
        'admin' => 'Admin',
    ];

    /**
     * Switch to a different panel
     */
    public function switchPanel(string $panel): void
    {
        if (array_key_exists($panel, $this->panels)) {
            $this->activePanel = $panel;
        } else {
            $this->activePanel = 'explorer';
        }
    }

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.graph-dashboard');
    }
}
