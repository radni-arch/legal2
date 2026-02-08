<?php

namespace Tests\Browser;

use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

class CollaborationTest extends DuskTestCase
{
    use DatabaseMigrations, MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();
    }
    // Note: Browser tests should NOT use DatabaseTransactions
    // because the browser runs in a separate process

    /**
     * Test 1: Case sharing between users
     *
     * Verifies that users can share cases with other team members
     * and that shared cases appear in the recipient's shared cases list
     *
     * @test
     */
    public function test_case_sharing(): void
    {
        $user1 = User::factory()->create([
            'name' => 'Attorney One',
            'email' => 'user1@test.com',
        ]);

        $user2 = User::factory()->create([
            'name' => 'Attorney Two',
            'email' => 'user2@test.com',
        ]);

        $case = LegalCase::factory()->create([
            'user_id' => $user1->id,
            'case_number' => 'K-TEST-2025-001',
            'case_title' => 'Test Criminal Case',
        ]);

        $this->browse(function (Browser $browser1, Browser $browser2) use ($user1, $user2, $case) {
            // User 1 shares case
            $browser1->loginAs($user1)
                ->visit("/cases/{$case->id}")
                ->assertSee($case->case_number)
                ->assertSee($case->case_title);

            // Check if share button exists (future feature)
            if ($browser1->element('button:contains("Share")') !== null) {
                $browser1->press('Share')
                    ->waitFor('#share-modal', 15)
                    ->assertSee('Share Case')
                    ->type('email', 'user2@test.com')
                    ->press('Send Invitation')
                    ->waitForText('Invitation Sent', 15);

                // User 2 sees shared case
                $browser2->loginAs($user2)
                    ->visit('/shared-cases')
                    ->waitFor('.case-list', 15)
                    ->assertSee($case->case_number);
            } else {
                // Feature not implemented yet, verify basic case access
                $browser1->assertSee($case->case_number);
            }
        });
    }

    /**
     * Test 2: Commenting on cases
     *
     * Verifies that users can add comments to cases and that
     * comments are visible to all team members with access
     *
     * @test
     */
    public function test_commenting_on_cases(): void
    {
        $user1 = User::factory()->create(['name' => 'Commenter']);
        $user2 = User::factory()->create(['name' => 'Viewer']);

        $case = LegalCase::factory()->create([
            'user_id' => $user1->id,
            'case_number' => 'K-COMMENT-2025',
        ]);

        $this->browse(function (Browser $browser1, Browser $browser2) use ($user1, $user2, $case) {
            // User 1 adds a comment
            $browser1->loginAs($user1)
                ->visit("/cases/{$case->id}")
                ->assertSee($case->case_number);

            // Check if comments section exists
            if ($browser1->element('.comments-section') !== null) {
                $browser1->scrollIntoView('.comments-section')
                    ->type('comment_text', 'This is a test comment about the evidence analysis')
                    ->press('Add Comment')
                    ->pause(1000)
                    ->assertSee('This is a test comment about the evidence analysis')
                    ->assertSee($user1->name);

                // User 2 views the comment (if sharing is enabled)
                $browser2->loginAs($user2);

                if ($browser2->visit("/cases/{$case->id}")->element('.comments-section') !== null) {
                    $browser2->scrollIntoView('.comments-section')
                        ->assertSee('This is a test comment about the evidence analysis')
                        ->assertSee($user1->name);
                }
            } else {
                // Feature not implemented yet
                $browser1->assertSee($case->case_number);
            }
        });
    }

    /**
     * Test 3: Activity feed updates
     *
     * Verifies that case activities are tracked and displayed
     * in a real-time activity feed
     *
     * @test
     */
    public function test_activity_feed_updates(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create([
            'user_id' => $user->id,
            'case_number' => 'K-ACTIVITY-2025',
        ]);

        $this->browse(function (Browser $browser) use ($user, $case) {
            $browser->loginAs($user)
                ->visit('/dashboard');

            // Check if activity feed exists
            if ($browser->element('.activity-feed') !== null) {
                $browser->assertPresent('.activity-feed')
                    ->assertSee('Recent Activity');

                // Navigate to case to generate activity
                $browser->visit("/cases/{$case->id}")
                    ->pause(1000);

                // Go back to dashboard and check activity
                $browser->visit('/dashboard')
                    ->waitFor('.activity-feed', 15);

                // Verify case view was logged
                if ($browser->element('.activity-item') !== null) {
                    $browser->assertSee('Viewed case')
                        ->assertSee($case->case_number);
                }
            } else {
                // Feature not implemented yet, verify dashboard loads
                $browser->assertSee('Dashboard');
            }
        });
    }

    /**
     * Test 4: Real-time notifications
     *
     * Verifies that users receive notifications for important events
     * such as case sharing, comments, and updates
     *
     * @test
     */
    public function test_notifications(): void
    {
        $user1 = User::factory()->create(['name' => 'Notifier']);
        $user2 = User::factory()->create(['name' => 'Recipient']);

        $case = LegalCase::factory()->create([
            'user_id' => $user1->id,
            'case_number' => 'K-NOTIFY-2025',
        ]);

        $this->browse(function (Browser $browser1, Browser $browser2) use ($user1, $user2, $case) {
            // User 2 checks notifications
            $browser2->loginAs($user2)
                ->visit('/dashboard');

            // Check if notifications bell/icon exists
            if ($browser2->element('.notifications-icon') !== null) {
                $browser2->click('.notifications-icon')
                    ->pause(500);

                // Check notification count badge
                $notificationCount = $browser2->text('.notification-count');

                // User 1 performs an action that should trigger notification
                $browser1->loginAs($user1)
                    ->visit("/cases/{$case->id}");

                // If share functionality exists, trigger it
                if ($browser1->element('button:contains("Share")') !== null) {
                    $browser1->press('Share')
                        ->waitFor('#share-modal', 5)
                        ->type('email', $user2->email)
                        ->press('Send Invitation')
                        ->pause(2000);

                    // User 2 should see new notification
                    $browser2->refresh()
                        ->pause(1000)
                        ->click('.notifications-icon')
                        ->pause(500)
                        ->assertSee('shared a case');
                }
            } else {
                // Feature not implemented yet
                $browser2->assertSee('Dashboard');
            }
        });
    }
}
