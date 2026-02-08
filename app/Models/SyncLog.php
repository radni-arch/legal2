<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    protected $fillable = ['court_id', 'register', 'year', 'last_case_number', 'total_fetched', 
                           'total_saved', 'total_errors', 'status', 'error_message', 'started_at', 
                           'completed_at', 'duration_seconds'];
    protected $casts = ['year' => 'integer', 'started_at' => 'datetime', 'completed_at' => 'datetime'];

    public function court(): BelongsTo { return $this->belongsTo(Court::class); }

    public function markAsRunning(): void { 
        $this->update(['status' => 'running', 'started_at' => now()]); 
    }
    
    public function markAsCompleted(): void { 
        $duration = $this->started_at ? max(0, (int) now()->diffInSeconds($this->started_at)) : null;
        $this->update([
            'status' => 'completed', 
            'completed_at' => now(), 
            'duration_seconds' => $duration
        ]); 
    }
    
    public function markAsFailed(string $msg): void { 
        $duration = $this->started_at ? max(0, (int) now()->diffInSeconds($this->started_at)) : null;
        $this->update([
            'status' => 'failed', 
            'error_message' => substr($msg, 0, 65000), // Truncate long error messages
            'completed_at' => now(),
            'duration_seconds' => $duration
        ]); 
    }
    
    public function updateLastCaseNumber(int $n): void { 
        $this->update(['last_case_number' => $n]); 
    }
}
