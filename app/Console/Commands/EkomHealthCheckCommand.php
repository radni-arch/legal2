<?php

namespace App\Console\Commands;

use App\Clients\EkomApiClientInterface;
use App\Exceptions\EkomApiException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class EkomHealthCheckCommand extends Command
{
    protected $signature = 'ekom:health-check
                            {--json : Output as JSON}';

    protected $description = 'Check e-Komunikacija API connectivity and token validity';

    public function handle(EkomApiClientInterface $client): int
    {
        $results = [
            'timestamp' => now()->toIso8601String(),
            'checks' => [],
        ];

        // Check configuration
        $results['checks']['config'] = $this->checkConfig();

        // Check API connectivity
        $results['checks']['api_connectivity'] = $this->checkApiConnectivity($client);

        // Check token validity
        $results['checks']['token_validity'] = $this->checkTokenValidity($client);

        // Determine overall status
        $allPassed = collect($results['checks'])->every(fn ($check) => $check['status'] === 'ok');
        $results['status'] = $allPassed ? 'healthy' : 'unhealthy';

        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT));
        } else {
            $this->displayResults($results);
        }

        return $allPassed ? self::SUCCESS : self::FAILURE;
    }

    private function checkConfig(): array
    {
        $baseUrl = Config::get('ekom.base_url');
        $token = Config::get('ekom.token');

        $issues = [];

        if (empty($baseUrl)) {
            $issues[] = 'EKOM_BASE_URL not configured';
        }

        if (empty($token)) {
            $issues[] = 'EKOM_TOKEN not configured';
        }

        return [
            'status' => empty($issues) ? 'ok' : 'error',
            'issues' => $issues,
            'details' => [
                'base_url' => $baseUrl ? 'configured' : 'missing',
                'token' => $token ? 'configured (hidden)' : 'missing',
                'timeout' => Config::get('ekom.timeout', 30),
                'retries' => Config::get('ekom.retries', 2),
            ],
        ];
    }

    private function checkApiConnectivity(EkomApiClientInterface $client): array
    {
        try {
            // Try to fetch courts as a simple connectivity check
            $startTime = microtime(true);
            $courts = $client->getSudovi();
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'ok',
                'issues' => [],
                'details' => [
                    'response_time_ms' => $duration,
                    'courts_count' => is_array($courts) ? count($courts) : 0,
                ],
            ];
        } catch (EkomApiException $e) {
            return [
                'status' => 'error',
                'issues' => ["API error: {$e->getMessage()} (HTTP {$e->statusCode})"],
                'details' => [
                    'error_code' => $e->statusCode,
                    'error_id' => $e->errorId,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'issues' => ["Connection failed: {$e->getMessage()}"],
                'details' => [
                    'exception' => get_class($e),
                ],
            ];
        }
    }

    private function checkTokenValidity(EkomApiClientInterface $client): array
    {
        try {
            // Fetch countries - a simple authenticated endpoint
            $countries = $client->getDrzave();

            return [
                'status' => 'ok',
                'issues' => [],
                'details' => [
                    'authenticated' => true,
                    'countries_count' => is_array($countries) ? count($countries) : 0,
                ],
            ];
        } catch (EkomApiException $e) {
            if ($e->statusCode === 401) {
                return [
                    'status' => 'error',
                    'issues' => ['Authentication failed - token may be invalid or expired'],
                    'details' => [
                        'authenticated' => false,
                        'http_status' => 401,
                    ],
                ];
            }

            if ($e->statusCode === 403) {
                return [
                    'status' => 'warning',
                    'issues' => ['Access forbidden - token may have insufficient permissions'],
                    'details' => [
                        'authenticated' => 'partial',
                        'http_status' => 403,
                    ],
                ];
            }

            return [
                'status' => 'error',
                'issues' => ["Token validation failed: {$e->getMessage()}"],
                'details' => [
                    'error_code' => $e->statusCode,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'issues' => ["Token check failed: {$e->getMessage()}"],
                'details' => [],
            ];
        }
    }

    private function displayResults(array $results): void
    {
        $this->newLine();
        $statusEmoji = $results['status'] === 'healthy' ? '✅' : '❌';
        $this->info("e-Komunikacija Health Check {$statusEmoji}");
        $this->line("Timestamp: {$results['timestamp']}");
        $this->newLine();

        foreach ($results['checks'] as $name => $check) {
            $statusIcon = match ($check['status']) {
                'ok' => '<fg=green>✓</>',
                'warning' => '<fg=yellow>⚠</>',
                default => '<fg=red>✗</>',
            };

            $this->line("  {$statusIcon} " . ucfirst(str_replace('_', ' ', $name)));

            if (! empty($check['issues'])) {
                foreach ($check['issues'] as $issue) {
                    $this->line("    <fg=red>→ {$issue}</>");
                }
            }

            if (! empty($check['details'])) {
                foreach ($check['details'] as $key => $value) {
                    if (is_bool($value)) {
                        $value = $value ? 'yes' : 'no';
                    }
                    $this->line("    <fg=gray>{$key}: {$value}</>");
                }
            }
        }

        $this->newLine();
        $this->line('Overall: <fg=' . ($results['status'] === 'healthy' ? 'green' : 'red') . ">{$results['status']}</>");
    }
}
