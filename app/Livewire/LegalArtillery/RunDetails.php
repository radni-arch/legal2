<?php

namespace App\Livewire\LegalArtillery;

use App\Agents\Contracts\LegalArtilleryAgentContract;
use App\Agents\LegalArtilleryAgent;
use App\Models\AgentPromptVersion;
use App\Models\DocumentGenerationRun;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Run Details Component
 *
 * Shows the full details of a specific DocumentGenerationRun including
 * all iterations, scores, improvement deltas, and the final document.
 * Provides editing capability and mandatory approval gate before dispatch.
 * Enforces ownership -- only the run's owner can view details.
 *
 * Includes approval workflow: approve completed runs, then dispatch
 * via email or e-komunikacija channels.
 * Polls for updates when the run is still in progress.
 */
class RunDetails extends Component
{
    public string $runId;

    public ?DocumentGenerationRun $run = null;

    public string $approvalNotes = '';

    public bool $showApprovalForm = false;

    public bool $showDispatchForm = false;

    public bool $showRetryDispatchForm = false;

    public string $dispatchEmail = '';

    public bool $dispatchAsDraft = true;

    public bool $dispatchSendEmail = false;

    public bool $dispatchSubmitEkom = false;

    public string $signatureChoice = 'typed';

    public string $typedSignature = '';

    public string $editableDocument = '';

    public bool $isEditing = false;

    public ?string $saveMessage = null;

    public ?array $dispatchPreview = null;

    public ?string $dispatchPreviewError = null;

    public string $escalationOverrideRung = '';

    public function mount(string $runId): void
    {
        $this->runId = $runId;
        $this->loadRun();
        $this->editableDocument = $this->run->final_document ?? '';
        $this->hydrateSignatureState();
    }

    public function render()
    {
        // Reload if in progress
        if ($this->run && in_array($this->run->status, ['pending', 'running'])) {
            $this->loadRun();
        }

        $promptVersion = null;
        if ($this->run) {
            $promptVersion = $this->run->model_config['prompt_version'] ?? null;

            if (!$promptVersion) {
                $activeVersion = AgentPromptVersion::activeFor("legal-artillery:{$this->run->document_type}");
                if ($activeVersion) {
                    $promptVersion = [
                        'id' => $activeVersion->id,
                        'version' => $activeVersion->version,
                        'agent_name' => $activeVersion->agent_name,
                        'git_hash' => $activeVersion->metadata['git_hash'] ?? null,
                        'saved_at' => $activeVersion->metadata['saved_at'] ?? null,
                        'metadata' => $activeVersion->metadata ?? [],
                    ];
                }
            }
        }

        $iterations = $this->run
            ? $this->run->iterations()->orderBy('iteration_number')->orderBy('phase')->get()
            : collect();

        $iterationPairs = $this->buildIterationPairs($iterations);
        $modelConfig = $this->run?->model_config ?? [];
        $reviewPackData = $this->normalizeReviewPackValue($modelConfig['review_pack'] ?? $modelConfig['reviewPack'] ?? []);
        $reviewPackDrafts = $this->normalizeReviewPackValue(
            $reviewPackData['drafts']
                ?? $reviewPackData['documents']
                ?? $reviewPackData['filings']
                ?? $modelConfig['drafts']
                ?? []
        );
        $reviewPackSourceMap = $this->normalizeReviewPackValue(
            $reviewPackData['source_map']
                ?? $reviewPackData['sourceMap']
                ?? $modelConfig['source_map']
                ?? []
        );
        $reviewPackDrafts = array_values($reviewPackDrafts);
        $reviewPackDrafts = array_slice($reviewPackDrafts, 0, 7);
        $reviewPackAvailableCount = count(array_filter(
            $reviewPackDrafts,
            static fn (array $draft): bool => empty($draft['missing'])
        ));
        $reviewPackCards = [];
        for ($i = 0; $i < 7; $i++) {
            $draft = $reviewPackDrafts[$i] ?? [
                'missing' => true,
                'title' => 'Draft ' . ($i + 1),
            ];
            $draftTitle = $draft['title']
                ?? $draft['name']
                ?? $draft['document_type']
                ?? $draft['label']
                ?? 'Draft ' . ($i + 1);
            $draftContent = $draft['content']
                ?? $draft['draft']
                ?? $draft['text']
                ?? $draft['body']
                ?? null;
            $draftKey = $draft['key'] ?? $draft['id'] ?? $draftTitle;
            $sourceRefs = $draft['source_map']
                ?? $draft['sourceMap']
                ?? ($reviewPackSourceMap[$draftKey] ?? $reviewPackSourceMap[$i] ?? $reviewPackSourceMap[$i + 1] ?? []);
            $reviewPackCards[] = [
                'index' => $i + 1,
                'title' => $draftTitle,
                'content' => $draftContent,
                'missing' => !empty($draft['missing']),
                'source_refs' => $this->normalizeReviewPackSourceRefs($sourceRefs),
            ];
        }

        return view('livewire.legal-artillery.run-details', [
            'iterations' => $iterations,
            'iterationPairs' => $iterationPairs,
            'isActive' => $this->run && in_array($this->run->status, ['pending', 'running']),
            'isApproved' => !empty($this->run?->model_config['approved_at']),
            'hasPendingDispatch' => !empty($this->run?->model_config['pending_dispatch']),
            'docxPath' => $this->run?->model_config['docx_path'] ?? null,
            'completenessCheck' => $this->run?->model_config['completeness_check'] ?? null,
            'promptVersion' => $promptVersion,
            'reviewPackDrafts' => $reviewPackCards,
            'reviewPackAvailableCount' => $reviewPackAvailableCount,
            'reviewPackHasData' => $reviewPackAvailableCount > 0 || !empty($reviewPackSourceMap),
        ]);
    }

