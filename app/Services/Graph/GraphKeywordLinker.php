<?php

namespace App\Services\Graph;

use App\Contracts\GraphLinkerInterface;
use App\Services\AdvancedKeywordExtractor;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;

/**
 * Service for linking keywords to graph nodes
 *
 * Extracts keywords from content and creates Keyword nodes with HAS_KEYWORD relationships.
 * Uses hybrid approach (embeddings + TF-IDF) when available, falls back to rule-based extraction.
 */
class GraphKeywordLinker implements GraphLinkerInterface
{
    public function __construct(
        protected GraphDatabaseService $graph,
        protected ?AdvancedKeywordExtractor $advancedExtractor = null
    ) {}

    /**
     * Extract keywords and create links to a graph node
     *
     * @param  string  $nodeLabel  The label of the node (e.g., 'LawDocument')
     * @param  string  $nodeId  The ID of the node
     * @param  string|array|null  $content  The content to extract keywords from
     */
    public function link(string $nodeLabel, string $nodeId, $content): void
    {
        // Handle null or non-string content gracefully
        if ($content === null || (is_string($content) && trim($content) === '')) {
            return;
        }

        // Handle array content (not supported for keyword extraction)
        if (is_array($content)) {
            return;
        }

        // Delegate to advanced extractor if available and enabled
        if ($this->advancedExtractor && config('keywords.use_hybrid', true)) {
            try {
                $keywords = $this->advancedExtractor->extract($content);

                if (config('keywords.log_extraction', false)) {
                    Log::info('GraphKeywordLinker - Using hybrid keyword extraction', [
                        'node_label' => $nodeLabel,
                        'node_id' => $nodeId,
                        'keywords_count' => count($keywords),
                    ]);
                }
            } catch (\Exception $e) {
                // Fall back to legacy extraction on error
                if (config('keywords.log_fallback', true)) {
                    Log::warning('GraphKeywordLinker - Hybrid extraction failed, using legacy method', [
                        'node_label' => $nodeLabel,
                        'node_id' => $nodeId,
                        'error' => $e->getMessage(),
                    ]);
                }

                $keywords = $this->extractKeywords($content);
            }
        } else {
            // Use legacy extraction when hybrid is disabled or extractor unavailable
            $keywords = $this->extractKeywords($content);
        }

        foreach ($keywords as $keyword => $weight) {
            $keywordId = 'keyword_'.md5(strtolower($keyword));

            $this->graph->upsertNode('Keyword', $keywordId, [
                'name' => $keyword,
                'normalized' => mb_strtolower($keyword),
            ]);

            $this->graph->createRelationship(
                $nodeLabel,
                $nodeId,
                'HAS_KEYWORD',
                'Keyword',
                $keywordId,
                ['weight' => $weight]
            );
        }
    }

