<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobNotification extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'job_id',
        'job_type',
        'job_name',
        'status',
        'stage',
        'error',
        'result',
        'metadata',
        'read',
        'read_at',
    ];

    protected $attributes = [
        'read' => false,
    ];

    protected $casts = [
        'result' => 'array',
        'metadata' => 'array',
        'read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead(): void
    {
        $this->update([
            'read' => true,
            'read_at' => now(),
        ]);
    }

    public function scopeUnread($query)
    {
        return $query->where('read', false);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
