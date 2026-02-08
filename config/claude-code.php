<?php

return [
    'binary' => env('CLAUDE_CODE_BINARY', '/usr/local/bin/claude'),
    'model' => env('CLAUDE_CODE_MODEL', 'claude-sonnet-4-5-20250929'),

    // Maximum time a single agent session can run
    'timeout' => env('CLAUDE_CODE_TIMEOUT', 600), // 10 minutes

    // Working directory for agent sessions
    'work_dir' => env('CLAUDE_CODE_WORK_DIR', storage_path('app/claude-agent')),

    // Output directory for agent results
    'output_dir' => env('CLAUDE_CODE_OUTPUT_DIR', storage_path('app/claude-agent/output')),

    // Max tokens for extended thinking
    'max_turns' => env('CLAUDE_CODE_MAX_TURNS', 25),

    // Allowed tools
    'allowed_tools' => ['bash', 'file_read', 'file_write'],

    // System prompt template for legal analysis
    'legal_analysis_prompt' => <<<'PROMPT'
You are a Croatian legal document analyst. You have access to case files on disk.

Your task: {TASK_DESCRIPTION}

Working directory: {WORK_DIR}
Output your results as JSON to: {OUTPUT_FILE}

Rules:
- All analysis must reference specific documents by filename
- Dates must be in ISO format (YYYY-MM-DD)
- Include confidence scores for every assertion
- Write Croatian descriptions, but JSON keys in English
- If you find contradictions, flag them with severity: high/medium/low
PROMPT,
];
