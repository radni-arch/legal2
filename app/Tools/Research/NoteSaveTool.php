<?php

namespace App\Tools\Research;

use App\Services\AgentToolbox;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

class NoteSaveTool implements ToolInterface
{
    public function __construct(
        protected AgentToolbox $toolbox
    ) {}

    public function definition(): array
    {
        return [
            'name' => 'note_save',
            'description' => 'Save research insights, findings, or notes to persistent agent memory for future reference',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'content' => [
                        'type' => 'string',
                        'description' => 'Content to save (insight, finding, or note)',
                    ],
                    'agent_name' => [
                        'type' => 'string',
                        'description' => 'Name of the agent saving the note (default: autonomous_agent)',
                        'default' => 'autonomous_agent',
                    ],
                    'namespace' => [
                        'type' => 'string',
                        'description' => 'Memory namespace for organization (default: insights)',
                        'default' => 'insights',
                    ],
                    'objective' => [
                        'type' => 'string',
                        'description' => 'Research objective this note relates to (optional)',
                    ],
                    'source' => [
                        'type' => 'string',
                        'description' => 'Source reference for this note (optional)',
                    ],
                ],
                'required' => ['content'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context, ?AgentMemory $memory = null): string
    {
        $content = $arguments['content'];
        $agentName = $arguments['agent_name'] ?? 'autonomous_agent';
        $options = [
            'namespace' => $arguments['namespace'] ?? 'insights',
            'objective' => $arguments['objective'] ?? null,
            'source' => $arguments['source'] ?? null,
            'metadata' => $arguments['metadata'] ?? [],
        ];

        try {
            $result = $this->toolbox->noteSave($agentName, $content, $options);

            return json_encode([
                'success' => $result['success'] ?? true,
                'id' => $result['id'] ?? null,
                'status' => $result['status'] ?? 'saved',
            ], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
