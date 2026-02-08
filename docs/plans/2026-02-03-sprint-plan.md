# Catalog Pipeline Refactoring — Sprint Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the fragile flat-file (`mapping.json`) catalog pipeline with a database-backed system, a clean service layer, and a unified Livewire UI that manages the full lifecycle: tag → store metadata → build catalog → upload to VS.

**Architecture:** Introduce an Eloquent model `VectorDocument` that tracks every file through a state machine (`pending → tagged → uploaded → cataloged`). A `CatalogService` encapsulates all business logic (tagging, attribute compaction, catalog generation, VS sync). Artisan commands become thin wrappers. The existing `OpenAIVectorManager` Livewire component gains catalog build/rebuild actions. The catalog itself becomes a generated artifact — a JSON file per VS that's auto-rebuilt when documents change.

**Tech Stack:** Laravel 11+, Livewire 3, PostgreSQL, OpenAI API (Files + Responses + Vector Stores)

---

## Analiza trenutnog stanja i problemi

### Što postoji:
1. **`ai:tag`** — tagira jedan file preko OpenAI Responses API, sprema `.metadata.json` + dodaje u `mapping.json`
2. **`vs:bulk-tagger`** — iterira foldere, poziva `ai:tag` za svaki file
3. **`vs:bulk-reattach`** — uploada fileove u OpenAI i attach-a u VS s atributima
4. **`vs:build-catalog`** — gradi `catalog.json` iz mappinga i uploada u VS
5. **`app:add-metadata-to-files`** — prepend-a JSON stranicu u PDF
6. **`app:add-correct-law-article-meta`** — generira metapodatke za članke zakona
7. **`OpenAIVectorManager`** — Livewire UI za browse/edit/delete fileova u VS
8. **`VectorStoreManager`** — Livewire UI za pgvector store management

### Ključni problemi:
- **`mapping.json` kao single source of truth** — flat file, nema transakcija, race condition-i, nema indeksa
- **Hardkodirani VS ID-evi** svugdje (`vs_68c89c6bb90081918cf07e4441f58ecc`)
- **Nema state tracking-a** — ne zna se koji file je tagiran, koji uploadan, koji katalogiziran
- **`dd()` pozivi** ostali u production kodu (`BuildAndUploadCatalog`, `BulkFileTagger`)
- **Duplicirani kod** — `compactMetadataAttributes()` u `BulkReattachAttributes` treba biti shared
- **Katalog se ne regenerira automatski** nakon promjena
- **Nema veze** između Livewire UI-a i catalog pipeline-a

### Prijedlog umjesto čistog kataloga:

Trenutni `catalog.json` je dobar koncept ali ima problema:
- Ogroman `text` field s duplikacijom podataka (keywords + law citations + anchors sve u jednom stringu)
- Anchori imaju full-quote sadržaj koji je redundantan jer VS ionako ima taj tekst
- Nema verzioniranja — ne znaš kad je katalog zadnji put buildiran

**Bolji pristup: Database-backed catalog s lean JSON export-om.**

Umjesto jednog masivnog JSON-a, katalog postaje:
1. **DB tablica** `vector_documents` — canonical source of truth
2. **Generated JSON** — lean verzija (bez `text` mega-stringa) koja se rebuilda on-demand
3. **VS attributes** na samim fileovima — za filtering u file_search
4. **Catalog file u VS-u** — summary dokument koji pomaže AI-u razumjeti što sve postoji

---

## Sprint: 8 taskova

---

### Task 1: Migracija — `vector_documents` tablica

**Files:**
- Create: `database/migrations/xxxx_create_vector_documents_table.php`
- Create: `app/Models/VectorDocument.php`

**Cilj:** Zamijeniti `mapping.json` s Eloquent modelom koji prati svaki dokument kroz pipeline.

**Step 1: Kreiraj migraciju**

```bash
php artisan make:migration create_vector_documents_table
```

