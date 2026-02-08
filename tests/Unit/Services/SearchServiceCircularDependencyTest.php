<?php

namespace Tests\Unit\Services;

use App\Services\CaseSearchService;
use App\Services\DecisionSearchService;
use App\Services\LawSearchService;
use Tests\TestCase;

/**
 * Tests that search services can be resolved without circular dependency.
 *
 * Root cause: DecisionSearchService injected DecisionSearchTool (MCP),
 * which injected DecisionSearchService back - infinite recursion.
 * Same pattern for LawSearchService and CaseSearchService.
 *
 * Fix: Removed unused circular MCP tool injections from search service
 * constructors and made remaining tools lazy-resolved via accessor methods.
 */
class SearchServiceCircularDependencyTest extends TestCase
{
    /** @test */
    public function decision_search_service_can_be_resolved_without_circular_dependency(): void
    {
        $memBefore = memory_get_usage(true);

        $service = app(DecisionSearchService::class);

        $memAfter = memory_get_usage(true);
        $memDelta = ($memAfter - $memBefore) / 1024 / 1024;

        $this->assertInstanceOf(DecisionSearchService::class, $service);
        $this->assertLessThan(50, $memDelta, "DecisionSearchService resolution consumed {$memDelta}MB");
    }

    /** @test */
    public function law_search_service_can_be_resolved_without_circular_dependency(): void
    {
        $memBefore = memory_get_usage(true);

        $service = app(LawSearchService::class);

        $memAfter = memory_get_usage(true);
        $memDelta = ($memAfter - $memBefore) / 1024 / 1024;

        $this->assertInstanceOf(LawSearchService::class, $service);
        $this->assertLessThan(50, $memDelta, "LawSearchService resolution consumed {$memDelta}MB");
    }

    /** @test */
    public function case_search_service_can_be_resolved_without_circular_dependency(): void
    {
        $memBefore = memory_get_usage(true);

        $service = app(CaseSearchService::class);

        $memAfter = memory_get_usage(true);
        $memDelta = ($memAfter - $memBefore) / 1024 / 1024;

        $this->assertInstanceOf(CaseSearchService::class, $service);
        $this->assertLessThan(50, $memDelta, "CaseSearchService resolution consumed {$memDelta}MB");
    }
}
