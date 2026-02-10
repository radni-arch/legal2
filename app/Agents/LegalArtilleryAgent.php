<?php

namespace App\Agents;

use App\Agents\Contracts\LegalArtilleryAgentContract;
use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Models\DocumentGenerationRun;
use App\Services\HrLegalCitationsDetector;
use App\Services\LegalArtillery\ArgumentValidator;
use App\Services\LegalArtillery\DigitalSigner;
use App\Services\LegalArtillery\DocxRenderer;
use App\Services\LegalArtillery\EKomunikacijaDispatcher;
use App\Services\LegalArtillery\GmailDispatcher;
use App\Services\LegalArtillery\QualityGate;
use App\Services\LegalArtillery\ResponseHandler;
use Illuminate\Support\Facades\Log;

/**
 * LegalArtilleryAgent
 *
 * Main facade for legal document generation.
 * Combines recursive improvement with profile-based context and output rendering.
 * Implements LegalArtilleryAgentContract for standardized API.
 */
class LegalArtilleryAgent implements LegalArtilleryAgentContract
{
    public function __construct(
        protected LegalArtilleryOrchestrator $orchestrator,
        protected DocxRenderer $renderer,
        protected ?ArgumentValidator $validator = null,
        protected ?GmailDispatcher $gmail = null,
        protected ?EKomunikacijaDispatcher $eKom = null,
        protected ?DigitalSigner $signer = null,
    ) {}

    /**
     * Get all available document profiles
     */
    public function availableProfiles(): array
    {
        $profiles = DocumentProfile::all();
        $result = [];
        foreach ($profiles as $profile) {
            $result[$profile->key] = [
                'name' => $profile->name,
                'forum' => $profile->recipient['institution'] ?? '-',
                'priority' => $profile->metadata['priority'] ?? '-',
                'legal_basis' => $profile->legalBasis,
            ];
        }
        return $result;
    }

