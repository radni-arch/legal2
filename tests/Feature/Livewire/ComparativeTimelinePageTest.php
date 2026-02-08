<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\ComparativeTimelinePage;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ComparativeTimelinePageTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Test 1: Component renders correctly
     *
     * @test
     */
    public function test_component_renders_correctly()
    {
        Livewire::test(ComparativeTimelinePage::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.comparative-timeline-page');
    }

    /**
     * Test 2: Component initializes with timeline data
     *
     * @test
     */
    public function test_component_initializes_with_timeline_data()
    {
        $component = Livewire::test(ComparativeTimelinePage::class);

        $this->assertNotNull($component->get('dataTopJs'));
        $this->assertNotNull($component->get('dataBottomJs'));
    }

    /**
     * Test 3: Top timeline data is valid JSON
     *
     * @test
     */
    public function test_top_timeline_data_is_valid_json()
    {
        $component = Livewire::test(ComparativeTimelinePage::class);

        $dataTopJs = $component->get('dataTopJs');

        $this->assertIsString($dataTopJs);
        $decoded = json_decode($dataTopJs, true);
        $this->assertNotNull($decoded);
        $this->assertIsArray($decoded);
    }

    /**
     * Test 4: Bottom timeline data is valid JSON
     *
     * @test
     */
    public function test_bottom_timeline_data_is_valid_json()
    {
        $component = Livewire::test(ComparativeTimelinePage::class);

        $dataBottomJs = $component->get('dataBottomJs');

        $this->assertIsString($dataBottomJs);
        $decoded = json_decode($dataBottomJs, true);
        $this->assertNotNull($decoded);
        $this->assertIsArray($decoded);
    }

    /**
     * Test 5: Top timeline contains title
     *
     * @test
     */
    public function test_top_timeline_contains_title()
    {
        $component = Livewire::test(ComparativeTimelinePage::class);

        $dataTopJs = $component->get('dataTopJs');
        $decoded = json_decode($dataTopJs, true);

        $this->assertArrayHasKey('title', $decoded);
    }

    /**
     * Test 6: Top timeline contains events
     *
     * @test
     */
    public function test_top_timeline_contains_events()
    {
        $component = Livewire::test(ComparativeTimelinePage::class);

        $dataTopJs = $component->get('dataTopJs');
        $decoded = json_decode($dataTopJs, true);

        $this->assertArrayHasKey('events', $decoded);
        $this->assertIsArray($decoded['events']);
        $this->assertNotEmpty($decoded['events']);
    }

    /**
     * Test 7: Bottom timeline contains title
     *
     * @test
     */
    public function test_bottom_timeline_contains_title()
    {
        $component = Livewire::test(ComparativeTimelinePage::class);

        $dataBottomJs = $component->get('dataBottomJs');
        $decoded = json_decode($dataBottomJs, true);

        $this->assertArrayHasKey('title', $decoded);
    }

    /**
     * Test 8: Bottom timeline contains events
     *
     * @test
     */
    public function test_bottom_timeline_contains_events()
    {
        $component = Livewire::test(ComparativeTimelinePage::class);

        $dataBottomJs = $component->get('dataBottomJs');
        $decoded = json_decode($dataBottomJs, true);

        $this->assertArrayHasKey('events', $decoded);
        $this->assertIsArray($decoded['events']);
        $this->assertNotEmpty($decoded['events']);
    }

    /**
     * Test 9: Timeline events have required structure
     *
     * @test
     */
    public function test_timeline_events_have_required_structure()
    {
        $component = Livewire::test(ComparativeTimelinePage::class);

        $dataTopJs = $component->get('dataTopJs');
        $decoded = json_decode($dataTopJs, true);

        $firstEvent = $decoded['events'][0];

        $this->assertArrayHasKey('start_date', $firstEvent);
        $this->assertArrayHasKey('text', $firstEvent);
    }

    /**
     * Test 10: Component renders without errors
     *
     * @test
     */
    public function test_component_renders_without_errors()
    {
        $this->withoutExceptionHandling();

        Livewire::test(ComparativeTimelinePage::class)
            ->assertSuccessful();
    }
}
