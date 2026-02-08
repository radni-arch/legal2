<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\TimelinePage;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Comprehensive Test Suite for TimelinePage Component
 *
 * Tests all functionality:
 * - Component mounting and initialization
 * - Timeline data structure validation
 * - Timeline JSON validity
 * - Event structure and required fields
 * - Event groups and categorization
 * - Date format validation
 * - View rendering
 * - Timeline configuration
 *
 * Coverage:
 * - Component mounting with timeline data
 * - Timeline JSON structure (title, events, scale)
 * - Event fields (start_date, text, group, unique_id)
 * - Group categorization
 * - Date format validation
 * - View rendering with injected JavaScript
 */
class TimelinePageTest extends TestCase
{
    use UsesTestDatabase;
    // ========================================
    // Component Mounting & Initialization Tests
    // ========================================

    /** @test */
    public function it_can_mount_and_display_the_component()
    {
        Livewire::test(TimelinePage::class)
            ->assertStatus(200)
            ->assertSet('timelineJs', function ($timelineJs) {
                return ! empty($timelineJs);
            });
    }

    /** @test */
    public function it_sets_timeline_js_property_on_mount()
    {
        $component = Livewire::test(TimelinePage::class);

        $this->assertNotEmpty($component->get('timelineJs'));
        $this->assertIsString($component->get('timelineJs'));
    }

    /** @test */
    public function it_renders_the_correct_view()
    {
        Livewire::test(TimelinePage::class)
            ->assertViewIs('livewire.timeline-page');
    }

    /** @test */
    public function it_passes_timeline_js_to_view()
    {
        Livewire::test(TimelinePage::class)
            ->assertViewHas('timelineJs')
            ->assertSee('timeline-embed', false);
    }

    // ========================================
    // Timeline JSON Structure Tests
    // ========================================

    /** @test */
    public function timeline_js_is_valid_json()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');

        $decoded = json_decode($timelineJs, true);