    public function startEditing(): void
    {
        $this->isEditing = true;
        $this->saveMessage = null;
    }

    public function cancelEditing(): void
    {
        $this->isEditing = false;
        $this->editableDocument = $this->run->final_document ?? '';
        $this->saveMessage = null;
    }

    public function saveDocument(): void
    {
        if (!$this->run || !$this->run->final_document) {
            return;
        }

        $this->run->update([
            'final_document' => $this->editableDocument,
        ]);

        $this->run = $this->run->fresh();
        $this->isEditing = false;
        $this->saveMessage = 'Dokument je spremljen.';
    }

    public function approveAndDispatch(): void
    {
        if (!$this->run || !$this->run->final_document) {
            return;
        }

        /** @var LegalArtilleryAgent $agent */
        $agent = app(LegalArtilleryAgent::class);
        $agent->approveAndDispatch($this->runId, Auth::id());

        $this->run = $this->run->fresh();
        $this->saveMessage = 'Dokument je odobren i poslan.';
    }

    /**
     * Build paired iteration data: each iteration number maps to its worker + critic phases.
     */
    protected function buildIterationPairs($iterations): array
    {
        $pairs = [];

        foreach ($iterations as $iteration) {
            $num = $iteration->iteration_number;

            if (! isset($pairs[$num])) {
                $pairs[$num] = [
                    'number' => $num,
                    'worker' => null,
                    'critic' => null,
                ];
            }

            if ($iteration->phase === 'worker') {
                $pairs[$num]['worker'] = $iteration;
            } else {
                $pairs[$num]['critic'] = $iteration;
            }
        }

        return array_values($pairs);
    }

    protected function normalizeReviewPackValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    protected function normalizeReviewPackSourceRefs(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            return [$value];
        }

        if ($value === null) {
            return [];
        }