    /**
     * Fire a single document generation
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
        bool $signDigitally = false,
        ?string $signingPin = null,
    ): DocumentGenerationRun {
        $profile = DocumentProfile::fromConfig($profileKey);
        $caseContext = CaseContext::fromConfig();

        Log::info('LegalArtilleryAgent: FIRE', ['profile' => $profileKey, 'user' => $userId]);

        // Generate with recursive improvement
        $run = $this->orchestrator->generate(
            $profile,
            $caseContext,
            $userId,
            $maxIterations,
            $additionalContext
        );

        $escalationState = $this->extractEscalationState($additionalContext);
        if ($escalationState !== []) {
            $run = $this->updateEscalationState($run->id, $escalationState);
        }

        if ($run->status !== 'completed' || !$run->final_document) {
            return $run;
        }

        // Render DOCX
        $docxPath = $this->renderer->render($profile, $caseContext, [
            'content' => $run->final_document,
            'sections' => [],
            'generated_at' => now()->toIso8601String(),
        ]);
        $docxVerified = $docxPath && file_exists($docxPath) && is_readable($docxPath);
        if (! $docxVerified) {
            $errorMessage = $docxPath
                ? "DOCX rendering failed: output missing or unreadable at {$docxPath}."
                : 'DOCX rendering failed: no output path returned.';
            $run->update([
                'status' => 'failed',
                'stopped_reason' => 'docx_missing',
                'error_message' => $errorMessage,
                'docx_verified' => false,
                'model_config' => array_merge($run->model_config ?? [], [
                    'docx_path' => $docxPath,
                ]),
            ]);
            Log::error('LegalArtilleryAgent: DOCX missing or unreadable after render', [
                'run_id' => $run->id,
                'docx_path' => $docxPath,
            ]);

            return $run->fresh(['iterations', 'context']);
        }

        $signatureMetadata = null;
        if ($signDigitally && $this->signer) {
            try {
                $signResult = $this->signer->signDocument($docxPath, $signingPin);
                $signedPdfPath = $signResult['signed_pdf'] ?? null;
                $signatureHash = null;
                $hashAlgorithm = 'SHA-256';

                if ($signedPdfPath && file_exists($signedPdfPath)) {
                    $signatureHash = hash_file('sha256', $signedPdfPath);
                }

                $signatureMetadata = [
                    'signed_pdf_path' => $signedPdfPath,
                    'hash' => $signatureHash,
                    'hash_algorithm' => $hashAlgorithm,
                    'signed_at' => $signResult['timestamp'] ?? now()->toIso8601String(),
                    'signature' => $signResult['signature'] ?? null,
                    'certificate' => $signResult['certificate'] ?? null,
                ];
            } catch (\Throwable $e) {
                Log::warning('LegalArtilleryAgent: Digital signing failed', [
                    'run_id' => $run->id,
                    'error' => $e->getMessage(),
                ]);

                $signatureMetadata = [
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toIso8601String(),
                ];
            }
        }

        // Run structural completeness validation
        $completenessCheck = null;
        $sourceMapValidation = null;
        if ($this->validator) {
            $completenessCheck = $this->validator->validateCompleteness(
                $run->final_document,
                $profile,
                $caseContext,
            );

            $run->loadMissing('context');
            $assembledContext = [];
            if ($run->context?->assembled_context) {
                $assembledContext = json_decode($run->context->assembled_context, true) ?? [];
            }

            if (! empty($assembledContext)) {
                $sourceMap = $this->validator->buildSourceMap($assembledContext);
                $sourceMapValidation = $this->validator->validateSourceMap($run->final_document, $sourceMap);

                if (! ($sourceMapValidation['complete'] ?? true)) {
                    Log::warning('LegalArtilleryAgent: Missing required source references', [
                        'run_id' => $run->id,
                        'missing_sources' => $sourceMapValidation['missing_sources'] ?? [],
                    ]);
                }
            }
        }

        // Update run with output path and completeness check — document awaits human approval
        $run->update([
            'model_config' => array_merge($run->model_config ?? [], [
                'docx_path' => $docxPath,
                'completeness_check' => $completenessCheck,
                'source_map_validation' => $sourceMapValidation,
                'digital_signature' => $signatureMetadata,
                'signed_pdf_path' => $signatureMetadata['signed_pdf_path'] ?? null,
            ]),
        ]);

        // MANDATORY APPROVAL GATE: Do NOT dispatch email/e-Komunikacija automatically.
        // Legal documents must be reviewed by a lawyer before sending.
        // Dispatch can only happen via dispatchApproved() after explicit approval.
        Log::info('LegalArtilleryAgent: Document generated, awaiting human approval before dispatch', [
            'run_id' => $run->id,
            'score' => $run->final_score,
            'iterations' => $run->total_iterations,
            'docx_path' => $docxPath,
            'dispatch_requested' => ['email' => $sendEmail, 'ekom' => $submitEkom],
        ]);

        // Store dispatch preferences to canonical DB columns (SOT-005)
        if ($sendEmail || $submitEkom) {
            $run->setSendOptions([
                'send_email' => $sendEmail,
                'as_draft' => $asDraft,
                'to_email' => $toEmail,
            ]);
            $run->update([
                'dispatch_status' => 'pending_dispatch',
            ]);
        }

        return $run->fresh(['iterations', 'context']);
    }

    /**
     * Approve AND dispatch a previously generated document.
     *
     * This is a convenience helper for UIs that want a single click flow.
     * It records approval metadata in model_config and then dispatches using the
     * run's stored pending_dispatch settings.
     *
     * NOTE: This does NOT replace the LegalArtilleryAgentContract API.
     */
    public function approveAndDispatch(string $runId, int $approvedBy): DocumentGenerationRun
    {
        $run = DocumentGenerationRun::findOrFail($runId);
        $docxPath = $run->model_config['docx_path'] ?? null;

        if (! $docxPath || ! $run->final_document) {
            throw new \RuntimeException('Run has no generated document to dispatch.');
        }

        $profile = DocumentProfile::fromConfig($run->document_type);
        $caseContext = CaseContext::fromConfig();
        // Read send options from canonical DB columns (SOT-005)
        $sendOptions = $run->getSendOptions();
        $dispatchPath = $this->resolveDispatchDocumentPath($run);

        $channels = [];
        if (! empty($sendOptions['send_email'])) {
            $channels[] = 'email';
        }

        $this->assertDispatchGate($run, $channels);

        // Mark as approved using canonical DB columns (SOT-005)
        $run->update([
            'approved_at' => now(),
            'approved_by' => $approvedBy,
        ]);

        // Send email if requested (reading from DB columns)
        $sendEmail = (bool) ($sendOptions['send_email'] ?? false);
        if ($sendEmail && $this->gmail && config('legal-artillery.gmail.enabled')) {
            $sendResult = $this->gmail->send(
                $profile,
                $caseContext,
                $dispatchPath,
                $sendOptions['to_email'] ?? null,
                $sendOptions['as_draft'] ?? true,
            );
            $run->update([
                'model_config' => array_merge($run->model_config ?? [], ['email_result' => $sendResult]),
            ]);
        }

        // Mark dispatch status
        $run->markDispatched();

        Log::info('LegalArtilleryAgent: Approved and dispatched (combined helper)', [
            'run_id' => $run->id,
            'approved_by' => $approvedBy,
            'email_sent' => $sendEmail,
            'send_options' => $sendOptions,
        ]);

        return $run->fresh(['iterations', 'context']);
    }

