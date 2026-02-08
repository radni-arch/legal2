<?php

namespace Database\Factories;

use App\Models\CaseDocument;
use App\Models\LegalCase;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CaseDocumentFactory extends Factory
{
    protected $model = CaseDocument::class;

    public function definition(): array
    {
        return [
            'id' => Str::ulid(),
            'case_id' => LegalCase::factory(),
            'doc_id' => 'doc-'.Str::uuid(),
            'upload_id' => null,
            'title' => fake()->randomElement([
                'Witness Statement',
                'Evidence Document',
                'Expert Opinion',
                'Court Transcript',
                'Legal Brief',
            ]),
            'category' => fake()->randomElement(['evidence', 'witness', 'expert', 'pleading', 'motion']),
            'author' => fake()->name(),
            'language' => 'hr',
            'tags' => [fake()->word(), fake()->word()],
            'chunk_index' => 0,
            'content' => fake()->paragraphs(5, true),
            'metadata' => [
                'page_count' => fake()->numberBetween(1, 50),
                'file_type' => fake()->randomElement(['pdf', 'docx', 'txt']),
            ],
            'actual' => null,
            'source' => 'upload',
            'source_id' => null,
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'embedding_norm' => fake()->randomFloat(4, 0.9, 1.0),
            'content_hash' => hash('sha256', fake()->text()),
            'token_count' => fake()->numberBetween(100, 2000),
            // Use 'embedding_vector' for cases_documents table
            'embedding_vector' => array_fill(0, 1536, 0),
        ];
    }

    /**
     * Indicate a contract document
     */
    public function contract(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'contract',
            'title' => 'Contract - '.fake()->words(3, true),
            'tags' => ['contract', 'legal', 'agreement'],
        ]);
    }

    /**
     * Indicate an evidence document
     */
    public function evidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'evidence',
            'title' => 'Evidence - '.fake()->words(3, true),
            'tags' => ['evidence', 'exhibit'],
        ]);
    }

    /**
     * Indicate a correspondence document
     */
    public function correspondence(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'correspondence',
            'title' => 'Email - '.fake()->words(3, true),
            'tags' => ['correspondence', 'email'],
            'source' => 'email',
        ]);
    }

    /**
     * Indicate a court order document
     */
    public function courtOrder(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'court-order',
            'title' => 'Court Order - '.fake()->words(3, true),
            'tags' => ['court-order', 'official'],
            'author' => fake()->randomElement([
                'Judge '.fake()->lastName(),
                'Court Clerk',
            ]),
        ]);
    }

    /**
     * Indicate a Croatian language document
     */
    public function croatian(): static
    {
        return $this->state(fn (array $attributes) => [
            'language' => 'hr',
            'title' => fake()->randomElement([
                'Ugovor o radu',
                'Ugovor o najmu',
                'Izvještaj o dokazima',
                'Presuda suda',
                'Tužba',
            ]),
            'content' => 'Članak 1. Predmet ugovora\n\nOvim ugovorom ugovorne strane se obvezuju...',
            'author' => 'Marko Marković',
        ]);
    }

    /**
     * Indicate an English language document
     */
    public function english(): static
    {
        return $this->state(fn (array $attributes) => [
            'language' => 'en',
        ]);
    }

    /**
     * Indicate a document for a specific case
     */
    public function forCase(LegalCase|string $case): static
    {
        $caseId = $case instanceof LegalCase ? $case->id : $case;

        return $this->state(fn (array $attributes) => [
            'case_id' => $caseId,
        ]);
    }

    /**
     * Indicate a chunked document (part of a larger document)
     */
    public function chunk(int $index = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'chunk_index' => $index,
            'title' => ($attributes['title'] ?? 'Document').' - Part '.($index + 1),
        ]);
    }

    /**
     * Indicate a document without embeddings
     */
    public function withoutEmbeddings(): static
    {
        return $this->state(fn (array $attributes) => [
            'embedding_vector' => array_fill(0, 1536, 0.0),
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'embedding_norm' => 0.0,
        ]);
    }

    /**
     * Indicate a confidential document
     */
    public function confidential(): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => array_merge($attributes['tags'] ?? [], ['confidential', 'restricted']),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'classification' => 'confidential',
                'access_level' => 'restricted',
            ]),
        ]);
    }

    /**
     * Indicate a document with complex metadata
     */
    public function withComplexMetadata(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => [
                'file_info' => [
                    'name' => 'document.pdf',
                    'size' => fake()->numberBetween(100000, 5000000),
                    'type' => 'application/pdf',
                ],
                'analysis' => [
                    'sentiment' => 'neutral',
                    'entities' => [fake()->company(), fake()->company()],
                    'key_terms' => ['contract', 'liability', 'indemnification'],
                ],
                'processing' => [
                    'ocr_quality' => fake()->randomFloat(2, 0.8, 1.0),
                    'extracted_at' => now()->toIso8601String(),
                    'processed_by' => 'textract',
                ],
            ],
        ]);
    }

    /**
     * Indicate a document with actual data
     */
    public function withActualData(): static
    {
        return $this->state(fn (array $attributes) => [
            'actual' => [
                'signed' => fake()->boolean(),
                'date_signed' => fake()->date(),
                'parties' => [fake()->name(), fake()->name()],
                'notarized' => fake()->boolean(),
            ],
        ]);
    }

    /**
     * Indicate a large document
     */
    public function large(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => fake()->paragraphs(50, true),
            'token_count' => fake()->numberBetween(5000, 20000),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'pages' => fake()->numberBetween(50, 200),
                'file_size' => fake()->numberBetween(5000000, 20000000),
            ]),
        ]);
    }

    /**
     * Indicate a document from a specific source
     */
    public function fromSource(string $source, ?string $sourceId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => $source,
            'source_id' => $sourceId ?? Str::random(10),
        ]);
    }

    /**
     * Indicate a document from email
     */
    public function fromEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'email',
            'source_id' => 'email-'.Str::random(10),
            'category' => 'correspondence',
        ]);
    }

    /**
     * Indicate a document from upload
     */
    public function fromUpload(?string $uploadId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'upload',
            'upload_id' => $uploadId ?? Str::ulid()->toString(),
            'title' => 'Evidence Document #'.fake()->numberBetween(1, 100),
        ]);
    }

    public function witness(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'witness',
            'title' => 'Witness Statement - '.fake()->name(),
        ]);
    }

    public function withEmbedding(): static
    {
        return $this->state(fn (array $attributes) => [
            'embedding_vector' => array_fill(0, 1536, fake()->randomFloat(6, -1, 1)),
        ]);
    }
}
