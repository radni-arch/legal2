<?php

namespace Tests\Unit\Events;

use App\Events\CaseReadyForReview;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Tests\TestCase;

class CaseReadyForReviewTest extends TestCase
{
    /** @test */
    public function it_stores_case_id()
    {
        $event = new CaseReadyForReview('case-123');

        $this->assertEquals('case-123', $event->caseId);
    }

    /** @test */
    public function it_uses_dispatchable_trait()
    {
        $reflection = new \ReflectionClass(CaseReadyForReview::class);
        $traits = $reflection->getTraitNames();

        $this->assertContains(Dispatchable::class, $traits);
    }

    /** @test */
    public function it_uses_serializes_models_trait()
    {
        $reflection = new \ReflectionClass(CaseReadyForReview::class);
        $traits = $reflection->getTraitNames();

        $this->assertContains(SerializesModels::class, $traits);
    }

    /** @test */
    public function it_can_be_instantiated_with_string_case_id()
    {
        $event = new CaseReadyForReview('abc-def-ghi');

        $this->assertInstanceOf(CaseReadyForReview::class, $event);
        $this->assertIsString($event->caseId);
    }
}
