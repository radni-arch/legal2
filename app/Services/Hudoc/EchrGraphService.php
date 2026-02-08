<?php

namespace App\Services\Hudoc;

use App\Models\EchrCase;
use App\Services\CircuitBreaker;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laudis\Neo4j\Contracts\ClientInterface;

class EchrGraphService
{
    protected ?ClientInterface $client;

    protected CircuitBreaker $circuitBreaker;

    public function __construct(?ClientInterface $client = null, ?CircuitBreaker $circuitBreaker = null)
    {
        $this->client = $client;
        $defaults = config('circuit_breaker.defaults.neo4j', []);
        $this->circuitBreaker = $circuitBreaker ?? new CircuitBreaker(
            'neo4j_echr',
            $defaults['failure_threshold'] ?? 5,
            $defaults['success_threshold'] ?? 2,
            $defaults['timeout'] ?? 30,
            $defaults['retry_after'] ?? 15
        );
    }

    protected function run(string $cypher, array $params = []): mixed
    {
        if (! $this->client) {
            Log::info('Neo4j disabled; skipping ECHR graph operation');

            return null;
        }

        return $this->circuitBreaker->call(function () use ($cypher, $params) {
            return $this->client->run($cypher, $params);
        });
    }

    public function syncCaseToGraph(EchrCase $case): void
    {
        try {
            // Create/update case node
            $this->run(
                'MERGE (c:EchrCase {item_id: $item_id})
                 SET c += $properties
                 SET c.updated_at = datetime()',
                [
                    'item_id' => $case->item_id,
                    'properties' => [
                        'case_name' => $case->short_name,
                        'application_number' => $case->application_number,
                        'respondent_state' => $case->respondent_state,
                        'judgment_date' => $case->judgment_date?->toDateString(),
                        'importance' => $case->importance,
                        'document_type' => $case->document_type,
                        'ecli' => $case->ecli,
                    ],
                ]
            );

            // Create article relationships
            foreach ($case->articles as $article) {
                $this->run(
                    'MERGE (a:ConventionArticle {code: $code})
                     ON CREATE SET a.name = $name
                     WITH a
                     MATCH (c:EchrCase {item_id: $item_id})
                     MERGE (c)-[r:CONCERNS]->(a)
                     SET r.status = $status',
                    [
                        'code' => $article->article_code,
                        'name' => $article->article_name,
                        'item_id' => $case->item_id,
                        'status' => $article->pivot->status,
                    ]
                );
            }

            // Create citation relationships
            foreach ($case->citedCases as $citation) {
                if ($citation->cited_case_id) {
                    $citedCase = EchrCase::find($citation->cited_case_id);
                    if ($citedCase) {
                        $this->run(
                            'MATCH (citing:EchrCase {item_id: $citing_id})
                             MATCH (cited:EchrCase {item_id: $cited_id})
                             MERGE (citing)-[r:CITES {type: $type}]->(cited)',
                            [
                                'citing_id' => $case->item_id,
                                'cited_id' => $citedCase->item_id,
                                'type' => $citation->citation_type,
                            ]
                        );
                    }
                }
            }

            Log::info('ECHR case synced to graph', ['item_id' => $case->item_id]);

        } catch (\Exception $e) {
            Log::error('ECHR graph sync failed', [
                'item_id' => $case->item_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function findCitationPath(string $fromItemId, string $toItemId): array
    {
        try {
            $result = $this->run(
                'MATCH path = shortestPath(
                    (from:EchrCase {item_id: $from})-[:CITES*..10]->(to:EchrCase {item_id: $to})
                 )
                 RETURN path',
                ['from' => $fromItemId, 'to' => $toItemId]
            );

            return $this->pathToArray($result);

        } catch (\Exception $e) {
            Log::error('ECHR citation path query failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function findRelatedByArticle(string $articleCode, int $limit = 20): Collection
    {
        try {
            $result = $this->run(
                'MATCH (c:EchrCase)-[r:CONCERNS]->(a:ConventionArticle {code: $code})
                 WHERE r.status = "VIOLATION"
                 RETURN c.item_id as item_id, c.case_name as name, c.judgment_date as date
                 ORDER BY c.importance, c.judgment_date DESC
                 LIMIT $limit',
                ['code' => $articleCode, 'limit' => $limit]
            );

            if (! $result) {
                return collect();
            }

            return collect($result->toArray())->map(fn ($r) => [
                'item_id' => $r['item_id'] ?? null,
                'name' => $r['name'] ?? null,
                'date' => $r['date'] ?? null,
            ]);

        } catch (\Exception $e) {
            Log::error('ECHR related by article query failed', ['error' => $e->getMessage()]);

            return collect();
        }
    }

    public function getMostCitedCases(int $limit = 20): Collection
    {
        try {
            $result = $this->run(
                'MATCH (c:EchrCase)<-[:CITES]-(citing:EchrCase)
                 WITH c, count(citing) as citation_count
                 ORDER BY citation_count DESC
                 LIMIT $limit
                 RETURN c.item_id as item_id, c.case_name as name, citation_count',
                ['limit' => $limit]
            );

            if (! $result) {
                return collect();
            }

            return collect($result->toArray())->map(fn ($r) => [
                'item_id' => $r['item_id'] ?? null,
                'name' => $r['name'] ?? null,
                'citation_count' => $r['citation_count'] ?? 0,
            ]);

        } catch (\Exception $e) {
            Log::error('ECHR most cited query failed', ['error' => $e->getMessage()]);

            return collect();
        }
    }

    public function getCaseNetwork(string $itemId, int $depth = 2): array
    {
        try {
            $result = $this->run(
                'MATCH (center:EchrCase {item_id: $item_id})
                 CALL apoc.path.subgraphAll(center, {
                     relationshipFilter: "CITES",
                     maxLevel: $depth
                 })
                 YIELD nodes, relationships
                 RETURN nodes, relationships',
                ['item_id' => $itemId, 'depth' => $depth]
            );

            if (! $result) {
                return ['nodes' => [], 'edges' => []];
            }

            $record = $result->first();
            if (! $record) {
                return ['nodes' => [], 'edges' => []];
            }

            return [
                'nodes' => collect($record->get('nodes'))->map(fn ($n) => [
                    'id' => $n->getProperty('item_id'),
                    'label' => $n->getProperty('case_name'),
                    'state' => $n->getProperty('respondent_state'),
                ])->toArray(),
                'edges' => collect($record->get('relationships'))->map(fn ($r) => [
                    'source' => $r->getStartNodeId(),
                    'target' => $r->getEndNodeId(),
                    'type' => $r->getType(),
                ])->toArray(),
            ];

        } catch (\Exception $e) {
            Log::error('ECHR case network query failed', ['error' => $e->getMessage()]);

            return ['nodes' => [], 'edges' => []];
        }
    }

    public function syncAllToGraph(): int
    {
        $count = 0;

        EchrCase::with(['articles', 'citedCases'])->chunk(100, function ($cases) use (&$count) {
            foreach ($cases as $case) {
                $this->syncCaseToGraph($case);
                $count++;
            }
        });

        return $count;
    }

    protected function pathToArray($result): array
    {
        if (! $result) {
            return [];
        }

        $record = $result->first();
        if (! $record) {
            return [];
        }

        $path = $record->get('path');
        $nodes = [];

        foreach ($path->nodes() as $node) {
            $nodes[] = [
                'item_id' => $node->getProperty('item_id'),
                'name' => $node->getProperty('case_name'),
            ];
        }

        return $nodes;
    }
}
