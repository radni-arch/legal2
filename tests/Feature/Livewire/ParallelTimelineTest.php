<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\ParallelTimeline;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ParallelTimelineTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Test 1: Component renders correctly
     *
     * @test
     */
    public function test_component_renders_correctly()
    {
        Livewire::test(ParallelTimeline::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.parallel-timeline');
    }

    /**
     * Test 2: Component class exists
     *
     * @test
     */
    public function test_component_class_exists()
    {
        $this->assertTrue(class_exists(ParallelTimeline::class));
    }

    /**
     * Test 3: Component is a Livewire component
     *
     * @test
     */
    public function test_component_is_livewire_component()
    {
        $component = new ParallelTimeline;
        $this->assertInstanceOf(\Livewire\Component::class, $component);
    }

    /**
     * Test 4: Component can be instantiated
     *
     * @test
     */
    public function test_component_can_be_instantiated()
    {
        $component = Livewire::test(ParallelTimeline::class);
        $this->assertNotNull($component);
    }

    /**
     * Test 5: View renders without errors
     *
     * @test
     */
    public function test_view_renders_without_errors()
    {
        $this->withoutExceptionHandling();

        Livewire::test(ParallelTimeline::class)
            ->assertSuccessful();
    }
}
