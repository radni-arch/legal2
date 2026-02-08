<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SudskaPraksaGenerateKeywords extends Command
{
    protected $signature = 'sudska-praksa:generate-keywords
        {--case-description= : Opis slucaja (tekst ili putanja do datoteke)}
        {--output=storage/app/keywords/generated.json : Putanja za spremanje}
        {--model=claude-sonnet-4-5-20250929 : AI model za generiranje}';

    protected $description = 'Generiraj keywords JSON datoteku pomocu AI-a prema METHODOLOGY.md';

    public function handle(): int
    {
        $description = $this->option('case-description');

        if (!$description) {
            $description = $this->ask('Opisi slucaj (cinjenicno stanje, pravni problemi):');
        }

        if (!$description) {
            $this->error('Opis slucaja je obavezan.');
            return self::FAILURE;
        }

        // If it's a file path, read it
        if (file_exists($description)) {
            $description = file_get_contents($description);
            $this->line("Ucitano iz datoteke: " . strlen($description) . " znakova");
        } elseif (file_exists(base_path($description))) {
            $description = file_get_contents(base_path($description));
            $this->line("Ucitano iz datoteke: " . strlen($description) . " znakova");
        }

        // Load methodology
        $methodologyPath = base_path('docs/sudska-praksa/METHODOLOGY.md');
        if (!file_exists($methodologyPath)) {
            $this->error("METHODOLOGY.md nije pronadena: {$methodologyPath}");
            return self::FAILURE;
        }

        $methodology = file_get_contents($methodologyPath);

        // Check for API key
        $apiKey = config('services.anthropic.key');
        if (!$apiKey) {
            $this->error('ANTHROPIC_API_KEY nije konfiguriran.');
            $this->line('Dodajte ANTHROPIC_API_KEY u .env datoteku.');
            return self::FAILURE;
        }

        $this->info("Generiram kljucne rijeci prema metodologiji...");
        $this->newLine();

        $systemPrompt = $this->buildSystemPrompt($methodology);

        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $this->option('model'),
                    'max_tokens' => 4096,
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => "Slucaj:\n\n{$description}"],
                    ],
                ]);

            if (!$response->successful()) {
                $this->error("AI API error: " . $response->status());
                $this->line($response->body());
                return self::FAILURE;
            }

            $content = $response->json('content.0.text');

            if (!$content) {
                $this->error('Prazan odgovor od AI-a.');
                return self::FAILURE;
            }

            // Extract JSON from response (may be wrapped in markdown code blocks)
            $json = $this->extractJson($content);

            if (!$json) {
                $this->error("Nije moguce izdvojiti JSON iz AI odgovora.");
                $this->line("Odgovor:");
                $this->line($content);
                return self::FAILURE;
            }

            $keywords = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("Neispravan JSON: " . json_last_error_msg());
                $this->line("Izdvojeni JSON:");
                $this->line($json);
                return self::FAILURE;
            }

            // Ensure metadata has required fields
            $keywords['metadata']['created_at'] = $keywords['metadata']['created_at'] ?? Carbon::now()->toIso8601String();
            $keywords['metadata']['methodology'] = 'METHODOLOGY.md';
            $keywords['metadata']['author'] = $keywords['metadata']['author'] ?? 'AI Legal War Machine (AI-generated)';
            $keywords['metadata']['model'] = $this->option('model');

            // Save to output file
            $outputPath = $this->option('output');
            $fullOutputPath = str_starts_with($outputPath, '/') ? $outputPath : base_path($outputPath);
            $dir = dirname($fullOutputPath);

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents(
                $fullOutputPath,
                json_encode($keywords, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            // Display summary
            $queryCount = collect($keywords['categories'] ?? [])
                ->sum(fn($cat) => count($cat['queries'] ?? []));
            $categoryCount = count($keywords['categories'] ?? []);

            $this->info("Generirano {$queryCount} upita u {$categoryCount} kategorija");
            $this->line("Spremljeno u: {$fullOutputPath}");
            $this->newLine();

            // Show categories
            $this->info("Kategorije:");
            foreach ($keywords['categories'] ?? [] as $cat) {
                $qCount = count($cat['queries'] ?? []);
                $this->line("  - {$cat['name']} ({$qCount} upita)");
            }

            $this->newLine();
            $this->info("Sljedeci korak:");
            $this->line("  php artisan sudska-praksa:search --keywords-file={$outputPath} --persist");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Greska: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Build the system prompt for AI keyword generation
     */
    private function buildSystemPrompt(string $methodology): string
    {
        return <<<PROMPT
Ti si strucnjak za pretragu hrvatske sudske prakse na odluke.sudovi.hr.

Slijedi METHODOLOGY.md upute za generiranje kljucnih rijeci:

{$methodology}

PRAVILA:
- Koristi UVIJEK "AND" operator (nikad OR)
- Optimalna duljina: 2-4 kljucne rijeci po upitu
- Koristi pravnu terminologiju, ne kolokvijalne izraze
- Koristi infinitiv/nominativ formu
- Ukljuci clanke zakona kad je moguce (npr. "cl. 35" ili "clanak 35")
- Cilj: <=50 rezultata po upitu za preciznost
- Generiraj 3-5 upita po kategoriji
- Kreiraj 3-6 relevantnih kategorija

IZLAZ: Vrati SAMO validan JSON (bez markdown oznaka, bez teksta prije ili poslije) u formatu:
{
  "metadata": {
    "name": "Kratak naziv slucaja",
    "description": "Kratki opis pravnih pitanja",
    "version": "1.0"
  },
  "categories": [
    {
      "name": "1. Naziv kategorije",
      "description": "Opis kategorije i zasto je relevantna",
      "queries": [
        { "q": "keyword1 AND keyword2", "comment": "Zasto ovaj upit" },
        { "q": "keyword1 AND keyword3 AND keyword4", "comment": "Specificirani upit" }
      ]
    }
  ]
}
PROMPT;
    }

    /**
     * Extract JSON from AI response (may be wrapped in markdown)
     */
    private function extractJson(string $content): ?string
    {
        // Try to find JSON in code blocks first
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $content, $matches)) {
            return $matches[1];
        }

        // Try to find raw JSON object
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            return $matches[0];
        }

        return null;
    }
}