    /**
     * Dispatch a previously approved document via email and/or e-Komunikacija.
     *
     * MUST only be called after a human (lawyer) has reviewed and approved the document.
     */
    public function dispatchApproved(
        string $runId,
        bool $sendEmail = false,
        bool $asDraft = false,
        ?string $toEmail = null,
        bool $submitEkom = false,
    ): DocumentGenerationRun {
        $run = DocumentGenerationRun::findOrFail($runId);

        if (! $run->isReadyForDispatch()) {
            throw new \InvalidArgumentException(
                "Run is not ready for dispatch. Status: {$run->status}, ".
                'Approved: '.($run->isApproved() ? 'yes' : 'no').', '.
                'DOCX verified: '.($run->docx_verified ? 'yes' : 'no')
            );
        }

        $profile = DocumentProfile::fromConfig($run->document_type);
        $caseContext = CaseContext::fromConfig();
        $dispatchPath = $this->resolveDispatchDocumentPath($run);

        $channels = [];
        if ($sendEmail) {
            $channels[] = 'email';
        }
        if ($submitEkom) {
            $channels[] = 'ekom';
        }

        $this->assertDispatchGate($run, $channels);

        // Persist the send options to canonical DB columns before dispatch (SOT-005)
        $run->setSendOptions([
            'send_email' => $sendEmail,
            'as_draft' => $asDraft,
            'to_email' => $toEmail,
        ]);

        $dispatchResult = [];
        $errors = [];

        if ($sendEmail && $this->gmail && config('legal-artillery.gmail.enabled')) {
            try {
                $dispatchResult['email'] = $this->gmail->send($profile, $caseContext, $dispatchPath, $toEmail, $asDraft);
            } catch (\Throwable $e) {
                $errors['email'] = $e->getMessage();
            }
        }

        if ($submitEkom && $this->eKom) {
            try {
                $dispatchResult['ekom'] = $this->eKom->submit($profile, $caseContext, $dispatchPath);
            } catch (\Throwable $e) {
                $errors['ekom'] = $e->getMessage();
            }
        }

        // Store dispatch result in model_config for audit trail
        $run->update([
            'model_config' => array_merge($run->model_config ?? [], [
                'dispatch_result' => $dispatchResult,
            ]),
        ]);

        // Update canonical dispatch status on DB columns (SOT-005)
        if (! empty($errors)) {
            $run->markDispatchFailed(implode('; ', $errors));
        } else {
            $run->markDispatched();
        }

        Log::info('LegalArtilleryAgent: Dispatched approved run', [
            'run_id' => $runId,
            'channels' => array_keys($dispatchResult),
            'errors' => $errors,
        ]);

        return $run->fresh();
    }

