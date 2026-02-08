<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\NotificationPanel;
use App\Models\JobNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(NotificationPanel::class)
            ->assertStatus(200);
    }

    public function test_shows_user_notifications(): void
    {
        $user = User::factory()->create();

        JobNotification::create([
            'user_id' => $user->id,
            'job_id' => 'job-123',
            'job_type' => 'ProcessTextractJob',
            'job_name' => 'Processing test.pdf',
            'status' => 'completed',
        ]);

        Livewire::actingAs($user)
            ->test(NotificationPanel::class)
            ->assertSee('Processing test.pdf')
            ->assertSee('Completed');
    }

    public function test_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();

        $notification = JobNotification::create([
            'user_id' => $user->id,
            'job_id' => 'job-123',
            'job_type' => 'ProcessTextractJob',
            'job_name' => 'Processing test.pdf',
            'status' => 'completed',
            'read' => false,
        ]);

        Livewire::actingAs($user)
            ->test(NotificationPanel::class)
            ->call('markAsRead', $notification->id);

        $this->assertTrue($notification->fresh()->read);
    }

    public function test_can_mark_all_as_read(): void
    {
        $user = User::factory()->create();

        JobNotification::create([
            'user_id' => $user->id,
            'job_id' => 'job-1',
            'job_type' => 'ProcessTextractJob',
            'job_name' => 'Doc 1',
            'status' => 'completed',
            'read' => false,
        ]);

        JobNotification::create([
            'user_id' => $user->id,
            'job_id' => 'job-2',
            'job_type' => 'ProcessTextractJob',
            'job_name' => 'Doc 2',
            'status' => 'failed',
            'read' => false,
        ]);

        Livewire::actingAs($user)
            ->test(NotificationPanel::class)
            ->call('markAllAsRead');

        $this->assertEquals(0, JobNotification::forUser($user->id)->unread()->count());
    }

    public function test_can_toggle_panel(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(NotificationPanel::class)
            ->assertSet('isOpen', false)
            ->call('toggle')
            ->assertSet('isOpen', true)
            ->call('toggle')
            ->assertSet('isOpen', false);
    }

    public function test_shows_unread_count(): void
    {
        $user = User::factory()->create();

        JobNotification::create([
            'user_id' => $user->id,
            'job_id' => 'job-1',
            'job_type' => 'ProcessTextractJob',
            'job_name' => 'Doc 1',
            'status' => 'completed',
            'read' => false,
        ]);

        JobNotification::create([
            'user_id' => $user->id,
            'job_id' => 'job-2',
            'job_type' => 'ProcessTextractJob',
            'job_name' => 'Doc 2',
            'status' => 'completed',
            'read' => false,
        ]);

        Livewire::actingAs($user)
            ->test(NotificationPanel::class)
            ->assertSee('2');
    }
}
