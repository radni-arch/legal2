<?php

namespace App\Services\LegalArtillery;

use App\Contracts\LegalArtillery\EscalationSuggesterInterface;
use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;

class EscalationLadderSuggester implements EscalationSuggesterInterface
{
    /** @var array<int, string> Linear hierarchy from config */
    protected array $hierarchyList;

    public function __construct(
        protected AttachmentCollector $attachmentCollector = new AttachmentCollector(),
        protected DocumentInventory $documentInventory = new DocumentInventory(),
        ?array $hierarchy = null,
    ) {
        $this->hierarchyList = $hierarchy ?? config('escalation-ladders.hierarchy', []);
    }

    /**
     * Suggest the next escalation rung.
     *
     * @param string $failedProfileKey Profile key of the failed attempt
     * @param string $responseType Response type (e.g., denied, ignored, no_response)
     * @param int $elapsedDays Days since the failed attempt
     * @param string $ladderKey Ladder identifier in config/escalation-ladders.php
     * @param CaseContext|null $caseContext Optional case context for attachment resolution
     * @return array<string, mixed>
     */
    public function suggestNextRung(
        string $failedProfileKey,
        string $responseType,
        int $elapsedDays,
        string $ladderKey = 'default',
        ?CaseContext $caseContext = null,
    ): array {
        $ladder = $this->resolveLadder($ladderKey);
        if ($ladder === []) {
            return $this->emptySuggestion('Escalation ladder not configured.');
        }

        $currentIndex = $this->findRungIndex($ladder, $failedProfileKey);
        if ($currentIndex === null) {
            return $this->emptySuggestion('Failed profile key not found in ladder.');
        }

        $currentRung = $ladder[$currentIndex];

        $responseTypes = $currentRung['escalate_on'] ?? ['denied', 'ignored', 'no_response'];
        $minWaitDays = (int) ($currentRung['min_wait_days'] ?? 0);

        if (!in_array($responseType, $responseTypes, true)) {
            return $this->emptySuggestion('Response type does not trigger escalation.');
        }

        if ($elapsedDays < $minWaitDays) {
            return $this->emptySuggestion('Minimum wait period has not elapsed.', [
                'wait_days_remaining' => $minWaitDays - $elapsedDays,
            ]);
        }

        $nextIndex = $currentIndex + 1;
        if (!isset($ladder[$nextIndex])) {
            return $this->emptySuggestion('No further escalation rung available.');
        }

        $nextRung = $ladder[$nextIndex];
        $profileKey = $nextRung['profile'] ?? $nextRung['profile_key'] ?? null;
        if ($profileKey === null) {
            return $this->emptySuggestion('Next rung missing profile key.');
        }

        $profile = DocumentProfile::fromConfig($profileKey);
        $requiredInputs = $nextRung['required_inputs']
            ?? ($profile->metadata['required_inputs'] ?? []);

        $requiredAttachmentTypes = $nextRung['required_attachment_types'] ?? [];
        $attachments = $this->resolveAttachments($profileKey, $requiredAttachmentTypes, $caseContext);

        return [
            'next_profile_key' => $profileKey,
            'next_profile_name' => $profile->name,
            'required_inputs' => $requiredInputs,
            'attachments' => $attachments,
            'reason' => $nextRung['reason'] ?? null,
        ];
    }

    /**
     * Resolve ladder configuration by key.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function resolveLadder(string $ladderKey): array
    {
        $config = config('escalation-ladders', []);
        $ladders = $config['ladders'] ?? $config;
        $ladder = $ladders[$ladderKey] ?? [];

        if (!is_array($ladder)) {
            return [];
        }

        return array_values($ladder);
    }

    /**
     * Find the index of the rung matching the failed profile key.
     */
    protected function findRungIndex(array $ladder, string $profileKey): ?int
    {
        foreach ($ladder as $index => $rung) {
            $key = $rung['profile'] ?? $rung['profile_key'] ?? null;
            if ($key === $profileKey) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Resolve attachment requirements for a rung.
     *
     * @param string $profileKey
     * @param array<int, string> $requiredAttachmentTypes
     * @return array<string, mixed>
     */
    protected function resolveAttachments(
        string $profileKey,
        array $requiredAttachmentTypes,
        ?CaseContext $caseContext,
    ): array {
        $attachments = [
            'document_inventory' => $this->documentInventory->forProfile($profileKey),
            'required_attachment_types' => $requiredAttachmentTypes,
            'required_attachment_files' => [],
        ];

        if ($caseContext !== null && $requiredAttachmentTypes !== []) {
            $profile = DocumentProfile::fromConfig($profileKey);
            $attachments['required_attachment_files'] = $this->collectAttachmentFiles(
                $profile,
                $caseContext,
                $requiredAttachmentTypes,
            );
        }

        return $attachments;
    }

    /**
     * Collect attachment files for the specified types.
     *
     * @param array<int, string> $requiredAttachmentTypes
     * @return array<int, array{path: ?string, filename: ?string, type: string, description: string}>
     */
    protected function collectAttachmentFiles(
        DocumentProfile $profile,
        CaseContext $caseContext,
        array $requiredAttachmentTypes,
    ): array {
        if ($requiredAttachmentTypes === []) {
            return [];
        }

        $profile = DocumentProfile::fromArray([
            'key' => $profile->key,
            'name' => $profile->name,
            'recipient' => $profile->recipient,
            'legal_basis' => $profile->legalBasis,
            'tone' => $profile->tone,
            'structure' => $profile->structure,
            'docx_template' => $profile->docxTemplate,
            'requires_attachments' => true,
            'metadata' => array_merge($profile->metadata, [
                'required_attachment_types' => $requiredAttachmentTypes,
            ]),
        ]);

        return $this->attachmentCollector->collect($profile, $caseContext);
    }

    /**
     * Get the linear hierarchy of escalation steps.
     *
     * @return array<int, string>
     */
    public function hierarchy(): array
    {
        return $this->hierarchyList;
    }

    /**
     * Suggest the next step in the linear hierarchy.
     */
    public function suggestNext(?string $current): ?string
    {
        if ($current === null) {
            return $this->hierarchyList[0] ?? null;
        }

        $index = array_search($current, $this->hierarchyList, true);

        if ($index === false) {
            return $this->hierarchyList[0] ?? null;
        }

        return $this->hierarchyList[$index + 1] ?? null;
    }

    /**
     * Check if the current step is the terminal (last) step.
     */
    public function isTerminal(?string $current): bool
    {
        if ($current === null) {
            return false;
        }

        $index = array_search($current, $this->hierarchyList, true);

        return $index !== false && $index === count($this->hierarchyList) - 1;
    }

    /**
     * Build an empty suggestion response.
     *
     * @param string $reason
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    protected function emptySuggestion(string $reason, array $extra = []): array
    {
        return array_merge([
            'next_profile_key' => null,
            'next_profile_name' => null,
            'required_inputs' => [],
            'attachments' => [],
            'reason' => $reason,
        ], $extra);
    }
}
