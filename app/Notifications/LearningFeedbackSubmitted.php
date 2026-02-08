<?php

namespace App\Notifications;

use App\Models\LearningOpportunity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Learning Feedback Submitted Notification
 *
 * Sprint 5.2: Human Feedback Integration
 *
 * Notifies AI team when attorney submits feedback on a learning opportunity.
 */
class LearningFeedbackSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public LearningOpportunity $opportunity
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $aiTeamEmail = config('mail.ai_team_email', 'ai-team@example.com');

        return (new MailMessage)
            ->to($aiTeamEmail)
            ->subject('New Learning Feedback Submitted - '.$this->opportunity->opportunity_type)
            ->greeting('New Learning Feedback Received')
            ->line('An attorney has provided feedback on a learning opportunity.')
            ->line('**Opportunity Type:** '.$this->opportunity->opportunity_type)
            ->line('**Source:** '.$this->opportunity->source_type)
            ->line('**AI Confidence:** '.($this->opportunity->confidence_score * 100).'%')
            ->line('**Reviewed By:** '.$notifiable->name.' ('.$notifiable->email.')')
            ->action('View Learning Opportunity', url('/learning-opportunities/'.$this->opportunity->id))
            ->line('This feedback will help improve our AI models.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'opportunity_id' => $this->opportunity->id,
            'opportunity_type' => $this->opportunity->opportunity_type,
            'confidence_score' => $this->opportunity->confidence_score,
            'reviewed_at' => $this->opportunity->reviewed_at?->toISOString(),
        ];
    }
}