```php
Schema::create('vector_documents', function (Blueprint $table) {
    $table->id();
    $table->string('file_name');
    $table->string('file_path')->nullable();
    $table->string('openai_file_id')->nullable()->index();
    $table->string('vector_store_id')->nullable()->index();
    $table->string('case_id')->nullable()->index();
    
    // State machine: pending → tagged → uploaded → cataloged
    $table->string('status')->default('pending')->index();
    
    // Metadata from AI tagger
    $table->jsonb('metadata')->nullable();        // Full tag_file_metadata output
    $table->jsonb('attributes')->nullable();       // Compacted attributes for VS
    $table->jsonb('catalog_entry')->nullable();    // Generated catalog entry
    
    // Tracking
    $table->timestamp('tagged_at')->nullable();
    $table->timestamp('uploaded_at')->nullable();
    $table->timestamp('cataloged_at')->nullable();
    $table->string('tagger_model')->nullable();
    $table->float('confidence')->nullable();
    
    $table->timestamps();
    $table->softDeletes();
    
    $table->unique(['file_name', 'vector_store_id']);
});
```

**Step 2: Kreiraj model**

```php
// app/Models/VectorDocument.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VectorDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'file_name', 'file_path', 'openai_file_id', 'vector_store_id',
        'case_id', 'status', 'metadata', 'attributes', 'catalog_entry',
        'tagged_at', 'uploaded_at', 'cataloged_at', 'tagger_model', 'confidence',
    ];

    protected $casts = [
        'metadata' => 'array',
        'attributes' => 'array',
        'catalog_entry' => 'array',
        'tagged_at' => 'datetime',
        'uploaded_at' => 'datetime',
        'cataloged_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_TAGGED = 'tagged';
    const STATUS_UPLOADED = 'uploaded';
    const STATUS_CATALOGED = 'cataloged';
    const STATUS_ERROR = 'error';

    // Scopes
    public function scopeForStore($query, string $vsId)
    {
        return $query->where('vector_store_id', $vsId);
    }

    public function scopeTagged($query)
    {
        return $query->where('status', self::STATUS_TAGGED);
    }

    public function scopeNeedsUpload($query)
    {
        return $query->whereIn('status', [self::STATUS_TAGGED])
                     ->whereNull('openai_file_id');
    }

    public function scopeNeedsCatalog($query)
    {
        return $query->where('status', self::STATUS_UPLOADED)
                     ->whereNull('cataloged_at');
    }
}
```

**Step 3: Pokreni migraciju**

```bash
php artisan migrate
```

**Step 4: Commit**

```bash
git add database/migrations/ app/Models/VectorDocument.php
git commit -m "feat: add vector_documents table and model"
```

---

### Task 2: CatalogService — Core business logic

**Files:**
- Create: `app/Services/CatalogService.php`

**Cilj:** Izvući svu business logiku iz Artisan komandi u servisni sloj. Ovo je srce refactoringa.

**Step 1: Kreiraj servis**

