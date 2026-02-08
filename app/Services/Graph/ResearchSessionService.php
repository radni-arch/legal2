<?php

namespace App\Services\Graph;

use App\Models\ResearchSession;
use Illuminate\Support\Facades\Auth;

class ResearchSessionService
{
    private ?ResearchSession $currentSession = null;

    /**
     * Get or create the current research session
     */
    public function getCurrentSession(): ResearchSession
    {
        if ($this->currentSession) {
            return $this->currentSession;
        }

        $userId = Auth::id();

        if (!$userId) {
            throw new \RuntimeException('User must be authenticated to access research sessions');
        }

        // Try to find recent unnamed session (within last 30 minutes)
        $this->currentSession = ResearchSession::query()
            ->where('user_id', $userId)
            ->whereNull('name')
            ->where('last_activity_at', '>', now()->subMinutes(30))
            ->orderBy('last_activity_at', 'desc')
            ->first();

        if (!$this->currentSession) {
            $this->currentSession = ResearchSession::create([
                'user_id' => $userId,
                'last_activity_at' => now(),
            ]);
        }

        return $this->currentSession;
    }

    /**
     * Set the current session by ID
     */
    public function setCurrentSession(int $sessionId): ?ResearchSession
    {
        $session = ResearchSession::find($sessionId);

        // Verify the session belongs to the authenticated user
        if ($session && $session->user_id !== Auth::id()) {
            return null; // Don't expose that session exists via exception
        }

        $this->currentSession = $session;
        return $this->currentSession;
    }

    /**
     * Track a viewed node
     */
    public function trackViewedNode(array $node): void
    {
        $session = $this->getCurrentSession();

        $viewedNodes = $session->viewed_nodes ?? [];

        // Don't add duplicates
        $existingIds = array_column($viewedNodes, 'id');
        if (!in_array($node['id'], $existingIds)) {
            $viewedNodes[] = [
                'id' => $node['id'],
                'type' => $node['type'] ?? null,
                'label' => $node['properties']['title'] ?? $node['properties']['case_number'] ?? $node['id'],
                'viewed_at' => now()->toISOString(),
            ];

            $session->update([
                'viewed_nodes' => $viewedNodes,
                'last_activity_at' => now(),
            ]);
        }
    }

    /**
     * Track a pinned node
     */
    public function trackPinnedNode(array $node): void
    {
        $session = $this->getCurrentSession();

        $pinnedNodes = $session->pinned_nodes ?? [];

        // Don't add duplicates
        $existingIds = array_column($pinnedNodes, 'id');
        if (!in_array($node['id'], $existingIds)) {
            $pinnedNodes[] = [
                'id' => $node['id'],
                'type' => $node['type'] ?? null,
                'label' => $node['properties']['title'] ?? $node['properties']['case_number'] ?? $node['id'],
                'pinned_at' => now()->toISOString(),
            ];

            $session->update([
                'pinned_nodes' => $pinnedNodes,
                'last_activity_at' => now(),
            ]);
        }
    }

    /**
     * Remove a pinned node
     */
    public function unpinNode(string $nodeId): void
    {
        $session = $this->getCurrentSession();

        $pinnedNodes = array_filter(
            $session->pinned_nodes ?? [],
            fn($n) => $n['id'] !== $nodeId
        );

        $session->update([
            'pinned_nodes' => array_values($pinnedNodes),
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Track an expanded node
     */
    public function trackExpandedNode(string $nodeId): void
    {
        $session = $this->getCurrentSession();

        $expandedNodes = $session->expanded_nodes ?? [];

        if (!in_array($nodeId, $expandedNodes)) {
            $expandedNodes[] = $nodeId;

            $session->update([
                'expanded_nodes' => $expandedNodes,
                'last_activity_at' => now(),
            ]);
        }
    }

    /**
     * Save the session with a name
     */
    public function saveSession(string $name, ?string $description = null): ResearchSession
    {
        $session = $this->getCurrentSession();

        $session->update([
            'name' => $name,
            'description' => $description,
            'last_activity_at' => now(),
        ]);

        return $session;
    }

    /**
     * Get user's saved sessions
     */
    public function getSavedSessions(?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();

        return ResearchSession::query()
            ->where('user_id', $userId)
            ->whereNotNull('name')
            ->orderBy('last_activity_at', 'desc')
            ->limit(20)
            ->get()
            ->toArray();
    }

    /**
     * Update filter settings
     */
    public function updateFilterSettings(array $settings): void
    {
        $session = $this->getCurrentSession();

        $session->update([
            'filter_settings' => $settings,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Set root node
     */
    public function setRootNode(string $nodeId): void
    {
        $session = $this->getCurrentSession();

        $session->update([
            'root_node_id' => $nodeId,
            'last_activity_at' => now(),
        ]);
    }
}