    /**
     * Preview a dispatch payload for an approved run without sending.
     *
     * MUST only be called after a human (lawyer) has reviewed and approved the document.
     */
    public function previewDispatch(
        string $runId,
        bool $sendEmail = false,
        bool $asDraft = false,
        ?string $toEmail = null,
        bool $submitEkom = false,
    ): array {
        $run = DocumentGenerationRun::findOrFail($runId);

        if (! $run->isApproved()) {
            throw new \InvalidArgumentException('Run must be approved before previewing dispatch.');
        }

        if ($run->status !== 'completed' || ! $run->final_document) {
            throw new \InvalidArgumentException("Run is not ready for preview. Status: {$run->status}");
        }

        $dispatchPath = $this->resolveDispatchDocumentPath($run);
        if (! $dispatchPath || ! file_exists($dispatchPath) || ! is_readable($dispatchPath)) {
            throw new \InvalidArgumentException('Dispatch document path is missing or unreadable.');
        }

        $profile = DocumentProfile::fromConfig($run->document_type);
        $caseContext = CaseContext::fromConfig();

        $preview = [];

        if ($sendEmail) {
            if (! $this->gmail || ! config('legal-artillery.gmail.enabled')) {
                throw new \InvalidArgumentException('Gmail dispatcher is not available.');
            }

            $emailPreview = $this->gmail->preview($profile, $caseContext, $dispatchPath, $toEmail);

            // Mirror GmailDispatcher::send() logic: force draft when no recipient
            $recipientMissing = empty($emailPreview['to']);
            $effectiveDraft = $asDraft || $recipientMissing;

            $emailPreview['as_draft'] = $effectiveDraft;
            if ($recipientMissing) {
                $emailPreview['recipient_missing'] = true;
            }

            $preview['email'] = $emailPreview;
        }

        if ($submitEkom) {
            if (! $this->eKom) {
                throw new \InvalidArgumentException('E-Komunikacija dispatcher is not available.');
            }

            $ekomPreview = $this->eKom->preview($profile, $caseContext, $dispatchPath);

            if (isset($ekomPreview['payload']['attachments']) && is_array($ekomPreview['payload']['attachments'])) {
                $ekomPreview['payload']['attachments'] = array_map(static function (array $attachment): array {
                    $content = $attachment['content'] ?? null;
                    $attachment['content_present'] = $content !== null;
                    $attachment['content_length'] = $content ? strlen($content) : 0;
                    unset($attachment['content']);

                    return $attachment;
                }, $ekomPreview['payload']['attachments']);
            }

            $preview['ekom'] = $ekomPreview;
        }

        if (empty($preview)) {
            throw new \InvalidArgumentException('Select at least one dispatch channel to preview.');
        }

        $run->update([
            'model_config' => array_merge($run->model_config ?? [], [
                'dispatch_preview' => [
                    'previewed_at' => now()->toIso8601String(),
                    'channels' => array_keys($preview),
                    'options' => [
                        'send_email' => $sendEmail,
                        'as_draft' => $asDraft,
                        'to_email' => $toEmail,
                        'submit_ekom' => $submitEkom,
                    ],
                ],
            ]),
        ]);

        return $preview;
    }

