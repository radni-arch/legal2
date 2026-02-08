<?php

namespace App\Services\Graph;

use App\Contracts\GraphLinkerInterface;
use App\Services\GraphDatabaseService;
use App\Services\LegalCitations\HrLegalCitationsDetector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for linking citations to graph nodes
 *
 * Extracts citations from content and creates CITES relationships to referenced laws.
 * Uses HrLegalCitationsDetector for comprehensive citation detection and legacy
 * patterns for backward compatibility.
 */
class GraphCitationLinker implements GraphLinkerInterface
{
    public function __construct(
        protected GraphDatabaseService $graph,
        protected ?HrLegalCitationsDetector $citationDetector = null
    ) {
        // Lazy-load detector if not injected
        $this->citationDetector ??= app(HrLegalCitationsDetector::class);
    }

    /**
     * Extract citations and create links to a graph node
     *
     * @param  string  $nodeLabel  The label of the node (e.g., 'LawDocument')
     * @param  string  $nodeId  The ID of the node
     * @param  string|array|null  $content  The content to extract citations from
     */
    public function link(string $nodeLabel, string $nodeId, $content): void
    {
        // Handle null or non-string content gracefully
        if ($content === null || (is_string($content) && trim($content) === '')) {
            return;
        }

        // Handle array content (not supported for citation extraction)
        if (is_array($content)) {
            return;
        }

        // Use HrLegalCitationsDetector for comprehensive citation extraction
        $detectedCitations = $this->citationDetector->detectAll($content);
        $citations = $this->convertDetectedToCitations($detectedCitations);

        // Also run legacy patterns for backward compatibility
        $legacyCitations = $this->extractLegacyCitations($content);
        $citations = array_merge($citations, $legacyCitations);

        // Deduplicate
        $citations = $this->deduplicateCitations($citations);

        // Process citations and create relationships
        $this->processCitations($nodeLabel, $nodeId, $citations);

        Log::info('Citation extraction completed', [
            'node_label' => $nodeLabel,
            'node_id' => $nodeId,
            'citations_found' => count($citations),
        ]);
    }

    /**
     * Convert HrLegalCitationsDetector output to internal citation format
     */
    protected function convertDetectedToCitations(array $detected): array
    {
        $citations = [];

        // Process statute citations
        foreach ($detected['statutes'] ?? [] as $statute) {
            $citations[] = [
                'type' => 'statute_citation',
                'law_name' => $statute['law'] ?? null,
                'article' => $statute['article'] ?? null,
                'paragraph' => $statute['paragraph'] ?? null,
                'item' => $statute['item'] ?? null,
                'canonical' => $statute['canonical'] ?? null,
            ];
        }

        // Process case number citations
        foreach ($detected['case_numbers'] ?? [] as $caseRef) {
            $citations[] = [
                'type' => 'case_reference',
                'value' => $caseRef['canonical'] ?? $caseRef['match'] ?? null,
            ];
        }

        // Process ECLI citations
        foreach ($detected['ecli'] ?? [] as $ecli) {
            $citations[] = [
                'type' => 'ecli_reference',
                'value' => $ecli['canonical'] ?? $ecli['match'] ?? null,
            ];
        }

        // Process Narodne Novine citations
        foreach ($detected['narodne_novine'] ?? [] as $nn) {
            foreach ($nn['issues'] ?? [] as $issue) {
                $citations[] = [
                    'type' => 'law_number',
                    'value' => $issue,
                ];
            }
        }

        return $citations;
    }

    /**
     * Extract citations using legacy patterns (backward compatibility)
     */
    protected function extractLegacyCitations(string $content): array
    {
        $citations = [];

        // Pattern 1: NN citations - "NN 123/20", "Narodne novine 45/2021"
        preg_match_all('/(?:NN|Narodne\s+novine)\s+(\d+\/\d+)/iu', $content, $matches);
        foreach ($matches[1] as $lawNumber) {
            $citations[] = [
                'type' => 'law_number',
                'value' => $lawNumber,
            ];
        }

        // Pattern 2: Article references with law numbers
        preg_match_all('/(?:članak|čl\.?)\s+(\d+)(?:\.|,)?\s*(?:Zakona?\s+)?(?:\()?(?:NN|Narodne\s+novine)\s+(\d+\/\d+)/iu', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $citations[] = [
                'type' => 'article_reference',
                'article' => $match[1],
                'law_number' => $match[2],
            ];
        }

        // Pattern 3: Law names with citations
        preg_match_all('/Zakon\s+o\s+([^\(]{5,100})\s*\((?:NN|Narodne\s+novine)\s+(\d+\/\d+)\)/iu', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $citations[] = [
                'type' => 'named_law',
                'name' => trim($match[1]),
                'law_number' => $match[2],
            ];
        }

        // Pattern 4: Case references
        preg_match_all('/(?:odluka|presuda|rješenje)\s+(?:broj:?\s+)?([A-Z]+-?\d+\/\d+(?:-\d+)?)/iu', $content, $matches);
        foreach ($matches[1] as $caseNumber) {
            $citations[] = [
                'type' => 'case_reference',
                'value' => $caseNumber,
            ];
        }

        return $citations;
    }

