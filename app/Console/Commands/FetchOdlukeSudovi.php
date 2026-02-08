<?php

namespace App\Console\Commands;

use App\Models\CourtCase;
use App\Models\CaseDocument;
use App\Services\OdlukeSudoviService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FetchOdlukeSudovi extends Command
{
    protected $signature = 'epredmet:fetch-odluke 
                            {--query=Pp Prz : Search query}
                            {--pages=56 : Maximum pages to fetch}
                            {--match : Try to match with existing e-Predmet cases}
                            {--stats : Show statistics only}
                            {--export= : Export to JSON file}';
    
    protected $description = 'Fetch court decisions from odluke.sudovi.hr and optionally match with e-Predmet cases';
    
    protected OdlukeSudoviService $service;
    protected array $stats = [
        'fetched' => 0,
        'vps_decisions' => 0,
        'matched' => 0,
        'pp_prz_references' => 0,
    ];
    
    public function __construct(OdlukeSudoviService $service)
    {
        parent::__construct();
        $this->service = $service;
    }
    
    public function handle(): int
    {
        $query = $this->option('query');
        $maxPages = (int) $this->option('pages');
        $shouldMatch = $this->option('match');
        $statsOnly = $this->option('stats');
        $exportPath = $this->option('export');
        
        $this->info("🔍 Pretraživanje odluke.sudovi.hr: \"{$query}\"");
        $this->newLine();
        
        if ($statsOnly) {
            return $this->showStats($query);
        }
        
        $decisions = [];
        $progressBar = $this->output->createProgressBar($maxPages);
        $progressBar->setFormat(' %current%/%max% stranica [%bar%] %percent:3s%% | %message%');
        $progressBar->setMessage('Dohvaćanje...');
        
        for ($page = 1; $page <= $maxPages; $page++) {
            $results = $this->service->search($query, $page);
            
            if (!$results['success'] || empty($results['documents'])) {
                break;
            }
            
            foreach ($results['documents'] as $doc) {
                $full = $this->service->getDocument($doc['id']);
                
                if ($full['success']) {
                    $decisions[] = $full;
                    $this->stats['fetched']++;
                    
                    if (isset($full['court']) && str_contains($full['court'], 'Visoki prekršajni sud')) {
                        $this->stats['vps_decisions']++;
                    }
                    
                    if (isset($full['previous_decision_parsed']['case_number'])) {
                        $this->stats['pp_prz_references']++;
                    }
                    
                    $progressBar->setMessage($full['decision_number'] ?? 'Nepoznato');
                }
            }
            
            $progressBar->advance();
            
            if ($page >= $results['total_pages']) {
                break;
            }
            
            usleep(300000); // 0.3s rate limit
        }
        
        $progressBar->finish();
        $this->newLine(2);
        
        // Show summary
        $this->showSummary($decisions);
        
        // Match with e-Predmet if requested
        if ($shouldMatch) {
            $this->matchWithEPredmet($decisions);
        }
        
        // Export if requested
        if ($exportPath) {
            $this->exportToJson($decisions, $exportPath);
        }
        
        return Command::SUCCESS;
    }
    
    protected function showStats(string $query): int
    {
        $results = $this->service->search($query, 1);
        
        if (!$results['success']) {
            $this->error('Greška pri dohvaćanju statistike');
            return Command::FAILURE;
        }
        
        $estimatedCount = $results['total_pages'] * 10;
        
        $this->info("📊 Statistika za upit: \"{$query}\"");
        $this->newLine();
        
        $this->table(
            ['Metrika', 'Vrijednost'],
            [
                ['Ukupno stranica', $results['total_pages']],
                ['Procijenjeni broj odluka', "~{$estimatedCount}"],
                ['Rezultata na prvoj stranici', $results['count']],
            ]
        );
        
        // Sample first page
        $this->newLine();
        $this->info("📋 Primjer odluka (prva stranica):");
        $this->newLine();
        
        $samples = [];
        foreach (array_slice($results['documents'], 0, 5) as $doc) {
            $full = $this->service->getDocument($doc['id']);
            if ($full['success']) {
                $samples[] = [
                    $full['decision_number'] ?? 'N/A',
                    $full['court'] ?? 'N/A',
                    $full['decision_date'] ?? 'N/A',
                    $full['previous_decision_parsed']['case_number'] ?? '-',
                ];
            }
        }
        
        $this->table(
            ['Broj odluke', 'Sud', 'Datum', 'Prethodna odluka (Pp Prz)'],
            $samples
        );
        
        return Command::SUCCESS;
    }
    
    protected function showSummary(array $decisions): void
    {
        $this->info("📊 Sažetak dohvaćanja:");
        $this->newLine();
        
        $this->table(
            ['Metrika', 'Broj'],
            [
                ['Ukupno dohvaćenih odluka', $this->stats['fetched']],
                ['VPS odluke', $this->stats['vps_decisions']],
                ['S referencom na Pp Prz', $this->stats['pp_prz_references']],
            ]
        );
        
        // Court distribution
        $byCourt = collect($decisions)->groupBy('court')->map->count()->sortDesc();
        
        $this->newLine();
        $this->info("📍 Raspodjela po sudovima:");
        $this->table(
            ['Sud', 'Broj odluka'],
            $byCourt->take(10)->map(fn($count, $court) => [$court, $count])->values()->toArray()
        );
        
        // Year distribution
        $byYear = collect($decisions)
            ->map(function ($d) {
                if (isset($d['decision_date']) && preg_match('/(\d{4})/', $d['decision_date'], $m)) {
                    return $m[1];
                }
                return 'Nepoznato';
            })
            ->countBy()
            ->sortKeysDesc();
        
        $this->newLine();
        $this->info("📅 Raspodjela po godinama:");
        $this->table(
            ['Godina', 'Broj odluka'],
            $byYear->take(5)->map(fn($count, $year) => [$year, $count])->values()->toArray()
        );
    }
    
    protected function matchWithEPredmet(array $decisions): void
    {
        $this->newLine();
        $this->info("🔗 Uparivanje s e-Predmet bazom...");
        $this->newLine();
        
        $matched = 0;
        $notFound = 0;
        
        foreach ($decisions as $decision) {
            if (!isset($decision['previous_decision_parsed']['case_number'])) {
                continue;
            }
            
            $caseNumber = $decision['previous_decision_parsed']['case_number'];
            
            // Normalize case number (Pp Prz-34/2024 -> Pp Prz-34/24)
            $normalized = $this->normalizeCaseNumber($caseNumber);
            
            // Try to find in database
            $courtCase = CourtCase::where('case_number', 'LIKE', "%{$normalized}%")
                ->orWhere('case_number', 'LIKE', "%{$caseNumber}%")
                ->first();
            
            if ($courtCase) {
                $matched++;
                
                // Create document record linking to odluke.sudovi.hr
                CaseDocument::updateOrCreate(
                    [
                        'court_case_id' => $courtCase->id,
                        'document_type' => 'VPS_ODLUKA',
                        'external_id' => $decision['id'],
                    ],
                    [
                        'document_name' => $decision['decision_number'] ?? 'VPS odluka',
                        'document_date' => $this->parseDate($decision['decision_date'] ?? null),
                        'external_url' => $decision['url'],
                        'metadata' => json_encode([
                            'court' => $decision['court'] ?? null,
                            'ecli' => $decision['ecli'] ?? null,
                            'decision_type' => $decision['decision_type'] ?? null,
                            'text_preview' => substr($decision['text_plain'] ?? '', 0, 500),
                        ]),
                    ]
                );
                
                $this->line("  ✅ {$caseNumber} → {$decision['decision_number']}");
            } else {
                $notFound++;
            }
        }
        
        $this->newLine();
        $this->table(
            ['Status', 'Broj'],
            [
                ['Uspješno upareno', $matched],
                ['Nije pronađeno u e-Predmet', $notFound],
            ]
        );
        
        $this->stats['matched'] = $matched;
    }
    
    protected function normalizeCaseNumber(string $caseNumber): string
    {
        // Convert Pp Prz-34/2024 to Pp Prz-34/24
        return preg_replace('/\/20(\d{2})/', '/$1', $caseNumber);
    }
    
    protected function parseDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }
        
        // Parse DD.MM.YYYY
        if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})/', $date, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        
        return null;
    }
    
    protected function exportToJson(array $decisions, string $path): void
    {
        $exportPath = str_starts_with($path, '/') ? $path : "/home/claude/{$path}";
        
        $exportData = [
            'generated_at' => now()->toIso8601String(),
            'query' => $this->option('query'),
            'stats' => $this->stats,
            'decisions' => $decisions,
        ];
        
        file_put_contents($exportPath, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->info("📁 Izvezeno u: {$exportPath}");
    }
}