```php
// app/Services/CatalogService.php
namespace App\Services;

use App\Models\VectorDocument;
use Illuminate\Support\Str;

class CatalogService
{
    public function __construct(
        protected OpenAIClient $openai
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
     * Extracted from BulkReattachAttributes::compactMetadataAttributes()
     */
    public function compactAttributes(array $m): array
    {
        // ... (preseliti logiku iz BulkReattachAttributes::compactMetadataAttributes())
        // Identična logika, samo živjeti ovdje kao single source of truth
    }

    /**
     * Build a catalog entry for a single document.
     * Extracted from BuildAndUploadCatalog logic.
     */
    public function buildCatalogEntry(VectorDocument $doc): array
    {
        $meta = $doc->metadata;
        if (!$meta) return [];

        $law = $meta['law'] ?? [];
        $pins = [];
        foreach ($law as $lawSingle) {
            foreach ($lawSingle['citations'] ?? [] as $c) {
                $lawName = $lawSingle['law_code'] ?? '';
                $alias = isset($lawSingle['law_code_alias']) ? reset($lawSingle['law_code_alias']) : '';
                if (!empty($c['clanak'])) {
                    $st = isset($c['stavci']) ? implode(',', $c['stavci']) : null;
                    $t = isset($c['tocke']) ? implode(',', $c['tocke']) : null;
                    $pins[] = "{$lawName} ({$alias}) čl.{$c['clanak']}" 
                        . ($st ? " st.{$st}" : '') 
                        . ($t ? " t.{$t}" : '');
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
            'law_pins' => $pins,  // Lean: samo popis citata, ne cijeli law objekt
            'ključne_riječi' => $meta['ključne_riječi'] ?? [],
            'kategorije_povrede' => $meta['kategorije_povrede'] ?? [],
            'datum' => $meta['datum'] ?? null,
            'lokacija' => $meta['lokacija'] ?? null,
            'confidence' => $doc->confidence,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate full catalog JSON for a vector store.
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
            if (!empty($entry)) {
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
     * Build, save, and upload catalog to vector store.
     */
    public function buildAndUploadCatalog(string $vsId, ?string $outDir = null): array
    {
        $outDir ??= storage_path('app/catalog');
        $entries = $this->generateCatalog($vsId);

        if (empty($entries)) {
            return ['entries' => 0, 'file_id' => null];
        }

        $outPath = rtrim($outDir, '/') . "/{$vsId}/catalog.json";
        @mkdir(dirname($outPath), 0775, true);
        file_put_contents($outPath, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Upload to VS
        $uploaded = $this->openai->uploadFile($outPath);
        $this->openai->post("/vector_stores/{$vsId}/files", [
            'file_id' => $uploaded['id'],
            'attributes' => ['type' => 'assistants', 'document_type' => 'catalog'],
        ])->throw();

        return ['entries' => count($entries), 'file_id' => $uploaded['id'], 'path' => $outPath];
    }
}
```

**Step 2: Registriraj servis u `AppServiceProvider`** (ako treba — Laravel auto-resolve radi za constructor injection)

**Step 3: Commit**

```bash
git add app/Services/CatalogService.php
git commit -m "feat: add CatalogService with catalog generation and attribute compaction"
```

---

### Task 3: Migracija podataka iz mapping.json

**Files:**
- Create: `app/Console/Commands/ImportMappingToDatabase.php`

**Cilj:** One-time migracija postojećih podataka iz `mapping.json` u novu tablicu.

**Step 1: Kreiraj komandu**

```bash
php artisan make:command ImportMappingToDatabase
```

```php
protected $signature = 'catalog:import-mapping
    {--mapping= : Path to mapping.json (default: storage/app/tagged/mappping.json)}
    {--vs= : Default vector store ID}
    {--dry : Dry run}';

protected $description = 'One-time import of mapping.json into vector_documents table';

public function handle(CatalogService $catalog): int
{
    $path = $this->option('mapping') ?: storage_path('app/tagged/mappping.json');
    if (!is_file($path)) {
        $this->error("File not found: {$path}");
        return self::FAILURE;
    }

    $vsId = $this->option('vs') ?: $this->ask('Default Vector Store ID?');
    
    if ($this->option('dry')) {
        $mapping = json_decode(file_get_contents($path), true);
        $this->info("[DRY] Would import " . count($mapping) . " entries");
        return self::SUCCESS;
    }

    $count = $catalog->importFromMapping($path, $vsId);
    $this->info("Imported {$count} documents into vector_documents table.");

    return self::SUCCESS;
}
```

**Step 2: Pokreni import**

```bash
php artisan catalog:import-mapping --vs=vs_68c89c6bb90081918cf07e4441f58ecc
```

**Step 3: Commit**

```bash
git add app/Console/Commands/ImportMappingToDatabase.php
git commit -m "feat: add one-time mapping.json import command"
```

---

### Task 4: Refactor `ai:tag` da koristi DB umjesto mapping.json

**Files:**
- Modify: `app/Console/Commands/TagLegalDocMetadata.php`

**Cilj:** `ai:tag` nakon uspješnog tagiranja sprema u `vector_documents` umjesto u `mapping.json`.

**Ključne promjene:**