    /**
     * Barrage - fire multiple profiles
     */
    public function barrage(
        ?array $profileKeys = null,
        int $userId = 1,
        bool $sendEmail = false,
        bool $asDraft = true,
        ?array $scenarioProfileKeys = null,
    ): array {
        $defaultScenarioProfiles = config('legal-artillery.profile_sets.pp_prz_74_2025_scenario', [
            'predsjednik_suda',
            'dorh_production',
            'kazneni_sud_motion',
            'ombudsman',
            'ministarstvo_pravosudja',
            'ustavni_sud',
            'izdvajanje_dokaza',
        ]);
        $scenarioKeys = array_values($scenarioProfileKeys ?? $defaultScenarioProfiles);
        $keys = $profileKeys;
        if (empty($keys)) {
            $keys = $scenarioKeys;
        }
        $keys = array_values($keys);
        $results = [];
        foreach ($keys as $key) {
            try {
                $results[$key] = $this->fire($key, $userId, sendEmail: $sendEmail, asDraft: $asDraft);
            } catch (\Exception $e) {
                Log::error("LegalArtilleryAgent: Barrage failed for {$key}", ['error' => $e->getMessage()]);
                $results[$key] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Get a generation run by ID
     */
    public function getRun(string $runId): ?DocumentGenerationRun
    {
        return DocumentGenerationRun::with(['iterations', 'context'])->find($runId);
    }

    /**
     * Approve a generation run for dispatch.
     *
     * Before approval, runs citation mapping on the final document
     * and enforces minimum citation coverage from config.
     *
     * @throws \InvalidArgumentException If run is not completed, has no document, or citation coverage is below threshold
     */
    public function approveRun(string $runId, int $approverId, ?string $notes = null, array $sendOptions = []): DocumentGenerationRun
    {
        $run = DocumentGenerationRun::findOrFail($runId);

        if ($run->status !== 'completed') {
            throw new \InvalidArgumentException("Cannot approve run with status: {$run->status}");
        }

        if (! $run->final_document) {
            throw new \InvalidArgumentException('Cannot approve run without final document');
        }

        // Try to construct config-dependent objects; fall back to stored checks
        $profile = null;
        $caseContext = null;
        $configAvailable = true;

        try {
            $profile = DocumentProfile::fromConfig($run->document_type);
            $caseContext = CaseContext::fromConfig();
        } catch (\Throwable $e) {
            $configAvailable = false;
        }

        $storedQualityScore = $run->model_config['quality_score'] ?? null;
        $storedBlockers = $run->model_config['blockers'] ?? null;
        $storedCompleteness = $run->model_config['completeness_check'] ?? null;

        if ($configAvailable) {
            $qualityGate = app(QualityGate::class);
            $qualityResult = $qualityGate->evaluate($run->final_document, $profile, $caseContext);

            $run->update([
                'model_config' => array_merge($run->model_config ?? [], [
                    'quality_score' => $qualityResult['quality_score'] ?? null,
                    'blockers' => $qualityResult['blockers'] ?? [],
                ]),
            ]);

            if (! ($qualityResult['passed'] ?? false)) {
                $blockers = $qualityResult['blockers'] ?? ['Quality gate failed.'];
                $message = 'Quality gate failed. ' . implode(' ', $blockers);
                throw new \InvalidArgumentException($message);
            }
        } elseif ($storedQualityScore !== null && empty($storedBlockers)) {
            // Config unavailable but stored quality gate passed — proceed
            Log::info('LegalArtilleryAgent: Using stored quality gate result (config unavailable)', [
                'run_id' => $runId,
                'stored_score' => $storedQualityScore,
            ]);
        } else {
            throw new \InvalidArgumentException(
                'Cannot approve run: case context configuration is unavailable and no stored quality gate result exists.'
            );
        }

        $completenessCheck = $run->model_config['completeness_check'] ?? null;
        if (! $completenessCheck) {
            if (! $configAvailable) {
                throw new \InvalidArgumentException(
                    'Cannot approve run: case context configuration is unavailable and no stored completeness check exists.'
                );
            }

            if (! $this->validator) {
                throw new \InvalidArgumentException('Completeness validation unavailable; cannot approve run.');
            }

            $completenessCheck = $this->validator->validateCompleteness($run->final_document, $profile);

            $run->update([
                'model_config' => array_merge($run->model_config ?? [], [
                    'completeness_check' => $completenessCheck,
                ]),
            ]);
        }

        if (! ($completenessCheck['complete'] ?? false)) {
            $missingSections = $completenessCheck['missing_sections'] ?? [];
            $missingMessage = $missingSections
                ? 'Missing sections: ' . implode(', ', $missingSections)
                : 'Missing required sections.';
            throw new \InvalidArgumentException("Cannot approve run. {$missingMessage}");
        }

        if ($this->validator && $configAvailable) {
            $run->loadMissing('context');
            $assembledContext = [];
            if ($run->context?->assembled_context) {
                $assembledContext = json_decode($run->context->assembled_context, true) ?? [];
            }

            $sourceMap = $this->validator->buildSourceMap($assembledContext);
            $sourceMapValidation = $this->validator->validateSourceMap($run->final_document, $sourceMap);

            $run->update([
                'model_config' => array_merge($run->model_config ?? [], [
                    'source_map_validation' => $sourceMapValidation,
                ]),
            ]);

            if (! ($sourceMapValidation['complete'] ?? true)) {
                $missingSources = $sourceMapValidation['missing_sources'] ?? [];
                $message = $missingSources
                    ? 'Missing required source references: ' . implode(', ', $missingSources)
                    : 'Missing required source references.';
                throw new \InvalidArgumentException("Cannot approve run. {$message}");
            }
        }

        // Run citation mapping on the final document
        $responseHandler = app(ResponseHandler::class);
        $citationMap = $responseHandler->mapCitations($run->final_document);

        $paragraphs = preg_split('/\n\s*\n/', $run->final_document);
        $ungroundedParagraphs = [];
        $paragraphNumber = 0;
        $flagPattern = '/\[(no_citation|uncited|citation_not_required)\]/i';

        foreach ($paragraphs as $index => $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            $paragraphNumber++;
            $citations = $citationMap[$index] ?? [];
            $hasCitations = false;

            foreach ($citations as $detected) {
                if (! empty($detected)) {
                    $hasCitations = true;
                    break;
                }
            }

            $isExplicitlyFlagged = preg_match($flagPattern, $paragraph) === 1;

            if (! $hasCitations && ! $isExplicitlyFlagged) {
                $excerpt = mb_substr($paragraph, 0, 160);
                $ungroundedParagraphs[] = "Paragraph {$paragraphNumber} (index {$index}) lacks citations. "
                    . "Excerpt: \"{$excerpt}\"";
            }
        }

        if (! empty($ungroundedParagraphs)) {
            $message = "Ungrounded paragraphs detected. Each non-empty paragraph must include at least one citation "
                . "or be explicitly flagged with [NO_CITATION], [UNCITED], or [CITATION_NOT_REQUIRED].";
            throw new \InvalidArgumentException($message . "\n" . implode("\n", $ungroundedParagraphs));
        }

        // Store citation map in model_config
        $run->update([
            'model_config' => array_merge($run->model_config ?? [], [
                'paragraph_citations' => $citationMap,
            ]),
        ]);

        // Enforce minimum citation coverage
        $minCoverage = (float) config('legal-artillery.grounding.min_citation_coverage', 0.5);

        if ($minCoverage > 0 && !empty($citationMap)) {
            $totalParagraphs = count($citationMap);
            $groundedParagraphs = 0;

            foreach ($citationMap as $citations) {
                foreach ($citations as $type => $detected) {
                    if (!empty($detected)) {
                        $groundedParagraphs++;
                        break;
                    }
                }
            }

            $coverage = $totalParagraphs > 0 ? $groundedParagraphs / $totalParagraphs : 0;

            if ($coverage < $minCoverage) {
                throw new \InvalidArgumentException(
                    "Insufficient citation coverage: {$groundedParagraphs}/{$totalParagraphs} paragraphs grounded " .
                    "(" . round($coverage * 100) . "%). Minimum required: " . round($minCoverage * 100) . "%."
                );
            }
        }

        // Verify DOCX exists
        $docxPath = $run->model_config['docx_path'] ?? null;
        $docxVerified = $docxPath && file_exists($docxPath) && is_readable($docxPath);

        $run->update([
            'approved_at' => now(),
            'approved_by' => $approverId,
            'approval_notes' => $notes,
            'docx_verified' => $docxVerified,
        ]);

        // Persist send options to canonical DB columns (SOT-005)
        if (! empty($sendOptions)) {
            $run->setSendOptions($sendOptions);
        }

        Log::info('LegalArtilleryAgent: Run approved', [
            'run_id' => $runId,
            'approver' => $approverId,
            'docx_verified' => $docxVerified,
            'send_options' => $run->getSendOptions(),
        ]);

        return $run->fresh();
    }

    /**
     * Retry a failed dispatch without regenerating the document.
     */
    public function retryDispatch(
        string $runId,
        bool $sendEmail = false,
        bool $asDraft = false,
        ?string $toEmail = null,
        bool $submitEkom = false,
    ): DocumentGenerationRun {
        $run = DocumentGenerationRun::findOrFail($runId);

        // Must have a DOCX to dispatch
        $docxPath = $run->model_config['docx_path'] ?? null;
        if (!$docxPath || !file_exists($docxPath)) {
            throw new \InvalidArgumentException('DOCX file not found for retry');
        }

        // Must be approved
        if (!$run->isApproved()) {
            throw new \InvalidArgumentException('Run must be approved before dispatch');
        }

        $profile = DocumentProfile::fromConfig($run->document_type);
        $caseContext = CaseContext::fromConfig();
        $dispatchPath = $this->resolveDispatchDocumentPath($run);

        $channels = [];
        if ($sendEmail) {
            $channels[] = 'email';
        }
        if ($submitEkom) {
            $channels[] = 'ekom';
        }

        $this->assertDispatchGate($run, $channels);

        $dispatchResult = [];
        $errors = [];

        if ($sendEmail && $this->gmail && config('legal-artillery.gmail.enabled')) {
            try {
                $dispatchResult['email'] = $this->gmail->send($profile, $caseContext, $dispatchPath, $toEmail, $asDraft);
            } catch (\Exception $e) {
                $errors['email'] = $e->getMessage();
            }
        }

        if ($submitEkom && $this->eKom) {
            try {
                $dispatchResult['ekom'] = $this->eKom->submit($profile, $caseContext, $dispatchPath);
            } catch (\Exception $e) {
                $errors['ekom'] = $e->getMessage();
            }
        }

        // Store dispatch result in model_config for audit trail
        $run->update([
            'model_config' => array_merge($run->model_config ?? [], [
                'dispatch_result' => $dispatchResult,
                'dispatch_errors' => $errors ?: null,
                'dispatch_retry_count' => ($run->model_config['dispatch_retry_count'] ?? 0) + 1,
            ]),
        ]);

        // Update canonical dispatch status on DB columns (SOT-005)
        if (! empty($errors)) {
            $run->markDispatchFailed(implode('; ', $errors));
        } else {
            $run->markDispatched();
        }

        Log::info('LegalArtilleryAgent: Retry dispatch', [
            'run_id' => $runId,
            'channels' => array_keys($dispatchResult),
            'errors' => $errors,
        ]);

        return $run->fresh();
    }

    /**
     * Update escalation metadata for a generation run.
     */
    public function updateEscalationState(string $runId, array $state): DocumentGenerationRun
    {
        $run = DocumentGenerationRun::findOrFail($runId);

        $allowedKeys = [
            'last_action',
            'last_response',
            'next_rung',
            'next_rung_confirmed',
            'next_rung_override',
            'confirmed_by',
            'confirmed_at',
            'updated_at',
        ];

        $filteredState = array_intersect_key($state, array_flip($allowedKeys));
        $filteredState['updated_at'] = now()->toIso8601String();

        $run->updateEscalationState($filteredState);

        return $run->fresh();
    }

    private function resolveDispatchDocumentPath(DocumentGenerationRun $run): string
    {
        $signedPdfPath = $run->model_config['signed_pdf_path'] ?? null;

        if ($signedPdfPath && file_exists($signedPdfPath) && is_readable($signedPdfPath)) {
            return $signedPdfPath;
        }

        return $run->model_config['docx_path'] ?? '';
    }

    private function extractEscalationState(array $additionalContext): array
    {
        $state = [];

        $rawState = $additionalContext['escalation_state'] ?? null;
        if (is_string($rawState)) {
            $decoded = json_decode($rawState, true);
            if (is_array($decoded)) {
                $rawState = $decoded;
            }
        }

        if (is_array($rawState)) {
            $state = $rawState;
        }

        $mappedState = [
            'last_action' => $additionalContext['escalation_last_action']
                ?? $additionalContext['last_action']
                ?? ($state['last_action'] ?? null),
            'last_response' => $additionalContext['escalation_last_response']
                ?? $additionalContext['last_response']
                ?? ($state['last_response'] ?? null),
            'next_rung' => $additionalContext['escalation_next_rung']
                ?? $additionalContext['next_rung']
                ?? ($state['next_rung'] ?? null),
        ];

        return array_filter($mappedState, static fn ($value) => $value !== null && $value !== '');
    }

    private function assertDispatchGate(DocumentGenerationRun $run, array $channels): void
    {
        if (empty($channels)) {
            throw new \InvalidArgumentException('Select at least one dispatch channel.');
        }

        $preview = $run->model_config['dispatch_preview'] ?? null;

        if (! is_array($preview) || empty($preview['previewed_at'])) {
            throw new \InvalidArgumentException('Dispatch preview is required before sending.');
        }

        $previewedChannels = $preview['channels'] ?? [];

        foreach ($channels as $channel) {
            if (! in_array($channel, $previewedChannels, true)) {
                throw new \InvalidArgumentException("Dispatch preview missing for channel: {$channel}.");
            }
        }

        if (! $this->hasRequiredSignature($run)) {
            throw new \InvalidArgumentException('Dispatch requires a digital or typed signature.');
        }
    }

    private function hasRequiredSignature(DocumentGenerationRun $run): bool
    {
        $dispatchSignature = $run->model_config['dispatch_signature'] ?? null;

        if (is_array($dispatchSignature)) {
            $type = $dispatchSignature['type'] ?? null;

            if ($type === 'typed') {
                return ! empty($dispatchSignature['value']);
            }

            if ($type === 'digital') {
                return ! empty($dispatchSignature['signed_pdf_path'])
                    || ! empty($dispatchSignature['signature'])
                    || ! empty($dispatchSignature['hash']);
            }
        }

        $digital = $run->model_config['digital_signature'] ?? null;

        if (! is_array($digital)) {
            return false;
        }

        return ! empty($digital['signed_pdf_path'])
            || ! empty($digital['signature'])
            || ! empty($digital['hash']);
    }
}
