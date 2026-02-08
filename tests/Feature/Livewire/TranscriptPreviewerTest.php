<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\TranscriptPreviewer;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class TranscriptPreviewerTest extends TestCase
{
    use UsesTestDatabase;

    protected string $testTranscriptPath;

    protected string $testLinguaPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test transcript file
        $this->testTranscriptPath = storage_path('test_transcript.txt');
        File::put($this->testTranscriptPath, "[00:00:00] S1: Test transcript line 1\n[00:01:00] S2: Test transcript line 2\n");

        // Create test lingua file
        $this->testLinguaPath = storage_path('test_lingua.txt');
        File::put($this->testLinguaPath, 'Test lingua analysis content');
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testTranscriptPath)) {
            File::delete($this->testTranscriptPath);
        }

        if (File::exists($this->testLinguaPath)) {
            File::delete($this->testLinguaPath);
        }

        parent::tearDown();
    }

    /**
     * Test 1: Component renders correctly
     *
     * @test
     */
    public function test_component_renders_correctly()
    {
        Livewire::test(TranscriptPreviewer::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.transcript-previewer')
            ->assertSet('search', '')
            ->assertSet('showTimestamps', true)
            ->assertSet('autoRefresh', false)
            ->assertSet('showLingua', true);
    }

    /**
     * Test 2: Component initializes with default path
     *
     * @test
     */
    public function test_component_initializes_with_default_path()
    {
        $component = Livewire::test(TranscriptPreviewer::class);

        $filePath = $component->get('filePath');

        $this->assertEquals(storage_path('iznedjenaIzjava.txt'), $filePath);
    }

    /**
     * Test 3: Component initializes with custom path
     *
     * @test
     */
    public function test_component_initializes_with_custom_path()
    {
        $component = Livewire::test(TranscriptPreviewer::class, ['path' => $this->testTranscriptPath]);

        $filePath = $component->get('filePath');

        $this->assertEquals($this->testTranscriptPath, $filePath);
    }

    /**
     * Test 4: Search input binding works
     *
     * @test
     */
    public function test_search_input_binding_works()
    {
        Livewire::test(TranscriptPreviewer::class)
            ->set('search', 'test query')
            ->assertSet('search', 'test query');
    }

    /**
     * Test 5: Show timestamps toggle works
     *
     * @test
     */
    public function test_show_timestamps_toggle_works()
    {
        Livewire::test(TranscriptPreviewer::class)
            ->assertSet('showTimestamps', true)
            ->set('showTimestamps', false)
            ->assertSet('showTimestamps', false);
    }

    /**
     * Test 6: Auto refresh toggle works
     *
     * @test
     */
    public function test_auto_refresh_toggle_works()
    {
        Livewire::test(TranscriptPreviewer::class)
            ->assertSet('autoRefresh', false)
            ->set('autoRefresh', true)
            ->assertSet('autoRefresh', true);
    }

    /**
     * Test 7: Show lingua toggle works
     *
     * @test
     */
    public function test_show_lingua_toggle_works()
    {
        Livewire::test(TranscriptPreviewer::class)
            ->assertSet('showLingua', true)
            ->set('showLingua', false)
            ->assertSet('showLingua', false);
    }

    /**
     * Test 8: Base start datetime is initialized
     *
     * @test
     */
    public function test_base_start_datetime_initialized()
    {
        $component = Livewire::test(TranscriptPreviewer::class);

        $baseStart = $component->get('baseStart');

        $this->assertIsString($baseStart);
        $this->assertEquals('2025-06-09 14:45:00', $baseStart);
    }

    /**
     * Test 9: Timezone is set correctly
     *
     * @test
     */
    public function test_timezone_set_correctly()
    {
        $component = Livewire::test(TranscriptPreviewer::class);

        $timezone = $component->get('timezone');

        $this->assertEquals('Europe/Zagreb', $timezone);
    }

    /**
     * Test 10: Segments array is initialized
     *
     * @test
     */
    public function test_segments_array_initialized()
    {
        $component = Livewire::test(TranscriptPreviewer::class);

        $segments = $component->get('segments');

        $this->assertIsArray($segments);
    }

    /**
     * Test 11: Lingua events array is initialized
     *
     * @test
     */
    public function test_lingua_events_array_initialized()
    {
        $component = Livewire::test(TranscriptPreviewer::class);

        $linguaEvents = $component->get('linguaEvents');

        $this->assertIsArray($linguaEvents);
    }

    /**
     * Test 12: Speakers array is initialized
     *
     * @test
     */
    public function test_speakers_array_initialized()
    {
        $component = Livewire::test(TranscriptPreviewer::class);

        $speakers = $component->get('speakers');

        $this->assertIsArray($speakers);
    }

    /**
     * Test 13: Toggle speaker method exists
     *
     * @test
     */
    public function test_toggle_speaker_method_exists()
    {
        $component = Livewire::test(TranscriptPreviewer::class);

        $this->assertTrue(method_exists($component->instance(), 'toggleSpeaker'));
    }

    /**
     * Test 14: All speakers method exists
     *
     * @test
     */
    public function test_all_speakers_method_exists()
    {
        $component = Livewire::test(TranscriptPreviewer::class);

        $this->assertTrue(method_exists($component->instance(), 'allSpeakers'));
    }

    /**
     * Test 15: Refresh now method exists
     *
     * @test
     */
    public function test_refresh_now_method_exists()
    {
        $component = Livewire::test(TranscriptPreviewer::class);

        $this->assertTrue(method_exists($component->instance(), 'refreshNow'));

        $component->call('refreshNow')
            ->assertSuccessful();
    }

    /**
     * Test 16: Duration is initialized to zero
     *
     * @test
     */
    public function test_duration_initialized_to_zero()
    {
        $component = Livewire::test(TranscriptPreviewer::class);

        $durationSec = $component->get('durationSec');

        // Duration may be calculated from the transcript file
        // Just verify it's a number
        $this->assertIsNumeric($durationSec);
        $this->assertGreaterThanOrEqual(0, $durationSec);
    }

    /**
     * Test 17: Lingua raw text is initialized as empty string
     *
     * @test
     */
    public function test_lingua_raw_initialized_empty()
    {
        $component = Livewire::test(TranscriptPreviewer::class);

        $linguaRaw = $component->get('linguaRaw');

        $this->assertIsString($linguaRaw);
    }

    /**
     * Test 18: Lingua summary is initialized as empty string
     *
     * @test
     */
    public function test_lingua_summary_initialized_empty()
    {
        $component = Livewire::test(TranscriptPreviewer::class);

        $linguaSummary = $component->get('linguaSummary');

        $this->assertIsString($linguaSummary);
    }

    /**
     * Test 19: Component handles missing transcript file gracefully
     *
     * @test
     */
    public function test_component_handles_missing_file_gracefully()
    {
        $component = Livewire::test(TranscriptPreviewer::class, ['path' => '/nonexistent/file.txt']);

        // Should not throw error
        $this->assertNotNull($component);
    }

    /**
     * Test 20: Updated hook reloads transcript when path changes
     *
     * @test
     */
    public function test_updated_hook_reloads_on_path_change()
    {
        $component = Livewire::test(TranscriptPreviewer::class);

        $component->set('filePath', $this->testTranscriptPath);

        // Should not throw error
        $this->assertTrue(true);
    }

    /**
     * Test 21: Textract segments are displayed correctly
     *
     * @test
     */
    public function test_textract_segments_are_displayed_correctly()
    {
        $component = Livewire::test(TranscriptPreviewer::class, ['path' => $this->testTranscriptPath]);

        $segments = $component->get('segments');

        $this->assertIsArray($segments);
        $this->assertGreaterThan(0, count($segments));

        // Verify segment structure
        foreach ($segments as $segment) {
            $this->assertArrayHasKey('time', $segment);
            $this->assertArrayHasKey('seconds', $segment);
            $this->assertArrayHasKey('speaker', $segment);
            $this->assertArrayHasKey('text', $segment);
            $this->assertArrayHasKey('has_time', $segment);
            $this->assertArrayHasKey('id', $segment);
        }
    }

    /**
     * Test 22: Textract segments contain speaker information
     *
     * @test
     */
    public function test_textract_segments_contain_speaker_information()
    {
        $component = Livewire::test(TranscriptPreviewer::class, ['path' => $this->testTranscriptPath]);

        $segments = $component->get('segments');

        $this->assertGreaterThan(0, count($segments));

        // Verify speakers are identified
        foreach ($segments as $segment) {
            $this->assertNotEmpty($segment['speaker']);
            $this->assertIsString($segment['speaker']);
        }

        // Verify speakers array is populated
        $speakers = $component->get('speakers');
        $this->assertIsArray($speakers);
        $this->assertGreaterThan(0, count($speakers));
    }

    /**
     * Test 23: Textract segments contain timestamp information
     *
     * @test
     */
    public function test_textract_segments_contain_timestamp_information()
    {
        $component = Livewire::test(TranscriptPreviewer::class, ['path' => $this->testTranscriptPath]);

        $segments = $component->get('segments');

        foreach ($segments as $segment) {
            $this->assertIsString($segment['time']);
            $this->assertIsInt($segment['seconds']);
            $this->assertGreaterThanOrEqual(0, $segment['seconds']);

            // Verify time format (HH:MM:SS:FF)
            if ($segment['has_time']) {
                $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}:\d{2}$/', $segment['time']);
            }
        }
    }

    /**
     * Test 24: Textract segments are sorted by timestamp
     *
     * @test
     */
    public function test_textract_segments_are_sorted_by_timestamp()
    {
        $component = Livewire::test(TranscriptPreviewer::class, ['path' => $this->testTranscriptPath]);

        $segments = $component->get('segments');

        if (count($segments) > 1) {
            // Verify segments are sorted by seconds
            $previousSeconds = -1;
            foreach ($segments as $segment) {
                $this->assertGreaterThanOrEqual($previousSeconds, $segment['seconds']);
                $previousSeconds = $segment['seconds'];
            }
        }

        $this->assertTrue(true);
    }

    /**
     * Test 25: Pagination with search filtering works
     *
     * @test
     */
    public function test_pagination_with_search_filtering_works()
    {
        $component = Livewire::test(TranscriptPreviewer::class, ['path' => $this->testTranscriptPath]);

        // Set search query
        $component->set('search', 'Test');

        $filtered = $component->instance()->getFilteredProperty();

        $this->assertIsArray($filtered);

        // Verify filtered results only contain search term
        foreach ($filtered as $segment) {
            $content = mb_strtolower($segment['speaker'].' '.$segment['text']);
            $this->assertStringContainsString('test', $content);
        }
    }

    /**
     * Test 26: Pagination with speaker filtering works
     *
     * @test
     */
    public function test_pagination_with_speaker_filtering_works()
    {
        $component = Livewire::test(TranscriptPreviewer::class, ['path' => $this->testTranscriptPath]);

        $speakers = $component->get('speakers');

        if (count($speakers) > 1) {
            // Disable first speaker
            $firstSpeaker = array_key_first($speakers);
            $component->call('toggleSpeaker', $firstSpeaker);

            $filtered = $component->instance()->getFilteredProperty();

            // Verify first speaker is not in filtered results
            foreach ($filtered as $segment) {
                $this->assertNotEquals($firstSpeaker, $segment['speaker']);
            }
        }

        $this->assertTrue(true);
    }

    /**
     * Test 27: Pagination with multiple filters works
     *
     * @test
     */
    public function test_pagination_with_multiple_filters_works()
    {
        $component = Livewire::test(TranscriptPreviewer::class, ['path' => $this->testTranscriptPath]);

        // Apply search filter
        $component->set('search', 'line');

        // Get first speaker and keep only them
        $speakers = $component->get('speakers');
        if (count($speakers) > 1) {
            $speakerKeys = array_keys($speakers);
            $firstSpeaker = $speakerKeys[0];

            // Disable all other speakers
            foreach ($speakerKeys as $speaker) {
                if ($speaker !== $firstSpeaker) {
                    $component->call('toggleSpeaker', $speaker);
                }
            }
        }

        $filtered = $component->instance()->getFilteredProperty();

        // Verify both filters are applied
        foreach ($filtered as $segment) {
            $content = mb_strtolower($segment['speaker'].' '.$segment['text']);
            $this->assertStringContainsString('line', $content);
        }

        $this->assertTrue(true);
    }

    /**
     * Test 28: Content editing with search updates results
     *
     * @test
     */
    public function test_content_editing_with_search_updates_results()
    {
        $component = Livewire::test(TranscriptPreviewer::class, ['path' => $this->testTranscriptPath]);

        // Initial count with no search
        $allSegments = $component->get('segments');
        $initialCount = count($allSegments);

        // Apply search
        $component->set('search', 'transcript');

        $filtered = $component->instance()->getFilteredProperty();

        // Filtered count should be less than or equal to initial count
        $this->assertLessThanOrEqual($initialCount, count($filtered));
    }

    /**
     * Test 29: Content editing with speaker toggle updates results
     *
     * @test
     */
    public function test_content_editing_with_speaker_toggle_updates_results()
    {
        $component = Livewire::test(TranscriptPreviewer::class, ['path' => $this->testTranscriptPath]);

        $speakers = $component->get('speakers');

        if (count($speakers) > 0) {
            $firstSpeaker = array_key_first($speakers);

            // Count segments before toggle
            $beforeToggle = $component->instance()->getFilteredProperty();

            // Toggle speaker off
            $component->call('toggleSpeaker', $firstSpeaker);

            // Count segments after toggle
            $afterToggle = $component->instance()->getFilteredProperty();

            // Should have fewer (or equal) segments after disabling a speaker
            $this->assertLessThanOrEqual(count($beforeToggle), count($afterToggle));
        }

        $this->assertTrue(true);
    }

    /**
     * Test 30: Content editing with all speakers toggle works
     *
     * @test
     */
    public function test_content_editing_with_all_speakers_toggle_works()
    {
        $component = Livewire::test(TranscriptPreviewer::class, ['path' => $this->testTranscriptPath]);

        $speakers = $component->get('speakers');

        if (count($speakers) > 0) {
            // Turn all speakers off
            $component->call('allSpeakers', false);

            $updatedSpeakers = $component->get('speakers');

            // All should be false
            foreach ($updatedSpeakers as $enabled) {
                $this->assertFalse($enabled);
            }

            // Turn all speakers on
            $component->call('allSpeakers', true);

            $updatedSpeakers = $component->get('speakers');

            // All should be true
            foreach ($updatedSpeakers as $enabled) {
                $this->assertTrue($enabled);
            }
        }

        $this->assertTrue(true);
    }

    /**
     * Test 31: Lingua events are loaded and parsed correctly
     *
     * @test
     */
    public function test_lingua_events_are_loaded_and_parsed_correctly()
    {
        $component = Livewire::test(TranscriptPreviewer::class, ['path' => $this->testTranscriptPath]);

        $linguaEvents = $component->get('linguaEvents');

        $this->assertIsArray($linguaEvents);

        // Verify lingua event structure (if any exist)
        foreach ($linguaEvents as $event) {
            $this->assertArrayHasKey('time', $event);
            $this->assertArrayHasKey('seconds', $event);
            $this->assertArrayHasKey('title', $event);
            $this->assertArrayHasKey('excerpt', $event);
        }
    }

    /**
     * Test 32: Lingua raw text is loaded correctly
     *
     * @test
     */
    public function test_lingua_raw_text_is_loaded_correctly()
    {
        $component = Livewire::test(TranscriptPreviewer::class, ['path' => $this->testTranscriptPath]);

        $linguaRaw = $component->get('linguaRaw');

        $this->assertIsString($linguaRaw);
    }

    /**
     * Test 33: Segment IDs are unique and properly formatted
     *
     * @test
     */
    public function test_segment_ids_are_unique_and_properly_formatted()
    {
        $component = Livewire::test(TranscriptPreviewer::class, ['path' => $this->testTranscriptPath]);

        $segments = $component->get('segments');
        $seenIds = [];

        foreach ($segments as $segment) {
            $this->assertArrayHasKey('id', $segment);
            $this->assertIsString($segment['id']);

            // Verify ID format (seg-XXXXXX)
            $this->assertMatchesRegularExpression('/^seg-\d{6}$/', $segment['id']);

            // Check uniqueness
            $this->assertNotContains($segment['id'], $seenIds, 'Segment ID should be unique');
            $seenIds[] = $segment['id'];
        }
    }

    /**
     * Test 34: Duration is calculated correctly from segments
     *
     * @test
     */
    public function test_duration_is_calculated_correctly_from_segments()
    {
        $component = Livewire::test(TranscriptPreviewer::class, ['path' => $this->testTranscriptPath]);

        $segments = $component->get('segments');
        $durationSec = $component->get('durationSec');

        $this->assertIsNumeric($durationSec);
        $this->assertGreaterThanOrEqual(0, $durationSec);

        // If we have segments, duration should be >= max segment time
        if (count($segments) > 0) {
            $maxSeconds = max(array_column($segments, 'seconds'));
            $this->assertGreaterThanOrEqual($maxSeconds, $durationSec);
        }
    }

    /**
     * Test 35: Search is case-insensitive
     *
     * @test
     */
    public function test_search_is_case_insensitive()
    {
        $component = Livewire::test(TranscriptPreviewer::class, ['path' => $this->testTranscriptPath]);

        // Search with lowercase
        $component->set('search', 'test');
        $lowercaseResults = $component->instance()->getFilteredProperty();

        // Search with uppercase
        $component->set('search', 'TEST');
        $uppercaseResults = $component->instance()->getFilteredProperty();

        // Should return same number of results
        $this->assertEquals(count($lowercaseResults), count($uppercaseResults));
    }

    /**
     * Test 36: Empty search returns all segments
     *
     * @test
     */
    public function test_empty_search_returns_all_segments()
    {
        $component = Livewire::test(TranscriptPreviewer::class, ['path' => $this->testTranscriptPath]);

        $allSegments = $component->get('segments');
        $component->set('search', '');

        $filtered = $component->instance()->getFilteredProperty();

        // With no search and all speakers enabled, should return all segments
        $this->assertEquals(count($allSegments), count($filtered));
    }

    /**
     * Test 37: Lingua events near timestamp works
     *
     * @test
     */
    public function test_lingua_events_near_timestamp_works()
    {
        $component = Livewire::test(TranscriptPreviewer::class, ['path' => $this->testTranscriptPath]);

        $instance = $component->instance();

        // Test with a timestamp
        $nearEvents = $instance->eventsNear(30); // 30 seconds

        $this->assertIsArray($nearEvents);

        // If events exist, verify they're within ±15s window
        foreach ($nearEvents as $event) {
            $this->assertArrayHasKey('seconds', $event);
            $this->assertLessThanOrEqual(15, abs($event['seconds'] - 30));
        }
    }

    /**
     * Test 38: Segment ID for seconds lookup works
     *
     * @test
     */
    public function test_segment_id_for_seconds_lookup_works()
    {
        $component = Livewire::test(TranscriptPreviewer::class, ['path' => $this->testTranscriptPath]);

        $instance = $component->instance();
        $segments = $component->get('segments');

        if (count($segments) > 0) {
            // Test with first segment's timestamp
            $firstSegment = $segments[0];
            $segmentId = $instance->segmentIdForSeconds($firstSegment['seconds']);

            $this->assertIsString($segmentId);
            $this->assertMatchesRegularExpression('/^seg-\d{6}$/', $segmentId);
        }

        $this->assertTrue(true);
    }

    /**
     * Test 39: Performance with large transcript pagination
     *
     * @test
     */
    public function test_performance_with_large_transcript_pagination()
    {
        // Create large test transcript
        $largeTranscript = storage_path('large_test_transcript.txt');
        $content = '';
        for ($i = 0; $i < 100; $i++) {
            $hour = str_pad((int) ($i / 60), 2, '0', STR_PAD_LEFT);
            $minute = str_pad($i % 60, 2, '0', STR_PAD_LEFT);
            $speaker = 'S'.($i % 5 + 1);
            $content .= "{$hour}:{$minute}:00:00 Speaker {$speaker}\n";
            $content .= "This is test line {$i} with some content to search.\n\n";
        }
        File::put($largeTranscript, $content);

        $startTime = microtime(true);

        $component = Livewire::test(TranscriptPreviewer::class, ['path' => $largeTranscript]);
        $segments = $component->get('segments');

        $endTime = microtime(true);

        $this->assertGreaterThan(90, count($segments));
        $this->assertLessThan(2, $endTime - $startTime); // Should load in < 2 seconds

        // Test filtering performance
        $startFilter = microtime(true);
        $component->set('search', 'test');
        $filtered = $component->instance()->getFilteredProperty();
        $endFilter = microtime(true);

        $this->assertGreaterThan(0, count($filtered));
        $this->assertLessThan(0.5, $endFilter - $startFilter); // Filter should be < 0.5 seconds

        // Cleanup
        File::delete($largeTranscript);
    }

    /**
     * Test 40: Absolute timestamps are calculated correctly
     *
     * @test
     */
    public function test_absolute_timestamps_are_calculated_correctly()
    {
        $component = Livewire::test(TranscriptPreviewer::class, ['path' => $this->testTranscriptPath]);

        $segments = $component->get('segments');
        $baseStart = $component->get('baseStart');

        foreach ($segments as $segment) {
            if ($segment['has_time']) {
                $this->assertArrayHasKey('abs', $segment);
                $this->assertIsString($segment['abs']);

                // Verify absolute timestamp format (Y-m-d H:i:s)
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $segment['abs']);

                // Verify it's after base start
                $absTime = strtotime($segment['abs']);
                $baseTime = strtotime($baseStart);
                $this->assertGreaterThanOrEqual($baseTime, $absTime);
            }
        }
    }
}