    /**
     * Deduplicate citations based on type and key values
     */
    protected function deduplicateCitations(array $citations): array
    {
        $seen = [];
        $unique = [];

        foreach ($citations as $citation) {
            $key = $this->getCitationKey($citation);
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $citation;
            }
        }

        return $unique;
    }

    protected function getCitationKey(array $citation): string
    {
        return md5(json_encode([
            'type' => $citation['type'],
            'law_number' => $citation['law_number'] ?? null,
            'law_name' => $citation['law_name'] ?? null,
            'article' => $citation['article'] ?? null,
            'value' => $citation['value'] ?? null,
        ]));
    }

    /**
     * Process citations and create graph relationships
     */
    protected function processCitations(string $nodeLabel, string $nodeId, array $citations): void
    {
        foreach ($citations as $citation) {
            try {
                $this->processSingleCitation($nodeLabel, $nodeId, $citation);
            } catch (\Exception $e) {
                Log::warning('Failed to create citation relationship', [
                    'citation' => $citation,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Process a single citation
     */
    protected function processSingleCitation(string $nodeLabel, string $nodeId, array $citation): void
    {
        // Try to find law by law_number
        if (isset($citation['law_number'])) {
            $this->linkLawByNumber($nodeLabel, $nodeId, $citation);

            return;
        }

        // Try to find law by name (statute citations from HrLegalCitationsDetector)
        if (isset($citation['law_name']) && $citation['type'] === 'statute_citation') {
            $this->linkLawByName($nodeLabel, $nodeId, $citation);

            return;
        }

        // Handle direct law_number type
        if ($citation['type'] === 'law_number' && isset($citation['value'])) {
            $this->linkLawByNumber($nodeLabel, $nodeId, ['law_number' => $citation['value']] + $citation);

            return;
        }

        // Handle case references
        if ($citation['type'] === 'case_reference' && isset($citation['value'])) {
            $this->linkCaseReference($nodeLabel, $nodeId, $citation);

            return;
        }
    }

    /**
     * Link to a law found by NN number
     */
    protected function linkLawByNumber(string $nodeLabel, string $nodeId, array $citation): void
    {
        $lawNumber = $citation['law_number'] ?? $citation['value'] ?? null;
        if (! $lawNumber) {
            return;
        }

        $referencedLaw = DB::table('laws')
            ->where('law_number', $lawNumber)
            ->first();

        if ($referencedLaw) {
            $this->createLawCitation($nodeLabel, $nodeId, $referencedLaw, $citation);
        }
    }

    /**
     * Link to a law found by name (new capability)
     */
    protected function linkLawByName(string $nodeLabel, string $nodeId, array $citation): void
    {
        $lawName = $citation['law_name'] ?? null;
        if (! $lawName) {
            return;
        }

        // Try exact abbreviation match first
        $referencedLaw = DB::table('laws')
            ->where('abbreviation', 'ILIKE', $lawName)
            ->first();

        if (! $referencedLaw) {
            // Try title match
            $referencedLaw = DB::table('laws')
                ->where('title', 'ILIKE', "%{$lawName}%")
                ->first();
        }

        if (! $referencedLaw) {
            // Try partial match with key words
            $keywords = array_filter(explode(' ', $lawName), fn ($w) => mb_strlen($w) > 3);
            if (! empty($keywords)) {
                $query = DB::table('laws');
                foreach ($keywords as $keyword) {
                    $query->where('title', 'ILIKE', "%{$keyword}%");
                }
                $referencedLaw = $query->first();
            }
        }

        if ($referencedLaw) {
            $this->createLawCitation($nodeLabel, $nodeId, $referencedLaw, $citation);
        }
    }

    /**
     * Create CITES relationship to a law
     */
    protected function createLawCitation(string $nodeLabel, string $nodeId, object $law, array $citation): void
    {
        $this->graph->upsertNode('LawDocument', $law->id, [
            'doc_id' => $law->doc_id ?? $law->id,
            'title' => $law->title,
            'law_number' => $law->law_number,
        ]);

        $relationshipProps = [
            'citation_type' => $citation['type'],
            'created_at' => now()->toIso8601String(),
        ];

        if (isset($citation['article'])) {
            $relationshipProps['article'] = $citation['article'];
        }
        if (isset($citation['paragraph'])) {
            $relationshipProps['paragraph'] = $citation['paragraph'];
        }
        if (isset($citation['item'])) {
            $relationshipProps['item'] = $citation['item'];
        }
        if (isset($citation['canonical'])) {
            $relationshipProps['canonical'] = $citation['canonical'];
        }

        $this->graph->createRelationship(
            $nodeLabel,
            $nodeId,
            'CITES',
            'LawDocument',
            $law->id,
            $relationshipProps
        );
    }

    /**
     * Link case reference
     */
    protected function linkCaseReference(string $nodeLabel, string $nodeId, array $citation): void
    {
        $referencedCase = DB::table('cases_documents')
            ->where('doc_id', 'LIKE', '%'.$citation['value'].'%')
            ->first();

        if ($referencedCase) {
            $this->graph->upsertNode('CaseDocument', $referencedCase->id, [
                'case_id' => $referencedCase->case_id,
                'doc_id' => $referencedCase->doc_id,
                'title' => $referencedCase->title,
            ]);

            $this->graph->createRelationship(
                $nodeLabel,
                $nodeId,
                'REFERENCES',
                'CaseDocument',
                $referencedCase->id,
                [
                    'citation_type' => 'case_reference',
                    'created_at' => now()->toIso8601String(),
                ]
            );
        }
    }

    /**
     * Link a statutory interpretation relationship
     */
    public function linkInterpretation(
        string $decisionId,
        string $lawId,
        string $article,
        string $interpretationType,
        ?string $interpretationSummary = null,
        bool $binding = false
    ): void {
        $this->graph->createRelationship(
            'CourtDecisionDocument',
            $decisionId,
            'INTERPRETS',
            'LawDocument',
            $lawId,
            [
                'article' => $article,
                'interpretation_type' => $interpretationType,
                'interpretation' => $interpretationSummary,
                'binding' => $binding,
                'created_at' => now()->toIso8601String(),
            ]
        );
    }

    /**
     * Find and link similar nodes based on content similarity
     * Not applicable for citation linker
     */
    public function linkSimilar(string $nodeType, string $nodeId, float $threshold = 0.8): int
    {
        return 0;
    }

    /**
     * Extract and link citations from content
     * Main functionality implemented in link() method
     */
    public function linkCitations(string $nodeType, string $nodeId, string $content): array
    {
        $this->link($nodeType, $nodeId, $content);

        return []; // Returns array of cited document IDs
    }

    /**
     * Link a node to relevant keywords and topics
     * Not applicable for citation linker
     */
    public function linkKeywords(string $nodeType, string $nodeId, string $content): array
    {
        return [];
    }

    /**
     * Remove all CITES and REFERENCES relationships for a given node
     *
     * Deletes all CITES and REFERENCES relationships (both incoming and outgoing)
     * for the specified node. Returns the count of deleted relationships.
     *
     * @param  string  $nodeType  The node label (e.g., 'Decision', 'Law')
     * @param  string  $nodeId  The node ID
     * @return int Count of deleted relationships
     *
     * @throws \App\Exceptions\GraphException If deletion fails (not connection issue)
     */
    public function unlinkAll(string $nodeType, string $nodeId): int
    {
        $startTime = microtime(true);

        try {
            // Build Cypher query to delete all CITES and REFERENCES relationships
            $query = "
                MATCH (n:{$nodeType} {id: \$nodeId})-[r:CITES|REFERENCES]-()
                DELETE r
                RETURN count(r) as count
            ";

            // Execute query
            $result = $this->graph->query($query, ['nodeId' => $nodeId]);

            // Extract count from result
            $deletedCount = $result[0]['count(r)'] ?? 0;

            // Log success with performance metrics
            $duration = (microtime(true) - $startTime) * 1000; // ms
            Log::info('GraphCitationLinker::unlinkAll - Relationships deleted', [
                'node_type' => $nodeType,
                'node_id' => $nodeId,
                'deleted_count' => $deletedCount,
                'duration_ms' => round($duration, 2),
            ]);

            return $deletedCount;

        } catch (\Exception $e) {
            // Check if it's a connection issue (Neo4j unavailable)
            if (str_contains($e->getMessage(), 'Connection refused') ||
                str_contains($e->getMessage(), 'Could not connect') ||
                str_contains($e->getMessage(), 'No alive nodes found')) {

                // Graceful handling - log warning and return 0
                Log::warning('GraphCitationLinker::unlinkAll - Neo4j unavailable', [
                    'node_type' => $nodeType,
                    'node_id' => $nodeId,
                    'error' => $e->getMessage(),
                ]);

                return 0;
            }

            // Non-connection error - log and throw GraphException
            Log::error('GraphCitationLinker::unlinkAll - Failed to delete relationships', [
                'node_type' => $nodeType,
                'node_id' => $nodeId,
                'error' => $e->getMessage(),
            ]);

            throw new \App\Exceptions\GraphException(
                "Failed to delete relationships for {$nodeType}:{$nodeId}",
                \App\Exceptions\GraphException::RELATIONSHIP_DELETE_FAILED,
                $e
            );
        }
    }

    /**
     * Get supported relationship types for citation linker
     */
    public function getSupportedRelationships(string $nodeType): array
    {
        return ['CITES', 'REFERENCES', 'INTERPRETS'];
    }
}
