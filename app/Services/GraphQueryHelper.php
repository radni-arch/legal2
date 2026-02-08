<?php

namespace App\Services;

use App\Exceptions\GraphException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Helper class with common graph query patterns for legal RAG system
 */
class GraphQueryHelper
{
    public function __construct(protected GraphDatabaseService $graph) {}

    /**
     * Find laws that cite specific law
     */
    public function findCitingLaws(string $lawId, int $limit = 20): array
    {
        $query = 'MATCH (citing:LawDocument)-[:CITES]->(cited:LawDocument {id: $lawId})
                  RETURN citing
                  ORDER BY citing.effective_date DESC
                  LIMIT $limit';

        $result = $this->graph->run($query, ['lawId' => $lawId, 'limit' => $limit]);

        return $result->map(fn ($r) => $r->get('citing')->getProperties())->toArray();
    }

    /**
     * Find cases that apply a specific law
     */
    public function findCasesApplyingLaw(string $lawId, int $limit = 20): array
    {
        $query = 'MATCH (case:CaseDocument)-[:REFERENCES]->(law:LawDocument {id: $lawId})
                  RETURN case
                  LIMIT $limit';

        $result = $this->graph->run($query, ['lawId' => $lawId, 'limit' => $limit]);

        return $result->map(fn ($r) => $r->get('case')->getProperties())->toArray();
    }

    /**
     * Find documents with similar tag patterns
     */
    public function findByTagPattern(array $tags, int $minMatches = 2, int $limit = 20): array
    {
        $tagIds = array_map(fn ($tag) => 'tag_'.str_replace(['-', ' '], '_', strtolower($tag)), $tags);

        $query = 'MATCH (doc)-[:HAS_TAG]->(t:Tag)
                  WHERE t.id IN $tagIds
                  WITH doc, count(DISTINCT t) as matches
                  WHERE matches >= $minMatches
                  RETURN doc, matches
                  ORDER BY matches DESC
                  LIMIT $limit';

        $result = $this->graph->run($query, [
            'tagIds' => $tagIds,
            'minMatches' => $minMatches,
            'limit' => $limit,
        ]);

        return $result->map(fn ($r) => [
            'document' => $r->get('doc')->getProperties(),
            'matches' => $r->get('matches'),
        ])->toArray();
    }

    /**
     * Find temporal evolution of laws (amendments, supersedes)
     */
    public function findLawEvolution(string $lawId): array
    {
        $query = 'MATCH path = (old:LawDocument)-[:SUPERSEDES|AMENDED_BY*]->(new:LawDocument {id: $lawId})
                  RETURN nodes(path) as evolution
                  ORDER BY length(path) DESC
                  LIMIT 1';

        $result = $this->graph->run($query, ['lawId' => $lawId]);

        if ($result->count() === 0) {
            return [];
        }

        $nodes = $result->first()->get('evolution');

        return array_map(fn ($node) => $node->getProperties(), $nodes);
    }

    /**
     * Find documents by jurisdiction and tags
     */
    public function findByJurisdictionAndTags(string $jurisdiction, array $tags, int $limit = 20): array
    {
        $tagIds = array_map(fn ($tag) => 'tag_'.str_replace(['-', ' '], '_', strtolower($tag)), $tags);
        $jurisdictionId = 'jurisdiction_'.$jurisdiction;

        $query = 'MATCH (doc)-[:BELONGS_TO_JURISDICTION]->(j:Jurisdiction {id: $jurisdictionId})
                  MATCH (doc)-[:HAS_TAG]->(t:Tag)
                  WHERE t.id IN $tagIds
                  RETURN DISTINCT doc
                  LIMIT $limit';

        $result = $this->graph->run($query, [
            'jurisdictionId' => $jurisdictionId,
            'tagIds' => $tagIds,
            'limit' => $limit,
        ]);

        return $result->map(fn ($r) => $r->get('doc')->getProperties())->toArray();
    }

    /**
     * Get keyword co-occurrence network
     */
    public function getKeywordNetwork(string $keyword, int $depth = 2, int $limit = 50): array
    {
        $keywordId = 'keyword_'.md5(strtolower($keyword));

        $query = 'MATCH (k:Keyword {id: $keywordId})<-[:HAS_KEYWORD]-(doc)-[:HAS_KEYWORD]->(related:Keyword)
                  WHERE k <> related
                  WITH related, count(doc) as frequency
                  ORDER BY frequency DESC
                  LIMIT $limit
                  RETURN related.name as keyword, frequency';

        $result = $this->graph->run($query, [
            'keywordId' => $keywordId,
            'limit' => $limit,
        ]);

        return $result->map(fn ($r) => [
            'keyword' => $r->get('keyword'),
            'frequency' => $r->get('frequency'),
        ])->toArray();
    }

