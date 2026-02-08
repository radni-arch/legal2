<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Explainability\ReasoningTraceService;
use App\Services\Graph\LawGraphSyncService;
use App\Services\Graph\ReasoningChainService;
use App\Services\OpenAIService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Test Neo4j schema documentation in ReasoningChainService system prompt
 *
 * Verifies that the system prompt in convertNLToCypher() includes complete
 * schema documentation for all node types and relationship types used in
 * the Neo4j graph database.
 */
class ReasoningChainServiceSchemaTest extends TestCase
{
    private ReasoningChainService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Create service with mock dependencies
        $lawGraphSync = $this->createMock(LawGraphSyncService::class);
        $openai = $this->createMock(OpenAIService::class);
        $traceService = $this->createMock(ReasoningTraceService::class);

        $this->service = new ReasoningChainService($lawGraphSync, $openai, $traceService);
    }

    /**
     * Extract system prompt from convertNLToCypher method using reflection
     */
    private function getSystemPrompt(): string
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('convertNLToCypher');

        // Get method source code
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        $source = file($filename);
        $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));

        // Extract HEREDOC content between <<<'PROMPT' and PROMPT;
        if (preg_match("/<<<'PROMPT'\n(.*?)\nPROMPT;/s", $methodSource, $matches)) {
            return $matches[1];
        }

        throw new \RuntimeException('Could not extract system prompt from convertNLToCypher method');
    }

    /**
     * Test that system prompt includes all 15 node types
     *
     * The Neo4j graph schema includes 15 node types. This test verifies
     * that the system prompt documents all of them so the LLM can generate
     * accurate Cypher queries.
     */
    public function test_it_includes_all_node_types_in_system_prompt(): void
    {
        $systemPrompt = $this->getSystemPrompt();

        $expectedNodeTypes = [
            'Decision',
            'LawDocument',
            'Jurisdiction',
            'Keyword',
            'Court',
            'Evidence',
            'LegalArgument',
            'DateEvent',
            'Party',
            'Judge',
            'Lawyer',
            'LegalConcept',
            'Article',
            'Verdict',
            'Topic',
        ];

        foreach ($expectedNodeTypes as $nodeType) {
            $this->assertStringContainsString(
                $nodeType,
                $systemPrompt,
                "System prompt should include node type: {$nodeType}"
            );
        }

        // Verify count in assertion message
        $this->assertCount(
            15,
            $expectedNodeTypes,
            'Should test for exactly 15 node types'
        );
    }

    /**
     * Test that system prompt includes all 14 relationship types
     *
     * The Neo4j graph schema includes 14 relationship types. This test verifies
     * that the system prompt documents all of them so the LLM can generate
     * accurate Cypher queries for graph traversals.
     */
    public function test_it_includes_all_relationship_types_in_system_prompt(): void
    {
        $systemPrompt = $this->getSystemPrompt();

        $expectedRelationshipTypes = [
            'CITES',
            'CONTRADICTS',
            'BELONGS_TO_JURISDICTION',
            'HAS_KEYWORD',
            'SUPERSEDES',
            'HAS_JUDGE',
            'HAS_PARTY',
            'CONTAINS_ARGUMENT',
            'CONSIDERS_EVIDENCE',
            'HAS_EVENT',
            'SIMILAR_TO',
            'HAS_PROSECUTOR',
            'DECIDED_BY',
            'REFERENCES',
        ];

        foreach ($expectedRelationshipTypes as $relType) {
            $this->assertStringContainsString(
                $relType,
                $systemPrompt,
                "System prompt should include relationship type: {$relType}"
            );
        }

        // Verify count in assertion message
        $this->assertCount(
            14,
            $expectedRelationshipTypes,
            'Should test for exactly 14 relationship types'
        );
    }
}
