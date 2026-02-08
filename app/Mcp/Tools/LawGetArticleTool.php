<?php

namespace App\Mcp\Tools;

use App\Models\Law;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tool;
use Prism\Prism\ValueObjects\ToolResult;

class LawGetArticleTool extends Tool
{
    protected string $name = 'law.get_article';

    protected string $title = 'Get Law Article';

    protected string $description = 'Retrieve a specific article or chunk from a law by doc_id and chunk number/identifier.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'doc_id' => $schema->string()->required()->description('Document ID of the law'),
            'number' => $schema->integer()->description('Chunk index or article number to retrieve (default: 0 for first chunk)'),
            'chapter' => $schema->string()->description('Filter by chapter name/number'),
            'section' => $schema->string()->description('Filter by section name/number'),
        ];
    }

    public function handle(array $arguments): ToolResult
    {
        $query = Law::where('doc_id', $arguments['doc_id']);

        // Filter by chunk index if provided
        if (isset($arguments['number'])) {
            $query->where('chunk_index', (int) $arguments['number']);
        }

        // Filter by chapter if provided
        if (! empty($arguments['chapter'])) {
            $query->where('chapter', 'like', '%'.$arguments['chapter'].'%');
        }

        // Filter by section if provided
        if (! empty($arguments['section'])) {
            $query->where('section', 'like', '%'.$arguments['section'].'%');
        }

        $articles = $query->orderBy('chunk_index')->get([
            'id', 'doc_id', 'title', 'law_number', 'chapter', 'section',
            'chunk_index', 'content', 'metadata', 'source_url',
        ]);

        if ($articles->isEmpty()) {
            return ToolResult::error(sprintf(
                'No articles found for doc_id "%s" with the given criteria.',
                $arguments['doc_id']
            ));
        }

        $result = [
            'success' => true,
            'doc_id' => $arguments['doc_id'],
            'total_chunks' => $articles->count(),
            'articles' => $articles->toArray(),
        ];

        return ToolResult::text(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function description(): string
    {
        return 'Retrieve specific articles or chunks from a law document by doc_id and optional filters.';
    }
}
