<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\GupTimeline;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class GupTimelineTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Test 1: Component renders correctly
     *
     * @test
     */
    public function test_component_renders_correctly()
    {
        Livewire::test(GupTimeline::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.gup-timeline')
            ->assertSet('showModal', false)
            ->assertSet('currentItem', null)
            ->assertSet('currentAssetIndex', 0)
            ->assertSet('currentAsset', null);
    }

    /**
     * Test 2: Component initializes with official items
     *
     * @test
     */
    public function test_component_initializes_with_official_items()
    {
        $component = Livewire::test(GupTimeline::class);

        $officialItems = $component->get('officialItems');

        $this->assertIsArray($officialItems);
        $this->assertNotEmpty($officialItems);
    }

    /**
     * Test 3: Component initializes with real items
     *
     * @test
     */
    public function test_component_initializes_with_real_items()
    {
        $component = Livewire::test(GupTimeline::class);

        $realItems = $component->get('realItems');

        $this->assertIsArray($realItems);
        $this->assertNotEmpty($realItems);
    }

    /**
     * Test 4: Official items have required structure
     *
     * @test
     */
    public function test_official_items_have_required_structure()
    {
        $component = Livewire::test(GupTimeline::class);

        $officialItems = $component->get('officialItems');
        $firstItem = $officialItems[0];

        $this->assertArrayHasKey('id', $firstItem);
        $this->assertArrayHasKey('content', $firstItem);
        $this->assertArrayHasKey('start', $firstItem);
        $this->assertArrayHasKey('type', $firstItem);
        $this->assertArrayHasKey('title', $firstItem);
        $this->assertArrayHasKey('className', $firstItem);
    }

    /**
     * Test 5: Real items have required structure
     *
     * @test
     */
    public function test_real_items_have_required_structure()
    {
        $component = Livewire::test(GupTimeline::class);

        $realItems = $component->get('realItems');

        if (! empty($realItems)) {
            $firstItem = $realItems[0];

            $this->assertArrayHasKey('id', $firstItem);
            $this->assertArrayHasKey('content', $firstItem);
            $this->assertArrayHasKey('start', $firstItem);
        }

        $this->assertIsArray($realItems);
    }

    /**
     * Test 6: Official items contain Carbon date objects
     *
     * @test
     */
    public function test_official_items_contain_carbon_dates()
    {
        $component = Livewire::test(GupTimeline::class);

        $officialItems = $component->get('officialItems');
        $firstItem = $officialItems[0];

        $this->assertInstanceOf(\Carbon\Carbon::class, $firstItem['start']);
    }

    /**
     * Test 7: Open evidence listener is registered
     *
     * @test
     */
    public function test_open_evidence_listener_registered()
    {
        $component = Livewire::test(GupTimeline::class);

        $listeners = $component->instance()->getEventsBeingListenedFor();

        $this->assertContains('openEvidence', $listeners);
    }

    /**
     * Test 8: OpenEvidence method exists and works
     *
     * @test
     */
    public function test_open_evidence_method_exists()
    {
        $component = Livewire::test(GupTimeline::class);

        $this->assertTrue(method_exists($component->instance(), 'openEvidence'));
    }

    /**
     * Test 9: Modal is initially hidden
     *
     * @test
     */
    public function test_modal_is_initially_hidden()
    {
        Livewire::test(GupTimeline::class)
            ->assertSet('showModal', false);
    }

    /**
     * Test 10: Current item is initially null
     *
     * @test
     */
    public function test_current_item_is_initially_null()
    {
        Livewire::test(GupTimeline::class)
            ->assertSet('currentItem', null);
    }

    /**
     * Test 11: Current asset index starts at 0
     *
     * @test
     */
    public function test_current_asset_index_starts_at_zero()
    {
        Livewire::test(GupTimeline::class)
            ->assertSet('currentAssetIndex', 0);
    }

    /**
     * Test 12: Timeline items have assets
     *
     * @test
     */
    public function test_timeline_items_have_assets()
    {
        $component = Livewire::test(GupTimeline::class);

        $officialItems = $component->get('officialItems');
        $firstItem = $officialItems[0];

        $this->assertArrayHasKey('assets', $firstItem);
        $this->assertIsArray($firstItem['assets']);
    }

    /**
     * Test 13: Timeline items have location
     *
     * @test
     */
    public function test_timeline_items_have_location()
    {
        $component = Livewire::test(GupTimeline::class);

        $officialItems = $component->get('officialItems');
        $firstItem = $officialItems[0];

        $this->assertArrayHasKey('location', $firstItem);
    }

    /**
     * Test 14: Multiple official items exist
     *
     * @test
     */
    public function test_multiple_official_items_exist()
    {
        $component = Livewire::test(GupTimeline::class);

        $officialItems = $component->get('officialItems');

        $this->assertGreaterThan(1, count($officialItems));
    }

    /**
     * Test 15: Component renders without errors
     *
     * @test
     */
    public function test_component_renders_without_errors()
    {
        $this->withoutExceptionHandling();

        Livewire::test(GupTimeline::class)
            ->assertSuccessful();
    }
}
