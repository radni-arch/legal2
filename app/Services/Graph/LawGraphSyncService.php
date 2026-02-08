<?php

namespace App\Services\Graph;

use App\Services\GraphDatabaseService;
use App\Services\TaggingService;
use Illuminate\Support\Facades\DB;

/**
 * Service for syncing law documents to the graph database
 *
 * Extracted from GraphRagService to provide focused, single-responsibility
 * service for law document synchronization.
 *
 * Responsibilities:
 * - Create/update law nodes in Neo4j
 * - Create jurisdiction nodes and relationships
 * - Delegate keyword extraction to GraphKeywordLinker
 * - Delegate citation extraction to GraphCitationLinker
 * - Delegate similarity calculation to GraphSimilarityLinker
 * - Auto-tag laws with metadata
 */
class LawGraphSyncService implements GraphSyncServiceInterface
{
    public function __construct(
        protected GraphDatabaseService $graph,
        protected GraphKeywordLinker $keywordLinker,
        protected GraphCitationLinker $citationLinker,
        protected GraphSimilarityLinker $similarityLinker,
        protected TaggingService $tagging
    ) {}

    /**
     * Sync a law document to the graph database
     *
     * @param  string  $lawId  The law document ID
     * @return array{nodes: array<string, int>, relationships: array<string, int>, errors: array, duration_ms: int}
     */
    public function sync(string $lawId): array
    {
        $law = DB::table('laws')->where('id', $lawId)->first();

        if (! $law) {
            return [
                'nodes' => [],
                'relationships' => [],
                'errors' => [],
                'duration_ms' => 0,
            ];
        }

        // Create law document node
        $this->createLawNode($law);

        // Create jurisdiction node and relationship
        $this->createJurisdictionRelationship($law);

        // Extract and link keywords
        $this->keywordLinker->link('LawDocument', $law->id, $law->content);

        // Extract and create citation relationships
        $this->citationLinker->link('LawDocument', $law->id, $law->content);

        // Find and create similarity relationships
        $this->similarityLinker->link('LawDocument', $law->id, $law->embedding ?? $law->embedding_vector ?? null);

        // Auto-tag the law
        $metadata = json_decode($law->metadata ?? '[]', true);
        // Ensure metadata is always an array (json_decode returns null for invalid JSON)
        if (! is_array($metadata)) {
            $metadata = [];
        }

        $this->tagging->autoTag('LawDocument', $law->id, $law->content, array_merge($metadata, [
            'jurisdiction' => $law->jurisdiction,
            'law_number' => $law->law_number,
        ]));

        // TODO: Add metrics tracking similar to DecisionGraphSyncService
        // For now, return empty metrics structure to satisfy interface
        return [
            'nodes' => [],
            'relationships' => [],
            'errors' => [],
            'duration_ms' => 0,
        ];
    }

    /**
     * Check if this service supports a given document type
     *
     * @param  string  $type  The document type
     */
    public function supportsType(string $type): bool
    {
        return $type === 'LawDocument';
    }

    /**
     * Create the law document node in the graph
     *
     * @param  object  $law  The law database record
     */
    protected function createLawNode($law): void
    {
        $this->graph->upsertNode('LawDocument', $law->id, [
            'doc_id' => $law->doc_id,
            'title' => $law->title,
            'law_number' => $law->law_number,
            'jurisdiction' => $law->jurisdiction,
            'country' => $law->country,
            'language' => $law->language,
            'chunk_index' => $law->chunk_index,
            'content_hash' => $law->content_hash,
            'effective_date' => $law->effective_date,
            'promulgation_date' => $law->promulgation_date,
            // Sprint 4.1: Temporal fields for tracking law evolution
            'valid_from' => $law->valid_from ?? null,
            'valid_until' => $law->valid_until ?? null,
            'version' => $law->version ?? null,
            // Phase 4: Amendment tracking properties
            'amendments' => isset($law->amendments) ? json_decode($law->amendments, true) : [],
            'repeal_date' => $law->repeal_date ?? null,
            'repealed_by' => $law->repealed_by ?? null,
            'parent_law_number' => $law->parent_law_number ?? null,
            'consolidation_date' => $law->consolidation_date ?? null,
        ]);
    }

    /**
     * Create jurisdiction node and relationship if jurisdiction exists
     *
     * @param  object  $law  The law database record
     */
    protected function createJurisdictionRelationship($law): void
    {
        if (! $law->jurisdiction) {
            return;
        }

        $this->graph->upsertNode('Jurisdiction', 'jurisdiction_'.$law->jurisdiction, [
            'name' => $law->jurisdiction,
        ]);

        $this->graph->createRelationship(
            'LawDocument',
            $law->id,
            'BELONGS_TO_JURISDICTION',
            'Jurisdiction',
            'jurisdiction_'.$law->jurisdiction
        );
    }

    /**
     * Create SUPERSEDED_BY relationship between old and new law versions
     *
     * @param  string  $oldLawId  The ID of the superseded law
     * @param  string  $newLawId  The ID of the new law version
     * @param  string  $date  The date when the law was superseded
     */
    public function createSupersededByRelationship(string $oldLawId, string $newLawId, string $date): void
    {
        $this->graph->createRelationship(
            'LawDocument',
            $oldLawId,
            'SUPERSEDED_BY',
            'LawDocument',
            $newLawId,
            ['superseded_date' => $date]
        );
    }