        $this->assertNotNull($decoded, 'Timeline JSON should be valid');
        $this->assertIsArray($decoded);
    }

    /** @test */
    public function timeline_json_has_required_top_level_fields()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        $this->assertArrayHasKey('scale', $decoded);
        $this->assertArrayHasKey('title', $decoded);
        $this->assertArrayHasKey('events', $decoded);
    }

    /** @test */
    public function timeline_has_correct_scale_value()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        $this->assertEquals('human', $decoded['scale']);
    }

    /** @test */
    public function timeline_title_has_text_object()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        $this->assertArrayHasKey('text', $decoded['title']);
        $this->assertArrayHasKey('headline', $decoded['title']['text']);
        $this->assertArrayHasKey('text', $decoded['title']['text']);
    }

    /** @test */
    public function timeline_title_has_headline_content()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        $this->assertNotEmpty($decoded['title']['text']['headline']);
        $this->assertStringContainsString('Pp Prz-74/2025', $decoded['title']['text']['headline']);
    }

    /** @test */
    public function timeline_has_events_array()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        $this->assertIsArray($decoded['events']);
        $this->assertNotEmpty($decoded['events']);
    }

    // ========================================
    // Event Structure Tests
    // ========================================

    /** @test */
    public function timeline_has_multiple_events()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        $this->assertGreaterThan(10, count($decoded['events']), 'Timeline should have more than 10 events');
    }

    /** @test */
    public function all_events_have_start_date()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        foreach ($decoded['events'] as $event) {
            $this->assertArrayHasKey('start_date', $event, 'Event should have start_date field');
            $this->assertIsArray($event['start_date']);
        }
    }

    /** @test */
    public function start_dates_have_required_fields()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        foreach ($decoded['events'] as $event) {
            $startDate = $event['start_date'];

            $this->assertArrayHasKey('year', $startDate, 'Start date should have year');
            $this->assertIsInt($startDate['year']);
            $this->assertGreaterThanOrEqual(2024, $startDate['year']);
            $this->assertLessThanOrEqual(2026, $startDate['year']);
        }
    }

    /** @test */
    public function all_events_have_text_object()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        foreach ($decoded['events'] as $event) {
            $this->assertArrayHasKey('text', $event, 'Event should have text field');
            $this->assertIsArray($event['text']);
        }
    }

    /** @test */
    public function event_text_has_headline()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        foreach ($decoded['events'] as $event) {
            $this->assertArrayHasKey('headline', $event['text'], 'Event text should have headline');
            $this->assertNotEmpty($event['text']['headline']);
        }
    }

    /** @test */
    public function most_events_have_text_content()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        $eventsWithText = 0;
        foreach ($decoded['events'] as $event) {
            if (isset($event['text']['text']) && ! empty($event['text']['text'])) {
                $eventsWithText++;
            }
        }

        $this->assertGreaterThan(
            count($decoded['events']) * 0.5,
            $eventsWithText,
            'More than half of events should have text content'
        );
    }

    // ========================================
    // Event Group Tests
    // ========================================

    /** @test */
    public function most_events_have_group_assigned()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        $eventsWithGroup = 0;
        foreach ($decoded['events'] as $event) {
            if (isset($event['group']) && ! empty($event['group'])) {
                $eventsWithGroup++;
            }
        }

        $this->assertGreaterThan(
            count($decoded['events']) * 0.7,
            $eventsWithGroup,
            'More than 70% of events should have a group'
        );
    }

    /** @test */
    public function timeline_contains_expected_groups()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        $groups = [];
        foreach ($decoded['events'] as $event) {
            if (isset($event['group'])) {
                $groups[] = $event['group'];
            }
        }

        $uniqueGroups = array_unique($groups);

        // Check for expected groups
        $expectedGroups = ['Pp Prz-74', 'KP-DO-731', 'Slavonska 8', 'Su‑1717'];

        foreach ($expectedGroups as $expectedGroup) {
            $this->assertContains(
                $expectedGroup,
                $uniqueGroups,
                "Timeline should contain group: {$expectedGroup}"
            );
        }
    }

    /** @test */
    public function events_with_same_group_are_related()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        $groupCounts = [];
        foreach ($decoded['events'] as $event) {
            if (isset($event['group'])) {
                $group = $event['group'];
                $groupCounts[$group] = ($groupCounts[$group] ?? 0) + 1;
            }
        }

        // Each group should have multiple events
        foreach ($groupCounts as $group => $count) {
            $this->assertGreaterThan(
                1,
                $count,
                "Group '{$group}' should have multiple events"
            );
        }
    }

    // ========================================
    // Unique ID Tests
    // ========================================

    /** @test */
    public function most_events_have_unique_id()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        $eventsWithId = 0;
        foreach ($decoded['events'] as $event) {
            if (isset($event['unique_id']) && ! empty($event['unique_id'])) {
                $eventsWithId++;
            }
        }

        $this->assertGreaterThan(
            count($decoded['events']) * 0.6,
            $eventsWithId,
            'More than 60% of events should have unique_id'
        );
    }

    /** @test */
    public function unique_ids_are_actually_unique()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        $uniqueIds = [];
        foreach ($decoded['events'] as $event) {
            if (isset($event['unique_id']) && ! empty($event['unique_id'])) {
                $uniqueIds[] = $event['unique_id'];
            }
        }

        $this->assertCount(
            count(array_unique($uniqueIds)),
            $uniqueIds,
            'All unique_ids should be unique'
        );
    }

    // ========================================
    // Date Format Tests
    // ========================================

    /** @test */
    public function dates_have_valid_month_values()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        foreach ($decoded['events'] as $event) {
            if (isset($event['start_date']['month'])) {
                $month = $event['start_date']['month'];
                $this->assertGreaterThanOrEqual(1, $month);
                $this->assertLessThanOrEqual(12, $month);
            }
        }
    }

    /** @test */
    public function dates_have_valid_day_values()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        foreach ($decoded['events'] as $event) {
            if (isset($event['start_date']['day'])) {
                $day = $event['start_date']['day'];
                $this->assertGreaterThanOrEqual(1, $day);
                $this->assertLessThanOrEqual(31, $day);
            }
        }
    }

    /** @test */
    public function some_events_have_time_information()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        $eventsWithTime = 0;
        foreach ($decoded['events'] as $event) {
            if (isset($event['start_date']['hour']) || isset($event['start_date']['minute'])) {
                $eventsWithTime++;
            }
        }

        $this->assertGreaterThan(0, $eventsWithTime, 'Some events should have time information');
    }

    /** @test */
    public function time_values_are_valid()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        foreach ($decoded['events'] as $event) {
            if (isset($event['start_date']['hour'])) {
                $hour = $event['start_date']['hour'];
                $this->assertGreaterThanOrEqual(0, $hour);
                $this->assertLessThanOrEqual(23, $hour);
            }

            if (isset($event['start_date']['minute'])) {
                $minute = $event['start_date']['minute'];
                $this->assertGreaterThanOrEqual(0, $minute);
                $this->assertLessThanOrEqual(59, $minute);
            }
        }
    }

    // ========================================
    // End Date Tests
    // ========================================

    /** @test */
    public function some_events_have_end_date()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        $eventsWithEndDate = 0;
        foreach ($decoded['events'] as $event) {
            if (isset($event['end_date'])) {
                $eventsWithEndDate++;
                $this->assertIsArray($event['end_date']);
            }
        }

        $this->assertGreaterThan(0, $eventsWithEndDate, 'Some events should have end_date for duration events');
    }

    /** @test */
    public function end_dates_have_valid_structure()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        foreach ($decoded['events'] as $event) {
            if (isset($event['end_date'])) {
                $this->assertArrayHasKey('year', $event['end_date']);
                $this->assertIsInt($event['end_date']['year']);
            }
        }
    }

    // ========================================
    // Display Date Tests
    // ========================================

    /** @test */
    public function some_events_have_custom_display_date()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        $eventsWithDisplayDate = 0;
        foreach ($decoded['events'] as $event) {
            if (isset($event['display_date'])) {
                $eventsWithDisplayDate++;
                $this->assertIsString($event['display_date']);
            }
        }

        $this->assertGreaterThan(0, $eventsWithDisplayDate, 'Some events should have custom display_date');
    }

    // ========================================
    // Autolink Tests
    // ========================================

    /** @test */
    public function most_events_have_autolink_enabled()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        $eventsWithAutolink = 0;
        foreach ($decoded['events'] as $event) {
            if (isset($event['autolink']) && $event['autolink'] === true) {
                $eventsWithAutolink++;
            }
        }

        $this->assertGreaterThan(
            count($decoded['events']) * 0.5,
            $eventsWithAutolink,
            'More than half of events should have autolink enabled'
        );
    }

    // ========================================
    // Content Validation Tests
    // ========================================

    /** @test */
    public function event_text_contains_html_content()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        $eventsWithHtml = 0;
        foreach ($decoded['events'] as $event) {
            if (isset($event['text']['text']) &&
                (str_contains($event['text']['text'], '<p>') ||
                 str_contains($event['text']['text'], '<ul>') ||
                 str_contains($event['text']['text'], '<details>'))) {
                $eventsWithHtml++;
            }
        }

        $this->assertGreaterThan(0, $eventsWithHtml, 'Some events should contain HTML formatted content');
    }

    /** @test */
    public function some_events_have_legal_references()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        $eventsWithLegalRefs = 0;
        foreach ($decoded['events'] as $event) {
            if (isset($event['text']['text']) &&
                (str_contains($event['text']['text'], 'ZKP') ||
                 str_contains($event['text']['text'], 'Kazneni zakon') ||
                 str_contains($event['text']['text'], 'čl.'))) {
                $eventsWithLegalRefs++;
            }
        }

        $this->assertGreaterThan(10, $eventsWithLegalRefs, 'Many events should contain legal references');
    }

    // ========================================
    // Timeline Configuration Tests
    // ========================================

    /** @test */
    public function timeline_title_has_background_color()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        $this->assertArrayHasKey('background', $decoded['title']);
        $this->assertArrayHasKey('color', $decoded['title']['background']);
    }

    /** @test */
    public function timeline_title_has_unique_id()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        $this->assertArrayHasKey('unique_id', $decoded['title']);
        $this->assertEquals('title-slide', $decoded['title']['unique_id']);
    }

    /** @test */
    public function timeline_title_has_autolink_enabled()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');
        $decoded = json_decode($timelineJs, true);

        $this->assertArrayHasKey('autolink', $decoded['title']);
        $this->assertTrue($decoded['title']['autolink']);
    }

    // ========================================
    // View Rendering Tests
    // ========================================

    /** @test */
    public function view_contains_timeline_embed_div()
    {
        Livewire::test(TimelinePage::class)
            ->assertSee('timeline-embed', false);
    }

    /** @test */
    public function view_contains_correct_blade_template()
    {
        $component = Livewire::test(TimelinePage::class);

        // Check that the view is rendered correctly
        $component->assertViewIs('livewire.timeline-page');

        // Check for the timeline embed div
        $component->assertSee('timeline-embed', false);
    }

    /** @test */
    public function timeline_data_is_accessible_in_component()
    {
        $component = Livewire::test(TimelinePage::class);

        // The timeline data should be accessible via the component
        $timelineJs = $component->get('timelineJs');

        $this->assertNotEmpty($timelineJs);
        $this->assertIsString($timelineJs);

        // Should be valid JSON
        $decoded = json_decode($timelineJs, true);
        $this->assertNotNull($decoded);
        $this->assertArrayHasKey('events', $decoded);
    }

    /** @test */
    public function timeline_json_is_properly_escaped_for_javascript()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');

        // Should not contain unescaped quotes that would break JavaScript
        $this->assertStringNotContainsString('</script>', $timelineJs);
    }

    // ========================================
    // Data Integrity Tests
    // ========================================

    /** @test */
    public function timeline_data_is_consistent_across_mounts()
    {
        $component1 = Livewire::test(TimelinePage::class);
        $component2 = Livewire::test(TimelinePage::class);

        $timelineJs1 = $component1->get('timelineJs');
        $timelineJs2 = $component2->get('timelineJs');

        $this->assertEquals($timelineJs1, $timelineJs2, 'Timeline data should be consistent across mounts');
    }

    /** @test */
    public function timeline_json_size_is_reasonable()
    {
        $component = Livewire::test(TimelinePage::class);
        $timelineJs = $component->get('timelineJs');

        $size = strlen($timelineJs);

        $this->assertGreaterThan(10000, $size, 'Timeline JSON should have substantial content');
        $this->assertLessThan(500000, $size, 'Timeline JSON should not be excessively large');
    }
}
