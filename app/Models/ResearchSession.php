<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ResearchSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'name',
        'description',
        'viewed_nodes',
        'pinned_nodes',
        'expanded_nodes',
        'alerts',
        'root_node_id',
        'filter_settings',
        'last_activity_at',
    ];

    protected $casts = [
        'viewed_nodes' => 'array',
        'pinned_nodes' => 'array',
        'expanded_nodes' => 'array',
        'alerts' => 'array',
        'filter_settings' => 'array',
        'last_activity_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($session) {
            if (empty($session->uuid)) {
                $session->uuid = (string) Str::uuid();
            }
        });
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Query Scopes

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId)
            ->orderBy('last_activity_at', 'desc');
    }

    public function scopeActive($query)
    {
        return $query->orderBy('last_activity_at', 'desc');
    }

    public function scopeNamed($query)
    {
        return $query->whereNotNull('name');
    }

    // Helper Methods

    public function getDisplayName(): string
    {
        if ($this->name) {
            return $this->name;
        }

        return 'Session ' . $this->created_at->format('M d, Y H:i');
    }

    public function getViewedNodeIds(): array
    {
        return array_column($this->viewed_nodes ?? [], 'id');
    }

    public function getPinnedNodeIds(): array
    {
        return array_column($this->pinned_nodes ?? [], 'id');
    }

    public function getNodeCount(): int
    {
        return count($this->viewed_nodes ?? []);
    }
}