    /**
     * Create SUPERSEDES relationship (reverse of SUPERSEDED_BY)
     *
     * @param  string  $newLawId  The ID of the new law version
     * @param  string  $oldLawId  The ID of the superseded law
     * @param  string  $date  The date when the new law superseded the old one
     */
    public function createSupersedesRelationship(string $newLawId, string $oldLawId, string $date): void
    {
        $this->graph->createRelationship(
            'LawDocument',
            $newLawId,
            'SUPERSEDES',
            'LawDocument',
            $oldLawId,
            ['superseded_date' => $date]
        );
    }

    /**
     * Create AMENDED_BY relationship between law and amendment
     *
     * @param  string  $lawId  The ID of the original law
     * @param  string  $amendmentId  The ID of the amendment law
     * @param  string  $date  The date of the amendment
     * @param  string  $scope  Description of what was amended
     */
    public function createAmendedByRelationship(string $lawId, string $amendmentId, string $date, string $scope): void
    {
        $this->graph->createRelationship(
            'LawDocument',
            $lawId,
            'AMENDED_BY',
            'LawDocument',
            $amendmentId,
            [
                'amendment_date' => $date,
                'amendment_scope' => $scope,
            ]
        );
    }

    /**
     * Create CONTRADICTS relationship between two laws
     *
     * @param  string  $law1Id  The ID of the first law
     * @param  string  $law2Id  The ID of the second law
     * @param  string  $type  Type of contradiction (e.g., 'conflicting_provisions', 'overlapping_jurisdiction')
     * @param  array  $articles  Array of article descriptions that contradict
     */
    public function createContradictsRelationship(string $law1Id, string $law2Id, string $type, array $articles): void
    {
        $this->graph->createRelationship(
            'LawDocument',
            $law1Id,
            'CONTRADICTS',
            'LawDocument',
            $law2Id,
            [
                'contradiction_type' => $type,
                'articles' => $articles,
            ]
        );
    }

    /**
     * Get the law version that was valid at a specific date
     *
     * @param  string  $lawNumber  The law number (e.g., 'NN 152/08')
     * @param  string  $queryDate  The date to query (YYYY-MM-DD format)
     * @return array|null Law node data if found, null otherwise
     */
    public function getLawVersionAtDate(string $lawNumber, string $queryDate): ?array
    {
        $cypher = <<<'CYPHER'
            MATCH (law:LawDocument)
            WHERE law.law_number = $law_number
              AND law.valid_from <= $query_date
              AND (law.valid_until IS NULL OR law.valid_until >= $query_date)
            RETURN
                law.id AS law_id,
                law.version AS version,
                law.valid_from AS valid_from,
                law.valid_until AS valid_until
            LIMIT 1
        CYPHER;

        $results = $this->graph->query($cypher, [
            'law_number' => $lawNumber,
            'query_date' => $queryDate,
        ]);

        return ! empty($results) ? $results[0] : null;
    }

    /**
     * Find all amendments to a law
     *
     * @param  string  $lawId  The ID of the law
     * @return array Array of amendment records
     */
    public function findAllAmendments(string $lawId): array
    {
        $cypher = <<<'CYPHER'
            MATCH (law:LawDocument)-[r:AMENDED_BY]->(amendment:LawDocument)
            WHERE law.id = $law_id
            RETURN
                amendment.id AS amendment_law_id,
                r.amendment_date AS amendment_date,
                r.amendment_scope AS amendment_scope
            ORDER BY r.amendment_date ASC
        CYPHER;

        return $this->graph->query($cypher, ['law_id' => $lawId]);
    }

    /**
     * Get all versions of a law in chronological order
     *
     * @param  string  $lawNumber  The law number (e.g., 'NN 152/08')
     * @return array Array of law versions ordered by valid_from date
     */
    public function getAllVersions(string $lawNumber): array
    {
        $cypher = <<<'CYPHER'
            MATCH (law:LawDocument)
            WHERE law.law_number = $law_number
            OPTIONAL MATCH (law)-[:SUPERSEDED_BY*]->(newer:LawDocument)
            WITH law
            RETURN
                law.id AS law_id,
                law.version AS version,
                law.valid_from AS valid_from,
                law.valid_until AS valid_until
            ORDER BY law.valid_from ASC
        CYPHER;

        return $this->graph->query($cypher, ['law_number' => $lawNumber]);
    }

    /**
     * Find all laws that contradict the given law
     *
     * @param  string  $lawId  The ID of the law
     * @return array Array of contradicting law records
     */
    public function findContradictions(string $lawId): array
    {
        $cypher = <<<'CYPHER'
            MATCH (law:LawDocument)-[r:CONTRADICTS]-(contradicting:LawDocument)
            WHERE law.id = $law_id
            RETURN DISTINCT
                contradicting.id AS contradicting_law_id,
                r.contradiction_type AS contradiction_type,
                r.articles AS articles
        CYPHER;

        return $this->graph->query($cypher, ['law_id' => $lawId]);
    }
}
