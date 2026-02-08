<?php

namespace Tests\Unit\Services\Navigation;

use App\Services\Navigation\Breadcrumb;
use App\Services\Navigation\BreadcrumbService;
use PHPUnit\Framework\TestCase;

class BreadcrumbServiceTest extends TestCase
{
    private BreadcrumbService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BreadcrumbService();
    }

    // ============================================
    // Breadcrumb Value Object Tests (from Task 1)
    // ============================================

    /** @test */
    public function breadcrumb_value_object_holds_label_url_and_active_state(): void
    {
        $breadcrumb = new Breadcrumb('Dashboard', '/dashboard', false);

        $this->assertEquals('Dashboard', $breadcrumb->label);
        $this->assertEquals('/dashboard', $breadcrumb->url);
        $this->assertFalse($breadcrumb->isActive);
    }

    /** @test */
    public function breadcrumb_can_be_created_as_active(): void
    {
        $breadcrumb = new Breadcrumb('Current Page', null, true);

        $this->assertEquals('Current Page', $breadcrumb->label);
        $this->assertNull($breadcrumb->url);
        $this->assertTrue($breadcrumb->isActive);
    }

    // ============================================
    // BreadcrumbService Tests (Task 2)
    // ============================================

    /** @test */
    public function can_register_a_route_with_breadcrumb_config(): void
    {
        $this->service->register('dashboard', [
            'label' => 'Dashboard',
        ]);

        $this->assertTrue($this->service->has('dashboard'));
    }

    /** @test */
    public function can_register_route_with_parent(): void
    {
        $this->service->register('dashboard', ['label' => 'Dashboard']);
        $this->service->register('chatbot', [
            'label' => 'AI Chatbot',
            'parent' => 'dashboard',
        ]);

        $this->assertTrue($this->service->has('chatbot'));
        $this->assertEquals('dashboard', $this->service->getParent('chatbot'));
    }

    /** @test */
    public function generates_breadcrumb_trail_for_single_level(): void
    {
        $this->service->register('dashboard', ['label' => 'Dashboard']);

        $trail = $this->service->generate('dashboard', '/dashboard');

        $this->assertCount(1, $trail);
        $this->assertEquals('Dashboard', $trail[0]->label);
        $this->assertTrue($trail[0]->isActive);
    }

    /** @test */
    public function generates_breadcrumb_trail_with_parent_chain(): void
    {
        $this->service->register('dashboard', ['label' => 'Dashboard']);
        $this->service->register('ekom.dashboard', [
            'label' => 'E-Komunikacije',
            'parent' => 'dashboard',
        ]);
        $this->service->register('ekom.predmeti', [
            'label' => 'Cases',
            'parent' => 'ekom.dashboard',
        ]);

        $trail = $this->service->generate('ekom.predmeti', '/ekom/predmeti');

        $this->assertCount(3, $trail);
        $this->assertEquals('Dashboard', $trail[0]->label);
        $this->assertFalse($trail[0]->isActive);
        $this->assertEquals('E-Komunikacije', $trail[1]->label);
        $this->assertFalse($trail[1]->isActive);
        $this->assertEquals('Cases', $trail[2]->label);
        $this->assertTrue($trail[2]->isActive);
    }

    // ============================================
    // Bulk Registration Tests (Task 3)
    // ============================================

    /** @test */
    public function service_can_bulk_register_routes(): void
    {
        $this->service->registerMany([
            'dashboard' => ['label' => 'Dashboard'],
            'chatbot' => ['label' => 'AI Chatbot', 'parent' => 'dashboard'],
            'search' => ['label' => 'Search', 'parent' => 'dashboard'],
        ]);

        $this->assertTrue($this->service->has('dashboard'));
        $this->assertTrue($this->service->has('chatbot'));
        $this->assertTrue($this->service->has('search'));
    }
}