    /**
     * Find most influential documents (most cited/referenced)
     */
    public function findInfluentialDocuments(string $nodeType = 'LawDocument', int $limit = 20): array
    {
        $query = "MATCH (doc:$nodeType)<-[r:CITES|REFERENCES]-()
                  WITH doc, count(r) as citations
                  ORDER BY citations DESC
                  LIMIT \$limit
                  RETURN doc, citations";

        $result = $this->graph->run($query, ['limit' => $limit]);

        return $result->map(fn ($r) => [
            'document' => $r->get('doc')->getProperties(),
            'citations' => $r->get('citations'),
        ])->toArray();
    }

    /**
     * Find documents in a specific topic cluster
     */
    public function findTopicCluster(string $topicTag, int $depth = 2): array
    {
        $tagId = 'tag_'.str_replace(['-', ' '], '_', strtolower($topicTag));

        $query = "MATCH (t:Tag {id: \$tagId})
                  OPTIONAL MATCH (t)<-[:PARENT_TAG*0..$depth]-(childTag:Tag)
                  WITH collect(DISTINCT t) + collect(DISTINCT childTag) as tags
                  UNWIND tags as tag
                  MATCH (doc)-[:HAS_TAG]->(tag)
                  RETURN DISTINCT doc";

        $result = $this->graph->run($query, ['tagId' => $tagId]);

        return $result->map(fn ($r) => $r->get('doc')->getProperties())->toArray();
    }

    /**
     * Find contradicting or supporting documents
     */
    public function findRelatedOpinions(string $docId, string $relationType = 'SUPPORTS'): array
    {
        $query = "MATCH (doc {id: \$docId})-[r:$relationType]->(related)
                  RETURN related, r.strength as strength, r.created_at as created_at
                  ORDER BY strength DESC";

        $result = $this->graph->run($query, ['docId' => $docId]);

        return $result->map(fn ($r) => [
            'document' => $r->get('related')->getProperties(),
            'strength' => $r->get('strength'),
            'created_at' => $r->get('created_at'),
        ])->toArray();
    }

    /**
     * Recommend similar documents based on multiple factors
     */
    public function recommendDocuments(string $docId, int $limit = 10): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('GraphQueryHelper: recommendDocuments initiated', [
            'doc_id' => $docId,
            'limit' => $limit,
            'user_id' => auth()->id(),
        ]);

