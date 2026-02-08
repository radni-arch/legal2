<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use JsonException;

/**
 * Manual test command for e-Komunikacija podnesak submission.
 *
 * Replicates the curl example from documentation for manual testing:
 * curl -X POST https://api.example.com/api/ekom/v1/podnesci \
 *   -H "Authorization: Bearer $TOKEN" \
 *   -H "Content-Type: application/json" \
 *   -d @payload.json
 */
class EkomTestSubmitCommand extends Command
{
    protected $signature = 'ekom:test-submit
        {--token-file= : Path to file containing the bearer token}
        {--json-file= : Path to JSON payload file}
        {--files= : Comma-separated list of file paths for multipart upload}
        {--save-response= : Path to save the API response}
        {--dry-run : Show what would be sent without making the request}
        {--show-curl : Show equivalent curl command}
        {--base-url= : Override API base URL}';

    protected $description = 'Test e-Komunikacija podnesak submission (manual testing tool)';

    public function handle(): int
    {
        // Validate required options
        $tokenFile = $this->option('token-file');
        if (!$tokenFile) {
            $this->error('Missing required option: --token-file');
            return self::FAILURE;
        }

        $jsonFile = $this->option('json-file');
        if (!$jsonFile) {
            $this->error('Missing required option: --json-file');
            return self::FAILURE;
        }

        // Validate token file exists
        if (!file_exists($tokenFile)) {
            $this->error("Token file not found: {$tokenFile}");
            return self::FAILURE;
        }

        // Validate JSON file exists
        if (!file_exists($jsonFile)) {
            $this->error("JSON file not found: {$jsonFile}");
            return self::FAILURE;
        }

        // Read and validate token
        $token = trim(file_get_contents($tokenFile));
        if (empty($token)) {
            $this->error('Token file is empty');
            return self::FAILURE;
        }

        // Read and validate JSON payload
        $jsonContent = file_get_contents($jsonFile);
        try {
            $payload = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error("Invalid JSON in payload file: {$e->getMessage()}");
            return self::FAILURE;
        }

        // Parse and validate file attachments
        $files = $this->parseFiles();
        if ($files === false) {
            return self::FAILURE;
        }

        // Build the request URL
        $baseUrl = $this->option('base-url') ?: config('services.ekom.base_url', 'https://api.e-komunikacija.pravosudje.hr');
        $endpoint = rtrim($baseUrl, '/') . '/api/ekom/v1/podnesci';

        // Show dry run info
        if ($this->option('dry-run')) {
            $this->showDryRun($payload, $files, $endpoint, $token);
            return self::SUCCESS;
        }

        // Show curl equivalent if requested
        if ($this->option('show-curl')) {
            $this->showCurlCommand($endpoint, $token, $jsonFile, $files);
        }

        // Make the API request
        $this->info('Submitting podnesak to e-Komunikacija...');
        $this->newLine();

        try {
            $response = $this->makeRequest($endpoint, $token, $payload, $files);
        } catch (\Throwable $e) {
            $this->error("Request failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        // Handle response
        $statusCode = $response->status();
        $responseBody = $response->json() ?? $response->body();

        if ($response->successful()) {
            $this->info("Success! Status: {$statusCode}");
            $this->newLine();
            $this->line(json_encode($responseBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Save response if requested
            if ($saveFile = $this->option('save-response')) {
                file_put_contents($saveFile, json_encode($responseBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->newLine();
                $this->info("Response saved to: {$saveFile}");
            }

            return self::SUCCESS;
        }

        // Handle error response
        $this->error("Error! Status: {$statusCode}");
        $this->newLine();
        $this->line(json_encode($responseBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Save error response if requested
        if ($saveFile = $this->option('save-response')) {
            file_put_contents($saveFile, json_encode($responseBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return self::FAILURE;
    }

    /**
     * Parse file attachments from option.
     *
     * @return array|false Array of file paths or false on error
     */
    private function parseFiles(): array|false
    {
        $filesOption = $this->option('files');
        if (!$filesOption) {
            return [];
        }

        $files = array_map('trim', explode(',', $filesOption));
        $validFiles = [];

        foreach ($files as $file) {
            if (empty($file)) {
                continue;
            }

            if (!file_exists($file)) {
                $this->error("File not found: {$file}");
                return false;
            }

            $validFiles[] = $file;
        }

        return $validFiles;
    }

    /**
     * Display dry run information.
     */
    private function showDryRun(array $payload, array $files, string $endpoint, string $token): void
    {
        $this->info('=== DRY RUN - No request will be made ===');
        $this->newLine();

        $this->line("<comment>Endpoint:</comment> {$endpoint}");
        $this->line('<comment>Authorization:</comment> Bearer ' . substr($token, 0, 10) . '...');
        $this->newLine();

        $this->line('<comment>Payload:</comment>');
        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->newLine();

        if (!empty($files)) {
            $this->line('<comment>Files:</comment>');
            foreach ($files as $file) {
                $size = filesize($file);
                $this->line("  - {$file} ({$size} bytes)");
            }
        }

        if ($this->option('show-curl')) {
            $this->newLine();
            $this->showCurlCommand($endpoint, $token, $this->option('json-file'), $files);
        }
    }

    /**
     * Display equivalent curl command.
     */
    private function showCurlCommand(string $endpoint, string $token, string $jsonFile, array $files): void
    {
        $this->line('<comment>Equivalent curl command:</comment>');

        if (empty($files)) {
            // Simple JSON request
            $curl = sprintf(
                'curl -X POST "%s" \\' . PHP_EOL .
                '  -H "Authorization: Bearer %s" \\' . PHP_EOL .
                '  -H "Content-Type: application/json" \\' . PHP_EOL .
                '  -d @"%s"',
                $endpoint,
                '$TOKEN',
                $jsonFile
            );
        } else {
            // Multipart request
            $curl = sprintf(
                'curl -X POST "%s" \\' . PHP_EOL .
                '  -H "Authorization: Bearer %s" \\' . PHP_EOL .
                '  -F "meta=@%s;type=application/json"',
                $endpoint,
                '$TOKEN',
                $jsonFile
            );

            foreach ($files as $i => $file) {
                $curl .= sprintf(' \\' . PHP_EOL . '  -F "file%d=@%s"', $i, $file);
            }
        }

        $this->line($curl);
    }

    /**
     * Make the API request.
     */
    private function makeRequest(string $endpoint, string $token, array $payload, array $files): \Illuminate\Http\Client\Response
    {
        $http = Http::withToken($token)
            ->timeout(60)
            ->withOptions(['verify' => config('services.ekom.verify_ssl', true)]);

        if (empty($files)) {
            // Simple JSON request
            return $http->post($endpoint, $payload);
        }

        // Multipart request with files
        return $http->asMultipart()
            ->attach('meta', json_encode($payload), 'meta.json', ['Content-Type' => 'application/json'])
            ->when(!empty($files), function ($request) use ($files) {
                foreach ($files as $i => $file) {
                    $request->attach(
                        "file{$i}",
                        file_get_contents($file),
                        basename($file)
                    );
                }
                return $request;
            })
            ->post($endpoint);
    }
}
