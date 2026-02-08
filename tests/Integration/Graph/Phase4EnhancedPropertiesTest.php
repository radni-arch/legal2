<?php

namespace Tests\Integration\Graph;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Models\Law;
use App\Services\Graph\DecisionGraphSyncService;
use App\Services\Graph\LawGraphSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for Phase 4: Enhanced Node Properties
 *
 * Tests verify that Phase 4 properties (dissent/concurrence counts,
 * amendment tracking) are properly synced from PostgreSQL to Neo4j.
 */
class Phase4EnhancedPropertiesTest extends GraphIntegrationTestCase
{
    use RefreshDatabase;

    protected DecisionGraphSyncService $decisionSyncService;

    protected LawGraphSyncService $lawSyncService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->decisionSyncService = app(DecisionGraphSyncService::class);
        $this->lawSyncService = app(LawGraphSyncService::class);
    }

    // ========================================
    // Court Decision: Dissent/Concurrence Tests
    // ========================================

    #[Test]
    public function it_syncs_dissent_count_to_neo4j(): void
    {
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Rev-Dissent/2026-P4',
            'court' => 'Vrhovni sud',
            'dissent_count' => 3,
            'concurrence_count' => 0,
        ]);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'Test content for dissent count sync.',
        ]);

        try {
            $this->decisionSyncService->sync($decision->id);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available') ||
                str_contains($e->getMessage(), 'Connection refused')) {
                $this->markTestSkipped('Neo4j not available: '.$e->getMessage());
            }
            throw $e;
        }

        // Verify dissent_count is synced to Neo4j
        $result = $this->graph->run(
            'MATCH (d:CourtDecisionDocument {case_number: $caseNumber})
             RETURN d.dissent_count as dissent_count, d.concurrence_count as concurrence_count',
            ['caseNumber' => 'Rev-Dissent/2026-P4']
        );

        $this->assertNotEmpty($result, 'Decision node should exist in Neo4j');
        $node = $result->first();
        $this->assertEquals(3, $node->get('dissent_count'));
        $this->assertEquals(0, $node->get('concurrence_count'));
    }

    #[Test]
    public function it_syncs_concurrence_count_to_neo4j(): void
    {
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Rev-Concurrence/2026-P4',
            'court' => 'Ustavni sud',
            'dissent_count' => 1,
            'concurrence_count' => 2,
        ]);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'Test content for concurrence count sync.',
        ]);

        try {
            $this->decisionSyncService->sync($decision->id);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            throw $e;
        }

        $result = $this->graph->run(
            'MATCH (d:CourtDecisionDocument {case_number: $caseNumber})
             RETURN d.dissent_count as dissent_count, d.concurrence_count as concurrence_count',
            ['caseNumber' => 'Rev-Concurrence/2026-P4']
        );

        $this->assertNotEmpty($result);
        $node = $result->first();
        $this->assertEquals(1, $node->get('dissent_count'));
        $this->assertEquals(2, $node->get('concurrence_count'));
    }

    #[Test]
    public function it_defaults_dissent_and_concurrence_to_zero_in_neo4j(): void
    {
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Rev-DefaultCounts/2026-P4',
            'court' => 'Županijski sud',
            // No dissent_count or concurrence_count specified
        ]);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'Test content for default counts.',
        ]);

        try {
            $this->decisionSyncService->sync($decision->id);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            throw $e;
        }

        $result = $this->graph->run(
            'MATCH (d:CourtDecisionDocument {case_number: $caseNumber})
             RETURN d.dissent_count as dissent_count, d.concurrence_count as concurrence_count',
            ['caseNumber' => 'Rev-DefaultCounts/2026-P4']
        );

        $this->assertNotEmpty($result);
        $node = $result->first();
        $this->assertEquals(0, $node->get('dissent_count'));
        $this->assertEquals(0, $node->get('concurrence_count'));
    }

    // ========================================
    // Law Document: Amendment Tracking Tests
    // ========================================

    #[Test]
    public function it_syncs_amendments_array_to_neo4j(): void
    {
        $law = Law::factory()->create([
            'law_number' => 'NN 50/10',
            'title' => 'Test Law with Amendments',
            'jurisdiction' => 'Hrvatska',
            'amendments' => ['NN 123/21', 'NN 45/22', 'NN 67/23'],
        ]);

        try {
            $this->lawSyncService->sync($law->id);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available') ||
                str_contains($e->getMessage(), 'Connection refused')) {
                $this->markTestSkipped('Neo4j not available: '.$e->getMessage());
            }
            throw $e;
        }

        $result = $this->graph->run(
            'MATCH (l:LawDocument {law_number: $lawNumber})
             RETURN l.amendments as amendments',
            ['lawNumber' => 'NN 50/10']
        );

        $this->assertNotEmpty($result, 'Law node should exist in Neo4j');
        $node = $result->first();
        $amendments = $node->get('amendments');
        $this->assertIsArray($amendments);
        $this->assertContains('NN 123/21', $amendments);
        $this->assertContains('NN 45/22', $amendments);
        $this->assertContains('NN 67/23', $amendments);
    }

    #[Test]
    public function it_syncs_repeal_information_to_neo4j(): void
    {
        $law = Law::factory()->create([
            'law_number' => 'NN 100/08',
            'title' => 'Repealed Law',
            'jurisdiction' => 'Hrvatska',
            'repeal_date' => '2025-12-31',
            'repealed_by' => 'NN 200/25',
        ]);

        try {
            $this->lawSyncService->sync($law->id);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            throw $e;
        }

        $result = $this->graph->run(
            'MATCH (l:LawDocument {law_number: $lawNumber})
             RETURN l.repeal_date as repeal_date, l.repealed_by as repealed_by',
            ['lawNumber' => 'NN 100/08']
        );

        $this->assertNotEmpty($result);
        $node = $result->first();
        $this->assertEquals('2025-12-31', $node->get('repeal_date'));
        $this->assertEquals('NN 200/25', $node->get('repealed_by'));
    }

    #[Test]
    public function it_syncs_parent_law_number_for_amendments(): void
    {
        $law = Law::factory()->create([
            'law_number' => 'NN 123/21',
            'title' => 'Amendment to Criminal Procedure Law',
            'jurisdiction' => 'Hrvatska',
            'parent_law_number' => 'NN 50/10',
        ]);

        try {
            $this->lawSyncService->sync($law->id);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            throw $e;
        }

        $result = $this->graph->run(
            'MATCH (l:LawDocument {law_number: $lawNumber})
             RETURN l.parent_law_number as parent_law_number',
            ['lawNumber' => 'NN 123/21']
        );

        $this->assertNotEmpty($result);
        $this->assertEquals('NN 50/10', $result->first()->get('parent_law_number'));
    }

    #[Test]
    public function it_syncs_consolidation_date_to_neo4j(): void
    {
        $law = Law::factory()->create([
            'law_number' => 'NN 75/15',
            'title' => 'Consolidated Law',
            'jurisdiction' => 'Hrvatska',
            'consolidation_date' => '2024-06-15',
        ]);

        try {
            $this->lawSyncService->sync($law->id);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            throw $e;
        }

        $result = $this->graph->run(
            'MATCH (l:LawDocument {law_number: $lawNumber})
             RETURN l.consolidation_date as consolidation_date',
            ['lawNumber' => 'NN 75/15']
        );

        $this->assertNotEmpty($result);
        $this->assertEquals('2024-06-15', $result->first()->get('consolidation_date'));
    }

    #[Test]
    public function it_handles_null_amendment_properties_gracefully(): void
    {
        $law = Law::factory()->create([
            'law_number' => 'NN 10/20',
            'title' => 'Simple Law without Amendments',
            'jurisdiction' => 'Hrvatska',
            // No amendment properties set
        ]);

        try {
            $this->lawSyncService->sync($law->id);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            throw $e;
        }

        $result = $this->graph->run(
            'MATCH (l:LawDocument {law_number: $lawNumber})
             RETURN l.amendments as amendments,
                    l.repealed_by as repealed_by,
                    l.parent_law_number as parent_law_number,
                    l.consolidation_date as consolidation_date',
            ['lawNumber' => 'NN 10/20']
        );

        $this->assertNotEmpty($result);
        $node = $result->first();

        // Should be empty array or null for amendments
        $amendments = $node->get('amendments');
        $this->assertTrue($amendments === null || $amendments === []);

        // Other nullable properties should be null
        $this->assertNull($node->get('repealed_by'));
        $this->assertNull($node->get('parent_law_number'));
    }

    // ========================================
    // Update/Idempotency Tests
    // ========================================

    #[Test]
    public function it_updates_dissent_count_on_resync(): void
    {
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Rev-UpdateDissent/2026-P4',
            'court' => 'Vrhovni sud',
            'dissent_count' => 1,
        ]);

        $doc = CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'Test content.',
        ]);

        try {
            // First sync
            $this->decisionSyncService->sync($decision->id);

            // Update dissent count in PostgreSQL
            $decision->update(['dissent_count' => 5]);
            $decision->refresh();

            // Resync
            $this->decisionSyncService->sync($decision->id);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            throw $e;
        }

        // Verify update was applied
        $result = $this->graph->run(
            'MATCH (d:CourtDecisionDocument {case_number: $caseNumber})
             RETURN d.dissent_count as dissent_count',
            ['caseNumber' => 'Rev-UpdateDissent/2026-P4']
        );

        $this->assertEquals(5, $result->first()->get('dissent_count'));
    }

    #[Test]
    public function it_updates_amendments_on_resync(): void
    {
        $law = Law::factory()->create([
            'law_number' => 'NN 88/12',
            'title' => 'Law to Update',
            'jurisdiction' => 'Hrvatska',
            'amendments' => ['NN 10/15'],
        ]);

        try {
            // First sync
            $this->lawSyncService->sync($law->id);

            // Add more amendments
            $law->update(['amendments' => ['NN 10/15', 'NN 20/18', 'NN 30/21']]);
            $law->refresh();

            // Resync
            $this->lawSyncService->sync($law->id);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            throw $e;
        }

        // Verify update was applied
        $result = $this->graph->run(
            'MATCH (l:LawDocument {law_number: $lawNumber})
             RETURN l.amendments as amendments',
            ['lawNumber' => 'NN 88/12']
        );

        $amendments = $result->first()->get('amendments');
        $this->assertCount(3, $amendments);
        $this->assertContains('NN 30/21', $amendments);
    }
}
