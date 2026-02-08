<?php

namespace App\Examples;

use App\Services\Graph\GraphEmbeddingService;
use App\Services\Graph\GraphRagOrchestrator;
use App\Services\Graph\ReasoningChainService;
use App\Services\Graph\TemporalReasoningService;
use App\Services\GraphDatabaseService;
use App\Services\GraphQueryHelper;
use App\Services\OpenAIService;

/**
 * Practical Examples: Using Neo4j as an LLM "Brain"
 *
 * This class demonstrates real-world patterns for combining LLMs with graph databases
 * to create a powerful knowledge and reasoning system.
 */
class LlmBrainExamples
{
    public function __construct(
        protected GraphRagOrchestrator $orchestrator,
        protected ReasoningChainService $reasoningChain,
        protected TemporalReasoningService $temporal,
        protected GraphQueryHelper $queryHelper,
        protected GraphDatabaseService $graph,
        protected GraphEmbeddingService $embeddings,
        protected OpenAIService $openai
    ) {}

    /**
     * Example 1: Hybrid Retrieval (Vector + Graph)
     *
     * Combines semantic search with graph relationships
     * for more comprehensive results.
     */
    public function hybridRetrieval(string $query): array
    {
        // Step 1: Vector search for semantic similarity
        $vectorResults = $this->orchestrator->enhancedQuery(
            query: $query,
            strategy: 'vector',
            limit: 20
        );

        // Step 2: Graph expansion - find related entities
        $graphContext = [];
        foreach ($vectorResults['results'] ?? [] as $result) {
            $nodeId = $result['node']['id'] ?? null;
            if ($nodeId) {
                $graphContext[] = $this->queryHelper->getGraphContext($nodeId);
            }
        }

        // Step 3: Merge and re-rank results
        return [
            'query' => $query,
            'vector_results' => $vectorResults,
            'graph_context' => $graphContext,
            'total_entities' => count($graphContext),
            'strategy' => 'hybrid',
        ];
    }

    /**
     * Example 2: Natural Language Graph Queries
     *
     * Let LLM convert natural language to Cypher queries
     */
    public function naturalLanguageQuery(string $nlQuery): array
    {
        return $this->reasoningChain->executeReasoningChain($nlQuery);

        // Examples:
        // - "Find Supreme Court decisions from 2023"
        // - "Find laws that contradict each other"
        // - "Show citation chains for law number NN 152/08"
        // - "Find prosecutors with high evidence suppression rates"
    }

    /**
     * Example 3: Time-Travel Queries
     *
     * Query what the law was at a specific point in time
     */
    public function timeTravelQuery(string $lawId, string $date): array
    {
        $validLaw = $this->temporal->findValidLawsAt($date, $lawId);

        return [
            'query_date' => $date,
            'law_id' => $lawId,
            'valid_version' => $validLaw,
            'explanation' => "This was the valid version of the law on {$date}",
        ];
    }

    /**
     * Example 4: Multi-Hop Reasoning Chains
     *
     * Traverse relationships to find complex connections
     */
    public function findReasoningChain(string $startCaseId, string $targetLawId): array
    {
        $query = '
            MATCH path = shortestPath(
                (c:Case {id: $caseId})-[*1..5]-(l:Law {id: $lawId})
            )
            RETURN nodes(path) as chain, relationships(path) as relationships
        ';

        $result = $this->graph->run($query, [
            'caseId' => $startCaseId,
            'lawId' => $targetLawId,
        ]);

        if ($result->count() === 0) {
            return ['found' => false];
        }

        $chain = $result->first()->get('chain');
        $relationships = $result->first()->get('relationships');

        return [
            'found' => true,
            'path_length' => count($chain),
            'nodes' => array_map(fn ($node) => [
                'type' => $node->getLabels()[0] ?? 'Unknown',
                'properties' => $node->getProperties(),
            ], $chain),
            'relationships' => array_map(fn ($rel) => [
                'type' => $rel->getType(),
                'properties' => $rel->getProperties(),
            ], $relationships),
        ];
    }