```php
// Umjesto:
$mappingArray[] = $res['mapping'];
file_put_contents($mappingFile, json_encode(...));

// Novo:
$doc = VectorDocument::updateOrCreate(
    ['file_name' => basename($filePath), 'vector_store_id' => $vsId],
    [
        'file_path' => $filePath,
        'openai_file_id' => $fileId,
        'status' => VectorDocument::STATUS_TAGGED,
        'metadata' => $arguments,
        'attributes' => app(CatalogService::class)->compactAttributes($arguments),
        'case_id' => $arguments['case_id'] ?? null,
        'tagged_at' => now(),
        'tagger_model' => $model,
        'confidence' => $arguments['confidence'] ?? null,
    ]
);
```

**Backward compatibility:** Zadrži pisanje u `mapping.json` kao fallback/log, ali DB postaje primary.

**Step: Commit**

```bash
git add app/Console/Commands/TagLegalDocMetadata.php
git commit -m "refactor: ai:tag writes to vector_documents table"
```

---

### Task 5: Refactor `vs:bulk-reattach` da koristi DB + CatalogService

**Files:**
- Modify: `app/Console/Commands/BulkReattachAttributes.php`

**Cilj:** Ukloniti duplirani `compactMetadataAttributes()` kod, koristiti `CatalogService`, čitati iz DB.

**Ključne promjene:**

```php
public function handle(CatalogService $catalog, OpenAIClient $client)
{
    $docs = VectorDocument::needsUpload()->get();

    $bar = $this->output->createProgressBar($docs->count());
    foreach ($docs as $doc) {
        $bar->advance();

        // Upload if no file_id
        if (!$doc->openai_file_id && $doc->file_path) {
            $response = $client->uploadFile($doc->file_path);
            $doc->openai_file_id = $response['id'];
        }

        // Compact attributes via service (single source of truth)
        $attributes = $catalog->compactAttributes($doc->metadata);

        // Attach to VS
        $client->post("/vector_stores/{$doc->vector_store_id}/files", [
            'file_id' => $doc->openai_file_id,
            'attributes' => $attributes,
        ])->throw();

        $doc->update([
            'status' => VectorDocument::STATUS_UPLOADED,
            'attributes' => $attributes,
            'uploaded_at' => now(),
        ]);
    }
    $bar->finish();
}
```

**Ukloniti:**
- Hardkodirane VS ID mapping array-e
- `compactMetadataAttributes()` metodu (sad živi u `CatalogService`)
- `$wanted` + `Cache::put('wanted', ...)` pattern

**Step: Commit**

```bash
git add app/Console/Commands/BulkReattachAttributes.php
git commit -m "refactor: bulk-reattach uses DB + CatalogService"
```

---

### Task 6: Nova `catalog:build` komanda (zamjena za `vs:build-catalog`)

**Files:**
- Create: `app/Console/Commands/BuildCatalog.php`
- Deprecate: `app/Console/Commands/BuildAndUploadCatalog.php`

**Cilj:** Čista, jednostavna komanda za build + upload kataloga iz DB-a.

```php
protected $signature = 'catalog:build
    {vs : Vector Store ID}
    {--upload : Upload catalog to VS after building}
    {--out= : Output directory (default: storage/app/catalog)}
    {--dry : Dry run}';

protected $description = 'Build catalog.json from database and optionally upload to VS';

public function handle(CatalogService $catalog): int
{
    $vsId = $this->argument('vs');
    $count = VectorDocument::forStore($vsId)
        ->whereIn('status', ['uploaded', 'cataloged'])
        ->count();

    $this->info("Building catalog for VS {$vsId} ({$count} documents)...");

    if ($this->option('dry')) {
        $entries = $catalog->generateCatalog($vsId);
        $this->line(json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }

    if ($this->option('upload')) {
        $result = $catalog->buildAndUploadCatalog($vsId, $this->option('out'));
        $this->info("Catalog built: {$result['entries']} entries, uploaded as {$result['file_id']}");
    } else {
        $entries = $catalog->generateCatalog($vsId);
        $outDir = $this->option('out') ?: storage_path('app/catalog');
        $outPath = "{$outDir}/{$vsId}/catalog.json";
        @mkdir(dirname($outPath), 0775, true);
        file_put_contents($outPath, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Catalog saved: {$outPath} ({count($entries)} entries)");
    }

    return self::SUCCESS;
}
```

