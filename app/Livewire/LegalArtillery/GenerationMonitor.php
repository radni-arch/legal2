<?php

namespace App\Livewire\LegalArtillery;

use App\Models\DocumentGenerationRun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Generation Monitor Component
 *
 * Real-time monitoring of an active document generation run.
 * Polls for status updates and displays current iteration progress,
 * scores, and status changes. Stops polling when run completes or fails.
 */
class GenerationMonitor extends Component
{
    public string $runId;

    public string $documentType = '';

    public string $status = 'running';

    public ?float $currentScore = null;

    public ?int $currentIteration = null;

    public ?string $stoppedReason = null;

    public ?string $errorMessage = null;

    public array $iterations = [];

    public ?int $userId = null;

    public function mount(string $runId): void
    {
        $this->runId = $runId;
        $this->userId = Auth::id();
        $this->loadRun();
    }

    public function render()
    {
        return view('livewire.legal-artillery.generation-monitor');
    }

    public function getListeners(): array
    {
        $userId = $this->userId ?? Auth::id();

        if (! $userId || ! $this->runId) {
            return [];
        }

        return [
            "echo-private:user.{$userId}.jobs,job.progress" => 'handleJobProgress',
        ];
    }

    public function handleJobProgress(array $data): void
    {
        if (($data['job_id'] ?? null) !== $this->runId) {
            return;
        }

        if (($data['status'] ?? null) === 'in_progress') {
            $this->status = 'running';
        }

        $metadata = $data['metadata'] ?? [];

        if (array_key_exists('iteration', $metadata)) {
            $this->currentIteration = (int) $metadata['iteration'];
        }

        if (array_key_exists('score', $metadata)) {
            $this->currentScore = $metadata['score'] !== null ? (float) $metadata['score'] : null;
        }

        if (array_key_exists('iteration', $metadata)) {
            $iterationNumber = (int) $metadata['iteration'];
            $timestamp = $data['timestamp'] ?? null;
            $iterationData = [
                'number' => $iterationNumber,
                'phase' => 'critic',
                'score' => array_key_exists('score', $metadata) && $metadata['score'] !== null
                    ? (float) $metadata['score']
                    : null,
                'delta' => array_key_exists('delta', $metadata) && $metadata['delta'] !== null
                    ? (float) $metadata['delta']
                    : null,
                'created_at' => $timestamp
                    ? Carbon::parse($timestamp)->format('H:i:s')
                    : now()->format('H:i:s'),
            ];

            $existingIndex = null;
            foreach ($this->iterations as $index => $iteration) {
                if (($iteration['number'] ?? null) === $iterationNumber
                    && ($iteration['phase'] ?? null) === 'critic') {
                    $existingIndex = $index;
                    break;
                }
            }

            if ($existingIndex !== null) {
                $this->iterations[$existingIndex] = array_merge(
                    $this->iterations[$existingIndex],
                    $iterationData
                );
            } else {
                $this->iterations[] = $iterationData;
            }

            usort($this->iterations, fn ($a, $b) => ($a['number'] ?? 0) <=> ($b['number'] ?? 0));
        }
    }

    public function checkStatus(): void
    {
        $this->loadRun();
    }

    protected function loadRun(): void
    {
        $run = DocumentGenerationRun::with('iterations')
            ->where('id', $this->runId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $run) {
            return;
        }

        $this->userId = $run->user_id;
        $this->documentType = $run->document_type;
        $this->status = $run->status;
        $this->currentScore = $run->final_score ? (float) $run->final_score : null;
        $this->currentIteration = $run->total_iterations;
        $this->stoppedReason = $run->stopped_reason;
        $this->errorMessage = $run->error_message;
        $this->iterations = $run->iterations
            ->sortBy('iteration_number')
            ->map(fn ($iter) => [
                'number' => $iter->iteration_number,
                'phase' => $iter->phase,
                'score' => $iter->weighted_score ? (float) $iter->weighted_score : null,
                'delta' => $iter->improvement_delta ? (float) $iter->improvement_delta : null,
                'created_at' => $iter->created_at?->format('H:i:s'),
            ])
            ->values()
            ->toArray();
    }
}
