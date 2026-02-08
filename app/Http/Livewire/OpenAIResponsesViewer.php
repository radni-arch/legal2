<?php

namespace App\Http\Livewire;

use App\Services\OpenAIService;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * OpenAI Responses Viewer Component
 *
 * Displays OpenAI API request/response logs for debugging, auditing, and cost analysis.
 * Uses OpenAI's Responses API (beta) to fetch conversation logs with filtering capabilities.
 *
 * Features:
 * - Date range filtering (from/to dates)
 * - Text search across input/output/model/ID
 * - Configurable result limit (1-100)
 * - Sort order (newest/oldest)
 * - Timeline-style UI with visual indicators
 * - Display of input/output text and images
 * - Error handling and validation
 *
 * @property string|null $from Start date filter (Y-m-d format)
 * @property string|null $to End date filter (Y-m-d format)
 * @property string $search Search query for filtering results
 * @property int $limit Maximum number of results to display (1-100)
 * @property string $order Sort order ('asc' or 'desc')
 * @property array $items Array of formatted response items
 * @property string|null $error Error message if loading fails
 */
class OpenAIResponsesViewer extends Component
{
    #[Url]
    public ?string $from = null; // Y-m-d

    #[Url]
    public ?string $to = null;   // Y-m-d

    #[Url]
    public string $search = '';

    #[Url]
    public int $limit = 20;

    #[Url]
    public string $order = 'desc'; // or 'asc'

    public array $items = [];

    public ?string $error = null;

    // Session credentials modal
    public bool $showCredentialsModal = false;
    public string $sessionToken = '';
    public string $organizationId = '';
    public string $projectId = '';
    public bool $needsCredentials = false;

    /**
     * Initialize component with default date range (last 7 days) and load responses.
     */
    public function mount(): void
    {
        // Defaults: last 7 days
        if (! $this->to) {
            $this->to = now()->format('Y-m-d');
        }
        if (! $this->from) {
            $this->from = now()->subDays(7)->format('Y-m-d');
        }

        $this->loadSessionCredentials();
        $this->loadResponses();
    }

    protected function loadSessionCredentials(): void
    {
        $this->sessionToken = session('openai_session_token', '');
        $this->organizationId = session('openai_organization_id', '');
        $this->projectId = session('openai_project_id', '');
    }

    public function saveCredentials(): void
    {
        if (empty(trim($this->sessionToken))) {
            $this->error = 'Session token is required.';
            return;
        }

        session([
            'openai_session_token' => trim($this->sessionToken),
            'openai_organization_id' => trim($this->organizationId),
            'openai_project_id' => trim($this->projectId),
        ]);

        $this->showCredentialsModal = false;
        $this->needsCredentials = false;
        $this->error = null;

        $this->loadResponses();
    }

    public function cancelCredentials(): void
    {
        $this->showCredentialsModal = false;
        $this->loadSessionCredentials();
    }

    public function openCredentialsModal(): void
    {
        $this->loadSessionCredentials();
        $this->showCredentialsModal = true;
    }

    public function clearCredentials(): void
    {
        session()->forget(['openai_session_token', 'openai_organization_id', 'openai_project_id']);
        $this->sessionToken = '';
        $this->organizationId = '';
        $this->projectId = '';
    }

    /**
     * Livewire lifecycle hook - reload responses when filter properties change.
     *
     * @param  string  $property  Name of the property that was updated
     */
    public function updated($property): void
    {
        if (in_array($property, ['from', 'to', 'limit', 'order', 'search'], true)) {
            $this->loadResponses();
        }
    }

    /**
     * Manual refresh triggered by user clicking the refresh button.
     */
    public function refreshNow(): void
    {
        $this->loadResponses();
    }

    /**
     * Load responses from OpenAI API with current filters applied.
     * Validates date range, applies search filter, and handles errors gracefully.
     */
    protected function loadResponses(): void
    {
        $this->error = null;
        $this->items = [];

        // Validate and convert date filters
        $fromTs = $this->parseDateToTs($this->from);
        $toTs = $this->parseDateToTs($this->to, endOfDay: true);
        if ($fromTs && $toTs && $fromTs > $toTs) {
            $this->error = 'Invalid range: from date is after to date.';

            return;
        }

        try {
            /** @var OpenAIService $svc */
            $svc = app(OpenAIService::class);

            $query = [
                'created_after' => $fromTs,
                'created_before' => $toTs,
                'limit' => max(1, min(100, (int) $this->limit)),
                'order' => in_array(strtolower($this->order), ['asc', 'desc'], true) ? strtolower($this->order) : 'desc',
                'input_item_limit' => 1,
                'output_item_limit' => 1,
            ];

            $include = [
                'message.input_text',
                'message.input_image.image_url',
                'output_text',
                'computer_call_output.output.image_url',
                'file_search_call.results',
            ];

            // Check if we have session credentials
            $sessionToken = session('openai_session_token');

            if ($sessionToken) {
                // Use session-based authentication
                $sessionCredentials = [
                    'token' => $sessionToken,
                    'organization' => session('openai_organization_id', ''),
                    'project' => session('openai_project_id', ''),
                ];
                $resp = $svc->getResponsesWithSession($query, $include, $sessionCredentials);
            } else {
                // Use default API key authentication
                $resp = $svc->getResponses($query, $include);
            }

            $data = (array) ($resp['data'] ?? []);

            $items = [];
            foreach ($data as $row) {
                $items[] = $this->mapResponseRow((array) $row);
            }

            // Optional search filter on mapped content
            if ($this->search) {
                $q = mb_strtolower($this->search);
                $items = array_values(array_filter($items, function ($it) use ($q) {
                    $hay = mb_strtolower(json_encode([$it['id'], $it['input_text'], $it['output_text'], $it['model'] ?? '', $it['created_at'] ?? '']));

                    return str_contains($hay, $q);
                }));
            }

            $this->items = $items;
            $this->needsCredentials = false; // Success, clear flag
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();

            // Check for 401 session key error
            if ($this->isSessionKeyError($errorMessage)) {
                $this->needsCredentials = true;
                $this->showCredentialsModal = true;
                $this->error = 'OpenAI requires session authentication. Please enter your credentials.';
                Log::info('openai.responses.viewer.session_required', [
                    'message' => $errorMessage,
                ]);
                return;
            }

            // Surface a friendly message without leaking secrets
            $this->error = 'Failed to load OpenAI responses: ' . $errorMessage;
            Log::warning('openai.responses.viewer.error', [
                'error' => $errorMessage,
                'class' => get_class($e),
            ]);
        }
    }

