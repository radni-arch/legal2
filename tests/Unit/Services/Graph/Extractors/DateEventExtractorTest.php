<?php

namespace Tests\Unit\Services\Graph\Extractors;

use App\Services\Graph\Extractors\DateEventExtractor;
use Illuminate\Support\Str;
use Tests\TestCase;

class DateEventExtractorTest extends TestCase
{
    protected DateEventExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new DateEventExtractor();
    }

    /** @test */
    public function it_extracts_croatian_date_format_with_month_names()
    {
        $text = 'Presuda je donesena 15. siječnja 2024. godine u Zagrebu.';

        $events = $this->extractor->extract($text, 'doc-123', 'decision');

        $this->assertCount(1, $events);
        $this->assertEquals('2024-01-15', $events[0]['date']);
        $this->assertEquals('judgment', $events[0]['event_type']);
        $this->assertEquals('decision', $events[0]['source_type']);
        $this->assertEquals('doc-123', $events[0]['source_id']);
        $this->assertNotEmpty($events[0]['id']);
        $this->assertNotEmpty($events[0]['description']);
    }

    /** @test */
    public function it_extracts_numeric_date_format()
    {
        $text = 'Tužba je podnesena 23.05.2023. u sudski ured.';

        $events = $this->extractor->extract($text, 'doc-456', 'decision');

        $this->assertCount(1, $events);
        $this->assertEquals('2023-05-23', $events[0]['date']);
        $this->assertEquals('filing', $events[0]['event_type']);
    }

    /** @test */
    public function it_extracts_iso_date_format()
    {
        $text = 'Dokument je datiran 2024-03-15 prema evidenciji.';

        $events = $this->extractor->extract($text, 'doc-789', 'decision');

        $this->assertCount(1, $events);
        $this->assertEquals('2024-03-15', $events[0]['date']);
    }

    /** @test */
    public function it_classifies_filing_events()
    {
        $texts = [
            'Tužba podnesena 10.01.2024.',
            'Zahtjev zaprimljen 15.02.2024.',
            'Prijava je podnijet 20.03.2024.',
        ];

        foreach ($texts as $text) {
            $events = $this->extractor->extract($text, 'doc-123', 'decision');
            $this->assertCount(1, $events, "Failed for: $text");
            $this->assertEquals('filing', $events[0]['event_type'], "Failed for: $text");
        }
    }

    /** @test */
    public function it_classifies_hearing_events()
    {
        $texts = [
            'Ročište održano 10.04.2024.',
            'Rasprava zakazana za 15.05.2024.',
            'Javna sjednica održana 20.06.2024.',
            'Saslušanje svjedoka 25.07.2024.',
        ];

        foreach ($texts as $text) {
            $events = $this->extractor->extract($text, 'doc-123', 'decision');
            $this->assertCount(1, $events, "Failed for: $text");
            $this->assertEquals('hearing', $events[0]['event_type'], "Failed for: $text");
        }
    }

    /** @test */
    public function it_classifies_judgment_events()
    {
        $texts = [
            'Presuda donesena 10.08.2024.',
            'Rješenje doneseno 15.09.2024.',
            'Odluka pravomočna 20.10.2024.',
            'Presuda izrečena 25.11.2024.',
        ];

        foreach ($texts as $text) {
            $events = $this->extractor->extract($text, 'doc-123', 'decision');
            $this->assertCount(1, $events, "Failed for: $text");
            $this->assertEquals('judgment', $events[0]['event_type'], "Failed for: $text");
        }
    }

    /** @test */
    public function it_classifies_effective_events()
    {
        $texts = [
            'Zakon stupa na snagu 01.01.2024.',
            'Primjenjuje se od 15.02.2024.',
            'Važi od 01.03.2024.',
        ];

        foreach ($texts as $text) {
            $events = $this->extractor->extract($text, 'law-123', 'law');
            $this->assertCount(1, $events, "Failed for: $text");
            $this->assertEquals('effective', $events[0]['event_type'], "Failed for: $text");
        }
    }

    /** @test */
    public function it_classifies_expiry_events()
    {
        $texts = [
            'Rok istječe 31.12.2024.',
            'Prestaje važiti 30.06.2025.',
            'Krajnji rok je 15.03.2024.',
        ];

        foreach ($texts as $text) {
            $events = $this->extractor->extract($text, 'law-123', 'law');
            $this->assertCount(1, $events, "Failed for: $text");
            $this->assertEquals('expiry', $events[0]['event_type'], "Failed for: $text");
        }
    }

    /** @test */
    public function it_returns_empty_for_empty_text()
    {
        $this->assertEmpty($this->extractor->extract('', 'doc-123'));
        $this->assertEmpty($this->extractor->extract('   ', 'doc-123'));
        $this->assertEmpty($this->extractor->extract("\n\t", 'doc-123'));
    }

    /** @test */
    public function it_provides_event_types()
    {
        $types = $this->extractor->getEventTypes();

        $this->assertIsArray($types);
        $this->assertContains('filing', $types);
        $this->assertContains('hearing', $types);
        $this->assertContains('judgment', $types);
        $this->assertContains('effective', $types);
        $this->assertContains('expiry', $types);
    }

    /** @test */
    public function it_handles_all_croatian_month_names()
    {
        $months = [
            'siječnja' => '2024-01-15',
            'veljače' => '2024-02-15',
            'ožujka' => '2024-03-15',
            'travnja' => '2024-04-15',
            'svibnja' => '2024-05-15',
            'lipnja' => '2024-06-15',
            'srpnja' => '2024-07-15',
            'kolovoza' => '2024-08-15',
            'rujna' => '2024-09-15',
            'listopada' => '2024-10-15',
            'studenoga' => '2024-11-15',
            'prosinca' => '2024-12-15',
        ];

        foreach ($months as $monthName => $expectedDate) {
            $text = "Datum je 15. {$monthName} 2024. godine.";
            $events = $this->extractor->extract($text, 'doc-123', 'decision');

            $this->assertCount(1, $events, "Failed for month: $monthName");
            $this->assertEquals($expectedDate, $events[0]['date'], "Failed for month: $monthName");
        }
    }

    /** @test */
    public function it_extracts_multiple_dates_from_text()
    {
        $text = 'Tužba podnesena 10.01.2024. godine. ' .
                'Zatim je zakazano ročište održano 15.02.2024. godine. ' .
                'Sud je donio presudu donesena 20.03.2024. godine.';

        $events = $this->extractor->extract($text, 'doc-123', 'decision');

        $this->assertCount(3, $events);
        $this->assertEquals('2024-01-10', $events[0]['date']);
        $this->assertEquals('filing', $events[0]['event_type']);
        $this->assertEquals('2024-02-15', $events[1]['date']);
        $this->assertEquals('hearing', $events[1]['event_type']);
        $this->assertEquals('2024-03-20', $events[2]['date']);
        $this->assertEquals('judgment', $events[2]['event_type']);
    }

    /** @test */
    public function it_deduplicates_same_date_and_event_type()
    {
        $text = 'Presuda donesena 10.01.2024. U odluci od 10.01.2024. navedeno...';

        $events = $this->extractor->extract($text, 'doc-123', 'decision');

        // Should only extract one event since date + event_type are the same
        $this->assertCount(1, $events);
        $this->assertEquals('2024-01-10', $events[0]['date']);
    }

    /** @test */
    public function it_sorts_events_by_date_chronologically()
    {
        $text = 'Presuda 20.03.2024., tužba 10.01.2024., ročište 15.02.2024.';

        $events = $this->extractor->extract($text, 'doc-123', 'decision');

        $this->assertCount(3, $events);
        $this->assertEquals('2024-01-10', $events[0]['date']); // Earliest first
        $this->assertEquals('2024-02-15', $events[1]['date']);
        $this->assertEquals('2024-03-20', $events[2]['date']); // Latest last
    }

    /** @test */
    public function it_generates_ulid_for_event_ids()
    {
        $text = 'Presuda donesena 10.01.2024.';

        $events = $this->extractor->extract($text, 'doc-123', 'decision');

        $this->assertCount(1, $events);
        $this->assertNotEmpty($events[0]['id']);
        $this->assertTrue(Str::isUlid($events[0]['id']));
    }

    /** @test */
    public function it_extracts_context_description_for_events()
    {
        $text = 'Nakon održanog ročišta, sud je donio presudu 15. siječnja 2024. godine kojom je usvojen tužbeni zahtjev.';

        $events = $this->extractor->extract($text, 'doc-123', 'decision');

        $this->assertCount(1, $events);
        $this->assertNotEmpty($events[0]['description']);
        $this->assertStringContainsString('presudu', $events[0]['description']);
        $this->assertLessThanOrEqual(300, mb_strlen($events[0]['description']));
    }

    /** @test */
    public function it_handles_dates_without_trailing_period()
    {
        $text = 'Datum je 15.01.2024 bez točke na kraju.';

        $events = $this->extractor->extract($text, 'doc-123', 'decision');

        $this->assertCount(1, $events);
        $this->assertEquals('2024-01-15', $events[0]['date']);
    }

    /** @test */
    public function it_handles_single_digit_days_and_months()
    {
        $text = 'Datum je 5.3.2024. za službenu bilješku.';

        $events = $this->extractor->extract($text, 'doc-123', 'decision');

        $this->assertCount(1, $events);
        $this->assertEquals('2024-03-05', $events[0]['date']);
    }

    /** @test */
    public function it_defaults_to_judgment_event_type_when_no_indicators_found()
    {
        $text = 'Neki tekst sa datumom 15.01.2024. bez specifičnih ključnih riječi.';

        $events = $this->extractor->extract($text, 'doc-123', 'decision');

        $this->assertCount(1, $events);
        $this->assertEquals('judgment', $events[0]['event_type']); // Default for decisions
    }
}
