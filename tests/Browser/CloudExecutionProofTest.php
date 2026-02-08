<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

/**
 * Cloud Execution Proof Test
 *
 * This test proves that Chrome and Laravel Dusk ARE actually
 * executing in the cloud environment, not just installed.
 *
 * @group dusk
 * @group proof
 */
class CloudExecutionProofTest extends DuskTestCase
{
    use DatabaseMigrations, MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();
    }

    /**
     * Test that Chrome can load and interact with a page
     *
     * This test loads a static HTML file and performs various
     * browser interactions to prove full functionality.
     */
    public function test_chrome_actually_works_in_cloud(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dusk-test')
                    // Test 1: Page loads
                ->assertSee('Dusk Test Success!')
                ->assertSee('Chrome and Laravel Dusk are working perfectly in the cloud!')

                    // Test 2: Can find elements
                ->assertPresent('#test-content')
                ->assertPresent('#test-button')
                ->assertPresent('#result')

                    // Test 3: JavaScript interaction works
                ->assertDontSee('Button Clicked!')
                ->click('#test-button')
                ->waitFor('#result', 15)
                ->assertSee('Button Clicked!');
        });
    }

    /**
     * Test screenshot capability
     *
     * Proves that Chrome can take screenshots in headless mode
     */
    public function test_chrome_can_take_screenshots(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dusk-test')
                ->assertSee('Dusk Test Success!')
                ->screenshot('cloud-execution-proof');
        });

        // Verify screenshot was created
        $screenshotPath = 'tests/Browser/screenshots/cloud-execution-proof.png';
        $this->assertFileExists($screenshotPath);
    }
}