**Step: Commit**

```bash
git add app/Console/Commands/BuildCatalog.php
git commit -m "feat: add clean catalog:build command backed by database"
```

---

### Task 7: Livewire UI — Catalog integracija u OpenAIVectorManager

**Files:**
- Modify: `app/Http/Livewire/OpenAIVectorManager.php`
- Modify: `resources/views/livewire/openai-vector-manager.blade.php`

**Cilj:** Dodati u postojeći UI:
1. Badge s brojem katalogiziranih dokumenata per store
2. "Build Catalog" button koji poziva `CatalogService::buildAndUploadCatalog()`
3. Status indikator (pending/tagged/uploaded/cataloged) za svaki file
4. Prikaz `catalog_entry` u metadata panelu

**Step 1: Dodaj metode u komponentu**

```php
// U OpenAIVectorManager.php dodati:

public int $catalogCount = 0;
public ?string $catalogStatus = null;

public function buildCatalog(): void
{
    if (!$this->selectedStore) return;

    try {
        $catalog = app(CatalogService::class);
        $result = $catalog->buildAndUploadCatalog($this->selectedStore);
        
        $this->catalogCount = $result['entries'];
        $this->catalogStatus = "Catalog built: {$result['entries']} entries";
        
        Cache::forget("openai_vector_files_{$this->selectedStore}");
        $this->fetchFiles();
    } catch (\Exception $e) {
        $this->error = 'Catalog build failed: ' . $e->getMessage();
    }
}

public function getDocumentStatus(string $fileId): ?string
{
    return VectorDocument::where('openai_file_id', $fileId)->value('status');
}
```

**Step 2: Dodaj UI elemente u blade**

U header sekciju files panela dodati "Build Catalog" button:
```blade
<button wire:click="buildCatalog"
    wire:confirm="Build and upload catalog for this vector store?"
    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded 
           bg-purple-500/15 hover:bg-purple-500/25 border border-purple-500/30 
           text-purple-300 transition-colors">
    📦 Build Catalog
</button>
```

U metadata panelu, dodati catalog_entry prikaz ispod attributes:
```blade
@if(!empty($metadata['catalog_entry'] ?? null))
<div class="mb-4">
    <h4 class="text-sm font-semibold mb-2 text-slate-400">Catalog Entry</h4>
    <pre class="p-3 rounded-lg text-xs max-h-48 overflow-auto vm-scroll 
                bg-slate-900/60 border border-slate-600 text-slate-300 font-mono">
        {{ json_encode($metadata['catalog_entry'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}
    </pre>
</div>
@endif
```

**Step 3: Commit**

```bash
git add app/Http/Livewire/OpenAIVectorManager.php resources/views/livewire/openai-vector-manager.blade.php
git commit -m "feat: add catalog build/view to OpenAI Vector Manager UI"
```

---

### Task 8: Config + Cleanup

**Files:**
- Create: `config/vector-stores.php`
- Modify: Multiple command files (remove `dd()`, hardcoded IDs)

**Cilj:** Centralizirati VS ID mapiranja, ukloniti dead code.

**Step 1: Kreiraj config**

```php
// config/vector-stores.php
return [
    'stores' => [
        'case_files' => [
            'id' => env('VS_CASE_FILES', 'vs_68c89c6bb90081918cf07e4441f58ecc'),
            'name' => 'Case Files (KP-DO-731, Pp Prz-74)',
        ],
        'laws' => [
            'id' => env('VS_LAWS', 'vs_68c89c812d408191add94d47a2b749e8'),
            'name' => 'Zakoni (članci)',
        ],
    ],
    'default_store' => env('VS_DEFAULT', 'vs_68c89c6bb90081918cf07e4441f58ecc'),
    'catalog_dir' => env('CATALOG_DIR', storage_path('app/catalog')),
];
```