    /**
     * Parse a date string to Unix timestamp.
     *
     * @param  string|null  $date  Date string in Y-m-d format
     * @param  bool  $endOfDay  If true, set time to end of day (23:59:59)
     * @return int|null Unix timestamp or null if parsing fails
     */
    protected function parseDateToTs(?string $date, bool $endOfDay = false): ?int
    {
        if (! $date) {
            return null;
        }
        try {
            $dt = Carbon::parse($date);
            if ($endOfDay) {
                $dt = $dt->endOfDay();
            }

            return $dt->timestamp;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Map a raw OpenAI API response row to a formatted array for display.
     * Extracts ID, timestamp, model, input/output text, and image URLs.
     *
     * @param  array  $row  Raw response data from OpenAI API
     * @return array Formatted response item with keys: id, created_at, model, input_text, output_text, images, raw
     */
    protected function mapResponseRow(array $row): array
    {
        $id = (string) ($row['id'] ?? Arr::get($row, 'response.id', ''));
        $created = Arr::get($row, 'created') ?? Arr::get($row, 'created_at');
        $createdAt = is_numeric($created) ? (int) $created : (is_string($created) ? strtotime($created) : null);
        $model = (string) ($row['model'] ?? Arr::get($row, 'response.model', ''));

        // Input and output summary (best-effort)
        $inputText = Arr::get($row, 'message.input_text');
        if (! $inputText) {
            $inputText = Arr::get($row, 'input_text');
        }
        if (is_array($inputText)) {
            $inputText = trim((string) ($inputText['text'] ?? json_encode($inputText)));
        }

        $outputText = Arr::get($row, 'output_text');
        if (! $outputText) {
            // try to synthesize from output items
            $outputs = (array) ($row['output'] ?? []);
            $texts = [];
            foreach ($outputs as $o) {
                $t = Arr::get($o, 'content.0.text') ?? Arr::get($o, 'text');
                if ($t) {
                    $texts[] = is_array($t) ? json_encode($t) : (string) $t;
                }
            }
            $outputText = $texts ? implode("\n\n", $texts) : null;
        }

        // Image URLs best-effort
        $images = [];
        $img1 = Arr::get($row, 'message.input_image.image_url');
        if ($img1) {
            $images[] = $img1;
        }
        $img2s = Arr::get($row, 'computer_call_output.output.image_url');
        if (is_array($img2s)) {
            foreach ($img2s as $u) {
                $images[] = (string) $u;
            }
        } elseif (is_string($img2s)) {
            $images[] = $img2s;
        }

        return [
            'id' => $id,
            'created_at' => $createdAt ? date('Y-m-d H:i:s', $createdAt) : null,
            'model' => $model,
            'input_text' => $this->clip($inputText, 2000),
            'output_text' => $this->clip($outputText, 4000),
            'images' => $images,
            'raw' => $row,
        ];
    }

    /**
     * Clip text to a maximum length, adding ellipsis if truncated.
     *
     * @param  string|null  $text  Text to clip
     * @param  int  $limit  Maximum character length
     * @return string|null Clipped text with ellipsis or null if input is null
     */
    protected function clip($text, int $limit): ?string
    {
        if ($text === null) {
            return null;
        }
        $s = (string) $text;
        if (mb_strlen($s) > $limit) {
            return mb_substr($s, 0, $limit - 1).'…';
        }

        return $s;
    }

    protected function isSessionKeyError(string $message): bool
    {
        $indicators = [
            'status code 401',
            'session key',
            'must be made with a session',
        ];

        $lowerMessage = mb_strtolower($message);

        foreach ($indicators as $indicator) {
            if (str_contains($lowerMessage, mb_strtolower($indicator))) {
                return true;
            }
        }

        return false;
    }

    public function render()
    {
        return view('livewire.openai-responses-viewer');
    }
}
