<?php

namespace App\Services;

use App\Services\CircuitBreaker;
use Illuminate\Support\Facades\Log;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;
use Psr\Log\LoggerInterface;

class Neo4jService
{
    protected $client;

    protected LoggerInterface $logger;

    protected CircuitBreaker $circuitBreaker;

    public function __construct(?CircuitBreaker $circuitBreaker = null)
    {
        $this->logger = Log::channel('stack');

        $defaults = config('circuit_breaker.defaults.neo4j', []);
        $this->circuitBreaker = $circuitBreaker ?? new CircuitBreaker(
            'neo4j',
            $defaults['failure_threshold'] ?? 5,
            $defaults['success_threshold'] ?? 2,
            $defaults['timeout'] ?? 30,
            $defaults['retry_after'] ?? 15
        );

        $enabled = (bool) config('neo4j.sync.enabled', true);
        if (! $enabled) {
            $this->client = null;

            return;
        }

        $uri = (string) config('neo4j.uri', 'bolt://localhost:7687');
        $user = (string) config('neo4j.user', 'neo4j');
        $password = (string) config('neo4j.password');

        try {
            $this->client = ClientBuilder::create()
                ->withDriver('bolt', $uri, Authenticate::basic($user, $password))
                ->withDefaultDriver('bolt')
                ->build();
        } catch (\Exception $e) {
            $this->logger->error('Neo4j client initialization failed', [
                'error' => $e->getMessage(),
            ]);
            $this->client = null;
        }
    }

    public function upsertCaseAndDocument(string $caseId, string $caseTitle, string $docId, string $docTitle): void
    {
        if (! $this->client) {
            $this->logger->info('Neo4j disabled; skipping upsert', compact('caseId', 'docId'));

            return;
        }

        $cypher = 'MERGE (c:Case {id: $case_id}) SET c.title = $case_title '.
                  'MERGE (d:CaseDocument {id: $doc_id}) SET d.title = $doc_title '.
                  'MERGE (c)-[:HAS_DOCUMENT]->(d)';

        $params = [
            'case_id' => $caseId,
            'case_title' => $caseTitle,
            'doc_id' => $docId,
            'doc_title' => $docTitle,
        ];

        try {
            $this->circuitBreaker->call(function () use ($cypher, $params) {
                return $this->client->run($cypher, $params);
            });
        } catch (\Exception $e) {
            $this->logger->error('Neo4j upsert failed', [
                'caseId' => $caseId,
                'docId' => $docId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
