<?php

namespace App\Jobs;

use App\Services\OpenAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Regenerate LLM-reasoned attributes for a file in an OpenAI vector store.
 *
 * Uses Chat Completions API with json_schema response format to extract
 * 10 flat string attributes suitable for vector store file metadata.
 *
 * Can be dispatched synchronously for immediate UI feedback:
 *   RegenerateFileAttributes::dispatchSync($storeId, $fileId);
 */
class RegenerateFileAttributes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public string $vectorStoreId,
        public string $fileId,
        public string $model = 'gpt-4o',
    ) {}

    public function handle(OpenAIService $service): array
    {
        Log::info('RegenerateFileAttributes: Starting', [
            'store' => $this->vectorStoreId,
            'file' => $this->fileId,
        ]);

        // Step 1: Retrieve file content reference for context
        $fileInfo = $service->vectorStoreGetFile($this->vectorStoreId, $this->fileId);

        // Step 2: Extract attributes via Chat Completions with structured output
        $rawAttributes = $this->extractAttributes($service);

        // Step 3: Flatten and truncate attributes
        $attributes = $this->flattenAttributes($rawAttributes);

        // Step 4: Save attributes back to vector store
        $service->vectorStoreFileMetadataUpdate(
            $this->vectorStoreId,
            $this->fileId,
            ['attributes' => $attributes]
        );

        Log::info('RegenerateFileAttributes: Completed', [
            'store' => $this->vectorStoreId,
            'file' => $this->fileId,
            'attributes_count' => count($attributes),
        ]);

        return $attributes;
    }

    /**
     * Extract attributes using Chat Completions API with json_schema response format.
     */
    protected function extractAttributes(OpenAIService $service): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => 'Uloga: "Tagger". Iz teksta HR pravnog dokumenta izvuci točne metapodatke. '
                    .'Ne nagađaj; ako nema podatka, vrati prazan string "". '
                    .'Normaliziraj pojmove i citate. Odgovaraj isključivo na hrvatskom jeziku. '
                    .'Izlaz je strogo JSON prema zadanoj shemi.',
            ],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'file',
                        'file' => ['file_id' => $this->fileId],
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Izvuci metapodatke iz priloženog dokumenta prema zadanoj JSON shemi. '
                            .'Ako nema podatka za polje, vrati prazan string "".',
                    ],
                ],
            ],
        ];

        $response = $service->chat($messages, $this->model, [
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'file_attributes',
                    'strict' => true,
                    'schema' => $this->attributeSchema(),
                ],
            ],
            'temperature' => 0.1,
        ]);

        $content = $response['choices'][0]['message']['content'] ?? null;

        if (! $content) {
            throw new \RuntimeException('Empty response from Chat Completions API for file attribute extraction');
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in Chat Completions response: '.json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Simplified flat JSON schema with 10 string fields for vector store attributes.
     */
    protected function attributeSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'file_name' => [
                    'type' => 'string',
                    'description' => 'Naziv datoteke ili dokumenta',
                ],
                'document_type' => [
                    'type' => 'string',
                    'description' => 'Vrsta dokumenta (npr. optužnica, naredba za pretragu, zapisnik, presuda, zakon)',
                ],
                'case_id' => [
                    'type' => 'string',
                    'description' => 'Identifikator predmeta (npr. KO-DO-58/2026, Pp-2343/2025)',
                ],
                'related_cases' => [
                    'type' => 'string',
                    'description' => 'Povezani predmeti odvojeni točka-zarezom (npr. Pp-100/2025; K-200/2024)',
                ],
                'date' => [
                    'type' => 'string',
                    'description' => 'Datum dokumenta u formatu YYYY-MM-DD',
                ],
                'court_or_authority' => [
                    'type' => 'string',
                    'description' => 'Sud ili tijelo koje je izdalo dokument',
                ],
                'parties' => [
                    'type' => 'string',
                    'description' => 'Stranke u postupku, odvojene zarezima',
                ],
                'laws_cited' => [
                    'type' => 'string',
                    'description' => 'Citirani zakoni i članci (npr. ZKP čl.38 st.2; KZ čl.190)',
                ],
                'keywords' => [
                    'type' => 'string',
                    'description' => 'Ključne riječi odvojene zarezima',
                ],
                'location' => [
                    'type' => 'string',
                    'description' => 'Lokacija radnje ili sjedište suda',
                ],
                'artifacts' => [
                    'type' => 'string',
                    'description' => 'Predmeti/artefakti navedeni u dokumentu (npr. mobitel Samsung, automatska puška M70)',
                ],
                'jurisdiction' => [
                    'type' => 'string',
                    'description' => 'Jurisdikcija (npr. HR, EU, ECHR)',
                ],
                'source_type' => [
                    'type' => 'string',
                    'description' => 'Izvor dokumenta: službeni, interni ili neslužbeni',
                ],
                'violation_categories' => [
                    'type' => 'string',
                    'description' => 'Kategorije povrede odvojene točka-zarezom (npr. Formalni elementi; Osnovanost; Zakonitost postupanja)',
                ],
                'summary' => [
                    'type' => 'string',
                    'description' => 'Kratki sažetak dokumenta u jednoj rečenici',
                ],
            ],
            'required' => [
                'file_name',
                'document_type',
                'case_id',
                'related_cases',
                'date',
                'court_or_authority',
                'parties',
                'laws_cited',
                'keywords',
                'location',
                'artifacts',
                'jurisdiction',
                'source_type',
                'violation_categories',
                'summary',
            ],
        ];
    }

    /**
     * Flatten attributes: truncate long values to 500 chars, remove empty strings.
     */
    public function flattenAttributes(array $raw): array
    {
        $attributes = [];

        foreach ($raw as $key => $value) {
            $value = (string) $value;

            // Truncate to 500 chars
            if (mb_strlen($value) > 500) {
                $value = mb_substr($value, 0, 500).'...';
            }

            // Only include non-empty values
            if ($value !== '') {
                $attributes[$key] = $value;
            }
        }

        return $attributes;
    }

    public function backoff(): array
    {
        return [5, 15];
    }

    public function failed(\Throwable $e): void
    {
        Log::error('RegenerateFileAttributes: Failed', [
            'store' => $this->vectorStoreId,
            'file' => $this->fileId,
            'error' => $e->getMessage(),
        ]);
    }
}
