<?php

namespace Tests\Integration\Graph;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Services\Graph\DecisionGraphSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class EndToEndSyncTest extends GraphIntegrationTestCase
{
    use RefreshDatabase;

    protected DecisionGraphSyncService $syncService;

    protected function setUp(): void
    {
        parent::setUp();

        // Get the sync service from container
        $this->syncService = app(DecisionGraphSyncService::class);
    }

    #[Test]
    public function it_syncs_court_decision_from_postgres_to_neo4j(): void
    {
        // Create a CourtDecision in PostgreSQL
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Rev-123/2026-E2E',
            'court' => 'Vrhovni sud',
            'jurisdiction' => 'Croatia',
            'judge' => 'Test Judge E2E',
            'decision_date' => '2026-01-07',
            'decision_type' => 'presuda',
        ]);

        // Create at least one document chunk for the decision
        CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'title' => 'Test Decision Document',
            'content' => 'This is test content for E2E sync testing.',
        ]);

        // Sync to Neo4j using the public sync() method
        try {
            $metrics = $this->syncService->sync($decision->id);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available') ||
                str_contains($e->getMessage(), 'Connection refused')) {
                $this->markTestSkipped('Neo4j not available: ' . $e->getMessage());
            }
            throw $e;
        }

        // Verify metrics indicate success
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('nodes', $metrics);
        $this->assertGreaterThan(0, array_sum($metrics['nodes']), 'Should create at least one node');

        // Verify node exists in Neo4j
        $result = $this->graph->run(
            'MATCH (d:CourtDecisionDocument {case_number: $caseNumber})
             RETURN d.court as court, d.jurisdiction as jurisdiction',
            ['caseNumber' => 'Rev-123/2026-E2E']
        );

        $this->assertNotEmpty($result, 'Decision node should exist in Neo4j');

        // Verify properties match
        $node = $result->first();
        $this->assertNotNull($node);
        $this->assertEquals('Vrhovni sud', $node->get('court'));
        $this->assertEquals('Croatia', $node->get('jurisdiction'));
    }

    #[Test]
    public function it_creates_court_relationship(): void
    {
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Rev-456/2026-E2E',
            'court' => 'Županijski sud u Zagrebu',
            'jurisdiction' => 'Croatia',
        ]);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'Court relationship test content.',
        ]);

        try {
            $this->syncService->sync($decision->id);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            throw $e;
        }

        // Verify Court node exists
        $courtResult = $this->graph->run(
            'MATCH (c:Court {name: $name}) RETURN c',
            ['name' => 'Županijski sud u Zagrebu']
        );
        $this->assertNotEmpty($courtResult, 'Court node should exist');

        // Verify DECIDED_BY relationship exists
        $relResult = $this->graph->run(
            'MATCH (d:CourtDecisionDocument {case_number: $caseNumber})-[:DECIDED_BY]->(c:Court)
             RETURN d, c',
            ['caseNumber' => 'Rev-456/2026-E2E']
        );
        $this->assertNotEmpty($relResult, 'DECIDED_BY relationship should exist');
    }

    #[Test]
    public function it_creates_jurisdiction_relationship(): void
    {
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Rev-789/2026-E2E',
            'court' => 'Test Court',
            'jurisdiction' => 'Test Jurisdiction E2E',
        ]);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'Jurisdiction relationship test content.',
        ]);

        try {
            $this->syncService->sync($decision->id);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            throw $e;
        }

        // Verify Jurisdiction node and relationship
        $result = $this->graph->run(
            'MATCH (d:CourtDecisionDocument {case_number: $caseNumber})
                   -[:BELONGS_TO_JURISDICTION]->(j:Jurisdiction)
             RETURN j.name as jurisdiction',
            ['caseNumber' => 'Rev-789/2026-E2E']
        );

        $this->assertNotEmpty($result);
        $this->assertEquals('Test Jurisdiction E2E', $result->first()->get('jurisdiction'));
    }

    #[Test]
    public function it_syncs_judge_when_present(): void
    {
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Rev-Judge/2026-E2E',
            'court' => 'Test Court for Judge',
            'jurisdiction' => 'Croatia',
            'judge' => 'Marija Jurić E2E',
        ]);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'Judge sync test content.',
        ]);

        try {
            $this->syncService->sync($decision->id);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            throw $e;
        }

        // Verify Judge node and PRESIDED_BY relationship
        $result = $this->graph->run(
            'MATCH (d:CourtDecisionDocument {case_number: $caseNumber})
                   -[:PRESIDED_BY]->(j:Judge)
             RETURN j.name as judge_name',
            ['caseNumber' => 'Rev-Judge/2026-E2E']
        );

        $this->assertNotEmpty($result, 'Judge relationship should exist');
        $this->assertEquals('Marija Jurić E2E', $result->first()->get('judge_name'));
    }

    #[Test]
    public function it_handles_decision_update_idempotently(): void
    {
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Rev-Idempotent/2026',
            'court' => 'Original Court',
            'title' => 'Original Title',
        ]);

        $doc = CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'title' => 'Original Doc Title',
            'content' => 'Idempotency test content.',
        ]);

        try {
            // First sync
            $this->syncService->sync($decision->id);

            // Count nodes before second sync
            $beforeCount = $this->graph->run(
                'MATCH (d:CourtDecisionDocument {case_number: $cn}) RETURN count(d) as c',
                ['cn' => 'Rev-Idempotent/2026']
            )->first()->get('c');

            // Update decision and document
            $decision->update(['title' => 'Updated Title']);
            $decision->refresh();
            $doc->update(['title' => 'Updated Doc Title']);
            $doc->refresh();

            // Sync again
            $this->syncService->sync($decision->id);

            // Should still be only one node (upsert, not duplicate)
            $afterCount = $this->graph->run(
                'MATCH (d:CourtDecisionDocument {case_number: $cn}) RETURN count(d) as c',
                ['cn' => 'Rev-Idempotent/2026']
            )->first()->get('c');

            $this->assertEquals($beforeCount, $afterCount, 'Should not create duplicate nodes');

            // Verify update was applied
            $result = $this->graph->run(
                'MATCH (d:CourtDecisionDocument {case_number: $cn}) RETURN d.title as title',
                ['cn' => 'Rev-Idempotent/2026']
            );
            $this->assertEquals('Updated Doc Title', $result->first()->get('title'));
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            throw $e;
        }
    }

    #[Test]
    public function it_reports_accurate_sync_metrics(): void
    {
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Rev-Metrics/2026',
            'court' => 'Metrics Test Court',
            'jurisdiction' => 'Metrics Jurisdiction',
            'judge' => 'Metrics Judge',
        ]);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'Metrics test content.',
        ]);

        try {
            $metrics = $this->syncService->sync($decision->id);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            throw $e;
        }

        // Verify metrics structure
        $this->assertArrayHasKey('nodes', $metrics);
        $this->assertArrayHasKey('relationships', $metrics);
        $this->assertArrayHasKey('errors', $metrics);
        $this->assertArrayHasKey('duration_ms', $metrics);

        // Should have created multiple nodes (decision, court, jurisdiction, judge)
        $this->assertGreaterThanOrEqual(1, array_sum($metrics['nodes']));

        // Should have relationships
        $this->assertGreaterThanOrEqual(1, array_sum($metrics['relationships']));

        // Should have no errors
        $this->assertEmpty($metrics['errors']);

        // Duration should be positive
        $this->assertGreaterThan(0, $metrics['duration_ms']);
    }
}
