<?php

namespace Tests\Unit\TestData\Builders;

use App\Models\Evidence;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Tests\TestData\Builders\EvidenceChainBuilder;
use Tests\UsesTestDatabase;

class EvidenceChainBuilderTest extends TestCase
{
    use UsesTestDatabase;

    public function test_can_build_evidence_chain_with_communication(): void
    {
        $count = 5;
        $evidence = EvidenceChainBuilder::make()
            ->withCommunicationChain($count)
            ->build();

        $this->assertInstanceOf(Collection::class, $evidence);
        $this->assertCount($count, $evidence);

        // Verify all items are Evidence models
        $evidence->each(function ($item) {
            $this->assertInstanceOf(Evidence::class, $item);
        });

        // Verify communication type
        $communicationEvidence = $evidence->where('type', 'communication');
        $this->assertGreaterThan(0, $communicationEvidence->count());
    }

    public function test_can_build_evidence_chain_with_physical_evidence(): void
    {
        $evidence = EvidenceChainBuilder::make()
            ->withPhysicalEvidence()
            ->build();

        $this->assertInstanceOf(Collection::class, $evidence);
        $this->assertGreaterThan(0, $evidence->count());

        // Verify physical evidence exists
        $physicalEvidence = $evidence->where('type', 'physical');
        $this->assertGreaterThan(0, $physicalEvidence->count());
    }

    public function test_can_build_evidence_chain_with_timeline(): void
    {
        $evidence = EvidenceChainBuilder::make()
            ->withTimeline()
            ->build();

        $this->assertInstanceOf(Collection::class, $evidence);
        $this->assertGreaterThan(0, $evidence->count());

        // Verify temporal consistency - timestamps should be in order
        $timestamps = $evidence->pluck('created_at')->map(fn ($ts) => $ts->timestamp)->toArray();
        $sortedTimestamps = $timestamps;
        sort($sortedTimestamps);

        $this->assertEquals($sortedTimestamps, $timestamps, 'Evidence timestamps should be in chronological order');
    }

    public function test_can_build_complex_evidence_chain(): void
    {
        $evidence = EvidenceChainBuilder::make()
            ->withCommunicationChain(3)
            ->withPhysicalEvidence()
            ->withTimeline()
            ->build();

        $this->assertInstanceOf(Collection::class, $evidence);
        $this->assertGreaterThanOrEqual(4, $evidence->count()); // 3 communications + 1 physical

        // Verify timeline consistency
        $timestamps = $evidence->pluck('created_at')->map(fn ($ts) => $ts->timestamp)->toArray();
        $sortedTimestamps = $timestamps;
        sort($sortedTimestamps);
        $this->assertEquals($sortedTimestamps, $timestamps);
    }

    public function test_evidence_chain_can_be_empty(): void
    {
        $evidence = EvidenceChainBuilder::make()->build();

        $this->assertInstanceOf(Collection::class, $evidence);
        $this->assertCount(0, $evidence);
    }

    public function test_builder_returns_fresh_instance_on_make(): void
    {
        $builder1 = EvidenceChainBuilder::make();
        $builder2 = EvidenceChainBuilder::make();

        $this->assertNotSame($builder1, $builder2);
    }
}