    /**
     * Example 5: Contradiction Detection with Explanation
     *
     * Find contradictions and have LLM explain them
     */
    public function detectAndExplainContradictions(string $decisionId): array
    {
        // Find contradictions
        $contradictions = $this->temporal->detectContradictions($decisionId);

        if (empty($contradictions)) {
            return ['contradictions_found' => false];
        }

        // For each contradiction, get LLM explanation
        foreach ($contradictions as &$contradiction) {
            $explanation = $this->explainContradiction(
                $decisionId,
                $contradiction['contradicting_decision_id']
            );
            $contradiction['llm_explanation'] = $explanation;
        }

        return [
            'contradictions_found' => true,
            'count' => count($contradictions),
            'contradictions' => $contradictions,
        ];
    }

    /**
     * Example 6: Knowledge Graph Enhanced Chat
     *
     * Chat with LLM that has access to graph knowledge
     */
    public function chatWithGraphKnowledge(string $userMessage): array
    {
        // Step 1: Extract key entities from message
        $entities = $this->extractEntities($userMessage);

        // Step 2: Query graph for relevant context
        $graphContext = $this->buildGraphContext($entities);

        // Step 3: Build enhanced system prompt
        $systemPrompt = $this->buildSystemPromptWithGraph($graphContext);

        // Step 4: Send to LLM
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage],
        ];

        $response = $this->openai->chat($messages, 'gpt-4o');

        return [
            'user_message' => $userMessage,
            'extracted_entities' => $entities,
            'graph_context_used' => count($graphContext),
            'llm_response' => $response['content'],
            'total_tokens' => $response['usage']['total_tokens'] ?? 0,
        ];
    }

    /**
     * Example 7: Structural Similarity (Graph Embeddings)
     *
     * Find cases that are structurally similar (not just content-similar)
     */
    public function findStructurallySimilarCases(string $caseId): array
    {
        // This uses Node2Vec embeddings to find cases with similar graph structure
        // (similar citation patterns, similar entities involved, etc.)

        $similar = $this->embeddings->findSimilarByStructure($caseId, limit: 10);

        return [
            'query_case' => $caseId,
            'similar_cases' => $similar,
            'similarity_type' => 'structural (Node2Vec)',
            'note' => 'These cases have similar graph structure, not just similar text',
        ];
    }

    /**
     * Example 8: Influence Analysis
     *
     * Find most influential laws/decisions using PageRank
     */
    public function findInfluentialEntities(): array
    {
        // Find influential laws
        $influentialLaws = $this->queryHelper->findInfluentialDocuments('Law', 20);

        // Find influential decisions
        $influentialDecisions = $this->queryHelper->findInfluentialDocuments('Decision', 20);

        return [
            'influential_laws' => $influentialLaws,
            'influential_decisions' => $influentialDecisions,
            'metric' => 'PageRank (citation count)',
        ];
    }

    /**
     * Example 9: Topic Exploration
     *
     * Explore a legal topic through graph relationships
     */
    public function exploreTopic(string $topic): array
    {
        $query = '
            MATCH (k:Keyword {term: $topic})<-[:HAS_KEYWORD]-(doc)
            WITH doc
            MATCH (doc)-[r]-(related)
            RETURN DISTINCT
                labels(doc)[0] as doc_type,
                count(doc) as doc_count,
                type(r) as relationship_type,
                labels(related)[0] as related_type,
                count(related) as related_count
            ORDER BY doc_count DESC
        ';

        $results = $this->graph->run($query, ['topic' => $topic]);

        return [
            'topic' => $topic,
            'exploration' => $results->map(fn ($r) => [
                'doc_type' => $r->get('doc_type'),
                'doc_count' => $r->get('doc_count'),
                'relationship' => $r->get('relationship_type'),
                'related_type' => $r->get('related_type'),
                'related_count' => $r->get('related_count'),
            ])->toArray(),
        ];
    }

    /**
     * Example 10: Counterfactual Reasoning
     *
     * "What if this law didn't exist?"
     */
    public function counterfactualAnalysis(string $lawId): array
    {
        // Find all cases that cite this law
        $affectedCases = $this->queryHelper->findCasesApplyingLaw($lawId);

        // Analyze what would change
        return [
            'law_id' => $lawId,
            'directly_affected_cases' => count($affectedCases),
            'affected_cases' => $affectedCases,
            'analysis' => 'If this law did not exist, these cases would need different legal basis',
        ];
    }

    // ==================== HELPER METHODS ====================

    /**
     * Extract entities from text using LLM
     */
    private function extractEntities(string $text): array
    {
        $prompt = <<<PROMPT
Extract key legal entities from this text. Return JSON array with:
- laws (law numbers, e.g., "NN 152/08")
- courts (court names)
- legal_concepts (e.g., "proportionality", "due process")
- dates

Text: {$text}

Return only JSON, no other text.
PROMPT;

        $response = $this->openai->chat([
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o-mini', ['temperature' => 0.1]);

        $entities = json_decode($response['content'], true);

        return $entities ?? [];
    }

    /**
     * Build graph context from extracted entities
     */
    private function buildGraphContext(array $entities): array
    {
        $context = [];

        // Find laws
        foreach ($entities['laws'] ?? [] as $lawNumber) {
            $query = 'MATCH (l:Law {law_number: $lawNumber}) RETURN l LIMIT 1';
            $result = $this->graph->run($query, ['lawNumber' => $lawNumber]);

            if ($result->count() > 0) {
                $context['laws'][] = $result->first()->get('l')->getProperties();
            }
        }

        // Find related decisions
        foreach ($entities['legal_concepts'] ?? [] as $concept) {
            $query = '
                MATCH (d:Decision)-[:HAS_KEYWORD]->(k:Keyword {term: $concept})
                RETURN d
                ORDER BY d.decision_date DESC
                LIMIT 3
            ';
            $results = $this->graph->run($query, ['concept' => $concept]);

            $context['related_decisions'][$concept] = $results->map(
                fn ($r) => $r->get('d')->getProperties()
            )->toArray();
        }

        return $context;
    }

    /**
     * Build system prompt with graph context
     */
    private function buildSystemPromptWithGraph(array $graphContext): string
    {
        $prompt = "You are a Croatian legal expert with access to a knowledge graph.\n\n";

        if (! empty($graphContext['laws'])) {
            $prompt .= "Relevant laws:\n";
            foreach ($graphContext['laws'] as $law) {
                $prompt .= "- {$law['title']} ({$law['law_number']})\n";
                $prompt .= "  Status: {$law['status']}\n";
            }
            $prompt .= "\n";
        }

        if (! empty($graphContext['related_decisions'])) {
            $prompt .= "Related court decisions:\n";
            foreach ($graphContext['related_decisions'] as $concept => $decisions) {
                $prompt .= "Topic: {$concept}\n";
                foreach ($decisions as $decision) {
                    $prompt .= "- {$decision['title']} ({$decision['decision_date']})\n";
                }
            }
            $prompt .= "\n";
        }

        $prompt .= 'Use this knowledge to provide accurate, well-cited answers.';

        return $prompt;
    }

    /**
     * Get LLM to explain contradiction
     */
    private function explainContradiction(string $decisionId1, string $decisionId2): string
    {
        // Fetch both decisions
        $query = '
            MATCH (d1:Decision {id: $id1})
            MATCH (d2:Decision {id: $id2})
            RETURN d1, d2
        ';

        $result = $this->graph->run($query, [
            'id1' => $decisionId1,
            'id2' => $decisionId2,
        ]);

        if ($result->count() === 0) {
            return 'Could not retrieve decisions';
        }

        $d1 = $result->first()->get('d1')->getProperties();
        $d2 = $result->first()->get('d2')->getProperties();

        // Ask LLM to explain
        $prompt = <<<PROMPT
Analyze these two court decisions and explain how they contradict each other:

Decision 1:
Title: {$d1['title']}
Court: {$d1['court']}
Date: {$d1['decision_date']}
Summary: {$d1['summary']}

Decision 2:
Title: {$d2['title']}
Court: {$d2['court']}
Date: {$d2['decision_date']}
Summary: {$d2['summary']}

Explain the contradiction in 2-3 sentences.
PROMPT;

        $response = $this->openai->chat([
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o');

        return $response['content'];
    }
}
