<?php

namespace App\Console\Commands;

use App\Models\CourtCase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UnmatchedCasesReport extends Command
{
    protected $signature = 'cases:unmatched-report
                            {--court= : Filter by court ID}
                            {--year= : Filter by year}
                            {--format=table : Output format (table|csv|json)}
                            {--output= : File path for csv/json export}';

    protected $description = 'Generate report of unmatched court cases';

    public function handle(): int
    {
        $courtId = $this->option('court');
        $year = $this->option('year');
        $format = $this->option('format');
        $outputPath = $this->option('output');

        $query = CourtCase::unmatched()
            ->with('court');

        if ($courtId) {
            $query->where('court_id', $courtId);
        }

        if ($year) {
            $query->where('year', $year);
        }

        $cases = $query->get();

        $this->info("Found {$cases->count()} unmatched cases");

        if ($cases->isEmpty()) {
            return self::SUCCESS;
        }

        $data = $cases->map(fn($case) => [
            'id' => $case->id,
            'case_number' => $case->case_number,
            'court' => $case->court?->name ?? 'N/A',
            'year' => $case->year,
            'judge' => $case->judge_name ?? 'N/A',
            'decision_date' => $case->date_decision?->format('Y-m-d') ?? 'N/A',
        ])->toArray();

        match ($format) {
            'json' => $this->outputJson($data, $outputPath),
            'csv' => $this->outputCsv($data, $outputPath),
            default => $this->outputTable($data),
        };

        return self::SUCCESS;
    }

    protected function outputTable(array $data): void
    {
        $this->table(
            ['ID', 'Case Number', 'Court', 'Year', 'Judge', 'Decision Date'],
            $data
        );
    }

    protected function outputJson(array $data, ?string $path): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($path) {
            File::put($path, $json);
            $this->info("Exported to: {$path}");
        } else {
            $this->line($json);
        }
    }

    protected function outputCsv(array $data, ?string $path): void
    {
        $csv = '';
        if (!empty($data)) {
            $csv .= implode(',', array_keys($data[0])) . "\n";
            foreach ($data as $row) {
                $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $row)) . "\n";
            }
        }

        if ($path) {
            File::put($path, $csv);
            $this->info("Exported to: {$path}");
        } else {
            $this->line($csv);
        }
    }
}
