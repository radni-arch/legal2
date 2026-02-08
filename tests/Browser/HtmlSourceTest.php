<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

class HtmlSourceTest extends DuskTestCase
{
    use MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();
    }

    /**
     * Check HTML source of login page
     *
     * @test
     */
    public function test_login_page_html_source(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login');

            $html = $browser->driver->getPageSource();

            // Save to file for inspection
            file_put_contents('/tmp/login-page-source.html', $html);

            // Assert we have some content
            $this->assertNotEmpty($html);
            $this->assertStringContainsString('<html', $html);
        });
    }
}
