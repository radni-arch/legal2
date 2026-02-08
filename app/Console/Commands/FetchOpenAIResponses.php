<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class FetchOpenAIResponses extends Command
{
    protected $signature = 'openai:fetch-responses 
                            {--after= : Start fetching after this response ID}
                            {--limit=100 : Maximum number of responses to fetch}
                            {--output=openai-responses : Output directory name}';

    protected $description = 'Recursively fetch OpenAI conversation logs and store as markdown files';

    private array $headers;
    private string $baseUrl = 'https://api.openai.com/v1/responses';

    public function __construct()
    {
        parent::__construct();

        $this->headers = [
            'Authorization' => 'Bearer ' . config('services.openai.session_token', 'sess-FoW9wWd17OSk6ObQh5eEPjE9cTzeJ0dPBB0djzpf'),
            'OpenAI-Beta' => 'responses=v1',
            'OpenAI-Organization' => config('services.openai.organization', 'org-oybkBvmP0ssQWNxNBD5RgFYM'),
            'OpenAI-Project' => config('services.openai.project', 'proj_kzHc1JffuBjFm90wLS8KzvMO'),
            'Origin' => 'https://platform.openai.com',
            'Referer' => 'https://platform.openai.com/',
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36',
        ];
    }

    public function handle(): int
    {
        $afterId = $this->option('after');
        $limit = (int) $this->option('limit');
        $outputDir = $this->option('output');

        $this->info("Fetching OpenAI responses...");
        $this->info("Output directory: storage/app/{$outputDir}");

        Storage::makeDirectory($outputDir);

        $fetchedCount = 0;
        $hasMore = true;

        while ($hasMore && $fetchedCount < $limit) {
            $responses = $this->fetchResponsesList($afterId);

            if (empty($responses['data'])) {
                $this->info("No more responses to fetch.");
                break;
            }

            foreach ($responses['data'] as $responseSummary) {
                if ($fetchedCount >= $limit) {
                    break;
                }

                $responseId = $responseSummary['id'];
                $this->line("Fetching details for: {$responseId}");

                $responseDetails = $this->fetchResponseDetails($responseId);

                if ($responseDetails) {
                    $markdown = $this->convertToMarkdown($responseDetails);
                    $filename = $this->generateFilename($responseDetails, $outputDir);

                    Storage::put($filename, $markdown);
                    $this->info("  Saved: {$filename}");
                    $fetchedCount++;
                }

                $afterId = $responseId;

                // Rate limiting - be gentle with the API
                usleep(200000); // 200ms delay
            }

            // Check if there are more results
            $hasMore = count($responses['data']) > 0;
        }

        $this->info("Completed! Fetched {$fetchedCount} responses.");

        return Command::SUCCESS;
    }

    private function fetchResponsesList(?string $afterId = null): array
    {
        $url = $this->baseUrl . '?' . http_build_query(array_filter([
            'after' => $afterId,
            'include[]' => 'message.input_image.image_url',
            'input_item_limit' => 1,
            'output_item_limit' => 1,
        ]));

        $response = Http::withHeaders($this->headers)
            ->timeout(30)
            ->get($url);

        if ($response->failed()) {
            $this->error("Failed to fetch responses list: " . $response->status());
            return ['data' => []];
        }

        return $response->json() ?? ['data' => []];
    }

    private function fetchResponseDetails(string $responseId): ?array
    {
        $url = $this->baseUrl . '/' . $responseId . '?' . http_build_query([
            'include[]' => [
                'message.input_image.image_url',
                'computer_call_output.output.image_url',
                'code_interpreter_call.outputs',
                'file_search_call.results',
            ],
        ]);

        // Fix the include[] array encoding
        $url = $this->baseUrl . '/' . $responseId . 
            '?include[]=message.input_image.image_url' .
            '&include[]=computer_call_output.output.image_url' .
            '&include[]=code_interpreter_call.outputs' .
            '&include[]=file_search_call.results';

        $response = Http::withHeaders($this->headers)
            ->timeout(30)
            ->get($url);

        if ($response->failed()) {
            $this->error("  Failed to fetch response details: " . $response->status());
            return null;
        }

        return $response->json();
    }

    private function convertToMarkdown(array $response): string
    {
        $md = [];

        // Header with metadata
        $createdAt = isset($response['created_at']) 
            ? Carbon::createFromTimestamp($response['created_at'])->toDateTimeString() 
            : 'Unknown';
        
        $model = $response['model'] ?? 'Unknown';
        $status = $response['status'] ?? 'Unknown';

        $md[] = "# OpenAI Response Log";
        $md[] = "";
        $md[] = "- **Response ID:** `{$response['id']}`";
        $md[] = "- **Created:** {$createdAt}";
        $md[] = "- **Model:** {$model}";
        $md[] = "- **Status:** {$status}";
        $md[] = "";
        $md[] = "---";
        $md[] = "";

        // User Input
        $md[] = "## User Input";
        $md[] = "";
        
        $userInput = $this->extractUserInput($response);
        if ($userInput) {
            $md[] = $userInput;
        } else {
            $md[] = "*No user input found*";
        }
        $md[] = "";
        $md[] = "---";
        $md[] = "";

        // Reasoning (if present)
        $reasoning = $this->extractReasoning($response);
        if ($reasoning) {
            $md[] = "## Reasoning";
            $md[] = "";
            $md[] = $reasoning;
            $md[] = "";
            $md[] = "---";
            $md[] = "";
        }

        // Assistant Response
        $md[] = "## Assistant Response";
        $md[] = "";
        
        $assistantResponse = $this->extractAssistantResponse($response);
        if ($assistantResponse) {
            $md[] = $assistantResponse;
        } else {
            $md[] = "*No assistant response found*";
        }

        return implode("\n", $md);
    }

    private function extractUserInput(array $response): ?string
    {
        $inputs = [];

        if (!isset($response['input']) || !is_array($response['input'])) {
            return null;
        }

        foreach ($response['input'] as $inputItem) {
            if ($inputItem['type'] === 'message' && $inputItem['role'] === 'user') {
                foreach ($inputItem['content'] ?? [] as $content) {
                    if ($content['type'] === 'input_text') {
                        $inputs[] = $content['text'];
                    }
                }
            }
        }

        return !empty($inputs) ? implode("\n\n", array_unique($inputs)) : null;
    }

    private function extractReasoning(array $response): ?string
    {
        $reasoningParts = [];

        if (!isset($response['output']) || !is_array($response['output'])) {
            return null;
        }

        foreach ($response['output'] as $outputItem) {
            if ($outputItem['type'] === 'reasoning' && isset($outputItem['summary'])) {
                foreach ($outputItem['summary'] as $summary) {
                    if ($summary['type'] === 'summary_text' && !empty($summary['text'])) {
                        $reasoningParts[] = $summary['text'];
                    }
                }
            }
        }

        if (empty($reasoningParts)) {
            return null;
        }

        // Deduplicate reasoning parts
        $uniqueParts = array_unique($reasoningParts);

        return implode("\n\n", $uniqueParts);
    }

    private function extractAssistantResponse(array $response): ?string
    {
        $responseParts = [];

        if (!isset($response['output']) || !is_array($response['output'])) {
            return null;
        }

        foreach ($response['output'] as $outputItem) {
            // Handle message type outputs
            if ($outputItem['type'] === 'message' && isset($outputItem['content'])) {
                foreach ($outputItem['content'] as $content) {
                    if (isset($content['text'])) {
                        $responseParts[] = $content['text'];
                    }
                }
            }

            // Handle direct text output
            if ($outputItem['type'] === 'text' && isset($outputItem['text'])) {
                $responseParts[] = $outputItem['text'];
            }
        }

        if (empty($responseParts)) {
            return null;
        }

        // Deduplicate response parts
        $uniqueParts = array_unique($responseParts);

        return implode("\n\n", $uniqueParts);
    }

    private function generateFilename(array $response, string $outputDir): string
    {
        $timestamp = isset($response['created_at'])
            ? Carbon::createFromTimestamp($response['created_at'])->format('Y-m-d_H-i-s')
            : Carbon::now()->format('Y-m-d_H-i-s');

        $shortId = substr($response['id'], 5, 12); // Take part of the response ID

        return "{$outputDir}/{$timestamp}_{$shortId}.md";
    }
}
