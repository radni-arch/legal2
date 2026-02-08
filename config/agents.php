<?php

/**
 * AI Agent Configuration
 *
 * This file configures the various AI CLI agents that can be used
 * for legal document analysis, research, and reasoning tasks.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Agent Driver
    |--------------------------------------------------------------------------
    |
    | The default agent driver to use when none is specified.
    |
    */
    'default' => env('AI_AGENT_DRIVER', 'claude'),

    /*
    |--------------------------------------------------------------------------
    | Fallback Chain
    |--------------------------------------------------------------------------
    |
    | When using runWithFallback(), agents will be tried in this order.
    | If the first fails or is unavailable, the next is tried.
    |
    */
    'fallback_chain' => explode(',', env('AI_AGENT_FALLBACK', 'claude,gemini')),

    /*
    |--------------------------------------------------------------------------
    | Shared Directories
    |--------------------------------------------------------------------------
    |
    | Default working and output directories for agent sessions.
    | Individual drivers can override these.
    |
    */
    'work_dir' => env('AI_AGENT_WORK_DIR', storage_path('app/agent-sessions')),
    'output_dir' => env('AI_AGENT_OUTPUT_DIR', storage_path('app/agent-output')),

    /*
    |--------------------------------------------------------------------------
    | Default Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum execution time in seconds for agent commands.
    |
    */
    'timeout' => env('AI_AGENT_TIMEOUT', 600),

    /*
    |--------------------------------------------------------------------------
    | Legal Analysis System Prompt
    |--------------------------------------------------------------------------
    |
    | Shared system prompt template for legal document analysis tasks.
    | Drivers can use this as a base and extend with driver-specific context.
    |
    */
    'legal_analysis_prompt' => <<<'PROMPT'
You are a legal document analyst AI assistant. Your role is to:

1. ANALYZE legal documents with precision and attention to detail
2. IDENTIFY key entities, dates, claims, and legal citations
3. EXTRACT structured information in JSON format
4. CROSS-REFERENCE related documents when multiple are provided
5. FLAG inconsistencies, contradictions, or gaps in evidence

When analyzing documents:
- Be thorough but concise in your findings
- Quote exact text when citing evidence
- Note page numbers and locations for all references
- Maintain objectivity - report what is present, not interpretations
- Structure output as valid JSON following the provided schema
PROMPT,

    /*
    |--------------------------------------------------------------------------
    | Agent Drivers
    |--------------------------------------------------------------------------
    |
    | Configuration for each available agent driver. Drivers with a
    | 'command_template' are registered as generic command-based agents.
    |
    */
    'drivers' => [

        /*
        |----------------------------------------------------------------------
        | Claude Code (Anthropic)
        |----------------------------------------------------------------------
        |
        | Claude CLI for document analysis and multi-turn interactions.
        | Requires: claude CLI installed via npm (npm install -g @anthropic-ai/claude-code)
        |
        */
        'claude' => [
            'binary' => env('CLAUDE_BINARY', 'claude'),
            'model' => env('CLAUDE_MODEL', 'claude-sonnet-4-20250514'),
            'api_key_env' => 'ANTHROPIC_API_KEY',
            'max_turns' => env('CLAUDE_MAX_TURNS', 1),
            'allowed_tools' => ['Read', 'Write', 'Bash', 'Glob', 'Grep'],
            'timeout' => env('CLAUDE_TIMEOUT', 600),
            'work_dir' => env('CLAUDE_WORK_DIR'),
            'output_dir' => env('CLAUDE_OUTPUT_DIR'),
            'system_prompt' => env('CLAUDE_SYSTEM_PROMPT', ''),
            'capabilities' => [
                'file_read',
                'file_write',
                'bash',
                'multi_turn',
                'json_output',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Gemini CLI (Google)
        |----------------------------------------------------------------------
        |
        | Google's Gemini CLI for document analysis.
        | Requires: gemini CLI installed
        |
        */
        'gemini' => [
            'binary' => env('GEMINI_BINARY', 'gemini'),
            'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
            'api_key_env' => 'GOOGLE_API_KEY',
            'sandbox' => env('GEMINI_SANDBOX', true),
            'timeout' => env('GEMINI_TIMEOUT', 600),
            'work_dir' => env('GEMINI_WORK_DIR'),
            'output_dir' => env('GEMINI_OUTPUT_DIR'),
            'capabilities' => [
                'file_read',
                'file_write',
                'bash',
                'web_search',
                'json_output',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Aider (AI Pair Programming)
        |----------------------------------------------------------------------
        |
        | Aider for code-focused tasks and file editing.
        | Requires: aider installed via pip (pip install aider-chat)
        |
        */
        'aider' => [
            'binary' => env('AIDER_BINARY', 'aider'),
            'model' => env('AIDER_MODEL', 'claude-3-sonnet'),
            'command_template' => '{binary} --model {model} --yes --message "{prompt}" {files}',
            'timeout' => env('AIDER_TIMEOUT', 600),
            'work_dir' => env('AIDER_WORK_DIR'),
            'output_dir' => env('AIDER_OUTPUT_DIR'),
            'capabilities' => [
                'file_read',
                'file_write',
                'multi_turn',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Amp (Sourcegraph Cody)
        |----------------------------------------------------------------------
        |
        | Sourcegraph's Amp CLI for codebase-aware analysis.
        | Requires: amp CLI installed
        |
        */
        'amp' => [
            'name' => 'Amp (Sourcegraph)',
            'binary' => env('AMP_BINARY', 'amp'),
            'command_template' => '{binary} chat --message "{prompt}" {files}',
            'timeout' => env('AMP_TIMEOUT', 600),
            'work_dir' => env('AMP_WORK_DIR'),
            'output_dir' => env('AMP_OUTPUT_DIR'),
            'capabilities' => [
                'file_read',
                'multi_turn',
            ],
            'env' => [
                'SRC_ACCESS_TOKEN' => env('SOURCEGRAPH_TOKEN'),
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Qwen (Alibaba)
        |----------------------------------------------------------------------
        |
        | Qwen CLI for multilingual document analysis.
        | Requires: qwen CLI installed
        |
        */
        'qwen' => [
            'name' => 'Qwen CLI',
            'binary' => env('QWEN_BINARY', 'qwen'),
            'command_template' => '{binary} --prompt "{prompt}" --files {files}',
            'timeout' => env('QWEN_TIMEOUT', 600),
            'work_dir' => env('QWEN_WORK_DIR'),
            'output_dir' => env('QWEN_OUTPUT_DIR'),
            'capabilities' => [
                'file_read',
                'json_output',
            ],
            'env' => [
                'DASHSCOPE_API_KEY' => env('QWEN_API_KEY'),
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Analysis Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for case analysis operations.
    |
    */
    'analysis' => [
        // Default phases for case analysis
        'phases' => [
            'document_review' => 'Review each document individually and extract key facts, dates, parties involved.',
            'cross_reference' => 'Cross-reference information across all documents to identify connections and contradictions.',
            'synthesis' => 'Synthesize findings into a comprehensive case analysis with recommendations.',
        ],

        // Output directory for analysis results
        'output_dir' => storage_path('app/case-analysis'),
    ],
];
