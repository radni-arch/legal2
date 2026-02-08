<?php

namespace Tests\Integration;

use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * GraphDbSemanticInvariantsTest
 *
 * Purpose:
 *  - Enforce "semantic" graph invariants: if the DB says a thing is synced or has rich content,
 *    the graph must have corresponding nodes and relationships.
 *
 * Why these matter:
 *  - Prevents the worst class of failures: UI says "synced", DB says "processed", but Neo4j has no edges.
 *  - Makes graph reasoning safe: documents are connected via at least one edge.
 */
class GraphDbSemanticInvariantsTest extends TestCase
{
    use UsesTestDatabase;

    private function graph(): GraphDatabaseService
    {
        /** @var GraphDatabaseService $svc */
        $svc = app(GraphDatabaseService::class);

        return $svc;
    }

    private function requireGraphOrSkip(): void
    {
        if (! config('neo4j.sync.enabled', true)) {
            $this->markTestSkipped('Neo4j sync is disabled (neo4j.sync.enabled=false)');
        }
        if (! $this->graph()->isAvailable()) {
            $this->markTestSkipped('Neo4j is not available for graph invariants');
        }
    }

    private function table(string $configKey, string $default): string
    {
        return (string) config($configKey, $default);
    }

    private function relCount(string $label, string $id, string $pattern = ''): int
    {
        $graph = $this->graph();
        $pattern = $pattern ?: '[r]-()';

        $res = $graph->run(
            "MATCH (n:{$label} {id: \$id})-{$pattern} RETURN count(r) as c",
            ['id' => $id]
        );

        return (int) $res->first()->get('c');
    }

    /**
     * Invariant: If textract_jobs.graph_sync_status is 'synced', Neo4j must contain
     * at least one TextractDocument node for that job.
     *
     * Reasoning:
     * - GraphRagOrchestrator::syncTextractJob sets graph_sync_status to synced only after
     *   upserting TextractDocument nodes for completed chunks.
     */
    public function test_synced_textract_jobs_have_textractdocument_nodes_in_neo4j(): void
    {
        $this->requireGraphOrSkip();

        if (! Schema::hasTable('textract_jobs') || ! Schema::hasColumn('textract_jobs', 'graph_sync_status')) {
            $this->markTestSkipped('textract_jobs.graph_sync_status not present');
        }

        $jobs = DB::table('textract_jobs')
            ->select(['id'])
            ->where('graph_sync_status', 'synced')
            ->orderBy('id')
            ->limit(50)
            ->get();

        foreach ($jobs as $job) {
            $jobId = (string) $job->id;

            $res = $this->graph()->run(
                'MATCH (t:TextractDocument) WHERE t.textract_job_id = $jobId RETURN count(t) as c',
                ['jobId' => $jobId]
            );

            $count = (int) $res->first()->get('c');
            $this->assertGreaterThan(0, $count, "textract_job {$jobId} is marked graph_synced but has 0 TextractDocument nodes");
        }

        $this->assertTrue(true);
    }

    /**
     * Invariant: Documents with non-trivial content should not be isolated in the graph.
     *
     * Reasoning:
     * - GraphRAG only adds value if document nodes are connected (keywords, tags, citations, similarity,
     *   jurisdiction/court relationships).
     * - This catches "node exists but no edges" issues.
     */
    public function test_rich_documents_have_at_least_one_graph_connection(): void
    {
        $this->requireGraphOrSkip();

        $samples = [
            // label => [table, content_col]
            'LawDocument' => [$this->table('vizra-adk.tables.laws', 'laws'), 'content'],
            'CaseDocument' => [$this->table('vizra-adk.tables.cases_documents', 'cases_documents'), 'content'],
            'CourtDecisionDocument' => [$this->table('vizra-adk.tables.court_decision_documents', 'court_decision_documents'), 'content'],
            'TextractDocument' => ['textract_documents', 'content'],
        ];

        foreach ($samples as $label => [$table, $contentCol]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id') || ! Schema::hasColumn($table, $contentCol)) {
                continue;
            }

            // Pull a small sample of "rich" docs. For postgres: use char_length, otherwise length.
            $driver = DB::connection()->getDriverName();
            $lenExpr = $driver === 'pgsql' ? "char_length({$contentCol})" : "length({$contentCol})";

            $ids = DB::table($table)
                ->select(['id'])
                ->whereNotNull($contentCol)
                ->whereRaw("{$lenExpr} >= 200")
                ->orderBy('id')
                ->limit(25)
                ->pluck('id');

            foreach ($ids as $id) {
                $id = (string) $id;

                // Count only relationship types that represent "connectedness".
                $res = $this->graph()->run(
                    "MATCH (n:{$label} {id: \$id})-[r]-()\n                     WHERE type(r) IN [\n                       'HAS_KEYWORD','HAS_TAG','CITES','REFERENCES','SIMILAR_TO',\n                       'BELONGS_TO_JURISDICTION','DECIDED_BY','BELONGS_TO'\n                     ]\n                     RETURN count(r) as c",
                    ['id' => $id]
                );

                $c = (int) $res->first()->get('c');
                $this->assertGreaterThan(0, $c, "{$label}:{$id} has rich DB content but 0 meaningful graph relationships");
            }
        }

        $this->assertTrue(true);
    }

    /**
     * Invariant: Citation edges must connect only allowed document node types.
     *
     * Reasoning:
     * - Prevents schema drift where a linker accidentally creates CITES/REFERENCES
     *   between unexpected node labels (hurts query assumptions).
     */
    public function test_citation_edges_connect_expected_node_labels(): void
    {
        $this->requireGraphOrSkip();

        $rows = $this->graph()->run(
            "MATCH (a)-[r:CITES|REFERENCES]->(b)\n             RETURN labels(a) as aLabels, labels(b) as bLabels\n             LIMIT 50"
        );

        $allowed = [
            'LawDocument',
            'CaseDocument',
            'CourtDecisionDocument',
            'TextractDocument',
            // Some code paths refer to broader labels configured in neo4j.php
            'Law',
            'Case',
        ];

        foreach ($rows as $row) {
            $aLabels = (array) ($row->get('aLabels') ?? []);
            $bLabels = (array) ($row->get('bLabels') ?? []);

            // If any label on either side is outside the allowed set, flag.
            foreach (array_merge($aLabels, $bLabels) as $lbl) {
                $this->assertTrue(in_array($lbl, $allowed, true), 'Unexpected label in citation edge: '.$lbl);
            }
        }

        $this->assertTrue(true);
    }
}
