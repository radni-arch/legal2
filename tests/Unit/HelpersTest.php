<?php

namespace Tests\Unit;

use App\Services\Navigation\Breadcrumb;
use Tests\TestCase;

class HelpersTest extends TestCase
{
    /** @test */
    public function breadcrumbs_helper_returns_trail_for_registered_route(): void
    {
        $result = breadcrumbs('ekom.predmeti');

        $this->assertNotEmpty($result);
        $this->assertContainsOnlyInstancesOf(Breadcrumb::class, $result);
    }

    /** @test */
    public function breadcrumbs_helper_returns_empty_for_unknown_route(): void
    {
        $result = breadcrumbs('unknown.route.xyz');

        $this->assertEmpty($result);
    }

    /** @test */
    public function breadcrumbs_helper_returns_correct_trail(): void
    {
        $result = breadcrumbs('ekom.predmeti');

        // Should have: Dashboard > E-Komunikacije > Cases
        $this->assertCount(3, $result);
        $this->assertEquals('Dashboard', $result[0]->label);
        $this->assertEquals('E-Komunikacije', $result[1]->label);
        $this->assertEquals('Cases (Predmeti)', $result[2]->label);
        $this->assertTrue($result[2]->isActive);
    }
}
