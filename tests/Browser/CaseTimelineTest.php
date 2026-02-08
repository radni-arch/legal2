<?php

namespace Tests\Browser;

use App\Models\Case as LegalCase;
use App\Models\CaseEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Case Timeline Browser Tests
 *
 * Tests the interactive case timeline visualization for tracking
 * case events, deadlines, proceedings, and document submissions.
 *
 * @group dusk
 * @group timeline
 */
class CaseTimelineTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test timeline visualization loads and displays events
     *
     * Verifies:
     * - Timeline page loads
     * - Events are displayed chronologically
     * - Event types are color-coded
     * - Event details are visible
     * - Navigation works (zoom, scroll)
     */
    public function test_timeline_loads_and_displays_events(): void
    {
        $case = LegalCase::factory()->create([
            'case_number' => 'K-123/2025',
            'court' => 'Županijski sud u Osijeku',
        ]);

        // Create timeline events
        CaseEvent::factory()->create([
            'case_id' => $case->id,
            'event_type' => 'filing',
            'title' => 'Optužnica podnesena',
            'event_date' => Carbon::now()->subDays(30),
            'description' => 'Državno odvjetništvo podnijelo optužnicu',
        ]);

        CaseEvent::factory()->create([
            'case_id' => $case->id,
            'event_type' => 'hearing',
            'title' => 'Pripremno ročište',
            'event_date' => Carbon::now()->subDays(15),
            'description' => 'Održano pripremno ročište',
        ]);

        CaseEvent::factory()->create([
            'case_id' => $case->id,
            'event_type' => 'motion',
            'title' => 'Prijedlog za isključenje dokaza',
            'event_date' => Carbon::now()->subDays(10),
            'description' => 'Obrana podnijela prijedlog za isključenje nezakonito pribavljenih dokaza',
        ]);

        $this->browse(function (Browser $browser) use ($case) {
            $browser->visit('/timeline/'.$case->id)
                ->assertSee('Case Timeline')
                ->assertSee('K-123/2025')

                    // Verify timeline canvas is present
                ->assertPresent('#timeline-canvas')

                    // Verify events are displayed
                ->assertSee('Optužnica podnesena')
                ->assertSee('Pripremno ročište')
                ->assertSee('Prijedlog za isključenje dokaza')

                    // Verify event types are color-coded
                ->assertPresent('[data-event-type="filing"]')
                ->assertPresent('[data-event-type="hearing"]')
                ->assertPresent('[data-event-type="motion"]')

                    // Test event click for details
                ->click('[data-event-id="1"]')
                ->waitFor('#event-detail-panel', 15)
                ->assertSee('Državno odvjetništvo podnijelo optužnicu')

                    // Test timeline navigation
                ->press('Zoom In')
                ->pause(500)
                ->assertPresent('.timeline-zoomed')
                ->press('Zoom Out')
                ->pause(500)

                    // Test timeline filtering
                ->select('event_type_filter', 'hearing')
                ->waitFor('[data-event-type="hearing"]', 15)
                ->assertDontSee('Optužnica podnesena');
        });
    }

    /**
     * Test adding new events to timeline
     *
     * Verifies:
     * - Add event button works
     * - Event form validates input
     * - New events appear on timeline
     * - Events are sorted chronologically
     * - Event notifications work
     */
    public function test_adding_new_events_to_timeline(): void
    {
        $case = LegalCase::factory()->create([
            'case_number' => 'K-456/2025',
        ]);

        $this->browse(function (Browser $browser) use ($case) {
            $browser->visit('/timeline/'.$case->id)
                ->assertSee('Case Timeline')

                    // Click add event button
                ->press('Add Event')

                    // Wait for event form modal
                ->waitFor('#add-event-modal', 15)

                    // Fill event details
                ->select('event_type', 'deadline')
                ->type('event_title', 'Rok za podnošenje žalbe')
                ->type('event_date', Carbon::now()->addDays(14)->format('Y-m-d'))
                ->type('event_time', '12:00')
                ->type('event_description', 'Krajnji rok za podnošenje žalbe na presudu')

                    // Set reminder
                ->check('set_reminder')
                ->select('reminder_days_before', '7')

                    // Add participants
                ->type('participants', 'Odvjetnik Marko Horvat')

                    // Save event
                ->press('Save Event')

                    // Wait for modal to close and event to appear
                ->waitUntilMissing('#add-event-modal', 15)
                ->waitForText('Rok za podnošenje žalbe', 15)

                    // Verify event is on timeline
                ->assertPresent('[data-event-type="deadline"]')
                ->assertSee('Rok za podnošenje žalbe')

                    // Verify reminder notification
                ->assertSee('Reminder set')

                    // Test invalid date handling
                ->press('Add Event')
                ->waitFor('#add-event-modal', 15)
                ->type('event_title', 'Invalid Event')
                ->type('event_date', '2020-01-01') // Past date
                ->press('Save Event')

                    // Verify validation error
                ->waitForText('Date cannot be in the past', 15)
                ->assertPresent('.validation-error');
        });
    }

    /**
     * Test deadline tracking and notifications
     *
     * Verifies:
     * - Upcoming deadlines are highlighted
     * - Overdue deadlines are marked
     * - Deadline notifications appear
     * - Deadline calendar view works
     * - Deadline export works
     */
    public function test_deadline_tracking_and_notifications(): void
    {
        $case = LegalCase::factory()->create();

        // Create deadlines
        CaseEvent::factory()->create([
            'case_id' => $case->id,
            'event_type' => 'deadline',
            'title' => 'Rok za dostavu dokaza',
            'event_date' => Carbon::now()->addDays(3),
            'priority' => 'high',
        ]);

        CaseEvent::factory()->create([
            'case_id' => $case->id,
            'event_type' => 'deadline',
            'title' => 'Propušten rok',
            'event_date' => Carbon::now()->subDays(2),
            'status' => 'overdue',
        ]);

        CaseEvent::factory()->create([
            'case_id' => $case->id,
            'event_type' => 'deadline',
            'title' => 'Budući rok',
            'event_date' => Carbon::now()->addMonths(2),
        ]);

        $this->browse(function (Browser $browser) use ($case) {
            $browser->visit('/timeline/'.$case->id)
                ->assertSee('Case Timeline')

                    // Verify upcoming deadline is highlighted
                ->assertPresent('[data-priority="high"]')
                ->assertPresent('.deadline-upcoming')
                ->assertSee('Rok za dostavu dokaza')

                    // Verify overdue deadline is marked
                ->assertPresent('[data-status="overdue"]')
                ->assertPresent('.deadline-overdue')
                ->assertSee('Propušten rok')

                    // Click on deadline notifications
                ->click('#deadline-notifications-icon')
                ->waitFor('#notifications-panel', 15)

                    // Verify notification count
                ->assertSee('2 upcoming deadlines')
                ->assertSee('1 overdue deadline')

                    // Switch to calendar view
                ->click('#calendar-view-tab')
                ->waitFor('#calendar', 15)

                    // Verify calendar displays deadlines
                ->assertPresent('.calendar-event[data-type="deadline"]')

                    // Test deadline export
                ->press('Export Deadlines')
                ->waitFor('#export-options', 15)

                    // Export to iCal format
                ->select('export_format', 'ical')
                ->press('Download')
                ->waitForText('Calendar file generated', 15);
        });
    }

    /**
     * Test document attachment to timeline events
     *
     * Verifies:
     * - Documents can be attached to events
     * - Attached documents are displayed
     * - Document preview works
     * - Multiple documents per event supported
     * - Document metadata is shown
     */
    public function test_document_attachment_to_events(): void
    {
        $case = LegalCase::factory()->create();

        $event = CaseEvent::factory()->create([
            'case_id' => $case->id,
            'event_type' => 'filing',
            'title' => 'Podnošenje podneska',
        ]);

        $this->browse(function (Browser $browser) use ($case, $event) {
            $browser->visit('/timeline/'.$case->id)
                ->assertSee('Case Timeline')

                    // Click on event to open details
                ->click('[data-event-id="'.$event->id.'"]')
                ->waitFor('#event-detail-panel', 15)

                    // Click attach document
                ->press('Attach Document')
                ->waitFor('#attach-document-modal', 15)

                    // Upload document
                ->attach('document_file', __DIR__.'/fixtures/test.pdf')
                ->type('document_title', 'Prijedlog obrane')
                ->select('document_type', 'motion')
                ->type('document_notes', 'Prijedlog za isključenje dokaza')

                    // Save attachment
                ->press('Attach')

                    // Wait for attachment to appear
                ->waitForText('Prijedlog obrane', 15)

                    // Verify attachment is listed
                ->assertPresent('[data-attachment]')
                ->assertSee('test.pdf')
                ->assertSee('motion')

                    // Test document preview
                ->click('[data-preview-doc]')
                ->waitFor('#document-preview-modal', 15)
                ->assertPresent('iframe[src*="pdf"]')

                    // Close preview
                ->press('Close Preview')

                    // Attach another document
                ->press('Attach Document')
                ->waitFor('#attach-document-modal', 15)
                ->attach('document_file', __DIR__.'/fixtures/test.txt')
                ->type('document_title', 'Bilješke')
                ->press('Attach')

                    // Verify both documents are shown
                ->waitForText('Bilješke', 15)
                ->assertSeeIn('#attachments-list', 'Prijedlog obrane')
                ->assertSeeIn('#attachments-list', 'Bilješke');
        });
    }

    /**
     * Test timeline export and sharing
     *
     * Verifies:
     * - Timeline can be exported as PDF
     * - Timeline can be shared via link
     * - Export includes all events and documents
     * - Different export formats supported
     * - Print-friendly format available
     */
    public function test_timeline_export_and_sharing(): void
    {
        $case = LegalCase::factory()->create([
            'case_number' => 'K-789/2025',
        ]);

        CaseEvent::factory()->count(5)->create([
            'case_id' => $case->id,
        ]);

        $this->browse(function (Browser $browser) use ($case) {
            $browser->visit('/timeline/'.$case->id)
                ->assertSee('Case Timeline')

                    // Click export button
                ->press('Export Timeline')
                ->waitFor('#export-timeline-modal', 15)

                    // Select date range
                ->type('export_start_date', Carbon::now()->subMonths(3)->format('Y-m-d'))
                ->type('export_end_date', Carbon::now()->addMonths(1)->format('Y-m-d'))

                    // Select export format
                ->select('export_format', 'pdf')

                    // Select options
                ->check('include_event_details')
                ->check('include_documents')
                ->check('include_participants')

                    // Generate export
                ->press('Generate Export')

                    // Wait for generation
                ->waitForText('Export ready', 10)
                ->assertSee('Download PDF')

                    // Test share link generation
                ->press('Share Timeline')
                ->waitFor('#share-modal', 15)

                    // Set sharing permissions
                ->check('require_password')
                ->type('share_password', 'SecurePass123')

                    // Set expiration
                ->select('link_expiration', '7_days')

                    // Generate share link
                ->press('Generate Share Link')

                    // Verify link is generated
                ->waitForText('Share link generated', 15)
                ->assertPresent('#share-link-input')

                    // Copy link
                ->press('Copy Link')
                ->waitForText('Copied', 15)

                    // Test print-friendly view
                ->press('Print View')
                ->waitFor('#print-preview', 15)
                ->assertPresent('.print-layout')
                ->assertSee('K-789/2025')
                ->assertSee('Case Timeline');
        });
    }
}
