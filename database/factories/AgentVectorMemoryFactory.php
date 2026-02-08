<?php

namespace Database\Factories;

use App\Models\AgentVectorMemory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AgentVectorMemoryFactory extends Factory
{
    protected $model = AgentVectorMemory::class;

    public function definition(): array
    {
        return [
            'id' => Str::ulid(),
            'agent_name' => fake()->randomElement([
                'research-agent',
                'analysis-agent',
                'synthesis-agent',
                'review-agent',
            ]),
            'namespace' => fake()->randomElement([
                'legal-research',
                'contract-analysis',
                'case-law',
                'precedent-search',
            ]),
            'objective' => fake()->optional()->sentence(),
            'content' => fake()->paragraphs(3, true),
            'metadata' => [
                'source' => fake()->randomElement(['case-law', 'legislation', 'user-input']),
                'category' => fake()->randomElement(['precedent', 'fact', 'rule']),
                'confidence' => fake()->randomFloat(2, 0.7, 0.99),
            ],
            'source' => fake()->randomElement(['case-document', 'law-database', 'court-decision']),
            'source_id' => Str::random(10),
            'chunk_index' => 0,
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'embedding_norm' => fake()->randomFloat(4, 0.9, 1.0),
            'content_hash' => hash('sha256', fake()->text()),
            'token_count' => fake()->numberBetween(50, 1000),
            'embedding_vector' => array_fill(0, 1536, fake()->randomFloat(4, -1, 1)), // Use embedding_vector instead of embedding (pgvector not installed)
            'access_count' => 0, // Sprint 5.7: Track memory access
        ];
    }

    /**
     * Indicate memory for a specific agent
     */
    public function forAgent(string $agentName): static
    {
        return $this->state(fn (array $attributes) => [
            'agent_name' => $agentName,
        ]);
    }

    /**
     * Indicate memory for research agent
     */
    public function research(): static
    {
        return $this->state(fn (array $attributes) => [
            'agent_name' => 'research-agent',
            'namespace' => 'legal-research',
            'content' => 'Research finding: '.fake()->sentence(),
        ]);
    }

    /**
     * Indicate memory for analysis agent
     */
    public function analysis(): static
    {
        return $this->state(fn (array $attributes) => [
            'agent_name' => 'analysis-agent',
            'namespace' => 'contract-analysis',
            'content' => 'Analysis result: '.fake()->sentence(),
        ]);
    }

    /**
     * Indicate memory in a specific namespace
     */
    public function inNamespace(string $namespace): static
    {
        return $this->state(fn (array $attributes) => [
            'namespace' => $namespace,
        ]);
    }

    /**
     * Indicate memory with embeddings
     */
    public function withEmbedding(): static
    {
        return $this->state(fn (array $attributes) => [
            'embedding_vector' => array_fill(0, 1536, fake()->randomFloat(6, -1, 1)),
        ]);
    }

    /**
     * Indicate memory from case documents
     */
    public function fromCaseDocument(?string $documentId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'case-document',
            'source_id' => $documentId ?? Str::ulid()->toString(),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'document_type' => 'case-document',
            ]),
        ]);
    }

    /**
     * Indicate memory from law database
     */
    public function fromLawDatabase(?string $lawId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'law-database',
            'source_id' => $lawId ?? Str::ulid()->toString(),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'source_type' => 'legislation',
            ]),
        ]);
    }

    /**
     * Indicate memory from court decision
     */
    public function fromCourtDecision(?string $decisionId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'court-decision',
            'source_id' => $decisionId ?? Str::random(10),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'source_type' => 'court-decision',
                'jurisdiction' => 'HR',
            ]),
        ]);
    }

    /**
     * Indicate a chunked memory (part of larger content)
     */
    public function chunk(int $index = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'chunk_index' => $index,
        ]);
    }

    /**
     * Indicate memory without embeddings
     */
    public function withoutEmbeddings(): static
    {
        return $this->state(fn (array $attributes) => [
            'embedding_vector' => null,
            'embedding_provider' => null,
            'embedding_model' => null,
            'embedding_dimensions' => null,
            'embedding_norm' => null,
        ]);
    }

    /**
     * Indicate memory with OpenAI embeddings
     */
    public function openai(): static
    {
        return $this->state(fn (array $attributes) => [
            'embedding_provider' => 'openai',
            'embedding_model' => fake()->randomElement([
                'text-embedding-3-small',
                'text-embedding-3-large',
            ]),
            'embedding_dimensions' => fake()->randomElement([1536, 3072]),
        ]);
    }

    /**
     * Indicate memory with Cohere embeddings
     */
    public function cohere(): static
    {
        return $this->state(fn (array $attributes) => [
            'embedding_provider' => 'cohere',
            'embedding_model' => 'embed-english-v3.0',
            'embedding_dimensions' => 1024,
            'embedding_vector' => array_fill(0, 1024, fake()->randomFloat(4, -1, 1)),
        ]);
    }

    /**
     * Indicate memory with complex metadata
     */
    public function withComplexMetadata(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => [
                'extraction' => [
                    'method' => 'automated',
                    'confidence' => fake()->randomFloat(2, 0.8, 0.99),
                    'timestamp' => now()->toIso8601String(),
                ],
                'context' => [
                    'case_id' => Str::ulid()->toString(),
                    'document_type' => 'contract',
                    'relevance_score' => fake()->randomFloat(2, 0.7, 0.95),
                ],
                'tags' => ['important', 'precedent', fake()->randomElement(['contract-law', 'labor-law'])],
                'relationships' => [
                    'related_cases' => [Str::random(8), Str::random(8)],
                    'cited_laws' => ['NN 123/2020', 'NN 45/2021'],
                ],
            ],
        ]);
    }

    /**
     * Indicate high-relevance memory
     */
    public function highRelevance(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'relevance_score' => fake()->randomFloat(2, 0.9, 0.99),
                'importance' => 'high',
            ]),
        ]);
    }

    /**
     * Indicate long-form content
     */
    public function longForm(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => fake()->paragraphs(20, true),
            'token_count' => fake()->numberBetween(2000, 10000),
        ]);
    }

    /**
     * Indicate Croatian legal content
     */
    public function croatian(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => 'Članak 1. Predmet ugovora\n\n'.
                'Ovim ugovorom ugovorne strane se obvezuju na ispunjenje sljedećih obveza...',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'language' => 'hr',
                'jurisdiction' => 'HR',
            ]),
        ]);
    }

    /**
     * Indicate precedent memory
     */
    public function precedent(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'category' => 'precedent',
                'case_type' => fake()->randomElement(['contract', 'tort', 'labor']),
                'binding' => fake()->boolean(),
            ]),
        ]);
    }

    /**
     * Indicate factual memory
     */
    public function fact(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'category' => 'fact',
                'verified' => fake()->boolean(80),
            ]),
        ]);
    }

    /**
     * Indicate rule/law memory
     */
    public function rule(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'category' => 'rule',
                'law_reference' => 'NN '.fake()->numberBetween(1, 200).'/'.fake()->year(),
            ]),
        ]);
    }
}