    /**
     * Extract keywords from content with legal term boosting (LEGACY METHOD)
     *
     * This is the rule-based fallback method used when hybrid extraction
     * is disabled or unavailable. Uses frequency analysis + legal term dictionary.
     *
     * @param  string  $content  The content to extract keywords from
     * @param  int  $maxKeywords  Maximum number of keywords to return
     * @return array Associative array of keyword => weight
     */
    protected function extractKeywords(string $content, int $maxKeywords = 10): array
    {
        // Croatian legal terms with boost weights
        $legalTerms = [
            // Core legal concepts
            'ugovor' => 2.5,        // contract
            'obveza' => 2.5,        // obligation
            'pravo' => 2.5,         // right/law
            'zakon' => 3.0,         // law/statute
            'odredba' => 2.5,       // provision
            'postupak' => 2.5,      // procedure
            'naknada' => 2.0,       // compensation
            'presuda' => 2.5,       // judgment/verdict
            'odluka' => 2.5,        // decision
            'rješenje' => 2.0,      // resolution

            // Legal entities and parties
            'tužitelj' => 2.0,      // plaintiff
            'tuženik' => 2.0,       // defendant
            'stranka' => 2.0,       // party
            'sud' => 2.5,           // court
            'sudac' => 2.0,         // judge
            'svjedok' => 2.0,       // witness
            'odvjetnik' => 2.0,     // lawyer

            // Legal processes
            'žalba' => 2.0,         // appeal
            'tužba' => 2.5,         // lawsuit
            'parnica' => 2.0,       // litigation
            'izvršenje' => 2.0,     // execution/enforcement
            'dokazivanje' => 2.0,   // proving/evidence
            'saslušanje' => 2.0,    // hearing
            'pretres' => 2.0,       // trial

            // Legal effects and outcomes
            'ništavost' => 2.0,     // nullity
            'poništenje' => 2.0,    // annulment
            'razvrgnuće' => 2.0,    // dissolution
            'prekid' => 1.8,        // termination
            'prestanak' => 1.8,     // cessation
            'stupanje' => 1.8,      // coming into force

            // Specific legal areas
            'kazneno' => 2.0,       // criminal
            'građansko' => 2.0,     // civil
            'upravno' => 2.0,       // administrative
            'trgovačko' => 2.0,     // commercial
            'radno' => 1.8,         // labor
            'obiteljsko' => 1.8,    // family

            // Important legal modifiers
            'zakonit' => 2.0,       // lawful
            'nezakonit' => 2.0,     // unlawful
            'valjan' => 1.8,        // valid
            'ništav' => 2.0,        // void
            'pravomočan' => 2.0,    // final/legally binding
            'izvršan' => 1.8,       // executable

            // Legal documents and norms
            'uredba' => 2.0,        // ordinance/regulation
            'pravilnik' => 2.0,     // rulebook
            'statut' => 2.0,        // statute
            'protokol' => 1.8,      // protocol
            'sporazum' => 2.0,      // agreement
            'konvencija' => 2.0,    // convention
        ];

        // Remove common Croatian stopwords
        $stopwords = ['je', 'su', 'biti', 'ima', 'da', 'za', 'na', 'u', 'i', 'ili', 'te', 'se', 'by', 'the', 'of', 'and', 'to', 'a', 'in', 'koji', 'koja', 'koje', 'ovaj', 'taj'];

        // Tokenize and clean
        $words = preg_split('/\s+/', mb_strtolower($content));
        $words = array_filter($words, fn ($w) => mb_strlen($w) > 3 && ! in_array($w, $stopwords));

        // Count frequencies
        $frequencies = array_count_values($words);

        // Apply legal term boosting
        foreach ($frequencies as $word => $freq) {
            if (isset($legalTerms[$word])) {
                $frequencies[$word] = $freq * $legalTerms[$word];
            }
        }

        arsort($frequencies);

        // Get top keywords and normalize weights
        $topKeywords = array_slice($frequencies, 0, $maxKeywords, true);
        $maxFreq = max($topKeywords ?: [1]);

        return array_map(fn ($freq) => round($freq / $maxFreq, 2), $topKeywords);
    }

    /**
     * Find and link similar nodes based on content similarity
     * Not applicable for keyword linker
     */
    public function linkSimilar(string $nodeType, string $nodeId, float $threshold = 0.8): int
    {
        return 0;
    }

    /**
     * Extract and link citations from content
     * Not applicable for keyword linker
     */
    public function linkCitations(string $nodeType, string $nodeId, string $content): array
    {
        return [];
    }

    /**
     * Link a node to relevant keywords and topics
     * Main functionality implemented in link() method
     */
    public function linkKeywords(string $nodeType, string $nodeId, string $content): array
    {
        $this->link($nodeType, $nodeId, $content);

        return []; // Returns array of keyword labels
    }

    /**
     * Remove all HAS_KEYWORD relationships for a given node
     *
     * Also cleans up orphaned Keyword nodes (keywords with no incoming relationships).
     *
     * @param  string  $nodeType  The type/label of the node (e.g., 'LawDocument')
     * @param  string  $nodeId  The ID of the node
     * @return int Number of relationships deleted
     */
    public function unlinkAll(string $nodeType, string $nodeId): int
    {
        try {
            // Step 1: Delete HAS_KEYWORD relationships and count them
            $deleteQuery = "
                MATCH (n:$nodeType {id: \$nodeId})-[r:HAS_KEYWORD]->(k:Keyword)
                DELETE r
                RETURN count(r) as count
            ";

            $result = $this->graph->run($deleteQuery, [
                'nodeType' => $nodeType,
                'nodeId' => $nodeId,
            ]);

            $count = $result->count ?? 0;

            // Step 2: Clean up orphaned Keyword nodes (no incoming relationships)
            $cleanupQuery = '
                MATCH (k:Keyword)
                WHERE NOT (k)<-[]
                DELETE k
                RETURN count(k) as count
            ';

            $this->graph->run($cleanupQuery, []);

            return $count;
        } catch (\RuntimeException $e) {
            // Neo4j is not available - return 0 gracefully
            Log::debug('GraphKeywordLinker::unlinkAll - Neo4j unavailable', [
                'node_type' => $nodeType,
                'node_id' => $nodeId,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get supported relationship types for keyword linker
     */
    public function getSupportedRelationships(string $nodeType): array
    {
        return ['HAS_KEYWORD'];
    }
}
