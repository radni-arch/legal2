<?php

namespace App\Jobs;

use App\Actions\Textract\ProcessDrivePdf as ProcessDrivePdfAction;
use App\Jobs\Concerns\HasQueuePriority;
use App\Models\TextractJob;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDrivePdfJob implements ShouldQueue
{
    use BroadcastsJobProgress, Dispatchable, HasQueuePriority, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Job timeout in seconds (10 minutes for large PDFs)
     */
    public $timeout = 600;

    /**
     * Number of times to retry the job
     */
    public $tries = 3;

    protected ?int $userId = null;

    public function __construct(public string $driveFileId, public string $driveFileName, ?int $userId = null)
    {
        $this->userId = $userId ?? auth()->id();
        // AWS Textract OCR processing - use dedicated textract queue
        $this->onTextractQueue();
    }

    public function getJobDisplayName(): string
    {
        return 'Processing PDF: ' . $this->driveFileName;
    }

    public function handle(): void
    {
        $broadcastJobId = 'pdf_' . $this->driveFileId;

        // Check if Textract circuit breaker is open before processing
        $textractService = app(\App\Services\TextractService::class);
        $circuitBreaker = $textractService->getCircuitBreaker();

        // Check circuit breaker state
        $status = $circuitBreaker->getStatus();
        if ($status['state'] === 'open') {
            \Log::warning('Textract circuit breaker open, releasing job for retry', [
                'drive_file_id' => $this->driveFileId,
                'circuit_state' => $status['state'],
                'wait_seconds' => 60,
            ]);

            // Release job back to queue for 60 seconds
            $this->release(60);

            return;
        }

        try {
            if ($this->userId) {
                $this->broadcastStarted($this->userId, $broadcastJobId, [
                    'file_name' => $this->driveFileName,
                ]);
            }

            // Delegate to the Action orchestrator via container to ease testing/mocking
            app(ProcessDrivePdfAction::class)->handle($this->driveFileId, $this->driveFileName, false);

            if ($this->userId) {
                $this->broadcastCompleted($this->userId, $broadcastJobId, [
                    'file_name' => $this->driveFileName,
                ]);
            }

        } catch (\Exception $e) {
            if ($this->userId) {
                $this->broadcastFailed($this->userId, $broadcastJobId, $e->getMessage(), 'PDF Processing');
            }

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        TextractJob::where('drive_file_id', $this->driveFileId)->update([
            'status' => 'failed',
            'error' => $e->getMessage(),
        ]);

        if ($this->userId) {
            $this->broadcastFailed(
                $this->userId,
                'pdf_' . $this->driveFileId,
                $e->getMessage(),
                'Permanently Failed',
                ['file_name' => $this->driveFileName]
            );
        }
    }
}
