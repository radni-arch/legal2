<?php

namespace App\Services\Hudoc;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class EchrExtractorBridge
{
    protected string $pythonPath;

    protected string $scriptPath;

    public function __construct()
    {
        $this->pythonPath = config('hudoc.python_path', 'python3');
        $this->scriptPath = base_path('scripts/echr_extractor.py');
    }

    public function extractMetadata(
        int $count = 100,
        ?string $startDate = null,
        ?string $endDate = null,
        string $language = 'ENG'
    ): Collection {
        $args = [
            'metadata',
            '--count', $count,
            '--language', $language,
        ];

        if ($startDate) {
            $args[] = '--start-date';
            $args[] = $startDate;
        }

        if ($endDate) {
            $args[] = '--end-date';
            $args[] = $endDate;
        }

        return $this->execute($args);
    }

    public function extractWithFullText(
        int $count = 100,
        ?string $startDate = null,
        ?string $endDate = null,
        string $language = 'ENG',
        int $threads = 10
    ): Collection {
        $args = [
            'fulltext',
            '--count', $count,
            '--language', $language,
            '--threads', $threads,
        ];

        if ($startDate) {
            $args[] = '--start-date';
            $args[] = $startDate;
        }

        if ($endDate) {
            $args[] = '--end-date';
            $args[] = $endDate;
        }

        return $this->execute($args);
    }

    public function extractByQuery(string $query): Collection
    {
        return $this->execute(['query', '--query', $query]);
    }

    public function extractNetwork(int $count = 100): array
    {
        $result = $this->execute(['network', '--count', $count]);

        return [
            'nodes' => collect($result->get('nodes', [])),
            'edges' => collect($result->get('edges', [])),
        ];
    }

    protected function execute(array $args): Collection
    {
        $command = array_merge([$this->pythonPath, $this->scriptPath], $args);

        try {
            $result = Process::timeout(600)->run($command);

            if ($result->successful()) {
                $output = $result->output();
                $data = json_decode($output, true);

                if (isset($data['error'])) {
                    Log::error('ECHR Extractor error', ['error' => $data['error']]);

                    return collect();
                }

                return collect($data);
            }

            Log::error('ECHR Extractor process failed', [
                'exit_code' => $result->exitCode(),
                'output' => $result->output(),
                'error' => $result->errorOutput(),
            ]);

            return collect();

        } catch (\Exception $e) {
            Log::error('ECHR Extractor exception', ['error' => $e->getMessage()]);

            return collect();
        }
    }

    public function isAvailable(): bool
    {
        try {
            $result = Process::run([$this->pythonPath, '-c', 'import echr_extractor']);

            return $result->successful();
        } catch (\Exception) {
            return false;
        }
    }
}
