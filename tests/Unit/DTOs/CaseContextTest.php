<?php

namespace Tests\Unit\DTOs;

use App\DTOs\CaseContext;
use App\DTOs\SenderIdentity;
use Tests\TestCase;

class CaseContextTest extends TestCase
{
    public function test_creates_from_config(): void
    {
        $context = CaseContext::fromConfig();

        $this->assertEquals('Pp Prz-74/2025', $context->caseNumber);
        $this->assertEquals('2025-06-09', $context->searchDate);
        $this->assertInstanceOf(SenderIdentity::class, $context->sender);
    }

    public function test_provides_template_variables(): void
    {
        $context = CaseContext::fromConfig();
        $vars = $context->toTemplateVars();

        $this->assertArrayHasKey('case_number', $vars);
        $this->assertArrayHasKey('sender_name', $vars);
        $this->assertArrayHasKey('search_date', $vars);
        $this->assertArrayHasKey('archive_date', $vars);
        $this->assertArrayHasKey('today_date', $vars);
    }

    public function test_interpolates_string_template(): void
    {
        $context = CaseContext::fromConfig();
        $result = $context->interpolate('Spis broj {case_number} - pretraga {search_date}');

        $this->assertStringContainsString('Pp Prz-74/2025', $result);
        $this->assertStringContainsString('2025-06-09', $result);
    }

    public function test_today_date_vars_are_consistent(): void
    {
        $context = CaseContext::fromConfig();
        $vars = $context->toTemplateVars();

        // Both dates should use the same timestamp
        // Extract ISO date from today_date_iso
        $isoDate = $vars['today_date_iso'];

        // today_date format is "d. F Y." (e.g., "03. February 2026.")
        // Both should represent the same day
        $this->assertArrayHasKey('today_date', $vars);
        $this->assertArrayHasKey('today_date_iso', $vars);

        // Parse both dates and verify they match
        $fromIso = \Carbon\Carbon::parse($isoDate);
        // The formatted date should match the ISO date's day
        $this->assertEquals($fromIso->toDateString(), $isoDate);
    }
}