        try {
            $query = 'MATCH (doc {id: $docId})

                      // Find similar by embeddings
                      OPTIONAL MATCH (doc)-[s:SIMILAR_TO]->(similar1)

                      // Find related by shared tags
                      OPTIONAL MATCH (doc)-[:HAS_TAG]->(t:Tag)<-[:HAS_TAG]-(similar2)

                      // Find related by shared keywords
                      OPTIONAL MATCH (doc)-[:HAS_KEYWORD]->(k:Keyword)<-[:HAS_KEYWORD]-(similar3)

                      WITH similar1, similar2, similar3, s.similarity as embedding_score

                      // Combine all similar documents
                      WITH collect(DISTINCT similar1) + collect(DISTINCT similar2) + collect(DISTINCT similar3) as candidates,
                           collect(DISTINCT embedding_score) as scores

                      UNWIND candidates as candidate
                      WHERE candidate IS NOT NULL

                      RETURN DISTINCT candidate,
                             CASE WHEN candidate IN collect(similar1) THEN 1.0 ELSE 0.5 END as score
                      ORDER BY score DESC
                      LIMIT $limit';

            $result = $this->graph->run($query, ['docId' => $docId, 'limit' => $limit]);

            $recommendations = $result->map(fn ($r) => [
                'document' => $r->get('candidate')->getProperties(),
                'score' => $r->get('score'),
            ])->toArray();

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('GraphQueryHelper: recommendDocuments completed', [
                'doc_id' => $docId,
                'recommendations_count' => count($recommendations),
                'duration_ms' => round($duration, 2),
            ]);

            return $recommendations;

        } catch (GraphException $e) {
            Log::error('GraphQueryHelper: recommendDocuments failed with GraphException', [
                'doc_id' => $docId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('GraphQueryHelper: recommendDocuments failed with unexpected exception', [
                'doc_id' => $docId,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new GraphException(
                'Document recommendation failed: '.$e->getMessage(),
                GraphException::QUERY_FAILED,
                $e
            );
        }
    }

    /**
     * Build parameterized Cypher query for creating/updating a court decision node
     * Uses MERGE for idempotency (handles duplicate ECLI gracefully)
     * Returns array with 'query' and suggested 'parameters'
     */
    public function buildDecisionQuery(array $meta, string $docId): array
    {
        // MERGE ensures idempotency - won't fail on duplicate ECLI
        // Using WITH clauses for explicit variable passing and better Neo4j compatibility
        $query = '
            // Create or update the decision document node
            MERGE (doc:CourtDecisionDocument {id: $doc_id})
            SET doc.case_number = $case_number,
                doc.court = $court,
                doc.jurisdiction = $jurisdiction,
                doc.decision_date = $decision_date,
                doc.publication_date = $publication_date,
                doc.decision_type = $decision_type,
                doc.register = $register,
                doc.finality = $finality,
                doc.ecli = $ecli,
                doc.title = $title,
                doc.updated_at = $updated_at,
                doc.created_at = coalesce(doc.created_at, $created_at)

            WITH doc

            // Create or merge court node if court is provided (using FOREACH for conditional execution)
            FOREACH (ignore IN CASE WHEN $court IS NOT NULL THEN [1] ELSE [] END |
                MERGE (court:Court {name: $court})
                SET court.jurisdiction = $jurisdiction,
                    court.updated_at = $updated_at
                MERGE (doc)-[:DECIDED_BY]->(court)
            )

            WITH doc

            // Create or merge jurisdiction node if jurisdiction is provided
            FOREACH (ignore IN CASE WHEN $jurisdiction IS NOT NULL THEN [1] ELSE [] END |
                MERGE (jurisdiction:Jurisdiction {id: $jurisdiction_id})
                SET jurisdiction.name = $jurisdiction,
                    jurisdiction.updated_at = $updated_at
                MERGE (doc)-[:BELONGS_TO_JURISDICTION]->(jurisdiction)
            )

            RETURN doc
        ';

        $now = now()->toIso8601String();

        $parameters = [
            'doc_id' => $docId,
            'case_number' => $meta['broj_odluke'] ?? null,
            'court' => $meta['sud'] ?? null,
            'jurisdiction' => $meta['jurisdiction'] ?? 'HR',
            'jurisdiction_id' => 'jurisdiction_'.($meta['jurisdiction'] ?? 'HR'),
            'decision_date' => $meta['datum_odluke'] ?? null,
            'publication_date' => $meta['datum_objave'] ?? null,
            'decision_type' => $meta['vrsta_odluke'] ?? null,
            'register' => $meta['upisnik'] ?? null,
            'finality' => $meta['pravomocnost'] ?? null,
            'ecli' => $meta['ecli'] ?? null,
            'title' => $meta['vrsta_odluke'] ?? 'Sudska odluka',
            'updated_at' => $now,
            'created_at' => $now,
        ];

        return compact('query', 'parameters');
    }

    /**
     * Find most influential court decisions using PageRank
     *
     * @param  int  $limit  Number of top decisions to return
     * @return array [['id' => '...', 'case_number' => '...', 'rank' => 0.85], ...]
     */
    public function findInfluentialDecisions(int $limit = 20): array
    {
        $cypher = "
            CALL gds.pageRank.stream('decisions-graph')
            YIELD nodeId, score
            MATCH (d:CourtDecisionDocument) WHERE id(d) = nodeId
            RETURN d.id AS id, d.case_number AS case_number,
                   d.court AS court, d.decision_date AS date,
                   score AS rank
            ORDER BY score DESC
            LIMIT \$limit
        ";

        $result = $this->graph->run($cypher, ['limit' => $limit]);

        return $result->toArray();
    }

    /**
     * Detect citation clusters using community detection
     *
     * @return array Communities with their member decisions
     */
    public function detectCitationClusters(): array
    {
        $cypher = "
            CALL gds.louvain.stream('decisions-graph')
            YIELD nodeId, communityId
            MATCH (d:CourtDecisionDocument) WHERE id(d) = nodeId
            RETURN communityId,
                   collect({id: d.id, case_number: d.case_number, court: d.court}) AS members
            ORDER BY size(members) DESC
        ";

        $result = $this->graph->run($cypher);

        return $result->toArray();
    }

    /**
     * Find related documents by graph topology (not just similarity)
     *
     * @param  string  $nodeId  Starting node ID
     * @param  int  $maxHops  Maximum relationship hops (default 3)
     * @return array Related nodes with relationship paths
     */
    public function findRelatedByTopology(string $nodeId, int $maxHops = 3): array
    {
        $cypher = '
            MATCH path = (start)-[*1..$maxHops]-(related)
            WHERE start.id = $nodeId
            WITH related, path, length(path) AS distance
            ORDER BY distance
            LIMIT 50
            RETURN related.id AS id,
                   labels(related)[0] AS type,
                   related.title AS title,
                   distance,
                   relationships(path) AS relationship_types
        ';

        $result = $this->graph->run($cypher, [
            'nodeId' => $nodeId,
            'maxHops' => $maxHops,
        ]);

        return $result->toArray();
    }

    /**
     * Analyze citation network for a specific decision
     *
     * @param  string  $decisionId  Decision ID to analyze
     * @return array Network metrics (centrality, clustering coefficient, etc.)
     */
    public function analyzeCitationNetwork(string $decisionId): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('GraphQueryHelper: analyzeCitationNetwork initiated', [
            'decision_id' => $decisionId,
            'user_id' => auth()->id(),
        ]);

        try {
            // Get basic network stats
            $cypher = '
                MATCH (d:CourtDecisionDocument {id: $id})
                OPTIONAL MATCH (d)-[:CITES]->(cited)
                OPTIONAL MATCH (d)<-[:CITES]-(citing)
                RETURN
                    count(DISTINCT cited) AS outgoing_citations,
                    count(DISTINCT citing) AS incoming_citations,
                    count(DISTINCT cited) + count(DISTINCT citing) AS total_degree
            ';

            $stats = $this->graph->run($cypher, ['id' => $decisionId])->first();

            // Get citation clustering coefficient
            $clusteringCypher = '
                MATCH (d:CourtDecisionDocument {id: $id})-[:CITES]->(neighbor)
                MATCH (neighbor)-[:CITES]->(common)
                MATCH (d)-[:CITES]->(common)
                RETURN count(DISTINCT common) AS common_citations
            ';

            $clustering = $this->graph->run($clusteringCypher, ['id' => $decisionId])->first();

            $result = [
                'decision_id' => $decisionId,
                'outgoing_citations' => $stats['outgoing_citations'] ?? 0,
                'incoming_citations' => $stats['incoming_citations'] ?? 0,
                'total_degree' => $stats['total_degree'] ?? 0,
                'common_citations' => $clustering['common_citations'] ?? 0,
                'clustering_coefficient' => $this->calculateClusteringCoefficient(
                    $stats['outgoing_citations'] ?? 0,
                    $clustering['common_citations'] ?? 0
                ),
            ];

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('GraphQueryHelper: analyzeCitationNetwork completed', [
                'decision_id' => $decisionId,
                'total_degree' => $result['total_degree'],
                'clustering_coefficient' => $result['clustering_coefficient'],
                'duration_ms' => round($duration, 2),
            ]);

            return $result;

        } catch (GraphException $e) {
            Log::error('GraphQueryHelper: analyzeCitationNetwork failed with GraphException', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('GraphQueryHelper: analyzeCitationNetwork failed with unexpected exception', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new GraphException(
                'Citation network analysis failed: '.$e->getMessage(),
                GraphException::QUERY_FAILED,
                $e
            );
        }
    }

    /**
     * Calculate clustering coefficient
     */
    protected function calculateClusteringCoefficient(int $neighbors, int $commonLinks): float
    {
        if ($neighbors < 2) {
            return 0.0;
        }

        $possibleLinks = ($neighbors * ($neighbors - 1)) / 2;

        return $possibleLinks > 0 ? round($commonLinks / $possibleLinks, 4) : 0.0;
    }

    /**
     * Find shortest path between two nodes
     *
     * @param  string  $fromId  Source node ID
     * @param  string  $toId  Target node ID
     * @param  int  $maxLength  Maximum path length
     * @return array|null Path nodes and relationships, or null if no path
     */
    public function findShortestPath(string $fromId, string $toId, int $maxLength = 5): ?array
    {
        $cypher = '
            MATCH (start {id: $fromId}), (end {id: $toId})
            MATCH path = shortestPath((start)-[*1..$maxLength]-(end))
            RETURN
                [node IN nodes(path) | {id: node.id, type: labels(node)[0], title: node.title}] AS nodes,
                [rel IN relationships(path) | type(rel)] AS relationships,
                length(path) AS length
        ';

        $result = $this->graph->run($cypher, [
            'fromId' => $fromId,
            'toId' => $toId,
            'maxLength' => $maxLength,
        ])->first();

        return $result ?: null;
    }
}
