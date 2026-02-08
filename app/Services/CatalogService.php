<?php

namespace App\Services;

use App\Models\VectorDocument;
use Illuminate\Support\Str;

/**
 * CatalogService - Core business logic for vector store catalog management.
 *
 * Encapsulates all catalog-related operations:
 * - Attribute compaction for VS metadata
 * - Catalog entry building from document metadata
 * - Catalog generation and upload to vector stores
 * - Import from legacy mapping.json format
 */
class CatalogService
{
    public function __construct(
        protected OpenAIService $openai
    ) {}

    /**
     * Import existing mapping.json into database (one-time migration).
     */
    public function importFromMapping(string $mappingPath, string $defaultVsId): int
    {
        $mapping = json_decode(file_get_contents($mappingPath), true);
        $count = 0;

        foreach ($mapping as $item) {
            $metaPath = $item['file_response_metadata'] ?? null;
            $meta = ($metaPath && is_file($metaPath))
                ? json_decode(file_get_contents($metaPath), true)
                : null;

            VectorDocument::updateOrCreate(
                ['file_name' => $item['file_name'], 'vector_store_id' => $defaultVsId],
                [
                    'file_path' => $item['file_path'] ?? null,
                    'openai_file_id' => $item['file_id'] ?? null,
                    'case_id' => $meta['case_id'] ?? null,
                    'status' => $item['file_id']
                        ? VectorDocument::STATUS_UPLOADED
                        : ($meta ? VectorDocument::STATUS_TAGGED : VectorDocument::STATUS_PENDING),
                    'metadata' => $meta,
                    'attributes' => $meta ? $this->compactAttributes($meta) : null,
                    'tagged_at' => $meta ? now() : null,
                    'uploaded_at' => $item['file_id'] ? now() : null,
                    'confidence' => $meta['confidence'] ?? null,
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Compact nested metadata into flat VS attributes.
     *
     * Extracted from BulkReattachAttributes::compactMetadataAttributes()
     * This is the single source of truth for attribute compaction.
     */
    public function compactAttributes(array $m): array
    {
        // 1) Keywords (handles diacritic and ascii versions)
        $keywordsArr = $m['ključne_riječi'] ?? $m['kljucne_rijeci'] ?? [];
        $keywordsArr = array_map('strval', $keywordsArr);

        // 2) Detect person (from keywords; looks for "Firstname Lastname")
        $person = '';
        foreach ($keywordsArr as $k) {
            if (preg_match('/\b\p{Lu}\p{Ll}+\s+\p{Lu}\p{Ll}+\b/u', $k)) {
                $person = $k;
                break;
            }
        }

        // 3) Detect police unit (from keywords)
        $policeUnit = '';
        foreach ($keywordsArr as $k) {
            if (stripos($k, 'PU ') !== false) {
                $policeUnit = $k;
                break;
            }
        }

        // 4) Order terms (from keywords)
        $orderTermsWanted = ['rok 3 dana', 'pravo na branitelja', 'žalba nije dopuštena'];
        $orderTerms = array_values(array_intersect($orderTermsWanted, $keywordsArr));

        // 5) Law codes + citations summary
        $law = $m['law'] ?? [];
        $codeShort = [];
        foreach ($law as $item) {
            $aliases = $item['law_code_alias'] ?? [];
            foreach ($aliases as $alias) {
                if (in_array($alias, ['PZ', 'ZKP', 'ZSZD'], true)) {
                    $codeShort[$item['law_code']] = $alias;
                }
            }
        }

        // Build code list and citations per code
        $codesSeen = [];
        $byCode = [];
        foreach ($law as $item) {
            $code = $item['law_code'] ?? '';
            if (! $code) {
                continue;
            }
            $codesSeen[$code] = true;

            foreach (($item['citations'] ?? []) as $c) {
                $part = $c['clanak'] ?? '';
                $part = 'čl. '.$part;
                if (! empty($c['stavci'])) {
                    $part .= ' st.'.implode(',', $c['stavci']);
                }
                if (! empty($c['tocke'])) {
                    $part .= ' t.'.implode(',', $c['tocke']);
                }
                if ($part !== '') {
                    $byCode[$code][] = $part;
                }
            }
        }

        // law_codes (short + full where available)
        $lawCodesParts = [];
        foreach (array_keys($codesSeen) as $full) {
            $short = $codeShort[$full] ?? null;
            $lawCodesParts[] = $short ? "{$short} ({$full})" : $full;
        }
        $lawCodesStr = implode('; ', $lawCodesParts);

        // law_articles (citations grouped by short code if known)
        $lawArticlesParts = [];
        foreach ($byCode as $full => $citations) {
            $label = $codeShort[$full] ?? $full;
            $lawArticlesParts[] = $label.': '.implode('; ', $citations);
        }
        $lawArticlesStr = implode('; ', $lawArticlesParts);

        // 6) Related cases
        $relatedCasesStr = implode('; ', array_map('strval', $m['related_cases'] ?? []));

        // 7) Build compact attributes (ASCII keys; values keep original diacritics)
        $attributes = [
            'file_name' => (string) ($m['file_name'] ?? ''),
            'jurisdikcija' => (string) ($m['jurisdikcija'] ?? ''),
            'case_id' => (string) ($m['case_id'] ?? ''),
            'related_cases' => $relatedCasesStr,
            'vrsta' => (string) ($m['vrsta'] ?? ''),
            'datum' => (string) ($m['datum'] ?? ''),
            'lokacija' => (string) ($m['lokacija'] ?? ''),
            'law_codes' => $lawCodesStr,
            'law_articles' => $lawArticlesStr,
            'keywords' => Str::limit(implode(', ', $keywordsArr), 500),
            'order_terms' => implode('; ', $orderTerms),
            'person' => $person,
            'police_unit' => $policeUnit,
            'izvor' => (string) ($m['izvor'] ?? ''),
            'confidence' => (string) ($m['confidence'] ?? ''),
        ];

        // Remove empty values to ensure we stay under the limit and keep it clean
        return array_filter($attributes, fn ($v) => $v !== '' && $v !== null);
    }

    /**
     * Build a lean catalog entry for a single document.
     *
     * Extracted from BuildAndUploadCatalog logic but simplified:
     * - No giant text field with duplicated data
     * - No full law objects (replaced with law_pins)
     * - No anchors with full quotes (they're in VS anyway)
     */
    public function buildCatalogEntry(VectorDocument $doc): array
    {
        $meta = $doc->metadata;
        if (! $meta) {
            return [];
        }

        // Build law_pins (compact law citation references)
        $law = $meta['law'] ?? [];
        $pins = [];
        foreach ($law as $lawSingle) {
            $lawName = $lawSingle['law_code'] ?? '';
            $alias = isset($lawSingle['law_code_alias']) ? reset($lawSingle['law_code_alias']) : '';

            foreach ($lawSingle['citations'] ?? [] as $c) {
                if (! empty($c['clanak'])) {
                    $st = isset($c['stavci']) ? implode(',', $c['stavci']) : null;
                    $t = isset($c['tocke']) ? implode(',', $c['tocke']) : null;
                    $pins[] = ($alias ?: $lawName).' čl.'.$c['clanak']
                        .($st ? ' st.'.$st : '')
                        .($t ? ' t.'.$t : '');
                }
            }
        }

        return [
            'type' => 'catalog_entry',
            'file_name' => $doc->file_name,
            'file_id' => $doc->openai_file_id,
            'case_id' => $doc->case_id,
            'related_cases' => $meta['related_cases'] ?? [],
            'vrsta' => $meta['vrsta'] ?? null,
            'artifact' => $meta['artifact'] ?? null,
            'vs_id' => $doc->vector_store_id,
            'law_pins' => $pins,
            'ključne_riječi' => $meta['ključne_riječi'] ?? [],
            'kategorije_povrede' => $meta['kategorije_povrede'] ?? [],
            'datum' => $meta['datum'] ?? null,
            'lokacija' => $meta['lokacija'] ?? null,
            'confidence' => $doc->confidence,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate full catalog array for a vector store.
     *
     * Updates each document's catalog_entry and status.
     */
    public function generateCatalog(string $vsId): array
    {
        $docs = VectorDocument::forStore($vsId)
            ->whereIn('status', [
                VectorDocument::STATUS_UPLOADED,
                VectorDocument::STATUS_CATALOGED,
            ])
            ->get();

        $entries = [];
        foreach ($docs as $doc) {
            $entry = $this->buildCatalogEntry($doc);
            if (! empty($entry)) {
                $doc->update([
                    'catalog_entry' => $entry,
                    'status' => VectorDocument::STATUS_CATALOGED,
                    'cataloged_at' => now(),
                ]);
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Build lean catalog JSON structure with metadata.
     */
    public function buildCatalogJson(string $vsId, array $entries): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'vector_store_id' => $vsId,
            'document_count' => count($entries),
            'entries' => $entries,
        ];
    }

    /**
     * Build, save, and optionally upload catalog to vector store.
     */
    public function buildAndUploadCatalog(string $vsId, ?string $outDir = null, bool $upload = true): array
    {
        $outDir ??= config('vector-stores.catalog_dir', storage_path('app/catalog'));
        $entries = $this->generateCatalog($vsId);

        if (empty($entries)) {
            return ['entries' => 0, 'file_id' => null, 'path' => null];
        }

        // Build lean catalog JSON
        $catalog = $this->buildCatalogJson($vsId, $entries);

        // Save to file
        $outPath = rtrim($outDir, '/')."/{$vsId}/catalog.json";
        @mkdir(dirname($outPath), 0775, true);
        file_put_contents($outPath, json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $result = [
            'entries' => count($entries),
            'file_id' => null,
            'path' => $outPath,
        ];

        // Upload to VS if requested
        if ($upload) {
            $uploaded = $this->openai->fileUpload($outPath, 'assistants');
            $fileId = $uploaded['id'] ?? null;

            if ($fileId) {
                $this->openai->vectorStoreAddFile($vsId, $fileId);
                $this->openai->vectorStoreFileMetadataUpdate($vsId, $fileId, [
                    'attributes' => [
                        'type' => 'assistants',
                        'document_type' => 'catalog',
                    ],
                ]);
                $result['file_id'] = $fileId;
            }
        }

        return $result;
    }

    /**
     * Upload a document file to OpenAI and attach to vector store.
     */
    public function uploadAndAttach(VectorDocument $doc): VectorDocument
    {
        if (! $doc->file_path || ! is_file($doc->file_path)) {
            throw new \InvalidArgumentException("File not found: {$doc->file_path}");
        }

        if (! $doc->vector_store_id) {
            throw new \InvalidArgumentException('Document has no vector_store_id');
        }

        // Upload file to OpenAI
        $uploaded = $this->openai->fileUpload($doc->file_path, 'assistants');
        $fileId = $uploaded['id'] ?? null;

        if (! $fileId) {
            throw new \RuntimeException('Failed to upload file to OpenAI');
        }

        // Compact attributes
        $attributes = $doc->metadata ? $this->compactAttributes($doc->metadata) : [];

        // Attach to vector store with attributes
        $this->openai->vectorStoreAddFile($doc->vector_store_id, $fileId);

        if (! empty($attributes)) {
            $this->openai->vectorStoreFileMetadataUpdate($doc->vector_store_id, $fileId, [
                'attributes' => $attributes,
            ]);
        }

        // Update document record
        $doc->markAsUploaded($fileId, $attributes);

        return $doc;
    }

    /**
     * Get document status counts for a vector store.
     */
    public function getStatusCounts(string $vsId): array
    {
        return VectorDocument::forStore($vsId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Get default vector store ID from config.
     */
    public function getDefaultVectorStoreId(): string
    {
        return config('vector-stores.default_store', 'vs_68c89c6bb90081918cf07e4441f58ecc');
    }

    /**
     * Get vector store ID by name from config.
     */
    public function getVectorStoreId(string $name): ?string
    {
        return config("vector-stores.stores.{$name}.id");
    }
}