        return [$value];
    }

    public function toggleApprovalForm(): void
    {
        $this->showApprovalForm = ! $this->showApprovalForm;
        $this->showDispatchForm = false;
        $this->dispatchPreview = null;
        $this->dispatchPreviewError = null;
    }

    public function toggleDispatchForm(): void
    {
        $this->showDispatchForm = ! $this->showDispatchForm;
        $this->showApprovalForm = false;
        $this->showRetryDispatchForm = false;
        $this->dispatchPreview = null;
        $this->dispatchPreviewError = null;
        $this->hydrateSignatureState();
    }

    public function toggleRetryDispatchForm(): void
    {
        $this->showRetryDispatchForm = ! $this->showRetryDispatchForm;
        $this->showDispatchForm = false;
        $this->showApprovalForm = false;
        $this->dispatchPreview = null;
        $this->dispatchPreviewError = null;
        $this->hydrateSignatureState();
    }

    public function approveRun(): void
    {
        if (! $this->run || $this->run->status !== 'completed') {
            session()->flash('error', 'Run must be completed before approval.');

            return;
        }

        try {
            $agent = app(LegalArtilleryAgentContract::class);
            $agent->approveRun($this->runId, Auth::id(), $this->approvalNotes ?: null);

            $this->loadRun();
            $this->showApprovalForm = false;
            $this->approvalNotes = '';
            session()->flash('success', 'Dokument je odobren za slanje.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function retryDispatch(): void
    {
        if (!$this->run) {
            session()->flash('error', 'No run loaded.');
            return;
        }

        try {
            if (! $this->persistDispatchSignature(true)) {
                return;
            }

            $agent = app(LegalArtilleryAgentContract::class);
            $agent->retryDispatch(
                runId: $this->runId,
                sendEmail: $this->dispatchSendEmail,
                asDraft: $this->dispatchAsDraft,
                toEmail: $this->dispatchEmail ?: null,
                submitEkom: $this->dispatchSubmitEkom,
            );

            $this->loadRun();
            $this->showRetryDispatchForm = false;
            session()->flash('success', 'Ponovni pokušaj slanja uspješan.');
        } catch (\Exception $e) {
            session()->flash('error', 'Ponovni pokušaj neuspješan: ' . $e->getMessage());
        }
    }

    public function dispatchRun(): void
    {
        if (! $this->run || ! $this->run->isReadyForDispatch()) {
            session()->flash('error', 'Run is not ready for dispatch.');

            return;
        }

        if (! $this->hasDispatchPreviewForSelection()) {
            session()->flash('error', 'Potrebno je napraviti pregled slanja prije slanja dokumenta.');

            return;
        }

        if (! $this->persistDispatchSignature(true)) {
            return;
        }

        try {
            $agent = app(LegalArtilleryAgentContract::class);
            $agent->dispatchApproved(
                runId: $this->runId,
                sendEmail: $this->dispatchSendEmail,
                asDraft: $this->dispatchAsDraft,
                toEmail: $this->dispatchEmail ?: null,
                submitEkom: $this->dispatchSubmitEkom,
            );

            $this->loadRun();
            $this->showDispatchForm = false;
            $this->dispatchPreview = null;
            $this->dispatchPreviewError = null;
            session()->flash('success', 'Dokument je poslan!');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function previewDispatch(): void
    {
        if (! $this->run) {
            session()->flash('error', 'No run loaded.');
            return;
        }

        if (! $this->run->isApproved()) {
            session()->flash('error', 'Dokument mora biti odobren prije pregleda slanja.');
            return;
        }

        if (! $this->validateSignatureChoice()) {
            session()->flash('error', 'Odaberite vazeci digitalni potpis ili unesite potpis.');
            return;
        }

        $this->persistDispatchSignature();

        try {
            $agent = app(LegalArtilleryAgentContract::class);
            $this->dispatchPreview = $agent->previewDispatch(
                runId: $this->runId,
                sendEmail: $this->dispatchSendEmail,
                asDraft: $this->dispatchAsDraft,
                toEmail: $this->dispatchEmail ?: null,
                submitEkom: $this->dispatchSubmitEkom,
            );
            $this->dispatchPreviewError = null;
            $this->loadRun();
        } catch (\Exception $e) {
            $this->dispatchPreview = null;
            $this->dispatchPreviewError = $e->getMessage();
        }
    }

    public function confirmEscalationSuggestion(): void
    {
        if (!$this->run) {
            session()->flash('error', 'No run loaded.');
            return;
        }

        $escalationState = $this->run->getEscalationState();
        $nextRung = $escalationState['next_rung'] ?? null;

        if (!$nextRung) {
            session()->flash('error', 'Nema prijedloga za potvrdu.');
            return;
        }

        try {
            $agent = app(LegalArtilleryAgentContract::class);
            $agent->updateEscalationState($this->runId, [
                'next_rung_confirmed' => true,
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now()->toIso8601String(),
            ]);

            $this->loadRun();
            session()->flash('success', 'Prijedlog eskalacije je potvrden.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function overrideEscalationSuggestion(): void
    {
        if (!$this->run) {
            session()->flash('error', 'No run loaded.');
            return;
        }

        if ($this->escalationOverrideRung === '') {
            session()->flash('error', 'Upisite novu razinu eskalacije.');
            return;
        }

        try {
            $agent = app(LegalArtilleryAgentContract::class);
            $agent->updateEscalationState($this->runId, [
                'next_rung_override' => $this->escalationOverrideRung,
                'next_rung_confirmed' => true,
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now()->toIso8601String(),
            ]);

            $this->loadRun();
            $this->escalationOverrideRung = '';
            session()->flash('success', 'Prijedlog eskalacije je izmijenjen.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    protected function loadRun(): void
    {
        $run = DocumentGenerationRun::with(['iterations', 'context'])->find($this->runId);

        if (! $run) {
            abort(404);
        }

        if ((int) $run->user_id !== Auth::id()) {
            throw new AccessDeniedHttpException('You are not authorized to view this run.');
        }

        $this->run = $run;
    }

    protected function hydrateSignatureState(): void
    {
        if (! $this->run) {
            return;
        }

        $dispatchSignature = $this->run->model_config['dispatch_signature'] ?? null;

        if (is_array($dispatchSignature)) {
            $type = $dispatchSignature['type'] ?? null;

            if ($type === 'typed') {
                $this->signatureChoice = 'typed';
                $this->typedSignature = (string) ($dispatchSignature['value'] ?? '');
                return;
            }

            if ($type === 'digital' && $this->hasDigitalSignature()) {
                $this->signatureChoice = 'digital';
                return;
            }
        }

        $this->signatureChoice = $this->hasDigitalSignature() ? 'digital' : 'typed';
    }

    protected function hasDigitalSignature(): bool
    {
        if (! $this->run) {
            return false;
        }

        $digital = $this->run->model_config['digital_signature'] ?? null;

        if (! is_array($digital)) {
            return false;
        }

        return ! empty($digital['signed_pdf_path'])
            || ! empty($digital['signature'])
            || ! empty($digital['hash']);
    }

    protected function validateSignatureChoice(): bool
    {
        if ($this->signatureChoice === 'digital') {
            return $this->hasDigitalSignature();
        }

        return trim($this->typedSignature) !== '';
    }

    protected function persistDispatchSignature(bool $require = false): bool
    {
        if (! $this->run) {
            return false;
        }

        if (! $this->validateSignatureChoice()) {
            if ($require) {
                session()->flash('error', 'Potpis je obavezan prije slanja.');
            }

            return ! $require;
        }

        $signaturePayload = null;

        if ($this->signatureChoice === 'digital') {
            $digital = $this->run->model_config['digital_signature'] ?? [];

            $signaturePayload = [
                'type' => 'digital',
                'signed_at' => $digital['signed_at'] ?? now()->toIso8601String(),
                'hash' => $digital['hash'] ?? null,
                'hash_algorithm' => $digital['hash_algorithm'] ?? null,
                'signed_pdf_path' => $digital['signed_pdf_path'] ?? null,
                'signed_by' => Auth::id(),
            ];
        } else {
            $signaturePayload = [
                'type' => 'typed',
                'value' => trim($this->typedSignature),
                'signed_at' => now()->toIso8601String(),
                'signed_by' => Auth::id(),
            ];
        }

        $this->run->update([
            'model_config' => array_merge($this->run->model_config ?? [], [
                'dispatch_signature' => $signaturePayload,
            ]),
        ]);

        $this->run = $this->run->fresh();

        return true;
    }

    protected function hasDispatchPreviewForSelection(): bool
    {
        if (! $this->run) {
            return false;
        }

        $preview = $this->run->model_config['dispatch_preview'] ?? null;

        if (! is_array($preview) || empty($preview['previewed_at'])) {
            return false;
        }

        $previewedChannels = $preview['channels'] ?? [];
        $requiredChannels = [];

        if ($this->dispatchSendEmail) {
            $requiredChannels[] = 'email';
        }

        if ($this->dispatchSubmitEkom) {
            $requiredChannels[] = 'ekom';
        }

        foreach ($requiredChannels as $channel) {
            if (! in_array($channel, $previewedChannels, true)) {
                return false;
            }
        }

        return ! empty($requiredChannels);
    }
}
