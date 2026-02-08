<?php

namespace App\Services\Agent\Contracts;

enum AgentCapability: string
{
    case FILE_READ = 'file_read';       // Can read files from disk
    case FILE_WRITE = 'file_write';     // Can write output files
    case BASH = 'bash';                 // Can execute bash commands
    case WEB_SEARCH = 'web_search';     // Can search the web
    case MULTI_TURN = 'multi_turn';     // Supports multi-turn conversation
    case EXTENDED_THINKING = 'extended_thinking'; // Extended reasoning mode
    case STREAMING = 'streaming';        // Streams output
    case JSON_OUTPUT = 'json_output';    // Can output structured JSON
}
