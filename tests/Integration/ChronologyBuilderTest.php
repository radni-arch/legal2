<?php

namespace Tests\Integration;

use App\Models\CaseChronology;
use App\Models\LegalFactPattern;
use App\Models\User;
use App\Services\Case\ChronologyBuilder;

/**
 * Integration tests for Chronology Builder
 *
 * Tests the complete chronology building workflow including event extraction,
 * timeline generation, gap identification, and multi-format export.
 *
 * NOTE: External dependencies (OpenAI, DecisionSearch) are mocked via IntegrationTestCase
 */
class ChronologyBuilderTest extends IntegrationTestCase
{
    protected User $user;

    protected ChronologyBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->builder = app(ChronologyBuilder::class);
    }

    /** @test */
    public function it_builds_complete_chronology()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'events' => [
                    [
                        'description' => 'Contract signed',
                        'date' => '2024-01-15',
                        'significance' => 'Formation of agreement',
                    ],
                    [
                        'description' => 'First payment made',
                        'date' => '2024-02-01',
                        'significance' => 'Performance began',
                    ],
                    [
                        'description' => 'Dispute arose',
                        'date' => '2024-03-15',
                        'significance' => 'Breach alleged',
                    ],
                ],
                'timeline' => [
                    ['date' => '2024-01-10', 'event' => 'Initial negotiations'],
                    ['date' => '2024-04-01', 'event' => 'Demand letter sent'],
                ],
                'procedural_posture' => [
                    'stage' => 'pre-filing',
                    'deadlines' => [
                        ['description' => 'Statute of limitations', 'date' => '2025-01-15'],
                    ],
                ],
                'evidence' => [
                    [
                        'type' => 'documentary',
                        'description' => 'Email from 2024-02-15',
                        'date' => '2024-02-15',
                    ],
                ],
                'parties' => [],
                'legal_issues' => [],
                'summary' => 'Chronology test case',
            ],
        ]);

        $chronology = $this->builder->buildChronology($factPattern->id);

        // Verify chronology structure
        $this->assertInstanceOf(CaseChronology::class, $chronology);
        $this->assertEquals($this->user->id, $chronology->user_id);
        $this->assertEquals($factPattern->id, $chronology->fact_pattern_id);

        // Verify events
        $events = $chronology->events;
        $this->assertNotEmpty($events);
        $this->assertIsArray($events);

        // Should have combined events from multiple sources
        $this->assertGreaterThanOrEqual(5, count($events)); // At least 3 events + 2 timeline + 1 evidence

        // Verify event structure
        foreach ($events as $event) {
            $this->assertArrayHasKey('date', $event);
            $this->assertArrayHasKey('description', $event);
            $this->assertArrayHasKey('sequence', $event);
            $this->assertArrayHasKey('category', $event);
        }

        // Verify chronological order
        $dates = array_column($events, 'date');
        $sortedDates = $dates;
        sort($sortedDates);
        $this->assertEquals($sortedDates, $dates, 'Events should be in chronological order');

        // Verify analysis
        $analysis = $chronology->analysis;
        $this->assertArrayHasKey('time_gaps', $analysis);
        $this->assertArrayHasKey('critical_dates', $analysis);
        $this->assertArrayHasKey('timeline_summary', $analysis);
    }

    /** @test */
    public function it_identifies_time_gaps()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'events' => [
                    ['description' => 'Event 1', 'date' => '2024-01-01'],
                    ['description' => 'Event 2', 'date' => '2024-03-15'], // 74-day gap
                    ['description' => 'Event 3', 'date' => '2024-03-20'], // 5-day gap
                ],
                'parties' => [],
                'legal_issues' => [],
                'evidence' => [],
                'summary' => 'Gap test',
            ],
        ]);

        $chronology = $this->builder->buildChronology($factPattern->id);

        $gaps = $chronology->analysis['time_gaps'];

        // Should identify the 74-day gap (> 30 days threshold)
        $this->assertGreaterThan(0, count($gaps));

        $largeGap = collect($gaps)->first(function ($gap) {
            return $gap['days'] > 30;
        });

        $this->assertNotNull($largeGap);
        $this->assertArrayHasKey('after_event', $largeGap);
        $this->assertArrayHasKey('before_event', $largeGap);
        $this->assertArrayHasKey('days', $largeGap);
        $this->assertArrayHasKey('date_range', $largeGap);
        $this->assertArrayHasKey('questions', $largeGap);

        // Should have investigation questions
        $this->assertNotEmpty($largeGap['questions']);
    }

    /** @test */
    public function it_identifies_critical_dates()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'events' => [
                    [
                        'description' => 'Contract signed',
                        'date' => '2024-01-15',
                        'significance' => 'Formation of contract',
                    ],
                    [
                        'description' => 'Breach occurred',
                        'date' => '2024-03-20',
                        'significance' => 'Material breach',
                    ],
                ],
                'procedural_posture' => [
                    'deadlines' => [
                        ['description' => 'Filing deadline', 'date' => '2025-01-15'],
                    ],
                ],
                'parties' => [],
                'legal_issues' => [],
                'evidence' => [],
                'summary' => 'Critical dates test',
            ],
        ]);

        $chronology = $this->builder->buildChronology($factPattern->id);

        $criticalDates = $chronology->analysis['critical_dates'];

        $this->assertNotEmpty($criticalDates);
        $this->assertIsArray($criticalDates);

        // Should flag significant events
        foreach ($criticalDates as $critical) {
            $this->assertArrayHasKey('date', $critical);
            $this->assertArrayHasKey('description', $critical);
            $this->assertArrayHasKey('reason', $critical);
        }
    }

    /** @test */
    public function it_categorizes_events()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'events' => [
                    ['description' => 'Contract signed', 'date' => '2024-01-15'],
                ],
                'timeline' => [
                    ['event' => 'Lawsuit filed', 'date' => '2024-06-01'],
                ],
                'procedural_posture' => [
                    'deadlines' => [
                        ['description' => 'Discovery deadline', 'date' => '2024-12-01'],
                    ],
                ],
                'evidence' => [
                    [
                        'type' => 'documentary',
                        'description' => 'Email sent',
                        'date' => '2024-02-01',
                    ],
                ],
                'parties' => [],
                'legal_issues' => [],
                'summary' => 'Category test',
            ],
        ]);

        $chronology = $this->builder->buildChronology($factPattern->id);

        $events = $chronology->events;

        $categories = array_unique(array_column($events, 'category'));

        // Should have multiple categories
        $this->assertGreaterThan(1, count($categories));

        // Expected categories
        $expectedCategories = ['event', 'timeline', 'procedural', 'evidence'];
        $foundCategories = array_intersect($categories, $expectedCategories);
        $this->assertGreaterThan(0, count($foundCategories));
    }

    /** @test */
    public function it_generates_narrative_timeline()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'events' => [
                    ['description' => 'Meeting held', 'date' => '2024-01-15'],
                    ['description' => 'Agreement reached', 'date' => '2024-01-16'],
                    ['description' => 'Contract signed', 'date' => '2024-01-20'],
                ],
                'parties' => [],
                'legal_issues' => [],
                'evidence' => [],
                'summary' => 'Narrative test',
            ],
        ]);

        $chronology = $this->builder->buildChronology($factPattern->id);

        $narrative = $chronology->analysis['timeline_summary'];

        $this->assertNotEmpty($narrative);
        $this->assertIsString($narrative);

        // Should mention key events
        $this->assertStringContainsString('2024', $narrative);
    }

    /** @test */
    public function it_exports_to_text_format()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'events' => [
                    ['description' => 'Event 1', 'date' => '2024-01-15'],
                    ['description' => 'Event 2', 'date' => '2024-02-20'],
                ],
                'parties' => [],
                'legal_issues' => [],
                'evidence' => [],
                'summary' => 'Export test',
            ],
        ]);

        $chronology = $this->builder->buildChronology($factPattern->id);

        $textExport = $this->builder->exportChronology($chronology->id, 'text');

        $this->assertNotEmpty($textExport);
        $this->assertIsString($textExport);

        // Should contain events
        $this->assertStringContainsString('2024-01-15', $textExport);
        $this->assertStringContainsString('Event 1', $textExport);
        $this->assertStringContainsString('2024-02-20', $textExport);
    }

    /** @test */
    public function it_exports_to_csv_format()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'events' => [
                    ['description' => 'Event 1', 'date' => '2024-01-15'],
                    ['description' => 'Event 2', 'date' => '2024-02-20'],
                ],
                'parties' => [],
                'legal_issues' => [],
                'evidence' => [],
                'summary' => 'CSV test',
            ],
        ]);

        $chronology = $this->builder->buildChronology($factPattern->id);

        $csvExport = $this->builder->exportChronology($chronology->id, 'csv');

        $this->assertNotEmpty($csvExport);
        $this->assertIsString($csvExport);

        // Should have CSV headers
        $this->assertStringContainsString('Date,Sequence,Description,Category', $csvExport);

        // Should have data rows
        $lines = explode("\n", $csvExport);
        $this->assertGreaterThan(2, count($lines)); // Header + at least 2 events
    }

    /** @test */
    public function it_exports_to_markdown_format()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'events' => [
                    ['description' => 'Event 1', 'date' => '2024-01-15', 'significance' => 'Important'],
                    ['description' => 'Event 2', 'date' => '2024-02-20'],
                ],
                'parties' => [],
                'legal_issues' => [],
                'evidence' => [],
                'summary' => 'Markdown test',
            ],
        ]);

        $chronology = $this->builder->buildChronology($factPattern->id);

        $markdownExport = $this->builder->exportChronology($chronology->id, 'markdown');

        $this->assertNotEmpty($markdownExport);
        $this->assertIsString($markdownExport);

        // Should use markdown formatting
        $this->assertStringContainsString('#', $markdownExport); // Headers
        $this->assertStringContainsString('**', $markdownExport); // Bold
        $this->assertStringContainsString('- ', $markdownExport); // List items
    }

    /** @test */
    public function it_exports_to_json_format()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'events' => [
                    ['description' => 'Event 1', 'date' => '2024-01-15'],
                ],
                'parties' => [],
                'legal_issues' => [],
                'evidence' => [],
                'summary' => 'JSON test',
            ],
        ]);

        $chronology = $this->builder->buildChronology($factPattern->id);

        $jsonExport = $this->builder->exportChronology($chronology->id, 'json');

        $this->assertNotEmpty($jsonExport);
        $this->assertIsString($jsonExport);

        // Should be valid JSON
        $decoded = json_decode($jsonExport, true);
        $this->assertNotNull($decoded);
        $this->assertArrayHasKey('events', $decoded);
        $this->assertArrayHasKey('analysis', $decoded);
    }

    /** @test */
    public function it_provides_visualization_data()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'events' => [
                    ['description' => 'Event 1', 'date' => '2024-01-15'],
                    ['description' => 'Event 2', 'date' => '2024-02-20'],
                    ['description' => 'Event 3', 'date' => '2024-03-10'],
                ],
                'parties' => [],
                'legal_issues' => [],
                'evidence' => [],
                'summary' => 'Visualization test',
            ],
        ]);

        $chronology = $this->builder->buildChronology($factPattern->id);

        $vizData = $chronology->visualization_data;

        $this->assertNotEmpty($vizData);
        $this->assertIsArray($vizData);

        // Should have chart-ready data
        $this->assertArrayHasKey('timeline_data', $vizData);
        $this->assertArrayHasKey('gap_visualization', $vizData);

        // Timeline data should be structured for charting
        $timelineData = $vizData['timeline_data'];
        $this->assertIsArray($timelineData);
        $this->assertNotEmpty($timelineData);

        foreach ($timelineData as $point) {
            $this->assertArrayHasKey('x', $point); // Date
            $this->assertArrayHasKey('y', $point); // Sequence or value
            $this->assertArrayHasKey('label', $point); // Description
        }
    }

    /** @test */
    public function it_handles_events_with_partial_dates()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'events' => [
                    ['description' => 'Event with full date', 'date' => '2024-01-15'],
                    ['description' => 'Event with month only', 'date' => '2024-02'],
                    ['description' => 'Event with year only', 'date' => '2024'],
                    ['description' => 'Event with no date'],
                ],
                'parties' => [],
                'legal_issues' => [],
                'evidence' => [],
                'summary' => 'Partial dates test',
            ],
        ]);

        $chronology = $this->builder->buildChronology($factPattern->id);

        // Should still build chronology
        $this->assertInstanceOf(CaseChronology::class, $chronology);
        $this->assertNotEmpty($chronology->events);

        // Events with dates should be included
        $eventsWithDates = collect($chronology->events)->filter(function ($event) {
            return ! empty($event['date']);
        });

        $this->assertGreaterThan(0, $eventsWithDates->count());
    }

    /** @test */
    public function it_calculates_duration_metrics()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'events' => [
                    ['description' => 'Start', 'date' => '2024-01-01'],
                    ['description' => 'End', 'date' => '2024-06-30'],
                ],
                'parties' => [],
                'legal_issues' => [],
                'evidence' => [],
                'summary' => 'Duration test',
            ],
        ]);

        $chronology = $this->builder->buildChronology($factPattern->id);

        $analysis = $chronology->analysis;

        $this->assertArrayHasKey('timeline_summary', $analysis);
        $summary = $analysis['timeline_summary'];

        // Should mention duration or time span
        $this->assertNotEmpty($summary);
    }

    /** @test */
    public function it_handles_complex_multi_event_cases()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'events' => [
                    ['description' => 'Event 1', 'date' => '2024-01-01'],
                    ['description' => 'Event 2', 'date' => '2024-01-15'],
                    ['description' => 'Event 3', 'date' => '2024-02-01'],
                    ['description' => 'Event 4', 'date' => '2024-02-15'],
                    ['description' => 'Event 5', 'date' => '2024-03-01'],
                ],
                'timeline' => [
                    ['event' => 'Timeline 1', 'date' => '2024-01-10'],
                    ['event' => 'Timeline 2', 'date' => '2024-02-10'],
                    ['event' => 'Timeline 3', 'date' => '2024-03-10'],
                ],
                'evidence' => [
                    ['type' => 'documentary', 'description' => 'Doc 1', 'date' => '2024-01-05'],
                    ['type' => 'documentary', 'description' => 'Doc 2', 'date' => '2024-02-05'],
                ],
                'procedural_posture' => [
                    'deadlines' => [
                        ['description' => 'Deadline 1', 'date' => '2024-04-01'],
                        ['description' => 'Deadline 2', 'date' => '2024-05-01'],
                    ],
                ],
                'parties' => [],
                'legal_issues' => [],
                'summary' => 'Complex timeline',
            ],
        ]);

        $chronology = $this->builder->buildChronology($factPattern->id);

        // Should combine all event sources
        $totalEvents = 5 + 3 + 2 + 2; // events + timeline + evidence + deadlines
        $this->assertCount($totalEvents, $chronology->events);

        // Should maintain chronological order
        $dates = array_column($chronology->events, 'date');
        $sortedDates = $dates;
        sort($sortedDates);
        $this->assertEquals($sortedDates, $dates);
    }

    /** @test */
    public function it_stores_chronology_metadata()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'events' => [
                    ['description' => 'Event 1', 'date' => '2024-01-15'],
                ],
                'parties' => [],
                'legal_issues' => [],
                'evidence' => [],
                'summary' => 'Metadata test',
            ],
        ]);

        $chronology = $this->builder->buildChronology($factPattern->id);

        // Should be persisted to database
        $this->assertDatabaseHas('case_chronologies', [
            'id' => $chronology->id,
            'user_id' => $this->user->id,
            'fact_pattern_id' => $factPattern->id,
        ]);

        // Should have timestamps
        $this->assertNotNull($chronology->created_at);
        $this->assertNotNull($chronology->updated_at);
    }

    /** @test */
    public function it_can_rebuild_chronology_with_updated_facts()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'events' => [
                    ['description' => 'Event 1', 'date' => '2024-01-15'],
                ],
                'parties' => [],
                'legal_issues' => [],
                'evidence' => [],
                'summary' => 'Initial version',
            ],
        ]);

        // Build initial chronology
        $chronology1 = $this->builder->buildChronology($factPattern->id);
        $initialEventCount = count($chronology1->events);

        // Update fact pattern with more events
        $factPattern->update([
            'structured_facts' => [
                'events' => [
                    ['description' => 'Event 1', 'date' => '2024-01-15'],
                    ['description' => 'Event 2', 'date' => '2024-02-20'],
                    ['description' => 'Event 3', 'date' => '2024-03-10'],
                ],
                'parties' => [],
                'legal_issues' => [],
                'evidence' => [],
                'summary' => 'Updated version',
            ],
        ]);

        // Rebuild chronology
        $chronology2 = $this->builder->buildChronology($factPattern->id);
        $updatedEventCount = count($chronology2->events);

        // Should have more events
        $this->assertGreaterThan($initialEventCount, $updatedEventCount);
    }

    /** @test */
    public function it_identifies_concurrent_events()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'events' => [
                    ['description' => 'Event A', 'date' => '2024-01-15'],
                    ['description' => 'Event B', 'date' => '2024-01-15'], // Same date
                    ['description' => 'Event C', 'date' => '2024-01-15'], // Same date
                    ['description' => 'Event D', 'date' => '2024-02-01'],
                ],
                'parties' => [],
                'legal_issues' => [],
                'evidence' => [],
                'summary' => 'Concurrent events test',
            ],
        ]);

        $chronology = $this->builder->buildChronology($factPattern->id);

        // All events should be included
        $this->assertCount(4, $chronology->events);

        // Events on same date should have different sequence numbers
        $eventsOnSameDate = collect($chronology->events)->filter(function ($event) {
            return $event['date'] === '2024-01-15';
        });

        $sequences = $eventsOnSameDate->pluck('sequence')->unique();
        $this->assertGreaterThan(1, $sequences->count());
    }
}
