<?php

namespace App\Services\LegalArtillery;

use App\Models\AgentPromptVersion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Manages prompt versioning for the legal-artillery system.
 *
 * Captures the full prompt (system + context + user) used for each generation,
 * stores it in the agent_prompt_versions table with git metadata, and allows
 * retrieval of historical versions for reproducibility.
 *
 * Integrates Dio 5 requirement: "Verzija prompta se cuva u Git repozitoriju
 * za reproducibilnost."
 */
class PromptVersionManager
{
    private const AGENT_PREFIX = 'legal-artillery:';

    /**
     * Save a prompt version for a specific profile generation.
     *
     * @param string $profileKey The document profile key (e.g., 'izdvajanje_dokaza')
     * @param string $systemPrompt The system prompt used
     * @param string $userPrompt The user prompt used
     * @param string $injectedContext The injected legal context
     * @param array $metadata Additional metadata (model, config, etc.)
     */
    public function saveVersion(
        string $profileKey,
        string $systemPrompt,
        string $userPrompt,
        string $injectedContext = '',
        array $metadata = [],
    ): AgentPromptVersion {
        $agentName = self::AGENT_PREFIX . $profileKey;
        $fullPrompt = $this->buildFullPrompt($systemPrompt, $userPrompt, $injectedContext);
        $promptHash = $this->hashPrompt($fullPrompt);

        $cachedVersion = $this->getCachedVersion($profileKey, $systemPrompt, $userPrompt, $injectedContext);
        if ($cachedVersion) {
            Log::info('PromptVersionManager: Reusing cached version', [
                'agent' => $agentName,
                'version' => $cachedVersion->version,
                'git_hash' => $cachedVersion->metadata['git_hash'] ?? null,
            ]);

            return $this->activateVersion($cachedVersion);
        }

        $version = $this->generateVersionString();
        $gitHash = $this->getCurrentGitHash();

        $versionMetadata = array_merge($metadata, [
            'git_hash' => $gitHash,
            'profile_key' => $profileKey,
            'system_prompt_length' => strlen($systemPrompt),
            'user_prompt_length' => strlen($userPrompt),
            'context_length' => strlen($injectedContext),
            'total_length' => strlen($fullPrompt),
            'prompt_hash' => $promptHash,
            'saved_at' => now()->toIso8601String(),
        ]);

        Log::info('PromptVersionManager: Saving version', [
            'agent' => $agentName,
            'version' => $version,
            'git_hash' => $gitHash,
        ]);

        return AgentPromptVersion::createVersion(
            agentName: $agentName,
            version: $version,
            instructions: $fullPrompt,
            metadata: $versionMetadata,
            activate: true,
        );
    }

    /**
     * Find an existing prompt version for identical prompts.
     */
    public function getCachedVersion(
        string $profileKey,
        string $systemPrompt,
        string $userPrompt,
        string $injectedContext = '',
    ): ?AgentPromptVersion {
        $fullPrompt = $this->buildFullPrompt($systemPrompt, $userPrompt, $injectedContext);
        $promptHash = $this->hashPrompt($fullPrompt);

        return AgentPromptVersion::where('agent_name', self::AGENT_PREFIX . $profileKey)
            ->where('metadata->prompt_hash', $promptHash)
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Activate a prompt version, deactivating any other active version for the agent.
     */
    public function activateVersion(AgentPromptVersion $version): AgentPromptVersion
    {
        if ($version->is_active) {
            return $version;
        }

        AgentPromptVersion::where('agent_name', $version->agent_name)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $version->update(['is_active' => true]);

        return $version->fresh();
    }

    /**
     * Get the active prompt version for a profile.
     */
    public function getActiveVersion(string $profileKey): ?AgentPromptVersion
    {
        return AgentPromptVersion::activeFor(self::AGENT_PREFIX . $profileKey);
    }

    /**
     * Get version history for a profile.
     */
    public function getHistory(string $profileKey): \Illuminate\Database\Eloquent\Collection
    {
        return AgentPromptVersion::historyFor(self::AGENT_PREFIX . $profileKey);
    }

    /**
     * Record performance metrics against a prompt version.
     */
    public function recordPerformance(string $profileKey, array $metrics): void
    {
        $active = $this->getActiveVersion($profileKey);
        if ($active) {
            $active->recordMetrics($metrics);
        }
    }

    /**
     * Generate a version string based on date and sequence.
     */
    private function generateVersionString(): string
    {
        $date = now()->format('Y.m.d');
        $existing = AgentPromptVersion::where('version', 'like', "{$date}%")->count();

        return "{$date}." . ($existing + 1);
    }

    /**
     * Get the current git commit hash (short form).
     */
    private function getCurrentGitHash(): ?string
    {
        try {
            $result = Process::timeout(5)->run('git rev-parse --short HEAD 2>/dev/null');
            return $result->successful() ? trim($result->output()) : null;
        } catch (\Exception) {
            return null;
        }
    }

    private function buildFullPrompt(
        string $systemPrompt,
        string $userPrompt,
        string $injectedContext = '',
    ): string {
        return "=== SYSTEM ===\n{$systemPrompt}\n\n"
            . "=== CONTEXT ===\n{$injectedContext}\n\n"
            . "=== USER ===\n{$userPrompt}";
    }

    private function hashPrompt(string $fullPrompt): string
    {
        return hash('sha256', $fullPrompt);
    }
}