**Step 2: Dodaj u `.env`**

```env
VS_CASE_FILES=vs_68c89c6bb90081918cf07e4441f58ecc
VS_LAWS=vs_68c89c812d408191add94d47a2b749e8
VS_DEFAULT=vs_68c89c6bb90081918cf07e4441f58ecc
```

**Step 3: Cleanup — ukloni iz svih komandi:**

- `dd('stop')` iz `BuildAndUploadCatalog` (linije 131, 183)
- `dd('stop')` iz `BulkFileTagger` (linija 42)
- Hardkodirane `$vsIdMapping` array-e (zamijeni s `config('vector-stores.stores')`)
- Zakomentirani blokovi koda u `BuildAndUploadCatalog`
- `$filesPath = '/reposss/Fileovi'` → konfigurabilan path

**Step 4: Commit**

```bash
git add config/vector-stores.php .env.example
git add app/Console/Commands/
git commit -m "chore: centralize VS config, remove dd() calls and dead code"
```

---

## Lean Catalog Format (preporuka)

Umjesto trenutnog formata s ogromnim `text` fieldom i dupliciranim podacima, predlažem lean format:

```json
{
  "generated_at": "2025-02-03T12:00:00+01:00",
  "vector_store_id": "vs_68c89c6bb90081918cf07e4441f58ecc",
  "document_count": 15,
  "entries": [
    {
      "file_id": "file-UHYMRxAg67E1YC4WJm6aP3",
      "file_name": "Naredba_o_pretresu.pdf",
      "case_id": "Pp Prz-74/2025",
      "related_cases": ["Pp Prz-74/2025-2", "NK-214-05/25-01/1155"],
      "vrsta": "naredba_za_pretragu",
      "datum": "2025-06-09",
      "lokacija": "Osijek",
      "law_pins": [
        "PZ čl.159 st.1 t.1",
        "ZKP čl.240",
        "ZKP čl.247-260",
        "ZSZD čl.54 st.3"
      ],
      "ključne_riječi": ["pretraga doma", "droga", "konoplja"],
      "kategorije_povrede": [],
      "confidence": 0.95
    }
  ]
}
```

**Što je uklonjeno vs. trenutni format:**
- `text` field (200+ znakova redundantnog teksta) — VS file_search ionako pretražuje originalni PDF
- `anchors` array (citati iz dokumenta) — ostaje u `metadata` kolumni u DB-u, ali ne ide u catalog
- `device` objekt — rijetko koristan u katalogu, ostaje u DB metadata
- `law` full objekti — zamijenjeni s `law_pins` (kratke reference)

**Što je dodano:**
- `generated_at` na root razini
- `document_count` za brzu provjeru
- Wrapper objekt umjesto golog array-a

---

## Redoslijed izvršavanja

```
Task 1 (migracija)           ← mora prvi, sve ovisi o tablici
  ↓
Task 2 (CatalogService)      ← core logika
  ↓
Task 3 (import mapping.json) ← one-time data migration
  ↓
Task 4 + 5 (refactor komandi) ← mogu paralelno
  ↓
Task 6 (nova catalog:build)  ← ovisi o Task 2
  ↓
Task 7 (Livewire UI)         ← ovisi o Task 2 + 6
  ↓
Task 8 (config + cleanup)    ← može zadnji, ali i paralelno s 7
```

**Procjena:** ~2-3 dana za jednog developera.

---

## Verifikacija

Nakon kompletnog sprinta, ovo mora raditi:

```bash
# 1. Import starih podataka
php artisan catalog:import-mapping --vs=vs_68c89c6bb90081918cf07e4441f58ecc

# 2. Tag novi file (sad piše u DB)
php artisan ai:tag /path/to/document.pdf --store

# 3. Upload u VS (čita iz DB)
php artisan vs:bulk-reattach

# 4. Build catalog (generira iz DB, uploada u VS)
php artisan catalog:build vs_68c89c6bb90081918cf07e4441f58ecc --upload

# 5. UI: otvoriti /vector-manager, odabrati store, kliknuti "Build Catalog"
```
