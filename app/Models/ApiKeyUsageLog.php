<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKeyUsageLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'api_key_id', 'model_used', 'endpoint', 'task_type',
        'prompt_tokens', 'completion_tokens', 'total_tokens',
        'http_status', 'response_time_ms', 'was_successful',
        'was_rate_limited', 'was_fallback', 'rate_limit_headers',
        'error_code', 'error_message', 'document_id', 'batch_id',
    ];

    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'http_status' => 'integer',
        'response_time_ms' => 'integer',
        'was_successful' => 'boolean',
        'was_rate_limited' => 'boolean',
        'was_fallback' => 'boolean',
        'rate_limit_headers' => 'array',
        'created_at' => 'datetime',
    ];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }
}
