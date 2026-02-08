<?php

namespace App\Notifications;

use App\Models\TextractJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Task 3.2: External Notifications
 *
 * Notification for Textract pipeline events.
 * Supports database and mail channels.
 */
class TextractPipelineNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TextractJob $job,
        public string $event,
        public ?string $message = null
    ) {}

    public function via($notifiable): array
    {
        $channels = ['database'];

        if (config('textract.notifications.mail.enabled', false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->getSubject())
            ->line($this->getMessage())
            ->action('View Job', url("/textract/jobs/{$this->job->id}"));

        if ($this->event === 'failed') {
            $mail->error();
        } else {
            $mail->success();
        }

        return $mail;
    }

    public function toArray($notifiable): array
    {
        return [
            'textract_job_id' => $this->job->id,
            'file_name' => $this->job->drive_file_name,
            'event' => $this->event,
            'message' => $this->getMessage(),
            'case_id' => $this->job->case_id,
        ];
    }

    private function getSubject(): string
    {
        return match ($this->event) {
            'completed' => 'Textract Processing Complete',
            'embedding_complete' => 'Embeddings Generated',
            'graph_synced' => 'Graph Sync Complete',
            'failed' => 'Textract Processing Failed',
            default => 'Textract Pipeline Update',
        };
    }

    private function getMessage(): string
    {
        if ($this->message) {
            return $this->message;
        }

        return match ($this->event) {
            'completed' => "Document processing completed for job #{$this->job->id}",
            'embedding_complete' => "Embeddings generated for job #{$this->job->id}",
            'graph_synced' => "Graph sync completed for job #{$this->job->id}",
            'failed' => "Processing failed for job #{$this->job->id}",
            default => "Pipeline update for job #{$this->job->id}",
        };
    }
}
