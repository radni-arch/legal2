<?php

namespace App\Mcp\Tools;

use App\Models\CourtDecision;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tool;
use Prism\Prism\ValueObjects\ToolResult;

class DecisionGetTool extends Tool
{
    protected string $name = 'decision.get';

    protected string $title = 'Get Court Decision';

    protected string $description = 'Retrieve a specific court decision by ID with its associated documents and content.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required()->description('Decision ID (ULID)'),
            'include_documents' => $schema->boolean()->default(true)
                ->description('Include associated documents and their content'),
            'include_content' => $schema->boolean()->default(false)
                ->description('Include full document content (can be large)'),
        ];
    }

    public function handle(array $arguments): ToolResult
    {
        $decision = CourtDecision::find($arguments['id']);

        if (! $decision) {
            return ToolResult::error(sprintf(
                'Court decision with ID "%s" not found.',
                $arguments['id']
            ));
        }

        $result = [
            'success' => true,
            'decision' => [
                'id' => $decision->id,
                'case_number' => $decision->case_number,
                'title' => $decision->title,
                'court' => $decision->court,
                'jurisdiction' => $decision->jurisdiction,
                'judge' => $decision->judge,
                'decision_date' => $decision->decision_date?->format('Y-m-d'),
                'publication_date' => $decision->publication_date?->format('Y-m-d'),
                'decision_type' => $decision->decision_type,
                'register' => $decision->register,
                'finality' => $decision->finality,
                'ecli' => $decision->ecli,
                'tags' => $decision->tags,
                'description' => $decision->description,
                'created_at' => $decision->created_at?->toIso8601String(),
                'updated_at' => $decision->updated_at?->toIso8601String(),
            ],
        ];

        // Include documents if requested
        if ($arguments['include_documents'] ?? true) {
            $documentsQuery = $decision->documents();

            if ($arguments['include_content'] ?? false) {
                // Include full content
                $documents = $documentsQuery->orderBy('chunk_index')->get();
            } else {
                // Minimal payload without content
                $documents = $documentsQuery->select([
                    'id', 'decision_id', 'doc_id', 'title', 'category',
                    'author', 'language', 'tags', 'chunk_index', 'metadata',
                    'source', 'source_id',
                ])->orderBy('chunk_index')->get();
            }

            $result['documents'] = [
                'total_chunks' => $documents->count(),
                'items' => $documents->toArray(),
            ];
        }

        return ToolResult::text(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function description(): string
    {
        return 'Retrieve a specific court decision with optional document content.';
    }
}
