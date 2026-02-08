<?php

namespace App\Http\Livewire;

use App\Models\JobNotification;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationPanel extends Component
{
    public bool $isOpen = false;

    public array $liveNotifications = [];

    public function toggle(): void
    {
        $this->isOpen = !$this->isOpen;
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    #[On('echo-private:user.{userId}.jobs,job.started')]
    public function handleJobStarted(array $data): void
    {
        $this->addLiveNotification($data);
    }

    #[On('echo-private:user.{userId}.jobs,job.progress')]
    public function handleJobProgress(array $data): void
    {
        $this->updateLiveNotification($data);
    }

    #[On('echo-private:user.{userId}.jobs,job.completed')]
    public function handleJobCompleted(array $data): void
    {
        $this->updateLiveNotification($data);
    }

    #[On('echo-private:user.{userId}.jobs,job.failed')]
    public function handleJobFailed(array $data): void
    {
        $this->updateLiveNotification($data);
    }

    protected function addLiveNotification(array $data): void
    {
        array_unshift($this->liveNotifications, $data);
        $this->liveNotifications = array_slice($this->liveNotifications, 0, 20);
    }

    protected function updateLiveNotification(array $data): void
    {
        $found = false;
        foreach ($this->liveNotifications as $key => $notification) {
            if ($notification['job_id'] === $data['job_id']) {
                $this->liveNotifications[$key] = $data;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $this->addLiveNotification($data);
        }
    }

    public function markAsRead(string $notificationId): void
    {
        $notification = JobNotification::find($notificationId);

        if ($notification && $notification->user_id === auth()->id()) {
            $notification->markAsRead();
        }
    }

    public function markAllAsRead(): void
    {
        JobNotification::forUser(auth()->id())
            ->unread()
            ->update([
                'read' => true,
                'read_at' => now(),
            ]);
    }

    public function dismissLiveNotification(string $jobId): void
    {
        $this->liveNotifications = array_values(array_filter(
            $this->liveNotifications,
            fn ($n) => $n['job_id'] !== $jobId
        ));
    }

    public function clearCompleted(): void
    {
        $this->liveNotifications = array_values(array_filter(
            $this->liveNotifications,
            fn ($n) => !in_array($n['status'], ['completed', 'failed'])
        ));
    }

    #[Computed]
    public function persistedNotifications(): Collection
    {
        return JobNotification::forUser(auth()->id())
            ->recent(7)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function unreadCount(): int
    {
        return JobNotification::forUser(auth()->id())
            ->unread()
            ->count();
    }

    #[Computed]
    public function userId(): int
    {
        return auth()->id();
    }

    public function render()
    {
        return view('livewire.notification-panel');
    }
}
