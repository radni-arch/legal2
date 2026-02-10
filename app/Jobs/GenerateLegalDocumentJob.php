<?php

namespace App\Jobs;

use App\Agents\LegalArtilleryOrchestrator;
use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Jobs\Concerns\HasQueuePriority;
use App\Models\DocumentGenerationRun;
use App\Services\LegalArtillery\DocxRenderer;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateLegalDocumentJob implements ShouldQueue
{
    use BroadcastsJobProgress, Dispatchable, HasQueuePriority, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public readonly string $runId,
        public readonly string $profileKey,
        public readonly int $userId,
        public readonly int $maxIterations = 5,
        public readonly bool $sendEmail = false,
        public readonly bool $asDraft = true,
        public readonly ?string $toEmail = null,
        public readonly ?string $caseId = null,
        public readonly array $evidenceIds = [],
        public readonly bool $confirmEscalation = false,
        public readonly ?array $sendOptions = null,
    ) {
        $this->onAgentsQueue();
    }

    public function getJobDisplayName(): string
    {
        return "Legal Artillery: {$this->profileKey}";
    }

    public function handle(LegalArtilleryOrchestrator $orchestrator): void
    {
        $run = DocumentGenerationRun::findOrFail($this->runId);

        // Persist send options to canonical DB columns (SOT-006)
        if ($this->sendOptions !== null) {
            $run->setSendOptions($this->sendOptions);
            if (! empty($this->sendOptions['send_email'])) {
                $run->update(['dispatch_status' => 'pending_dispatch']);
            }
        }

        $this->broadcastStarted($this->userId, $this->runId, [
            'profile' => $this->profileKey,
            'max_iterations' => $this->maxIterations,
        ]);

        try {
            $profile = DocumentProfile::fromConfig($this->profileKey);
            $caseContext = CaseContext::fromConfig();

            $orchestrator->generate(
                profile: $profile,
                caseContext: $caseContext,
                userId: $this->userId,
                maxIterations: $this->maxIterations,
                run: $run,
                additionalContext: array_filter([
                    'case_id' => $this->caseId,
                    'evidence_ids' => $this->evidenceIds,
                    'escalation_confirmed' => $this->confirmEscalation,
                ], fn ($value) => $value !== null && $value !== '' && $value !== []),
            );

            $run->refresh();

            // Render DOCX if completed
            if ($run->status === 'completed' && $run->final_document) {
                try {
                    $renderer = app(DocxRenderer::class);
                    $docxPath = $renderer->render($profile, $caseContext, [
                        'content' => $run->final_document,
                        'sections' => [],
                        'generated_at' => now()->toIso8601String(),
                    ]);

                    $run->update([
                        'model_config' => array_merge($run->model_config ?? [], ['docx_path' => $docxPath]),
                    ]);
                } catch (\Exception $e) {
                    Log::warning('GenerateLegalDocumentJob: DOCX render failed', [
                        'run_id' => $this->runId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->broadcastCompleted($this->userId, $this->runId, [
                'final_score' => $run->final_score,
                'total_iterations' => $run->total_iterations,
                'stopped_reason' => $run->stopped_reason,
            ]);

        } catch (\Exception $e) {
            Log::error('GenerateLegalDocumentJob failed', [
                'run_id' => $this->runId,
                'error' => $e->getMessage(),
            ]);

            $run->update([
                'status' => 'failed',
                'stopped_reason' => 'error: '.substr($e->getMessage(), 0, 200),
            ]);

            $this->broadcastFailed(
                $this->userId,
                $this->runId,
                $e->getMessage(),
                'generation',
            );

            throw $e;
        }
    }
}
