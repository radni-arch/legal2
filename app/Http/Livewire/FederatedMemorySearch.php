<?php

namespace App\Http\Livewire;

use App\Services\FederatedMemoryService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * Federated Memory Search Component
 *
 * UI for pgvector similarity search across agent memories.
 *
 * Features:
 * - Semantic search with vector similarity
 * - Cross-agent memory retrieval
 * - Agent-specific filtering
 * - Fallback to text search
 * - Access count tracking
 */
class FederatedMemorySearch extends Component
{
    public $searchQuery = '';

    public $agentFilter = ''; // Empty = all agents

    public $searchResults = [];

    public $searchPerformed = false;

    public $errorMessage = '';

    public $limit = 10;

    protected $rules = [
        'searchQuery' => 'required|min:1',
    ];

    protected $messages = [
        'searchQuery.required' => 'Please enter a search query',
        'searchQuery.min' => 'Please enter a search query',
    ];

    /**
     * Perform semantic search across agent memories
     */
    public function search()
    {
        // Validate input
        $this->validate();

        $this->errorMessage = '';
        $this->searchResults = [];
        $this->searchPerformed = false;

        try {
            $federatedMemory = app(FederatedMemoryService::class);

            // Search with optional agent filter
            $agentType = empty($this->agentFilter) ? null : $this->agentFilter;

            $this->searchResults = $federatedMemory->searchCrossAgent(
                $this->searchQuery,
                $agentType,
                $this->limit
            );

            $this->searchPerformed = true;

            Log::info('Federated memory search completed', [
                'query' => $this->searchQuery,
                'agent_filter' => $agentType,
                'results_count' => count($this->searchResults),
            ]);
        } catch (\Exception $e) {
            $this->errorMessage = 'Search failed: '.$e->getMessage();

            Log::error('Federated memory search failed', [
                'query' => $this->searchQuery,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reset search
     */
    public function resetSearch()
    {
        $this->searchQuery = '';
        $this->agentFilter = '';
        $this->searchResults = [];
        $this->searchPerformed = false;
        $this->errorMessage = '';
    }

    /**
     * Render component
     */
    public function render()
    {
        return view('livewire.federated-memory-search');
    }
}
