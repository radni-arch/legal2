<?php

namespace App\Agents\Contracts;

use App\Models\DocumentGenerationRun;

/**
 * Contract for the Legal Artillery Agent
 *
 * Defines the public API for legal document generation agents.
 * Implemented by LegalArtilleryAgent which wraps the orchestrator
 * with rendering and email dispatch capabilities.
 */
interface LegalArtilleryAgentContract
{
    /**
     * Fire a single document generation for a given profile
     *
     * @param string $profileKey The document profile key (e.g. 'predsjednik_suda')
     * @param int $userId The user initiating the generation
     * @param array $additionalContext Extra context to inject into the generation
     * @param bool $sendEmail Whether to send the result via email
     * @param bool $asDraft Whether to save as draft instead of sending
     * @param string|null $toEmail Override recipient email
     * @param int|null $maxIterations Override max iteration count
     * @return DocumentGenerationRun The completed generation run
     */
    public function fire(
        string $profileKey,
        int $userId,
        array $additionalContext = [],
        bool $sendEmail = false,
        bool $asDraft = false,
        ?string $toEmail = null,
        ?int $maxIterations = null,
        bool $submitEkom = false,
    ): DocumentGenerationRun;

    /**
     * Fire multiple document profiles in sequence (barrage)
     *
     * @param array|null $profileKeys Profile keys to fire, null for scenario profile list
     * @param int $userId The user initiating the generation
     * @param bool $sendEmail Whether to send results via email
     * @param bool $asDraft Whether to save as drafts
     * @param array|null $scenarioProfileKeys Ordered scenario profile keys to use when $profileKeys is null
     * @return array<string, DocumentGenerationRun|array> Results keyed by profile key
     */
    public function barrage(
        ?array $profileKeys = null,
        int $userId = 1,
        bool $sendEmail = false,
        bool $asDraft = true,
        ?array $scenarioProfileKeys = null,
    ): array;

    /**
     * List all available document profiles with their metadata
     *
     * @return array<string, array> Profiles keyed by profile key
     */
    public function availableProfiles(): array;

    /**
     * Approve a generation run for dispatch
     *
     * @param  string  $runId  The generation run ID
     * @param  int  $approverId  The user ID of the approver
     * @param  string|null  $notes  Optional approval notes
     * @return DocumentGenerationRun The updated run
     *
     * @throws \InvalidArgumentException If run cannot be approved
     */
    public function approveRun(string $runId, int $approverId, ?string $notes = null): DocumentGenerationRun;

    /**
     * Dispatch an approved run (send email/eKom)
     *
     * @param  string  $runId  The generation run ID
     * @param  bool  $sendEmail  Whether to send via email
     * @param  bool  $asDraft  Whether to save as draft
     * @param  string|null  $toEmail  Override recipient email
     * @param  bool  $submitEkom  Whether to submit via e-Komunikacija
     * @return DocumentGenerationRun The updated run
     *
     * @throws \InvalidArgumentException If run is not ready for dispatch
     */
    public function dispatchApproved(
        string $runId,
        bool $sendEmail = false,
        bool $asDraft = false,
        ?string $toEmail = null,
        bool $submitEkom = false,
    ): DocumentGenerationRun;

    /**
     * Preview dispatch payloads for an approved run.
     *
     * @param  string  $runId  The generation run ID
     * @param  bool  $sendEmail  Whether to preview email dispatch
     * @param  bool  $asDraft  Whether the email would be saved as draft
     * @param  string|null  $toEmail  Override recipient email
     * @param  bool  $submitEkom  Whether to preview e-Komunikacija submission
     * @return array<string, array> Preview payloads keyed by channel
     *
     * @throws \InvalidArgumentException If run is not approved or preview cannot be generated
     */
    public function previewDispatch(
        string $runId,
        bool $sendEmail = false,
        bool $asDraft = false,
        ?string $toEmail = null,
        bool $submitEkom = false,
    ): array;

    /**
     * Retry a failed dispatch without regenerating the document.
     *
     * @param  string  $runId  The generation run ID
     * @param  bool  $sendEmail  Whether to send via email
     * @param  bool  $asDraft  Whether to save as draft
     * @param  string|null  $toEmail  Override recipient email
     * @param  bool  $submitEkom  Whether to submit via e-Komunikacija
     * @return DocumentGenerationRun The updated run
     *
     * @throws \InvalidArgumentException If DOCX file not found or run not approved
     */
    public function retryDispatch(
        string $runId,
        bool $sendEmail = false,
        bool $asDraft = false,
        ?string $toEmail = null,
        bool $submitEkom = false,
    ): DocumentGenerationRun;

    /**
     * Update escalation metadata for a generation run.
     *
     * @param string $runId The generation run ID
     * @param array $state Escalation state fields (last_action, last_response, next_rung, etc.)
     * @return DocumentGenerationRun The updated run
     */
    public function updateEscalationState(string $runId, array $state): DocumentGenerationRun;
}
